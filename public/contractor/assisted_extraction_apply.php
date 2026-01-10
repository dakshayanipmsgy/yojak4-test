<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/offline_tenders.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_offline_tender_env($yojId);
    ensure_assisted_extraction_env();
    ensure_packs_env($yojId);

    $offtdId = trim($_POST['id'] ?? '');
    $taskId = trim($_POST['taskId'] ?? '');

    $tender = $offtdId !== '' ? load_offline_tender($yojId, $offtdId) : null;
    if (!$tender || ($tender['yojId'] ?? '') !== $yojId) {
        render_error_page('Tender not found.');
        return;
    }

    $task = $taskId !== '' ? assisted_load_task($taskId) : null;
    if (!$task || ($task['contractor']['yojId'] ?? '') !== $yojId || ($task['tender']['offtdId'] ?? '') !== $offtdId) {
        render_error_page('Assisted extraction task not found.');
        return;
    }

    if (($task['status'] ?? '') !== 'delivered') {
        set_flash('error', 'This assisted extraction is not delivered yet.');
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        return;
    }

    $snapshot = readJson(assisted_task_snapshot_path($taskId));
    $form = $snapshot['form'] ?? $task['form'] ?? null;
    if (!$form || !is_array($form)) {
        render_error_page('Delivered data is unavailable.');
        return;
    }

    $extracted = assisted_task_form_to_tender_extract($form);
    $checklist = assisted_task_form_to_checklist($form);

    $tender['extracted'] = $extracted;
    $tender['checklist'] = $checklist;
    $tender['status'] = 'assisted_applied';
    $tender['assistedExtraction'] = [
        'taskId' => $taskId,
        'appliedAt' => now_kolkata()->format(DateTime::ATOM),
        'deliveredAt' => $task['delivered']['deliveredAt'] ?? null,
    ];

    save_offline_tender($tender);

    $normalized = [
        'lists' => [
            'restricted' => $extracted['restrictedAnnexures'] ?? [],
        ],
    ];
    $contractor = load_contractor($yojId) ?? [];
    $pack = pack_upsert_offline_tender($tender, $normalized, $contractor);

    $task['history'][] = [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'by' => $yojId,
        'event' => 'APPLIED',
    ];
    $task['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    assisted_save_task($task);

    assisted_task_log([
        'event' => 'CONTRACTOR_APPLIED',
        'taskId' => $taskId,
        'yojId' => $yojId,
        'offtdId' => $offtdId,
        'packId' => $pack['packId'] ?? null,
    ]);
    assisted_task_log([
        'event' => 'PACK_GENERATED',
        'taskId' => $taskId,
        'yojId' => $yojId,
        'offtdId' => $offtdId,
        'packId' => $pack['packId'] ?? null,
    ]);

    set_flash('success', 'Assisted extraction applied. Your pack and annexures are ready to print.');
    redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
});
