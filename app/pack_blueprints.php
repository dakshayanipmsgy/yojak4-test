<?php
declare(strict_types=1);

function packtpl_global_dir(): string
{
    return DATA_PATH . '/packs/global';
}

function packtpl_contractor_dir(string $yojId): string
{
    return DATA_PATH . '/packs/contractors/' . $yojId;
}

function packtpl_index_path(string $scope, string $yojId = ''): string
{
    if ($scope === 'global') {
        return packtpl_global_dir() . '/index.json';
    }
    return packtpl_contractor_dir($yojId) . '/index.json';
}

function packtpl_record_path(string $scope, string $packTplId, string $yojId = ''): string
{
    if ($scope === 'global') {
        return packtpl_global_dir() . '/' . $packTplId . '.json';
    }
    return packtpl_contractor_dir($yojId) . '/' . $packTplId . '.json';
}

function ensure_packtpl_env(string $yojId = ''): void
{
    $paths = [packtpl_global_dir()];
    if ($yojId !== '') {
        $paths[] = packtpl_contractor_dir($yojId);
    }
    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
    if (!file_exists(packtpl_index_path('global'))) {
        writeJsonAtomic(packtpl_index_path('global'), ['packTemplates' => [], 'updatedAt' => now_kolkata()->format(DateTime::ATOM)]);
    }
    if ($yojId !== '' && !file_exists(packtpl_index_path('contractor', $yojId))) {
        writeJsonAtomic(packtpl_index_path('contractor', $yojId), ['packTemplates' => [], 'updatedAt' => now_kolkata()->format(DateTime::ATOM)]);
    }
}

function normalize_packtpl_index(array $data): array
{
    if (isset($data['packTemplates']) && is_array($data['packTemplates'])) {
        return array_values($data['packTemplates']);
    }
    return array_values($data);
}

function load_packtpl_index(string $scope, string $yojId = ''): array
{
    ensure_packtpl_env($yojId);
    $index = readJson(packtpl_index_path($scope, $yojId));
    return normalize_packtpl_index($index);
}

function save_packtpl_index(string $scope, array $records, string $yojId = ''): void
{
    ensure_packtpl_env($yojId);
    writeJsonAtomic(packtpl_index_path($scope, $yojId), [
        'packTemplates' => array_values($records),
        'updatedAt' => now_kolkata()->format(DateTime::ATOM),
    ]);
}

function generate_packtpl_id(string $scope, string $yojId = ''): string
{
    ensure_packtpl_env($yojId);
    $date = now_kolkata()->format('Ymd');
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = 'PACKTPL-' . $date . '-' . $suffix;
    } while (file_exists(packtpl_record_path($scope, $candidate, $yojId)));
    return $candidate;
}

function load_packtpl_record(string $scope, string $packTplId, string $yojId = ''): ?array
{
    if ($packTplId === '') {
        return null;
    }
    $path = packtpl_record_path($scope, $packTplId, $yojId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function load_packtpl_for_contractor(string $yojId, string $packTplId): ?array
{
    if ($packTplId === '') {
        return null;
    }
    $contractor = load_packtpl_record('contractor', $packTplId, $yojId);
    if ($contractor) {
        return $contractor;
    }
    return load_packtpl_record('global', $packTplId);
}

function save_packtpl_record(string $scope, array $packTpl, string $yojId = ''): void
{
    if (empty($packTpl['packTplId'])) {
        throw new InvalidArgumentException('packTplId required.');
    }
    $now = now_kolkata()->format(DateTime::ATOM);
    $packTpl['scope'] = $scope;
    $packTpl['createdAt'] = $packTpl['createdAt'] ?? $now;
    $packTpl['updatedAt'] = $now;
    writeJsonAtomic(packtpl_record_path($scope, $packTpl['packTplId'], $yojId), $packTpl);

    $index = load_packtpl_index($scope, $yojId);
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['packTplId'] ?? '') === $packTpl['packTplId']) {
            $entry['title'] = $packTpl['title'] ?? $entry['title'];
            $entry['description'] = $packTpl['description'] ?? $entry['description'];
            $entry['updatedAt'] = $packTpl['updatedAt'];
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'packTplId' => $packTpl['packTplId'],
            'title' => $packTpl['title'] ?? 'Pack Preset',
            'description' => $packTpl['description'] ?? '',
            'updatedAt' => $packTpl['updatedAt'],
        ];
    }
    save_packtpl_index($scope, $index, $yojId);
}

