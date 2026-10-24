<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

$report = null;
$error = null;

$reportCode = trim($_POST['report_code'] ?? '');
$accessCode = trim($_POST['access_code'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    check_csrf();

    if ($reportCode === '' || $accessCode === '') {

        $error = 'Report ID dan Access Code wajib diisi.';

    } else {

        $statement = $pdo->prepare(
            "
            SELECT
                r.*,
                ps.name AS service,
                l.city,
                l.district
            FROM reports r
            LEFT JOIN public_services ps
                ON ps.id = r.service_id
            LEFT JOIN locations l
                ON l.id = r.location_id
            WHERE r.report_code = ?
              AND r.access_code = ?
            LIMIT 1
            "
        );

        $statement->execute([
            $reportCode,
            $accessCode
        ]);

        $report = $statement->fetch();

        if (!$report) {
            $error = 'Report ID atau Access Code tidak ditemukan. Pastikan data yang kamu masukkan benar.';
        } elseif (isset($_POST['reporter_action'])) {
            $action = (string) $_POST['reporter_action'];
            $responseNote = trim((string) ($_POST['reporter_response_note'] ?? ''));
            $currentStatus = (string) ($report['status'] ?? '');
            $deadline = !empty($report['reporter_deadline_at']) ? strtotime((string) $report['reporter_deadline_at']) : false;
            $now = time();

            if ($currentStatus !== 'awaiting_reporter_confirmation') {
                $error = 'Laporan ini tidak sedang menunggu konfirmasi pelapor.';
            } elseif ($deadline && $now > $deadline) {
                $error = 'Batas konfirmasi sudah berakhir. Silakan hubungi kanal pengaduan resmi jika masih ada keberatan.';
            } elseif (!in_array($action, ['accept', 'dispute'], true)) {
                $error = 'Pilihan konfirmasi tidak valid.';
            } elseif ($action === 'dispute' && mb_strlen($responseNote) < 10) {
                $error = 'Jelaskan keberatan kamu minimal 10 karakter agar dapat ditindaklanjuti.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $lock = $pdo->prepare('SELECT status FROM reports WHERE id = ? FOR UPDATE');
                    $lock->execute([(int) $report['id']]);
                    $lockedStatus = $lock->fetchColumn();
                    if ($lockedStatus !== 'awaiting_reporter_confirmation') {
                        throw new RuntimeException('Status laporan sudah berubah. Muat ulang halaman.');
                    }
                    $newStatus = $action === 'accept' ? 'resolved' : 'reporter_disputed';
                    $responseValue = $action === 'accept' ? 'accepted' : 'disputed';
                    $update = $pdo->prepare('UPDATE reports SET status = ?, reporter_response = ?, reporter_response_note = ?, reporter_response_at = NOW() WHERE id = ?');
                    $update->execute([$newStatus, $responseValue, $action === 'dispute' ? $responseNote : null, (int) $report['id']]);
                    $history = $pdo->prepare('INSERT INTO report_status_history (report_id, old_status, new_status, note, changed_by) VALUES (?, ?, ?, ?, NULL)');
                    $historyNote = $action === 'accept' ? 'Pelapor menyatakan hasil tindak lanjut sudah sesuai.' : 'Pelapor mengajukan keberatan: ' . $responseNote;
                    $history->execute([(int) $report['id'], (string) $lockedStatus, $newStatus, $historyNote]);
                    $pdo->commit();
                    $statement->execute([$reportCode, $accessCode]);
                    $report = $statement->fetch();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('SIGAP reporter confirmation: ' . $e->getMessage());
                    $error = 'Konfirmasi belum berhasil disimpan. Muat ulang halaman dan coba kembali.';
                }
            }
        }
    }
}

$publishedFollowUps = [];
$publicEvidenceByFollowUp = [];
if ($report) {
    try {
        $followUpStmt = $pdo->prepare("SELECT id, action_type, action_date, summary, public_note, published_at FROM report_follow_ups WHERE report_id = ? AND publication_status = 'published' ORDER BY action_date DESC, id DESC");
        $followUpStmt->execute([(int) $report['id']]);
        $publishedFollowUps = $followUpStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($publishedFollowUps) {
            $followUpIds = array_map('intval', array_column($publishedFollowUps, 'id'));
            $placeholders = implode(',', array_fill(0, count($followUpIds), '?'));
            $evidenceStmt = $pdo->prepare("SELECT follow_up_id, original_name, file_path, mime_type, file_size FROM report_follow_up_evidence WHERE follow_up_id IN ($placeholders) ORDER BY id ASC");
            $evidenceStmt->execute($followUpIds);
            foreach ($evidenceStmt->fetchAll(PDO::FETCH_ASSOC) as $evidence) {
                $publicEvidenceByFollowUp[(int) $evidence['follow_up_id']][] = $evidence;
            }
        }
    } catch (Throwable $e) {
        error_log('SIGAP public follow-up lookup: ' . $e->getMessage());
        $publishedFollowUps = [];
        $publicEvidenceByFollowUp = [];
    }
}

