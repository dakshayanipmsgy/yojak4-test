<?php
declare(strict_types=1);

function ensure_template_pack_library_env(): void
{
    $dirs = [
        DATA_PATH . '/library',
        DATA_PATH . '/library/templates_global',
        DATA_PATH . '/library/packs_global',
        DATA_PATH . '/requests',
        DATA_PATH . '/requests/uploads',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $indexPath = DATA_PATH . '/requests/index.json';
    if (!file_exists($indexPath)) {
        writeJsonAtomic($indexPath, []);
    }
}

function global_templates_dir(): string
{
    return DATA_PATH . '/library/templates_global';
}

function global_packs_dir(): string
{
    return DATA_PATH . '/library/packs_global';
}

function global_template_path(string $tplId): string
{
    return global_templates_dir() . '/' . $tplId . '.json';
}

function global_pack_path(string $packId): string
{
    return global_packs_dir() . '/' . $packId . '.json';
}

function generate_global_template_id(): string
{
    ensure_template_pack_library_env();
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $candidate = 'TPLG-' . $suffix;
    } while (file_exists(global_template_path($candidate)));
    return $candidate;
}

function generate_global_pack_id(): string
{
    ensure_template_pack_library_env();
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $candidate = 'PKG-' . $suffix;
    } while (file_exists(global_pack_path($candidate)));
    return $candidate;
}

