<?php
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $schemeCode = strtoupper(trim($_GET['schemeCode'] ?? ''));
    $caseId = trim($_GET['caseId'] ?? '');
    $docId = trim($_GET['docId'] ?? '');
    $genId = trim($_GET['genId'] ?? '');
    if ($schemeCode === '' || $caseId === '' || $docId === '') {
        redirect('/contractor/schemes.php');
    }

    $case = readJson(scheme_case_core_path($schemeCode, $user['yojId'] ?? '', $caseId));
    if (!$case) {
        render_error_page('Case not found.');
    }

    if ($genId !== '') {
        $doc = readJson(scheme_document_generation_path($schemeCode, $user['yojId'] ?? '', $caseId, $docId, $genId));
    } else {
        $doc = scheme_document_latest_generation($schemeCode, $user['yojId'] ?? '', $caseId, $docId);
    }
    if (!$doc) {
        render_error_page('Document not generated yet.');
    }

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= sanitize($doc['label'] ?? 'Document'); ?></title>
        <style>
            body { font-family: Arial, sans-serif; padding:24px; color:#111827; }
            .placeholder-line { border-bottom:1px solid #9ca3af; display:inline-block; min-width:120px; }
        </style>
    </head>
    <body>
        <?= $doc['renderedHtml'] ?? ''; ?>
    </body>
    </html>
    <?php
});
