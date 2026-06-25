<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Analisis Sentimen</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 text-slate-800">

    <div class="flex min-h-screen">

        <!-- Sidebar -->
        <aside class="w-64 bg-slate-900 text-white">
            <div class="p-6 border-b border-slate-700">
                <h1 class="text-xl font-bold">UAS analisis sentimen</h1>
            </div>

            <nav class="p-4 space-y-2">
                <a href="#" class="block px-4 py-3 rounded-lg bg-blue-600 font-medium">
                    Dashboard
                </a>

                <a href="#" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Input Dataset
                </a>

                <a href="#" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Preprocessing
                </a>

                <a href="#" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Pembobotan TF-IDF
                </a>

                <a href="#" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Data Training
                </a>

                <a href="#" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Data Testing
                </a>

                <a href="#" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Klasifikasi KNN
                </a>

                <a href="#" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Hasil Analisis
                </a>

                <a href="#" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Laporan
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">

            <!-- Header -->
            <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <h2 class="text-2xl font-bold text-slate-900">
                    Dashboard Utama
                </h2>
                <p class="text-slate-500 mt-2">
                    Project UAs Analisis Sentimen
                </p>
            </div>

            <!-- Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Total Dataset</p>
                    <h3 class="text-3xl font-bold text-blue-600 mt-2">120</h3>
                    <p class="text-xs text-slate-400 mt-2">Data komentar/ulasan</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Data Training</p>
                    <h3 class="text-3xl font-bold text-green-600 mt-2">90</h3>
                    <p class="text-xs text-slate-400 mt-2">Data latih algoritma KNN</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Data Testing</p>
                    <h3 class="text-3xl font-bold text-orange-500 mt-2">30</h3>
                    <p class="text-xs text-slate-400 mt-2">Data uji klasifikasi</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Akurasi Model</p>
                    <h3 class="text-3xl font-bold text-purple-600 mt-2">86%</h3>
                    <p class="text-xs text-slate-400 mt-2">Dummy hasil pengujian</p>
                </div>

            </div>

            <!-- Workflow -->
            <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <h3 class="text-xl font-bold text-slate-900 mb-2">
                    Alur Proses Analisis Sentimen
                </h3>
                <p class="text-slate-500 mb-6">
                    Tahapan umum sistem mulai dari input dataset sampai hasil klasifikasi sentimen.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">

                    <div class="border border-blue-100 bg-blue-50 rounded-xl p-4 text-center">
                        <div class="w-10 h-10 mx-auto rounded-full bg-blue-600 text-white flex items-center justify-center font-bold mb-3">
                            1
                        </div>
                        <h4 class="font-semibold text-blue-700">Dataset</h4>
                        <p class="text-sm text-slate-500 mt-2">
                            Input data komentar atau ulasan.
                        </p>
                    </div>

                    <div class="border border-blue-100 bg-blue-50 rounded-xl p-4 text-center">
                        <div class="w-10 h-10 mx-auto rounded-full bg-blue-600 text-white flex items-center justify-center font-bold mb-3">
                            2
                        </div>
                        <h4 class="font-semibold text-blue-700">Preprocessing</h4>
                        <p class="text-sm text-slate-500 mt-2">
                            Cleaning, case folding, tokenizing, stopword, stemming.
                        </p>
                    </div>

                    <div class="border border-blue-100 bg-blue-50 rounded-xl p-4 text-center">
                        <div class="w-10 h-10 mx-auto rounded-full bg-blue-600 text-white flex items-center justify-center font-bold mb-3">
                            3
                        </div>
                        <h4 class="font-semibold text-blue-700">TF-IDF</h4>
                        <p class="text-sm text-slate-500 mt-2">
                            Mengubah teks menjadi nilai bobot numerik.
                        </p>
                    </div>

                    <div class="border border-blue-100 bg-blue-50 rounded-xl p-4 text-center">
                        <div class="w-10 h-10 mx-auto rounded-full bg-blue-600 text-white flex items-center justify-center font-bold mb-3">
                            4
                        </div>
                        <h4 class="font-semibold text-blue-700">KNN</h4>
                        <p class="text-sm text-slate-500 mt-2">
                            Klasifikasi berdasarkan tetangga terdekat.
                        </p>
                    </div>

                    <div class="border border-blue-100 bg-blue-50 rounded-xl p-4 text-center">
                        <div class="w-10 h-10 mx-auto rounded-full bg-blue-600 text-white flex items-center justify-center font-bold mb-3">
                            5
                        </div>
                        <h4 class="font-semibold text-blue-700">Hasil</h4>
                        <p class="text-sm text-slate-500 mt-2">
                            Menampilkan sentimen positif, negatif, atau netral.
                        </p>
                    </div>

                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">
                            Contoh Data Hasil Klasifikasi
                        </h3>
                        <p class="text-slate-500 text-sm mt-1">
                            Data Hasil Proses *Dummy.
                        </p>
                    </div>

                    <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                        Lihat Semua
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">Teks</th>
                                <th class="px-4 py-3">Sentimen</th>
                                <th class="px-4 py-3">Skor</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr>
                                <td class="px-4 py-4">1</td>
                                <td class="px-4 py-4">
                                    Membantu sekali apa si
                                </td>
                                <td class="px-4 py-4">
                                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">
                                        Positif
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-slate-500">0.8</td>
                            </tr>

                            <tr>
                                <td class="px-4 py-4">2</td>
                                <td class="px-4 py-4">
                                    Pelayanannya KURANG memuaskan karena ada apa ya
                                </td>
                                <td class="px-4 py-4">
                                    <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium">
                                        Negatif
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-slate-500">0.2</td>
                            </tr>

                            <tr>
                                <td class="px-4 py-4">3</td>
                                <td class="px-4 py-4">
                                    Fitur yang diberikan cukup standar
                                </td>
                                <td class="px-4 py-4">
                                    <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-medium">
                                        Netral
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-slate-500">0.5</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Footer -->
            <div class="text-center text-sm text-slate-400 mt-8">
                &copy; <?php echo date("Y"); ?> Sistem Analisis Sentimen - Algoritma KNN | UAS Keren
            </div>

        </main>

    </div>

</body>
</html>