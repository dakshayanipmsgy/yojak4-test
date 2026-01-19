<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/templates.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $payload = [
        'title' => trim((string)($_POST['title'] ?? '')),
        'category' => trim((string)($_POST['category'] ?? 'Other')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'body' => trim((string)($_POST['body'] ?? '')),
    ];
    $errors = template_validate($payload);
    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/contractor/template_new.php');
    }

    $id = template_generate_id();
    $template = [
        'id' => $id,
        'scope' => 'contractor',
        'owner' => ['yojId' => $yojId],
        'title' => $payload['title'],
        'category' => $payload['category'],
        'description' => $payload['description'],
        'templateType' => 'simple_html',
        'body' => $payload['body'],
        'placeholdersUsed' => template_extract_placeholders($payload['body']),
        'published' => true,
        'archived' => false,
    ];

    template_save($template, $yojId);
    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_created',
        'scope' => 'contractor',
        'yojId' => $yojId,
        'templateId' => $id,
        'title' => $template['title'],
    ]);

    set_flash('success', 'Template saved.');
    redirect('/contractor/template_edit.php?id=' . urlencode($id));
});
