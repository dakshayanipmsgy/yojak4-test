<?php
declare(strict_types=1);

function create_docs_log_path(): string
{
    return DATA_PATH . '/logs/create_docs.log';
}

function contractor_generated_docs_path(string $yojId): string
{
    return contractors_approved_path($yojId) . '/generated_docs';
}

function ensure_contractor_generated_docs_env(string $yojId): void
{
    $dir = contractor_generated_docs_path($yojId);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function ensure_department_generated_docs_env(string $deptId): void
{
    $dir = department_generated_docs_path($deptId);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function create_docs_generate_id(string $dir): string
{
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $docId = 'DOC-' . now_kolkata()->format('Ymd') . '-' . $suffix;
        $path = rtrim($dir, '/') . '/' . $docId . '.json';
    } while (file_exists($path));
    return $docId;
}

function create_docs_list_generated(string $dir, int $limit = 12): array
{
    $docs = [];
    $files = is_dir($dir) ? scandir($dir) : [];
    foreach ($files as $file) {
        if (!str_ends_with((string)$file, '.json')) {
            continue;
        }
        $doc = readJson($dir . '/' . $file);
        if ($doc) {
            $docs[] = $doc;
        }
    }
    usort($docs, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
    return array_slice($docs, 0, $limit);
}

function create_docs_template_body(array $template): array
{
    if (array_key_exists('bodyHtml', $template)) {
        return [(string)($template['bodyHtml'] ?? ''), true];
    }
    return [(string)($template['body'] ?? ''), false];
}

function create_docs_template_id(array $template): string
{
    return (string)($template['id'] ?? ($template['tplId'] ?? ($template['templateId'] ?? '')));
}

function create_docs_template_title(array $template): string
{
    return (string)($template['title'] ?? ($template['name'] ?? 'Template'));
}

function create_docs_collect_template_keys(array $template): array
{
    [$body] = create_docs_template_body($template);
    return template_placeholder_tokens($body);
}

function create_docs_table_columns(string $tableKey): array
{
    $tableKey = placeholder_canonical_table_key($tableKey);
    $schemas = [
        'items' => [
            ['key' => 'item_desc', 'label' => 'Item Description'],
            ['key' => 'qty', 'label' => 'Qty'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'rate', 'label' => 'Rate'],
            ['key' => 'amount', 'label' => 'Amount'],
        ],
        'experience_summary' => [
            ['key' => 'project', 'label' => 'Project'],
            ['key' => 'client', 'label' => 'Client'],
            ['key' => 'year', 'label' => 'Year'],
            ['key' => 'scope', 'label' => 'Scope'],
            ['key' => 'status', 'label' => 'Completion Status'],
        ],
        'manpower_list' => [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'role', 'label' => 'Role'],
            ['key' => 'experience_years', 'label' => 'Experience (Years)'],
            ['key' => 'qualification', 'label' => 'Qualifications'],
        ],
        'equipment_list' => [
            ['key' => 'equipment', 'label' => 'Equipment'],
            ['key' => 'make_model', 'label' => 'Make/Model'],
            ['key' => 'quantity', 'label' => 'Quantity'],
            ['key' => 'availability', 'label' => 'Availability'],
        ],
        'tech_compliance' => [
            ['key' => 'clause', 'label' => 'Clause'],
            ['key' => 'requirement', 'label' => 'Requirement'],
            ['key' => 'response', 'label' => 'Response'],
        ],
    ];

    return $schemas[$tableKey] ?? $schemas['items'];
}

function create_docs_normalize_rows(array $rows, array $columns): array
{
    $clean = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $entry = [];
        $hasValue = false;
        foreach ($columns as $column) {
            $key = $column['key'];
            $value = trim((string)($row[$key] ?? ''));
            if ($value !== '') {
                $hasValue = true;
            }
            $entry[$key] = $value;
        }
        if ($hasValue) {
            $clean[] = $entry;
        }
    }
    return $clean;
}

function create_docs_autofill_amount(array &$row): void
{
    $qty = trim((string)($row['qty'] ?? ''));
    $rate = trim((string)($row['rate'] ?? ''));
    $amount = trim((string)($row['amount'] ?? ''));
    if ($amount !== '' || $qty === '' || $rate === '') {
        return;
    }
    $qtyValue = is_numeric($qty) ? (float)$qty : null;
    $rateValue = is_numeric($rate) ? (float)$rate : null;
    if ($qtyValue === null || $rateValue === null) {
        return;
    }
    $row['amount'] = rtrim(rtrim(number_format($qtyValue * $rateValue, 2, '.', ''), '0'), '.');
}

