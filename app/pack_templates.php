<?php
declare(strict_types=1);

function pack_templates_global_dir(): string
{
    return DATA_PATH . '/packs/global';
}

function pack_templates_contractor_dir(string $yojId): string
{
    return DATA_PATH . '/packs/contractors/' . $yojId;
}

function pack_template_path(string $scope, ?string $yojId, string $packTemplateId): string
{
    if ($scope === 'global') {
        return pack_templates_global_dir() . '/' . $packTemplateId . '/pack_template.json';
    }
    if (!$yojId) {
        throw new InvalidArgumentException('Missing contractor id for pack path');
    }
    return pack_templates_contractor_dir($yojId) . '/' . $packTemplateId . '/pack_template.json';
}

function ensure_pack_templates_env(): void
{
    $paths = [
        DATA_PATH . '/packs',
        DATA_PATH . '/packs/global',
        DATA_PATH . '/packs/contractors',
    ];
    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}

function generate_pack_template_id(): string
{
    ensure_pack_templates_env();
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $candidate = 'PKT-' . $suffix;
        $globalExists = file_exists(pack_template_path('global', null, $candidate));
        $contractorExists = glob(DATA_PATH . '/packs/contractors/*/' . $candidate . '/pack_template.json');
    } while ($globalExists || !empty($contractorExists));

    return $candidate;
}

function pack_template_field_types(): array
{
    return template_library_field_types();
}

function load_pack_template_record(string $scope, ?string $yojId, string $packTemplateId): ?array
{
    ensure_pack_templates_env();
    $path = pack_template_path($scope, $yojId, $packTemplateId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function list_pack_template_records(string $scope, ?string $yojId): array
{
    ensure_pack_templates_env();
    $dir = $scope === 'global'
        ? pack_templates_global_dir()
        : pack_templates_contractor_dir((string)$yojId);
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*/pack_template.json') ?: [];
    $records = [];
    foreach ($files as $file) {
        $data = readJson($file);
        if (is_array($data) && !empty($data['packTemplateId'])) {
            $records[] = $data;
        }
    }
    usort($records, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));
    return $records;
}

function save_pack_template_record(array $pack, string $scope, ?string $yojId): array
{
    ensure_pack_templates_env();
    $now = now_kolkata()->format(DateTime::ATOM);
    $pack['packTemplateId'] = $pack['packTemplateId'] ?? generate_pack_template_id();
    $pack['scope'] = $scope;
    $pack['owner'] = $pack['owner'] ?? ['yojId' => $yojId];
    if ($scope === 'contractor') {
        $pack['owner']['yojId'] = $yojId;
    }
    $pack['createdAt'] = $pack['createdAt'] ?? $now;
    $pack['updatedAt'] = $now;
    $pack['status'] = $pack['status'] ?? 'active';
    $pack['items'] = array_values($pack['items'] ?? []);
    $pack['fieldCatalog'] = array_values($pack['fieldCatalog'] ?? []);

    $path = pack_template_path($scope, $yojId, $pack['packTemplateId']);
    writeJsonAtomic($path, $pack);
    return $pack;
}

function archive_pack_template_record(string $scope, ?string $yojId, string $packTemplateId): bool
{
    $pack = load_pack_template_record($scope, $yojId, $packTemplateId);
    if (!$pack) {
        return false;
    }
    $pack['status'] = 'archived';
    save_pack_template_record($pack, $scope, $yojId);
    return true;
}

function pack_template_payload_has_forbidden_terms(array $pack): array
{
    $hits = [];
    $fields = [
        (string)($pack['title'] ?? ''),
        (string)($pack['description'] ?? ''),
    ];
    foreach ($fields as $field) {
        if ($field !== '' && template_library_contains_forbidden_terms($field)) {
            $hits[] = 'pack_text';
            break;
        }
    }
    foreach ((array)($pack['fieldCatalog'] ?? []) as $field) {
        $label = strtolower((string)($field['label'] ?? ''));
        $key = strtolower((string)($field['key'] ?? ''));
        if ($label !== '' && template_library_contains_forbidden_terms($label)) {
            $hits[] = 'field_label';
        }
        if ($key !== '' && template_library_contains_forbidden_terms($key)) {
            $hits[] = 'field_key';
        }
    }
    foreach ((array)($pack['items'] ?? []) as $item) {
        $title = strtolower((string)($item['title'] ?? ''));
        if ($title !== '' && template_library_contains_forbidden_terms($title)) {
            $hits[] = 'item_title';
        }
    }
    return array_values(array_unique($hits));
}
