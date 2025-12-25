<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/tender_archive.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_tender_archive_env($yojId);

    $archId = trim($_POST['id'] ?? '');
    $archive = $archId !== '' ? load_tender_archive($yojId, $archId) : null;
    if (!$archive || ($archive['yojId'] ?? '') !== $yojId) {
        render_error_page('Archive not found.');
        return;
    }

    $templateTitle = trim($_POST['templateTitle'] ?? '');
    if ($templateTitle === '') {
        $templateTitle = ($archive['title'] ?? 'Template') . ' Checklist';
    }

    $aiSummary = array_merge(tender_archive_ai_defaults(), $archive['aiSummary'] ?? []);
    $items = normalize_archive_checklist($aiSummary['suggestedChecklist'] ?? []);
    if (!$items) {
        set_flash('error', 'No checklist items to export. Add items or run AI summary first.');
        redirect('/contractor/tender_archive_view.php?id=' . urlencode($archId));
        return;
    }

    $templates = load_checklist_templates($yojId);
    $templateId = generate_template_id($yojId);
    $templates[] = [
        'templateId' => $templateId,
        'title' => $templateTitle,
        'createdAt' => now_kolkata()->format(DateTime::ATOM),
        'items' => $items,
    ];
    save_checklist_templates($yojId, $templates);

    tender_archive_log([
        'event' => 'template_exported',
        'yojId' => $yojId,
        'archId' => $archId,
        'templateId' => $templateId,
        'itemCount' => count($items),
    ]);

    set_flash('success', 'Checklist template saved for reuse.');
    redirect('/contractor/tender_archive_view.php?id=' . urlencode($archId));
});
