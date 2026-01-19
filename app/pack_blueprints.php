<?php
declare(strict_types=1);

function pack_blueprints_global_dir(): string
{
    return DATA_PATH . '/pack_blueprints_global';
}

function pack_blueprints_contractor_dir(string $yojId): string
{
    return contractors_approved_path($yojId) . '/pack_blueprints';
}

function ensure_pack_blueprints_env(?string $yojId = null): void
{
    $paths = [pack_blueprints_global_dir()];
    if ($yojId !== null && $yojId !== '') {
        $paths[] = pack_blueprints_contractor_dir($yojId);
    }
    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}

function pack_blueprint_generate_id(): string
{
    $date = now_kolkata()->format('Ymd');
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = 'PKB-' . $date . '-' . $suffix;
    } while (file_exists(pack_blueprints_global_dir() . '/' . $candidate . '.json'));

    return $candidate;
}

function pack_blueprint_path(string $scope, string $id, ?string $yojId = null): string
{
    if ($scope === 'global') {
        return pack_blueprints_global_dir() . '/' . $id . '.json';
    }
    if ($scope === 'contractor' && $yojId) {
        return pack_blueprints_contractor_dir($yojId) . '/' . $id . '.json';
    }
    throw new InvalidArgumentException('Invalid blueprint scope.');
}

function pack_blueprint_normalize(array $record, string $scope, ?string $yojId = null): array
{
    $id = $record['id'] ?? '';
    $now = now_kolkata()->format(DateTime::ATOM);
    return [
        'id' => $id,
        'scope' => $scope,
        'owner' => [
            'yojId' => $record['owner']['yojId'] ?? ($scope === 'contractor' ? (string)$yojId : ''),
        ],
        'title' => $record['title'] ?? 'Pack Blueprint',
        'description' => $record['description'] ?? '',
        'items' => [
            'checklist' => array_values($record['items']['checklist'] ?? []),
            'requiredFieldKeys' => array_values($record['items']['requiredFieldKeys'] ?? []),
            'templates' => array_values($record['items']['templates'] ?? []),
        ],
        'printStructure' => [
            'includeIndex' => (bool)($record['printStructure']['includeIndex'] ?? true),
            'includeChecklist' => (bool)($record['printStructure']['includeChecklist'] ?? true),
            'includeTemplates' => (bool)($record['printStructure']['includeTemplates'] ?? true),
        ],
        'createdAt' => $record['createdAt'] ?? $now,
        'updatedAt' => $record['updatedAt'] ?? $now,
        'published' => (bool)($record['published'] ?? true),
        'archived' => (bool)($record['archived'] ?? false),
    ];
}

function pack_blueprint_load(string $scope, string $id, ?string $yojId = null): ?array
{
    $path = pack_blueprint_path($scope, $id, $yojId);
    if (!file_exists($path)) {
        return null;
    }
    $record = readJson($path);
    if (!is_array($record)) {
        return null;
    }
    return pack_blueprint_normalize($record, $scope, $yojId);
}

function pack_blueprint_list(string $scope, ?string $yojId = null): array
{
    ensure_pack_blueprints_env($scope === 'contractor' ? $yojId : null);
    $dir = $scope === 'global' ? pack_blueprints_global_dir() : pack_blueprints_contractor_dir((string)$yojId);
    if (!is_dir($dir)) {
        return [];
    }
    $files = array_values(array_filter(scandir($dir) ?: [], static function (string $file): bool {
        return str_starts_with($file, 'PKB-') && str_ends_with($file, '.json');
    }));
    $blueprints = [];
    foreach ($files as $file) {
        $record = readJson($dir . '/' . $file);
        if (!is_array($record)) {
            continue;
        }
        $blueprints[] = pack_blueprint_normalize($record, $scope, $yojId);
    }
    usort($blueprints, static function (array $a, array $b): int {
        return strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? '');
    });
    return $blueprints;
}

function pack_blueprint_save(array $blueprint, ?string $yojId = null): void
{
    $scope = $blueprint['scope'] ?? '';
    $id = $blueprint['id'] ?? '';
    if (!is_string($scope) || !is_string($id) || $id === '') {
        throw new InvalidArgumentException('Invalid blueprint payload.');
    }
    $normalized = pack_blueprint_normalize($blueprint, $scope, $yojId);
    $normalized['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    if (!$normalized['createdAt']) {
        $normalized['createdAt'] = $normalized['updatedAt'];
    }
    writeJsonAtomic(pack_blueprint_path($scope, $id, $yojId), $normalized);
}

function pack_blueprint_validate(array $payload): array
{
    $errors = [];
    if (trim((string)($payload['title'] ?? '')) === '') {
        $errors[] = 'Pack title is required.';
    }
    $checklist = $payload['items']['checklist'] ?? [];
    if (!is_array($checklist)) {
        $errors[] = 'Checklist must be an array.';
    }
    return $errors;
}

function pack_blueprints_available_for_contractor(string $yojId): array
{
    $global = array_filter(pack_blueprint_list('global'), static function (array $bp): bool {
        return !empty($bp['published']) && empty($bp['archived']);
    });
    $contractor = array_filter(pack_blueprint_list('contractor', $yojId), static function (array $bp): bool {
        return empty($bp['archived']);
    });
    return array_values(array_merge($global, $contractor));
}

function pack_blueprint_parse_checklist(string $raw): array
{
    $lines = preg_split('/\r\n|\r|\n/', $raw);
    $items = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $title = $parts[0] ?? '';
        if ($title === '') {
            continue;
        }
        $required = true;
        if (isset($parts[1])) {
            $required = !in_array(strtolower($parts[1]), ['no', 'false', '0', 'optional'], true);
        }
        $category = $parts[2] ?? '';
        $items[] = [
            'title' => $title,
            'required' => $required,
            'category' => $category !== '' ? $category : pack_infer_category($title),
        ];
    }
    return $items;
}
