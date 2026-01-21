<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/create_docs.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $docId = trim((string)($_POST['docId'] ?? ''));
    if ($docId === '') {
        set_flash('error', 'Document not found.');
        redirect('/contractor/create_docs.php');
    }

    $path = contractor_generated_docs_path($yojId) . '/' . $docId . '.json';
    if (!file_exists($path)) {
        set_flash('error', 'Document not found.');
        redirect('/contractor/create_docs.php');
    }

    $doc = readJson($path);
    if (!$doc || ($doc['yojId'] ?? '') !== $yojId) {
        render_error_page('Unauthorized access.');
        return;
    }

    unlink($path);
    logEvent(create_docs_log_path(), [
        'event' => 'doc_deleted',
        'ownerType' => 'contractor',
        'yojId' => $yojId,
        'docId' => $docId,
    ]);

    set_flash('success', 'Document deleted.');
    redirect('/contractor/create_docs.php');
});
