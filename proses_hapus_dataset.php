<?php
if (isset($_POST['hapus_dataset'])) {
    
    require 'koneksidb.php';
    
    try {
        // PENTING: Kita pakai TRUNCATE supaya seluruh data hilang 
        // dan nomor ID/Auto Increment kembali lagi mulai dari 1
        $stmt = $pdo->prepare("TRUNCATE TABLE dataset_awal");
        $stmt->execute();
        
        echo "<script>
                alert('Berhasil! Seluruh data dataset telah dihapus.');
                window.location.href='input_dataset.php';
              </script>";
              
    } catch (PDOException $e) {
        echo "<script>
                alert('Gagal menghapus data: " . $e->getMessage() . "');
                window.location.href='input_dataset.php';
              </script>";
    }

} else {
    // Kalau file ini diakses langsung dari URL tanpa klik tombol, tendang balik
    header("Location: input_dataset.php");
    exit();
}
?>