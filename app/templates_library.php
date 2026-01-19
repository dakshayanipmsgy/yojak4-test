<?php
declare(strict_types=1);

function templates_global_dir(): string
{
    return DATA_PATH . '/templates_global';
}

function templates_contractor_dir(string $yojId): string
{
    return contractors_approved_path($yojId) . '/templates';
}

function templates_request_uploads_dir(string $reqId): string
{
    return DATA_PATH . '/requests/uploads/' . $reqId;
}

function ensure_templates_library_env(?string $yojId = null): void
{
    $paths = [templates_global_dir()];
    if ($yojId !== null && $yojId !== '') {
        $paths[] = templates_contractor_dir($yojId);
    }
    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}

function template_generate_id(): string
{
    $date = now_kolkata()->format('Ymd');
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = 'TPL-' . $date . '-' . $suffix;
    } while (file_exists(templates_global_dir() . '/' . $candidate . '.json'));

    return $candidate;
}

function template_allowed_categories(): array
{
    return ['Affidavit', 'Letter', 'Declaration', 'Other'];
}

function template_path(string $scope, string $id, ?string $yojId = null): string
{
    if ($scope === 'global') {
        return templates_global_dir() . '/' . $id . '.json';
    }
    if ($scope === 'contractor' && $yojId) {
        return templates_contractor_dir($yojId) . '/' . $id . '.json';
    }
    throw new InvalidArgumentException('Invalid template scope.');
}

function template_extract_placeholders(string $body): array
{
    preg_match_all('/\{\{\s*field:([a-zA-Z0-9._-]+)\s*\}\}/', $body, $matches);
    $keys = $matches[1] ?? [];
    $keys = array_values(array_unique(array_filter(array_map('trim', $keys))));
    return $keys;
}

function template_normalize_record(array $record, string $scope, ?string $yojId = null): array
{
    $id = $record['id'] ?? $record['tplId'] ?? '';
    $title = $record['title'] ?? $record['name'] ?? 'Template';
    $body = $record['body'] ?? '';
    $category = $record['category'] ?? 'Other';
    $templateType = $record['templateType'] ?? 'simple_html';
    $placeholdersUsed = $record['placeholdersUsed'] ?? $record['placeholders'] ?? template_extract_placeholders($body);
    $now = now_kolkata()->format(DateTime::ATOM);

    return [
        'id' => $id,
        'scope' => $scope,
        'owner' => [
            'yojId' => $record['owner']['yojId'] ?? ($scope === 'contractor' ? (string)$yojId : ''),
        ],
        'title' => $title,
        'category' => in_array($category, template_allowed_categories(), true) ? $category : 'Other',
        'description' => $record['description'] ?? '',
        'templateType' => $templateType,
        'body' => $body,
        'placeholdersUsed' => array_values(array_unique(array_filter(array_map('trim', (array)$placeholdersUsed)))),
        'createdAt' => $record['createdAt'] ?? $now,
        'updatedAt' => $record['updatedAt'] ?? $now,
        'published' => (bool)($record['published'] ?? true),
        'archived' => (bool)($record['archived'] ?? false),
    ];
}

function template_load(string $scope, string $id, ?string $yojId = null): ?array
{
    $path = template_path($scope, $id, $yojId);
    if (!file_exists($path)) {
        return null;
    }
    $record = readJson($path);
    if (!is_array($record)) {
        return null;
    }
    return template_normalize_record($record, $scope, $yojId);
}

function template_list(string $scope, ?string $yojId = null): array
{
    ensure_templates_library_env($scope === 'contractor' ? $yojId : null);
    $dir = $scope === 'global' ? templates_global_dir() : templates_contractor_dir((string)$yojId);
    if (!is_dir($dir)) {
        return [];
    }
    $files = array_values(array_filter(scandir($dir) ?: [], static function (string $file): bool {
        return str_starts_with($file, 'TPL-') && str_ends_with($file, '.json');
    }));
    $templates = [];
    foreach ($files as $file) {
        $record = readJson($dir . '/' . $file);
        if (!is_array($record)) {
            continue;
        }
        $templates[] = template_normalize_record($record, $scope, $yojId);
    }
    usort($templates, static function (array $a, array $b): int {
        return strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? '');
    });
    return $templates;
}

