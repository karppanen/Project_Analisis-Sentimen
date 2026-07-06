<?php
set_time_limit(0);
ini_set('memory_limit', '512M');
require 'koneksidb.php';

if (isset($_POST['jalankan_knn'])) {
    $K = (int)$_POST['nilai_k'];

    try {
        // 1. Ambil info label asli dari Data Latih sebagai kunci pembanding tetangga
        $stmtLatih = $pdo->query("SELECT id, label FROM dataset_awal WHERE jenis_data = 'Latih'");
        $labelsLatih = $stmtLatih->fetchAll(PDO::FETCH_KEY_PAIR); // Struktur array langsung: [id => label]

        if (count($labelsLatih) == 0) {
            throw new Exception("Data Latih tidak ditemukan atau belum displit!");
        }

        // 2. Load struktur matriks bobot TF-IDF dari database ke dalam memori PHP biar eksekusi cepat
        // Ambil data latih
        $stmtBobotLatih = $pdo->query("SELECT id_dataset, term, bobot FROM bobot_tfidf WHERE jenis_data = 'Latih'");
        $matrixLatih = [];
        $magLatih = []; // Menyimpan magnitude/panjang vektor dokumen latih
        while ($row = $stmtBobotLatih->fetch(PDO::FETCH_ASSOC)) {
            $id = $row['id_dataset'];
            $term = $row['term'];
            $bobot = (double)$row['bobot'];

            $matrixLatih[$id][$term] = $bobot;
            if (!isset($magLatih[$id])) $magLatih[$id] = 0;
            $magLatih[$id] += $bobot * $bobot;
        }

        // Ambil data uji
        $stmtBobotUji = $pdo->query("SELECT id_dataset, term, bobot FROM bobot_tfidf WHERE jenis_data = 'Uji'");
        $matrixUji = [];
        $magUji = []; // Menyimpan magnitude/panjang vektor dokumen uji
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

        // Siapkan kueri update label prediksi sistem
        $stmtUpdate = $pdo->prepare("UPDATE dataset_awal SET label_prediksi = :prediksi WHERE id = :id");

        // 3. Mulai Perhitungan Cosine Similarity untuk tiap Data Uji
        foreach ($matrixUji as $idUji => $vectorUji) {
            $scores = []; // Untuk menampung skor similarity dengan seluruh dokumen latih
            $magnitudeUji = $magUji[$idUji];

            if ($magnitudeUji == 0) {
                // Jika data uji kosong melompong isinya setelah dibersihkan
                $scores = array_fill_keys(array_keys($matrixLatih), 0);
            } else {
                foreach ($matrixLatih as $idLatih => $vectorLatih) {
                    $magnitudeLatih = $magLatih[$idLatih];
                    
                    // Hitung Dot Product (Hanya kalikan term yang beririsan antara uji dan latih)
                    $dotProduct = 0;
                    foreach ($vectorUji as $term => $bobotUji) {
                        if (isset($vectorLatih[$term])) {
                            $dotProduct += $bobotUji * $vectorLatih[$term];
                        }
                    }

                    // Rumus Rumus Cosine Similarity
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

            // Lakukan voting mayoritas label
            $votes = ['Positif' => 0, 'Negatif' => 0, 'Netral' => 0];
            foreach ($tetanggaTerdekat as $idLatih => $score) {
                $labelTetangga = $labelsLatih[$idLatih];
                $votes[$labelTetangga]++;
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