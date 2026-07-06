<?php
require 'koneksidb.php';
$menu_aktif = 'tfidf';

// --- LOGIKA PAGINATION ---
$limit = 100; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Hitung total kata unik di kamus
$stmtTotal = $pdo->query("SELECT COUNT(*) FROM term_vocabulary");
$totalRecords = $stmtTotal->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Ambil data kamus untuk ditampilkan
$stmtData = $pdo->prepare("SELECT * FROM term_vocabulary ORDER BY id ASC LIMIT :limit OFFSET :offset");
$stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtData->execute();
$dataset = $stmtData->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>TF-IDF - Analisis Sentimen</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800">
    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>
        
        <main class="flex-1 p-8">
            <div class="bg-white rounded-2xl shadow-sm p-6 mb-6 border-l-4 border-blue-600">
                <h2 class="text-2xl font-bold text-slate-900">Pembobotan TF-IDF</h2>
                <p class="text-slate-500 mt-2">
                    Proses pembagian data latih/uji, pembentukan kamus kata (Term Vocabulary), dan perhitungan bobot TF-IDF untuk seluruh dataset.
                </p>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <h3 class="text-lg font-bold text-slate-900 mb-4">Langkah Ekstraksi Fitur</h3>
                
                <div class="flex flex-col md:flex-row gap-6">
                    
                    <div class="flex-1 bg-slate-50 p-5 rounded-xl border border-slate-200">
                        <div class="flex items-center gap-3 mb-2">
                            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 font-bold">1</span>
                            <h4 class="font-bold text-slate-800">Split Data Latih & Uji</h4>
                        </div>
                        <p class="text-sm text-slate-500 mb-4">
                            Bagi dataset yang sudah berlabel secara acak menjadi porsi 80% Data Latih dan 20% Data Uji.
                        </p>
                        <form action="proses_split_data.php" method="POST" onsubmit="return confirm('Yakin ingin membagi ulang data secara acak? (Pembagian sebelumnya akan direset)');">
                            <button type="submit" name="split_data" class="w-full px-4 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 shadow-sm transition-colors flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M5 4a1 1 0 00-2 0v7.268a2 2 0 000 3.464V16a1 1 0 102 0v-1.268a2 2 0 000-3.464V4zM11 4a1 1 0 10-2 0v1.268a2 2 0 000 3.464V16a1 1 0 102 0V8.732a2 2 0 000-3.464V4zM16 3a1 1 0 011 1v7.268a2 2 0 010 3.464V16a1 1 0 11-2 0v-1.268a2 2 0 010-3.464V4a1 1 0 011-1z" />
                                </svg>
                                Proses Split Data 80:20
                            </button>
                        </form>
                    </div>

                    <div class="flex-1 bg-slate-50 p-5 rounded-xl border border-slate-200">
                        <div class="flex items-center gap-3 mb-2">
                            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 text-blue-600 font-bold">2</span>
                            <h4 class="font-bold text-slate-800">Hitung Bobot TF-IDF</h4>
                        </div>
                        <p class="text-sm text-slate-500 mb-4">
                            Membentuk kamus kata unik dari 80% Data Latih, lalu menghitung bobot nilai untuk seluruh ulasan.
                        </p>
                        <form action="proses_tfidf.php" method="POST">
                            <button type="submit" name="run_tfidf" class="w-full px-4 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 shadow-sm transition-colors flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                                </svg>
                                Hitung Bobot TF-IDF
                            </button>
                        </form>
                    </div>

                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-6">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">Kamus Kata (Term Vocabulary)</h3>
                        <p class="text-sm text-slate-500">Total kata unik dari Data Latih: <span class="font-bold text-blue-600"><?= number_format($totalRecords) ?></span> kata</p>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 w-16">No</th>
                                <th class="px-4 py-3">Term (Kata Dasar)</th>
                                <th class="px-4 py-3 w-32">DF</th>
                                <th class="px-4 py-3 w-48">Nilai IDF</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php if ($totalRecords > 0): ?>
                                <?php foreach ($dataset as $index => $row): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 text-slate-500"><?= $offset + $index + 1 ?></td>
                                        <td class="px-4 py-3 font-medium text-slate-700"><?= htmlspecialchars($row['term']) ?></td>
                                        <td class="px-4 py-3 text-slate-600"><?= $row['df'] ?></td>
                                        <td class="px-4 py-3 text-blue-600 font-mono"><?= number_format($row['idf'], 6) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-slate-500">
                                        Belum ada kamus kata. Silakan jalankan perhitungan TF-IDF.
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
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($i = $start; $i <= $end; $i++): 
                        ?>
                            <?php if ($i == $page): ?>
                                <button disabled class="w-8 h-8 flex items-center justify-center rounded-lg border border-blue-600 bg-blue-600 text-white text-sm font-bold shadow-sm"><?= $i ?></button>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 text-sm font-medium hover:bg-slate-100 transition-colors"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

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