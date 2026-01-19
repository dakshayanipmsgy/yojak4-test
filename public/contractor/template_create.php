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
        redirect('/contractor/template_new.php');
    }

    $templateId = generate_template_id('contractor', $yojId);
    $record = array_merge($validation['template'], [
        'templateId' => $templateId,
        'scope' => 'contractor',
        'owner' => ['yojId' => $yojId],
        'visibility' => ['contractorEditable' => true],
    ]);
    save_template_record('contractor', $record, $yojId);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_created',
        'yojId' => $yojId,
        'templateId' => $templateId,
    ]);

    set_flash('success', 'Template created.');
    redirect('/contractor/templates.php?tab=mine');
});