$statusLabels = [
    'new' => 'Laporan Diterima',
    'pending_review' => 'Menunggu Verifikasi',
    'under_review' => 'Sedang Ditinjau',
    'follow_up_in_progress' => 'Sedang Ditindaklanjuti',
    'awaiting_reporter_confirmation' => 'Menunggu Konfirmasi Kamu',
    'reporter_disputed' => 'Keberatan Menunggu Tinjauan',
    'resolved' => 'Selesai',
    'closed_no_response' => 'Ditutup — Tidak Ada Konfirmasi',
];
$statusLabel = $report ? ($statusLabels[(string) $report['status']] ?? status_label((string) $report['status'])) : '';

?>

<!doctype html>
<html lang="id">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Cek Status Laporan — SIGAP</title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >
    <link rel="stylesheet" href="/assets/css/check_status.css">
    
    <link rel="icon" type="image/png" href="/assets/images/logo tab.png">
    <link rel="apple-touch-icon" href="/assets/images/logo tab.png">
</head>

<body>

    <?php require_once __DIR__ . '/../../config/navbar.php'; ?>


    <main>

        <section class="section check-status-page">

            <div class="container">

                <div class="check-status-header">

                    <span class="eyebrow">
                        <span class="material-symbols-rounded" aria-hidden="true">data_check</span>
                        Pelacakan Laporan
                    </span>

                    <h1 class="page-title">
                        Cek Status Laporan
                    </h1>

                    <p class="muted">
                        Masukkan Report ID dan Access Code untuk melihat perkembangan laporan kamu.
                    </p>

                </div>


                <div class="card check-status-card">

                    <form
                        method="post"
                        class="check-status-form"
                    >

                        <input
                            type="hidden"
                            name="csrf"
                            value="<?= e(csrf_token()) ?>"
                        >


                        <div class="form-group">

                            <label for="report_code">
                                Report ID
                            </label>

                            <input
                                type="text"
                                id="report_code"
                                name="report_code"
                                value="<?= e($reportCode) ?>"
                                placeholder="Contoh: SIGAP-26-001284"
                                autocomplete="off"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="access_code">
                                Access Code
                            </label>

                            <input
                                type="text"
                                id="access_code"
                                name="access_code"
                                value="<?= e($accessCode) ?>"
                                placeholder="Contoh: X7K9-PQ28"
                                autocomplete="off"
                                required
                            >

                        </div>


                        <div class="form-divider">
                            
                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary btn-full"
                        >
                            Cek Status
                        </button>

                    </form>


                    <?php if ($error): ?>

                        <div class="notice-error">

                            <?= e($error) ?>

                        </div>

                    <?php endif; ?>


                    <?php if ($report): ?>

                        <div class="report-result">

                            <div class="report-result-header">

                                <div>

                                    <span class="eyebrow">
                                        Laporan Ditemukan
                                    </span>

                                    <h2>
                                        <?= e($report['title']) ?>
                                    </h2>

                                </div>

                                <span class="status-badge status-<?= e($report['status']) ?>">
                                    <?= e($statusLabel) ?>
                                </span>

                            </div>


                            <div class="report-result-grid">

                                <div class="detail-info-item">

                                    <span>
                                        Report ID
                                    </span>

                                    <strong>
                                        <?= e($report['report_code']) ?>
                                    </strong>

                                </div>


                                <div class="detail-info-item">

                                    <span>
                                        Layanan
                                    </span>

                                    <strong>
                                        <?= e($report['service'] ?? '-') ?>
                                    </strong>

                                </div>


                                <div class="detail-info-item">

                                    <span>
                                        Lokasi
                                    </span>

                                    <strong>
                                        <?= e(
                                            trim(
                                                ($report['district'] ?? '') .
                                                (($report['district'] ?? '') && ($report['city'] ?? '') ? ', ' : '') .
                                                ($report['city'] ?? '')
                                            ) ?: '-'
                                        ) ?>
                                    </strong>

                                </div>


                                <div class="detail-info-item">

                                    <span>
                                        Dibuat
                                    </span>

                                    <strong>
                                        <?= e($report['created_at']) ?>
                                    </strong>

                                </div>

                            </div>


                            <?php if (!empty($report['chronology'])): ?>

                                <div class="report-result-description">

                                    <span>
                                        Kronologi
                                    </span>

                                    <p>
                                        <?= nl2br(e($report['chronology'])) ?>
                                    </p>

                                </div>

                            <?php endif; ?>

                            <?php if ($publishedFollowUps): ?>
                                <section class="public-followups" aria-labelledby="public-followups-title">
                                    <div class="public-followups-heading">
                                        <span class="public-followups-icon material-symbols-rounded" aria-hidden="true">verified_user</span>
                                        <div>
                                            <h3 id="public-followups-title">Hasil Tindak Lanjut</h3>
                                            <p>Informasi berikut telah dipublikasikan oleh admin SIGAP.</p>
                                        </div>
                                    </div>
                                    <?php foreach ($publishedFollowUps as $followUp): ?>
                                        <article class="public-followup-item">
                                            <div class="public-followup-meta">
                                                <span class="public-followup-type"><?= e(ucfirst(str_replace('_', ' ', (string) $followUp['action_type']))) ?></span>
                                                <time datetime="<?= e((string) $followUp['action_date']) ?>">Tanggal tindakan: <?= e(date('d M Y', strtotime((string) $followUp['action_date']))) ?></time>
                                            </div>
                                            <?php if (!empty($followUp['public_note'])): ?>
                                                <h4>Penjelasan untuk Pelapor</h4>
                                                <p><?= nl2br(e((string) $followUp['public_note'])) ?></p>
                                            <?php else: ?>
                                                <p><?= nl2br(e((string) $followUp['summary'])) ?></p>
                                            <?php endif; ?>
                                            <?php $evidenceItems = $publicEvidenceByFollowUp[(int) $followUp['id']] ?? []; ?>
                                            <?php if ($evidenceItems): ?>
                                                <div class="public-evidence-list">
                                                    <h4>Bukti yang Dipublikasikan</h4>
                                                    <div class="public-evidence-grid">
                                                        <?php foreach ($evidenceItems as $evidence): ?>
                                                            <a class="public-evidence-link" href="<?= e((string) $evidence['file_path']) ?>" target="_blank" rel="noopener noreferrer">
                                                                <span class="material-symbols-rounded" aria-hidden="true"><?= str_starts_with((string) $evidence['mime_type'], 'image/') ? 'image' : 'description' ?></span>
                                                                <span><strong><?= e((string) $evidence['original_name']) ?></strong><small><?= e(number_format(((int) $evidence['file_size']) / 1024, 0, ',', '.')) ?> KB · Buka bukti</small></span>
                                                                <span class="material-symbols-rounded" aria-hidden="true">open_in_new</span>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </section>
                            <?php else: ?>
                                <section class="public-followups public-followups-empty">
                                    <div class="public-followups-heading">
                                        <span class="public-followups-icon material-symbols-rounded" aria-hidden="true">hourglass_top</span>
                                        <div><h3>Informasi Tindak Lanjut</h3><p>Hasil tindak lanjut akan muncul di sini setelah diverifikasi dan dipublikasikan oleh admin SIGAP.</p></div>
                                    </div>
                                </section>
                            <?php endif; ?>

                            <?php if (($report['status'] ?? '') === 'awaiting_reporter_confirmation'): ?>
                                <section class="reporter-confirmation">
                                    <span class="confirmation-label">Tindakan diperlukan</span>
                                    <h3>Apakah hasil tindak lanjut sudah sesuai?</h3>
                                    <p>Periksa catatan dan bukti di atas. Kamu memiliki batas waktu sampai <strong><?= !empty($report['reporter_deadline_at']) ? e(date('d M Y, H:i', strtotime((string) $report['reporter_deadline_at']))) . ' WIB' : '3 hari kerja sejak hasil dipublikasikan' ?></strong> untuk memberikan tanggapan.</p>
                                    <form method="post" class="reporter-response-form">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="report_code" value="<?= e($reportCode) ?>">
                                        <input type="hidden" name="access_code" value="<?= e($accessCode) ?>">
                                        <label for="reporter_response_note">Catatan keberatan (wajib jika mengajukan keberatan)</label>
                                        <textarea id="reporter_response_note" name="reporter_response_note" rows="3" maxlength="3000" placeholder="Jelaskan bagian hasil yang belum sesuai agar dapat ditinjau kembali."></textarea>
                                        <input type="hidden" name="reporter_action" id="reporterActionInput" value="">
                                        <div class="reporter-response-actions">
                                            <button type="button" class="btn btn-primary" data-confirm-action="accept">Saya Setuju, Laporan Selesai</button>
                                            <button type="button" class="btn btn-outline" data-confirm-action="dispute">Saya Mengajukan Keberatan</button>
                                        </div>
                                    </form>
                                </section>
                            <?php elseif (($report['status'] ?? '') === 'reporter_disputed'): ?>
                                <div class="reporter-response-notice"><strong>Keberatan kamu sudah tercatat.</strong><p>Admin SIGAP perlu meninjau kembali keberatan tersebut. Periksa halaman ini lagi untuk melihat pembaruan.</p></div>
                            <?php elseif (($report['status'] ?? '') === 'resolved'): ?>
                                <div class="reporter-response-notice is-success"><strong>Laporan dinyatakan selesai.</strong><p>Terima kasih telah memeriksa hasil tindak lanjut SIGAP.</p></div>
                            <?php endif; ?>

                        </div>

                    <?php endif; ?>


                    <div class="form-divider">

                        <div class="status-note">
                            <h3>Simpan Access Code kamu</h3>

                            <p>
                                SIGAP tidak dapat memulihkan Access Code yang hilang.
                                Gunakan Report ID dan Access Code untuk mengecek
                                laporan ini kembali.
                            </p>
                        </div>
                    </div>

                </div>

            </div>

        </section>

    </main>




    <div class="sigap-confirm-overlay" id="sigapConfirmModal" aria-hidden="true">
        <section class="sigap-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="sigapConfirmTitle" aria-describedby="sigapConfirmMessage">
            <button type="button" class="sigap-confirm-close" id="sigapConfirmClose" aria-label="Tutup popup">&times;</button>
            <div class="sigap-confirm-icon material-symbols-rounded" aria-hidden="true">fact_check</div>
            <h2 id="sigapConfirmTitle">Konfirmasi Hasil Tindak Lanjut</h2>
            <p id="sigapConfirmMessage">Konfirmasi bahwa hasil tindak lanjut sudah sesuai dan laporan dapat dinyatakan selesai?</p>
            <div class="sigap-confirm-actions">
                <button type="button" class="sigap-confirm-cancel" id="sigapConfirmCancel">Periksa Kembali</button>
                <button type="button" class="sigap-confirm-submit" id="sigapConfirmSubmit">Ya, Selesaikan Laporan</button>
            </div>
        </section>
    </div>

    <script>
    (() => {
        const modal = document.getElementById('sigapConfirmModal');
        const title = document.getElementById('sigapConfirmTitle');
        const message = document.getElementById('sigapConfirmMessage');
        const submitButton = document.getElementById('sigapConfirmSubmit');
        const actionInput = document.getElementById('reporterActionInput');
        const form = document.querySelector('.reporter-response-form');
        if (!modal || !form || !actionInput) return;

        let pendingAction = '';
        const openModal = (action) => {
            pendingAction = action;
            actionInput.value = action;
            if (action === 'dispute') {
                title.textContent = 'Konfirmasi Pengajuan Keberatan';
                message.textContent = 'Kamu akan mengajukan keberatan atas hasil tindak lanjut ini. Pastikan catatan keberatan sudah diisi dengan jelas.';
                submitButton.textContent = 'Ya, Ajukan Keberatan';
            } else {
                title.textContent = 'Konfirmasi Hasil Tindak Lanjut';
                message.textContent = 'Konfirmasi bahwa hasil tindak lanjut sudah sesuai dan laporan dapat dinyatakan selesai?';
                submitButton.textContent = 'Ya, Selesaikan Laporan';
            }
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('sigap-modal-open');
            submitButton.focus();
        };
        const closeModal = () => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('sigap-modal-open');
            pendingAction = '';
            actionInput.value = '';
        };

        document.querySelectorAll('[data-confirm-action]').forEach((button) => {
            button.addEventListener('click', () => openModal(button.dataset.confirmAction));
        });
        document.getElementById('sigapConfirmClose')?.addEventListener('click', closeModal);
        document.getElementById('sigapConfirmCancel')?.addEventListener('click', closeModal);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });
        submitButton.addEventListener('click', () => {
            if (!pendingAction) return;
            actionInput.value = pendingAction;
            if (pendingAction === 'dispute') {
                const note = form.querySelector('textarea[name="reporter_response_note"]');
                if (!note || note.value.trim().length < 10) {
                    closeModal();
                    note?.focus();
                    note?.setCustomValidity('Jelaskan keberatan minimal 10 karakter.');
                    note?.reportValidity();
                    note?.addEventListener('input', () => note.setCustomValidity(''), { once: true });
                    return;
                }
            }
            form.submit();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
        });
    })();
    </script>

    <?php require_once __DIR__ . '/../../config/footer.php'; ?>

</body>

</html>