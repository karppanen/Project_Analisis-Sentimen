<?php
// 1. Panggil koneksi database (untuk ambil data kamus normalisasi)
require_once 'koneksidb.php';

// 2. Panggil file stopword (pastikan di dalamnya ada variabel $daftar_stopword)
require_once 'stopword_array.php'; 

// 3. Wajib panggil file autoload dari Composer biar Sastrawi-nya kebaca
require_once __DIR__ . '/vendor/autoload.php';

// 4. Inisialisasi Stemmer Sastrawi (Cukup sekali di luar function biar memori aman)
$stemmerFactory = new \Sastrawi\Stemmer\StemmerFactory();
$stemmer  = $stemmerFactory->createStemmer();

// 5. Load Kamus Normalisasi dari Database (Cukup sekali)
$kamus_normalisasi = [];
$stmt_kamus = $pdo->query("SELECT kata_alay, kata_baku FROM kamus_normalisasi");
while ($row = $stmt_kamus->fetch()) {
    $kamus_normalisasi[$row['kata_alay']] = $row['kata_baku'];
}

/**
 * FUNGSI UTAMA PREPROCESSING
 * Tinggal panggil fungsi ini dari file lain: preprocessing($teks, $stemmer, $daftar_stopword, $kamus_normalisasi)
 */
function preprocessing($teks, $stemmer, $daftar_stopword, $kamus_normalisasi) {
    
    // Tahap 1: Case Folding (Ubah ke huruf kecil)
    $teks = strtolower($teks);
    
    // Tahap 2: Punctuation Removal (Hapus tanda baca DAN ANGKA, biarkan a-z dan spasi saja)
    $teks = preg_replace('/[^a-z\s]/', ' ', $teks);
    
    // Hapus spasi yang berlebihan akibat penghapusan tanda baca
    $teks = preg_replace('/\s+/', ' ', $teks);
    $teks = trim($teks);
    
    // Tahap 3: Tokenisasi (Pecah kalimat jadi array per kata)
    $tokens = explode(" ", $teks);
    
    // Tahap 4 & 5: Normalisasi & Spelling Correction
    $tokens_normal = [];
    foreach ($tokens as $kata) {
        // Cek apakah kata alay/typo ini ada di database kamus kita
        if (array_key_exists($kata, $kamus_normalisasi)) {
            // Kalau ada, ubah jadi kata baku
            $tokens_normal[] = $kamus_normalisasi[$kata];
        } else {
            // Kalau nggak ada, biarin aja
            $tokens_normal[] = $kata;
        }
    }
    
    // Tahap 6: Stopwords Removal
    $tokens_bersih = [];
    foreach ($tokens_normal as $kata) {
        // Cek apakah kata ini BUKAN stopword dan bukan string kosong
        if (!in_array($kata, $daftar_stopword) && $kata != "") {
            $tokens_bersih[] = $kata;
        }
    }
    
    // Gabungkan kembali array token jadi 1 kalimat string untuk di-stemming
    $teks_gabung = implode(" ", $tokens_bersih);
    
    // Tahap 7: Stemming (Menggunakan Sastrawi)
    $hasil_akhir = $stemmer->stem($teks_gabung);
    
    return $hasil_akhir;
}

// =========================================================================
// CONTOH PENGGUNAAN (Bisa kamu hapus/comment kalau projectnya udah jalan)
// =========================================================================
/*
$teks_uji = "Mhs mengerjakan ujian dgn sungguh-sungguh, jgn ada yg nyontek bgt!!!";
echo "<strong>Teks Asli:</strong> " . $teks_uji . "<br><br>";

$hasil_prepo = preprocessing($teks_uji, $stemmer, $daftar_stopword, $kamus_normalisasi);
echo "<strong>Hasil Preprocessing:</strong> " . $hasil_prepo; 
*/
?>