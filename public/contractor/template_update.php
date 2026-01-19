<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $tplId = trim((string)($_POST['tplId'] ?? ''));
    if ($tplId === '') {
        render_error_page('Template not found.');
        return;
    }

    $template = load_contractor_template($yojId, $tplId);
    if (!$template) {
        render_error_page('Template not found.');
        return;
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'General'));
    $description = trim((string)($_POST['description'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));

    if ($title === '' || $body === '') {
        set_flash('error', 'Title and body are required.');
        redirect('/contractor/template_edit.php?id=' . urlencode($tplId));
    }

    $errors = template_body_errors($body);
    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/contractor/template_edit.php?id=' . urlencode($tplId));
    }

    $template['title'] = $title;
    $template['name'] = $title;
    $template['category'] = $category;
    $template['description'] = $description;
    $template['body'] = $body;
    $template['fieldRefs'] = template_placeholder_tokens($body);
    $template['placeholders'] = array_map(static fn($key) => '{{field:' . $key . '}}', template_placeholder_tokens($body));

    save_contractor_template($yojId, $template);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_updated',
        'scope' => 'contractor',
        'yojId' => $yojId,
        'tplId' => $tplId,
    ]);

    set_flash('success', 'Template updated.');
    redirect('/contractor/template_edit.php?id=' . urlencode($tplId));
});
