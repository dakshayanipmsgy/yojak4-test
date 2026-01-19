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

    $title = trim((string)($_POST['title'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'Tender'));
    $description = trim((string)($_POST['description'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $fieldCatalogRaw = (string)($_POST['field_catalog'] ?? '[]');
    $fieldCatalog = json_decode($fieldCatalogRaw, true);
    if (!is_array($fieldCatalog)) {
        $fieldCatalog = [];
    }

    if ($title === '' || $body === '') {
        set_flash('error', 'Title and body are required.');
        redirect('/contractor/template_new.php');
    }

    $template = [
        'templateId' => generate_template_library_id(),
        'scope' => 'contractor',
        'owner' => ['yojId' => $yojId],
        'title' => $title,
        'category' => $category,
        'description' => $description,
        'editorType' => 'simple_html',
        'body' => $body,
        'fieldCatalog' => $fieldCatalog,
        'rules' => [
            'allowManualEditBeforePrint' => true,
            'lockAfterGenerate' => false,
        ],
        'status' => 'active',
    ];

    if (template_library_payload_has_forbidden_terms($template)) {
        set_flash('error', 'Template contains restricted bid/rate fields. Please remove pricing references.');
        redirect('/contractor/template_new.php');
    }

    $saved = save_template_library_record($template, 'contractor', $yojId);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_created',
        'templateId' => $saved['templateId'] ?? '',
        'scope' => 'contractor',
        'yojId' => $yojId,
        'ip' => mask_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    ]);

    set_flash('success', 'Template created successfully.');
    redirect('/contractor/templates.php');
});
