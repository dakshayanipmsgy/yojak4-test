<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/templates.php');
    }

    require_csrf();
    require_superadmin_or_permission('templates_manage');
    $json = trim((string)($_POST['json'] ?? ''));
    $existingId = trim((string)($_POST['id'] ?? ''));
    if ($json === '') {
        set_flash('error', 'JSON payload is required.');
        redirect($existingId !== '' ? '/superadmin/template_edit.php?id=' . urlencode($existingId) : '/superadmin/template_edit.php');
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        set_flash('error', 'Invalid JSON.');
        redirect($existingId !== '' ? '/superadmin/template_edit.php?id=' . urlencode($existingId) : '/superadmin/template_edit.php');
    }

    if ($existingId !== '' && ($data['id'] ?? '') !== $existingId) {
        set_flash('error', 'JSON id does not match the template you are editing.');
        redirect('/superadmin/template_edit.php?id=' . urlencode($existingId));
    }

    $payload = [
        'id' => $data['id'] ?? '',
        'title' => trim((string)($data['title'] ?? '')),
        'category' => trim((string)($data['category'] ?? 'Other')),
        'description' => trim((string)($data['description'] ?? '')),
        'body' => trim((string)($data['body'] ?? '')),
    ];
    $errors = template_validate($payload, true);
    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect($existingId !== '' ? '/superadmin/template_edit.php?id=' . urlencode($existingId) : '/superadmin/template_edit.php');
    }

    $id = $payload['id'] ?: template_generate_id();
    $template = template_normalize_record(array_merge($data, [
        'id' => $id,
        'scope' => 'global',
        'owner' => ['yojId' => 'YOJAK'],
        'templateType' => 'simple_html',
    ]), 'global');
    $template['placeholdersUsed'] = template_extract_placeholders((string)($template['body'] ?? ''));

    template_save($template);
    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_import_json',
        'scope' => 'global',
        'templateId' => $id,
        'title' => $template['title'],
    ]);

    set_flash('success', 'Template JSON applied.');
    redirect('/superadmin/template_edit.php?id=' . urlencode($id));
});
