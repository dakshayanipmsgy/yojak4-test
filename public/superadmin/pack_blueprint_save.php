<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/pack_blueprints.php');
    }

    require_csrf();
    require_superadmin_or_permission('pack_blueprints_manage');
    $id = trim((string)($_POST['id'] ?? ''));

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
        $target = $id !== '' ? '/superadmin/pack_blueprint_edit.php?id=' . urlencode($id) : '/superadmin/pack_blueprint_edit.php';
        redirect($target);
    }

    if ($id === '') {
        $id = pack_blueprint_generate_id();
    }

    $blueprint = [
        'id' => $id,
        'scope' => 'global',
        'owner' => ['yojId' => 'YOJAK'],
        'title' => $payload['title'],
        'description' => $payload['description'],
        'items' => $payload['items'],
        'printStructure' => $payload['printStructure'],
        'published' => true,
        'archived' => false,
    ];

    $existing = pack_blueprint_load('global', $id);
    if ($existing) {
        $blueprint['createdAt'] = $existing['createdAt'] ?? null;
        $blueprint['published'] = $existing['published'] ?? true;
    }

    pack_blueprint_save($blueprint);
    logEvent(DATA_PATH . '/logs/pack_blueprints.log', [
        'event' => $existing ? 'pack_blueprint_updated' : 'pack_blueprint_created',
        'scope' => 'global',
        'blueprintId' => $id,
        'title' => $blueprint['title'],
    ]);

    set_flash('success', 'Pack blueprint saved.');
    redirect('/superadmin/pack_blueprint_edit.php?id=' . urlencode($id));
});
