<?php
require 'koneksidb.php';

if (isset($_POST['split_data'])) {
    try {
        // 1. Hitung total data yang sudah bersih dan ada labelnya
        $stmtTotal = $pdo->query("SELECT COUNT(*) FROM dataset_awal WHERE teks_bersih IS NOT NULL AND teks_bersih != '' AND label IS NOT NULL");
        $totalData = $stmtTotal->fetchColumn();

        if ($totalData == 0) {
            echo "<script>
                    alert('Gagal! Belum ada data yang dipreprocessing atau dilabeli. Selesaikan tahap Preprocessing dulu.');
                    window.location.href='preprocessing.php';
                  </script>";
            exit();
        }

        // 2. Hitung porsi 80% Latih dan 20% Uji
        $jumlahLatih = round(0.8 * $totalData);
        $jumlahUji = $totalData - $jumlahLatih; 

        // 3. Reset status jenis_data jadi NULL semua dulu biar bersih (jika diulang)
        $pdo->query("UPDATE dataset_awal SET jenis_data = NULL");

        // 4. Ambil ID data secara ACAK (Random)
        $stmtIds = $pdo->query("SELECT id FROM dataset_awal WHERE teks_bersih IS NOT NULL AND teks_bersih != '' AND label IS NOT NULL ORDER BY RAND()");
        $allIds = $stmtIds->fetchAll(PDO::FETCH_COLUMN);

        // 5. Update 80% data pertama jadi 'Latih'
        $latihIds = array_slice($allIds, 0, $jumlahLatih);
        if (count($latihIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($latihIds), '?'));
            $stmtUpdateLatih = $pdo->prepare("UPDATE dataset_awal SET jenis_data = 'Latih' WHERE id IN ($placeholders)");
            $stmtUpdateLatih->execute($latihIds);
        }

        // 6. Update 20% sisanya jadi 'Uji'
        $ujiIds = array_slice($allIds, $jumlahLatih);
        if (count($ujiIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($ujiIds), '?'));
            $stmtUpdateUji = $pdo->prepare("UPDATE dataset_awal SET jenis_data = 'Uji' WHERE id IN ($placeholders)");
            $stmtUpdateUji->execute($ujiIds);
        }

        echo "<script>
                alert('Berhasil Split Data! Data Latih: $jumlahLatih baris (80%), Data Uji: $jumlahUji baris (20%).');
                window.location.href='tfidf.php'; 
              </script>";

    } catch (PDOException $e) {
        echo "<script>
                alert('Terjadi kesalahan database: " . $e->getMessage() . "');
                window.location.href='tfidf.php';
              </script>";
    }
} else {
    // Kalau diakses langsung via URL, tendang balik ke tfidf
    header("Location: tfidf.php");
    exit();
}
?>