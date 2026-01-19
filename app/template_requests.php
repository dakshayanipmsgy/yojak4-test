<?php
declare(strict_types=1);

function request_base_dir(string $type): string
{
    $type = $type === 'pack' ? 'packs' : 'templates';
    return DATA_PATH . '/requests/' . $type;
}

function request_uploads_base_dir(): string
{
    return DATA_PATH . '/requests/uploads';
}

function request_generate_id(): string
{
    $date = now_kolkata()->format('Ymd');
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = 'REQ-' . $date . '-' . $suffix;
    } while (file_exists(request_uploads_base_dir() . '/' . $candidate));

    return $candidate;
}

function ensure_request_env(string $type): void
{
    $paths = [
        request_base_dir($type),
        request_uploads_base_dir(),
    ];
    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}

function request_path(string $type, string $reqId): string
{
    return request_base_dir($type) . '/' . $reqId . '.json';
}

function request_load(string $type, string $reqId): ?array
{
    $path = request_path($type, $reqId);
    if (!file_exists($path)) {
        return null;
    }
    $record = readJson($path);
    return is_array($record) ? $record : null;
}

function request_save(string $type, array $request): void
{
    if (empty($request['id'])) {
        throw new InvalidArgumentException('Missing request id.');
    }
    ensure_request_env($type);
    writeJsonAtomic(request_path($type, (string)$request['id']), $request);
}

function request_list(string $type): array
{
    ensure_request_env($type);
    $dir = request_base_dir($type);
    if (!is_dir($dir)) {
        return [];
    }
    $files = array_values(array_filter(scandir($dir) ?: [], static function (string $file): bool {
        return str_starts_with($file, 'REQ-') && str_ends_with($file, '.json');
    }));
    $requests = [];
    foreach ($files as $file) {
        $record = readJson($dir . '/' . $file);
        if (is_array($record)) {
            $requests[] = $record;
        }
    }
    usort($requests, static function (array $a, array $b): int {
        return strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? '');
    });
    return $requests;
}

function request_status_label(string $status): string
{
    return match ($status) {
        'assigned' => 'Assigned',
        'in_progress' => 'In progress',
        'delivered' => 'Delivered',
        'rejected' => 'Rejected',
        default => 'New',
    };
}
