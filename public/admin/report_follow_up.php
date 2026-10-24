<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/_layout.php';

require_staff();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$currentUser = $_SESSION['user'] ?? [];
if (($currentUser['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Halaman ini hanya dapat diakses admin.');
}

if (empty($_SESSION['sigap_followup_csrf'])) $_SESSION['sigap_followup_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['sigap_followup_csrf'];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); exit('ID laporan tidak valid.'); }
$stmt = $pdo->prepare('SELECT id, report_code, title, status FROM reports WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$report) { http_response_code(404); exit('Laporan tidak ditemukan.'); }

$errors = [];
$success = isset($_GET['saved']) && $_GET['saved'] === '1';
$allowedActions = [
    'clarification_request' => 'Permintaan klarifikasi',
    'initial_review' => 'Pemeriksaan awal',
    'official_warning' => 'Teguran resmi',
    'service_correction' => 'Perbaikan layanan',
    'official_fee_confirmation' => 'Konfirmasi tarif resmi',
    'other' => 'Tindakan lainnya',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $postedToken)) $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';

    $actionType = (string) ($_POST['action_type'] ?? '');
    $actionDate = (string) ($_POST['action_date'] ?? '');
    $summary = trim((string) ($_POST['summary'] ?? ''));
    $publicNote = trim((string) ($_POST['public_note'] ?? ''));
    $publish = isset($_POST['publish_result']) ? 1 : 0;
    if (!isset($allowedActions[$actionType])) $errors[] = 'Pilih jenis tindakan yang valid.';
    $dateObj = DateTime::createFromFormat('Y-m-d', $actionDate);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $actionDate) $errors[] = 'Tanggal tindakan tidak valid.';
    if (mb_strlen($summary) < 15) $errors[] = 'Ringkasan tindakan minimal 15 karakter.';
    if (mb_strlen($summary) > 10000 || mb_strlen($publicNote) > 5000) $errors[] = 'Teks terlalu panjang.';

    $files = [];
    $totalBytes = 0;
    $maxFileSize = 5 * 1024 * 1024;
    $maxFileCount = 5;
    $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    if (isset($_FILES['evidence']) && is_array($_FILES['evidence']['name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $submittedFileCount = count(array_filter($_FILES['evidence']['error'], static fn($code) => $code !== UPLOAD_ERR_NO_FILE));
        if ($submittedFileCount > $maxFileCount) $errors[] = 'Maksimal 5 file bukti untuk setiap tindak lanjut.';
        foreach ($_FILES['evidence']['name'] as $i => $originalName) {
            $errorCode = $_FILES['evidence']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($errorCode === UPLOAD_ERR_NO_FILE) continue;
            if ($errorCode !== UPLOAD_ERR_OK) { $errors[] = 'Salah satu file gagal diunggah.'; continue; }
            $tmp = $_FILES['evidence']['tmp_name'][$i] ?? '';
            $size = (int) ($_FILES['evidence']['size'][$i] ?? 0);
            if (!is_uploaded_file($tmp)) { $errors[] = 'File upload tidak valid.'; continue; }
            if ($size < 1 || $size > $maxFileSize) { $errors[] = 'Setiap file maksimal 5 MB.'; continue; }
            $mime = $finfo->file($tmp) ?: '';
            if (!isset($allowedMimes[$mime])) { $errors[] = 'Format bukti harus PDF, JPG, PNG, atau WEBP.'; continue; }
            $totalBytes += $size;
            $files[] = ['tmp' => $tmp, 'original' => basename((string) $originalName), 'size' => $size, 'mime' => $mime, 'ext' => $allowedMimes[$mime]];
        }
    }
    if ($totalBytes > 25 * 1024 * 1024) $errors[] = 'Total ukuran semua bukti maksimal 25 MB.';
    if ($publish && count($files) === 0) $errors[] = 'Untuk memublikasikan hasil, lampirkan minimal satu bukti dokumen atau foto.';

    if (!$errors) {
        $uploadDirectory = __DIR__ . '/../../public/assets/uploads/followups';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) $errors[] = 'Folder upload bukti tidak dapat dibuat.';
    }
    if (!$errors) {
        $moved = [];
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT status FROM reports WHERE id = ? FOR UPDATE');
            $lock->execute([$id]);
            $lockedStatus = $lock->fetchColumn();
            if ($lockedStatus === false) throw new RuntimeException('Laporan tidak ditemukan.');

            $insert = $pdo->prepare('INSERT INTO report_follow_ups (report_id, created_by, action_type, action_date, summary, public_note, publication_status, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $adminId = (int) ($currentUser['id'] ?? 0);
            $publicationStatus = $publish ? 'published' : 'draft';
            $publishedAt = $publish ? date('Y-m-d H:i:s') : null;
            $insert->execute([$id, $adminId ?: null, $actionType, $actionDate, $summary, $publicNote !== '' ? $publicNote : null, $publicationStatus, $publishedAt]);
            $followUpId = (int) $pdo->lastInsertId();

            $insertEvidence = $pdo->prepare('INSERT INTO report_follow_up_evidence (follow_up_id, original_name, stored_name, file_path, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($files as $file) {
                $storedName = bin2hex(random_bytes(20)) . '.' . $file['ext'];
                $destination = $uploadDirectory . '/' . $storedName;
                if (!move_uploaded_file($file['tmp'], $destination)) throw new RuntimeException('Bukti gagal disimpan.');
                $moved[] = $destination;
                $relativePath = '/assets/uploads/followups/' . $storedName;
                $insertEvidence->execute([$followUpId, $file['original'], $storedName, $relativePath, $file['mime'], $file['size']]);
            }

            $lockedStatus = (string) $lockedStatus;
            if ($publish) {
                $deadline = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
                $businessDaysAdded = 0;
                while ($businessDaysAdded < 3) {
                    $deadline = $deadline->modify('+1 day');
                    if ((int) $deadline->format('N') >= 6) continue;
                    $holidayCheck = $pdo->prepare('SELECT 1 FROM public_holidays WHERE holiday_date = ? LIMIT 1');
                    $holidayCheck->execute([$deadline->format('Y-m-d')]);
                    if ($holidayCheck->fetchColumn()) continue;
                    $businessDaysAdded++;
                }
                $deadlineAt = $deadline->format('Y-m-d H:i:s');
                $newStatus = 'awaiting_reporter_confirmation';
                $update = $pdo->prepare('UPDATE reports SET status = ?, reporter_deadline_at = ?, reporter_response = NULL, reporter_response_note = NULL, reporter_response_at = NULL WHERE id = ?');
                $update->execute([$newStatus, $deadlineAt, $id]);
                $history = $pdo->prepare('INSERT INTO report_status_history (report_id, old_status, new_status, note, changed_by) VALUES (?, ?, ?, ?, ?)');
                $history->execute([$id, $lockedStatus, $newStatus, 'Hasil tindak lanjut dipublikasikan kepada pelapor. Tenggat konfirmasi: ' . $deadlineAt, $adminId ?: null]);
            }
            $pdo->commit();
            header('Location: /admin/report_follow_up.php?id=' . $id . '&saved=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($moved ?? [] as $path) if (is_file($path)) @unlink($path);
            error_log('SIGAP follow-up save: ' . $e->getMessage());
            $errors[] = 'Tindak lanjut belum berhasil disimpan. Periksa migrasi database dan coba kembali.';
        }
    }
}

$historyStmt = $pdo->prepare('SELECT f.*, u.name AS admin_name FROM report_follow_ups f LEFT JOIN users u ON u.id = f.created_by WHERE f.report_id = ? ORDER BY f.created_at DESC, f.id DESC');
$historyStmt->execute([$id]);
$followUps = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
$evidenceStmt = $pdo->prepare('SELECT e.*, f.report_id FROM report_follow_up_evidence e JOIN report_follow_ups f ON f.id = e.follow_up_id WHERE f.report_id = ? ORDER BY e.id DESC');
$evidenceStmt->execute([$id]);
$followUpEvidence = $evidenceStmt->fetchAll(PDO::FETCH_ASSOC);

admin_header('Tindak Lanjut — ' . $report['report_code'], 'reports');
?>

<link rel="stylesheet"
      href="/assets/css/admin_report_follow_up.css">
      
<div class="report-followup-page">
    <div class="report-followup-heading">
        <a class="report-detail-back" href="/admin/report_detail.php?id=<?= (int) $id ?>"><span class="material-symbols-rounded">arrow_back</span> Kembali ke Report Detail</a>
    </div>
    <section class="card report-followup-report-card">
        <div><small>Report ID</small><strong><?= e((string) $report['report_code']) ?></strong></div>
        <div><small>Judul laporan</small><strong><?= e((string) $report['title']) ?></strong></div>
        <div><small>Status saat ini</small><strong><?= e(ucwords(str_replace('_', ' ', (string) $report['status']))) ?></strong></div>
    </section>
    <?php if ($success): ?><div class="report-followup-alert is-success">Tindak lanjut berhasil disimpan.</div><?php endif; ?>
    <?php if ($errors): ?><div class="report-followup-alert is-error"><strong>Periksa hal berikut:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <form class="card report-followup-form" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="report_id" value="<?= (int) $id ?>">
        <div class="report-followup-section-title"><span class="material-symbols-rounded">edit_note</span><div><h2>Catatan Tindakan</h2><p>Isi berdasarkan tindakan yang sudah terjadi, bukan rencana yang belum dilakukan.</p></div></div>
        <div class="report-followup-fields">
            <label>Jenis tindakan <select name="action_type" required><?php foreach ($allowedActions as $value => $label): ?><option value="<?= e($value) ?>" <?= (($_POST['action_type'] ?? '') === $value) ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Tanggal tindakan <input type="date" name="action_date" required value="<?= e((string) ($_POST['action_date'] ?? date('Y-m-d'))) ?>"></label>
            <label class="field-full">Ringkasan tindakan <textarea name="summary" rows="5" required minlength="15" maxlength="10000" placeholder="Jelaskan siapa yang melakukan tindakan, apa yang dilakukan, dan hasil yang sudah diketahui."><?= e((string) ($_POST['summary'] ?? '')) ?></textarea></label>
            <label class="field-full">Catatan yang ditampilkan kepada pelapor <textarea name="public_note" rows="3" maxlength="5000" placeholder="Tuliskan penjelasan yang aman untuk dibagikan. Jangan cantumkan data pribadi atau informasi rahasia."><?= e((string) ($_POST['public_note'] ?? '')) ?></textarea><small>Catatan ini akan terlihat oleh pelapor setelah hasil dipublikasikan.</small></label>
        </div>
        <div class="report-followup-section-title evidence-title"><span class="material-symbols-rounded">attach_file</span><div><h2>Bukti Pendukung</h2><p>Tambahkan foto, bukti pembayaran, atau dokumen pendukung yang relevan.</p></div></div>
        <div class="report-followup-upload">
            <input type="file" id="followup-evidence" name="evidence[]" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" multiple required>
            <label class="report-followup-upload-area" for="followup-evidence">
                <span class="material-symbols-rounded upload-icon" aria-hidden="true">cloud_upload</span>
                <strong>Tambahkan Bukti</strong>
                <span class="upload-description">Foto, bukti pembayaran, atau dokumen pendukung</span>
                <span class="upload-select-button">Pilih File</span>
            </label>
            <div class="followup-evidence-preview" id="followup-evidence-preview" aria-live="polite"></div>
            <p class="followup-evidence-help">Opsional untuk draft · Maksimal 5 file · JPG, PNG, WEBP, atau PDF · Maksimal 5 MB per file. Demi keamanan, hindari mengunggah NIK, nomor rekening, password, atau data pribadi yang tidak diperlukan.</p>
        </div>
        <label class="report-followup-publish"><input type="checkbox" name="publish_result" value="1"><span><strong>Publikasikan hasil kepada pelapor</strong><small>Jika dicentang, hasil dan bukti akan ditampilkan pada halaman tracking. Pastikan dokumen sudah disamarkan dan aman dipublikasikan.</small></span></label>
        <div class="report-followup-notice"><span class="material-symbols-rounded">privacy_tip</span><p>Jangan unggah identitas pelapor, nomor identitas pribadi, data sensitif, atau dokumen internal yang tidak diizinkan untuk dipublikasikan. Surat teguran hanya boleh dilampirkan jika benar-benar diterbitkan oleh pihak berwenang.</p></div>
        <div class="report-followup-form-actions"><a class="btn report-followup-secondary" href="/admin/report_detail.php?id=<?= (int) $id ?>">Batal</a><button class="btn btn-primary" type="submit"><span class="material-symbols-rounded">save</span> Simpan Tindak Lanjut</button></div>
    </form>

    <section class="card report-followup-history-card"><h2>Riwayat Tindak Lanjut</h2><p class="muted">Draft hanya terlihat oleh admin; hasil yang dipublikasikan akan tersedia untuk pelapor melalui halaman tracking.</p>
        <?php if (!$followUps): ?><div class="report-follow-up-empty"><span class="material-symbols-rounded">pending_actions</span><p>Belum ada tindak lanjut.</p></div><?php else: ?><div class="report-follow-up-list"><?php foreach ($followUps as $item): ?><article class="report-follow-up-item"><div class="report-follow-up-item-head"><strong><?= e($allowedActions[$item['action_type']] ?? 'Tindakan lainnya') ?></strong><span class="report-follow-up-pill <?= $item['publication_status'] === 'published' ? 'is-published' : 'is-draft' ?>"><?= $item['publication_status'] === 'published' ? 'Dipublikasikan' : 'Draft' ?></span></div><small><?= e(date('d M Y', strtotime($item['action_date']))) ?> · <?= e($item['admin_name'] ?? 'Admin') ?></small><p><?= nl2br(e($item['summary'])) ?></p><?php if (!empty($item['public_note'])): ?><p class="muted"><?= nl2br(e($item['public_note'])) ?></p><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('followup-evidence');
    const preview = document.getElementById('followup-evidence-preview');
    if (!input || !preview) return;
    const maxBytes = 5 * 1024 * 1024;
    const maxFiles = 5;
    const allowed = /\.(pdf|jpe?g|png|webp)$/i;
    let selectedFiles = [];

    function formatSize(bytes) {
        return bytes >= 1024 * 1024 ? (bytes / (1024 * 1024)).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }
    function syncInput() {
        const transfer = new DataTransfer();
        selectedFiles.forEach(file => transfer.items.add(file));
        input.files = transfer.files;
    }
    function renderPreview() {
        preview.replaceChildren();
        selectedFiles.forEach((file, index) => {
            const card = document.createElement('article');
            card.className = 'followup-evidence-file';
            const visual = document.createElement('div');
            visual.className = 'followup-evidence-file__visual';
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.alt = 'Pratinjau ' + file.name;
                img.src = URL.createObjectURL(file);
                visual.appendChild(img);
            } else {
                const icon = document.createElement('span');
                icon.className = 'material-symbols-rounded';
                icon.textContent = 'picture_as_pdf';
                visual.appendChild(icon);
            }
            const name = document.createElement('p');
            name.className = 'followup-evidence-file__name';
            name.textContent = file.name;
            const size = document.createElement('small');
            size.textContent = formatSize(file.size);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'followup-evidence-file__remove';
            remove.textContent = 'Hapus file';
            remove.setAttribute('aria-label', 'Hapus ' + file.name);
            remove.addEventListener('click', function () {
                selectedFiles.splice(index, 1);
                syncInput();
                renderPreview();
            });
            card.append(visual, name, size, remove);
            preview.appendChild(card);
        });
        input.required = selectedFiles.length === 0;
    }
    input.addEventListener('change', function () {
        const incoming = Array.from(input.files || []);
        const combined = selectedFiles.concat(incoming);
        if (combined.length > maxFiles) {
            alert('Maksimal 5 file bukti.');
            syncInput();
            return;
        }
        if (incoming.some(file => file.size < 1 || file.size > maxBytes || !allowed.test(file.name))) {
            alert('Pilih file PDF, JPG, PNG, atau WEBP dengan ukuran maksimal 5 MB per file.');
            syncInput();
            return;
        }
        const unique = [];
        const seen = new Set();
        combined.forEach(file => {
            const key = file.name + ':' + file.size + ':' + file.lastModified;
            if (!seen.has(key)) { seen.add(key); unique.push(file); }
        });
        selectedFiles = unique;
        syncInput();
        renderPreview();
    });
    const form = input.closest('form');
    if (form) form.addEventListener('reset', function () {
        selectedFiles = [];
        preview.replaceChildren();
    });
});
</script>

<?php admin_footer(); ?>