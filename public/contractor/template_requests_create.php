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
    $type = ($_POST['type'] ?? 'template') === 'pack' ? 'pack' : 'template';

    $title = trim((string)($_POST['title'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    if ($title === '' || $notes === '') {
        set_flash('error', 'Title and notes are required.');
        redirect($type === 'pack' ? '/contractor/tender_pack_blueprints.php' : '/contractor/templates.php');
    }

    $reqId = request_generate_id();
    $now = now_kolkata()->format(DateTime::ATOM);
    $attachments = [];
    if (!empty($_FILES['attachment']['name'] ?? '')) {
        $uploadDir = request_uploads_base_dir() . '/' . $reqId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $filename = basename((string)$_FILES['attachment']['name']);
        $target = $uploadDir . '/' . $filename;
        if (is_uploaded_file($_FILES['attachment']['tmp_name'] ?? '') && move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
            $attachments[] = [
                'file' => 'uploads/' . $reqId . '/' . $filename,
                'name' => $filename,
            ];
        }
    }

    $request = [
        'id' => $reqId,
        'type' => $type,
        'from' => ['yojId' => $yojId],
        'status' => 'new',
        'title' => $title,
        'notes' => $notes,
        'attachments' => $attachments,
        'assignedTo' => null,
        'createdAt' => $now,
        'updatedAt' => $now,
    ];

    request_save($type, $request);
    logEvent(DATA_PATH . '/logs/requests.log', [
        'event' => 'request_created',
        'type' => $type,
        'yojId' => $yojId,
        'requestId' => $reqId,
    ]);

    set_flash('success', 'Request submitted.');
    redirect($type === 'pack' ? '/contractor/tender_pack_blueprints.php' : '/contractor/templates.php');
});
