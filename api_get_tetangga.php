<?php
require 'koneksidb.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id_uji = (int)$_GET['id'];

    try {
        // Query Join: Ambil skor dari tabel hasil_cosine, dan ambil teks aslinya dari tabel dataset_awal
        $query = "
            SELECT 
                h.urutan, 
                h.score, 
                d.teks_bersih, 
                d.label 
            FROM hasil_cosine_similarity h
            JOIN dataset_awal d ON h.id_latih = d.id
            WHERE h.id_uji = :id_uji
            ORDER BY h.urutan ASC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':id_uji', $id_uji, PDO::PARAM_INT);
        $stmt->execute();
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($data);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'ID tidak ditemukan.']);
}
?>