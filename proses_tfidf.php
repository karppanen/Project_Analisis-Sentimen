<?php
// Bebaskan limit waktu dan memori karena proses ini berat
set_time_limit(0);
ini_set('memory_limit', '512M');

require 'koneksidb.php';

if (isset($_POST['run_tfidf'])) {
    try {
        // 1. Kosongkan tabel TF-IDF lama supaya tidak dobel
        // PENTING: TRUNCATE harus ditaruh sebelum beginTransaction karena memicu auto-commit di MySQL
        $pdo->exec("TRUNCATE TABLE term_vocabulary");
        $pdo->exec("TRUNCATE TABLE bobot_tfidf");

        // Mulai transaksi database biar aman kalau di tengah jalan ada error
        $pdo->beginTransaction();

        // 2. Ambil DATA LATIH saja untuk menyusun Kamus (Vocabulary)
        $stmtLatih = $pdo->query("SELECT id, teks_bersih FROM dataset_awal WHERE jenis_data = 'Latih' AND teks_bersih IS NOT NULL AND teks_bersih != ''");
        $dataLatih = $stmtLatih->fetchAll(PDO::FETCH_ASSOC);
        $N = count($dataLatih);

        // Kalau data latih 0, lempar error (ini yang ngetrigger error kamu tadi karena belum di-split)
        if ($N == 0) {
            throw new Exception("Data Latih tidak ditemukan! Lakukan split data 80:20 terlebih dahulu di halaman Preprocessing.");
        }

        $df_array = []; // Menyimpan Document Frequency (DF)

        // Hitung DF (Berapa banyak dokumen latih yang mengandung kata tersebut)
        foreach ($dataLatih as $row) {
            $teks = trim($row['teks_bersih']);
            $kata_array = explode(" ", $teks);
            
            // Pakai array_unique agar kata yang berulang di 1 dokumen cuma dihitung 1x DF-nya
            $kata_unik = array_unique($kata_array); 

            foreach ($kata_unik as $kata) {
                $kata = trim($kata);
                if ($kata != "") {
                    if (!isset($df_array[$kata])) {
                        $df_array[$kata] = 0;
                    }
                    $df_array[$kata]++;
                }
            }
        }

        // Hitung IDF dan Masukkan ke tabel term_vocabulary
        $stmtInsertVocab = $pdo->prepare("INSERT INTO term_vocabulary (term, df, idf) VALUES (:term, :df, :idf)");
        $idf_dict = []; // Kita simpan ke variabel dict untuk perhitungan TF-IDF nanti
        
        foreach ($df_array as $term => $df) {
            // Rumus IDF: log10(Total Dokumen / DF)
            $idf = log10($N / $df);
            $idf_dict[$term] = $idf;

            $stmtInsertVocab->execute([
                ':term' => $term,
                ':df' => $df,
                ':idf' => $idf
            ]);
        }

        // 3. Hitung TF dan Bobot Akhir (TF * IDF) untuk SELURUH DATA (Latih & Uji)
        $stmtAll = $pdo->query("SELECT id, jenis_data, teks_bersih FROM dataset_awal WHERE teks_bersih IS NOT NULL AND teks_bersih != ''");
        $dataAll = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        $stmtInsertBobot = $pdo->prepare("INSERT INTO bobot_tfidf (id_dataset, jenis_data, term, tf, bobot) VALUES (:id_dataset, :jenis_data, :term, :tf, :bobot)");

        foreach ($dataAll as $row) {
            $id_dataset = $row['id'];
            $jenis_data = $row['jenis_data'];
            $teks = trim($row['teks_bersih']);

            $kata_array = explode(" ", $teks);
            
            // Hitung Term Frequency (TF) per dokumen
            $tf_array = array_count_values($kata_array); 

            foreach ($tf_array as $term => $tf) {
                $term = trim($term);
                
                // Cek apakah kata ini ada di kamus Data Latih (idf_dict)
                // Jika Data Uji punya kata baru yang tidak pernah dilihat saat latih, kata itu akan di-skip (aturan baku ML)
                if ($term != "" && isset($idf_dict[$term])) {
                    $idf = $idf_dict[$term];
                    $bobot = $tf * $idf; // Bobot = TF * IDF

                    $stmtInsertBobot->execute([
                        ':id_dataset' => $id_dataset,
                        ':jenis_data' => $jenis_data,
                        ':term' => $term,
                        ':tf' => $tf,
                        ':bobot' => $bobot
                    ]);
                }
            }
        }

        $pdo->commit();

        echo "<script>
                alert('Proses Pembobotan TF-IDF Berhasil!');
                window.location.href='tfidf.php';
              </script>";

    } catch (Exception $e) {
        // Cek dulu apakah masih ada transaksi aktif sebelum nge-rollback (Biar gak kena error PDOException)
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<script>
                alert('Gagal: " . $e->getMessage() . "');
                window.location.href='tfidf.php';
              </script>";
    }
} else {
    header("Location: tfidf.php");
    exit();
}
?>