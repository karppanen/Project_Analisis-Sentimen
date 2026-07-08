<?php
session_start();
require 'koneksidb.php';
$menu_aktif = 'evaluasi';

$lastKUsed = $_SESSION['last_knn_k'] ?? null;

// 1. Inisialisasi Kategori Kelas
$classes = ['Positif', 'Negatif', 'Netral'];

// 2. Siapkan wadah struktur Confusion Matrix array 2D
// Contoh struktur: $matrix['Aktual']['Prediksi']
$matrix = [];
foreach ($classes as $actual) {
    foreach ($classes as $predicted) {
        $matrix[$actual][$predicted] = 0;
    }
}

// 3. Ambil data hasil klasifikasi khusus untuk Data Uji yang sudah diprediksi
$stmt = $pdo->query("SELECT label, label_prediksi, COUNT(*) as jumlah FROM dataset_awal WHERE jenis_data = 'Uji' AND label_prediksi IS NOT NULL GROUP BY label, label_prediksi");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_uji = 0;
foreach ($results as $row) {
    $matrix[$row['label']][$row['label_prediksi']] = (int)$row['jumlah'];
    $total_uji += (int)$row['jumlah'];
}

// 4. Hitung Nilai Diagonal (Tebakan Benar / True Positive tiap kelas)
$total_benar = 0;
foreach ($classes as $cls) {
    $total_benar += $matrix[$cls][$cls];
}

// Hitung Akurasi Global
$accuracy = $total_uji > 0 ? ($total_benar / $total_uji) * 100 : 0;

// 5. Hitung Precision, Recall, F1-Score per Kelas
$metrics = [];
$sum_precision = 0;
$sum_recall = 0;
$sum_f1 = 0;

foreach ($classes as $cls) {
    $tp = $matrix[$cls][$cls];
    
    // Total Aktual (Jumlah Baris Matriks)
    $total_aktual = array_sum($matrix[$cls]);
    
    // Total Prediksi (Jumlah Kolom Matriks)
    $total_prediksi = 0;
    foreach ($classes as $actual_row) {
        $total_prediksi += $matrix[$actual_row][$cls];
    }
    
    // Rumus Matematika Evaluasi
    $precision = $total_prediksi > 0 ? ($tp / $total_prediksi) : 0;
    $recall = $total_aktual > 0 ? ($tp / $total_aktual) : 0;
    $f1_score = ($precision + $recall) > 0 ? 2 * (($precision * $recall) / ($precision + $recall)) : 0;
    
    $metrics[$cls] = [
        'precision' => $precision * 100,
        'recall' => $recall * 100,
        'f1_score' => $f1_score * 100
    ];
    
    $sum_precision += $precision;
    $sum_recall += $recall;
    $sum_f1 += $f1_score;
}

