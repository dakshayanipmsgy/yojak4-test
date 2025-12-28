<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/assisted_extraction_queue.php');
    }

    require_csrf();
    $actor = assisted_staff_actor();
    $reqId = trim($_POST['reqId'] ?? '');
    $action = $_POST['action'] ?? 'save';
    $input = (string)($_POST['assistantDraft'] ?? '');

    $request = $reqId !== '' ? assisted_load_request($reqId) : null;
    if (!$request) {
        render_error_page('Request not found.');
        return;
    }

    $decoded = json_decode($input, true);
    if (!is_array($decoded)) {
        $_SESSION['assisted_draft_input'][$reqId] = $input;
        set_flash('error', 'Please provide valid JSON for the draft.');
        redirect('/superadmin/assisted_extraction_view.php?reqId=' . urlencode($reqId));
        return;
    }

    $errors = assisted_validate_payload($decoded);
    if ($errors) {
        $_SESSION['assisted_draft_input'][$reqId] = $input;
        set_flash('error', implode(' ', $errors));
        redirect('/superadmin/assisted_extraction_view.php?reqId=' . urlencode($reqId));
        return;
    }

    $normalized = assisted_normalize_payload($decoded);
    $request['assistantDraft'] = $normalized;
    assisted_assign_request($request, $actor);
    $request['assignedTo'] = $actor['id'] ?? ($request['assignedTo'] ?? null);

    if ($action === 'deliver') {
        $request['status'] = 'delivered';
        $request['deliveredAt'] = now_kolkata()->format(DateTime::ATOM);
        assisted_append_audit($request, assisted_actor_label($actor), 'delivered', null);
        assisted_deliver_notification($request);
        logEvent(ASSISTED_EXTRACTION_LOG, [
            'event' => 'delivered',
            'reqId' => $reqId,
            'yojId' => $request['yojId'] ?? '',
            'offtdId' => $request['offtdId'] ?? '',
            'actor' => assisted_actor_label($actor),
        ]);
        set_flash('success', 'Checklist delivered to contractor.');
    } else {
        $request['status'] = 'in_progress';
        assisted_append_audit($request, assisted_actor_label($actor), 'draft_saved', null);
        logEvent(ASSISTED_EXTRACTION_LOG, [
            'event' => 'draft_saved',
            'reqId' => $reqId,
            'yojId' => $request['yojId'] ?? '',
            'offtdId' => $request['offtdId'] ?? '',
            'actor' => assisted_actor_label($actor),
        ]);
        set_flash('success', 'Draft saved.');
    }

    assisted_save_request($request);
    redirect('/superadmin/assisted_extraction_view.php?reqId=' . urlencode($reqId));
});
