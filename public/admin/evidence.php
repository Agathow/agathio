<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

require_staff();

$fileId = filter_input(INPUT_GET, 'file_id', FILTER_VALIDATE_INT);
if (!$fileId || $fileId < 1) {
    http_response_code(400);
    exit('ID bukti tidak valid.');
}

$stmt = $pdo->prepare('SELECT file_name, file_path, file_type FROM report_evidence WHERE id = ? LIMIT 1');
$stmt->execute([$fileId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$file) {
    http_response_code(404);
    exit('Bukti tidak ditemukan di database.');
}

$relativePath = str_replace('\\', '/', trim((string)($file['file_path'] ?? '')));
$relativePath = ltrim($relativePath, '/');
$projectRoot = dirname(__DIR__, 2);
$publicRoot = dirname(__DIR__);

if (str_starts_with($relativePath, 'storage/reports/')) {
    $baseDirectory = realpath($projectRoot . '/storage/reports');
    $candidate = realpath($projectRoot . '/' . $relativePath);
} elseif (str_starts_with($relativePath, 'assets/uploads/reports/')) {
    $baseDirectory = realpath($publicRoot . '/assets/uploads/reports');
    $candidate = realpath($publicRoot . '/' . $relativePath);
} else {
    http_response_code(403);
    exit('Lokasi bukti tidak diizinkan.');
}

if ($baseDirectory === false || $candidate === false || !is_file($candidate) || !str_starts_with($candidate, $baseDirectory . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('File bukti tidak ditemukan di folder penyimpanan. Periksa file_path dan lokasi file di server.');
}

$mime = (string)($file['file_type'] ?? '');
if ($mime === '' || !str_contains($mime, '/')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($candidate) ?: 'application/octet-stream';
}
$downloadName = basename((string)($file['file_name'] ?? basename($candidate)));
$downloadName = str_replace(["\r", "\n", '"'], ['', '', ''], $downloadName);

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($candidate));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . $downloadName . '"');
readfile($candidate);
exit;
