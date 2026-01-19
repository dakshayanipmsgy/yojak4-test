<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $user = require_role('contractor');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        render_error_page('Invalid request.');
        return;
    }
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $yojId = $user['yojId'];
    $type = $_POST['type'] ?? 'template';
    if (!in_array($type, ['template', 'pack', 'both'], true)) {
        $type = 'template';
    }
    $notes = trim((string)($_POST['notes'] ?? ''));

    $requestId = generate_template_request_id();
    $requestDir = template_request_dir($requestId);
    $uploadDir = template_request_upload_dir($requestId);
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $uploadedFiles = [];
    $files = $_FILES['uploads'] ?? null;
    if ($files && is_array($files['name'] ?? null)) {
        foreach ($files['name'] as $index => $name) {
            $tmpName = $files['tmp_name'][$index] ?? '';
            $error = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;
            if ($error !== UPLOAD_ERR_OK || $tmpName === '') {
                continue;
            }
            $original = basename((string)$name);
            $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                continue;
            }
            $safeName = 'tender_' . strtolower(bin2hex(random_bytes(3))) . '.pdf';
            $dest = $uploadDir . '/' . $safeName;
            if (move_uploaded_file($tmpName, $dest)) {
                $uploadedFiles[] = $safeName;
            }
        }
    }

    $request = [
        'requestId' => $requestId,
        'yojId' => $yojId,
        'type' => $type,
        'notes' => $notes,
        'status' => 'pending',
        'deliverables' => [
            'templateIds' => [],
            'packTemplateIds' => [],
            'scope' => 'contractor',
        ],
    ];
    save_template_request($request);

    logEvent(TEMPLATE_REQUESTS_LOG, [
        'event' => 'template_request_created',
        'requestId' => $requestId,
        'yojId' => $yojId,
        'type' => $type,
        'uploadedCount' => count($uploadedFiles),
    ]);

    set_flash('success', 'Request submitted. Our staff will reach out.');
    redirect('/contractor/templates.php');
});
