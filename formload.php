<?php
session_start();
require 'koneksidb.php';

// Definisikan menu mana yang lagi aktif
$menu_aktif = 'dashboard';

$lastKUsed = $_SESSION['last_knn_k'] ?? null;

// Ambil data statistik dari database
$stmtTotalDataset = $pdo->query("SELECT COUNT(*) FROM dataset_awal");
$totalDataset = (int)$stmtTotalDataset->fetchColumn();

$stmtLatih = $pdo->query("SELECT COUNT(*) FROM dataset_awal WHERE jenis_data = 'Latih'");
$totalLatih = (int)$stmtLatih->fetchColumn();

$stmtUji = $pdo->query("SELECT COUNT(*) FROM dataset_awal WHERE jenis_data = 'Uji'");
$totalUji = (int)$stmtUji->fetchColumn();

$stmtPrediksiUji = $pdo->query("SELECT COUNT(*) FROM dataset_awal WHERE jenis_data = 'Uji' AND label_prediksi IS NOT NULL");
$totalPrediksiUji = (int)$stmtPrediksiUji->fetchColumn();

$classes = ['Positif', 'Negatif', 'Netral'];
$matrix = [];
foreach ($classes as $actual) {
    foreach ($classes as $predicted) {
        $matrix[$actual][$predicted] = 0;
    }
}

$normalizeLabel = function ($value) use ($classes) {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $mapping = [
        'positif' => 'Positif',
        'negatif' => 'Negatif',
        'netral' => 'Netral',
    ];

    $lower = strtolower($value);
    return $mapping[$lower] ?? $value;
};

$stmtAkurasi = $pdo->query("SELECT label, label_prediksi, COUNT(*) as jumlah FROM dataset_awal WHERE jenis_data = 'Uji' AND label_prediksi IS NOT NULL GROUP BY label, label_prediksi");
$results = $stmtAkurasi->fetchAll(PDO::FETCH_ASSOC);

$totalUjiEvaluasi = 0;
foreach ($results as $row) {
    $actualLabel = $normalizeLabel($row['label']);
    $predictedLabel = $normalizeLabel($row['label_prediksi']);

    if ($actualLabel === '' || $predictedLabel === '' || !isset($matrix[$actualLabel][$predictedLabel])) {
        continue;
    }

    $matrix[$actualLabel][$predictedLabel] = (int)$row['jumlah'];
    $totalUjiEvaluasi += (int)$row['jumlah'];
}

$totalBenar = 0;
foreach ($classes as $cls) {
    $totalBenar += $matrix[$cls][$cls];
}

$accuracyPercent = $totalUjiEvaluasi > 0 ? round(($totalBenar / $totalUjiEvaluasi) * 100, 2) : 0;

