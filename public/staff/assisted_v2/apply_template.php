<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/staff/assisted_v2/queue.php');
    }
    require_csrf();
    $actor = require_active_employee();
    if (!employee_has_permission($actor, 'can_process_assisted')) {
        redirect('/staff/dashboard.php');
    }

    $reqId = trim((string)($_POST['reqId'] ?? ''));
    $templateId = trim((string)($_POST['templateId'] ?? ''));
    $request = $reqId !== '' ? assisted_v2_load_request($reqId) : null;
    if (!$request) {
        set_flash('error', 'Request not found.');
        redirect('/staff/assisted_v2/queue.php');
    }
    $template = $templateId !== '' ? assisted_v2_load_template($templateId) : null;
    if (!$template) {
        set_flash('error', 'Template not found.');
        redirect('/staff/assisted_v2/process.php?reqId=' . urlencode($reqId));
    }

    $yojId = $request['contractor']['yojId'] ?? '';
    $offtdId = $request['source']['offtdId'] ?? '';
    $tender = ($yojId && $offtdId) ? load_offline_tender($yojId, $offtdId) : null;
    if (!$tender) {
        set_flash('error', 'Tender not found.');
        redirect('/staff/assisted_v2/process.php?reqId=' . urlencode($reqId));
    }

    $contractor = load_contractor($yojId) ?? [];
    $pack = assisted_v2_apply_template_to_pack($template, $tender, $contractor, $templateId);

    $now = now_kolkata()->format(DateTime::ATOM);
    $request['status'] = 'delivered';
    $request['staff']['processedBy'] = assisted_v2_actor_label($actor);
    $request['staff']['processedAt'] = $now;
    $request['result']['packId'] = $pack['packId'] ?? null;
    $request['result']['templateUsedId'] = $templateId;
    $request['draftPayload'] = null;
    assisted_v2_append_audit($request, assisted_v2_actor_label($actor), 'TEMPLATE_APPLIED');
    assisted_v2_save_request($request);

    assisted_v2_log_event([
        'event' => 'template_applied',
        'reqId' => $reqId,
        'packId' => $pack['packId'] ?? null,
        'templateId' => $templateId,
        'actor' => assisted_v2_actor_label($actor),
    ]);

    set_flash('success', 'Template applied and pack delivered.');
    redirect('/staff/assisted_v2/process.php?reqId=' . urlencode($reqId));
});
