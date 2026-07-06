<aside class="w-64 bg-slate-900 text-white min-h-screen">
    <div class="p-6 border-b border-slate-700">
        <h1 class="text-xl font-bold">UAS Analisis Sentimen</h1>
    </div>

    <nav class="p-4 space-y-2">
        <a href="formload.php" class="block px-4 py-3 rounded-lg font-medium transition-colors <?php echo ($menu_aktif == 'dashboard') ? 'bg-blue-600 text-white shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
            Dashboard
        </a>

        <a href="input_dataset.php" class="block px-4 py-3 rounded-lg font-medium transition-colors <?php echo ($menu_aktif == 'input_dataset') ? 'bg-blue-600 text-white shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
            Input Dataset
        </a>

        <a href="preprocessing.php" class="block px-4 py-3 rounded-lg font-medium transition-colors <?php echo ($menu_aktif == 'preprocessing') ? 'bg-blue-600 text-white shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
            Preprocessing
        </a>

        <a href="tfidf.php" class="block px-4 py-3 rounded-lg font-medium transition-colors <?php echo ($menu_aktif == 'tfidf') ? 'bg-blue-600 text-white shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
            Pembobotan TF-IDF
        </a>

        <a href="klasifikasi.php" class="block px-4 py-3 rounded-lg font-medium transition-colors <?php echo ($menu_aktif == 'klasifikasi') ? 'bg-blue-600 text-white shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
            Klasifikasi KNN
        </a>

        <a href="evaluasi.php" class="block px-4 py-3 rounded-lg font-medium transition-colors <?php echo ($menu_aktif == 'evaluasi') ? 'bg-blue-600 text-white shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
            Evaluasi Performa
        </a>
    </nav>
</aside>