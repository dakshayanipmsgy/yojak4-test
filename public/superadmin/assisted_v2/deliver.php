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
    $saveTemplate = !empty($_POST['save_template']);
    $request = $reqId !== '' ? assisted_v2_load_request($reqId) : null;
    if (!$request) {
        set_flash('error', 'Request not found.');
        redirect('/superadmin/assisted_v2/queue.php');
    }
    $payload = $request['draftPayload'] ?? null;
    if (!$payload) {
        set_flash('error', 'Validate JSON payload before delivery.');
        redirect('/superadmin/assisted_v2/process.php?reqId=' . urlencode($reqId));
    }

    $yojId = $request['contractor']['yojId'] ?? '';
    $offtdId = $request['source']['offtdId'] ?? '';
    $tender = ($yojId && $offtdId) ? load_offline_tender($yojId, $offtdId) : null;
    if (!$tender) {
        set_flash('error', 'Tender not found.');
        redirect('/superadmin/assisted_v2/process.php?reqId=' . urlencode($reqId));
    }
    $contractor = load_contractor($yojId) ?? [];

    $pack = assisted_v2_build_pack_from_payload($payload, $tender, $contractor, $request['result']['templateUsedId'] ?? null);

    $now = now_kolkata()->format(DateTime::ATOM);
    $request['status'] = 'delivered';
    $request['staff']['processedBy'] = assisted_v2_actor_label($actor);
    $request['staff']['processedAt'] = $now;
    $request['result']['packId'] = $pack['packId'] ?? null;
    $request['draftPayload'] = null;

    if ($saveTemplate) {
        $savedId = assisted_v2_create_template_from_payload($payload, assisted_v2_actor_label($actor));
        $request['result']['savedTemplateId'] = $savedId;
    }

    assisted_v2_append_audit($request, assisted_v2_actor_label($actor), 'DELIVERED');
    assisted_v2_save_request($request);

    assisted_v2_log_event([
        'event' => 'delivered',
        'reqId' => $reqId,
        'packId' => $pack['packId'] ?? null,
        'saveTemplate' => $saveTemplate,
        'actor' => assisted_v2_actor_label($actor),
    ]);

    set_flash('success', 'Assisted pack delivered to contractor.');
    redirect('/superadmin/assisted_v2/process.php?reqId=' . urlencode($reqId));
});