function delete_packtpl_record(string $scope, string $packTplId, string $yojId = ''): void
{
    $path = packtpl_record_path($scope, $packTplId, $yojId);
    if (file_exists($path)) {
        unlink($path);
    }
    $index = load_packtpl_index($scope, $yojId);
    $index = array_values(array_filter($index, static fn($entry) => ($entry['packTplId'] ?? '') !== $packTplId));
    save_packtpl_index($scope, $index, $yojId);
}

function packtpl_parse_checklist_lines(string $raw): array
{
    $items = [];
    $lines = preg_split('/\r?\n/', $raw);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $required = true;
        if (str_contains(strtolower($line), '(optional)')) {
            $required = false;
            $line = trim(str_ireplace('(optional)', '', $line));
        }
        $category = '';
        $label = $line;
        if (str_contains($line, '|')) {
            [$category, $label] = array_map('trim', explode('|', $line, 2));
        }
        $items[] = [
            'label' => $label,
            'category' => $category,
            'required' => $required,
        ];
    }
    return $items;
}

function packtpl_normalize_sections(array $sections): array
{
    $normalized = [];
    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }
        $sectionId = trim((string)($section['sectionId'] ?? ''));
        $label = trim((string)($section['label'] ?? ''));
        if ($sectionId === '' || $label === '') {
            continue;
        }
        $entry = [
            'sectionId' => $sectionId,
            'label' => $label,
        ];
        if (isset($section['items'])) {
            $entry['items'] = array_values($section['items']);
        }
        if (isset($section['templateIds'])) {
            $entry['templateIds'] = array_values(array_filter(array_map('strval', (array)$section['templateIds'])));
        }
        if (isset($section['allowedTags'])) {
            $entry['allowedTags'] = array_values(array_filter(array_map('strval', (array)$section['allowedTags'])));
        }
        $normalized[] = $entry;
    }
    return $normalized;
}

function packtpl_validate_payload(array $payload): array
{
    $errors = [];
    $title = trim((string)($payload['title'] ?? ''));
    if (strlen($title) < 3 || strlen($title) > 80) {
        $errors[] = 'Title must be between 3 and 80 characters.';
    }
    $sections = $payload['sections'] ?? [];
    if (!is_array($sections) || !$sections) {
        $errors[] = 'At least one section is required.';
        $sections = [];
    }
    $packTpl = [
        'title' => $title,
        'description' => trim((string)($payload['description'] ?? '')),
        'sections' => packtpl_normalize_sections((array)$sections),
    ];

    return ['errors' => $errors, 'packTpl' => $packTpl];
}

function packtpl_validate_advanced_json(array $payload): array
{
    $errors = [];
    if (empty($payload['packTplId']) || !is_string($payload['packTplId'])) {
        $errors[] = 'packTplId is required.';
    }
    if (empty($payload['title']) || !is_string($payload['title'])) {
        $errors[] = 'title is required.';
    }
    if (!isset($payload['sections']) || !is_array($payload['sections'])) {
        $errors[] = 'sections must be an array.';
    }

    $sectionIds = [];
    foreach ((array)($payload['sections'] ?? []) as $section) {
        $sectionId = (string)($section['sectionId'] ?? '');
        if ($sectionId === '') {
            $errors[] = 'Each section must include sectionId.';
            continue;
        }
        if (isset($sectionIds[$sectionId])) {
            $errors[] = 'Duplicate sectionId detected: ' . $sectionId;
        }
        $sectionIds[$sectionId] = true;
    }

    $payload['sections'] = packtpl_normalize_sections((array)($payload['sections'] ?? []));
    return ['errors' => $errors, 'packTpl' => $payload];
}

function packtpl_apply_blueprint(array $pack, array $blueprint): array
{
    $pack['blueprintId'] = $blueprint['packTplId'] ?? null;
    $sections = $blueprint['sections'] ?? [];
    $templateIds = [];
    foreach ($sections as $section) {
        if (($section['sectionId'] ?? '') === 'checklist') {
            $items = $section['items'] ?? [];
            if ($items) {
                $pack['items'] = array_merge($pack['items'] ?? [], pack_items_from_checklist($items));
            }
        }
        if (($section['sectionId'] ?? '') === 'templates') {
            $templateIds = array_values(array_unique(array_merge($templateIds, $section['templateIds'] ?? [])));
        }
        if (($section['sectionId'] ?? '') === 'attachments') {
            $pack['attachmentTagsAllowed'] = array_values(array_unique(array_merge(
                $pack['attachmentTagsAllowed'] ?? [],
                $section['allowedTags'] ?? []
            )));
        }
    }
    if ($templateIds) {
        $pack['templateIdsPreferred'] = $templateIds;
    }
    return $pack;
}
