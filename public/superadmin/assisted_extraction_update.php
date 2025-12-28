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

    $sanitizedInput = assisted_sanitize_json_input($input);
    $decoded = json_decode($sanitizedInput, true);
    if (!is_array($decoded)) {
        $_SESSION['assisted_draft_input'][$reqId] = $sanitizedInput;
        $_SESSION['assisted_validation'][$reqId] = [
            'errors' => ['Invalid JSON. Please paste valid JSON only.'],
            'missingKeys' => [],
            'forbiddenFindings' => [],
            'jsonError' => json_last_error_msg(),
        ];
        logEvent(ASSISTED_EXTRACTION_LOG, [
            'at' => now_kolkata()->format(DateTime::ATOM),
            'event' => 'ASSISTED_PASTE_VALIDATE',
            'ok' => false,
            'reqId' => $reqId,
            'actor' => assisted_actor_label($actor),
            'jsonError' => json_last_error_msg(),
            'rawSnippet' => mb_substr($sanitizedInput, 0, 500),
        ]);
        set_flash('error', 'Invalid JSON. Please paste valid JSON only.');
        redirect('/superadmin/assisted_extraction_view.php?reqId=' . urlencode($reqId));
        return;
    }

    $validation = assisted_validate_payload($decoded);
    $errors = $validation['errors'] ?? [];
    if ($errors) {
        $_SESSION['assisted_draft_input'][$reqId] = $sanitizedInput;
        $_SESSION['assisted_validation'][$reqId] = $validation;
        $forbidden = $validation['forbiddenFindings'] ?? [];
        if (!empty($forbidden)) {
            $findingsToLog = [];
            foreach ($forbidden as $finding) {
                $findingsToLog[] = [
                    'path' => $finding['path'] ?? '',
                    'reasonCode' => $finding['reasonCode'] ?? '',
                ];
            }
            logEvent(ASSISTED_EXTRACTION_LOG, [
                'at' => now_kolkata()->format(DateTime::ATOM),
                'event' => 'ASSISTED_VALIDATE_BLOCK',
                'reqId' => $reqId,
                'actor' => assisted_actor_label($actor),
                'findings' => $findingsToLog,
            ]);
        } else {
            logEvent(ASSISTED_EXTRACTION_LOG, [
                'at' => now_kolkata()->format(DateTime::ATOM),
                'event' => 'ASSISTED_PASTE_VALIDATE',
                'ok' => false,
                'reqId' => $reqId,
                'actor' => assisted_actor_label($actor),
                'missingKeys' => $validation['missingKeys'] ?? [],
            ]);
        }
        set_flash('error', implode(' ', $errors));
        redirect('/superadmin/assisted_extraction_view.php?reqId=' . urlencode($reqId));
        return;
    }

    $_SESSION['assisted_validation'][$reqId] = [
        'errors' => [],
        'missingKeys' => [],
        'forbiddenFindings' => [],
    ];
    logEvent(ASSISTED_EXTRACTION_LOG, [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => 'ASSISTED_PASTE_VALIDATE',
        'ok' => true,
        'reqId' => $reqId,
        'actor' => assisted_actor_label($actor),
        'missingKeys' => [],
        'forbiddenFindings' => [],
    ]);

    $normalized = $validation['normalized'] ?? assisted_normalize_payload($decoded);
    $request['assistantDraft'] = $normalized;
    assisted_assign_request($request, $actor);
    $request['assignedTo'] = $actor['id'] ?? ($request['assignedTo'] ?? null);

    if ($action === 'deliver') {
        $request['status'] = 'delivered';
        $request['deliveredAt'] = now_kolkata()->format(DateTime::ATOM);
        assisted_append_audit($request, assisted_actor_label($actor), 'delivered', null);
        assisted_deliver_notification($request);
        logEvent(ASSISTED_EXTRACTION_LOG, [
            'at' => now_kolkata()->format(DateTime::ATOM),
            'event' => 'ASSISTED_DELIVER',
            'ok' => true,
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
            'at' => now_kolkata()->format(DateTime::ATOM),
            'event' => 'draft_saved',
            'ok' => true,
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
