<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $title = trim((string)($_POST['title'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'General'));
    $description = trim((string)($_POST['description'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));

    if ($title === '' || $body === '') {
        set_flash('error', 'Title and body are required.');
        redirect('/contractor/template_new.php');
    }

    $errors = template_body_errors($body);
    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/contractor/template_new.php');
    }

    $tplId = generate_contractor_template_id_v2($yojId);
    $template = [
        'id' => $tplId,
        'tplId' => $tplId,
        'scope' => 'contractor',
        'owner' => ['yojId' => $yojId],
        'title' => $title,
        'name' => $title,
        'category' => $category,
        'description' => $description,
        'templateType' => 'simple_html',
        'body' => $body,
        'fieldRefs' => template_placeholder_tokens($body),
        'placeholders' => array_map(static fn($key) => '{{field:' . $key . '}}', template_placeholder_tokens($body)),
        'published' => true,
    ];

    save_contractor_template($yojId, $template);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_created',
        'scope' => 'contractor',
        'yojId' => $yojId,
        'tplId' => $tplId,
    ]);

    set_flash('success', 'Template created.');
    redirect('/contractor/template_edit.php?id=' . urlencode($tplId));
});
