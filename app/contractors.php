<?php
declare(strict_types=1);

function ensure_contractors_root(): void
{
    $base = DATA_PATH . '/contractors';
    $directories = [
        $base,
        $base . '/pending',
        $base . '/approved',
        $base . '/rejected',
        DATA_PATH . '/logs',
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $indexPath = contractors_index_path();
    if (!file_exists($indexPath)) {
        writeJsonAtomic($indexPath, []);
    }

    $contractorLog = DATA_PATH . '/logs/contractors.log';
    if (!file_exists($contractorLog)) {
        touch($contractorLog);
    }

    $uploadLog = DATA_PATH . '/logs/uploads.log';
    if (!file_exists($uploadLog)) {
        touch($uploadLog);
    }

    $profileMemoryLog = DATA_PATH . '/logs/profile_memory.log';
    if (!file_exists($profileMemoryLog)) {
        touch($profileMemoryLog);
    }
}

function contractors_index_path(): string
{
    return DATA_PATH . '/contractors/index.json';
}

function contractors_index(): array
{
    $index = readJson(contractors_index_path());
    return is_array($index) ? array_values($index) : [];
}

function contractor_profile_defaults(): array
{
    return [
        'yojId' => null,
        'mobile' => null,
        'officePhone' => null,
        'residencePhone' => null,
        'fax' => null,
        'firmName' => null,
        'firmType' => null,
        'addressLine1' => null,
        'addressLine2' => null,
        'district' => null,
        'state' => null,
        'pincode' => null,
        'authorizedSignatoryName' => null,
        'authorizedSignatoryDesignation' => null,
        'email' => null,
        'gstNumber' => null,
        'panNumber' => null,
        'bankName' => null,
        'bankBranch' => null,
        'bankAccount' => null,
        'ifsc' => null,
        'placeDefault' => null,
        'updatedAt' => null,
    ];
}

function contractor_profile_memory_path(string $yojId): string
{
    return contractors_approved_path($yojId) . '/profile_memory.json';
}

function profile_memory_defaults(): array
{
    return [
        'version' => 1,
        'fields' => [],
        'lastUpdatedAt' => null,
    ];
}

function profile_memory_label_from_key(string $key): string
{
    $clean = preg_replace('/^custom\./', '', $key);
    $clean = str_replace(['.', '_'], ' ', $clean);
    return ucwords(trim($clean));
}

function profile_memory_max_length(string $type): int
{
    return $type === 'textarea' ? 2000 : 500;
}

function profile_memory_limit_value(string $value, int $max): string
{
    $value = trim(strip_tags($value));
    if ($max > 0 && function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }
    if ($max > 0) {
        return substr($value, 0, $max);
    }
    return $value;
}

function profile_memory_value_contains_pricing(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    $hasCurrency = (bool)preg_match('/(₹|rs\.?|inr)/i', $value);
    $hasDigits = (bool)preg_match('/\d/', $value);
    $hasKeyword = (bool)preg_match('/\b(rate|boq|amount|price|pricing)\b/i', $value);
    return $hasCurrency && $hasDigits && $hasKeyword;
}

function profile_memory_is_eligible_key(string $key, string $value): bool
{
    $normalized = pack_normalize_placeholder_key($key);
    if ($normalized === '') {
        return false;
    }
    if (preg_match('/^(tender|meta|dates|fees)\./', $normalized)) {
        return false;
    }
    if (preg_match('/^table\..+\.(rate|amount)$/', $normalized)) {
        return false;
    }
    $allowedPrefix = (bool)preg_match('/^(firm|tax|contact|bank|signatory)\./', $normalized)
        || str_starts_with($normalized, 'custom.');
    if (!$allowedPrefix) {
        return false;
    }
    if (profile_memory_value_contains_pricing($value)) {
        return false;
    }
    return true;
}

function load_profile_memory(string $yojId): array
{
    static $cache = [];
    if (isset($cache[$yojId])) {
        return $cache[$yojId];
    }
    $path = contractor_profile_memory_path($yojId);
    $data = readJson($path);
    if (!is_array($data)) {
        $data = [];
    }
    $memory = array_merge(profile_memory_defaults(), $data);
    if (!is_array($memory['fields'] ?? null)) {
        $memory['fields'] = [];
    }
    $cache[$yojId] = $memory;
    return $memory;
}

function save_profile_memory(string $yojId, array $memory): void
{
    writeJsonAtomic(contractor_profile_memory_path($yojId), $memory);
}

function profile_memory_upsert_entries(string $yojId, array $entries, string $source): int
{
    if (!$entries) {
        return 0;
    }
    $memory = load_profile_memory($yojId);
    $fields = is_array($memory['fields'] ?? null) ? $memory['fields'] : [];
    $now = now_kolkata()->format(DateTime::ATOM);
    $updated = 0;

    foreach ($entries as $key => $entry) {
        $normalized = pack_normalize_placeholder_key((string)$key);
        if ($normalized === '') {
            continue;
        }
        $rawValue = (string)($entry['value'] ?? '');
        $type = (string)($entry['type'] ?? 'text');
        $type = $type === 'textarea' ? 'textarea' : ($type === 'number' ? 'number' : ($type === 'choice' ? 'choice' : 'text'));
        $value = profile_memory_limit_value($rawValue, profile_memory_max_length($type));
        if ($value === '') {
            continue;
        }
        if (!profile_memory_is_eligible_key($normalized, $value)) {
            continue;
        }
        $label = trim((string)($entry['label'] ?? ''));
        if ($label === '') {
            $label = profile_memory_label_from_key($normalized);
        }
        $fields[$normalized] = [
            'label' => $label,
            'value' => $value,
            'type' => $type,
            'updatedAt' => $now,
            'source' => $source,
        ];
        $updated++;
        logEvent(DATA_PATH . '/logs/profile_memory.log', [
            'at' => $now,
            'yojId' => $yojId,
            'event' => 'MEMORY_UPSERT',
            'key' => $normalized,
        ]);
    }

    if ($updated > 0) {
        $memory['fields'] = $fields;
        $memory['lastUpdatedAt'] = $now;
        save_profile_memory($yojId, $memory);
    }

    return $updated;
}

function normalize_contractor_profile(array $contractor): array
{
    $defaults = contractor_profile_defaults();
    foreach ($defaults as $key => $default) {
        if (!array_key_exists($key, $contractor)) {
            $contractor[$key] = $default;
        }
    }

    if (empty($contractor['addressLine1']) && !empty($contractor['address'])) {
        $contractor['addressLine1'] = $contractor['address'];
    }

    if (!isset($contractor['name']) && !empty($contractor['authorizedSignatoryName'])) {
        $contractor['name'] = $contractor['authorizedSignatoryName'];
    }

    return $contractor;
}

function contractor_profile_address(array $contractor): string
{
    $parts = [];
    foreach (['addressLine1', 'addressLine2', 'district', 'state', 'pincode'] as $field) {
        $value = trim((string)($contractor[$field] ?? ''));
        if ($value !== '') {
            $parts[] = $value;
        }
    }

    if (!$parts && !empty($contractor['address'])) {
        $parts[] = $contractor['address'];
    }

    return trim(implode(', ', $parts));
}

function save_contractors_index(array $entries): void
{
    writeJsonAtomic(contractors_index_path(), array_values($entries));
}

function normalize_mobile(string $mobile): string
{
    $digits = preg_replace('/\D+/', '', $mobile);
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        $digits = substr($digits, -10);
    }
    return $digits;
}

