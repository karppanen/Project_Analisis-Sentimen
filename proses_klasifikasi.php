<?php
session_start();
set_time_limit(0);
ini_set('memory_limit', '512M');
require 'koneksidb.php';

if (isset($_POST['jalankan_knn'])) {
    $K = (int)$_POST['nilai_k'];
    $_SESSION['last_knn_k'] = $K;

    try {
        // Kosongkan tabel hasil score sebelumnya biar nggak numpuk
        $pdo->exec("TRUNCATE TABLE hasil_cosine_similarity");

        // [BARU] Ambil SEMUA ID Data Uji asli yang ada di database untuk validasi nanti
        $stmtAllUji = $pdo->query("SELECT id FROM dataset_awal WHERE jenis_data = 'Uji'");
        $allUjiIds = $stmtAllUji->fetchAll(PDO::FETCH_COLUMN);
        $processedIds = []; // Untuk mencatat ID mana saja yang sukses dihitung Cosine-nya

        // 1. Ambil info label asli dari Data Latih sebagai kunci pembanding tetangga
        $stmtLatih = $pdo->query("SELECT id, label FROM dataset_awal WHERE jenis_data = 'Latih'");
        $labelsLatih = $stmtLatih->fetchAll(PDO::FETCH_KEY_PAIR); 

        if (count($labelsLatih) == 0) {
            throw new Exception("Data Latih tidak ditemukan atau belum displit!");
        }

        // 2. Load struktur matriks bobot TF-IDF dari database ke dalam memori
        $stmtBobotLatih = $pdo->query("SELECT id_dataset, term, bobot FROM bobot_tfidf WHERE jenis_data = 'Latih'");
        $matrixLatih = [];
        $magLatih = []; 
        while ($row = $stmtBobotLatih->fetch(PDO::FETCH_ASSOC)) {
            $id = $row['id_dataset'];
            $term = $row['term'];
            $bobot = (double)$row['bobot'];

            $matrixLatih[$id][$term] = $bobot;
            if (!isset($magLatih[$id])) $magLatih[$id] = 0;
            $magLatih[$id] += $bobot * $bobot;
        }

        $stmtBobotUji = $pdo->query("SELECT id_dataset, term, bobot FROM bobot_tfidf WHERE jenis_data = 'Uji'");
        $matrixUji = [];
        $magUji = []; 
        while ($row = $stmtBobotUji->fetch(PDO::FETCH_ASSOC)) {
            $id = $row['id_dataset'];
            $term = $row['term'];
            $bobot = (double)$row['bobot'];

            $matrixUji[$id][$term] = $bobot;
            if (!isset($magUji[$id])) $magUji[$id] = 0;
            $magUji[$id] += $bobot * $bobot;
        }

        // Akarkan semua nilai magnitude ulasan
        foreach ($magLatih as $id => $val) { $magLatih[$id] = sqrt($val); }
        foreach ($magUji as $id => $val) { $magUji[$id] = sqrt($val); }

        // Siapkan kueri update dan insert
        $stmtUpdate = $pdo->prepare("UPDATE dataset_awal SET label_prediksi = :prediksi WHERE id = :id");
        $stmtInsertScore = $pdo->prepare("INSERT INTO hasil_cosine_similarity (id_uji, id_latih, score, urutan) VALUES (:id_uji, :id_latih, :score, :urutan)");

        // 3. Mulai Perhitungan Cosine Similarity untuk tiap Data Uji
        foreach ($matrixUji as $idUji => $vectorUji) {
            $processedIds[] = $idUji; // Catat ID yang berhasil diproses secara normal
            
            $scores = []; 
            $magnitudeUji = $magUji[$idUji];

            if ($magnitudeUji == 0) {
                $scores = array_fill_keys(array_keys($matrixLatih), 0);
            } else {
                foreach ($matrixLatih as $idLatih => $vectorLatih) {
                    $magnitudeLatih = $magLatih[$idLatih];
                    
                    // Hitung Dot Product
                    $dotProduct = 0;
                    foreach ($vectorUji as $term => $bobotUji) {
                        if (isset($vectorLatih[$term])) {
                            $dotProduct += $bobotUji * $vectorLatih[$term];
                        }
                    }

                    // Rumus Cosine Similarity
                    if ($magnitudeLatih > 0) {
                        $scores[$idLatih] = $dotProduct / ($magnitudeUji * $magnitudeLatih);
                    } else {
                        $scores[$idLatih] = 0;
                    }
                }
            }

            // Urutkan skor kemiripan dari yang PALING TINGGI (Descending)
            arsort($scores);

            // Ambil K tetangga teratas
            $tetanggaTerdekat = array_slice($scores, 0, $K, true);

            // Lakukan voting mayoritas label DAN simpan skornya
            $votes = ['Positif' => 0, 'Negatif' => 0, 'Netral' => 0];
            $urutan = 1;
            
            foreach ($tetanggaTerdekat as $idLatih => $score) {
                $labelTetangga = $labelsLatih[$idLatih];
                $votes[$labelTetangga]++;

                $stmtInsertScore->execute([
                    ':id_uji' => $idUji,
                    ':id_latih' => $idLatih,
                    ':score' => $score,
                    ':urutan' => $urutan
                ]);
                $urutan++;
            }

            // Cari label pemenang vote terbanyak
            arsort($votes);
            $labelPemenang = key($votes);

            // Simpan label tebakan KNN ke database
            $stmtUpdate->execute([
                ':prediksi' => $labelPemenang,
                ':id' => $idUji
            ]);
        }

        // [BARU] Deteksi ID mana saja yang terlewat (Kata-katanya OOV / Tidak ada di Kamus)
        $skippedIds = array_diff($allUjiIds, $processedIds);
        if (count($skippedIds) > 0) {
            // Berikan prediksi Default 'Netral' karena merupakan kelas mayoritas dataset
            $stmtDefault = $pdo->prepare("UPDATE dataset_awal SET label_prediksi = 'Netral' WHERE id = :id");
            foreach ($skippedIds as $idSkipped) {
                $stmtDefault->execute([':id' => $idSkipped]);
            }
        }

        echo "<script>
                alert('Proses Klasifikasi KNN Cosine Similarity Selesai dengan Nilai K = $K!');
                window.location.href='klasifikasi.php';
              </script>";

    } catch (Exception $e) {
        echo "<script>
                alert('Gagal: " . $e->getMessage() . "');
                window.location.href='klasifikasi.php';
              </script>";
    }
} else {
    header("Location: klasifikasi.php");
    exit();
}
?>