function create_docs_render_table_html(string $tableKey, array $rows): string
{
    $columns = create_docs_table_columns($tableKey);
    $rows = create_docs_normalize_rows($rows, $columns);
    if (!$rows) {
        $blank = [];
        foreach ($columns as $column) {
            $blank[$column['key']] = '';
        }
        $rows = [$blank];
    }

    $headerCells = '';
    foreach ($columns as $column) {
        $headerCells .= '<th>' . htmlspecialchars($column['label'], ENT_QUOTES, 'UTF-8') . '</th>';
    }

    $bodyRows = '';
    foreach ($rows as $row) {
        create_docs_autofill_amount($row);
        $bodyRows .= '<tr>';
        foreach ($columns as $column) {
            $value = (string)($row[$column['key']] ?? '');
            $bodyRows .= '<td>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        $bodyRows .= '</tr>';
    }

    return '<table class="doc-table">'
        . '<thead><tr>' . $headerCells . '</tr></thead>'
        . '<tbody>' . $bodyRows . '</tbody>'
        . '</table>';
}

function create_docs_apply_placeholders(string $body, bool $isHtml, array $values, array $tables, array &$missing): string
{
    $missing = [];
    $stats = [];
    $body = migrate_placeholders_to_canonical($body, $stats);

    $tableTokens = [];
    $body = preg_replace_callback('/{{\s*field:table:([a-z0-9_.-]+)\s*}}/i', static function (array $match) use (&$tableTokens, $tables): string {
        $tableKey = placeholder_canonical_table_key($match[1] ?? '');
        $token = '##TABLE_' . strtoupper(bin2hex(random_bytes(4))) . '##';
        $tableTokens[$token] = create_docs_render_table_html($tableKey, $tables[$tableKey] ?? []);
        return $token;
    }, $body) ?? $body;

    $body = preg_replace_callback('/{{\s*field:([a-z0-9_.-]+)\s*}}/i', static function (array $match) use (&$missing, $values, $isHtml): string {
        $key = placeholder_canonical_key($match[1] ?? '');
        $value = trim((string)($values[$key] ?? ''));
        if ($value === '') {
            $missing[] = $key;
            return '__________';
        }
        return $isHtml ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
    }, $body) ?? $body;

    $body = preg_replace('/{{\s*[^}]+\s*}}/', '__________', $body) ?? $body;

    if (!$isHtml) {
        $body = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
    } else {
        $body = str_replace('__________', '<span class="doc-blank">__________</span>', $body);
    }

    if ($tableTokens) {
        $body = str_replace(array_keys($tableTokens), array_values($tableTokens), $body);
    }

    $missing = array_values(array_unique($missing));
    return $body;
}

function create_docs_resolve_contractor_values(string $yojId, array $contractor): array
{
    $values = pack_profile_placeholder_values($contractor);
    $memory = pack_profile_memory_values($yojId);
    foreach ($memory as $key => $value) {
        if (trim((string)$value) !== '') {
            $values[$key] = (string)$value;
        }
    }

    if (trim((string)($values['contractor.date'] ?? '')) === '') {
        $values['contractor.date'] = now_kolkata()->format('d M Y');
    }
    if (trim((string)($values['contractor.place'] ?? '')) === '') {
        $values['contractor.place'] = pack_placeholder_suggestion('contractor.place', [], $contractor);
    }

    return $values;
}

function create_docs_resolve_department_values(array $department, array $user, array $memory, string $docTitle): array
{
    $values = [
        'tender.departmentName' => (string)($department['nameEn'] ?? ($department['name'] ?? ($department['deptId'] ?? ''))),
        'tender.office_address' => (string)($department['address'] ?? ''),
        'tender.user_name' => (string)($user['displayName'] ?? ($user['username'] ?? '')),
        'tender.document_date' => now_kolkata()->format('d M Y'),
        'tender.document_title' => $docTitle,
    ];

    foreach ($memory as $key => $value) {
        if (trim((string)$value) !== '') {
            $values[$key] = (string)$value;
        }
    }

    return $values;
}

function create_docs_wrap_html(string $bodyHtml, array $options): string
{
    $paper = $options['paper'] ?? 'A4';
    $letterhead = !empty($options['letterhead']);
    $headerFooterSpace = !empty($options['headerFooterSpace']);
    $headerHtml = (string)($options['headerHtml'] ?? '');
    $footerHtml = (string)($options['footerHtml'] ?? '');
    $title = (string)($options['title'] ?? 'Generated Document');

    $headerBlock = '';
    $footerBlock = '';
    $headerSpace = $headerFooterSpace || $letterhead;
    if ($letterhead || $headerFooterSpace || $headerHtml !== '' || $footerHtml !== '') {
        $headerBlock = '<div class="print-header">' . $headerHtml . '</div>';
        $footerBlock = '<div class="print-footer">' . $footerHtml . '</div>';
    }

    return '<!doctype html><html lang="en"><head>'
        . '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
        . '<link rel="stylesheet" href="/assets/css/theme_tokens.css">'
        . '<style>'
        . ':root{color-scheme:light;}'
        . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;margin:0;color:var(--text);background:#fff;}'
        . '.doc-page{max-width:900px;margin:24px auto;padding:24px;border:1px solid var(--border);border-radius:12px;background:var(--surface);} '
        . '.print-header,.print-footer{width:100%;}'
        . '.print-header{border-bottom:1px solid var(--border);' . ($headerSpace ? 'min-height:40mm;' : '') . 'padding-bottom:12px;}'
        . '.print-footer{border-top:1px solid var(--border);' . ($headerSpace ? 'min-height:20mm;' : '') . 'padding-top:12px;margin-top:16px;font-size:12px;color:var(--muted);} '
        . '.doc-content{line-height:1.7;font-size:15px;}'
        . '.doc-table{width:100%;border-collapse:collapse;margin:12px 0;font-size:14px;}'
        . '.doc-table th,.doc-table td{border:1px solid var(--border);padding:8px;text-align:left;vertical-align:top;}'
        . '.doc-blank{display:inline-block;min-width:120px;border-bottom:1px solid #9ca3af;color:var(--text);} '
        . '@page{size:' . htmlspecialchars($paper, ENT_QUOTES, 'UTF-8') . ';margin:16mm;} '
        . '@media print{body{background:#fff;} .ui-only,.no-print,header,nav,footer,.topbar,.actions,.btn,.controls,.toolbar,.sidebar,.panel,.sticky-header,[data-ui="true"]{display:none !important;} .doc-page{margin:0;box-shadow:none;border:none;border-radius:0;padding:0 16mm;}}'
        . '</style></head><body>'
        . '<div class="doc-page">'
        . $headerBlock
        . '<div class="doc-content">' . $bodyHtml . '</div>'
        . $footerBlock
        . '</div>'
        . '</body></html>';
}

function create_docs_find_contractor_template(string $yojId, string $templateId, string $scope = ''): ?array
{
    $scope = strtolower($scope);
    if ($scope === 'contractor' || $scope === '') {
        $template = load_contractor_template($yojId, $templateId);
        if ($template) {
            $template['scope'] = 'contractor';
            return $template;
        }
    }
    $globalTemplates = array_values(array_filter(load_global_templates(), fn($tpl) => !empty($tpl['published'])));
    foreach ($globalTemplates as $tpl) {
        if (($tpl['id'] ?? '') === $templateId) {
            $tpl['scope'] = 'global';
            return $tpl;
        }
    }
    return null;
}

function create_docs_find_department_template(string $deptId, string $templateId, string $scope = ''): ?array
{
    $scope = strtolower($scope);
    if ($scope === 'department' || $scope === '') {
        foreach (load_department_templates($deptId) as $tpl) {
            if (($tpl['templateId'] ?? '') === $templateId) {
                $tpl['scope'] = 'department';
                return $tpl;
            }
        }
    }
    foreach (load_global_templates_cache($deptId) as $tpl) {
        if (($tpl['templateId'] ?? '') === $templateId) {
            $tpl['scope'] = 'global';
            return $tpl;
        }
    }
    return null;
}

function create_docs_group_missing_fields(array $missing, array $registry): array
{
    $groups = [];
    foreach ($missing as $key) {
        $meta = $registry['fields'][$key] ?? [
            'label' => profile_memory_label_from_key($key),
            'group' => str_starts_with($key, 'contractor.') ? 'Contractor' : (str_starts_with($key, 'tender.') ? 'Tender' : 'Custom Fields'),
            'type' => 'text',
            'max' => 200,
        ];
        $group = $meta['group'] ?? 'Other';
        if (!isset($groups[$group])) {
            $groups[$group] = [];
        }
        $groups[$group][$key] = $meta;
    }
    return $groups;
}
