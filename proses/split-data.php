<?php
require '../db_config.php';

$message = '';
$messageType = 'success';

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

function ensureSplitTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS split_data_kelompok_knn (
            id_split INT AUTO_INCREMENT PRIMARY KEY,
            id_preprocessing INT NOT NULL,
            label_sentimen VARCHAR(50) NOT NULL,
            tipe_data ENUM('training', 'testing') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_id_preprocessing (id_preprocessing),
            KEY idx_tipe_data (tipe_data)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

try {
    $pdo = getPdoConnection();
    ensureSplitTable($pdo);

    $persentaseTraining = 80;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['clear_data'])) {
            $pdo->exec('TRUNCATE TABLE split_data_kelompok_knn');
            $message = 'Data split berhasil dikosongkan.';
        } elseif (isset($_POST['split_data'])) {
            $persentaseTraining = (int) ($_POST['persentase_training'] ?? 80);

            if ($persentaseTraining < 50 || $persentaseTraining > 95) {
                $persentaseTraining = 80;
            }

            $rows = $pdo->query('SELECT id_preprocessing, label_sentimen FROM preprocessing_kelompok_knn')->fetchAll();

            if (count($rows) === 0) {
                $message = 'Belum ada data preprocessing untuk displit. Lakukan preprocessing dataset terlebih dahulu.';
                $messageType = 'error';
            } else {
                $labelById = [];
                $groupedByLabel = [];

                foreach ($rows as $row) {
                    $id = (int) $row['id_preprocessing'];
                    $label = $row['label_sentimen'] !== '' ? $row['label_sentimen'] : 'netral';

                    $labelById[$id] = $label;
                    $groupedByLabel[$label][] = $id;
                }

                $trainingIds = [];
                $testingIds = [];

                foreach ($groupedByLabel as $ids) {
                    shuffle($ids);
                    $jumlahTraining = (int) round(count($ids) * $persentaseTraining / 100);

                    $trainingIds = array_merge($trainingIds, array_slice($ids, 0, $jumlahTraining));
                    $testingIds = array_merge($testingIds, array_slice($ids, $jumlahTraining));
                }

                $pdo->exec('TRUNCATE TABLE split_data_kelompok_knn');

                $stmt = $pdo->prepare("
                    INSERT INTO split_data_kelompok_knn (id_preprocessing, label_sentimen, tipe_data)
                    VALUES (:id_preprocessing, :label_sentimen, :tipe_data)
                ");

                $pdo->beginTransaction();

                foreach ($trainingIds as $id) {
                    $stmt->execute([
                        ':id_preprocessing' => $id,
                        ':label_sentimen' => $labelById[$id],
                        ':tipe_data' => 'training',
                    ]);
                }

                foreach ($testingIds as $id) {
                    $stmt->execute([
                        ':id_preprocessing' => $id,
                        ':label_sentimen' => $labelById[$id],
                        ':tipe_data' => 'testing',
                    ]);
                }

                $pdo->commit();

                $message = 'Split data berhasil: ' . count($trainingIds) . ' data training (' . $persentaseTraining
                    . '%) dan ' . count($testingIds) . ' data testing (' . (100 - $persentaseTraining) . '%), '
                    . 'dibagi merata per label sentimen.';
            }
        }
    }

    $filter = $_GET['filter'] ?? 'semua';
    if (!in_array($filter, ['semua', 'training', 'testing'], true)) {
        $filter = 'semua';
    }

    $totalPreprocessing = (int) $pdo->query('SELECT COUNT(*) FROM preprocessing_kelompok_knn')->fetchColumn();
    $totalSplit = (int) $pdo->query('SELECT COUNT(*) FROM split_data_kelompok_knn')->fetchColumn();
    $totalTraining = (int) $pdo->query("SELECT COUNT(*) FROM split_data_kelompok_knn WHERE tipe_data = 'training'")->fetchColumn();
    $totalTesting = (int) $pdo->query("SELECT COUNT(*) FROM split_data_kelompok_knn WHERE tipe_data = 'testing'")->fetchColumn();

    $persenTrainingAktual = $totalSplit > 0 ? round(($totalTraining / $totalSplit) * 100, 1) : 0;
    $persenTestingAktual = $totalSplit > 0 ? round(($totalTesting / $totalSplit) * 100, 1) : 0;

    $breakdownLabel = $pdo->query("
        SELECT
            label_sentimen,
            COUNT(*) AS total,
            SUM(CASE WHEN tipe_data = 'training' THEN 1 ELSE 0 END) AS training,
            SUM(CASE WHEN tipe_data = 'testing' THEN 1 ELSE 0 END) AS testing
        FROM split_data_kelompok_knn
        GROUP BY label_sentimen
        ORDER BY label_sentimen ASC
    ")->fetchAll();

    $sqlDataSplit = "
        SELECT s.id_split, s.id_preprocessing, p.teks_asli, s.label_sentimen, s.tipe_data
        FROM split_data_kelompok_knn s
        INNER JOIN preprocessing_kelompok_knn p ON p.id_preprocessing = s.id_preprocessing
    ";

    if ($filter !== 'semua') {
        $stmt = $pdo->prepare($sqlDataSplit . ' WHERE s.tipe_data = :tipe ORDER BY s.id_split ASC LIMIT 200');
        $stmt->execute([':tipe' => $filter]);
        $dataSplit = $stmt->fetchAll();
    } else {
        $dataSplit = $pdo->query($sqlDataSplit . ' ORDER BY s.id_split ASC LIMIT 200')->fetchAll();
    }
} catch (Throwable $e) {
    $message = 'Terjadi kesalahan: ' . $e->getMessage();
    $messageType = 'error';
    $totalPreprocessing = 0;
    $totalSplit = 0;
    $totalTraining = 0;
    $totalTesting = 0;
    $persenTrainingAktual = 0;
    $persenTestingAktual = 0;
    $breakdownLabel = [];
    $dataSplit = [];
    $filter = 'semua';
    $persentaseTraining = 80;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Split Data</title>
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

                <a href="preprocessing_kelompok4.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Preprocessing
                </a>

                <a href="tf-idf.php" class="block px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                    Pembobotan TF-IDF
                </a>

                <a href="split-data.php" class="block px-4 py-3 rounded-lg bg-blue-600 font-medium">
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
                        <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Pembagian Dataset</p>
                        <h2 class="text-2xl font-bold text-slate-900 mt-1">Split Data Training &amp; Testing</h2>
                        <p class="text-slate-500 mt-2">Membagi data hasil preprocessing secara acak dan merata per label sentimen.</p>
                    </div>

                    <form method="POST" class="flex items-center gap-3">
                        <div>
                            <label for="persentase_training" class="block text-xs font-semibold text-slate-500 mb-1">Rasio Training (%)</label>
                            <input
                                id="persentase_training"
                                name="persentase_training"
                                type="number"
                                min="50"
                                max="95"
                                step="5"
                                value="<?php echo (int) $persentaseTraining; ?>"
                                class="w-24 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100"
                            >
                        </div>
                        <button type="submit" name="split_data" value="1" class="rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-700 self-end">
                            Split Data
                        </button>
                    </form>
                </div>
            </section>

            <?php if ($message !== ''): ?>
                <div class="mb-6 rounded-xl px-5 py-4 <?php echo $messageType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Total Dataset</p>
                    <h3 class="text-3xl font-bold text-blue-600 mt-2"><?php echo number_format($totalPreprocessing); ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Data hasil preprocessing</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Data Training</p>
                    <h3 class="text-3xl font-bold text-green-600 mt-2"><?php echo number_format($totalTraining); ?></h3>
                    <p class="text-xs text-slate-400 mt-2"><?php echo $persenTrainingAktual; ?>% dari data yang displit</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Data Testing</p>
                    <h3 class="text-3xl font-bold text-orange-500 mt-2"><?php echo number_format($totalTesting); ?></h3>
                    <p class="text-xs text-slate-400 mt-2"><?php echo $persenTestingAktual; ?>% dari data yang displit</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <p class="text-sm text-slate-500">Rasio Digunakan</p>
                    <h3 class="text-3xl font-bold text-purple-600 mt-2"><?php echo (int) $persentaseTraining; ?>:<?php echo 100 - (int) $persentaseTraining; ?></h3>
                    <p class="text-xs text-slate-400 mt-2">Training : Testing</p>
                </div>
            </div>

            <section class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <h3 class="text-xl font-bold text-slate-900 mb-4">Distribusi per Label Sentimen</h3>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Label Sentimen</th>
                                <th class="px-4 py-3 text-center">Total</th>
                                <th class="px-4 py-3 text-center">Training</th>
                                <th class="px-4 py-3 text-center">Testing</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php if (count($breakdownLabel) === 0): ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-slate-500">Belum ada data split. Klik "Split Data" untuk memulai.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($breakdownLabel as $row): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 font-semibold text-slate-700 capitalize"><?php echo htmlspecialchars($row['label_sentimen'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-3 text-center text-slate-500"><?php echo (int) $row['total']; ?></td>
                                        <td class="px-4 py-3 text-center text-green-600 font-medium"><?php echo (int) $row['training']; ?></td>
                                        <td class="px-4 py-3 text-center text-orange-500 font-medium"><?php echo (int) $row['testing']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bg-white rounded-2xl shadow-sm p-6">
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">Detail Hasil Split</h3>
                        <p class="text-sm text-slate-500 mt-1">Menampilkan maksimal 200 data.</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a href="?filter=semua" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $filter === 'semua' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                            Semua
                        </a>
                        <a href="?filter=training" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $filter === 'training' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                            Training
                        </a>
                        <a href="?filter=testing" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $filter === 'testing' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                            Testing
                        </a>
                        <form method="POST" onsubmit="return confirm('Kosongkan semua data split?')">
                            <button type="submit" name="clear_data" value="1" class="px-4 py-2 rounded-lg text-sm font-medium border border-red-200 text-red-600 hover:bg-red-50">
                                Kosongkan
                            </button>
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">Teks Asli</th>
                                <th class="px-4 py-3">Label Sentimen</th>
                                <th class="px-4 py-3">Jenis Data</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php if (count($dataSplit) === 0): ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-slate-500">Belum ada data split. Klik "Split Data" untuk memulai.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dataSplit as $index => $row): ?>
                                    <tr class="align-top hover:bg-slate-50">
                                        <td class="px-4 py-4 font-semibold text-slate-500"><?php echo $index + 1; ?></td>
                                        <td class="px-4 py-4 max-w-md text-slate-700"><?php echo htmlspecialchars($row['teks_asli'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="px-4 py-4">
                                            <?php
                                                $labelClass = match ($row['label_sentimen']) {
                                                    'positif' => 'bg-green-100 text-green-700',
                                                    'negatif' => 'bg-red-100 text-red-700',
                                                    default => 'bg-slate-100 text-slate-700',
                                                };
                                            ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-medium capitalize <?php echo $labelClass; ?>">
                                                <?php echo htmlspecialchars($row['label_sentimen'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4">
                                            <?php if ($row['tipe_data'] === 'training'): ?>
                                                <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold">Training</span>
                                            <?php else: ?>
                                                <span class="px-3 py-1 rounded-full bg-orange-100 text-orange-700 text-xs font-semibold">Testing</span>
                                            <?php endif; ?>
                                        </td>
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
