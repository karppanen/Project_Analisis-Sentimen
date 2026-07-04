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

function ensureTfidfTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tfidf_kelompok_knn (
            id_tfidf INT AUTO_INCREMENT PRIMARY KEY,
            id_preprocessing INT NOT NULL,
            term VARCHAR(150) NOT NULL,
            tf INT NOT NULL DEFAULT 0,
            df INT NOT NULL DEFAULT 0,
            idf DOUBLE NOT NULL DEFAULT 0,
            bobot DOUBLE NOT NULL DEFAULT 0,
            KEY idx_id_preprocessing (id_preprocessing),
            KEY idx_term (term)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function tokenizeDocument(string $text): array
{
    $text = trim($text);

    if ($text === '') {
        return [];
    }

    return preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

try {
    $pdo = getPdoConnection();
    ensureTfidfTable($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['clear_data'])) {
            $pdo->exec('TRUNCATE TABLE tfidf_kelompok_knn');
            $message = 'Data TF-IDF berhasil dikosongkan.';
        } elseif (isset($_POST['hitung_tfidf'])) {
            $documents = $pdo->query("
                SELECT id_preprocessing, spelling_correction
                FROM preprocessing_kelompok_knn
                WHERE spelling_correction IS NOT NULL AND spelling_correction <> ''
            ")->fetchAll();

            if (count($documents) === 0) {
                $message = 'Belum ada data preprocessing. Lakukan preprocessing dataset terlebih dahulu.';
                $messageType = 'error';
            } else {
                $totalDocuments = count($documents);
                $documentTerms = [];
                $documentFrequency = [];

                foreach ($documents as $document) {
                    $termCounts = array_count_values(tokenizeDocument((string) $document['spelling_correction']));
                    $documentTerms[(int) $document['id_preprocessing']] = $termCounts;

                    foreach (array_keys($termCounts) as $term) {
                        $documentFrequency[$term] = ($documentFrequency[$term] ?? 0) + 1;
                    }
                }

                $idfByTerm = [];
                foreach ($documentFrequency as $term => $df) {
                    $idfByTerm[$term] = log10($totalDocuments / $df);
                }

                $pdo->exec('TRUNCATE TABLE tfidf_kelompok_knn');

                $stmt = $pdo->prepare("
                    INSERT INTO tfidf_kelompok_knn (id_preprocessing, term, tf, df, idf, bobot)
                    VALUES (:id_preprocessing, :term, :tf, :df, :idf, :bobot)
                ");

                $pdo->beginTransaction();

                foreach ($documentTerms as $idPreprocessing => $termCounts) {
                    foreach ($termCounts as $term => $tf) {
                        $df = $documentFrequency[$term];
                        $idf = $idfByTerm[$term];

                        $stmt->execute([
                            ':id_preprocessing' => $idPreprocessing,
                            ':term' => $term,
                            ':tf' => $tf,
                            ':df' => $df,
                            ':idf' => $idf,
                            ':bobot' => $tf * $idf,
                        ]);
                    }
                }

                $pdo->commit();

                $message = 'Perhitungan TF-IDF selesai untuk ' . $totalDocuments . ' dokumen dan '
                    . count($documentFrequency) . ' term unik.';
            }
        }
    }

    $totalDokumen = (int) $pdo->query('SELECT COUNT(*) FROM preprocessing_kelompok_knn')->fetchColumn();
    $totalTermUnik = (int) $pdo->query('SELECT COUNT(DISTINCT term) FROM tfidf_kelompok_knn')->fetchColumn();
    $totalBobot = (int) $pdo->query('SELECT COUNT(*) FROM tfidf_kelompok_knn')->fetchColumn();

    $ringkasanTerm = $pdo->query("
        SELECT term, MAX(df) AS df, MAX(idf) AS idf
        FROM tfidf_kelompok_knn
        GROUP BY term
        ORDER BY idf DESC, term ASC
        LIMIT 30
    ")->fetchAll();

    $detailTfidf = $pdo->query("
        SELECT t.id_preprocessing, p.teks_asli, t.term, t.tf, t.df, t.idf, t.bobot
        FROM tfidf_kelompok_knn t
        INNER JOIN preprocessing_kelompok_knn p ON p.id_preprocessing = t.id_preprocessing
        ORDER BY t.id_preprocessing ASC, t.bobot DESC
        LIMIT 100
    ")->fetchAll();
} catch (Throwable $e) {
    $message = 'Terjadi kesalahan: ' . $e->getMessage();
    $messageType = 'error';
    $totalDokumen = 0;
    $totalTermUnik = 0;
    $totalBobot = 0;
    $ringkasanTerm = [];
    $detailTfidf = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembobotan TF-IDF</title>
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

                <a href="tf-idf.php" class="block px-4 py-3 rounded-lg bg-blue-600 font-medium">
                    Pembobotan TF-IDF
                </a>

                <a href="split-data.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Split Data
                </a>

                <a href="klasifikasi_knn.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
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
                        <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Pembobotan Term</p>
                        <h2 class="text-2xl font-bold text-slate-900 mt-1">Pembobotan TF-IDF</h2>
                        <p class="text-slate-500 mt-2">Menghitung Term Frequency &ndash; Inverse Document Frequency dari hasil preprocessing.</p>
                    </div>
                    <form method="POST">
                        <button type="submit" name="hitung_tfidf" value="1" class="rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-700">
                            Hitung Ulang TF-IDF
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
                <strong>Cara baca IDF & Bobot:</strong> <em>TF</em> = berapa kali term muncul di 1 dokumen.
                <em>DF</em> = di berapa dokumen (dari total semua dokumen) term itu muncul.
                <em>IDF</em> = log10(total dokumen &divide; DF) &mdash; semakin tinggi nilainya, semakin
                <strong>jarang/khas</strong> term tersebut (muncul di sedikit dokumen), <u>bukan</u> berarti sentimennya
                semakin kuat. Term yang muncul di hampir semua dokumen IDF-nya mendekati 0.
                Contoh: term yang cuma muncul di 1 dari 1042 dokumen &rarr; IDF = log10(1042/1) &asymp; 3.0179.
                <em>Bobot</em> = TF &times; IDF, inilah nilai akhir yang dipakai pada tahap Klasifikasi KNN.
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Total Dokumen</p>
                    <h3 class="text-3xl font-bold text-blue-600 mt-2"><?php echo number_format($totalDokumen); ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Data hasil preprocessing</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Total Term Unik</p>
                    <h3 class="text-3xl font-bold text-green-600 mt-2"><?php echo number_format($totalTermUnik); ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Kata unik pada seluruh dokumen</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Total Bobot Dihitung</p>
                    <h3 class="text-3xl font-bold text-purple-600 mt-2"><?php echo number_format($totalBobot); ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Pasangan dokumen &amp; term</p>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
                <section class="bg-white rounded-2xl shadow-sm p-6 xl:col-span-2">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-slate-900">Ringkasan IDF per Term</h3>
                            <p class="text-sm text-slate-500 mt-1">30 term dengan IDF tertinggi (paling jarang muncul).</p>
                        </div>
                        <form method="POST" onsubmit="return confirm('Kosongkan semua data TF-IDF?')">
                            <button type="submit" name="clear_data" value="1" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">
                                Kosongkan
                            </button>
                        </form>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-slate-200 max-h-[520px] overflow-y-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3">Term</th>
                                    <th class="px-4 py-3 text-center">DF</th>
                                    <th class="px-4 py-3 text-right">IDF</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <?php if (count($ringkasanTerm) === 0): ?>
                                    <tr>
                                        <td colspan="3" class="px-4 py-10 text-center text-slate-500">Belum ada data TF-IDF. Klik "Hitung Ulang TF-IDF".</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ringkasanTerm as $row): ?>
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-3 font-medium text-slate-700"><?php echo htmlspecialchars($row['term'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-4 py-3 text-center text-slate-500"><?php echo (int) $row['df']; ?></td>
                                            <td class="px-4 py-3 text-right font-semibold text-blue-600"><?php echo number_format((float) $row['idf'], 4); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="bg-white rounded-2xl shadow-sm p-6 xl:col-span-3">
                    <div class="mb-4">
                        <h3 class="text-xl font-bold text-slate-900">Detail Bobot TF-IDF</h3>
                        <p class="text-sm text-slate-500 mt-1">Menampilkan maksimal 100 baris, diurutkan per dokumen dan bobot tertinggi.</p>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-slate-200 max-h-[520px] overflow-y-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3">Dokumen</th>
                                    <th class="px-4 py-3">Term</th>
                                    <th class="px-4 py-3 text-center">TF</th>
                                    <th class="px-4 py-3 text-center">DF</th>
                                    <th class="px-4 py-3 text-right">IDF</th>
                                    <th class="px-4 py-3 text-right">Bobot</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <?php if (count($detailTfidf) === 0): ?>
                                    <tr>
                                        <td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada data TF-IDF. Klik "Hitung Ulang TF-IDF".</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($detailTfidf as $row): ?>
                                        <tr class="align-top hover:bg-slate-50">
                                            <td class="px-4 py-3 max-w-[220px] text-slate-600">
                                                <span class="block text-xs font-semibold text-slate-400">#<?php echo (int) $row['id_preprocessing']; ?></span>
                                                <span class="line-clamp-2"><?php echo htmlspecialchars($row['teks_asli'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td class="px-4 py-3 font-medium text-slate-700"><?php echo htmlspecialchars($row['term'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-4 py-3 text-center text-slate-500"><?php echo (int) $row['tf']; ?></td>
                                            <td class="px-4 py-3 text-center text-slate-500"><?php echo (int) $row['df']; ?></td>
                                            <td class="px-4 py-3 text-right text-slate-500"><?php echo number_format((float) $row['idf'], 4); ?></td>
                                            <td class="px-4 py-3 text-right font-semibold text-purple-600"><?php echo number_format((float) $row['bobot'], 4); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="text-center text-sm text-slate-400 mt-8">
                &copy; <?php echo date('Y'); ?> Sistem Analisis Sentimen - Algoritma KNN
            </div>
        </main>
    </div>
</body>
</html>
