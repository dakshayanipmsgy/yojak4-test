<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

$type = $_GET['type'] ?? '';

if ($type === 'dept_public_tender') {
    $deptId = normalize_dept_id(trim($_GET['deptId'] ?? ''));
    $ytdId = trim($_GET['ytdId'] ?? '');
    $fileKey = trim($_GET['file'] ?? '');

    if ($deptId === '' || $ytdId === '' || $fileKey === '') {
        render_error_page('Invalid download request.');
        exit;
    }

    $user = current_user();
    if (!$user || !in_array($user['type'] ?? '', ['contractor', 'department', 'superadmin'], true)) {
        redirect('/contractor/login.php');
    }

    $snapshot = load_public_tender_snapshot($deptId, $ytdId);
    if (!$snapshot) {
        render_error_page('File not found.');
        exit;
    }

    $attachment = null;
    foreach ($snapshot['attachmentsPublic'] ?? [] as $att) {
        if (($att['storedPath'] ?? '') === $fileKey || ($att['name'] ?? '') === $fileKey) {
            $attachment = $att;
            break;
        }
    }

    if (!$attachment) {
        render_error_page('Attachment not available.');
        exit;
    }

    $base = department_public_tenders_path($deptId);
    $fullPath = realpath($base . '/' . $attachment['storedPath']);
    if ($fullPath === false || !is_path_within($fullPath, $base) || !file_exists($fullPath)) {
        render_error_page('Attachment missing.');
        exit;
    }

    header('Content-Type: ' . ($attachment['mime'] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string)filesize($fullPath));
    header('Content-Disposition: attachment; filename="' . basename($attachment['name'] ?? 'file') . '"');
    readfile($fullPath);
    exit;
}

render_error_page('Unsupported download.');
