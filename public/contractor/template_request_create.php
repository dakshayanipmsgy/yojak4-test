<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/templates.php');
    }
    require_csrf();

    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $type = trim((string)($_POST['type'] ?? 'template'));
    if (!in_array($type, ['template', 'pack'], true)) {
        $type = 'template';
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $source = trim((string)($_POST['offlineTenderId'] ?? ''));
    if ($title === '') {
        set_flash('error', 'Please provide a title for your request.');
        redirect('/contractor/templates.php');
    }

    $request = [
        'requestId' => generate_template_request_id(),
        'yojId' => $yojId,
        'type' => $type,
        'title' => $title,
        'notes' => $notes,
        'category' => $category !== '' ? $category : null,
        'source' => $source !== '' ? ['offlineTenderId' => $source, 'optional' => true] : null,
        'status' => 'new',
        'assignedTo' => null,
        'result' => [
            'createdTemplateIds' => [],
            'createdPackTemplateIds' => [],
        ],
    ];

    $request = save_template_request($request);

    $uploadsDir = template_request_upload_dir($request['requestId']);
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0775, true);
    }

    $uploadedFiles = [];
    if (!empty($_FILES['attachments']['name'])) {
        $names = $_FILES['attachments']['name'];
        $tmpNames = $_FILES['attachments']['tmp_name'];
        $errors = $_FILES['attachments']['error'];
        foreach ($names as $index => $name) {
            if (($errors[$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename((string)$name));
            if ($safeName === '') {
                continue;
            }
            $target = $uploadsDir . '/' . $safeName;
            if (move_uploaded_file($tmpNames[$index], $target)) {
                $uploadedFiles[] = $safeName;
            }
        }
    }

    if ($uploadedFiles) {
        $request['attachments'] = $uploadedFiles;
        save_template_request($request);
    }

    logEvent(DATA_PATH . '/logs/template_requests.log', [
        'event' => 'request_created',
        'requestId' => $request['requestId'],
        'yojId' => $yojId,
        'type' => $type,
        'attachments' => $uploadedFiles,
        'ip' => mask_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    ]);

    set_flash('success', 'Request submitted. Our team will update you soon.');
    redirect('/contractor/template_requests.php');
});
