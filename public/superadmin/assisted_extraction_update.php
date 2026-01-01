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
    $action = $_POST['action'] ?? 'validate';
    $input = (string)($_POST['assistantDraft'] ?? '');
    $autoFixSnippets = isset($_POST['auto_fix_snippets']);

    $request = $reqId !== '' ? assisted_load_request($reqId) : null;
    if (!$request) {
        render_error_page('Request not found.');
        return;
    }

    $sanitizeResult = sanitize_ai_json_input($input, $autoFixSnippets);

    logEvent(ASSISTED_EXTRACTION_LOG, [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => 'ASSISTED_JSON_SANITIZE',
        'actor' => assisted_actor_label($actor),
        'reqId' => $reqId,
        'changed' => $sanitizeResult['changed'],
        'fixes' => $sanitizeResult['fixes'],
        'hash' => $sanitizeResult['hash'],
    ]);

    $sanitizedInput = $sanitizeResult['sanitized'];
    $decoded = json_decode($sanitizedInput, true);
    if (!is_array($decoded)) {
        $_SESSION['assisted_draft_input'][$reqId] = $sanitizedInput;
        $_SESSION['assisted_validation'][$reqId] = [
            'errors' => ['Invalid JSON. Please paste valid JSON only.'],
            'missingKeys' => [],
            'forbiddenFindings' => [],
            'jsonError' => json_last_error_msg(),
            'sanitizedHash' => $sanitizeResult['hash'] ?? null,
            'sanitizedPreview' => mb_substr($sanitizedInput, 0, 500),
            'snippetPreview' => $sanitizeResult['snippetPreview'] ?? null,
        ];
        $_SESSION['assisted_sanitized'][$reqId] = $sanitizedInput;
        logEvent(ASSISTED_EXTRACTION_LOG, [
            'at' => now_kolkata()->format(DateTime::ATOM),
            'event' => 'ASSISTED_JSON_PARSE_FAIL',
            'actor' => assisted_actor_label($actor),
            'reqId' => $reqId,
            'errorMsg' => json_last_error_msg(),
            'hash' => $sanitizeResult['hash'] ?? hash('sha256', $sanitizedInput),
            'sanitizedPreview' => mb_substr($sanitizedInput, 0, 500),
        ]);
        logEvent(ASSISTED_EXTRACTION_LOG, [
            'at' => now_kolkata()->format(DateTime::ATOM),
            'event' => 'ASSISTED_PASTE_VALIDATE',
            'ok' => false,
            'reqId' => $reqId,
            'actor' => assisted_actor_label($actor),
            'jsonError' => json_last_error_msg(),
            'rawSnippet' => mb_substr($sanitizedInput, 0, 500),
        ]);
        set_flash('error', 'Invalid JSON (possibly unescaped newline in snippets). Click Copy Sanitized Input to review.');
        redirect('/superadmin/assisted_extraction_view.php?reqId=' . urlencode($reqId));
        return;
    }

    $validation = assisted_validate_payload($decoded);
    $errors = $validation['errors'] ?? [];
    $nonBlockingFindings = array_values(array_filter($validation['allFindings'] ?? [], static function ($finding) {
        return ($finding['blocked'] ?? true) === false;
    }));
    if ($errors) {
        $_SESSION['assisted_draft_input'][$reqId] = $sanitizedInput;
        $_SESSION['assisted_validation'][$reqId] = $validation;
        $_SESSION['assisted_sanitized'][$reqId] = $sanitizedInput;
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
                'restrictedAnnexuresCount' => $validation['restrictedAnnexuresCount'] ?? 0,
                'nonBlockingFindings' => $nonBlockingFindings,
            ]);
        } else {
            logEvent(ASSISTED_EXTRACTION_LOG, [
                'at' => now_kolkata()->format(DateTime::ATOM),
                'event' => 'ASSISTED_PASTE_VALIDATE',
                'ok' => false,
                'reqId' => $reqId,
                'actor' => assisted_actor_label($actor),
                'missingKeys' => $validation['missingKeys'] ?? [],
                'restrictedAnnexuresCount' => $validation['restrictedAnnexuresCount'] ?? 0,
                'nonBlockingFindings' => $nonBlockingFindings,
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
    $_SESSION['assisted_sanitized'][$reqId] = $sanitizedInput;
    $_SESSION['assisted_preview'][$reqId] = [
        'tenderTitle' => $decoded['tender']['tenderTitle'] ?? null,
        'tenderNumber' => $decoded['tender']['tenderNumber'] ?? null,
        'checklistCount' => count($decoded['checklist'] ?? []),
        'templateCount' => count($decoded['templates'] ?? []),
        'snippetCount' => count($decoded['snippets'] ?? []),
        'fixes' => $sanitizeResult['fixes'],
        'hash' => $sanitizeResult['hash'],
    ];
    logEvent(ASSISTED_EXTRACTION_LOG, [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => 'ASSISTED_PASTE_VALIDATE',
        'ok' => true,
        'reqId' => $reqId,
        'actor' => assisted_actor_label($actor),
        'missingKeys' => [],
        'forbiddenFindings' => [],
        'restrictedAnnexuresCount' => $validation['restrictedAnnexuresCount'] ?? 0,
        'nonBlockingFindings' => $nonBlockingFindings,
    ]);

    $normalized = $validation['normalized'] ?? assisted_normalize_payload($decoded);
    $request['assistantDraft'] = $normalized;
    assisted_assign_request($request, $actor);
    $request['assignedTo'] = $actor['id'] ?? ($request['assignedTo'] ?? null);

    if ($action === 'validate') {
        assisted_save_request($request);
        set_flash('success', 'Validated. Preview available below.');
        redirect('/superadmin/assisted_extraction_view.php?reqId=' . urlencode($reqId));
        return;
    }

    if ($action === 'deliver') {
        $yojId = $request['yojId'] ?? '';
        $offtdId = $request['offtdId'] ?? '';
        if ($yojId !== '' && $offtdId !== '') {
            ensure_offline_tender_env($yojId);
            $meta = assisted_persist_payload($yojId, $offtdId, $reqId, $normalized, $actor);
            assisted_link_payload_to_entities($yojId, $offtdId, $meta);
            $request['deliveredPayloadPath'] = $meta['payloadPath'] ?? null;
        }
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
            'restrictedCount' => count($normalized['lists']['restricted'] ?? []),
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
