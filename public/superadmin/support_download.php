<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    $ticketId = $_GET['ticketId'] ?? '';
    $fileName = basename($_GET['file'] ?? '');
    $path = support_ticket_path($ticketId);
    if ($ticketId === '' || $fileName === '' || !file_exists($path)) {
        render_error_page('File not found');
        return;
    }
    $ticket = readJson($path);
    $allowed = array_filter($ticket['attachments'] ?? [], fn($f) => ($f['name'] ?? '') === $fileName);
    if (!$allowed) {
        render_error_page('Attachment missing');
        return;
    }
    $file = array_values($allowed)[0];
    $stored = $file['storedPath'] ?? '';
    if (!is_file($stored)) {
        render_error_page('Attachment unavailable');
        return;
    }
    $mime = $file['mime'] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($stored));
    header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
    readfile($stored);
    exit;
});
