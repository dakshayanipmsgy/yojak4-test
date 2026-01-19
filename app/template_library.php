<?php
declare(strict_types=1);

function template_library_global_dir(): string
{
    return DATA_PATH . '/templates/global';
}

function template_library_contractor_dir(string $yojId): string
{
    return DATA_PATH . '/templates/contractors/' . $yojId;
}

function template_library_path(string $scope, ?string $yojId, string $templateId): string
{
    if ($scope === 'global') {
        return template_library_global_dir() . '/' . $templateId . '/template.json';
    }
    if (!$yojId) {
        throw new InvalidArgumentException('Missing contractor id for template path');
    }
    return template_library_contractor_dir($yojId) . '/' . $templateId . '/template.json';
}

function ensure_template_library_env(): void
{
    $paths = [
        DATA_PATH . '/templates',
        DATA_PATH . '/templates/global',
        DATA_PATH . '/templates/contractors',
    ];
    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}

function generate_template_library_id(): string
{
    ensure_template_library_env();
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $candidate = 'TPL-' . $suffix;
        $globalExists = file_exists(template_library_path('global', null, $candidate));
        $contractorExists = glob(DATA_PATH . '/templates/contractors/*/' . $candidate . '/template.json');
    } while ($globalExists || !empty($contractorExists));

    return $candidate;
}

function template_library_profile_fields(): array
{
    return [
        [
            'key' => 'contractor.firm_name',
            'label' => 'Firm Name',
            'type' => 'text',
            'source' => 'contractor_profile',
            'required' => true,
            'guidance' => 'Registered contractor firm name.',
        ],
        [
            'key' => 'contractor.address',
            'label' => 'Firm Address',
            'type' => 'text',
            'source' => 'contractor_profile',
            'required' => true,
            'guidance' => 'Registered office address.',
        ],
        [
            'key' => 'contractor.gst',
            'label' => 'GST Number',
            'type' => 'text',
            'source' => 'contractor_profile',
            'required' => false,
            'guidance' => 'GSTIN from registration.',
        ],
        [
            'key' => 'contractor.pan',
            'label' => 'PAN Number',
            'type' => 'text',
            'source' => 'contractor_profile',
            'required' => false,
            'guidance' => 'PAN from income tax records.',
        ],
        [
            'key' => 'contractor.email',
            'label' => 'Contact Email',
            'type' => 'text',
            'source' => 'contractor_profile',
            'required' => false,
            'guidance' => 'Primary email for communication.',
        ],
        [
            'key' => 'contractor.mobile',
            'label' => 'Contact Mobile',
            'type' => 'text',
            'source' => 'contractor_profile',
            'required' => true,
            'guidance' => 'Primary mobile number.',
        ],
        [
            'key' => 'contractor.signatory_name',
            'label' => 'Authorized Signatory',
            'type' => 'text',
            'source' => 'contractor_profile',
            'required' => true,
            'guidance' => 'Name of the authorized signatory.',
        ],
        [
            'key' => 'contractor.signatory_designation',
            'label' => 'Signatory Designation',
            'type' => 'text',
            'source' => 'contractor_profile',
            'required' => false,
            'guidance' => 'Designation of authorized signatory.',
        ],
        [
            'key' => 'contractor.bank_name',
            'label' => 'Bank Name',
            'type' => 'text',
            'source' => 'contractor_profile',
            'required' => false,
            'guidance' => 'Bank name as per profile.',
        ],
        [
            'key' => 'contractor.bank_account',
            'label' => 'Bank Account Number',
            'type' => 'text',
            'source' => 'contractor_profile',
            'required' => false,
            'guidance' => 'Account number for correspondence.',
        ],
        [
            'key' => 'contractor.ifsc',
            'label' => 'Bank IFSC',
            'type' => 'text',
            'source' => 'contractor_profile',
            'required' => false,
            'guidance' => 'IFSC code for bank branch.',
        ],
        [
            'key' => 'tender.title',
            'label' => 'Tender Title',
            'type' => 'text',
            'source' => 'tender_context',
            'required' => false,
            'guidance' => 'Tender name/title.',
        ],
        [
            'key' => 'tender.number',
            'label' => 'Tender Number',
            'type' => 'text',
            'source' => 'tender_context',
            'required' => false,
            'guidance' => 'Tender ID or reference number.',
        ],
        [
            'key' => 'tender.department',
            'label' => 'Department Name',
            'type' => 'text',
            'source' => 'tender_context',
            'required' => false,
            'guidance' => 'Department issuing the tender.',
        ],
        [
            'key' => 'tender.deadline',
            'label' => 'Submission Deadline',
            'type' => 'text',
            'source' => 'tender_context',
            'required' => false,
            'guidance' => 'Last date/time for submission.',
        ],
        [
            'key' => 'meta.place',
            'label' => 'Place',
            'type' => 'text',
            'source' => 'tender_context',
            'required' => false,
            'guidance' => 'Place of signing.',
        ],
        [
            'key' => 'meta.date_today',
            'label' => 'Today Date',
            'type' => 'text',
            'source' => 'system',
            'required' => true,
            'guidance' => 'Today\'s date (auto).',
        ],
    ];
}

