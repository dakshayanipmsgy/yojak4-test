<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/tender_pack_blueprints.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $payload = [
        'title' => trim((string)($_POST['title'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'items' => [
            'checklist' => pack_blueprint_parse_checklist((string)($_POST['checklist'] ?? '')),
            'requiredFieldKeys' => array_values(array_filter(array_map('trim', explode(',', (string)($_POST['requiredFieldKeys'] ?? ''))))),
            'templates' => array_values(array_filter(array_map('trim', (array)($_POST['templates'] ?? [])))),
        ],
        'printStructure' => [
            'includeIndex' => !empty($_POST['print_include_index']),
            'includeChecklist' => !empty($_POST['print_include_checklist']),
            'includeTemplates' => !empty($_POST['print_include_templates']),
        ],
    ];
    $errors = pack_blueprint_validate($payload);
    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/contractor/pack_blueprint_new.php');
    }

    $id = pack_blueprint_generate_id();
    $blueprint = [
        'id' => $id,
        'scope' => 'contractor',
        'owner' => ['yojId' => $yojId],
        'title' => $payload['title'],
        'description' => $payload['description'],
        'items' => $payload['items'],
        'printStructure' => $payload['printStructure'],
        'published' => true,
        'archived' => false,
    ];

    pack_blueprint_save($blueprint, $yojId);
    logEvent(DATA_PATH . '/logs/pack_blueprints.log', [
        'event' => 'pack_blueprint_created',
        'scope' => 'contractor',
        'yojId' => $yojId,
        'blueprintId' => $id,
        'title' => $blueprint['title'],
    ]);

    set_flash('success', 'Pack blueprint saved.');
    redirect('/contractor/pack_blueprint_edit.php?id=' . urlencode($id));
});
