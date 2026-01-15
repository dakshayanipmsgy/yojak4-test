<?php
declare(strict_types=1);

function ensure_suggestions_environment(): void
{
    $directories = [
        DATA_PATH . '/suggestions',
        DATA_PATH . '/ratelimits',
        DATA_PATH . '/ratelimits/suggestions',
    ];
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $logFile = DATA_PATH . '/logs/suggestions.log';
    if (!file_exists($logFile)) {
        touch($logFile);
    }
}

function suggestions_log_event(string $event, array $payload): void
{
    $file = DATA_PATH . '/logs/suggestions.log';
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $entry = array_merge([
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => $event,
    ], $payload);

    $handle = fopen($file, 'a');
    if ($handle) {
        flock($handle, LOCK_EX);
        fwrite($handle, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function suggestion_device_hint(string $userAgent): string
{
    if ($userAgent === '') {
        return 'unknown';
    }
    if (preg_match('/mobile|android|iphone|ipad|ipod|tablet/i', $userAgent)) {
        return 'mobile';
    }
    return 'desktop';
}

function suggestion_normalize_page_url(string $value): string
{
    $value = trim(strip_tags($value));
    if ($value === '') {
        return '';
    }
    $parts = parse_url($value);
    if ($parts === false) {
        return '';
    }
    $path = $parts['path'] ?? '';
    $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
    $result = $path . $query;
    return substr($result, 0, 200);
}

function suggestion_rate_limit_path(string $key): string
{
    return DATA_PATH . '/ratelimits/suggestions/' . $key . '.json';
}

function suggestion_rate_limit_state(string $key): array
{
    $state = readJson(suggestion_rate_limit_path($key));
    if (!isset($state['attempts'])) {
        $state['attempts'] = [];
    }
    return $state;
}

function suggestion_rate_limit_allowed(string $key, int $windowSeconds = 3600, int $maxAttempts = 5): bool
{
    $state = suggestion_rate_limit_state($key);
    $now = time();
    $state['attempts'] = array_values(array_filter(
        $state['attempts'],
        fn($timestamp) => ($now - (int)$timestamp) <= $windowSeconds
    ));
    writeJsonAtomic(suggestion_rate_limit_path($key), $state);
    return count($state['attempts']) < $maxAttempts;
}

function suggestion_rate_limit_record(string $key, int $windowSeconds = 3600): void
{
    $state = suggestion_rate_limit_state($key);
    $now = time();
    $state['attempts'] = array_values(array_filter(
        $state['attempts'],
        fn($timestamp) => ($now - (int)$timestamp) <= $windowSeconds
    ));
    $state['attempts'][] = $now;
    writeJsonAtomic(suggestion_rate_limit_path($key), $state);
}

function suggestion_rate_limit_key(array $user): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $identity = $user['type'] ?? 'unknown';
    if (($user['type'] ?? '') === 'contractor') {
        $identity .= '|' . ($user['yojId'] ?? ($user['username'] ?? 'unknown'));
    } elseif (($user['type'] ?? '') === 'department') {
        $identity .= '|' . ($user['fullUserId'] ?? ($user['username'] ?? 'unknown'));
    }
    return hash('sha256', $identity . '|' . $ip . '|' . $ua);
}

function suggestion_generate_id(): string
{
    $date = now_kolkata()->format('Ymd');
    $suffix = strtoupper(bin2hex(random_bytes(3)));
    return 'SUG-' . $date . '-' . $suffix;
}

function suggestion_storage_path(string $id): string
{
    return DATA_PATH . '/suggestions/' . $id . '.json';
}

function suggestion_validate(array $data): array
{
    $errors = [];
    $category = trim((string)($data['category'] ?? 'feature'));
    $allowedCategories = ['feature', 'bug', 'ui', 'performance', 'other'];
    if (!in_array($category, $allowedCategories, true)) {
        $errors[] = 'Please choose a valid category.';
    }

    $title = trim((string)($data['title'] ?? ''));
    if ($title === '' || strlen($title) < 5 || strlen($title) > 80) {
        $errors[] = 'Title must be between 5 and 80 characters.';
    }

    $message = trim((string)($data['message'] ?? ''));
    if ($message === '' || strlen($message) < 20 || strlen($message) > 2000) {
        $errors[] = 'Message must be between 20 and 2000 characters.';
    }

    return $errors;
}

function suggestion_sanitize_text(string $value, int $maxLength): string
{
    $clean = trim(strip_tags($value));
    if (strlen($clean) > $maxLength) {
        $clean = substr($clean, 0, $maxLength);
    }
    return $clean;
}

function suggestion_create_payload(array $user, array $data): array
{
    $category = suggestion_sanitize_text((string)($data['category'] ?? 'feature'), 32);
    $title = suggestion_sanitize_text((string)($data['title'] ?? ''), 80);
    $message = suggestion_sanitize_text((string)($data['message'] ?? ''), 2000);
    $pageUrl = suggestion_normalize_page_url((string)($data['pageUrl'] ?? ''));

    $role = ($user['type'] ?? '') === 'department' ? 'department' : 'contractor';

    return [
        'category' => $category,
        'title' => $title,
        'message' => $message,
        'pageUrl' => $pageUrl,
        'createdBy' => [
            'role' => $role,
            'yojId' => $user['yojId'] ?? null,
            'deptId' => $user['deptId'] ?? null,
            'userId' => $user['fullUserId'] ?? ($user['username'] ?? ''),
        ],
    ];
}

function suggestion_store(array $user, array $data, string $deviceHint): array
{
    ensure_suggestions_environment();

    $id = suggestion_generate_id();
    $path = suggestion_storage_path($id);
    while (file_exists($path)) {
        $id = suggestion_generate_id();
        $path = suggestion_storage_path($id);
    }

    $payload = suggestion_create_payload($user, $data);
    $now = now_kolkata()->format(DateTime::ATOM);

    $record = [
        'id' => $id,
        'createdAt' => $now,
        'createdBy' => $payload['createdBy'],
        'category' => $payload['category'],
        'title' => $payload['title'],
        'message' => $payload['message'],
        'pageUrl' => $payload['pageUrl'],
        'deviceHint' => $deviceHint,
        'status' => 'new',
    ];

    writeJsonAtomic($path, $record);

    suggestions_log_event('SUG_CREATE', [
        'id' => $id,
        'role' => $record['createdBy']['role'] ?? 'unknown',
    ]);

    return $record;
}

function suggestion_list(): array
{
    ensure_suggestions_environment();
    $files = glob(DATA_PATH . '/suggestions/SUG-*.json');
    if (!is_array($files)) {
        return [];
    }

    $items = [];
    foreach ($files as $file) {
        $data = readJson($file);
        if (!$data) {
            continue;
        }
        $items[] = $data;
    }

    usort($items, function (array $a, array $b): int {
        return strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? ''));
    });

    return $items;
}

function suggestion_find(string $id): ?array
{
    if (!preg_match('/^SUG-\d{8}-[A-Z0-9]{6}$/', $id)) {
        return null;
    }
    $path = suggestion_storage_path($id);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function suggestion_update_status(string $id, string $status): ?array
{
    $allowed = ['new', 'reviewed', 'planned', 'done'];
    if (!in_array($status, $allowed, true)) {
        return null;
    }
    $record = suggestion_find($id);
    if (!$record) {
        return null;
    }
    $record['status'] = $status;
    writeJsonAtomic(suggestion_storage_path($id), $record);

    suggestions_log_event('SUG_STATUS', [
        'id' => $id,
        'status' => $status,
    ]);

    return $record;
}

function suggestions_filter(array $items, string $roleFilter, string $statusFilter): array
{
    return array_values(array_filter($items, function (array $item) use ($roleFilter, $statusFilter): bool {
        if ($roleFilter !== '' && ($item['createdBy']['role'] ?? '') !== $roleFilter) {
            return false;
        }
        if ($statusFilter !== '' && ($item['status'] ?? '') !== $statusFilter) {
            return false;
        }
        return true;
    }));
}