function template_library_profile_index(): array
{
    $index = [];
    foreach (template_library_profile_fields() as $field) {
        $index[$field['key']] = $field;
    }
    return $index;
}

function template_library_field_types(): array
{
    return [
        'text' => 'Text',
        'number' => 'Number',
        'date' => 'Date',
        'select' => 'Dropdown',
        'textarea' => 'Paragraph',
    ];
}

function template_library_categories(): array
{
    return ['Tender', 'Workorder', 'General', 'Affidavit', 'Letter', 'Other'];
}

function load_template_library_record(string $scope, ?string $yojId, string $templateId): ?array
{
    ensure_template_library_env();
    $path = template_library_path($scope, $yojId, $templateId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function list_template_library_records(string $scope, ?string $yojId): array
{
    ensure_template_library_env();
    $dir = $scope === 'global'
        ? template_library_global_dir()
        : template_library_contractor_dir((string)$yojId);
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*/template.json') ?: [];
    $records = [];
    foreach ($files as $file) {
        $data = readJson($file);
        if (is_array($data) && !empty($data['templateId'])) {
            $records[] = $data;
        }
    }
    usort($records, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));
    return $records;
}

function save_template_library_record(array $template, string $scope, ?string $yojId): array
{
    ensure_template_library_env();
    $now = now_kolkata()->format(DateTime::ATOM);
    $template['templateId'] = $template['templateId'] ?? generate_template_library_id();
    $template['scope'] = $scope;
    $template['owner'] = $template['owner'] ?? ['yojId' => $yojId];
    if ($scope === 'contractor') {
        $template['owner']['yojId'] = $yojId;
    }
    $template['createdAt'] = $template['createdAt'] ?? $now;
    $template['updatedAt'] = $now;
    $template['status'] = $template['status'] ?? 'active';
    $template['editorType'] = $template['editorType'] ?? 'simple_html';
    $template['fieldCatalog'] = array_values($template['fieldCatalog'] ?? []);
    $template['rules'] = $template['rules'] ?? [
        'allowManualEditBeforePrint' => true,
        'lockAfterGenerate' => false,
    ];

    $path = template_library_path($scope, $yojId, $template['templateId']);
    writeJsonAtomic($path, $template);
    return $template;
}

function archive_template_library_record(string $scope, ?string $yojId, string $templateId): bool
{
    $template = load_template_library_record($scope, $yojId, $templateId);
    if (!$template) {
        return false;
    }
    $template['status'] = 'archived';
    save_template_library_record($template, $scope, $yojId);
    return true;
}

function template_library_forbidden_terms(): array
{
    return [
        'rate',
        'rates',
        'price',
        'boq',
        'bill of quantity',
        'unit rate',
        'bid value',
        'quoted price',
        'financial bid',
    ];
}

function template_library_allowed_terms(): array
{
    return [
        'tender fee',
        'emd',
        'security deposit',
        'performance security',
        'bid security',
    ];
}

function template_library_contains_forbidden_terms(string $text): bool
{
    $text = strtolower($text);
    foreach (template_library_allowed_terms() as $allowed) {
        if (str_contains($text, $allowed)) {
            $text = str_replace($allowed, '', $text);
        }
    }
    foreach (template_library_forbidden_terms() as $term) {
        if ($term !== '' && str_contains($text, $term)) {
            return true;
        }
    }
    return false;
}

function template_library_payload_has_forbidden_terms(array $template): array
{
    $hits = [];
    $fields = [
        (string)($template['title'] ?? ''),
        (string)($template['description'] ?? ''),
        (string)($template['body'] ?? ''),
    ];
    foreach ($fields as $field) {
        if ($field !== '' && template_library_contains_forbidden_terms($field)) {
            $hits[] = 'template_text';
            break;
        }
    }
    foreach ((array)($template['fieldCatalog'] ?? []) as $field) {
        $label = strtolower((string)($field['label'] ?? ''));
        $key = strtolower((string)($field['key'] ?? ''));
        if ($label !== '' && template_library_contains_forbidden_terms($label)) {
            $hits[] = 'field_label';
        }
        if ($key !== '' && template_library_contains_forbidden_terms($key)) {
            $hits[] = 'field_key';
        }
    }
    return array_values(array_unique($hits));
}

function template_library_render_body_preview(string $body, array $values = []): string
{
    return preg_replace_callback('/\{\{\s*field:([^}]+)\s*\}\}/', function ($matches) use ($values) {
        $key = trim((string)($matches[1] ?? ''));
        $value = trim((string)($values[$key] ?? ''));
        if ($value === '') {
            return '__________';
        }
        return sanitize($value);
    }, $body);
}
