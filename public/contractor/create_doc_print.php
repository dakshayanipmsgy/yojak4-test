<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $docId = trim((string)($_GET['docId'] ?? ''));
    if ($docId === '') {
        render_error_page('Document not found.');
        return;
    }

    $path = contractor_generated_docs_path($yojId) . '/' . $docId . '.json';
    if (!file_exists($path)) {
        render_error_page('Document not found.');
        return;
    }

    $doc = readJson($path);
    if (!$doc || ($doc['yojId'] ?? '') !== $yojId) {
        render_error_page('Unauthorized access.');
        return;
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo $doc['renderedHtml'] ?? '';
});
