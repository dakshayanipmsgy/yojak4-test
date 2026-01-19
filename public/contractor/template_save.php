<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $user = require_role('contractor');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        render_error_page('Invalid request.');
        return;
    }
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $yojId = $user['yojId'];

    $templateId = trim((string)($_POST['templateId'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'tender'));
    $description = trim((string)($_POST['description'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $tablesJson = (string)($_POST['tables_json'] ?? '[]');

    if ($title === '' || strlen($title) < 3) {
        set_flash('error', 'Title must be at least 3 characters.');
        redirect('/contractor/template_edit.php' . ($templateId !== '' ? '?templateId=' . urlencode($templateId) : ''));
    }

    $tables = [];
    if ($tablesJson !== '') {
        $decoded = json_decode($tablesJson, true);
        if (is_array($decoded)) {
            foreach ($decoded as $table) {
                if (!is_array($table)) {
                    continue;
                }
                $tableId = trim((string)($table['tableId'] ?? ''));
                if ($tableId === '') {
                    continue;
                }
                $columns = [];
                foreach ((array)($table['columns'] ?? []) as $column) {
                    if (!is_array($column)) {
                        continue;
                    }
                    $key = trim((string)($column['key'] ?? ''));
                    if ($key === '') {
                        continue;
                    }
                    $columns[] = [
                        'key' => $key,
                        'label' => trim((string)($column['label'] ?? $key)),
                        'type' => $column['type'] ?? 'text',
                        'allowManual' => (bool)($column['allowManual'] ?? false),
                        'defaultBlank' => (bool)($column['defaultBlank'] ?? false),
                        'formula' => $column['formula'] ?? null,
                    ];
                }
                if ($columns) {
                    $tables[] = [
                        'tableId' => $tableId,
                        'title' => trim((string)($table['title'] ?? '')),
                        'columns' => $columns,
                    ];
                }
            }
        }
    }

    $existing = null;
    if ($templateId !== '') {
        $existing = load_contractor_template($yojId, $templateId);
    }

    $payload = [
        'templateId' => $templateId !== '' ? $templateId : generate_contractor_template_id($yojId),
        'scope' => 'contractor',
        'owner' => ['yojId' => $yojId],
        'title' => $title,
        'category' => $category,
        'description' => $description,
        'bodyType' => 'simple_html',
        'body' => $body,
        'tables' => $tables,
        'rules' => template_default_rules(),
        'createdAt' => $existing['createdAt'] ?? null,
        'status' => $existing['status'] ?? 'active',
    ];

    save_contractor_template($yojId, $payload);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => $existing ? 'template_updated' : 'template_created',
        'yojId' => $yojId,
        'templateId' => $payload['templateId'],
    ]);

    set_flash('success', 'Template saved.');
    redirect('/contractor/templates.php#my-templates');
});
