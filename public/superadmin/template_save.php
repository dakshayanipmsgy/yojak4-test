<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/templates.php');
    }

    require_csrf();
    require_superadmin_or_permission('templates_manage');
    $id = trim((string)($_POST['id'] ?? ''));

    $payload = [
        'id' => $id,
        'title' => trim((string)($_POST['title'] ?? '')),
        'category' => trim((string)($_POST['category'] ?? 'Other')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'body' => trim((string)($_POST['body'] ?? '')),
    ];
    $errors = template_validate($payload, $id !== '');
    if ($errors) {
        set_flash('error', implode(' ', $errors));
        $target = $id !== '' ? '/superadmin/template_edit.php?id=' . urlencode($id) : '/superadmin/template_edit.php';
        redirect($target);
    }

    if ($id === '') {
        $id = template_generate_id();
    }

    $template = [
        'id' => $id,
        'scope' => 'global',
        'owner' => ['yojId' => 'YOJAK'],
        'title' => $payload['title'],
        'category' => $payload['category'],
        'description' => $payload['description'],
        'templateType' => 'simple_html',
        'body' => $payload['body'],
        'placeholdersUsed' => template_extract_placeholders($payload['body']),
        'published' => true,
        'archived' => false,
    ];

    $existing = template_load('global', $id);
    if ($existing) {
        $template['createdAt'] = $existing['createdAt'] ?? null;
        $template['published'] = $existing['published'] ?? true;
    }

    template_save($template);
    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => $existing ? 'template_updated' : 'template_created',
        'scope' => 'global',
        'templateId' => $id,
        'title' => $template['title'],
    ]);

    set_flash('success', 'Template saved.');
    redirect('/superadmin/template_edit.php?id=' . urlencode($id));
});
