<?php
// Daftar stopword ekstensif untuk analisis sentimen bahasa Indonesia & Sosmed
$daftar_stopword = array(
    // Pronoun (Kata Ganti)
    "saya", "aku", "ku", "kamu", "mu", "dia", "nya", "kita", "kami", "mereka", "kalian", "anda", "beliau",
    
    // Preposisi & Konjungsi (Kata Depan & Hubung)
    "di", "ke", "dari", "pada", "dalam", "untuk", "dengan", "oleh", "atas", "antara", "tentang", "bagi",
    "dan", "atau", "tetapi", "tapi", "namun", "melainkan", "sedangkan", "serta",
    
    // Kata Penunjuk
    "ini", "itu", "sini", "situ", "sana", "begini", "begitu", "sini", "sana",
    
    // Kata Keterangan Waktu & Aspek
    "sudah", "telah", "belum", "sedang", "akan", "lagi", "baru", "pernah", "segera", "selalu", "sering", "kadang",
    "saat", "ketika", "waktu", "setelah", "sesudah", "sebelum", "sejak", "hingga", "sampai", "kemudian", "lalu",
    
    // Kata Keterangan & Partikel
    "sangat", "amat", "sekali", "terlalu", "paling", "agak", "lumayan",
    "pun", "lah", "kah", "tah", "dong", "sih", "deh", "kok", "nah", "kan", "lho", "loh", "kek", "toh",
    
    // Kata Tugas & Penghubung Lainnya
    "yaitu", "yakni", "adalah", "ialah", "merupakan", "sebagai",
    "karena", "sebab", "jadi", "maka", "sehingga", "kalau", "jika", "jikalau", "bila", "apabila",
    "biar", "agar", "supaya", "meski", "meskipun", "walau", "walaupun",
    "seperti", "macam", "bagaikan", "ibarat", "umpama",
    "hal", "cara", "buat", "ada",
    "bisa", "dapat", "mampu", "harus", "wajib", "mesti", "boleh", "mungkin", "pasti",
    "apa", "siapa", "mengapa", "kenapa", "bagaimana", "mana", "kapan", "berapa",
    
    // Kata Umum / Slang Sosmed (yang biasanya tidak mengubah arah sentimen)
    "yg", "aja", "jd", "udah", "blm", "kalo", "utk", "dgn", "dr", "krn", "tp", "nih", "tuh", "pas", "emang", "memang",
    "terus", "trs", "bgt", "banget", "bikin", "doang", "kayak", "kyk", "ya", "yah", "yo", "oh", "eh", "hmm", "wow",
    "nya", "sih", "klo", "jgn", "udh", "gitu", "gini", "begitu", "begini", "sih", "mah", "sok", "yaa", "yee"
);
?>