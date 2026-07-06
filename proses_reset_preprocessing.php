<?php
if (isset($_POST['reset_preprocessing'])) {
    
    // Panggil koneksi
    require 'koneksidb.php';
    
    try {
        // Query untuk mengosongkan (set NULL) hanya pada kolom teks_bersih
        $stmt = $pdo->prepare("UPDATE dataset_awal SET teks_bersih = NULL");
        $stmt->execute();
        
        echo "<script>
                alert('Berhasil! Hasil preprocessing telah direset/dikosongkan.');
                window.location.href='preprocessing.php';
              </script>";
              
    } catch (PDOException $e) {
        echo "<script>
                alert('Gagal mereset data: " . $e->getMessage() . "');
                window.location.href='preprocessing.php';
              </script>";
    }

} else {
    header("Location: preprocessing.php");
    exit();
}
?>