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
$metrics = [];
$total = 0;
$diagonalSum = 0;
$accuracy = 0;
$macroPrecision = 0;
$macroRecall = 0;
$macroF1 = 0;
$kValueDigunakan = 0;

try {
    $pdo = getPdoConnection();
    ensureKlasifikasiTable($pdo);

    $rows = $pdo->query('SELECT label_aktual, label_prediksi FROM klasifikasi_kelompok_knn')->fetchAll();

    foreach ($rows as $row) {
        if (isset($matrix[$row['label_aktual']][$row['label_prediksi']])) {
            $matrix[$row['label_aktual']][$row['label_prediksi']]++;
        }
    }

    $total = array_sum(array_map('array_sum', $matrix));
    foreach ($labels as $label) {
        $diagonalSum += $matrix[$label][$label];
    }
    $accuracy = $total > 0 ? $diagonalSum / $total : 0;

    foreach ($labels as $label) {
        $truePositive = $matrix[$label][$label];

        $falsePositive = 0;
        foreach ($labels as $actual) {
            if ($actual !== $label) {
                $falsePositive += $matrix[$actual][$label];
            }
        }

        $falseNegative = 0;
        foreach ($labels as $predicted) {
            if ($predicted !== $label) {
                $falseNegative += $matrix[$label][$predicted];
            }
        }

        $precision = ($truePositive + $falsePositive) > 0 ? $truePositive / ($truePositive + $falsePositive) : 0.0;
        $recall = ($truePositive + $falseNegative) > 0 ? $truePositive / ($truePositive + $falseNegative) : 0.0;
        $f1 = ($precision + $recall) > 0 ? 2 * $precision * $recall / ($precision + $recall) : 0.0;

        $metrics[$label] = [
            'tp' => $truePositive,
            'fp' => $falsePositive,
            'fn' => $falseNegative,
            'precision' => $precision,
            'recall' => $recall,
            'f1' => $f1,
        ];
    }

    $macroPrecision = array_sum(array_column($metrics, 'precision')) / count($labels);
    $macroRecall = array_sum(array_column($metrics, 'recall')) / count($labels);
    $macroF1 = array_sum(array_column($metrics, 'f1')) / count($labels);

    $kValueDigunakan = (int) ($pdo->query('SELECT k_value FROM klasifikasi_kelompok_knn LIMIT 1')->fetchColumn() ?: 0);

    if ($total === 0) {
        $message = 'Belum ada hasil klasifikasi. Jalankan Klasifikasi KNN terlebih dahulu.';
        $messageType = 'error';
    }
} catch (Throwable $e) {
    $message = 'Terjadi kesalahan: ' . $e->getMessage();
    $messageType = 'error';
}

