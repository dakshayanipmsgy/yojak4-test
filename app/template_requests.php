<?php
declare(strict_types=1);

const TEMPLATE_REQUESTS_ROOT = DATA_PATH . '/template_requests';
const TEMPLATE_REQUESTS_LOG = DATA_PATH . '/logs/template_requests.log';

function template_requests_index_path(): string
{
    return TEMPLATE_REQUESTS_ROOT . '/index.json';
}

function template_request_dir(string $requestId): string
{
    return TEMPLATE_REQUESTS_ROOT . '/' . $requestId;
}

function template_request_path(string $requestId): string
{
    return template_request_dir($requestId) . '/request.json';
}

function template_request_upload_dir(string $requestId): string
{
    return template_request_dir($requestId) . '/uploads';
}

function ensure_template_requests_env(): void
{
    if (!is_dir(TEMPLATE_REQUESTS_ROOT)) {
        mkdir(TEMPLATE_REQUESTS_ROOT, 0775, true);
    }
    if (!file_exists(template_requests_index_path())) {
        writeJsonAtomic(template_requests_index_path(), []);
    }
    if (!file_exists(TEMPLATE_REQUESTS_LOG)) {
        touch(TEMPLATE_REQUESTS_LOG);
    }
}

function generate_template_request_id(): string
{
    ensure_template_requests_env();
    $date = now_kolkata()->format('Ymd');
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = 'REQ-' . $date . '-' . $suffix;
    } while (file_exists(template_request_path($candidate)));

    return $candidate;
}

function load_template_request_index(): array
{
    ensure_template_requests_env();
    $index = readJson(template_requests_index_path());
    return is_array($index) ? array_values($index) : [];
}

function save_template_request_index(array $records): void
{
    ensure_template_requests_env();
    writeJsonAtomic(template_requests_index_path(), array_values($records));
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

function save_template_request(array $request): void
{
    if (empty($request['requestId'])) {
        throw new InvalidArgumentException('requestId missing');
    }
    ensure_template_requests_env();
    $now = now_kolkata()->format(DateTime::ATOM);
    $request['createdAt'] = $request['createdAt'] ?? $now;
    $request['updatedAt'] = $now;
    $request['status'] = in_array($request['status'] ?? 'pending', ['pending', 'in_progress', 'delivered', 'rejected'], true)
        ? ($request['status'] ?? 'pending')
        : 'pending';

    writeJsonAtomic(template_request_path($request['requestId']), $request);

    $index = load_template_request_index();
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['requestId'] ?? '') === $request['requestId']) {
            $entry['yojId'] = $request['yojId'] ?? '';
            $entry['type'] = $request['type'] ?? 'template';
            $entry['status'] = $request['status'];
            $entry['updatedAt'] = $request['updatedAt'];
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'requestId' => $request['requestId'],
            'yojId' => $request['yojId'] ?? '',
            'type' => $request['type'] ?? 'template',
            'status' => $request['status'],
            'updatedAt' => $request['updatedAt'],
        ];
    }
    save_template_request_index($index);
}