function template_save(array $template, ?string $yojId = null): void
{
    $scope = $template['scope'] ?? '';
    $id = $template['id'] ?? '';
    if (!is_string($scope) || !is_string($id) || $id === '') {
        throw new InvalidArgumentException('Invalid template payload.');
    }
    $normalized = template_normalize_record($template, $scope, $yojId);
    $normalized['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    if (!$normalized['createdAt']) {
        $normalized['createdAt'] = $normalized['updatedAt'];
    }
    writeJsonAtomic(template_path($scope, $id, $yojId), $normalized);
}

function template_validate(array $payload, bool $requireId = false): array
{
    $errors = [];
    if ($requireId && trim((string)($payload['id'] ?? '')) === '') {
        $errors[] = 'Missing template id.';
    }
    $title = trim((string)($payload['title'] ?? ''));
    if ($title === '') {
        $errors[] = 'Template title is required.';
    }
    $category = $payload['category'] ?? '';
    if (!in_array($category, template_allowed_categories(), true)) {
        $errors[] = 'Invalid template category.';
    }
    $body = trim((string)($payload['body'] ?? ''));
    if ($body === '') {
        $errors[] = 'Template body is required.';
    }
    if (preg_match('/(₹|Rs\.?|INR)\s*\d+/i', $body)) {
        $errors[] = 'Template body must not include filled bid/rate amounts.';
    }
    return $errors;
}

function template_placeholder_groups(string $yojId): array
{
    $groups = [
        'Firm/Company' => [
            ['key' => 'firm.name', 'label' => 'Firm Name'],
            ['key' => 'firm.address', 'label' => 'Firm Address'],
            ['key' => 'tax.gst', 'label' => 'GST Number'],
            ['key' => 'tax.pan', 'label' => 'PAN Number'],
        ],
        'Contacts' => [
            ['key' => 'contact.phone', 'label' => 'Phone'],
            ['key' => 'contact.email', 'label' => 'Email'],
        ],
        'Bank details' => [
            ['key' => 'bank.account_no', 'label' => 'Account Number'],
            ['key' => 'bank.ifsc', 'label' => 'IFSC'],
            ['key' => 'bank.branch', 'label' => 'Branch'],
        ],
        'Tender details' => [
            ['key' => 'tender.title', 'label' => 'Tender Title'],
            ['key' => 'tender.number', 'label' => 'Tender Number'],
        ],
    ];

    $memory = load_profile_memory($yojId);
    $custom = [];
    foreach (($memory['fields'] ?? []) as $key => $entry) {
        $normalized = trim((string)$key);
        if ($normalized === '') {
            continue;
        }
        $custom[] = [
            'key' => $normalized,
            'label' => profile_memory_label_from_key($normalized),
        ];
    }
    if ($custom) {
        $groups['Custom saved fields'] = $custom;
    }
    return $groups;
}

function template_render_body(string $body, array $contractor, array $tender = []): string
{
    $map = contractor_template_context($contractor, $tender);
    $profile = pack_profile_placeholder_values($contractor);
    $memory = pack_profile_memory_values((string)($contractor['yojId'] ?? ''));
    foreach (array_merge($profile, $memory) as $key => $value) {
        $val = trim((string)$value);
        if ($val === '') {
            $val = '__________';
        }
        $map['{{field:' . $key . '}}'] = $val;
        $map['{{' . $key . '}}'] = $val;
    }
    return str_replace(array_keys($map), array_values($map), $body);
}

function templates_available_for_contractor(string $yojId): array
{
    $global = array_filter(template_list('global'), static function (array $tpl): bool {
        return !empty($tpl['published']) && empty($tpl['archived']);
    });
    $contractor = array_filter(template_list('contractor', $yojId), static function (array $tpl): bool {
        return empty($tpl['archived']);
    });
    return array_values(array_merge($global, $contractor));
}

function templates_to_pack_format(array $templates): array
{
    $normalized = [];
    foreach ($templates as $tpl) {
        $normalized[] = [
            'tplId' => $tpl['id'] ?? '',
            'name' => $tpl['title'] ?? 'Template',
            'category' => 'tender',
            'language' => 'en',
            'body' => $tpl['body'] ?? '',
            'placeholders' => $tpl['placeholdersUsed'] ?? template_extract_placeholders((string)($tpl['body'] ?? '')),
        ];
    }
    return $normalized;
}
