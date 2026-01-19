<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/packs_blueprints.php?tab=mine');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $sections = [];
    $checklistItems = packtpl_parse_checklist_lines((string)($_POST['checklist_items'] ?? ''));
    if ($checklistItems) {
        $sections[] = [
            'sectionId' => 'checklist',
            'label' => 'Checklist',
            'items' => $checklistItems,
        ];
    }

    $templateIds = array_values(array_filter(array_map('strval', $_POST['template_ids'] ?? [])));
    if ($templateIds) {
        $sections[] = [
            'sectionId' => 'templates',
            'label' => 'Templates',
            'templateIds' => $templateIds,
        ];
    }

    $attachmentTags = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['attachment_tags'] ?? '')))));
    if ($attachmentTags) {
        $sections[] = [
            'sectionId' => 'attachments',
            'label' => 'Attachments',
            'allowedTags' => $attachmentTags,
        ];
    }

    $customLabel = trim((string)($_POST['custom_label'] ?? ''));
    $customItemsRaw = trim((string)($_POST['custom_items'] ?? ''));
    if ($customLabel !== '' || $customItemsRaw !== '') {
        $items = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $customItemsRaw))));
        $sections[] = [
            'sectionId' => 'custom',
            'label' => $customLabel !== '' ? $customLabel : 'Custom',
            'items' => $items,
        ];
    }

    $payload = [
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'sections' => $sections,
    ];

    $validation = packtpl_validate_payload($payload);
    if ($validation['errors']) {
        set_flash('error', implode(' ', $validation['errors']));
        redirect('/contractor/packtpl_new.php');
    }

    $packTplId = generate_packtpl_id('contractor', $yojId);
    $record = array_merge($validation['packTpl'], [
        'packTplId' => $packTplId,
        'scope' => 'contractor',
        'owner' => ['yojId' => $yojId],
    ]);
    save_packtpl_record('contractor', $record, $yojId);

    logEvent(DATA_PATH . '/logs/packs_blueprints.log', [
        'event' => 'packtpl_created',
        'yojId' => $yojId,
        'packTplId' => $packTplId,
    ]);

    set_flash('success', 'Pack preset created.');
    redirect('/contractor/packs_blueprints.php?tab=mine');
});
