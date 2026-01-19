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

    $global = load_global_template($tplId);
    if (!$global) {
        render_error_page('Template not found.');
        return;
    }

    $newId = generate_contractor_template_id_v2($yojId);
    $template = [
        'id' => $newId,
        'tplId' => $newId,
        'scope' => 'contractor',
        'owner' => ['yojId' => $yojId],
        'title' => $global['title'] ?? 'Template',
        'name' => $global['title'] ?? 'Template',
        'category' => $global['category'] ?? 'General',
        'description' => $global['description'] ?? '',
        'templateType' => $global['templateType'] ?? 'simple_html',
        'body' => $global['body'] ?? '',
        'fieldRefs' => $global['fieldRefs'] ?? template_placeholder_tokens((string)($global['body'] ?? '')),
        'placeholders' => array_map(static fn($key) => '{{field:' . $key . '}}', template_placeholder_tokens((string)($global['body'] ?? ''))),
        'published' => true,
    ];

    save_contractor_template($yojId, $template);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_copied_from_global',
        'scope' => 'contractor',
        'yojId' => $yojId,
        'tplId' => $newId,
        'sourceTplId' => $tplId,
    ]);

    set_flash('success', 'Template copied. You can now customize it.');
    redirect('/contractor/template_edit.php?id=' . urlencode($newId));
});