function is_valid_mobile(string $mobile): bool
{
    $normalized = normalize_mobile($mobile);
    return (bool)preg_match('/^[6-9][0-9]{9}$/', $normalized);
}

function is_valid_yoj_id(string $yojId): bool
{
    return (bool)preg_match('/^YOJ-[A-Z0-9]{5}$/', strtoupper(trim($yojId)));
}

function contractors_pending_path(string $signupId): string
{
    return DATA_PATH . '/contractors/pending/' . $signupId . '.json';
}

function contractors_rejected_path(string $signupId): string
{
    return DATA_PATH . '/contractors/rejected/' . $signupId . '.json';
}

function contractors_approved_path(string $yojId): string
{
    return DATA_PATH . '/contractors/approved/' . $yojId;
}

function contractors_vault_index_path(string $yojId): string
{
    return contractors_approved_path($yojId) . '/vault/index.json';
}

function contractors_vault_files_path(string $yojId): string
{
    return contractors_approved_path($yojId) . '/vault/files';
}

function generate_signup_id(): string
{
    return 'csg-' . bin2hex(random_bytes(6));
}

function generate_yoj_id(): string
{
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
        $candidate = 'YOJ-' . $suffix;
    } while (is_dir(contractors_approved_path($candidate)));

    return $candidate;
}

