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
    $reason = trim((string)($_POST['reason'] ?? ''));
    $request = $reqId !== '' ? assisted_v2_load_request($reqId) : null;
    if (!$request) {
        set_flash('error', 'Request not found.');
        redirect('/superadmin/assisted_v2/queue.php');
    }
    if ($reason === '') {
        set_flash('error', 'Reject reason is required.');
        redirect('/superadmin/assisted_v2/process.php?reqId=' . urlencode($reqId));
    }

    $now = now_kolkata()->format(DateTime::ATOM);
    $request['status'] = 'rejected';
    $request['reject']['reason'] = $reason;
    $request['staff']['processedBy'] = assisted_v2_actor_label($actor);
    $request['staff']['processedAt'] = $now;
    $request['draftPayload'] = null;
    assisted_v2_append_audit($request, assisted_v2_actor_label($actor), 'REJECTED');
    assisted_v2_save_request($request);

    assisted_v2_log_event([
        'event' => 'rejected',
        'reqId' => $reqId,
        'actor' => assisted_v2_actor_label($actor),
    ]);

    set_flash('success', 'Request rejected.');
    redirect('/superadmin/assisted_v2/process.php?reqId=' . urlencode($reqId));
});
