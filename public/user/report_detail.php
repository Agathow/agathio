<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

require_login();

$reportId = (int) ($_GET['id'] ?? 0);

$statement = $pdo->prepare("
    SELECT
        r.*,
        ps.name AS service,
        l.city,
        l.district
    FROM reports r
    LEFT JOIN public_services ps ON ps.id = r.service_id
    LEFT JOIN locations l ON l.id = r.location_id
    WHERE r.id = ?
      AND r.user_id = ?
    LIMIT 1
");
$statement->execute([
    $reportId,
    $_SESSION['user']['id'],
]);
$report = $statement->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    http_response_code(404);
    exit('Laporan tidak ditemukan.');
}

$evidenceRows = [];
try {
    $evidenceStmt = $pdo->prepare("
        SELECT file_name, file_path, file_type, file_size, created_at
        FROM report_evidence
        WHERE report_id = ?
        ORDER BY id ASC
    ");
    $evidenceStmt->execute([$reportId]);
    $evidenceRows = $evidenceStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $exception) {

}

if (!$evidenceRows && !empty($report['evidence_path'])) {
    $legacyPath = (string) $report['evidence_path'];
    $evidenceRows[] = [
        'file_name' => basename($legacyPath),
        'file_path' => $legacyPath,
        'file_type' => '',
        'file_size' => 0,
        'created_at' => null,
    ];
}

$projectRoot = dirname(__DIR__, 2);
$formatBytes = static function ($bytes): string {
    $bytes = (int) $bytes;
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024) . ' KB';
    }
    return $bytes . ' B';
};
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($report['report_code'] ?? 'Detail Laporan') ?> — SIGAP</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/report_detail.css">
    <link rel="icon" type="image/png" href="/assets/images/logo tab.png">
    <link rel="apple-touch-icon" href="/assets/images/logo tab.png">
</head>
<body>
<?php require_once __DIR__ . '/../../config/navbar.php'; ?>

<main>
    <section class="section">
        <div class="container">
            <a href="/user/profile.php" class="report-detail-back">
                <span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
                <span>Kembali ke Profil</span>
            </a>

            <div class="detail-hero report-detail-hero">
                <span class="eyebrow"><?= e($report['report_code'] ?? '') ?></span>
                <h1><?= e($report['title'] ?? 'Laporan masyarakat') ?></h1>
                <p class="muted">Dibuat pada <?= e($report['created_at'] ?? '-') ?></p>

                <div class="two-col report-info-grid">
                    <div class="detail-info-item">
                        <b>Layanan</b>
                        <p><?= e($report['service'] ?? '-') ?></p>
                    </div>
                    <div class="detail-info-item">
                        <b>Lokasi</b>
                        <p><?= e(trim(($report['district'] ?? '-') . ', ' . ($report['city'] ?? '-'), ', ')) ?></p>
                    </div>
                    <div class="detail-info-item">
                        <b>Status</b>
                        <p><span class="status"><?= e(status_label($report['status'] ?? 'New')) ?></span></p>
                    </div>
                    <div class="detail-info-item">
                        <b>Risiko</b>
                        <p><?= risk_badge($report['risk_level'] ?? 'Unclassified') ?></p>
                    </div>
                </div>
            </div>

            <div class="card report-detail-card">
                <h2>Kronologi</h2>
                <p class="muted report-chronology"><?= nl2br(e($report['chronology'] ?? $report['description'] ?? 'Tidak ada kronologi yang dicantumkan.')) ?></p>
            </div>

            <section class="card report-detail-card">
                <h2>Bukti Pendukung</h2>
                <?php if ($evidenceRows): ?>
                    <div class="report-evidence-grid">
                        <?php foreach ($evidenceRows as $evidence): ?>
                            <?php
                            $storedPath = trim((string) ($evidence['file_path'] ?? ''));
                            $fileName = (string) ($evidence['file_name'] ?? basename($storedPath));
                            $fileType = (string) ($evidence['file_type'] ?? '');
                            $fileSize = (int) ($evidence['file_size'] ?? 0);
                            $normalizedPath = ltrim(str_replace('\\', '/', $storedPath), '/');
                            $normalizedPath = preg_replace('#^(\./)+#', '', $normalizedPath);

                            if (strpos($normalizedPath, 'public/') === 0) {
                                $normalizedPath = substr($normalizedPath, 7);
                            }
                            $marker = strpos($normalizedPath, 'storage/reports/');
                            if ($marker !== false) {
                                $normalizedPath = substr($normalizedPath, $marker);
                            }
                            $evidenceUrl = '/' . $normalizedPath;
                            $imageType = stripos($fileType, 'image/') === 0
                                || preg_match('/\.(png|jpe?g|gif|webp|bmp)$/i', $fileName);
                            ?>
                            <article class="report-evidence-card">
                                <div class="report-evidence-preview">
                                    <?php if ($imageType): ?>
                                        <img
                                            src="<?= e($evidenceUrl) ?>"
                                            alt="<?= e($fileName) ?>"
                                            loading="lazy"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
                                        >
                                        <p class="report-evidence-error">Preview gambar tidak dapat dimuat. Periksa lokasi file.</p>
                                    <?php else: ?>
                                        <p class="report-evidence-error" style="display:block;color:#71877f;">Preview tidak tersedia untuk jenis file ini.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="report-evidence-details">
                                    <p class="report-evidence-name"><?= e($fileName) ?></p>
                                    <p class="report-evidence-meta">
                                        <?= e($fileType !== '' ? $fileType : 'File') ?>
                                        <?php if ($fileSize > 0): ?> · <?= e($formatBytes($fileSize)) ?><?php endif; ?>
                                    </p>
                                    <a class="report-evidence-link" href="<?= e($evidenceUrl) ?>" target="_blank" rel="noopener noreferrer">Buka file asli</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">Tidak ada bukti pendukung yang dilampirkan.</p>
                <?php endif; ?>
            </section>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../../config/footer.php'; ?>
</body>
</html>