function create_pending_contractor(string $mobile, string $password, string $name): array
{
    $signupId = generate_signup_id();
    $record = [
        'signupId' => $signupId,
        'mobile' => normalize_mobile($mobile),
        'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
        'name' => $name,
        'createdAt' => now_kolkata()->format(DateTime::ATOM),
        'status' => 'pending',
    ];

    writeJsonAtomic(contractors_pending_path($signupId), $record);

    logEvent(DATA_PATH . '/logs/contractors.log', [
        'event' => 'contractor_signup_pending',
        'mobile' => $record['mobile'],
        'signupId' => $signupId,
    ]);

    return $record;
}

function list_pending_contractors(): array
{
    $files = glob(DATA_PATH . '/contractors/pending/*.json') ?: [];
    $records = [];
    foreach ($files as $file) {
        $data = readJson($file);
        if (!empty($data)) {
            $records[] = $data;
        }
    }
    usort($records, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
    return $records;
}

function list_rejected_contractors(): array
{
    $files = glob(DATA_PATH . '/contractors/rejected/*.json') ?: [];
    $records = [];
    foreach ($files as $file) {
        $data = readJson($file);
        if (!empty($data)) {
            $records[] = $data;
        }
    }
    usort($records, fn($a, $b) => strcmp($b['rejectedAt'] ?? '', $a['rejectedAt'] ?? ''));
    return $records;
}

function load_pending_contractor(string $signupId): ?array
{
    $path = contractors_pending_path($signupId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function mobile_exists_in_pending(string $mobile): bool
{
    $normalized = normalize_mobile($mobile);
    foreach (list_pending_contractors() as $pending) {
        if (($pending['mobile'] ?? '') === $normalized && ($pending['status'] ?? '') === 'pending') {
            return true;
        }
    }
    return false;
}

function mobile_exists_in_approved(string $mobile): bool
{
    $normalized = normalize_mobile($mobile);
    foreach (contractors_index() as $record) {
        if (($record['mobile'] ?? '') === $normalized) {
            return true;
        }
    }
    return false;
}

function approve_pending_contractor(string $signupId, string $actor): ?array
{
    $pending = load_pending_contractor($signupId);
    if (!$pending || ($pending['status'] ?? '') !== 'pending') {
        return null;
    }

    $yojId = generate_yoj_id();
    $basePath = contractors_approved_path($yojId);
    $directories = [
        $basePath,
        $basePath . '/vault',
        $basePath . '/vault/files',
    ];
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $now = now_kolkata()->format(DateTime::ATOM);
    $contractor = [
        'type' => 'contractor',
        'yojId' => $yojId,
        'mobile' => $pending['mobile'],
        'passwordHash' => $pending['passwordHash'],
        'mustResetPassword' => false,
        'lastPasswordResetAt' => null,
        'passwordResetBy' => null,
        'status' => 'approved',
        'name' => $pending['name'] ?? '',
        'firmName' => '',
        'firmType' => '',
        'address' => '',
        'addressLine1' => '',
        'addressLine2' => '',
        'district' => '',
        'state' => '',
        'pincode' => '',
        'authorizedSignatoryName' => $pending['name'] ?? '',
        'authorizedSignatoryDesignation' => '',
        'email' => '',
        'gstNumber' => '',
        'panNumber' => '',
        'bankName' => '',
        'bankAccount' => '',
        'ifsc' => '',
        'placeDefault' => '',
        'linkedDepartments' => [],
        'createdAt' => $pending['createdAt'] ?? $now,
        'approvedAt' => $now,
        'lastLoginAt' => null,
    ];
    $contractor = normalize_contractor_profile($contractor);
    writeJsonAtomic($basePath . '/contractor.json', $contractor);
    writeJsonAtomic(contractors_vault_index_path($yojId), [
        'items' => [],
        'updatedAt' => $now,
    ]);

    $index = contractors_index();
    $index[] = [
        'yojId' => $yojId,
        'mobile' => $contractor['mobile'],
        'name' => $contractor['name'],
        'status' => $contractor['status'],
        'createdAt' => $contractor['createdAt'],
        'approvedAt' => $contractor['approvedAt'],
    ];
    save_contractors_index($index);

    if (file_exists(contractors_pending_path($signupId))) {
        unlink(contractors_pending_path($signupId));
    }

    logEvent(DATA_PATH . '/logs/contractors.log', [
        'event' => 'contractor_approved',
        'signupId' => $signupId,
        'yojId' => $yojId,
        'actor' => $actor,
    ]);

    return $contractor;
}

function reject_pending_contractor(string $signupId, string $actor, string $reason = ''): bool
{
    $pending = load_pending_contractor($signupId);
    if (!$pending || ($pending['status'] ?? '') !== 'pending') {
        return false;
    }

    $pending['status'] = 'rejected';
    $pending['rejectedAt'] = now_kolkata()->format(DateTime::ATOM);
    $pending['rejectedBy'] = $actor;
    $pending['reason'] = $reason;

    writeJsonAtomic(contractors_rejected_path($signupId), $pending);
    if (file_exists(contractors_pending_path($signupId))) {
        unlink(contractors_pending_path($signupId));
    }

    logEvent(DATA_PATH . '/logs/contractors.log', [
        'event' => 'contractor_rejected',
        'signupId' => $signupId,
        'actor' => $actor,
        'reason' => $reason,
    ]);

    return true;
}

function load_contractor(string $yojId): ?array
{
    $path = contractors_approved_path($yojId) . '/contractor.json';
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    if (!$data) {
        return null;
    }

    if (!array_key_exists('mustResetPassword', $data)) {
        $data['mustResetPassword'] = false;
    }
    if (!array_key_exists('lastPasswordResetAt', $data)) {
        $data['lastPasswordResetAt'] = null;
    }
    if (!array_key_exists('passwordResetBy', $data)) {
        $data['passwordResetBy'] = null;
    }

    return normalize_contractor_profile($data);
}

function find_contractor_by_mobile(string $mobile): ?array
{
    $normalized = normalize_mobile($mobile);
    foreach (contractors_index() as $entry) {
        if (($entry['mobile'] ?? '') === $normalized && ($entry['status'] ?? '') === 'approved') {
            return load_contractor($entry['yojId']);
        }
    }
    return null;
}

function save_contractor(array $contractor): void
{
    if (empty($contractor['yojId'])) {
        throw new InvalidArgumentException('Missing contractor id.');
    }
    $contractor = normalize_contractor_profile($contractor);
    $contractor['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    if (!array_key_exists('mustResetPassword', $contractor)) {
        $contractor['mustResetPassword'] = false;
    }
    if (!array_key_exists('lastPasswordResetAt', $contractor)) {
        $contractor['lastPasswordResetAt'] = null;
    }
    if (!array_key_exists('passwordResetBy', $contractor)) {
        $contractor['passwordResetBy'] = null;
    }
    $path = contractors_approved_path($contractor['yojId']) . '/contractor.json';
    writeJsonAtomic($path, $contractor);

    $index = contractors_index();
    foreach ($index as &$entry) {
        if (($entry['yojId'] ?? '') === $contractor['yojId']) {
            $entry['name'] = $contractor['name'] ?? '';
            $entry['status'] = $contractor['status'] ?? $entry['status'];
            $entry['mobile'] = $contractor['mobile'] ?? $entry['mobile'];
            break;
        }
    }
    save_contractors_index($index);
}

function contractor_upload_dir(string $yojId): string
{
    return PUBLIC_PATH . '/uploads/contractors/' . $yojId . '/vault';
}

function contractor_links_dir(string $yojId): string
{
    return contractors_approved_path($yojId) . '/links';
}

function contractor_links_index_path(string $yojId): string
{
    return contractor_links_dir($yojId) . '/index.json';
}

function ensure_contractor_links_env(string $yojId): void
{
    $dir = contractor_links_dir($yojId);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!file_exists(contractor_links_index_path($yojId))) {
        writeJsonAtomic(contractor_links_index_path($yojId), []);
    }
}

function load_contractor_links(string $yojId): array
{
    ensure_contractor_links_env($yojId);
    $links = readJson(contractor_links_index_path($yojId));
    return is_array($links) ? array_values($links) : [];
}

function save_contractor_links(string $yojId, array $links): void
{
    ensure_contractor_links_env($yojId);
    writeJsonAtomic(contractor_links_index_path($yojId), array_values($links));
}

function load_contractor_link(string $yojId, string $deptId): ?array
{
    ensure_contractor_links_env($yojId);
    $path = contractor_links_dir($yojId) . '/' . $deptId . '.json';
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function save_contractor_link_file(string $yojId, array $link): void
{
    ensure_contractor_links_env($yojId);
    $path = contractor_links_dir($yojId) . '/' . ($link['deptId'] ?? '') . '.json';
    writeJsonAtomic($path, $link);
}

function contractor_notifications_dir(string $yojId): string
{
    return contractors_approved_path($yojId) . '/notifications';
}

function contractor_notifications_index_path(string $yojId): string
{
    return contractor_notifications_dir($yojId) . '/index.json';
}

function ensure_contractor_notifications_env(string $yojId): void
{
    $dir = contractor_notifications_dir($yojId);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!file_exists(contractor_notifications_index_path($yojId))) {
        writeJsonAtomic(contractor_notifications_index_path($yojId), []);
    }
}

function contractor_notifications_index(string $yojId): array
{
    ensure_contractor_notifications_env($yojId);
    $data = readJson(contractor_notifications_index_path($yojId));
    return is_array($data) ? array_values($data) : [];
}

function save_contractor_notifications_index(string $yojId, array $records): void
{
    ensure_contractor_notifications_env($yojId);
    writeJsonAtomic(contractor_notifications_index_path($yojId), array_values($records));
}

function generate_notification_id(): string
{
    $date = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Ymd');
    $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    return 'N-' . $date . '-' . $suffix;
}

function create_contractor_notification(string $yojId, array $payload): ?array
{
    if (!is_valid_yoj_id($yojId) || !load_contractor($yojId)) {
        return null;
    }
    ensure_contractor_notifications_env($yojId);
    do {
        $notifId = generate_notification_id();
        $path = contractor_notifications_dir($yojId) . '/' . $notifId . '.json';
    } while (file_exists($path));

    $now = now_kolkata()->format(DateTime::ATOM);
    $notification = [
        'notifId' => $notifId,
        'type' => $payload['type'] ?? 'info',
        'title' => $payload['title'] ?? '',
        'message' => $payload['message'] ?? '',
        'deptId' => $payload['deptId'] ?? null,
        'createdAt' => $now,
        'readAt' => null,
    ];

    writeJsonAtomic($path, $notification);

    $index = contractor_notifications_index($yojId);
    $index[] = [
        'notifId' => $notifId,
        'type' => $notification['type'],
        'title' => $notification['title'],
        'deptId' => $notification['deptId'],
        'createdAt' => $notification['createdAt'],
        'readAt' => $notification['readAt'],
    ];
    save_contractor_notifications_index($yojId, $index);

    return $notification;
}

function ensure_contractor_upload_dir(string $yojId): void
{
    $dir = contractor_upload_dir($yojId);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function contractor_vault_index(string $yojId): array
{
    $path = contractors_vault_index_path($yojId);
    $index = readJson($path);
    if (!is_array($index)) {
        return [];
    }
    if (array_key_exists('items', $index) && is_array($index['items'])) {
        $index = $index['items'];
    }
    foreach ($index as &$record) {
        if (!is_array($record)) {
            $record = [];
            continue;
        }
        $record['docId'] = $record['docId'] ?? ($record['fileId'] ?? '');
        $record['docType'] = $record['docType'] ?? ($record['category'] ?? 'Other');
        if (!isset($record['tags']) || !is_array($record['tags'])) {
            $record['tags'] = [];
        }
        if (!isset($record['title']) || $record['title'] === '') {
            $record['title'] = $record['originalName'] ?? ($record['storedName'] ?? 'Untitled');
        }
        if (!isset($record['originalName']) || $record['originalName'] === '') {
            $record['originalName'] = $record['title'] ?? ($record['storedName'] ?? 'Untitled');
        }
        if (!isset($record['storedName']) || $record['storedName'] === '') {
            $path = (string)($record['storedPath'] ?? '');
            $record['storedName'] = $path !== '' ? basename($path) : ($record['originalName'] ?? ($record['fileId'] ?? ''));
        }
        if (!isset($record['sizeBytes']) && isset($record['size'])) {
            $record['sizeBytes'] = (int)$record['size'];
        }
    }
    unset($record);
    return $index;
}

function save_contractor_vault_index(string $yojId, array $records): void
{
    writeJsonAtomic(contractors_vault_index_path($yojId), [
        'items' => array_values($records),
        'updatedAt' => now_kolkata()->format(DateTime::ATOM),
    ]);
}

function generate_vault_file_id(): string
{
    $date = now_kolkata()->format('Ymd');
    return 'VLT-' . $date . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
}

function allowed_vault_mimes(): array
{
    return [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
}

function update_contractor_password(string $yojId, string $newPassword, string $resetBy = 'self'): bool
{
    $contractor = load_contractor($yojId);
    if (!$contractor) {
        return false;
    }

    $contractor['passwordHash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $contractor['mustResetPassword'] = false;
    $contractor['lastPasswordResetAt'] = now_kolkata()->format(DateTime::ATOM);
    $contractor['passwordResetBy'] = $resetBy;
    save_contractor($contractor);
    $_SESSION['user']['mustResetPassword'] = false;

    return true;
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

function vault_file_meta_path(string $yojId, string $fileId): string
{
    return contractors_vault_files_path($yojId) . '/' . $fileId . '/meta.json';
}

function ensure_vault_file_dir(string $yojId, string $fileId): string
{
    $dir = contractors_vault_files_path($yojId) . '/' . $fileId;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}
