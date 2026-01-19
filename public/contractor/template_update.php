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
    $templateId = trim((string)($_POST['templateId'] ?? ''));
    if ($templateId === '') {
        render_error_page('Template ID missing.');
        return;
    }

    $existing = load_template_library_record('contractor', $yojId, $templateId);
    if (!$existing) {
        render_error_page('Template not found.');
        return;
    }

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
        redirect('/contractor/template_edit.php?templateId=' . urlencode($templateId));
    }

    $template = array_merge($existing, [
        'title' => $title,
        'category' => $category,
        'description' => $description,
        'body' => $body,
        'fieldCatalog' => $fieldCatalog,
    ]);

    if (template_library_payload_has_forbidden_terms($template)) {
        set_flash('error', 'Template contains restricted bid/rate fields. Please remove pricing references.');
        redirect('/contractor/template_edit.php?templateId=' . urlencode($templateId));
    }

    save_template_library_record($template, 'contractor', $yojId);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_updated',
        'templateId' => $templateId,
        'scope' => 'contractor',
        'yojId' => $yojId,
        'ip' => mask_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    ]);

    set_flash('success', 'Template updated successfully.');
    redirect('/contractor/templates.php');
});
