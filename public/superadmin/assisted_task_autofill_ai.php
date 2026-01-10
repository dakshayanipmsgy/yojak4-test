<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/assisted_tasks.php');
    }

    require_csrf();
    $actor = assisted_tasks_require_staff();
    $actorLabel = assisted_tasks_actor_label($actor);

    $taskId = trim($_POST['taskId'] ?? '');
    $task = $taskId !== '' ? assisted_tasks_load_task($taskId) : null;
    if (!$task) {
        render_error_page('Assisted task not found.');
        return;
    }

    $tender = load_offline_tender($task['yojId'] ?? '', $task['offtdId'] ?? '');
    if (!$tender) {
        render_error_page('Tender not found for AI auto-fill.');
        return;
    }

    $configResult = ai_get_config();
    if (empty($configResult['ok'])) {
        set_flash('error', 'AI is not configured. Please configure AI Studio before auto-fill.');
        redirect('/superadmin/assisted_task_edit.php?taskId=' . urlencode($taskId));
        return;
    }

    $systemPrompt = "You are an extraction assistant for offline tenders. Return ONLY valid JSON with the following keys:\n"
        . "tenderTitle, tenderNumber, issuingAuthority, departmentName, location, submissionDeadline, openingDate, preBidDate, completionMonths, bidValidityDays,\n"
        . "fees {tenderFeeText, emdText, sdText, pgText}, eligibilityDocs[], annexures[], formats[], restrictedAnnexures[], checklist[], notes[].\n"
        . "Checklist items must be objects: {title, category, required, notes, snippet}. "
        . "Do NOT include pricing/BOQ/SOR/rate details. If unsure, leave value null or empty string.";
    $existingForm = $task['extractForm'] ?? assisted_tasks_default_form();
    $existingSnapshot = json_encode($existingForm, JSON_UNESCAPED_SLASHES);
    $sourceText = offline_tender_extract_text($tender['sourceFiles'] ?? []);
    $userPrompt = "Tender text:\n" . $sourceText . "\n\nExisting extracted form:\n" . $existingSnapshot . "\n\n"
        . "Fill missing fields or improve entries. Output JSON only.";

    $aiResult = ai_call_text('assisted_extract_v2', $systemPrompt, $userPrompt, [
        'expectJson' => true,
        'runMode' => 'lenient',
        'maxTokens' => 1200,
    ]);

    $nowIso = now_kolkata()->format(DateTime::ATOM);
    $aiConfig = load_ai_config();
    $task['aiAssist'] = [
        'lastRunAt' => $nowIso,
        'provider' => $aiConfig['provider'] ?? null,
        'model' => $aiResult['modelUsed'] ?? ($aiConfig['textModel'] ?? null),
        'requestId' => $aiResult['requestId'] ?? null,
        'status' => $aiResult['parsedOk'] ? 'ok' : 'failed',
        'error' => $aiResult['parsedOk'] ? null : implode('; ', $aiResult['errors'] ?? []),
    ];

    if (!empty($aiResult['json']) && is_array($aiResult['json'])) {
        $incomingForm = assisted_tasks_normalize_ai_payload($aiResult['json']);
        $task['extractForm'] = assisted_tasks_merge_form($existingForm, $incomingForm);
        assisted_tasks_append_history($task, $actorLabel, 'autofilled');
        assisted_tasks_save_task($task);
        set_flash('success', 'AI auto-fill completed. Review the fields before delivery.');
    } else {
        assisted_tasks_append_history($task, $actorLabel, 'autofill_failed');
        assisted_tasks_save_task($task);
        set_flash('error', 'AI response could not be parsed. Continue manually.');
    }

    ai_log([
        'event' => 'ASSISTED_AUTOFILL',
        'at' => $nowIso,
        'provider' => $task['aiAssist']['provider'] ?? '',
        'model' => $task['aiAssist']['model'] ?? '',
        'requestId' => $task['aiAssist']['requestId'] ?? null,
        'parsed' => (bool)($aiResult['parsedOk'] ?? false),
        'errorsCount' => count($aiResult['errors'] ?? []),
        'taskId' => $taskId,
    ]);

    assisted_tasks_log([
        'event' => 'task_autofill_ai',
        'taskId' => $taskId,
        'actor' => $actorLabel,
        'status' => $task['aiAssist']['status'] ?? 'failed',
    ]);

    redirect('/superadmin/assisted_task_edit.php?taskId=' . urlencode($taskId));
});
