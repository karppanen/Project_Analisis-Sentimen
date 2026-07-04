<?php
require '../db_config.php';

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

function ensureSplitTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS split_data_kelompok_knn (
            id_split INT AUTO_INCREMENT PRIMARY KEY,
            id_preprocessing INT NOT NULL,
            label_sentimen VARCHAR(50) NOT NULL,
            tipe_data ENUM('training', 'testing') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_id_preprocessing (id_preprocessing),
            KEY idx_tipe_data (tipe_data)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
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

$message = '';
$messageType = 'success';
$labels = ['positif', 'negatif', 'netral'];
$matrix = array_fill_keys($labels, array_fill_keys($labels, 0));

$totalDataset = 0;
$labelDistribusi = [];
$totalTermUnik = 0;
$totalDokumenTfidf = 0;
$totalPasanganTfidf = 0;
$totalTraining = 0;
$totalTesting = 0;
$rasioTraining = 0;
$rasioTesting = 0;
$kValueDigunakan = 0;
$totalKlasifikasi = 0;
$diagonalSum = 0;
$accuracy = 0;

try {
    $pdo = getPdoConnection();
    ensureTfidfTable($pdo);
    ensureSplitTable($pdo);
    ensureKlasifikasiTable($pdo);

    // (a) Ringkasan dataset & distribusi label
    $totalDataset = (int) $pdo->query('SELECT COUNT(*) FROM preprocessing_kelompok_knn')->fetchColumn();
    $labelDistribusi = $pdo->query("
        SELECT label_sentimen, COUNT(*) AS total
        FROM preprocessing_kelompok_knn
        GROUP BY label_sentimen
        ORDER BY label_sentimen ASC
    ")->fetchAll();

    // (b) TF-IDF
    $totalTermUnik = (int) $pdo->query('SELECT COUNT(DISTINCT term) FROM tfidf_kelompok_knn')->fetchColumn();
    $totalDokumenTfidf = (int) $pdo->query('SELECT COUNT(DISTINCT id_preprocessing) FROM tfidf_kelompok_knn')->fetchColumn();
    $totalPasanganTfidf = (int) $pdo->query('SELECT COUNT(*) FROM tfidf_kelompok_knn')->fetchColumn();

    // (c) Split data
    $totalTraining = (int) $pdo->query("SELECT COUNT(*) FROM split_data_kelompok_knn WHERE tipe_data = 'training'")->fetchColumn();
    $totalTesting = (int) $pdo->query("SELECT COUNT(*) FROM split_data_kelompok_knn WHERE tipe_data = 'testing'")->fetchColumn();
    $totalSplitKeseluruhan = $totalTraining + $totalTesting;
    $rasioTraining = $totalSplitKeseluruhan > 0 ? round($totalTraining / $totalSplitKeseluruhan * 100) : 0;
    $rasioTesting = $totalSplitKeseluruhan > 0 ? 100 - $rasioTraining : 0;

    // (d) Klasifikasi KNN + confusion matrix
    $kValueDigunakan = (int) ($pdo->query('SELECT k_value FROM klasifikasi_kelompok_knn LIMIT 1')->fetchColumn() ?: 0);

    $rows = $pdo->query('SELECT label_aktual, label_prediksi FROM klasifikasi_kelompok_knn')->fetchAll();
    foreach ($rows as $row) {
        if (isset($matrix[$row['label_aktual']][$row['label_prediksi']])) {
            $matrix[$row['label_aktual']][$row['label_prediksi']]++;
        }
    }

    $totalKlasifikasi = array_sum(array_map('array_sum', $matrix));
    foreach ($labels as $label) {
        $diagonalSum += $matrix[$label][$label];
    }
    $accuracy = $totalKlasifikasi > 0 ? $diagonalSum / $totalKlasifikasi : 0;
} catch (Throwable $e) {
    $message = 'Terjadi kesalahan: ' . $e->getMessage();
    $messageType = 'error';
}

// --- Download CSV (harus dijalankan sebelum ada output HTML apa pun) ---
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan_analisis_sentimen.csv"');
    $output = fopen('php://output', 'w');

    fputcsv($output, ['LAPORAN ANALISIS SENTIMEN - ALGORITMA KNN']);
    fputcsv($output, ['Tanggal Export', date('Y-m-d H:i:s')]);
    fputcsv($output, []);

    fputcsv($output, ['A. Ringkasan Dataset']);
    fputcsv($output, ['Total Data', $totalDataset]);
    fputcsv($output, ['Label Sentimen', 'Jumlah']);
    foreach ($labelDistribusi as $row) {
        fputcsv($output, [$row['label_sentimen'], $row['total']]);
    }
    fputcsv($output, []);

    fputcsv($output, ['B. Ringkasan Preprocessing']);
    fputcsv($output, ['Total Data Diproses', $totalDataset]);
    fputcsv($output, []);

    fputcsv($output, ['C. Ringkasan TF-IDF']);
    fputcsv($output, ['Total Dokumen', $totalDokumenTfidf]);
    fputcsv($output, ['Total Term Unik', $totalTermUnik]);
    fputcsv($output, ['Total Pasangan Dokumen & Term', $totalPasanganTfidf]);
    fputcsv($output, []);

    fputcsv($output, ['D. Ringkasan Split Data']);
    fputcsv($output, ['Data Training', $totalTraining, $rasioTraining . '%']);
    fputcsv($output, ['Data Testing', $totalTesting, $rasioTesting . '%']);
    fputcsv($output, []);

    fputcsv($output, ['E. Hasil Klasifikasi KNN']);
    fputcsv($output, ['K digunakan', $kValueDigunakan]);
    fputcsv($output, ['Total Data Diuji', $totalKlasifikasi]);
    fputcsv($output, ['Prediksi Benar', $diagonalSum]);
    fputcsv($output, ['Prediksi Salah', $totalKlasifikasi - $diagonalSum]);
    fputcsv($output, ['Akurasi', number_format($accuracy * 100, 2) . '%']);
    fputcsv($output, []);
    fputcsv($output, ['Confusion Matrix']);
    fputcsv($output, array_merge(['Aktual \\ Prediksi'], $labels));
    foreach ($labels as $actual) {
        $row = [$actual];
        foreach ($labels as $predicted) {
            $row[] = $matrix[$actual][$predicted];
        }
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan</title>
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

                <a href="klasifikasi_knn.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Klasifikasi KNN
                </a>

                <a href="hasil_analisis.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Hasil Analisis
                </a>

                <a href="laporan.php" class="block px-4 py-3 rounded-lg bg-blue-600 font-medium">
                    Laporan
                </a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <section class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Ringkasan Akhir</p>
                        <h2 class="text-2xl font-bold text-slate-900 mt-1">Laporan Analisis Sentimen</h2>
                        <p class="text-slate-500 mt-2">Rangkuman seluruh tahapan &ndash; dataset, preprocessing, TF-IDF, split data, hingga hasil klasifikasi KNN.</p>
                    </div>
                    <a href="?download=csv" class="rounded-lg bg-green-600 px-5 py-3 text-sm font-semibold text-white hover:bg-green-700 inline-flex items-center gap-2">
                        Download Laporan Excel (CSV)
                    </a>
                </div>
            </section>

            <?php if ($message !== ''): ?>
                <div class="mb-6 rounded-xl px-5 py-4 <?php echo $messageType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Total Dataset</p>
                    <h3 class="text-3xl font-bold text-blue-600 mt-2"><?php echo number_format($totalDataset); ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Data hasil preprocessing</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Rasio Split</p>
                    <h3 class="text-3xl font-bold text-purple-600 mt-2"><?php echo (int) $rasioTraining; ?>:<?php echo (int) $rasioTesting; ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Training : Testing</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Nilai K (KNN)</p>
                    <h3 class="text-3xl font-bold text-slate-700 mt-2"><?php echo (int) $kValueDigunakan; ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Jumlah tetangga terdekat</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Akurasi KNN</p>
                    <h3 class="text-3xl font-bold text-green-600 mt-2"><?php echo number_format($accuracy * 100, 2); ?>%</h3>
                    <p class="text-xs text-slate-400 mt-2"><?php echo number_format($diagonalSum); ?> / <?php echo number_format($totalKlasifikasi); ?> benar</p>
                </div>
            </div>

            <section class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <h3 class="text-xl font-bold text-slate-900 mb-4">A. Ringkasan Dataset</h3>
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Label Sentimen</th>
                                <th class="px-4 py-3 text-center">Jumlah Data</th>
                                <th class="px-4 py-3 text-right">Persentase</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php if (count($labelDistribusi) === 0): ?>
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-slate-500">Belum ada data preprocessing.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($labelDistribusi as $row): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 font-semibold text-slate-700 capitalize"><?php echo htmlspecialchars($row['label_sentimen'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-3 text-center text-slate-500"><?php echo (int) $row['total']; ?></td>
                                        <td class="px-4 py-3 text-right text-slate-500"><?php echo $totalDataset > 0 ? number_format($row['total'] / $totalDataset * 100, 1) : 0; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <h3 class="text-xl font-bold text-slate-900 mb-4">B. Ringkasan Preprocessing</h3>
                <p class="text-sm text-slate-600">
                    Total <span class="font-semibold"><?php echo number_format($totalDataset); ?></span> data telah melalui tahap preprocessing
                    (case folding, punctuation removal, stopwords removal, tokenisasi, stemming, normalisasi, dan spelling correction)
                    sebelum masuk ke tahap pembobotan TF-IDF.
                </p>
            </section>

            <section class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <h3 class="text-xl font-bold text-slate-900 mb-4">C. Ringkasan TF-IDF</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-xs text-slate-500">Total Dokumen</p>
                        <p class="text-xl font-bold text-slate-800 mt-1"><?php echo number_format($totalDokumenTfidf); ?></p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-xs text-slate-500">Total Term Unik</p>
                        <p class="text-xl font-bold text-slate-800 mt-1"><?php echo number_format($totalTermUnik); ?></p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-xs text-slate-500">Total Pasangan Dokumen &amp; Term</p>
                        <p class="text-xl font-bold text-slate-800 mt-1"><?php echo number_format($totalPasanganTfidf); ?></p>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <h3 class="text-xl font-bold text-slate-900 mb-4">D. Ringkasan Split Data</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-xs text-slate-500">Data Training</p>
                        <p class="text-xl font-bold text-green-600 mt-1"><?php echo number_format($totalTraining); ?> <span class="text-sm font-normal text-slate-400">(<?php echo (int) $rasioTraining; ?>%)</span></p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-xs text-slate-500">Data Testing</p>
                        <p class="text-xl font-bold text-orange-500 mt-1"><?php echo number_format($totalTesting); ?> <span class="text-sm font-normal text-slate-400">(<?php echo (int) $rasioTesting; ?>%)</span></p>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-2xl shadow-sm p-6">
                <h3 class="text-xl font-bold text-slate-900 mb-1">E. Hasil Klasifikasi KNN</h3>
                <p class="text-sm text-slate-500 mb-4">K = <?php echo (int) $kValueDigunakan; ?>, akurasi <?php echo number_format($accuracy * 100, 2); ?>%.</p>

                <div class="overflow-x-auto rounded-xl border border-slate-200 max-w-xl">
                    <table class="w-full text-center text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Aktual &darr; / Prediksi &rarr;</th>
                                <?php foreach ($labels as $label): ?>
                                    <th class="px-4 py-3 capitalize"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php foreach ($labels as $actual): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-left font-semibold text-slate-700 capitalize"><?php echo htmlspecialchars($actual, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <?php foreach ($labels as $predicted): ?>
                                        <?php $isDiagonal = $actual === $predicted; ?>
                                        <td class="px-4 py-3 font-semibold <?php echo $isDiagonal ? 'bg-green-50 text-green-700' : 'text-slate-500'; ?>">
                                            <?php echo (int) $matrix[$actual][$predicted]; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalKlasifikasi === 0): ?>
                    <p class="text-sm text-slate-500 mt-4">Belum ada hasil klasifikasi. Jalankan Klasifikasi KNN terlebih dahulu untuk melihat hasil evaluasi.</p>
                <?php endif; ?>
            </section>

            <div class="text-center text-sm text-slate-400 mt-8">
                &copy; <?php echo date('Y'); ?> Sistem Analisis Sentimen - Algoritma KNN
            </div>
        </main>
    </div>
</body>
</html>
