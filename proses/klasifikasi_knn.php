<?php
require '../db_config.php';

$message = '';
$messageType = 'success';

function getPdoConnection(): PDO
{
    global $host, $port, $dbname, $user, $password;

    return new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function ensureKlasifikasiTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS klasifikasi_kelompok_knn (
            id_klasifikasi INT AUTO_INCREMENT PRIMARY KEY,
            id_preprocessing INT NOT NULL,
            label_aktual VARCHAR(50) NOT NULL,
            label_prediksi VARCHAR(50) NOT NULL,
            k_value INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_id_preprocessing (id_preprocessing)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function vectorNorm(array $vector): float
{
    $sumOfSquares = 0.0;
    foreach ($vector as $weight) {
        $sumOfSquares += $weight * $weight;
    }

    return sqrt($sumOfSquares);
}

// Cosine similarity antara 2 vector TF-IDF (bentuk sparse: term => bobot).
// Hasil 0 kalau salah satu vector kosong (dokumen tanpa term tersisa setelah preprocessing).
function cosineSimilarity(array $vectorA, array $vectorB, float $normA, float $normB): float
{
    if ($normA <= 0.0 || $normB <= 0.0) {
        return 0.0;
    }

    // Iterasi di map yang lebih kecil supaya tetap efisien walau datanya sparse.
    if (count($vectorA) > count($vectorB)) {
        [$vectorA, $vectorB] = [$vectorB, $vectorA];
    }

    $dotProduct = 0.0;
    foreach ($vectorA as $term => $weight) {
        if (isset($vectorB[$term])) {
            $dotProduct += $weight * $vectorB[$term];
        }
    }

    return $dotProduct / ($normA * $normB);
}

function labelBadgeClass(string $label): string
{
    return match ($label) {
        'positif' => 'bg-green-100 text-green-700',
        'negatif' => 'bg-red-100 text-red-700',
        default => 'bg-slate-100 text-slate-700',
    };
}

try {
    $pdo = getPdoConnection();
    ensureKlasifikasiTable($pdo);

    $kValue = 5;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['clear_data'])) {
            $pdo->exec('TRUNCATE TABLE klasifikasi_kelompok_knn');
            $message = 'Data hasil klasifikasi berhasil dikosongkan.';
        } elseif (isset($_POST['jalankan_knn'])) {
            $kValue = (int) ($_POST['k_value'] ?? 5);
            if ($kValue < 1) {
                $kValue = 1;
            }
            if ($kValue > 25) {
                $kValue = 25;
            }

            $totalTfidf = (int) $pdo->query('SELECT COUNT(*) FROM tfidf_kelompok_knn')->fetchColumn();
            $totalSplit = (int) $pdo->query('SELECT COUNT(*) FROM split_data_kelompok_knn')->fetchColumn();

            if ($totalSplit === 0) {
                $message = 'Belum ada data split. Lakukan Split Data terlebih dahulu.';
                $messageType = 'error';
            } elseif ($totalTfidf === 0) {
                $message = 'Belum ada data TF-IDF. Hitung Pembobotan TF-IDF terlebih dahulu.';
                $messageType = 'error';
            } else {
                // Muat semua vector TF-IDF sekaligus (hindari query per dokumen / N+1).
                $vectorRows = $pdo->query('SELECT id_preprocessing, term, bobot FROM tfidf_kelompok_knn')->fetchAll();
                $vectors = [];
                foreach ($vectorRows as $row) {
                    $vectors[(int) $row['id_preprocessing']][$row['term']] = (float) $row['bobot'];
                }

                $splitRows = $pdo->query('SELECT id_preprocessing, label_sentimen, tipe_data FROM split_data_kelompok_knn')->fetchAll();
                $trainingIds = [];
                $testingIds = [];
                $labelById = [];
                foreach ($splitRows as $row) {
                    $id = (int) $row['id_preprocessing'];
                    $labelById[$id] = $row['label_sentimen'];
                    if ($row['tipe_data'] === 'training') {
                        $trainingIds[] = $id;
                    } else {
                        $testingIds[] = $id;
                    }
                }

                if ($kValue > count($trainingIds)) {
                    $kValue = count($trainingIds);
                }

                // Pre-hitung norm tiap dokumen training sekali saja (dipakai berulang di loop testing).
                $trainingNorms = [];
                foreach ($trainingIds as $id) {
                    $trainingNorms[$id] = vectorNorm($vectors[$id] ?? []);
                }

                $predictions = [];
                foreach ($testingIds as $testId) {
                    $testVector = $vectors[$testId] ?? [];
                    $testNorm = vectorNorm($testVector);

                    $similarities = [];
                    foreach ($trainingIds as $trainId) {
                        $similarities[$trainId] = cosineSimilarity(
                            $testVector,
                            $vectors[$trainId] ?? [],
                            $testNorm,
                            $trainingNorms[$trainId]
                        );
                    }

                    arsort($similarities);
                    $topK = array_slice($similarities, 0, $kValue, true);

                    $voteCount = [];
                    $voteWeight = [];
                    foreach ($topK as $trainId => $similarity) {
                        $label = $labelById[$trainId];
                        $voteCount[$label] = ($voteCount[$label] ?? 0) + 1;
                        $voteWeight[$label] = ($voteWeight[$label] ?? 0.0) + $similarity;
                    }

                    arsort($voteCount);
                    $maxVotes = reset($voteCount);
                    $candidates = array_keys(array_filter($voteCount, fn ($v) => $v === $maxVotes));

                    if (count($candidates) === 1) {
                        $predictedLabel = $candidates[0];
                    } else {
                        // Kalau voting seri: menang label dgn total cosine similarity tertinggi (deterministik).
                        $bestWeight = -1.0;
                        $predictedLabel = $candidates[0];
                        foreach ($candidates as $label) {
                            if ($voteWeight[$label] > $bestWeight) {
                                $bestWeight = $voteWeight[$label];
                                $predictedLabel = $label;
                            }
                        }
                    }

                    $predictions[$testId] = $predictedLabel;
                }

                $pdo->exec('TRUNCATE TABLE klasifikasi_kelompok_knn');

                $stmt = $pdo->prepare("
                    INSERT INTO klasifikasi_kelompok_knn (id_preprocessing, label_aktual, label_prediksi, k_value)
                    VALUES (:id_preprocessing, :label_aktual, :label_prediksi, :k_value)
                ");

                $pdo->beginTransaction();
                foreach ($predictions as $testId => $predictedLabel) {
                    $stmt->execute([
                        ':id_preprocessing' => $testId,
                        ':label_aktual' => $labelById[$testId],
                        ':label_prediksi' => $predictedLabel,
                        ':k_value' => $kValue,
                    ]);
                }
                $pdo->commit();

                $message = 'Klasifikasi KNN selesai untuk ' . count($predictions) . ' data testing dengan K=' . $kValue . '.';
            }
        }
    }

    $totalKlasifikasi = (int) $pdo->query('SELECT COUNT(*) FROM klasifikasi_kelompok_knn')->fetchColumn();
    $totalBenar = (int) $pdo->query('SELECT COUNT(*) FROM klasifikasi_kelompok_knn WHERE label_aktual = label_prediksi')->fetchColumn();
    $totalSalah = $totalKlasifikasi - $totalBenar;
    $akurasi = $totalKlasifikasi > 0 ? round(($totalBenar / $totalKlasifikasi) * 100, 2) : 0;

    $kValueTerakhir = (int) ($pdo->query('SELECT k_value FROM klasifikasi_kelompok_knn LIMIT 1')->fetchColumn() ?: $kValue);

    $detailKlasifikasi = $pdo->query("
        SELECT k.id_preprocessing, p.teks_asli, k.label_aktual, k.label_prediksi
        FROM klasifikasi_kelompok_knn k
        INNER JOIN preprocessing_kelompok_knn p ON p.id_preprocessing = k.id_preprocessing
        ORDER BY k.id_klasifikasi ASC
        LIMIT 200
    ")->fetchAll();
} catch (Throwable $e) {
    $message = 'Terjadi kesalahan: ' . $e->getMessage();
    $messageType = 'error';
    $totalKlasifikasi = 0;
    $totalBenar = 0;
    $totalSalah = 0;
    $akurasi = 0;
    $kValueTerakhir = $kValue ?? 5;
    $detailKlasifikasi = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Klasifikasi KNN</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-slate-900 text-white">
            <div class="p-6 border-b border-slate-700">
                <h1 class="text-xl font-bold">UAS analisis sentimen</h1>
            </div>

            <nav class="p-4 space-y-2">
                <a href="../formload.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Dashboard
                </a>

                <a href="preprocessing_kelompok4.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Preprocessing
                </a>

                <a href="tf-idf.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Pembobotan TF-IDF
                </a>

                <a href="split-data.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Split Data
                </a>

                <a href="klasifikasi_knn.php" class="block px-4 py-3 rounded-lg bg-blue-600 font-medium">
                    Klasifikasi KNN
                </a>

                <a href="hasil_analisis.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Hasil Analisis
                </a>

                <a href="laporan.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Laporan
                </a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <section class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Klasifikasi</p>
                        <h2 class="text-2xl font-bold text-slate-900 mt-1">Klasifikasi KNN (Cosine Similarity)</h2>
                        <p class="text-slate-500 mt-2">Memprediksi label sentimen data testing berdasarkan K tetangga terdekat dari data training.</p>
                    </div>

                    <form method="POST" class="flex items-center gap-3">
                        <div>
                            <label for="k_value" class="block text-xs font-semibold text-slate-500 mb-1">Jumlah Tetangga (K)</label>
                            <input
                                id="k_value"
                                name="k_value"
                                type="number"
                                min="1"
                                max="25"
                                step="1"
                                value="<?php echo (int) $kValueTerakhir; ?>"
                                class="w-24 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100"
                            >
                        </div>
                        <button type="submit" name="jalankan_knn" value="1" class="rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-700 self-end">
                            Jalankan Klasifikasi
                        </button>
                    </form>
                </div>
            </section>

            <?php if ($message !== ''): ?>
                <div class="mb-6 rounded-xl px-5 py-4 <?php echo $messageType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="mb-6 rounded-lg bg-blue-50 text-blue-700 text-xs px-4 py-3">
                <strong>Cara kerja:</strong> Tiap data testing dibandingkan (cosine similarity) ke seluruh data training memakai vector TF-IDF.
                K tetangga dengan similarity tertinggi "voting" &mdash; label terbanyak di antara K tetangga itu jadi label prediksi.
                Jika hasil voting seri, dimenangkan oleh label dengan total similarity tertinggi.
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Total Data Diuji</p>
                    <h3 class="text-3xl font-bold text-blue-600 mt-2"><?php echo number_format($totalKlasifikasi); ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Data testing yang diklasifikasi</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Prediksi Benar</p>
                    <h3 class="text-3xl font-bold text-green-600 mt-2"><?php echo number_format($totalBenar); ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Label prediksi = label aktual</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Prediksi Salah</p>
                    <h3 class="text-3xl font-bold text-red-500 mt-2"><?php echo number_format($totalSalah); ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Label prediksi &ne; label aktual</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Akurasi</p>
                    <h3 class="text-3xl font-bold text-purple-600 mt-2"><?php echo $akurasi; ?>%</h3>
                    <p class="text-xs text-slate-400 mt-2">K = <?php echo (int) $kValueTerakhir; ?></p>
                </div>
            </div>

            <section class="bg-white rounded-2xl shadow-sm p-6">
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">Detail Hasil Klasifikasi</h3>
                        <p class="text-sm text-slate-500 mt-1">Menampilkan maksimal 200 data testing.</p>
                    </div>

                    <form method="POST" onsubmit="return confirm('Kosongkan semua hasil klasifikasi?')">
                        <button type="submit" name="clear_data" value="1" class="px-4 py-2 rounded-lg text-sm font-medium border border-red-200 text-red-600 hover:bg-red-50">
                            Kosongkan
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">Teks Asli</th>
                                <th class="px-4 py-3">Label Aktual</th>
                                <th class="px-4 py-3">Label Prediksi</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php if (count($detailKlasifikasi) === 0): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-slate-500">Belum ada hasil klasifikasi. Jalankan klasifikasi terlebih dahulu.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($detailKlasifikasi as $index => $row): ?>
                                    <?php $benar = $row['label_aktual'] === $row['label_prediksi']; ?>
                                    <tr class="align-top hover:bg-slate-50">
                                        <td class="px-4 py-4 font-semibold text-slate-500"><?php echo $index + 1; ?></td>
                                        <td class="px-4 py-4 max-w-md text-slate-700"><?php echo htmlspecialchars($row['teks_asli'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium capitalize <?php echo labelBadgeClass($row['label_aktual']); ?>">
                                                <?php echo htmlspecialchars($row['label_aktual'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium capitalize <?php echo labelBadgeClass($row['label_prediksi']); ?>">
                                                <?php echo htmlspecialchars($row['label_prediksi'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4">
                                            <?php if ($benar): ?>
                                                <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold">Benar</span>
                                            <?php else: ?>
                                                <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-semibold">Salah</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="text-center text-sm text-slate-400 mt-8">
                &copy; <?php echo date('Y'); ?> Sistem Analisis Sentimen - Algoritma KNN
            </div>
        </main>
    </div>
</body>
</html>
