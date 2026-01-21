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

    $stats = [];
    $body = migrate_placeholders_to_canonical($body, $stats);
    $errors = template_body_errors($body);
    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/contractor/template_edit.php?id=' . urlencode($tplId));
    }

    $contractor = load_contractor($yojId) ?? [];
    $memory = load_profile_memory($yojId);
    $registry = placeholder_registry([
        'contractor' => $contractor,
        'memory' => $memory,
    ]);
    $validation = validate_placeholders($body, $registry);
    if (!empty($validation['invalidTokens'])) {
        set_flash('error', 'Invalid placeholders: ' . implode(', ', $validation['invalidTokens']));
        redirect('/contractor/template_edit.php?id=' . urlencode($tplId));
    }
    if (!empty($validation['unknownKeys'])) {
        set_flash('warning', 'Unknown fields: ' . implode(', ', $validation['unknownKeys']) . '. Save them as custom fields if needed.');
    }

    $template['title'] = $title;
    $template['name'] = $title;
    $template['category'] = $category;
    $template['description'] = $description;
    $template['body'] = $body;
    $tokens = template_placeholder_tokens($body);
    $template['fieldRefs'] = array_values(array_filter($tokens, static fn($key) => !str_starts_with($key, 'table:')));
    $template['placeholders'] = array_map(static function ($key) {
        if (str_starts_with($key, 'table:')) {
            return '{{field:table:' . substr($key, 6) . '}}';
        }
        return '{{field:' . $key . '}}';
    }, $tokens);

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
