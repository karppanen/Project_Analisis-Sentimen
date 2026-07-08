# Analisis Sentimen NCT

> 📊 Sistem Analisis Sentimen Teks dengan KNN & TF-IDF untuk Ulasan Bahasa Indonesia.

## Deskripsi Proyek

Analisis Sentimen NCT adalah proyek akademik yang ditujukan untuk mengotomatisasi identifikasi sentimen dalam teks ulasan. Sistem ini memecahkan masalah evaluasi opini secara manual dengan mengubah teks mentah menjadi fitur numerik yang dapat dihitung, lalu memprediksi apakah teks tersebut bernada positif, negatif, atau netral.

Sistem memanfaatkan proses _preprocessing_ teks, pembobotan fitur menggunakan TF-IDF, dan klasifikasi K-Nearest Neighbor (KNN) berbasis Cosine Similarity. Proyek ini cocok untuk mahasiswa, dosen, dan praktisi yang ingin mempelajari penerapan analisis sentimen pada dataset berbahasa Indonesia.

## Teknologi yang Digunakan

- **PHP**: Bahasa utama untuk semua logika backend dan interaksi web.
- **MySQL / PDO**: Menyimpan dataset, hasil preprocessing, bobot TF-IDF, serta label prediksi dalam basis data.
- **Tailwind CSS**: Menyediakan antarmuka web yang rapi, responsif, dan konsisten.
- **Sastrawi Stemmer**: Library PHP untuk melakukan stemming kata Bahasa Indonesia.
- **Composer**: Pengelola dependency PHP untuk memuat library Sastrawi dan autoloading.

## Fitur Utama

- **Manajemen Dataset**
  - Unggah dataset `.csv` dan simpan ke database.
  - Tampilkan statistik jumlah data dan distribusi label sentimen.

- **Preprocessing**
  - _Tokenisasi_ teks menjadi kata-kata.
  - _Normalisasi_ kata slang/alay menggunakan kamus normalisasi.
  - _Stopword removal_ untuk menghapus kata-kata umum yang tidak bermakna.
  - _Stemming_ menggunakan Sastrawi untuk mengubah kata ke bentuk dasar.

- **Pembobotan TF-IDF**
  - Split data menjadi 80% latih dan 20% uji.
  - Bangun term vocabulary dari data latih.
  - Hitung bobot TF-IDF untuk setiap dokumen.

- **Klasifikasi KNN**
  - Prediksi label sentimen menggunakan algoritma K-Nearest Neighbor.
  - Mengukur kemiripan vektor dokumen dengan _Cosine Similarity_.
  - Voting mayoritas untuk menentukan hasil klasifikasi.

- **Evaluasi Performa**
  - Tampilkan Confusion Matrix.
  - Hitung metrik: Akurasi, Precision, Recall, dan F1-Score.

## Cara Instalasi

### Prasyarat

- XAMPP atau web server yang mendukung PHP.
- PHP versi 7.x atau 8.x.
- Composer terpasang.
- MySQL / MariaDB.

### Langkah Instalasi

1. Copy atau pindahkan folder proyek ke direktori `htdocs` XAMPP:
   - `C:\xampp\htdocs\Project_Analisis-Sentimen`
2. Buka terminal di folder proyek.
3. Jalankan perintah berikut untuk menginstal dependency:
   ```bash
   composer install
   ```
4. Buka file `db_config.php` dan sesuaikan pengaturan database:
   ```php
   $host = 'localhost';
   $dbname = 'db_uasanalisissentimen';
   $user = 'root';
   $password = '';
   ```
5. Pastikan `koneksidb.php` sudah menggunakan konfigurasi yang benar.
6. Buat database MySQL sesuai nama yang digunakan dan pastikan tabel terkait tersedia.
7. Buka aplikasi melalui browser:
   ```
   http://localhost/Project_Analisis-Sentimen/
   ```

## Panduan Penggunaan

1. **Input Dataset**
   - Buka menu `Input Dataset`.
   - Unggah file CSV yang berisi kolom teks dan label sentimen.

2. **Preprocessing**
   - Buka menu `Preprocessing`.
   - Jalankan proses preprocessing untuk membersihkan teks dan menyimpannya ke kolom `teks_bersih`.

3. **Split Data**
   - Buka menu `Pembobotan TF-IDF`.
   - Klik `Proses Split Data 80:20` untuk membagi data menjadi data latih dan data uji.

4. **Hitung TF-IDF**
   - Masih di menu `Pembobotan TF-IDF`, klik `Hitung Bobot TF-IDF`.
   - Sistem akan membuat kamus kata dari data latih dan menghitung bobot TF-IDF.

5. **Klasifikasi KNN**
   - Buka menu `Klasifikasi KNN`.
   - Masukkan nilai `K` (disarankan bilangan ganjil) lalu jalankan klasifikasi.

6. **Evaluasi**
   - Buka menu `Evaluasi Performa`.
   - Lihat Confusion Matrix dan metrik evaluasi untuk menilai kualitas model.

## Struktur Folder

```
Project_Analisis-Sentimen/
├── composer.json
├── composer.lock
├── db_config.php
├── koneksidb.php
├── stopword_array.php
├── fungsi_preprocessing.php
├── input_dataset.php
├── preprocessing.php
├── tfidf.php
├── klasifikasi.php
├── evaluasi.php
├── proses_upload.php
├── proses_run_preprocessing.php
├── proses_split_data.php
├── proses_tfidf.php
├── proses_klasifikasi.php
├── proses_reset_preprocessing.php
├── proses_hapus_dataset.php
├── sidebar.php
├── readme-instructions.md
├── README_NEW.md
└── vendor/
```

## Kontributor

- Fauzi Alfadhillah
- Adinda Kusuma Dewi
- Cantika Amalia
- Muhammad Rifqi Fauzan

## Lisensi / Catatan

Proyek ini dibuat sebagai tugas akademik untuk mata kuliah Analisis Sentimen. Kode dan dokumentasi disusun untuk tujuan pembelajaran dan evaluasi, bukan untuk penggunaan komersial tanpa izin.
