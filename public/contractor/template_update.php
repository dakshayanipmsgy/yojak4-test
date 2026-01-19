<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/templates.php?tab=mine');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $templateId = trim((string)($_POST['id'] ?? ''));

    $existing = load_template_record_by_scope('contractor', $templateId, $yojId);
    if (!$existing || (($existing['owner']['yojId'] ?? '') !== $yojId)) {
        render_error_page('Template not found.');
        return;
    }

    $contractor = load_contractor($yojId) ?? [];
    $payload = [
        'title' => $_POST['title'] ?? '',
        'category' => $_POST['category'] ?? 'Other',
        'description' => $_POST['description'] ?? '',
        'bodyHtml' => $_POST['bodyHtml'] ?? '',
    ];

    $validation = template_validate_payload($payload, $contractor, null, true);
    if ($validation['errors']) {
        set_flash('error', implode(' ', $validation['errors']));
        redirect('/contractor/template_edit.php?id=' . urlencode($templateId));
    }

    $record = array_merge($existing, $validation['template']);
    $record['templateId'] = $templateId;
    save_template_record('contractor', $record, $yojId);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_updated',
        'yojId' => $yojId,
        'templateId' => $templateId,
    ]);

    set_flash('success', 'Template updated.');
    redirect('/contractor/templates.php?tab=mine');
});
