<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/templates.php?tab=request');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $title = trim((string)($_POST['title'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $makeGlobal = !empty($_POST['make_global']);

    if (strlen($title) < 3 || strlen($title) > 80) {
        set_flash('error', 'Title must be between 3 and 80 characters.');
        redirect('/contractor/templates.php?tab=request');
    }

    if (empty($_FILES['sample']['name'])) {
        set_flash('error', 'Please upload a PDF sample.');
        redirect('/contractor/templates.php?tab=request');
    }

    $file = $_FILES['sample'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'Upload failed.');
        redirect('/contractor/templates.php?tab=request');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        set_flash('error', 'Only PDF files are allowed.');
        redirect('/contractor/templates.php?tab=request');
    }

    $requestId = template_request_generate_id();
    $uploadDir = template_request_upload_dir($requestId);
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', basename($file['name']));
    $storedName = 'sample_' . $safeName;
    $targetPath = $uploadDir . '/' . $storedName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        set_flash('error', 'Unable to store uploaded file.');
        redirect('/contractor/templates.php?tab=request');
    }

    $now = now_kolkata()->format(DateTime::ATOM);
    $request = [
        'requestId' => $requestId,
        'yojId' => $yojId,
        'title' => $title,
        'notes' => $notes,
        'makeGlobalSuggestion' => $makeGlobal,
        'status' => 'pending',
        'files' => [
            [
                'name' => $file['name'],
                'storedName' => $storedName,
                'size' => $file['size'] ?? 0,
                'uploadedAt' => $now,
            ],
        ],
        'createdAt' => $now,
        'updatedAt' => $now,
    ];

    save_template_request($request);

    logEvent(DATA_PATH . '/logs/template_requests.log', [
        'event' => 'request_created',
        'yojId' => $yojId,
        'requestId' => $requestId,
        'title' => $title,
    ]);

    set_flash('success', 'Template request submitted.');
    redirect('/contractor/templates.php?tab=request');
});
