<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/_layout.php';

require_staff();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    header('Location: /admin/reports.php');
    exit;
}

$errors = [];
$success = '';
$report = null;

function report_edit_column_exists(PDO $pdo, string $column): bool
{
    $allowed = [
        'title', 'subject', 'description', 'chronology', 'service_id',
        'location_id', 'requested_amount', 'official_fee',
        'discrepancy_amount', 'status', 'risk_level', 'office_name'
    ];
    if (!in_array($column, $allowed, true)) {
        return false;
    }

    static $columns = null;
    if ($columns === null) {
        $columns = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM reports');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[] = $row['Field'];
        }
    }
    return in_array($column, $columns, true);
}

function report_edit_fetch(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT r.*,
                ps.name AS service_name,
                l.district,
                l.city
         FROM reports r
         LEFT JOIN public_services ps ON ps.id = r.service_id
         LEFT JOIN locations l ON l.id = r.location_id
         WHERE r.id = ?
         LIMIT 1"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

$report = report_edit_fetch($pdo, $id);
if (!$report) {
    http_response_code(404);
    admin_header('Report Not Found', 'reports');
    echo '<div class="card"><h2>Laporan tidak ditemukan</h2><p class="muted">Laporan tidak tersedia atau sudah dihapus.</p><a class="btn btn-primary" href="/admin/reports.php">Kembali ke Report Management</a></div>';
    admin_footer();
    exit;
}

$services = $pdo->query(
    "SELECT id, name FROM public_services WHERE status = 'active' ORDER BY name"
)->fetchAll(PDO::FETCH_ASSOC);

$locations = $pdo->query(
    "SELECT id, district, city FROM locations ORDER BY city, district"
)->fetchAll(PDO::FETCH_ASSOC);

$statusOptions = [
    'New' => 'New',
    'In Review' => 'In Review',
    'Investigating' => 'Investigating',
    'Resolved' => 'Resolved',
    'Rejected' => 'Rejected',
];
$riskOptions = [
    'low' => 'Low',
    'moderate' => 'Moderate',
    'elevated' => 'Elevated',
    'high' => 'High',
];

$values = [
    'title' => (string)($report['title'] ?? $report['subject'] ?? ''),
    'service_id' => (string)($report['service_id'] ?? ''),
    'location_id' => (string)($report['location_id'] ?? ''),
    'office_name' => (string)($report['office_name'] ?? ''),
    'requested_amount' => (string)($report['requested_amount'] ?? '0'),
    'official_fee' => (string)($report['official_fee'] ?? '0'),
    'status' => (string)($report['status'] ?? 'New'),
    'risk_level' => strtolower((string)($report['risk_level'] ?? 'moderate')),
    'chronology' => (string)($report['chronology'] ?? $report['description'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $oldValue) {
        if (isset($_POST[$key]) && is_scalar($_POST[$key])) {
            $values[$key] = trim((string)$_POST[$key]);
        }
    }

    if ($values['title'] === '') {
        $errors[] = 'Judul laporan wajib diisi.';
    }
    if (!array_key_exists($values['status'], $statusOptions)) {
        $errors[] = 'Status laporan tidak valid.';
    }
    if (!array_key_exists($values['risk_level'], $riskOptions)) {
        $errors[] = 'Level risiko tidak valid.';
    }

    foreach (['requested_amount', 'official_fee'] as $moneyField) {
        if ($values[$moneyField] === '' || !is_numeric($values[$moneyField]) || (float)$values[$moneyField] < 0) {
            $errors[] = 'Nominal biaya harus berupa angka nol atau lebih.';
            break;
        }
    }

    $serviceId = $values['service_id'] === '' ? null : filter_var($values['service_id'], FILTER_VALIDATE_INT);
    $locationId = $values['location_id'] === '' ? null : filter_var($values['location_id'], FILTER_VALIDATE_INT);
    if ($values['service_id'] !== '' && (!$serviceId || (int)$serviceId < 1)) {
        $errors[] = 'Layanan yang dipilih tidak valid.';
    }
    if ($values['location_id'] !== '' && (!$locationId || (int)$locationId < 1)) {
        $errors[] = 'Lokasi yang dipilih tidak valid.';
    }

    if (!$errors) {
        try {
            $requestedAmount = (float)$values['requested_amount'];
            $officialFee = (float)$values['official_fee'];
            $discrepancy = max(0, $requestedAmount - $officialFee);

            $updates = [];
            $params = [];

            if (report_edit_column_exists($pdo, 'title')) {
                $updates[] = 'title = ?';
                $params[] = $values['title'];
            } elseif (report_edit_column_exists($pdo, 'subject')) {
                $updates[] = 'subject = ?';
                $params[] = $values['title'];
            }

            if (report_edit_column_exists($pdo, 'service_id')) {
                $updates[] = 'service_id = ?';
                $params[] = $serviceId ? (int)$serviceId : null;
            }
            if (report_edit_column_exists($pdo, 'location_id')) {
                $updates[] = 'location_id = ?';
                $params[] = $locationId ? (int)$locationId : null;
            }
            if (report_edit_column_exists($pdo, 'office_name')) {
                $updates[] = 'office_name = ?';
                $params[] = $values['office_name'] !== '' ? $values['office_name'] : null;
            }

            $chronologyColumn = report_edit_column_exists($pdo, 'chronology')
                ? 'chronology'
                : (report_edit_column_exists($pdo, 'description') ? 'description' : null);
            if ($chronologyColumn !== null) {
                $updates[] = $chronologyColumn . ' = ?';
                $params[] = $values['chronology'];
            }

            if (report_edit_column_exists($pdo, 'requested_amount')) {
                $updates[] = 'requested_amount = ?';
                $params[] = $requestedAmount;
            }
            if (report_edit_column_exists($pdo, 'official_fee')) {
                $updates[] = 'official_fee = ?';
                $params[] = $officialFee;
            }
            if (report_edit_column_exists($pdo, 'discrepancy_amount')) {
                $updates[] = 'discrepancy_amount = ?';
                $params[] = $discrepancy;
            }
            if (report_edit_column_exists($pdo, 'status')) {
                $updates[] = 'status = ?';
                $params[] = $values['status'];
            }
            if (report_edit_column_exists($pdo, 'risk_level')) {
                $updates[] = 'risk_level = ?';
                $params[] = $values['risk_level'];
            }

            if (!$updates) {
                throw new RuntimeException('Tidak ada kolom laporan yang dapat diperbarui.');
            }

            $params[] = $id;
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE reports SET ' . implode(', ', $updates) . ' WHERE id = ?');
            $stmt->execute($params);
            $pdo->commit();

            header('Location: /admin/report_detail.php?id=' . $id . '&updated=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('SIGAP report edit error: ' . $e->getMessage());
            $errors[] = 'Perubahan gagal disimpan. Periksa struktur tabel reports dan konfigurasi database.';
        }
    }
}

$reportCode = (string)($report['report_code'] ?? $report['report_id'] ?? ('SIGAP-' . $id));
$evidenceStmt = $pdo->prepare(
    "SELECT id, file_name, file_path, file_type, file_size
     FROM report_evidence
     WHERE report_id = ?
     ORDER BY created_at DESC"
);
$evidenceStmt->execute([$id]);
$evidenceFiles = $evidenceStmt->fetchAll(PDO::FETCH_ASSOC);

admin_header('Edit Report — ' . $reportCode, 'reports');
?>
<link rel="stylesheet" href="/assets/css/admin_report_edit.css">

<div class="report-edit-page">
    <header class="report-edit-heading">
        <a class="report-edit-back" href="/admin/report_detail.php?id=<?= (int)$id ?>">
            <span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
            <span>Kembali ke Report Detail</span>
        </a>
    </header>

    <?php if ($errors): ?>
        <div class="report-edit-alert report-edit-alert-error" role="alert">
            <strong>Periksa kembali data berikut:</strong>
            <ul>
                <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="report-edit-layout">
        <main class="report-edit-main">
            <section class="card report-edit-card">
                <div class="report-edit-section-heading">
                    <span class="report-edit-section-icon material-symbols-rounded" aria-hidden="true">description</span>
                    <div><h2>Informasi Laporan</h2><p>Data utama yang disampaikan oleh pelapor.</p></div>
                </div>

                <div class="report-edit-field">
                    <label for="title">Judul laporan <span>*</span></label>
                    <input id="title" name="title" type="text" maxlength="255" required value="<?= e($values['title']) ?>">
                </div>

                <div class="report-edit-form-grid">
                    <div class="report-edit-field">
                        <label for="service_id">Layanan publik</label>
                        <select id="service_id" name="service_id">
                            <option value="">Pilih layanan</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= (int)$service['id'] ?>" <?= $values['service_id'] === (string)$service['id'] ? 'selected' : '' ?>>
                                    <?= e($service['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="report-edit-field">
                        <label for="location_id">Lokasi</label>
                        <select id="location_id" name="location_id">
                            <option value="">Pilih lokasi</option>
                            <?php foreach ($locations as $location): ?>
                                <?php $locationLabel = trim(($location['district'] ?? '') . ', ' . ($location['city'] ?? ''), ', '); ?>
                                <option value="<?= (int)$location['id'] ?>" <?= $values['location_id'] === (string)$location['id'] ? 'selected' : '' ?>>
                                    <?= e($locationLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="report-edit-field">
                    <label for="office_name">Nama instansi / kantor</label>
                    <input id="office_name" name="office_name" type="text" maxlength="255" value="<?= e($values['office_name']) ?>" placeholder="Nama instansi jika tersedia">
                </div>

                <div class="report-edit-field">
                    <label for="chronology">Kronologi laporan</label>
                    <textarea id="chronology" name="chronology" rows="7" placeholder="Uraikan kronologi laporan..."><?= e($values['chronology']) ?></textarea>
                </div>
            </section>

            <section class="card report-edit-card">
                <div class="report-edit-section-heading">
                    <span class="report-edit-section-icon material-symbols-rounded" aria-hidden="true">payments</span>
                    <div><h2>Informasi Biaya</h2><p>Periksa nominal berdasarkan tarif resmi layanan.</p></div>
                </div>
                <div class="report-edit-form-grid">
                    <div class="report-edit-field">
                        <label for="official_fee">Biaya resmi (Rupiah)</label>
                        <input id="official_fee" name="official_fee" type="number" min="0" step="1" required value="<?= e($values['official_fee']) ?>">
                    </div>
                    <div class="report-edit-field">
                        <label for="requested_amount">Biaya yang diminta (Rupiah)</label>
                        <input id="requested_amount" name="requested_amount" type="number" min="0" step="1" required value="<?= e($values['requested_amount']) ?>">
                    </div>
                </div>
                <p class="report-edit-hint">Selisih dihitung otomatis saat disimpan: biaya yang diminta dikurangi biaya resmi, dengan nilai minimum Rp 0.</p>
            </section>

            <?php if ($evidenceFiles): ?>
                <section class="card report-edit-card">
                    <div class="report-edit-section-heading">
                        <span class="report-edit-section-icon material-symbols-rounded" aria-hidden="true">attach_file</span>
                        <div><h2>Bukti Pendukung</h2><p>Bukti yang telah diunggah tidak diubah oleh formulir ini.</p></div>
                    </div>
                    <div class="report-edit-evidence-list">
                        <?php foreach ($evidenceFiles as $file): ?>
                            <?php
                            $path = ltrim((string)($file['file_path'] ?? ''), '/\\');
                            $fileUrl = '/admin/evidence.php?file_id=' . (int)($file['id'] ?? 0);
                            $size = (int)($file['file_size'] ?? 0);
                            ?>
                            <div class="report-edit-evidence-item">
                                <span class="material-symbols-rounded" aria-hidden="true">draft</span>
                                <div class="report-edit-evidence-info">
                                    <strong><?= e($file['file_name'] ?? basename($path)) ?></strong>
                                    <small><?= e($file['file_type'] ?? 'File') ?><?= $size > 0 ? ' · ' . number_format($size / 1024, 0, ',', '.') . ' KB' : '' ?></small>
                                </div>
                                <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener noreferrer">Buka file</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>

        <aside class="report-edit-side">
            <section class="card report-edit-card report-edit-sticky">
                <div class="report-edit-section-heading">
                    <span class="report-edit-section-icon material-symbols-rounded" aria-hidden="true">fact_check</span>
                    <div><h2>Hasil Pemeriksaan</h2><p>Perbarui status dan tingkat risiko.</p></div>
                </div>

                <div class="report-edit-field">
                    <label for="status">Status laporan</label>
                    <select id="status" name="status" required>
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $values['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="report-edit-field">
                    <label for="risk_level">Level risiko</label>
                    <select id="risk_level" name="risk_level" required>
                        <?php foreach ($riskOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $values['risk_level'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="report-edit-notice">
                    <span class="material-symbols-rounded" aria-hidden="true">info</span>
                    <p>Pastikan status dan level risiko sesuai hasil pemeriksaan. Simpan perubahan setelah seluruh data diperiksa.</p>
                </div>

                <button type="submit" class="btn btn-primary report-edit-save">
                    <span class="material-symbols-rounded" aria-hidden="true">save</span>
                    Simpan Perubahan
                </button>
                <a class="btn report-edit-cancel" href="/admin/report_detail.php?id=<?= (int)$id ?>">Batal</a>
            </section>
        </aside>
    </form>
</div>

<?php admin_footer(); ?>
