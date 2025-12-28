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
        'address' => '',
        'district' => '',
        'linkedDepartments' => [],
        'createdAt' => $pending['createdAt'] ?? $now,
        'approvedAt' => $now,
        'lastLoginAt' => null,
    ];

    writeJsonAtomic($basePath . '/contractor.json', $contractor);
    writeJsonAtomic(contractors_vault_index_path($yojId), []);

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

    return $data;
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
    return is_array($index) ? $index : [];
}

function save_contractor_vault_index(string $yojId, array $records): void
{
    writeJsonAtomic(contractors_vault_index_path($yojId), array_values($records));
}

function generate_vault_file_id(): string
{
    return 'VF-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 6));
}

function allowed_vault_mimes(): array
{
    return [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
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
