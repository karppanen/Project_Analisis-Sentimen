<?php
include 'koneksidb.php'; 

if (isset($_POST['upload'])) {
    if (isset($_FILES['file_dataset']) && $_FILES['file_dataset']['error'] == 0) {
        
        $file_tmp = $_FILES['file_dataset']['tmp_name'];
        
        if (($handle = fopen($file_tmp, "r")) !== FALSE) {
            
            // Pemisahnya di file temanmu pakai titik koma
            $delimiter = ";"; 
            
            // Lewati baris pertama (header: id;full_text;label_draft)
            fgetcsv($handle, 1000, $delimiter); 

            // Query insert untuk memasukkan TEKS dan LABEL-nya sekaligus
            $query = "INSERT INTO dataset_awal (teks, label) VALUES (:teks, :label)";
            $stmt = $pdo->prepare($query);

            $baris_sukses = 0;
            $baris_gagal = 0;

            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                
                // Di CSV kamu: 
                // $data[0] = id
                // $data[1] = full_text (ulasan)
                // $data[2] = label_draft (sentimen)
                if (isset($data[1]) && isset($data[2]) && trim($data[1]) !== '') {
                    try {
                        $stmt->execute([
                            ':teks' => trim($data[1]),
                            ':label' => trim($data[2])
                        ]);
                        $baris_sukses++;
                    } catch (PDOException $e) {
                        $baris_gagal++;
                    }
                } else {
                    $baris_gagal++;
                }
            }
            
            fclose($handle);
            echo "<script>
                    alert('Proses Selesai! Berhasil masuk: $baris_sukses data berlabel. Gagal: $baris_gagal data.'); 
                    window.location.href='input_dataset.php';
                  </script>";
        }
    }
}
?>