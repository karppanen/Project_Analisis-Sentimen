<?php
require '../db_config.php';

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

$message = '';
$messageType = 'success';
$previewRows = [];

function getPdoConnection(): PDO
{
    global $host, $port, $dbname, $user, $password;

    return new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function normalizeWhitespace(string $text): string
{
    return trim(preg_replace('/\s+/', ' ', $text) ?? '');
}

function caseFoldText(string $text): string
{
    return function_exists('mb_strtolower')
        ? mb_strtolower($text, 'UTF-8')
        : strtolower($text);
}

function punctuationRemovalText(string $text): string
{
    $text = preg_replace('/https?:\/\/\S+|www\.\S+/i', ' ', $text) ?? $text;
    $text = preg_replace('/@[A-Za-z0-9_]+/', ' ', $text) ?? $text;
    $text = preg_replace('/#/', ' ', $text) ?? $text;
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;

    return normalizeWhitespace($text);
}

function tokenizeText(string $text): array
{
    if ($text === '') {
        return [];
    }

    return preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

function loadStopwords(PDO $pdo): array
{
    $rows = $pdo
        ->query("SELECT stopword FROM stopwords_kelompok_knn")
        ->fetchAll(PDO::FETCH_COLUMN);

    $stopwords = [];

    foreach ($rows as $row) {
        $word = caseFoldText(trim((string) $row));

        if ($word !== '') {
            $stopwords[$word] = true;
        }
    }

    return $stopwords;
}

function loadNormalisasiDictionary(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT kata_tidak_baku, kata_baku
        FROM normalisasi_kelompok_knn
    ");

    $dictionary = [];

    while ($row = $stmt->fetch()) {
        $kataTidakBaku = caseFoldText(trim((string) ($row['kata_tidak_baku'] ?? '')));
        $kataBaku = caseFoldText(trim((string) ($row['kata_baku'] ?? '')));

        if ($kataTidakBaku !== '' && $kataBaku !== '') {
            $dictionary[$kataTidakBaku] = $kataBaku;
        }
    }

    return $dictionary;
}

function loadSpellingDictionary(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT kata_salah, kata_benar
        FROM spelling_correction_kelompok_knn
    ");

    $dictionary = [];

    while ($row = $stmt->fetch()) {
        $kataSalah = caseFoldText(trim((string) ($row['kata_salah'] ?? '')));
        $kataBenar = caseFoldText(trim((string) ($row['kata_benar'] ?? '')));

        if ($kataSalah !== '' && $kataBenar !== '') {
            $dictionary[$kataSalah] = $kataBenar;
        }
    }

    return $dictionary;
}

function loadPositiveLexicon(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT kata, weight
        FROM lexicon_positive_kelompok_knn
    ");

    $lexicon = [];

    while ($row = $stmt->fetch()) {
        $kata = caseFoldText(trim((string) ($row['kata'] ?? '')));
        $weight = (int) ($row['weight'] ?? 0);

        if ($kata !== '' && $weight !== 0) {
            $lexicon[$kata] = $weight;
        }
    }

    return $lexicon;
}

function loadNegativeLexicon(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT kata, weight
        FROM lexicon_negative_kelompok_knn
    ");

    $lexicon = [];

    while ($row = $stmt->fetch()) {
        $kata = caseFoldText(trim((string) ($row['kata'] ?? '')));
        $weight = (int) ($row['weight'] ?? 0);

        if ($kata !== '' && $weight !== 0) {
            $lexicon[$kata] = $weight;
        }
    }

    return $lexicon;
}

function removeStopwordsFromText(string $text, array $stopwords): string
{
    $tokens = tokenizeText($text);

    $filteredTokens = array_values(array_filter(
        $tokens,
        static fn($token) => $token !== '' && !isset($stopwords[$token])
    ));

    return implode(' ', $filteredTokens);
}

function applyDictionaryToText(string $text, array $dictionary): string
{
    $tokens = tokenizeText($text);

    $convertedTokens = array_map(
        static fn($token) => $dictionary[$token] ?? $token,
        $tokens
    );

    return normalizeWhitespace(implode(' ', $convertedTokens));
}

function createStemmer()
{
    if (class_exists('\Sastrawi\Stemmer\StemmerFactory')) {
        $stemmerFactory = new \Sastrawi\Stemmer\StemmerFactory();
        return $stemmerFactory->createStemmer();
    }

    return null;
}

function stemmingText(string $text, $stemmer = null): string
{
    if ($text === '') {
        return '';
    }

    if ($stemmer !== null) {
        return normalizeWhitespace($stemmer->stem($text));
    }

    return $text;
}

function countPhraseOccurrences(string $text, string $phrase): int
{
    $phrase = trim($phrase);

    if ($phrase === '') {
        return 0;
    }

    $pattern = '/(?<!\p{L})' . preg_quote($phrase, '/') . '(?!\p{L})/u';

    if (preg_match_all($pattern, $text, $matches)) {
        return count($matches[0]);
    }

    return 0;
}

function calculateSentimentScore(string $text, array $positiveLexicon, array $negativeLexicon): int
{
    $text = normalizeWhitespace(caseFoldText($text));
    $score = 0;

    foreach ($positiveLexicon as $kata => $weight) {
        $count = countPhraseOccurrences($text, $kata);

        if ($count > 0) {
            $score += $weight * $count;
        }
    }

    foreach ($negativeLexicon as $kata => $weight) {
        $count = countPhraseOccurrences($text, $kata);

        if ($count > 0) {
            $score += $weight * $count;
        }
    }

    return $score;
}

function determineSentimentLabel(int $score): string
{
    if ($score > 0) {
        return 'positif';
    }

    if ($score < 0) {
        return 'negatif';
    }

    return 'netral';
}

function preprocessText(
    string $text,
    array $stopwords,
    array $normalisasiDictionary,
    array $spellingDictionary,
    array $positiveLexicon,
    array $negativeLexicon,
    $stemmer = null
): array {
    $caseFolding = caseFoldText($text);
    $punctuationRemoval = punctuationRemovalText($caseFolding);
    $stopwordsRemoval = removeStopwordsFromText($punctuationRemoval, $stopwords);
    $tokenisasi = implode(' ', tokenizeText($stopwordsRemoval));
    $stemming = stemmingText($tokenisasi, $stemmer);
    $normalisasi = applyDictionaryToText($stemming, $normalisasiDictionary);
    $spellingCorrection = applyDictionaryToText($normalisasi, $spellingDictionary);

    $sentimentScore = calculateSentimentScore(
        $spellingCorrection,
        $positiveLexicon,
        $negativeLexicon
    );

    $labelSentimen = determineSentimentLabel($sentimentScore);

    return [
        'teks_asli' => $text,
        'case_folding' => $caseFolding,
        'punctuation_removal' => $punctuationRemoval,
        'stopwords_removal' => $stopwordsRemoval,
        'tokenisasi' => $tokenisasi,
        'stemming' => $stemming,
        'normalisasi' => $normalisasi,
        'spelling_correction' => $spellingCorrection,
        'label_sentimen' => $labelSentimen,
    ];
}

function readDatasetFromCsv(string $path): array
{
    $rows = [];
    $handle = fopen($path, 'r');

    if ($handle === false) {
        return $rows;
    }

    $header = fgetcsv($handle);
    $textIndex = 0;

    if (is_array($header)) {
        $lowerHeader = array_map(static fn($item) => strtolower(trim((string) $item)), $header);
        $foundIndex = array_search('full_text', $lowerHeader, true);

        if ($foundIndex === false) {
            $foundIndex = array_search('teks', $lowerHeader, true);
        }

        if ($foundIndex === false) {
            $foundIndex = array_search('text', $lowerHeader, true);
        }

        if ($foundIndex !== false) {
            $textIndex = (int) $foundIndex;
        } else {
            $firstText = trim(implode(' ', $header));

            if ($firstText !== '') {
                $rows[] = $firstText;
            }
        }
    }

    while (($row = fgetcsv($handle)) !== false) {
        $text = trim((string) ($row[$textIndex] ?? ''));

        if ($text !== '') {
            $rows[] = $text;
        }
    }

    fclose($handle);

    return $rows;
}

function readDatasetFromTextarea(string $text): array
{
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

    return array_values(array_filter(
        array_map('trim', $lines),
        static fn($line) => $line !== ''
    ));
}

try {
    $pdo = getPdoConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['clear_data'])) {
            $pdo->exec('TRUNCATE TABLE preprocessing_kelompok_knn');
            $message = 'Data preprocessing berhasil dikosongkan.';
        } else {
            $texts = [];

            if (!empty($_FILES['dataset_file']['tmp_name']) && is_uploaded_file($_FILES['dataset_file']['tmp_name'])) {
                $texts = readDatasetFromCsv($_FILES['dataset_file']['tmp_name']);
            }

            if (!empty($_POST['dataset_text'])) {
                $texts = array_merge($texts, readDatasetFromTextarea((string) $_POST['dataset_text']));
            }

            $texts = array_values(array_unique(array_filter(
                $texts,
                static fn($text) => trim($text) !== ''
            )));

            if (count($texts) === 0) {
                $message = 'Dataset masih kosong. Upload CSV atau isi teks manual terlebih dahulu.';
                $messageType = 'error';
            } else {
                $stopwords = loadStopwords($pdo);
                $normalisasiDictionary = loadNormalisasiDictionary($pdo);
                $spellingDictionary = loadSpellingDictionary($pdo);
                $positiveLexicon = loadPositiveLexicon($pdo);
                $negativeLexicon = loadNegativeLexicon($pdo);
                $stemmer = createStemmer();

                $stmt = $pdo->prepare("
                    INSERT INTO preprocessing_kelompok_knn
                    (
                        teks_asli,
                        case_folding,
                        punctuation_removal,
                        stopwords_removal,
                        tokenisasi,
                        stemming,
                        normalisasi,
                        spelling_correction,
                        label_sentimen
                    )
                    VALUES
                    (
                        :teks_asli,
                        :case_folding,
                        :punctuation_removal,
                        :stopwords_removal,
                        :tokenisasi,
                        :stemming,
                        :normalisasi,
                        :spelling_correction,
                        :label_sentimen
                    )
                ");

                foreach ($texts as $text) {
                    $stmt->execute(preprocessText(
                        $text,
                        $stopwords,
                        $normalisasiDictionary,
                        $spellingDictionary,
                        $positiveLexicon,
                        $negativeLexicon,
                        $stemmer
                    ));
                }

                $message = count($texts) . ' data berhasil diinput, diproses, dan diberi label sentimen otomatis.';

                if ($stemmer === null) {
                    $message .= ' Catatan: Sastrawi belum terdeteksi, sehingga stemming belum menggunakan library Sastrawi.';
                }
            }
        }
    }

    $totalData = (int) $pdo
        ->query('SELECT COUNT(*) FROM preprocessing_kelompok_knn')
        ->fetchColumn();

    $previewRows = $pdo
        ->query('SELECT * FROM preprocessing_kelompok_knn ORDER BY id_preprocessing DESC LIMIT 50')
        ->fetchAll();
} catch (Throwable $e) {
    $message = 'Terjadi kesalahan: ' . $e->getMessage();
    $messageType = 'error';
    $totalData = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preprocessing Dataset</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-slate-900 text-white">
            <div class="p-6 border-b border-slate-700">
                <h1 class="text-xl font-bold">UAS analisis sentimen</h1>
            </div>

            <nav class="p-4 space-y-2">
                <a href="../formload.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Dashboard
                </a>

                <a href="preprocessing_kelompok4.php" class="block px-4 py-3 rounded-lg bg-blue-600 font-medium">
                    Preprocessing
                </a>

                <a href="tf-idf.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Pembobotan TF-IDF
                </a>

                <a href="split-data.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Split Data
                </a>

                <a href="klasifikasi_knn.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Klasifikasi KNN
                </a>

                <a href="hasil_analisis.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Hasil Analisis
                </a>

                <a href="laporan.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Laporan
                </a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <section class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Input Dataset & Preprocessing</p>
                        <h2 class="text-2xl font-bold text-slate-900 mt-1">Preprocessing Dataset KNN</h2>
                        <p class="text-slate-500 mt-2">Upload CSV atau masukkan teks manual.</p>
                    </div>
                    <div class="rounded-xl bg-blue-50 px-5 py-4 text-right">
                        <p class="text-sm text-blue-700">Total Data</p>
                        <p class="text-3xl font-bold text-blue-700"><?php echo number_format($totalData); ?></p>
                    </div>
                </div>
            </section>

            <?php if ($message !== ''): ?>
                <div class="mb-6 rounded-xl px-5 py-4 <?php echo $messageType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <section class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <form method="POST" enctype="multipart/form-data" class="space-y-5">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <label for="dataset_file" class="block text-sm font-semibold text-slate-700 mb-2">Upload Dataset CSV</label>
                            <input id="dataset_file" name="dataset_file" type="file" accept=".csv" class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-700">
                            <p class="text-xs text-slate-400 mt-2">Kolom teks dapat bernama <span class="font-semibold">full_text</span>, <span class="font-semibold">teks</span>, atau <span class="font-semibold">text</span>.</p>
                        </div>

                        <div>
                            <label for="dataset_text" class="block text-sm font-semibold text-slate-700 mb-2">Input Teks Manual</label>
                            <textarea id="dataset_text" name="dataset_text" rows="5" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100" placeholder="Masukkan satu data teks per baris..."></textarea>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-slate-100 pt-5">
                        <p class="text-sm text-slate-500">
                        <div class="flex gap-3">
                            <button type="submit" name="clear_data" value="1" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50" onclick="return confirm('Kosongkan semua data preprocessing?')">
                                Kosongkan Data
                            </button>
                            <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                Simpan & Preprocessing
                            </button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="bg-white rounded-2xl shadow-sm p-6">
                <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">Hasil Preprocessing</h3>
                        <p class="text-sm text-slate-500 mt-1">Menampilkan 50 data terbaru dari tabel preprocessing.</p>
                    </div>
                    <span class="w-fit rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600">
                        preprocessing_kelompok_knn
                    </span>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">Teks Asli</th>
                                <th class="px-4 py-3">Case Folding</th>
                                <th class="px-4 py-3">Punctuation Removal</th>
                                <th class="px-4 py-3">Stopwords Removal</th>
                                <th class="px-4 py-3">Tokenisasi</th>
                                <th class="px-4 py-3">Stemming</th>
                                <th class="px-4 py-3">Normalisasi</th>
                                <th class="px-4 py-3">Spelling Correction</th>
                                <th class="px-4 py-3">Label Sentimen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php if (count($previewRows) === 0): ?>
                                <tr>
                                    <td colspan="10" class="px-4 py-10 text-center text-slate-500">Belum ada data. Upload dataset atau isi teks manual terlebih dahulu.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($previewRows as $index => $row): ?>
                                    <tr class="align-top hover:bg-slate-50">
                                        <td class="px-4 py-4 font-semibold text-slate-500"><?php echo $index + 1; ?></td>
                                        <td class="px-4 py-4 max-w-xs text-slate-700"><?php echo htmlspecialchars($row['teks_asli'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-4 max-w-xs text-slate-600"><?php echo htmlspecialchars($row['case_folding'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-4 max-w-xs text-slate-600"><?php echo htmlspecialchars($row['punctuation_removal'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-4 max-w-xs text-slate-600"><?php echo htmlspecialchars($row['stopwords_removal'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-4 max-w-xs text-slate-600"><?php echo htmlspecialchars($row['tokenisasi'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-4 max-w-xs font-medium text-slate-800"><?php echo htmlspecialchars($row['stemming'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-4 max-w-xs text-slate-600"><?php echo htmlspecialchars($row['normalisasi'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-4 max-w-xs text-slate-600"><?php echo htmlspecialchars($row['spelling_correction'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-4 max-w-xs font-semibold text-slate-800"><?php echo htmlspecialchars($row['label_sentimen'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="text-center text-sm text-slate-400 mt-8">
                &copy; <?php echo date('Y'); ?> Sistem Analisis Sentimen - Algoritma KNN
            </div>
        </main>
    </div>
</body>
</html>