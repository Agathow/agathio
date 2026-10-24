<?php
require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('Asia/Jakarta');
$pdo->beginTransaction();
try {
    $stmt = $pdo->query("SELECT id FROM reports WHERE status = 'awaiting_reporter_confirmation' AND reporter_deadline_at IS NOT NULL AND reporter_deadline_at <= NOW() FOR UPDATE");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $update = $pdo->prepare("UPDATE reports SET status = 'closed_no_response', closed_no_response_at = NOW() WHERE id = ? AND status = 'awaiting_reporter_confirmation'");
    $history = $pdo->prepare("INSERT INTO report_status_history (report_id, old_status, new_status, note, changed_by) VALUES (?, 'awaiting_reporter_confirmation', 'closed_no_response', ?, NULL)");
    foreach ($ids as $id) {
        $update->execute([(int)$id]);
        if ($update->rowCount() === 1) $history->execute([(int)$id, 'Tahap konfirmasi ditutup karena tidak ada respons sampai tenggat.']);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('SIGAP close expired reports: ' . $e->getMessage());
    exit(1);
}