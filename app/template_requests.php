<?php
declare(strict_types=1);

function template_requests_dir(): string
{
    return DATA_PATH . '/template_requests';
}

function template_request_path(string $requestId): string
{
    return template_requests_dir() . '/' . $requestId . '.json';
}

function template_request_upload_dir(string $requestId): string
{
    return template_requests_dir() . '/' . $requestId . '/uploads';
}

function ensure_template_requests_env(): void
{
    $paths = [
        template_requests_dir(),
    ];
    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}

function generate_template_request_id(): string
{
    ensure_template_requests_env();
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = 'REQ-' . now_kolkata()->format('Ymd') . '-' . $suffix;
    } while (file_exists(template_request_path($candidate)));
    return $candidate;
}

function load_template_request(string $requestId): ?array
{
    ensure_template_requests_env();
    $path = template_request_path($requestId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function save_template_request(array $request): array
{
    ensure_template_requests_env();
    $now = now_kolkata()->format(DateTime::ATOM);
    $request['requestId'] = $request['requestId'] ?? generate_template_request_id();
    $request['createdAt'] = $request['createdAt'] ?? $now;
    $request['updatedAt'] = $now;
    $request['status'] = $request['status'] ?? 'new';
    $request['result'] = $request['result'] ?? [
        'createdTemplateIds' => [],
        'createdPackTemplateIds' => [],
    ];
    writeJsonAtomic(template_request_path($request['requestId']), $request);
    return $request;
}

function list_template_requests(?string $yojId = null): array
{
    ensure_template_requests_env();
    $files = glob(template_requests_dir() . '/*.json') ?: [];
    $records = [];
    foreach ($files as $file) {
        $data = readJson($file);
        if (!is_array($data) || empty($data['requestId'])) {
            continue;
        }
        if ($yojId && ($data['yojId'] ?? '') !== $yojId) {
            continue;
        }
        $records[] = $data;
    }
    usort($records, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));
    return $records;
}

function update_template_request_status(string $requestId, array $updates): ?array
{
    $request = load_template_request($requestId);
    if (!$request) {
        return null;
    }
    $request = array_merge($request, $updates);
    $request['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(template_request_path($requestId), $request);
    return $request;
}
