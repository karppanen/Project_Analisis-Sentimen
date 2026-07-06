<?php
// Panggil koneksi database
require 'koneksidb.php';

// Ubah nilai ini sesuai halaman
$menu_aktif = 'input_dataset';

// --- LOGIKA PAGINATION ---
$limit = 100; // Jumlah data per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Hitung total data untuk pagination
$stmtTotal = $pdo->query("SELECT COUNT(*) FROM dataset_awal");
$totalRecords = $stmtTotal->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Ambil data sesuai limit dan halaman saat ini (diurutkan dari yang terbaru masuk)
$stmtData = $pdo->prepare("SELECT * FROM dataset_awal ORDER BY id DESC LIMIT :limit OFFSET :offset");
$stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtData->execute();
$dataset = $stmtData->fetchAll();

// Hitung jumlah label per kategori
$stmtLabelSummary = $pdo->query("SELECT 
    SUM(CASE WHEN LOWER(TRIM(COALESCE(label, ''))) = 'positif' THEN 1 ELSE 0 END) AS positif,
    SUM(CASE WHEN LOWER(TRIM(COALESCE(label, ''))) = 'negatif' THEN 1 ELSE 0 END) AS negatif,
    SUM(CASE WHEN LOWER(TRIM(COALESCE(label, ''))) = 'netral' THEN 1 ELSE 0 END) AS netral
FROM dataset_awal");
$labelSummary = $stmtLabelSummary->fetch(PDO::FETCH_ASSOC);
$positifCount = (int)($labelSummary['positif'] ?? 0);
$negatifCount = (int)($labelSummary['negatif'] ?? 0);
$netralCount = (int)($labelSummary['netral'] ?? 0);
// --------------------------
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Dataset - Analisis Sentimen</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 text-slate-800">

    <div class="flex min-h-screen">

        <?php include 'sidebar.php'; ?>
        
        <main class="flex-1 p-8">

            <div class="bg-white rounded-2xl shadow-sm p-6 mb-6 border-l-4 border-blue-600">
                <h2 class="text-2xl font-bold text-slate-900">
                    Manajemen Dataset
                </h2>
                <p class="text-slate-500 mt-2">
                    Upload data ulasan atau komentar baru ke dalam sistem untuk diproses lebih lanjut.
                </p>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <h3 class="text-xl font-bold text-slate-900 mb-2">
                    Upload Dataset Baru
                </h3>
                <p class="text-slate-500 mb-6 text-sm">
                    Pastikan file dalam format .csv dan memiliki kolom untuk teks dan label sentimen.
                </p>

                <form action="proses_upload.php" method="POST" enctype="multipart/form-data" class="max-w-2xl">
                    <div class="mb-6 p-4 border-2 border-dashed border-slate-300 rounded-xl bg-slate-50">
                        <label class="block text-sm font-medium text-slate-700 mb-3">Pilih File CSV Dataset Anda</label>
                        <input type="file" name="file_dataset" accept=".csv" required
                            class="block w-full text-sm text-slate-500
                                    file:mr-4 file:py-2 file:px-4
                                    file:rounded-full file:border-0
                                    file:text-sm file:font-semibold
                                    file:bg-blue-600 file:text-white
                                    hover:file:bg-blue-700 cursor-pointer transition-colors">
                    </div>
                    
                    <button type="submit" name="upload" class="px-6 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 shadow-sm transition-colors flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                        Upload & Simpan ke Database
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">Data Tersimpan</h3>
                        <p class="text-sm text-slate-500">Total data saat ini: <span class="font-bold text-blue-600"><?= number_format($totalRecords) ?></span> baris</p>
                    </div>
                    
                    <?php if ($totalRecords > 0): ?>
                    <form action="proses_hapus_dataset.php" method="POST" onsubmit="return confirm('Yakin ingin menghapus SELURUH data? Aksi ini tidak dapat dibatalkan dan akan mengosongkan tabel.');">
                        <button type="submit" name="hapus_dataset" class="px-4 py-2 bg-red-50 text-red-600 font-medium rounded-lg hover:bg-red-100 border border-red-200 transition-colors flex items-center gap-2 text-sm shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            Hapus Semua Data
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <div class="rounded-xl border border-green-200 bg-green-50 p-4">
                        <p class="text-sm font-medium text-green-700">Jumlah Positif</p>
                        <p class="text-2xl font-bold text-green-800"><?= number_format($positifCount) ?></p>
                    </div>
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4">
                        <p class="text-sm font-medium text-red-700">Jumlah Negatif</p>
                        <p class="text-2xl font-bold text-red-800"><?= number_format($negatifCount) ?></p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-medium text-slate-700">Jumlah Netral</p>
                        <p class="text-2xl font-bold text-slate-800"><?= number_format($netralCount) ?></p>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 w-16">No</th>
                                <th class="px-4 py-3">Teks Ulasan</th>
                                <th class="px-4 py-3 w-32">Label</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php if ($totalRecords > 0): ?>
                                <?php foreach ($dataset as $index => $row): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 text-slate-500"><?= $offset + $index + 1 ?></td>
                                        <td class="px-4 py-3 text-slate-700 leading-relaxed"><?= htmlspecialchars($row['teks']) ?></td>
                                        <td class="px-4 py-3">
                                            <?php if (!empty($row['label'])): ?>
                                                <?php $label = strtolower(trim((string)$row['label'])); ?>
                                                <?php if ($label === 'positif'): ?>
                                                    <span class="px-3 py-1 rounded-full bg-green-50 text-green-700 text-xs font-medium border border-green-200">
                                                        <?= htmlspecialchars($row['label']) ?>
                                                    </span>
                                                <?php elseif ($label === 'negatif'): ?>
                                                    <span class="px-3 py-1 rounded-full bg-red-50 text-red-700 text-xs font-medium border border-red-200">
                                                        <?= htmlspecialchars($row['label']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-medium border border-slate-200">
                                                        <?= htmlspecialchars($row['label']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 italic">Belum dilabeli</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-slate-500">
                                        Belum ada data dataset yang diupload.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="flex items-center justify-between border-t border-slate-200 pt-4 mt-4">
                    <div class="text-sm text-slate-500">
                        Menampilkan halaman <span class="font-medium text-slate-900"><?= $page ?></span> dari <span class="font-medium text-slate-900"><?= $totalPages ?></span>
                    </div>
                    
                    <div class="flex flex-wrap gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=1" class="px-3 h-8 flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 text-sm font-medium hover:bg-slate-100 hover:text-slate-900 transition-colors">
                                « First
                            </a>
                        <?php endif; ?>

                        <?php 
                        $start = max(1, $page - 1);
                        $end = min($totalPages, $page + 1);

                        if ($start > 1) {
                            if ($start > 2) echo '<span class="w-8 h-8 flex items-center justify-center text-slate-400">...</span>';
                        }

                        for ($i = $start; $i <= $end; $i++): 
                        ?>
                            <?php if ($i == $page): ?>
                                <button disabled class="w-8 h-8 flex items-center justify-center rounded-lg border border-blue-600 bg-blue-600 text-white text-sm font-bold shadow-sm">
                                    <?= $i ?>
                                </button>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 text-sm font-medium hover:bg-slate-100 hover:text-slate-900 transition-colors">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?>
                                <span class="w-8 h-8 flex items-center justify-center text-slate-400">...</span>
                            <?php endif; ?>
                            <a href="?page=<?= $totalPages ?>" class="px-3 h-8 flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 text-sm font-medium hover:bg-slate-100 hover:text-slate-900 transition-colors">
                                Last »
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>
</body>
</html>