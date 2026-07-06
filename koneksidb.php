<?php
require 'db_config.php';

try {
    // 1. Ubah pgsql jadi mysql
    // 2. Hapus port dan sslmode karena XAMPP biasanya pakai port default (3306) tanpa SSL
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname", 
        $user, 
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Kalau udah yakin jalan, baris echo ini mending di-comment aja biar nggak ngerusak tampilan web kamu nanti
    // echo "Koneksi berhasil";

} catch(PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}
?>