<?php
declare(strict_types=1);

function ensure_departments_root(): void
{
    $base = DATA_PATH . '/departments';
    $directories = [
        $base,
        $base . '/indexes',
        DATA_PATH . '/logs',
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $indexPath = departments_index_path();
    if (!file_exists($indexPath)) {
        writeJsonAtomic($indexPath, []);
    }

    $logPath = DATA_PATH . '/logs/departments.log';
    if (!file_exists($logPath)) {
        touch($logPath);
    }
}

function departments_index_path(): string
{
    return DATA_PATH . '/departments/index.json';
}

function departments_index(): array
{
    $index = readJson(departments_index_path());
    return array_values(is_array($index) ? $index : []);
}

function save_departments_index(array $entries): void
{
    writeJsonAtomic(departments_index_path(), array_values($entries));
}

function normalize_dept_id(string $deptId): string
{
    return strtolower(trim($deptId));
}

function is_valid_dept_id(string $deptId): bool
{
    return (bool)preg_match('/^[a-z0-9]{3,10}$/', $deptId);
}

function is_valid_admin_short_id(string $shortId): bool
{
    return (bool)preg_match('/^[a-z0-9]{3,12}$/', $shortId);
}

function department_path(string $deptId): string
{
    return DATA_PATH . '/departments/' . normalize_dept_id($deptId);
}

function department_json_path(string $deptId): string
{
    return department_path($deptId) . '/department.json';
}

function department_users_path(string $deptId, bool $archived = false): string
{
    $suffix = $archived ? 'archived' : 'active';
    return department_path($deptId) . '/users/' . $suffix;
}

function department_user_path(string $deptId, string $fullUserId, bool $archived = false): string
{
    return department_users_path($deptId, $archived) . '/' . $fullUserId . '.json';
}

function department_roles_path(string $deptId): string
{
    return department_path($deptId) . '/rbac/roles.json';
}

function load_department(string $deptId): ?array
{
    $path = department_json_path($deptId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function save_department(array $department): void
{
    if (empty($department['deptId'])) {
        throw new InvalidArgumentException('Missing department id.');
    }
    $path = department_json_path($department['deptId']);
    writeJsonAtomic($path, $department);
}

function department_exists(string $deptId): bool
{
    return is_dir(department_path($deptId));
}

function create_department_record(string $deptId, string $nameEn, string $nameHi, string $address = '', string $contactEmail = ''): array
{
    $deptId = normalize_dept_id($deptId);
    $now = now_kolkata()->format(DateTime::ATOM);
    $department = [
        'deptId' => $deptId,
        'nameEn' => $nameEn,
        'nameHi' => $nameHi,
        'address' => $address,
        'contactEmail' => $contactEmail,
        'createdAt' => $now,
        'updatedAt' => $now,
        'status' => 'active',
        'activeAdminUserId' => null,
        'adminHistory' => [],
    ];

    $base = department_path($deptId);
    $directories = [
        $base,
        $base . '/users/active',
        $base . '/users/archived',
        $base . '/rbac',
    ];
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    save_department($department);

    $roles = [
        [
            'roleId' => 'admin',
            'name' => 'Department Admin',
            'permissions' => ['*'],
            'createdAt' => $now,
        ],
    ];
    writeJsonAtomic(department_roles_path($deptId), $roles);

    $index = departments_index();
    $index[] = [
        'deptId' => $deptId,
        'nameEn' => $nameEn,
        'nameHi' => $nameHi,
        'status' => 'active',
        'createdAt' => $now,
    ];
    save_departments_index($index);

    logEvent(DATA_PATH . '/logs/departments.log', [
        'event' => 'department_created',
        'deptId' => $deptId,
        'nameEn' => $nameEn,
        'nameHi' => $nameHi,
    ]);

    return $department;
}

function load_active_department_user(string $fullUserId): ?array
{
    $parsed = parse_department_login_identifier($fullUserId);
    if (!$parsed) {
        return null;
    }
    $path = department_user_path($parsed['deptId'], $fullUserId, false);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function parse_department_login_identifier(string $identifier): ?array
{
    $normalized = strtolower(trim($identifier));
    if (!preg_match('/^([a-z0-9]{3,12})\.([a-z0-9]{3,20})\.([a-z0-9]{3,10})$/', $normalized, $matches)) {
        return null;
    }
    return [
        'userShortId' => $matches[1],
        'roleId' => $matches[2],
        'deptId' => $matches[3],
        'fullUserId' => $normalized,
    ];
}

function archive_department_admin(string $deptId, string $fullUserId): void
{
    $existing = load_active_department_user($fullUserId);
    if (!$existing) {
        return;
    }
    $existing['status'] = 'archived';
    $existing['archivedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(department_user_path($deptId, $fullUserId, true), $existing);
    $activePath = department_user_path($deptId, $fullUserId, false);
    if (file_exists($activePath)) {
        unlink($activePath);
    }
}

function create_department_admin(string $deptId, string $adminShortId, string $password, string $displayName): array
{
    $deptId = normalize_dept_id($deptId);
    if (!is_valid_dept_id($deptId)) {
        throw new InvalidArgumentException('Invalid department.');
    }

    $department = load_department($deptId);
    if (!$department) {
        throw new RuntimeException('Department not found.');
    }

    $fullUserId = strtolower($adminShortId . '.admin.' . $deptId);
    $now = now_kolkata()->format(DateTime::ATOM);

    if (!empty($department['activeAdminUserId'])) {
        $previousId = $department['activeAdminUserId'];
        archive_department_admin($deptId, $previousId);
        $department['adminHistory'][] = $previousId;
    }

    $user = [
        'type' => 'department',
        'deptId' => $deptId,
        'userShortId' => $adminShortId,
        'roleId' => 'admin',
        'fullUserId' => $fullUserId,
        'displayName' => $displayName,
        'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
        'status' => 'active',
        'createdAt' => $now,
        'lastLoginAt' => null,
        'mustResetPassword' => true,
    ];

    writeJsonAtomic(department_user_path($deptId, $fullUserId, false), $user);

    $department['activeAdminUserId'] = $fullUserId;
    $department['updatedAt'] = $now;
    save_department($department);

    logEvent(DATA_PATH . '/logs/departments.log', [
        'event' => 'department_admin_created',
        'deptId' => $deptId,
        'fullUserId' => $fullUserId,
    ]);

    return $user;
}

function update_department_user_password(string $deptId, string $fullUserId, string $newPassword): void
{
    $record = load_active_department_user($fullUserId);
    if (!$record || $record['deptId'] !== $deptId) {
        throw new RuntimeException('User not found for password update.');
    }

    $record['passwordHash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $record['mustResetPassword'] = false;
    $record['updatedAt'] = now_kolkata()->format(DateTime::ATOM);

    writeJsonAtomic(department_user_path($deptId, $fullUserId, false), $record);
    $_SESSION['user']['mustResetPassword'] = false;
}

function update_department_last_login(string $fullUserId): void
{
    $record = load_active_department_user($fullUserId);
    if (!$record) {
        return;
    }
    $record['lastLoginAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(department_user_path($record['deptId'], $fullUserId, false), $record);
}
