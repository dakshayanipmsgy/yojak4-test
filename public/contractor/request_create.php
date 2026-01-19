<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $type = trim((string)($_POST['type'] ?? 'template'));
    $type = in_array($type, ['template', 'pack'], true) ? $type : 'template';
    $title = trim((string)($_POST['title'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $tenderId = trim((string)($_POST['tender_id'] ?? ''));
    $tenderTitle = trim((string)($_POST['tender_title'] ?? ''));

    if ($title === '') {
        set_flash('error', 'Title is required.');
        redirect('/contractor/request_new.php?type=' . urlencode($type));
    }

    $requestId = generate_request_id();
    $upload = request_handle_upload($requestId, $_FILES['tender_pdf'] ?? []);
    if (!$upload) {
        set_flash('error', 'Please upload a valid tender PDF.');
        redirect('/contractor/request_new.php?type=' . urlencode($type));
    }

    $request = [
        'id' => $requestId,
        'type' => $type,
        'yojId' => $yojId,
        'title' => $title,
        'notes' => $notes,
        'tenderRef' => [
            'offtdId' => $tenderId,
            'tenderTitle' => $tenderTitle,
        ],
        'uploads' => [$upload],
        'status' => 'new',
        'delivered' => null,
    ];

    save_request($request);

    logEvent(DATA_PATH . '/logs/requests.log', [
        'event' => 'request_created',
        'yojId' => $yojId,
        'requestId' => $requestId,
        'type' => $type,
    ]);

    set_flash('success', 'Request submitted.');
    $redirect = $type === 'pack' ? '/contractor/packs_library.php?tab=requests' : '/contractor/templates.php?tab=requests';
    redirect($redirect);
});
