<?php
require 'koneksidb.php';

// Ubah nilai ini sesuai halaman untuk efek aktif di sidebar
$menu_aktif = 'preprocessing';

// --- LOGIKA PAGINATION ---
$limit = 100; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Hitung total data
$stmtTotal = $pdo->query("SELECT COUNT(*) FROM dataset_awal");
$totalRecords = $stmtTotal->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Ambil data untuk ditampilkan
$stmtData = $pdo->prepare("SELECT * FROM dataset_awal ORDER BY id DESC LIMIT :limit OFFSET :offset");
$stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtData->execute();
$dataset = $stmtData->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Preprocessing - Analisis Sentimen</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 text-slate-800">

    <div class="flex min-h-screen">

        <!-- Panggil Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <main class="flex-1 p-8">

            <div class="bg-white rounded-2xl shadow-sm p-6 mb-6 flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-slate-900">Manajemen Preprocessing</h3>
                    <p class="text-sm text-slate-500 mt-1">
                        Jalankan proses pembersihan teks atau reset kembali hasil yang sudah ada ke kondisi awal.
                    </p>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                    <form action="proses_reset_preprocessing.php" method="POST" onsubmit="return confirm('Yakin ingin mereset/menghapus semua hasil preprocessing? Data asli akan tetap aman.');">
                        <button type="submit" name="reset_preprocessing" class="w-full sm:w-auto px-6 py-3 bg-red-50 text-red-600 font-medium rounded-lg hover:bg-red-100 border border-red-200 transition-colors flex items-center justify-center gap-2 whitespace-nowrap">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            Reset Hasil
                        </button>
                    </form>

                    <form action="proses_run_preprocessing.php" method="POST">
                        <button type="submit" name="run_preprocessing" class="w-full sm:w-auto px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 shadow-sm transition-colors flex items-center justify-center gap-2 whitespace-nowrap">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                            </svg>
                            Jalankan Preprocessing
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tabel Data -->
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">Hasil Preprocessing</h3>
                        <p class="text-sm text-slate-500">Total data: <span class="font-bold text-blue-600"><?= number_format($totalRecords) ?></span> baris</p>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-sm text-left table-fixed">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 w-16">No</th>
                                <th class="px-4 py-3 w-5/12">Teks Asli (Mentah)</th>
                                <th class="px-4 py-3 w-5/12">Teks Bersih (Hasil)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php if ($totalRecords > 0): ?>
                                <?php foreach ($dataset as $index => $row): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-4 py-3 text-slate-500 align-top"><?= $offset + $index + 1 ?></td>
                                        
                                        <!-- Kolom Teks Asli -->
                                        <td class="px-4 py-3 text-slate-700 align-top pr-4">
                                            <?= htmlspecialchars($row['teks']) ?>
                                        </td>
                                        
                                        <!-- Kolom Teks Bersih -->
                                        <td class="px-4 py-3 text-blue-700 align-top">
                                            <?php 
                                            // Asumsi kita menyimpan hasil bersih di kolom 'teks_bersih'
                                            // Kalau kolomnya kosong, tampilkan badge abu-abu
                                            if (!empty($row['teks_bersih'])): ?>
                                                <?= htmlspecialchars($row['teks_bersih']) ?>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-slate-100 text-slate-500">
                                                    Belum diproses
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-slate-500">
                                        Belum ada data di database. Silakan upload dataset terlebih dahulu.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <?php if ($totalPages > 1): ?>
                <div class="flex items-center justify-between border-t border-slate-200 pt-4 mt-4">
                    <div class="text-sm text-slate-500">
                        Menampilkan halaman <span class="font-medium text-slate-900"><?= $page ?></span> dari <span class="font-medium text-slate-900"><?= $totalPages ?></span>
                    </div>
                    
                    <div class="flex flex-wrap gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=1" class="px-3 h-8 flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 text-sm font-medium hover:bg-slate-100 transition-colors">
                                « First
                            </a>
                        <?php endif; ?>

                        <?php 
                        // Tampilkan maksimal 5 tombol angka di sekitar halaman aktif biar gak kepanjangan
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        
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
                                <a href="?page=<?= $i ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 text-sm font-medium hover:bg-slate-100 transition-colors">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php
                        if ($end < $totalPages) {
                            if ($end < $totalPages - 1) echo '<span class="w-8 h-8 flex items-center justify-center text-slate-400">...</span>';
                        }
                        ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $totalPages ?>" class="px-3 h-8 flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 text-sm font-medium hover:bg-slate-100 transition-colors">
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