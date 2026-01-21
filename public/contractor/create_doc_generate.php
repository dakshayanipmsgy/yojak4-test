<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/create_docs.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $templateId = trim((string)($_POST['templateId'] ?? ''));
    $scope = trim((string)($_POST['scope'] ?? ''));
    if ($templateId === '') {
        set_flash('error', 'Template not selected.');
        redirect('/contractor/create_docs.php');
    }

    ensure_template_pack_library_env();
    ensure_contractor_templates_env($yojId);
    ensure_contractor_generated_docs_env($yojId);

    $template = create_docs_find_contractor_template($yojId, $templateId, $scope);
    if (!$template) {
        render_error_page('Template not found or access denied.');
        return;
    }

    $keys = create_docs_collect_template_keys($template);
    $fieldKeys = array_values(array_filter($keys, static fn($key) => !str_starts_with($key, 'table:')));
    $tableKeys = array_values(array_map(static fn($key) => substr($key, 6), array_filter($keys, static fn($key) => str_starts_with($key, 'table:'))));

    $contractor = load_contractor($yojId) ?? [];
    $memory = load_profile_memory($yojId);
    $values = create_docs_resolve_contractor_values($yojId, $contractor);

    $inputFields = $_POST['fields'] ?? [];
    if (!is_array($inputFields)) {
        $inputFields = [];
    }
    foreach ($fieldKeys as $key) {
        if (array_key_exists($key, $inputFields)) {
            $values[$key] = trim((string)$inputFields[$key]);
        }
    }

    $missing = array_values(array_filter($fieldKeys, static function ($key) use ($values) {
        return trim((string)($values[$key] ?? '')) === '';
    }));

    $registry = placeholder_registry([
        'contractor' => $contractor,
        'memory' => $memory,
    ]);
    $saveFuture = $_POST['save_future'] ?? [];
    if (is_array($saveFuture)) {
        $entries = [];
        foreach ($saveFuture as $key => $flag) {
            if (!isset($inputFields[$key])) {
                continue;
            }
            $value = trim((string)$inputFields[$key]);
            if ($value === '') {
                continue;
            }
            $meta = $registry['fields'][$key] ?? [];
            $entries[$key] = [
                'value' => $value,
                'label' => $meta['label'] ?? profile_memory_label_from_key((string)$key),
                'type' => $meta['type'] ?? 'text',
            ];
        }
        if ($entries) {
            profile_memory_upsert_entries($yojId, $entries, 'create_docs');
        }
    }

    $inputTables = $_POST['tables'] ?? [];
    if (!is_array($inputTables)) {
        $inputTables = [];
    }
    $tables = [];
    foreach ($tableKeys as $tableKey) {
        $rows = $inputTables[$tableKey] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }
        $columns = create_docs_table_columns($tableKey);
        $rows = create_docs_normalize_rows($rows, $columns);
        foreach ($rows as &$row) {
            create_docs_autofill_amount($row);
        }
        unset($row);
        $tables[$tableKey] = $rows;
    }

    [$body, $isHtml] = create_docs_template_body($template);
    $renderMissing = [];
    $renderedBody = create_docs_apply_placeholders($body, $isHtml, $values, $tables, $renderMissing);

    $paper = trim((string)($_POST['paper'] ?? 'A4')) ?: 'A4';
    $letterhead = isset($_POST['letterhead']);
    $headerFooterSpace = isset($_POST['headerFooterSpace']);
    $useSavedLetterhead = isset($_POST['useSavedLetterhead']);

    $headerHtml = '';
    $footerHtml = '';
    if ($useSavedLetterhead) {
        $settings = load_contractor_print_settings($yojId);
        $logoHtml = '';
        if (!empty($settings['logoEnabled']) && !empty($settings['logoPublicPath'])) {
            $align = $settings['logoAlign'] ?? 'left';
            $logoHtml = '<div class="print-logo" style="text-align:' . htmlspecialchars($align, ENT_QUOTES, 'UTF-8') . ';">'
                . '<img src="' . htmlspecialchars((string)$settings['logoPublicPath'], ENT_QUOTES, 'UTF-8') . '" alt="Logo"></div>';
        }
        if (!empty($settings['headerEnabled']) && trim((string)($settings['headerText'] ?? '')) !== '') {
            $headerHtml = $logoHtml . '<div class="print-header-text">' . nl2br(htmlspecialchars((string)$settings['headerText'], ENT_QUOTES, 'UTF-8')) . '</div>';
        } else {
            $headerHtml = $logoHtml;
        }
        if (!empty($settings['footerEnabled']) && trim((string)($settings['footerText'] ?? '')) !== '') {
            $footerHtml = '<div class="print-footer-text">' . nl2br(htmlspecialchars((string)$settings['footerText'], ENT_QUOTES, 'UTF-8')) . '</div>';
        }
    }

    $renderedHtml = create_docs_wrap_html($renderedBody, [
        'paper' => $paper,
        'letterhead' => $letterhead,
        'headerFooterSpace' => $headerFooterSpace,
        'headerHtml' => $headerHtml,
        'footerHtml' => $footerHtml,
        'title' => create_docs_template_title($template),
    ]);

    $docId = create_docs_generate_id(contractor_generated_docs_path($yojId));
    $now = now_kolkata()->format(DateTime::ATOM);
    $doc = [
        'docId' => $docId,
        'ownerType' => 'contractor',
        'yojId' => $yojId,
        'templateRef' => [
            'templateId' => create_docs_template_id($template),
            'scope' => $template['scope'] ?? 'contractor',
        ],
        'title' => create_docs_template_title($template),
        'createdAt' => $now,
        'filled' => [
            'values' => $values,
            'tables' => $tables,
        ],
        'renderedHtml' => $renderedHtml,
        'print' => [
            'paper' => $paper,
            'letterhead' => $letterhead,
            'headerFooterSpace' => $headerFooterSpace,
            'useSavedLetterhead' => $useSavedLetterhead,
        ],
        'missingFields' => $renderMissing,
        'audit' => [
            ['at' => $now, 'event' => 'CREATED'],
        ],
    ];

    writeJsonAtomic(contractor_generated_docs_path($yojId) . '/' . $docId . '.json', $doc);

    logEvent(create_docs_log_path(), [
        'event' => 'doc_generated',
        'ownerType' => 'contractor',
        'yojId' => $yojId,
        'docId' => $docId,
        'templateId' => create_docs_template_id($template),
        'missingFields' => $missing,
    ]);

    set_flash('success', 'Document generated.');
    redirect('/contractor/create_doc_view.php?docId=' . urlencode($docId));
});