function load_global_template(string $tplId): ?array
{
    $path = global_template_path($tplId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function load_global_pack(string $packId): ?array
{
    $path = global_pack_path($packId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function load_global_templates(): array
{
    ensure_template_pack_library_env();
    $templates = [];
    foreach (scandir(global_templates_dir()) ?: [] as $file) {
        if (!str_ends_with($file, '.json')) {
            continue;
        }
        $data = readJson(global_templates_dir() . '/' . $file);
        if ($data) {
            $templates[] = $data;
        }
    }
    return $templates;
}

function load_global_packs(): array
{
    ensure_template_pack_library_env();
    $packs = [];
    foreach (scandir(global_packs_dir()) ?: [] as $file) {
        if (!str_ends_with($file, '.json')) {
            continue;
        }
        $data = readJson(global_packs_dir() . '/' . $file);
        if ($data) {
            $packs[] = $data;
        }
    }
    return $packs;
}

function save_global_template(array $template): void
{
    if (empty($template['id'])) {
        throw new InvalidArgumentException('Missing template id.');
    }
    $now = now_kolkata()->format(DateTime::ATOM);
    $template['updatedAt'] = $now;
    $template['createdAt'] = $template['createdAt'] ?? $now;
    $template['scope'] = 'global';
    $template['published'] = $template['published'] ?? true;
    writeJsonAtomic(global_template_path($template['id']), $template);
}

function save_global_pack(array $pack): void
{
    if (empty($pack['id'])) {
        throw new InvalidArgumentException('Missing pack id.');
    }
    $now = now_kolkata()->format(DateTime::ATOM);
    $pack['updatedAt'] = $now;
    $pack['createdAt'] = $pack['createdAt'] ?? $now;
    $pack['scope'] = 'global';
    $pack['published'] = $pack['published'] ?? true;
    writeJsonAtomic(global_pack_path($pack['id']), $pack);
}

function generate_contractor_template_id_v2(string $yojId): string
{
    ensure_contractor_templates_env($yojId);
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $candidate = 'TPLM-' . $suffix;
    } while (file_exists(contractor_template_path($yojId, $candidate)));
    return $candidate;
}

function generate_contractor_pack_id_v2(string $yojId): string
{
    ensure_contractor_pack_blueprints_env($yojId);
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $candidate = 'PKM-' . $suffix;
    } while (file_exists(contractor_pack_blueprint_path($yojId, $candidate)));
    return $candidate;
}

function template_placeholder_tokens(string $body): array
{
    if ($body === '') {
        return [];
    }
    preg_match_all('/{{\s*field:([a-z0-9_.-]+)\s*}}/i', $body, $matches);
    $tokens = [];
    foreach ($matches[1] ?? [] as $raw) {
        $key = pack_normalize_placeholder_key((string)$raw);
        if ($key !== '') {
            $tokens[] = $key;
        }
    }
    preg_match_all('/{{\s*field:table:([a-z0-9_.-]+)\s*}}/i', $body, $tableMatches);
    foreach ($tableMatches[1] ?? [] as $raw) {
        $tableKey = placeholder_canonical_table_key((string)$raw);
        if ($tableKey !== '') {
            $tokens[] = 'table:' . $tableKey;
        }
    }
    return array_values(array_unique($tokens));
}

function template_contains_forbidden_fields(string $body): bool
{
    $forbidden = [
        'bid_rate',
        'bidrate',
        'quoted_price',
        'quotedprice',
        'boq_rate',
        'boqrate',
        'price_bid',
        'financial_bid',
        'quoted_amount',
    ];
    $tokens = template_placeholder_tokens($body);
    preg_match_all('/{{\\s*([^}]+)}}/i', $body, $matches);
    foreach (($matches[1] ?? []) as $raw) {
        $raw = preg_replace('/^field:/i', '', (string)$raw);
        $tokens[] = pack_normalize_placeholder_key((string)$raw);
    }
    foreach (array_unique($tokens) as $token) {
        $normalized = strtolower(str_replace(['.', '-'], '_', (string)$token));
        foreach ($forbidden as $bad) {
            if (str_contains($normalized, $bad)) {
                return true;
            }
        }
    }
    return false;
}

function template_body_errors(string $body): array
{
    $errors = [];
    if (template_contains_forbidden_fields($body)) {
        $errors[] = 'Pricing fields like bid_rate or quoted_price are not allowed. Use blank manual columns only.';
    }
    return $errors;
}

function templates_field_catalog(): array
{
    return pack_default_field_meta();
}

function templates_missing_profile_fields(array $contractor): array
{
    $values = pack_profile_placeholder_values($contractor);
    $missing = [];
    foreach (templates_field_catalog() as $key => $meta) {
        if (!in_array($meta['group'] ?? '', ['Contractor Contact', 'Bank Details', 'Signatory'], true)) {
            continue;
        }
        $value = (string)($values[$key] ?? '');
        if (trim($value) === '') {
            $missing[$key] = $meta['label'] ?? $key;
        }
    }
    return $missing;
}

function request_index_path(): string
{
    return DATA_PATH . '/requests/index.json';
}

function request_upload_root(): string
{
    return DATA_PATH . '/requests/uploads';
}

function request_path(string $requestId): string
{
    return DATA_PATH . '/requests/' . $requestId . '.json';
}

function load_requests_index(): array
{
    ensure_template_pack_library_env();
    $index = readJson(request_index_path());
    return is_array($index) ? array_values($index) : [];
}

function save_requests_index(array $records): void
{
    writeJsonAtomic(request_index_path(), array_values($records));
}

function generate_request_id(): string
{
    $date = now_kolkata()->format('Ymd');
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $candidate = 'REQ-' . $date . '-' . $suffix;
    } while (file_exists(request_path($candidate)));
    return $candidate;
}

function load_request(string $requestId): ?array
{
    $path = request_path($requestId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function save_request(array $request): void
{
    if (empty($request['id'])) {
        throw new InvalidArgumentException('Missing request id.');
    }
    $now = now_kolkata()->format(DateTime::ATOM);
    $request['updatedAt'] = $now;
    $request['createdAt'] = $request['createdAt'] ?? $now;
    writeJsonAtomic(request_path($request['id']), $request);

    $index = load_requests_index();
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['id'] ?? '') === $request['id']) {
            $entry['type'] = $request['type'];
            $entry['yojId'] = $request['yojId'] ?? '';
            $entry['title'] = $request['title'] ?? '';
            $entry['status'] = $request['status'] ?? 'new';
            $entry['updatedAt'] = $request['updatedAt'];
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'id' => $request['id'],
            'type' => $request['type'],
            'yojId' => $request['yojId'] ?? '',
            'title' => $request['title'] ?? '',
            'status' => $request['status'] ?? 'new',
            'updatedAt' => $request['updatedAt'],
        ];
    }
    save_requests_index($index);
}

function request_handle_upload(string $requestId, array $file): ?array
{
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    $name = basename((string)($file['name'] ?? 'tender.pdf'));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        return null;
    }
    $uploadDir = request_upload_root() . '/' . $requestId;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }
    $target = $uploadDir . '/original.pdf';
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return null;
    }
    return [
        'name' => $name,
        'path' => $target,
    ];
}
