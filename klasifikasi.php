<?php
require 'koneksidb.php';
$menu_aktif = 'klasifikasi';

// --- LOGIKA PAGINATION ---
$limit = 50; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Kita hanya menampilkan Data Uji (20%) yang dites oleh KNN
$stmtTotal = $pdo->query("SELECT COUNT(*) FROM dataset_awal WHERE jenis_data = 'Uji'");
$totalRecords = $stmtTotal->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Ambil Data Uji untuk ditampilkan di tabel ulasan
$stmtData = $pdo->prepare("SELECT * FROM dataset_awal WHERE jenis_data = 'Uji' ORDER BY id ASC LIMIT :limit OFFSET :offset");
$stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtData->execute();
$dataset = $stmtData->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Klasifikasi KNN - Analisis Sentimen</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800">
    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>
        
        <main class="flex-1 p-8">
            <div class="bg-white rounded-2xl shadow-sm p-6 mb-6 border-l-4 border-blue-600">
                <h2 class="text-2xl font-bold text-slate-900">Klasifikasi K-Nearest Neighbor</h2>
                <p class="text-slate-500 mt-2">
                    Tahap pengujian algoritma KNN menggunakan rumus kedekatan arah vektor <strong>Cosine Similarity</strong> pada 20% Data Uji.
                </p>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <h3 class="text-lg font-bold text-slate-900 mb-4">Pengaturan Parameter Algoritma</h3>
                <form action="proses_klasifikasi.php" method="POST" class="flex flex-col md:flex-row items-end gap-4 max-w-3xl">
                    <div class="w-full md:w-1/3">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Nilai K (Tetangga)</label>
                        <input type="number" name="nilai_k" min="1" max="19" value="3" required step="2"
                            class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                        <p class="text-xs text-slate-400 mt-1">* Disarankan bilangan ganjil (3, 5, 7, dst)</p>
                    </div>
                    
                    <button type="submit" name="jalankan_knn" class="px-6 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 shadow-sm transition-colors flex items-center gap-2 whitespace-nowrap">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        Jalankan Klasifikasi KNN
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-6">
                <div class="mb-4">
                    <h3 class="text-xl font-bold text-slate-900">Hasil Prediksi Data Uji</h3>
                    <p class="text-sm text-slate-500">Jumlah data uji yang dievaluasi: <span class="font-bold text-blue-600"><?= number_format($totalRecords) ?></span> baris</p>
                </div>

                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 w-16">No</th>
                                <th class="px-4 py-3">Teks Bersih (Ulasan)</th>
                                <th class="px-4 py-3 w-32">Label Asli</th>
                                <th class="px-4 py-3 w-32">Prediksi KNN</th>
                                <th class="px-4 py-3 w-28">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php if ($totalRecords > 0): ?>
                                <?php foreach ($dataset as $index => $row): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-4 py-3 text-slate-500 align-top"><?= $offset + $index + 1 ?></td>
                                        <td class="px-4 py-3 text-slate-700 leading-relaxed align-top"><?= htmlspecialchars($row['teks_bersih']) ?></td>
                                        
                                        <td class="px-4 py-3 align-top">
                                            <span class="px-2 py-1 rounded text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                                <?= $row['label'] ?>
                                            </span>
                                        </td>
                                        
                                        <td class="px-4 py-3 align-top">
                                            <?php if (!empty($row['label_prediksi'])): ?>
                                                <span class="px-2 py-1 rounded text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                                    <?= $row['label_prediksi'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 italic">Belum dihitung</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-4 py-3 align-top">
                                            <?php if (empty($row['label_prediksi'])): ?>
                                                <span class="text-slate-400 text-xs">-</span>
                                            <?php elseif ($row['label'] == $row['label_prediksi']): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Sesuai</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Salah</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                                        Data uji belum disiapkan. Pastikan kamu sudah melakukan Split Data dan TF-IDF terlebih dahulu.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="flex items-center justify-between border-t border-slate-200 pt-4 mt-4">
                    <div class="text-sm text-slate-500">
                        Halaman <span class="font-medium text-slate-900"><?= $page ?></span> dari <span class="font-medium text-slate-900"><?= $totalPages ?></span>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=1" class="px-3 h-8 flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 text-sm font-medium hover:bg-slate-100 transition-colors">
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
                                <button disabled class="w-8 h-8 flex items-center justify-center rounded-lg border border-blue-600 bg-blue-600 text-white text-sm font-bold shadow-sm"><?= $i ?></button>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 text-sm font-medium hover:bg-slate-100 transition-colors"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?>
                                <span class="w-8 h-8 flex items-center justify-center text-slate-400">...</span>
                            <?php endif; ?>
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