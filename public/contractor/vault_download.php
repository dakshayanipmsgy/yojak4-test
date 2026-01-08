<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $contractor = load_contractor($user['yojId']);
    if (!$contractor) {
        render_error_page('Contractor not found.');
        return;
    }

    $fileId = trim($_GET['fileId'] ?? '');
    if ($fileId === '') {
        render_error_page('Missing file.');
        return;
    }

    $index = contractor_vault_index($contractor['yojId']);
    $record = null;
    foreach ($index as $item) {
        if (($item['fileId'] ?? '') === $fileId && empty($item['deletedAt'])) {
            $record = $item;
            break;
        }
    }

    if (!$record) {
        render_error_page('File not found.');
        return;
    }

    $storedPath = (string)($record['storedPath'] ?? '');
    $candidate = $storedPath;
    if ($storedPath !== '' && $storedPath[0] === '/') {
        $publicCandidate = PUBLIC_PATH . $storedPath;
        if (file_exists($publicCandidate)) {
            $candidate = $publicCandidate;
        }
    }
    if ($candidate === '' || !file_exists($candidate)) {
        render_error_page('File missing on server.');
        return;
    }

    $mime = $record['mime'] ?? 'application/octet-stream';
    $filename = $record['originalName'] ?? ($record['storedName'] ?? 'vault-file');

    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename((string)$filename) . '"');
    header('Content-Length: ' . filesize($candidate));
    readfile($candidate);
});
