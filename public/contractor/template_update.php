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
    $tplId = trim((string)($_POST['id'] ?? ''));
    if ($tplId === '') {
        render_error_page('Missing template id.');
        return;
    }
    $existing = template_load('contractor', $tplId, $yojId);
    if (!$existing) {
        render_error_page('Template not found.');
        return;
    }

    $payload = [
        'id' => $tplId,
        'title' => trim((string)($_POST['title'] ?? '')),
        'category' => trim((string)($_POST['category'] ?? 'Other')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'body' => trim((string)($_POST['body'] ?? '')),
    ];
    $errors = template_validate($payload, true);
    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/contractor/template_edit.php?id=' . urlencode($tplId));
    }

    $template = array_merge($existing, [
        'title' => $payload['title'],
        'category' => $payload['category'],
        'description' => $payload['description'],
        'body' => $payload['body'],
        'placeholdersUsed' => template_extract_placeholders($payload['body']),
    ]);

    template_save($template, $yojId);
    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_updated',
        'scope' => 'contractor',
        'yojId' => $yojId,
        'templateId' => $tplId,
        'title' => $template['title'],
    ]);

    set_flash('success', 'Template updated.');
    redirect('/contractor/template_edit.php?id=' . urlencode($tplId));
});
