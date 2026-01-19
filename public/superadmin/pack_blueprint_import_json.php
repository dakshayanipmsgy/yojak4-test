<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/pack_blueprints.php');
    }

    require_csrf();
    require_superadmin_or_permission('pack_blueprints_manage');
    $json = trim((string)($_POST['json'] ?? ''));
    $existingId = trim((string)($_POST['id'] ?? ''));
    if ($json === '') {
        set_flash('error', 'JSON payload is required.');
        redirect($existingId !== '' ? '/superadmin/pack_blueprint_edit.php?id=' . urlencode($existingId) : '/superadmin/pack_blueprint_edit.php');
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        set_flash('error', 'Invalid JSON.');
        redirect($existingId !== '' ? '/superadmin/pack_blueprint_edit.php?id=' . urlencode($existingId) : '/superadmin/pack_blueprint_edit.php');
    }

    if ($existingId !== '' && ($data['id'] ?? '') !== $existingId) {
        set_flash('error', 'JSON id does not match the blueprint you are editing.');
        redirect('/superadmin/pack_blueprint_edit.php?id=' . urlencode($existingId));
    }

    $payload = [
        'title' => trim((string)($data['title'] ?? '')),
        'items' => $data['items'] ?? [],
    ];
    $errors = pack_blueprint_validate($payload);
    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect($existingId !== '' ? '/superadmin/pack_blueprint_edit.php?id=' . urlencode($existingId) : '/superadmin/pack_blueprint_edit.php');
    }

    $id = $data['id'] ?? '';
    if ($id === '') {
        $id = pack_blueprint_generate_id();
    }

    $blueprint = pack_blueprint_normalize(array_merge($data, [
        'id' => $id,
        'scope' => 'global',
        'owner' => ['yojId' => 'YOJAK'],
    ]), 'global');

    pack_blueprint_save($blueprint);
    logEvent(DATA_PATH . '/logs/pack_blueprints.log', [
        'event' => 'pack_blueprint_import_json',
        'scope' => 'global',
        'blueprintId' => $id,
        'title' => $blueprint['title'],
    ]);

    set_flash('success', 'Pack blueprint JSON applied.');
    redirect('/superadmin/pack_blueprint_edit.php?id=' . urlencode($id));
});