// Hitung Rata-rata Makro (Macro Average)
$macro_precision = ($sum_precision / count($classes)) * 100;
$macro_recall = ($sum_recall / count($classes)) * 100;
$macro_f1 = ($sum_f1 / count($classes)) * 100;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Evaluasi Model - Analisis Sentimen</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800">
    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>
        
        <main class="flex-1 p-8">
            <div class="bg-white rounded-2xl shadow-sm p-6 mb-6 border-l-4 border-blue-600">
                <h2 class="text-2xl font-bold text-slate-900">Evaluasi Performa Model</h2>
                <p class="text-slate-500 mt-2">
                    Mengukur tingkat keberhasilan algoritma KNN dalam melakukan klasifikasi sentimen menggunakan metode pengujian <strong>Confusion Matrix</strong>.
                </p>
                <div class="mt-4 inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-sm font-medium text-blue-700">
                    <?php if ($lastKUsed !== null): ?>
                        Parameter K terakhir yang dipakai: <span class="ml-1 font-semibold">K = <?= (int)$lastKUsed ?></span>
                    <?php else: ?>
                        Belum ada klasifikasi terakhir yang tersimpan.
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($total_uji == 0): ?>
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-amber-800">
                    <h4 class="font-bold mb-1">Klasifikasi Belum Dijalankan!</h4>
                    <p class="text-sm text-amber-700">Hasil evaluasi tidak bisa ditampilkan karena kamu belum melakukan proses tebakan data. Silakan ke halaman <strong>Klasifikasi KNN</strong> terlebih dahulu.</p>
                </div>
            <?php else: ?>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <p class="text-sm font-medium text-slate-400">Akurasi Sistem (Accuracy)</p>
                        <h3 class="text-3xl font-bold text-blue-600 mt-1"><?= number_format($accuracy, 2) ?>%</h3>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <p class="text-sm font-medium text-slate-400">Rata-rata Presisi</p>
                        <h3 class="text-3xl font-bold text-indigo-600 mt-1"><?= number_format($macro_precision, 2) ?>%</h3>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <p class="text-sm font-medium text-slate-400">Rata-rata Recall</p>
                        <h3 class="text-3xl font-bold text-emerald-600 mt-1"><?= number_format($macro_recall, 2) ?>%</h3>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <p class="text-sm font-medium text-slate-400">Rata-rata F1-Score</p>
                        <h3 class="text-3xl font-bold text-purple-600 mt-1"><?= number_format($macro_f1, 2) ?>%</h3>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="bg-white rounded-2xl shadow-sm p-6 lg:col-span-1 border border-slate-200">
                        <h3 class="text-lg font-bold text-slate-900 mb-4">Confusion Matrix Table</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-center text-sm border-collapse">
                                <thead>
                                    <tr class="bg-slate-50">
                                        <th class="p-3 border border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider" rowspan="2">Label Aktual</th>
                                        <th class="p-2 border border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider" colspan="3">Label Prediksi (KNN)</th>
                                    </tr>
                                    <tr class="bg-slate-50 text-slate-700 font-semibold">
                                        <th class="p-2 border border-slate-200 w-20">Positif</th>
                                        <th class="p-2 border border-slate-200 w-20">Negatif</th>
                                        <th class="p-2 border border-slate-200 w-20">Netral</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 font-medium">
                                    <?php foreach ($classes as $actual): ?>
                                        <tr>
                                            <td class="p-3 bg-slate-50 border border-slate-200 font-bold text-slate-700 text-left"><?= $actual ?></td>
                                            <?php foreach ($classes as $predicted): ?>
                                                <?php 
                                                    // Beri warna background hijau terang khusus area diagonal u/ menandakan data sukses ditebak
                                                    $isDiagonal = ($actual == $predicted);
                                                    $bgClass = $isDiagonal ? 'bg-green-50 text-green-700 font-bold text-base' : 'text-slate-600';
                                                ?>
                                                <td class="p-3 border border-slate-200 <?= $bgClass ?>">
                                                    <?= $matrix[$actual][$predicted] ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs text-slate-400 mt-3">* Kolom berwarna hijau menandakan total data ulasan yang berhasil diprediksi secara tepat oleh sistem.</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm p-6 lg:col-span-2 border border-slate-200">
                        <h3 class="text-lg font-bold text-slate-900 mb-4">Rincian Klasifikasi Per-Kelas</h3>
                        <div class="overflow-x-auto rounded-lg border border-slate-200">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-slate-50 text-slate-700 font-bold">
                                    <tr>
                                        <th class="px-4 py-3">Kategori Sentimen</th>
                                        <th class="px-4 py-3 w-32">Precision</th>
                                        <th class="px-4 py-3 w-32">Recall</th>
                                        <th class="px-4 py-3 w-32">F1-Score</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <?php foreach ($classes as $cls): ?>
                                        <tr class="hover:bg-slate-50 transition-colors">
                                            <td class="px-4 py-3 font-semibold text-slate-800"><?= $cls ?></td>
                                            <td class="px-4 py-3 font-mono text-indigo-600"><?= number_format($metrics[$cls]['precision'], 2) ?>%</td>
                                            <td class="px-4 py-3 font-mono text-emerald-600"><?= number_format($metrics[$cls]['recall'], 2) ?>%</td>
                                            <td class="px-4 py-3 font-mono text-purple-600"><?= number_format($metrics[$cls]['f1_score'], 2) ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="bg-slate-50 font-bold border-t-2 border-slate-300 text-slate-900">
                                        <td class="px-4 py-3">Macro Average</td>
                                        <td class="px-4 py-3 font-mono text-indigo-700"><?= number_format($macro_precision, 2) ?>%</td>
                                        <td class="px-4 py-3 font-mono text-emerald-700"><?= number_format($macro_recall, 2) ?>%</td>
                                        <td class="px-4 py-3 font-mono text-purple-700"><?= number_format($macro_f1, 2) ?>%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>