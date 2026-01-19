<?php
declare(strict_types=1);

const PACK_TEMPLATE_LIBRARY_DIR = DATA_PATH . '/library/packs';

function global_pack_templates_dir(): string
{
    return PACK_TEMPLATE_LIBRARY_DIR;
}

function global_pack_templates_index_path(): string
{
    return global_pack_templates_dir() . '/index.json';
}

function global_pack_template_path(string $packTemplateId): string
{
    return global_pack_templates_dir() . '/' . $packTemplateId . '.json';
}

function contractor_pack_templates_dir(string $yojId): string
{
    return contractors_approved_path($yojId) . '/pack_templates';
}

function contractor_pack_templates_index_path(string $yojId): string
{
    return contractor_pack_templates_dir($yojId) . '/index.json';
}

function contractor_pack_template_path(string $yojId, string $packTemplateId): string
{
    return contractor_pack_templates_dir($yojId) . '/' . $packTemplateId . '.json';
}

function ensure_global_pack_templates_env(): void
{
    $dir = global_pack_templates_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!file_exists(global_pack_templates_index_path())) {
        writeJsonAtomic(global_pack_templates_index_path(), []);
    }
}

function ensure_contractor_pack_templates_env(string $yojId): void
{
    $dir = contractor_pack_templates_dir($yojId);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!file_exists(contractor_pack_templates_index_path($yojId))) {
        writeJsonAtomic(contractor_pack_templates_index_path($yojId), []);
    }
}

function generate_pack_template_id(): string
{
    $date = now_kolkata()->format('Ymd');
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = 'PACKTPL-' . $date . '-' . $suffix;
    } while (file_exists(global_pack_template_path($candidate)));

    return $candidate;
}

function normalize_pack_template_schema(array $template, string $scope, string $yojId = ''): array
{
    $now = now_kolkata()->format(DateTime::ATOM);
    $packTemplateId = $template['packTemplateId'] ?? ($template['id'] ?? '');
    if ($packTemplateId === '') {
        $packTemplateId = generate_pack_template_id();
    }

    $items = is_array($template['items'] ?? null) ? $template['items'] : [];

    return [
        'packTemplateId' => $packTemplateId,
        'scope' => $scope,
        'owner' => [
            'yojId' => $yojId !== '' ? $yojId : (string)($template['owner']['yojId'] ?? ''),
        ],
        'title' => trim((string)($template['title'] ?? 'Pack Template')),
        'description' => trim((string)($template['description'] ?? '')),
        'items' => $items,
        'rules' => array_merge([
            'autoCreateOnNewTenderPack' => false,
        ], is_array($template['rules'] ?? null) ? $template['rules'] : []),
        'createdAt' => $template['createdAt'] ?? $now,
        'updatedAt' => $now,
        'status' => in_array($template['status'] ?? 'active', ['active', 'archived'], true) ? ($template['status'] ?? 'active') : 'active',
    ];
}

function load_global_pack_template_index(): array
{
    ensure_global_pack_templates_env();
    $index = readJson(global_pack_templates_index_path());
    return is_array($index) ? array_values($index) : [];
}

function save_global_pack_template_index(array $records): void
{
    ensure_global_pack_templates_env();
    writeJsonAtomic(global_pack_templates_index_path(), array_values($records));
}

function load_global_pack_template(string $packTemplateId): ?array
{
    ensure_global_pack_templates_env();
    $path = global_pack_template_path($packTemplateId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ? normalize_pack_template_schema($data, 'global') : null;
}

function load_global_pack_templates_full(): array
{
    $templates = [];
    foreach (load_global_pack_template_index() as $entry) {
        $tpl = load_global_pack_template($entry['packTemplateId'] ?? '');
        if ($tpl) {
            $templates[] = $tpl;
        }
    }
    return $templates;
}

function save_global_pack_template(array $template): void
{
    $template = normalize_pack_template_schema($template, 'global');
    ensure_global_pack_templates_env();
    writeJsonAtomic(global_pack_template_path($template['packTemplateId']), $template);

    $index = load_global_pack_template_index();
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['packTemplateId'] ?? '') === $template['packTemplateId']) {
            $entry['title'] = $template['title'];
            $entry['status'] = $template['status'];
            $entry['updatedAt'] = $template['updatedAt'];
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'packTemplateId' => $template['packTemplateId'],
            'title' => $template['title'],
            'status' => $template['status'],
            'updatedAt' => $template['updatedAt'],
        ];
    }
    save_global_pack_template_index($index);
}

function load_contractor_pack_template_index(string $yojId): array
{
    ensure_contractor_pack_templates_env($yojId);
    $index = readJson(contractor_pack_templates_index_path($yojId));
    return is_array($index) ? array_values($index) : [];
}

function save_contractor_pack_template_index(string $yojId, array $records): void
{
    ensure_contractor_pack_templates_env($yojId);
    writeJsonAtomic(contractor_pack_templates_index_path($yojId), array_values($records));
}

function load_contractor_pack_template(string $yojId, string $packTemplateId): ?array
{
    ensure_contractor_pack_templates_env($yojId);
    $path = contractor_pack_template_path($yojId, $packTemplateId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ? normalize_pack_template_schema($data, 'contractor', $yojId) : null;
}

function load_contractor_pack_templates_full(string $yojId): array
{
    $templates = [];
    foreach (load_contractor_pack_template_index($yojId) as $entry) {
        $tpl = load_contractor_pack_template($yojId, $entry['packTemplateId'] ?? '');
        if ($tpl) {
            $templates[] = $tpl;
        }
    }
    return $templates;
}

function save_contractor_pack_template(string $yojId, array $template): void
{
    $template = normalize_pack_template_schema($template, 'contractor', $yojId);
    ensure_contractor_pack_templates_env($yojId);
    writeJsonAtomic(contractor_pack_template_path($yojId, $template['packTemplateId']), $template);

    $index = load_contractor_pack_template_index($yojId);
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['packTemplateId'] ?? '') === $template['packTemplateId']) {
            $entry['title'] = $template['title'];
            $entry['status'] = $template['status'];
            $entry['updatedAt'] = $template['updatedAt'];
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'packTemplateId' => $template['packTemplateId'],
            'title' => $template['title'],
            'status' => $template['status'],
            'updatedAt' => $template['updatedAt'],
        ];
    }
    save_contractor_pack_template_index($yojId, $index);
}
