<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/_layout.php';

require_staff();

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        r.*,
        ps.name AS service_name,
        ps.official_fee AS service_official_fee,
        l.province,
        l.city,
        l.district,
        l.subdistrict,
        l.address AS location_address,
        l.latitude AS location_latitude,
        l.longitude AS location_longitude
    FROM reports r
    LEFT JOIN public_services ps ON ps.id = r.service_id
    LEFT JOIN locations l ON l.id = r.location_id
    WHERE r.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    http_response_code(404);
    admin_header('Report Not Found', 'reports');
    echo '<div class="card"><h2>Laporan tidak ditemukan</h2><p class="muted">Laporan yang kamu cari tidak tersedia atau sudah dihapus.</p><a class="btn btn-primary" href="/admin/reports.php">Kembali ke Report Management</a></div>';
    admin_footer();
    exit;
}

$evidenceStmt = $pdo->prepare("
    SELECT id, report_id, file_name, file_path, file_type, file_size, created_at
    FROM report_evidence
    WHERE report_id = ?
    ORDER BY created_at DESC, id DESC
");
$evidenceStmt->execute([(int) $report['id']]);
$evidences = $evidenceStmt->fetchAll(PDO::FETCH_ASSOC);

function report_evidence_url(array $evidence): string
{
    $evidenceId = (int) ($evidence['id'] ?? 0);
    return $evidenceId > 0 ? '/admin/evidence.php?id=' . $evidenceId : '';
}

$reportId = $report['report_code'] ?? $report['report_id'] ?? ('SIGAP-' . $report['id']);
$serviceName = $report['service_name'] ?? 'Layanan tidak diketahui';
$locationParts = array_filter([
    trim((string) ($report['location_address'] ?? '')),
    trim((string) ($report['subdistrict'] ?? '')),
    trim((string) ($report['district'] ?? '')),
    trim((string) ($report['city'] ?? '')),
    trim((string) ($report['province'] ?? '')),
], static fn($part) => $part !== '');
$locationName = implode(', ', array_unique($locationParts));
$latitude = is_numeric($report['location_latitude'] ?? null) ? (float) $report['location_latitude'] : null;
$longitude = is_numeric($report['location_longitude'] ?? null) ? (float) $report['location_longitude'] : null;
$hasCoordinates = $latitude !== null && $longitude !== null
    && $latitude >= -90 && $latitude <= 90
    && $longitude >= -180 && $longitude <= 180;
$mapQuery = $hasCoordinates ? $latitude . ',' . $longitude : $locationName;
$riskLevel = ucfirst(strtolower(trim((string) ($report['risk_level'] ?? 'Unclassified'))));
$riskClassMap = [
    'low' => 'report-detail-risk-low',
    'moderate' => 'report-detail-risk-moderate',
    'elevated' => 'report-detail-risk-elevated',
    'high' => 'report-detail-risk-high',
];
$riskClass = $riskClassMap[strtolower(trim($riskLevel))] ?? 'report-detail-risk-unknown';
$status = (string) ($report['status'] ?? 'new');
$statusLabels = [
    'new' => 'Laporan Baru',
    'pending_review' => 'Menunggu Review',
    'under_review' => 'Sedang Ditinjau',
    'follow_up' => 'Sedang Ditindaklanjuti',
    'awaiting_admin_verification' => 'Menunggu Verifikasi Admin',
    'awaiting_reporter_confirmation' => 'Menunggu Konfirmasi Pelapor',
    'reporter_disputed' => 'Keberatan Pelapor',
    'resolved' => 'Selesai',
    'closed_no_response' => 'Ditutup — Tanpa Konfirmasi',
    'rejected' => 'Ditolak',
];
$statusLabel = $statusLabels[$status] ?? ucwords(str_replace('_', ' ', $status));
$officialFee = (float) ($report['official_fee'] ?? $report['service_official_fee'] ?? $report['official_amount'] ?? 0);
$requestedAmount = (float) ($report['requested_amount'] ?? 0);
$discrepancy = (float) ($report['discrepancy_amount'] ?? max(0, $requestedAmount - $officialFee));
$reportMode = !empty($report['is_anonymous']) ? 'Anonymous' : 'Identified';
$chronology = $report['description'] ?? $report['chronology'] ?? '';
$accessCode = $report['access_code'] ?? '';

admin_header('Report Detail — ' . $reportId, 'reports');
?>


<div class="report-detail-page">
    <header class="report-detail-heading">
        <div class="report-detail-back-row">
            <a class="report-detail-back" href="/admin/reports.php">
                <span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
                <span>Kembali ke Report Management</span>
            </a>
        </div>
    </header>

    <div class="report-detail-layout">
        <main class="report-detail-main">
            <section class="card report-summary-card">
                <div class="report-detail-status-line">
                    <span class="report-detail-risk-badge <?= e($riskClass) ?>"><?= e($riskLevel) ?></span>
                    <span class="report-detail-status-badge"><?= e($statusLabel) ?></span>
                </div>

                <h2><?= e($report['title'] ?? $report['subject'] ?? 'Laporan masyarakat') ?></h2>

                <p class="report-detail-subtitle">
                    <?= e($serviceName) ?>
                    <?php if ($locationName !== ''): ?>
                        · <?= e($locationName) ?>
                    <?php endif; ?>
                </p>

                <?php if (!empty($report['office_name'])): ?>
                    <p class="report-detail-subtitle"><?= e($report['office_name']) ?></p>
                <?php endif; ?>

                <div class="report-detail-fee-grid">
                    <div class="report-detail-fee">
                        <small>Official Fee</small>
                        <strong><?= rupiah($officialFee) ?></strong>
                    </div>
                    <div class="report-detail-fee">
                        <small>Requested</small>
                        <strong><?= rupiah($requestedAmount) ?></strong>
                    </div>
                    <div class="report-detail-fee">
                        <small>Discrepancy</small>
                        <strong><?= rupiah($discrepancy) ?></strong>
                    </div>
                    <div class="report-detail-fee">
                        <small>Report Mode</small>
                        <strong><?= e($reportMode) ?></strong>
                    </div>
                </div>
            </section>

            <section class="card report-info-card report-location-card">
                <div class="report-location-heading">
                    <div>
                        <h2 class="report-detail-section-title">Lokasi Kejadian</h2>
                        <p class="muted">Lokasi yang dipilih pelapor untuk membantu proses investigasi.</p>
                    </div>

                </div>

                <div class="report-location-meta-list">
                    <div class="report-location-meta-item">
                        <span class="report-location-icon material-symbols-rounded" aria-hidden="true">my_location</span>
                        <div><strong><?= $hasCoordinates ? 'Titik GPS tersedia' : 'Titik GPS belum tersedia' ?></strong>
                        <small><?= $hasCoordinates ? e(number_format($latitude, 7, '.', '') . ', ' . number_format($longitude, 7, '.', '')) : 'Koordinat belum tersimpan pada laporan ini.' ?></small></div>
                    </div>
                    <div class="report-location-meta-item">
                        <span class="report-location-icon material-symbols-rounded" aria-hidden="true">location_on</span>
                        <div><strong>Unit / alamat pelayanan</strong><small><?= e($locationName !== '' ? $locationName : 'Alamat lokasi belum tersedia.') ?></small></div>
                    </div>
                </div>

                <?php if ($hasCoordinates): ?>
                    <div id="report-location-map" class="report-location-map" role="application" aria-label="Peta lokasi kejadian"></div>
                    <div class="report-location-actions">
                        <a class="btn btn-primary" href="https://www.openstreetmap.org/?mlat=<?= rawurlencode((string) $latitude) ?>&amp;mlon=<?= rawurlencode((string) $longitude) ?>#map=18/<?= rawurlencode((string) $latitude) ?>/<?= rawurlencode((string) $longitude) ?>" target="_blank" rel="noopener noreferrer">
                            <span class="material-symbols-rounded" aria-hidden="true">map</span> Lihat Peta Lengkap
                        </a>
                        <button class="btn report-location-copy" type="button" id="copy-report-coordinates" data-coordinates="<?= e(number_format($latitude, 7, '.', '') . ', ' . number_format($longitude, 7, '.', '')) ?>">
                            <span class="material-symbols-rounded" aria-hidden="true">content_copy</span> Salin Koordinat
                        </button>
                    </div>
                    <p id="report-location-copy-feedback" class="report-location-feedback" aria-live="polite"></p>
                <?php else: ?>
                    <div class="report-location-no-coordinates">
                        <span class="material-symbols-rounded" aria-hidden="true">map</span>
                        <div><strong>Koordinat belum tersedia</strong><p>Lokasi administratif di atas tetap ditampilkan, tetapi titik spesifik tidak dapat dipetakan karena koordinat belum tersimpan pada data lokasi.</p></div>
                    </div>
                    <?php if ($locationName !== ''): ?>
                        <a class="btn report-detail-map-action report-location-search" href="https://www.openstreetmap.org/search?query=<?= rawurlencode($locationName) ?>" target="_blank" rel="noopener noreferrer"><span class="material-symbols-rounded" aria-hidden="true">search</span> Cari alamat di peta</a>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="card report-info-card">
                <h2 class="report-detail-section-title">Kronologi Pelapor</h2>
                <p class="report-detail-chronology"><?= nl2br(e($chronology !== '' ? $chronology : 'Tidak ada kronologi yang dicantumkan.')) ?></p>
            </section>

            <section class="card report-info-card">
                <h2 class="report-detail-section-title">Bukti Pendukung</h2>

                <?php if (!empty($evidences)): ?>
                    <div class="admin-evidence-grid">
                        <?php foreach ($evidences as $evidence): ?>
                            <?php
                            $evidenceUrl = report_evidence_url($evidence);
                            $fileType = strtolower(trim((string) ($evidence['file_type'] ?? '')));
                            $fileName = (string) ($evidence['file_name'] ?? 'Bukti pendukung');
                            $isImage = strpos($fileType, 'image/') === 0;
                            $fileSize = (int) ($evidence['file_size'] ?? 0);
                            ?>
                            <article class="admin-evidence-item">
                                <?php if ($evidenceUrl !== '' && $isImage): ?>
                                    <a class="admin-evidence-preview"
                                       href="<?= e($evidenceUrl) ?>"
                                       target="_blank"
                                       rel="noopener noreferrer">
                                        <img src="<?= e($evidenceUrl) ?>"
                                             alt="<?= e($fileName) ?>"
                                             loading="lazy"
                                             onerror="this.closest('.admin-evidence-preview').classList.add('is-broken'); this.style.display='none';">
                                        <span class="admin-evidence-fallback">Preview gambar tidak dapat dimuat. Periksa lokasi file.</span>
                                    </a>
                                <?php elseif ($evidenceUrl !== ''): ?>
                                    <a class="admin-evidence-preview admin-evidence-document"
                                       href="<?= e($evidenceUrl) ?>"
                                       target="_blank"
                                       rel="noopener noreferrer">
                                        <span class="material-symbols-rounded" aria-hidden="true">description</span>
                                        <span>Buka dokumen</span>
                                    </a>
                                <?php else: ?>
                                    <div class="admin-evidence-preview is-broken">
                                        <span class="material-symbols-rounded" aria-hidden="true">broken_image</span>
                                        <span>Path file tidak valid.</span>
                                    </div>
                                <?php endif; ?>

                                <div class="admin-evidence-meta">
                                    <strong><?= e($fileName) ?></strong>
                                    <small>
                                        <?= e($fileType !== '' ? $fileType : 'Tipe file tidak diketahui') ?>
                                        <?php if ($fileSize > 0): ?>
                                            · <?= e(number_format($fileSize / 1024, 0)) ?> KB
                                        <?php endif; ?>
                                    </small>
                                    <?php if ($evidenceUrl !== ''): ?>
                                        <a href="<?= e($evidenceUrl) ?>" target="_blank" rel="noopener noreferrer">Buka file asli</a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">Tidak ada bukti pendukung yang dilampirkan.</p>
                <?php endif; ?>
            </section>

            <section class="card report-info-card">
                <h2 class="report-detail-section-title">Riwayat Tindak Lanjut</h2>
                <p class="muted">Catatan tindakan dan bukti yang telah dipublikasikan kepada pelapor.</p>
                <?php
                try {
                    $followUpStmt = $pdo->prepare("SELECT id, action_type, action_date, summary, public_note, publication_status, created_at FROM report_follow_ups WHERE report_id = ? ORDER BY action_date DESC, id DESC");
                    $followUpStmt->execute([(int) $report['id']]);
                    $followUps = $followUpStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Throwable $followUpError) {
                    $followUps = [];
                    $followUpTableUnavailable = true;
                }
                ?>
                <?php if (!empty($followUpTableUnavailable)): ?>
                    <p class="muted">Jalankan migrasi SQL SIGAP terlebih dahulu untuk mengaktifkan riwayat tindak lanjut.</p>
                <?php elseif (!$followUps): ?>
                    <div class="report-follow-up-empty"><span class="material-symbols-rounded" aria-hidden="true">pending_actions</span><p>Belum ada tindak lanjut yang dicatat.</p></div>
                <?php else: ?>
                    <div class="report-follow-up-list">
                    <?php foreach ($followUps as $followUp): ?>
                        <article class="report-follow-up-item">
                            <div class="report-follow-up-item-head"><strong><?= e(ucwords(str_replace('_', ' ', (string) $followUp['action_type']))) ?></strong><span class="report-follow-up-pill <?= $followUp['publication_status'] === 'published' ? 'is-published' : 'is-draft' ?>"><?= $followUp['publication_status'] === 'published' ? 'Dipublikasikan' : 'Draft' ?></span></div>
                            <small><?= e(date('d M Y', strtotime((string) $followUp['action_date']))) ?></small>
                            <p><?= e((string) $followUp['summary']) ?></p>
                            <?php if (!empty($followUp['public_note'])): ?><p class="muted"><?= e((string) $followUp['public_note']) ?></p><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>

        <aside class="report-detail-side">
            <section class="card report-info-card">
                <h2 class="report-detail-section-title">Tindak Lanjut Laporan</h2>
                <p class="muted">Catat tindakan yang sudah dilakukan, lampirkan bukti resmi, lalu publikasikan hasilnya untuk pelapor.</p>

                <div class="report-detail-action-stack">
                    <a class="btn btn-primary" href="/admin/report_follow_up.php?id=<?= (int) $report['id'] ?>">
                        <span class="material-symbols-rounded" aria-hidden="true">assignment_add</span>
                        Isi Tindak Lanjut
                    </a>
                    <a class="btn report-detail-map-action" href="/admin/risk_map.php">
                        <span class="material-symbols-rounded" aria-hidden="true">map</span>
                        Lihat Risk Map
                    </a>
                </div>
            </section>

            <section class="card report-info-card">
                <h2 class="report-detail-section-title">Identitas &amp; Metadata</h2>
                <p class="muted">Informasi administratif laporan.</p>

                <dl class="report-detail-info-list">
                    <div class="report-detail-info-item">
                        <dt>Report ID</dt>
                        <dd><?= e($reportId) ?></dd>
                    </div>
                    <div class="report-detail-info-item">
                        <dt>Access Code</dt>
                        <dd><?= e($accessCode !== '' ? $accessCode : '—') ?></dd>
                    </div>
                    <div class="report-detail-info-item">
                        <dt>Pelapor</dt>
                        <dd><?= e($reportMode === 'Anonymous' ? 'Anonim' : ($report['reporter_name'] ?? 'Teridentifikasi')) ?></dd>
                    </div>
                </dl>
            </section>
        </aside>
    </div>
</div>

<?php if ($hasCoordinates): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
    const mapElement = document.getElementById('report-location-map');
    if (!mapElement || typeof L === 'undefined') return;
    const lat = <?= json_encode($latitude) ?>;
    const lng = <?= json_encode($longitude) ?>;
    const map = L.map(mapElement, {
        scrollWheelZoom: false,
        zoomControl: false
    }).setView([lat, lng], 16);

    L.control.zoom({ position: 'topright' }).addTo(map);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    const sigapMarker = L.divIcon({
        className: 'sigap-map-marker-wrapper',
        html: '<div class="sigap-map-marker"><span></span></div>',
        iconSize: [28, 36],
        iconAnchor: [14, 32],
        popupAnchor: [0, -30]
    });

    L.marker([lat, lng], {
        icon: sigapMarker,
        title: 'Lokasi kejadian laporan',
        alt: 'Pin lokasi kejadian'
    })
    .addTo(map)
    .bindPopup(
        '<div class="sigap-map-popup">' +
            '<strong>Lokasi Kejadian</strong>' +
            '<span>Titik laporan SIGAP</span>' +
        '</div>'
    );

    setTimeout(() => map.invalidateSize(), 150);
})();
const copyCoordinatesButton = document.getElementById('copy-report-coordinates');
if (copyCoordinatesButton) {
    copyCoordinatesButton.addEventListener('click', async function () {
        const value = this.dataset.coordinates || '';
        const feedback = document.getElementById('report-location-copy-feedback');
        try {
            await navigator.clipboard.writeText(value);
            feedback.textContent = 'Koordinat berhasil disalin.';
        } catch (error) {
            feedback.textContent = 'Tidak dapat menyalin otomatis. Koordinat: ' + value;
        }
    });
}
</script>
<?php endif; ?>

<?php admin_footer(); ?>