// --- Download CSV (harus dijalankan sebelum ada output HTML apa pun) ---
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="hasil_analisis_knn.csv"');
    $output = fopen('php://output', 'w');

    fputcsv($output, ['Confusion Matrix']);
    fputcsv($output, array_merge(['Aktual \\ Prediksi'], $labels));
    foreach ($labels as $actual) {
        $row = [$actual];
        foreach ($labels as $predicted) {
            $row[] = $matrix[$actual][$predicted];
        }
        fputcsv($output, $row);
    }

    fputcsv($output, []);
    fputcsv($output, ['Metrik per Kelas']);
    fputcsv($output, ['Label', 'TP', 'FP', 'FN', 'Precision', 'Recall', 'F1-Score']);
    foreach ($labels as $label) {
        fputcsv($output, [
            $label,
            $metrics[$label]['tp'],
            $metrics[$label]['fp'],
            $metrics[$label]['fn'],
            number_format($metrics[$label]['precision'], 4),
            number_format($metrics[$label]['recall'], 4),
            number_format($metrics[$label]['f1'], 4),
        ]);
    }
    fputcsv($output, ['Rata-rata (Macro)', '', '', '', number_format($macroPrecision, 4), number_format($macroRecall, 4), number_format($macroF1, 4)]);

    fputcsv($output, []);
    fputcsv($output, ['Akurasi Keseluruhan', number_format($accuracy * 100, 2) . '%']);
    fputcsv($output, ['K digunakan', $kValueDigunakan]);

    fclose($output);
    exit;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Analisis</title>
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

                <a href="hasil_analisis.php" class="block px-4 py-3 rounded-lg bg-blue-600 font-medium">
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
                        <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Evaluasi Model</p>
                        <h2 class="text-2xl font-bold text-slate-900 mt-1">Hasil Analisis &amp; Confusion Matrix</h2>
                        <p class="text-slate-500 mt-2">Evaluasi performa klasifikasi KNN terhadap data testing (K = <?php echo (int) $kValueDigunakan; ?>).</p>
                    </div>
                    <a href="?download=csv" class="rounded-lg bg-green-600 px-5 py-3 text-sm font-semibold text-white hover:bg-green-700 inline-flex items-center gap-2">
                        Download Excel (CSV)
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
                    <p class="text-sm text-slate-500">Akurasi</p>
                    <h3 class="text-3xl font-bold text-blue-600 mt-2"><?php echo number_format($accuracy * 100, 2); ?>%</h3>
                    <p class="text-xs text-slate-400 mt-2">Prediksi benar dari seluruh data</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Total Data Diuji</p>
                    <h3 class="text-3xl font-bold text-slate-700 mt-2"><?php echo number_format($total); ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Data testing</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Prediksi Benar</p>
                    <h3 class="text-3xl font-bold text-green-600 mt-2"><?php echo number_format($diagonalSum); ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Sesuai label aktual</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Prediksi Salah</p>
                    <h3 class="text-3xl font-bold text-red-500 mt-2"><?php echo number_format($total - $diagonalSum); ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Tidak sesuai label aktual</p>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
                <section class="bg-white rounded-2xl shadow-sm p-6 xl:col-span-2">
                    <h3 class="text-xl font-bold text-slate-900 mb-1">Confusion Matrix</h3>
                    <p class="text-sm text-slate-500 mb-4">Baris = label aktual, kolom = label prediksi. Diagonal (hijau) = prediksi benar.</p>

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
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
                </section>

                <section class="bg-white rounded-2xl shadow-sm p-6 xl:col-span-3">
                    <h3 class="text-xl font-bold text-slate-900 mb-1">Precision, Recall, F1-Score</h3>
                    <p class="text-sm text-slate-500 mb-4">Dihitung per kelas sentimen, plus rata-rata (macro average).</p>

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Label</th>
                                    <th class="px-4 py-3 text-center">TP</th>
                                    <th class="px-4 py-3 text-center">FP</th>
                                    <th class="px-4 py-3 text-center">FN</th>
                                    <th class="px-4 py-3 text-right">Precision</th>
                                    <th class="px-4 py-3 text-right">Recall</th>
                                    <th class="px-4 py-3 text-right">F1-Score</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <?php foreach ($labels as $label): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 font-semibold text-slate-700 capitalize"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-3 text-center text-slate-500"><?php echo (int) $metrics[$label]['tp']; ?></td>
                                        <td class="px-4 py-3 text-center text-slate-500"><?php echo (int) $metrics[$label]['fp']; ?></td>
                                        <td class="px-4 py-3 text-center text-slate-500"><?php echo (int) $metrics[$label]['fn']; ?></td>
                                        <td class="px-4 py-3 text-right font-medium text-blue-600"><?php echo number_format($metrics[$label]['precision'], 4); ?></td>
                                        <td class="px-4 py-3 text-right font-medium text-purple-600"><?php echo number_format($metrics[$label]['recall'], 4); ?></td>
                                        <td class="px-4 py-3 text-right font-medium text-green-600"><?php echo number_format($metrics[$label]['f1'], 4); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="bg-slate-50 font-bold">
                                    <td class="px-4 py-3 text-slate-800">Rata-rata (Macro)</td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3 text-right text-blue-700"><?php echo number_format($macroPrecision, 4); ?></td>
                                    <td class="px-4 py-3 text-right text-purple-700"><?php echo number_format($macroRecall, 4); ?></td>
                                    <td class="px-4 py-3 text-right text-green-700"><?php echo number_format($macroF1, 4); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 rounded-lg bg-blue-50 text-blue-700 text-xs px-4 py-3">
                        <strong>Cara baca:</strong> <em>Precision</em> = dari semua yang diprediksi label ini, berapa persen yang benar.
                        <em>Recall</em> = dari semua data aktual berlabel ini, berapa persen yang berhasil ditemukan.
                        <em>F1-Score</em> = rata-rata harmonik precision &amp; recall (menyeimbangkan keduanya).
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
