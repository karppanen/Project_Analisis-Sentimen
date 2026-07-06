<?php
// PENTING: Matikan batas waktu eksekusi PHP!
// Karena library Stemmer Sastrawi itu lumayan berat, kalau datanya ribuan bisa bikin loading lama dan time-out.
set_time_limit(0); 

if (isset($_POST['run_preprocessing'])) {
    
    // Panggil file yang isinya koneksi, array stopword, dan rumus preprocessing tadi
    require 'fungsi_preprocessing.php';

    // --- TAMBAHKAN BARIS INI BISA BIAR VS CODE & PHP NGGAK BINGUNG ---
    require_once 'stopword_array.php'; 
    global $daftar_stopword; 
    global $kamus_normalisasi;
    // -----------------------------------------------------------------
    
    // Ambil semua data teks asli dari database
    $stmt = $pdo->query("SELECT id, teks FROM dataset_awal");
    $dataset = $stmt->fetchAll();

    $sukses = 0;

    // Siapkan query untuk update hasil pembersihan ke kolom 'teks_bersih'
    $updateStmt = $pdo->prepare("UPDATE dataset_awal SET teks_bersih = :bersih WHERE id = :id");

    // Looping datanya satu per satu
    foreach ($dataset as $row) {
        
        // Panggil fungsi preprocessing dari file fungsi_preprocessing.php
        $hasil_bersih = preprocessing($row['teks'], $stemmer, $daftar_stopword, $kamus_normalisasi);
        
        // Update database dengan teks yang sudah bersih
        $updateStmt->execute([
            ':bersih' => $hasil_bersih,
            ':id' => $row['id']
        ]);
        
        $sukses++;
    }

    // Kalau udah kelar semua, kasih alert dan balikin ke halaman tabel
    echo "<script>
            alert('Berhasil memproses $sukses baris data.');
            window.location.href='preprocessing.php';
          </script>";
} else {
    // Kalau ada yang iseng buka file ini langsung lewat URL, lempar balik ke halaman preprocessing
    header("Location: preprocessing.php");
    exit();
}
?>