$stmtHasilTerbaru = $pdo->prepare("SELECT id, teks_bersih, label, label_prediksi FROM dataset_awal WHERE jenis_data = 'Uji' ORDER BY id ASC LIMIT 5");
$stmtHasilTerbaru->execute();
$hasilTerbaru = $stmtHasilTerbaru->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Analisis Sentimen</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 text-slate-800">

    <div class="flex min-h-screen">

        <?php include 'sidebar.php'; ?>
        <!-- Main Content -->
        <main class="flex-1 p-8">

            <!-- Header -->
            <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <h2 class="text-2xl font-bold text-slate-900">
                    Dashboard Utama
                </h2>
                <p class="text-slate-500 mt-2">
                    Project UAS Analisis Sentimen
                </p>
                <div class="mt-4 inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-sm font-medium text-blue-700">
                    <?php if ($lastKUsed !== null): ?>
                        Klasifikasi terakhir menggunakan <span class="ml-1 font-semibold">K = <?= (int)$lastKUsed ?></span>
                    <?php else: ?>
                        Belum ada klasifikasi terakhir yang tercatat.
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Total Dataset</p>
                    <h3 class="text-3xl font-bold text-blue-600 mt-2"><?= number_format($totalDataset) ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Data komentar/ulasan tersimpan</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Data Training</p>
                    <h3 class="text-3xl font-bold text-green-600 mt-2"><?= number_format($totalLatih) ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Data latih algoritma KNN</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Data Testing</p>
                    <h3 class="text-3xl font-bold text-orange-500 mt-2"><?= number_format($totalUji) ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Data uji klasifikasi</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Akurasi Model</p>
                    <h3 class="text-3xl font-bold text-purple-600 mt-2"><?= number_format($accuracyPercent, 2) ?>%</h3>
                    <p class="text-xs text-slate-400 mt-2">Berdasarkan hasil prediksi KNN</p>
                </div>

            </div>

            <!-- Workflow -->
            <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <h3 class="text-xl font-bold text-slate-900 mb-2">
                    Alur Proses Analisis Sentimen
                </h3>
                <p class="text-slate-500 mb-6">
                    Tahapan umum sistem mulai dari input dataset sampai hasil klasifikasi sentimen.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">

                    <div class="border border-blue-100 bg-blue-50 rounded-xl p-4 text-center">
                        <div class="w-10 h-10 mx-auto rounded-full bg-blue-600 text-white flex items-center justify-center font-bold mb-3">
                            1
                        </div>
                        <h4 class="font-semibold text-blue-700">Dataset sudah Labeling</h4>
                        <p class="text-sm text-slate-500 mt-2">
                            Input data komentar atau ulasan.
                        </p>
                    </div>

                    <div class="border border-blue-100 bg-blue-50 rounded-xl p-4 text-center">
                        <div class="w-10 h-10 mx-auto rounded-full bg-blue-600 text-white flex items-center justify-center font-bold mb-3">
                            2
                        </div>
                        <h4 class="font-semibold text-blue-700">Preprocessing</h4>
                        <p class="text-sm text-slate-500 mt-2">
                            Case folding, Punctuation & Number Removal, Tokenisasi, Normalisasi (Spelling Correction), Stopwords Removal, Stemming.
                        </p>
                    </div>

                    <div class="border border-blue-100 bg-blue-50 rounded-xl p-4 text-center">
                        <div class="w-10 h-10 mx-auto rounded-full bg-blue-600 text-white flex items-center justify-center font-bold mb-3">
                            3
                        </div>
                        <h4 class="font-semibold text-blue-700">TF-IDF</h4>
                        <p class="text-sm text-slate-500 mt-2">
                            Mengubah teks menjadi nilai bobot numerik.
                        </p>
                    </div>

                    <div class="border border-blue-100 bg-blue-50 rounded-xl p-4 text-center">
                        <div class="w-10 h-10 mx-auto rounded-full bg-blue-600 text-white flex items-center justify-center font-bold mb-3">
                            4
                        </div>
                        <h4 class="font-semibold text-blue-700">KNN (Cosine Similarity)</h4>
                        <p class="text-sm text-slate-500 mt-2">
                            Klasifikasi berdasarkan tetangga terdekat.
                        </p>
                    </div>

                    <div class="border border-blue-100 bg-blue-50 rounded-xl p-4 text-center">
                        <div class="w-10 h-10 mx-auto rounded-full bg-blue-600 text-white flex items-center justify-center font-bold mb-3">
                            5
                        </div>
                        <h4 class="font-semibold text-blue-700">Hasil Evaluasi (Confusion Matrix)</h4>
                        <p class="text-sm text-slate-500 mt-2">
                            Menampilkan performa model klasifikasi berdasarkan data uji.
                        </p>
                    </div>

                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">
                            Hasil Klasifikasi Terbaru
                        </h3>
                        <p class="text-slate-500 text-sm mt-1">
                            Data uji yang sudah diprediksi menggunakan algoritma KNN.
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">Teks</th>
                                <th class="px-4 py-3">Label Asli</th>
                                <th class="px-4 py-3">Prediksi KNN</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <?php if (!empty($hasilTerbaru)): ?>
                                <?php foreach ($hasilTerbaru as $index => $row): ?>
                                    <?php $labelAsli = trim((string)($row['label'] ?? '')); ?>
                                    <?php $labelPrediksi = trim((string)($row['label_prediksi'] ?? '')); ?>
                                    <?php $status = empty($labelPrediksi) ? 'Belum dihitung' : (($labelAsli === $labelPrediksi) ? 'Sesuai' : 'Salah'); ?>
                                    <tr>
                                        <td class="px-4 py-4"><?= $index + 1 ?></td>
                                        <td class="px-4 py-4 leading-relaxed"><?= htmlspecialchars($row['teks_bersih'] ?? '-') ?></td>
                                        <td class="px-4 py-4">
                                            <?php if ($labelAsli !== ''): ?>
                                                <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-medium">
                                                    <?= htmlspecialchars($labelAsli) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-400 italic">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4">
                                            <?php if ($labelPrediksi !== ''): ?>
                                                <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-medium">
                                                    <?= htmlspecialchars($labelPrediksi) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-400 italic">Belum dihitung</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4">
                                            <?php if ($status === 'Sesuai'): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Sesuai</span>
                                            <?php elseif ($status === 'Salah'): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Salah</span>
                                            <?php else: ?>
                                                <span class="text-slate-400 text-xs">Belum dihitung</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-slate-500">
                                        Belum ada hasil klasifikasi untuk ditampilkan.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Footer -->
            <div class="text-center text-sm text-slate-400 mt-8">
                &copy; <?php echo date("Y"); ?> Sistem Analisis Sentimen - Algoritma KNN | Cosine Similarity
            </div>

        </main>

    </div>

</body>
</html>