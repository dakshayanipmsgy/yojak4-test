<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/assisted_v2/queue.php');
    }
    require_csrf();
    $actor = require_role('superadmin');

    $reqId = trim((string)($_POST['reqId'] ?? ''));
    $payloadInput = (string)($_POST['payload'] ?? '');
    $request = $reqId !== '' ? assisted_v2_load_request($reqId) : null;
    if (!$request) {
        set_flash('error', 'Request not found.');
        redirect('/superadmin/assisted_v2/queue.php');
    }

    $validation = assisted_v2_parse_json_payload($payloadInput);
    if (!$validation['ok']) {
        assisted_v2_log_event([
            'event' => 'paste_validated',
            'reqId' => $reqId,
            'status' => 'invalid',
            'errors' => $validation['errors'],
            'actor' => assisted_v2_actor_label($actor),
        ]);
        set_flash('error', implode(' ', $validation['errors']));
        redirect('/superadmin/assisted_v2/process.php?reqId=' . urlencode($reqId));
    }

    $normalized = $validation['normalized'];
    $request['draftPayload'] = $normalized;
    assisted_v2_assign_request($request, $actor);
    assisted_v2_append_audit($request, assisted_v2_actor_label($actor), 'PASTE_VALIDATED');
    assisted_v2_save_request($request);

    assisted_v2_log_event([
        'event' => 'paste_validated',
        'reqId' => $reqId,
        'status' => 'ok',
        'counts' => assisted_v2_payload_summary($normalized),
        'actor' => assisted_v2_actor_label($actor),
    ]);

    set_flash('success', 'Payload validated. Preview updated.');
    redirect('/superadmin/assisted_v2/process.php?reqId=' . urlencode($reqId));
});
