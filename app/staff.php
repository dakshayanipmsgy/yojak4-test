<?php
declare(strict_types=1);

function ensure_staff_environment(): void
{
    $paths = [
        DATA_PATH . '/staff',
        DATA_PATH . '/staff/employees',
        DATA_PATH . '/security/password_resets',
        DATA_PATH . '/backups',
        DATA_PATH . '/logs',
    ];

    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }

    $indexPath = staff_employee_index_path();
    if (!file_exists($indexPath)) {
        writeJsonAtomic($indexPath, []);
    }

    $resetIndex = DATA_PATH . '/security/password_resets/index.json';
    if (!file_exists($resetIndex)) {
        writeJsonAtomic($resetIndex, []);
    }

    $logFiles = [
        DATA_PATH . '/logs/superadmin.log',
        DATA_PATH . '/logs/backup.log',
        DATA_PATH . '/logs/reset.log',
    ];
    foreach ($logFiles as $logFile) {
        if (!file_exists($logFile)) {
            touch($logFile);
        }
    }
}

function staff_employee_index_path(): string
{
    return DATA_PATH . '/staff/employees/index.json';
}

function staff_employee_path(string $empId): string
{
    return DATA_PATH . '/staff/employees/' . $empId . '.json';
}

function staff_employee_index(): array
{
    ensure_staff_environment();
    $records = readJson(staff_employee_index_path());
    return is_array($records) ? array_values($records) : [];
}

function save_staff_employee_index(array $records): void
{
    ensure_staff_environment();
    writeJsonAtomic(staff_employee_index_path(), array_values($records));
}

function generate_employee_id(): string
{
    ensure_staff_environment();
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $candidate = 'EMP-' . $suffix;
    } while (file_exists(staff_employee_path($candidate)));

    return $candidate;
}

function employee_default_permissions(string $role): array
{
    return match ($role) {
        'support' => ['tickets', 'stats_view', 'can_process_assisted'],
        'content' => ['content_tools', 'stats_view', 'can_process_assisted'],
        'approvals' => ['reset_approvals', 'stats_view', 'can_process_assisted'],
        'auditor' => ['audit_view', 'stats_view'],
        default => [],
    };
}

function employee_permission_catalog(): array
{
    return [
        'tickets' => 'Support Tickets',
        'content_tools' => 'Content Tools',
        'reset_approvals' => 'Password Reset Approvals',
        'audit_view' => 'Audit Logs (metadata)',
        'stats_view' => 'Platform Stats',
        'can_process_assisted' => 'Assisted Pack Processing',
        'staff_guide_editor' => 'Staff Guide Editor',
        'scheme_builder_advanced' => 'Scheme Builder Advanced JSON Apply',
        'templates_manage' => 'Templates Library Management',
        'pack_blueprints_manage' => 'Pack Blueprints Management',
        'requests_manage' => 'Template/Pack Requests Queue',
    ];
}

function normalize_employee_username(string $username): string
{
    return normalize_login_identifier($username);
}

function create_employee(string $username, string $password, string $role, array $permissions = []): array
{
    ensure_staff_environment();
    $normalized = normalize_employee_username($username);
    foreach (staff_employee_index() as $record) {
        if (normalize_employee_username($record['username'] ?? '') === $normalized) {
            throw new RuntimeException('Username already exists.');
        }
    }

    $empId = generate_employee_id();
    $now = now_kolkata()->format(DateTime::ATOM);
    $perms = $permissions ?: employee_default_permissions($role);
    $employee = [
        'empId' => $empId,
        'type' => 'employee',
        'username' => $normalized,
        'displayName' => $username,
        'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
        'permissions' => array_values(array_unique($perms)),
        'status' => 'active',
        'createdAt' => $now,
        'updatedAt' => $now,
        'lastLoginAt' => null,
        'mustResetPassword' => false,
    ];

    writeJsonAtomic(staff_employee_path($empId), $employee);

    $index = staff_employee_index();
    $index[] = [
        'empId' => $empId,
        'username' => $normalized,
        'role' => $role,
        'status' => 'active',
        'createdAt' => $now,
    ];
    save_staff_employee_index($index);

    logEvent(DATA_PATH . '/logs/superadmin.log', [
        'event' => 'employee_created',
        'empId' => $empId,
        'username' => $normalized,
        'role' => $role,
    ]);

    return $employee;
}

function load_employee(string $empId): ?array
{
    ensure_staff_environment();
    $path = staff_employee_path($empId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function find_employee_by_username(string $username): ?array
{
    $normalized = normalize_employee_username($username);
    foreach (staff_employee_index() as $record) {
        if (normalize_employee_username($record['username'] ?? '') === $normalized) {
            return load_employee($record['empId'] ?? '');
        }
    }
    return null;
}

function save_employee(array $employee): void
{
    ensure_staff_environment();
    if (empty($employee['empId'])) {
        throw new InvalidArgumentException('Missing employee id.');
    }
    $employee['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(staff_employee_path($employee['empId']), $employee);

    $index = staff_employee_index();
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['empId'] ?? '') === $employee['empId']) {
            $entry['username'] = $employee['username'] ?? ($entry['username'] ?? '');
            $entry['role'] = $employee['role'] ?? ($entry['role'] ?? '');
            $entry['status'] = $employee['status'] ?? ($entry['status'] ?? '');
            $entry['createdAt'] = $entry['createdAt'] ?? ($employee['createdAt'] ?? now_kolkata()->format(DateTime::ATOM));
            $found = true;
            break;
        }
    }
    unset($entry);

    if (!$found) {
        $index[] = [
            'empId' => $employee['empId'],
            'username' => $employee['username'] ?? '',
            'role' => $employee['role'] ?? '',
            'status' => $employee['status'] ?? '',
            'createdAt' => $employee['createdAt'] ?? now_kolkata()->format(DateTime::ATOM),
        ];
    }

    save_staff_employee_index($index);
}

function update_employee_status(string $empId, string $status): bool
{
    $employee = load_employee($empId);
    if (!$employee) {
        return false;
    }
    $employee['status'] = $status;
    save_employee($employee);
    logEvent(DATA_PATH . '/logs/superadmin.log', [
        'event' => 'employee_status_changed',
        'empId' => $empId,
        'status' => $status,
    ]);
    return true;
}

function update_employee_role_permissions(string $empId, string $role, array $permissions): bool
{
    $employee = load_employee($empId);
    if (!$employee) {
        return false;
    }
    $employee['role'] = $role;
    $employee['permissions'] = array_values(array_unique($permissions ?: employee_default_permissions($role)));
    save_employee($employee);
    logEvent(DATA_PATH . '/logs/superadmin.log', [
        'event' => 'employee_role_changed',
        'empId' => $empId,
        'role' => $role,
        'permissions' => $employee['permissions'],
    ]);
    return true;
}

function update_employee_last_login(string $empId): void
{
    $employee = load_employee($empId);
    if (!$employee) {
        return;
    }
    $employee['lastLoginAt'] = now_kolkata()->format(DateTime::ATOM);
    save_employee($employee);
}

function employee_has_permission(array $employee, string $permission): bool
{
    if (($employee['status'] ?? '') !== 'active') {
        return false;
    }
    $perms = $employee['permissions'] ?? [];
    return in_array('*', $perms, true) || in_array($permission, $perms, true);
}

function current_employee_record(): ?array
{
    $session = current_user();
    if (!$session || ($session['type'] ?? '') !== 'employee') {
        return null;
    }
    $empId = $session['empId'] ?? '';
    if (!$empId) {
        return null;
    }
    $record = load_employee($empId);
    if (!$record || ($record['status'] ?? '') !== 'active') {
        logout_user();
        return null;
    }
    return $record;
}

function require_active_employee(): array
{
    $record = current_employee_record();
    if (!$record) {
        redirect('/auth/login.php');
    }
    return $record;
}

function require_superadmin_or_permission(string $permission): array
{
    $user = current_user();
    if ($user && ($user['type'] ?? '') === 'superadmin') {
        return $user;
    }

    $employee = current_employee_record();
    if ($employee && employee_has_permission($employee, $permission)) {
        return $employee;
    }

    redirect('/auth/login.php');
}

function list_backups(): array
{
    ensure_staff_environment();
    $files = glob(DATA_PATH . '/backups/backup_*.zip') ?: [];
    $records = [];
    foreach ($files as $file) {
        $records[] = [
            'filename' => basename($file),
            'size' => filesize($file),
            'createdAt' => (new DateTimeImmutable('@' . filemtime($file)))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format(DateTime::ATOM),
            'path' => $file,
        ];
    }
    usort($records, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
    return $records;
}

function create_backup_archive(string $actor): array
{
    ensure_staff_environment();
    $timestamp = now_kolkata()->format('Ymd_His');
    $filename = 'backup_' . $timestamp . '.zip';
    $targetPath = DATA_PATH . '/backups/' . $filename;

    logEvent(DATA_PATH . '/logs/backup.log', [
        'event' => 'backup_started',
        'file' => $filename,
        'actor' => $actor,
    ]);

    $zip = new ZipArchive();
    if ($zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        logEvent(DATA_PATH . '/logs/backup.log', [
            'event' => 'backup_failed',
            'file' => $filename,
            'actor' => $actor,
            'error' => 'unable_to_open_zip',
        ]);
        throw new RuntimeException('Unable to create backup archive.');
    }

    $sourceRoot = realpath(DATA_PATH);
    if ($sourceRoot === false) {
        throw new RuntimeException('Data directory missing.');
    }
    $backupRoot = realpath(DATA_PATH . '/backups') ?: DATA_PATH . '/backups';

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        $filePath = $file->getRealPath();
        if ($filePath === false) {
            continue;
        }
        if (str_starts_with($filePath, $backupRoot)) {
            continue;
        }
        $relativePath = ltrim(str_replace($sourceRoot, '', $filePath), DIRECTORY_SEPARATOR);
        if ($file->isDir()) {
            $zip->addEmptyDir('data/' . $relativePath);
        } else {
            $zip->addFile($filePath, 'data/' . $relativePath);
        }
    }

    $zip->close();
    $size = file_exists($targetPath) ? filesize($targetPath) : 0;

    logEvent(DATA_PATH . '/logs/backup.log', [
        'event' => 'backup_completed',
        'file' => $filename,
        'actor' => $actor,
        'size' => $size,
    ]);

    return [
        'filename' => $filename,
        'path' => $targetPath,
        'size' => $size,
    ];
}

function delete_path_recursive(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    $items = scandir($path);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        delete_path_recursive($path . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($path);
}

function perform_factory_reset(string $actor): void
{
    ensure_staff_environment();
    logEvent(DATA_PATH . '/logs/superadmin.log', [
        'event' => 'factory_reset_started',
        'actor' => $actor,
    ]);

    $items = scandir(DATA_PATH);
    if ($items === false) {
        throw new RuntimeException('Unable to read data directory.');
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === 'config') {
            continue;
        }
        delete_path_recursive(DATA_PATH . '/' . $item);
    }

    ensure_data_structure();

    logEvent(DATA_PATH . '/logs/superadmin.log', [
        'event' => 'factory_reset_completed',
        'actor' => $actor,
    ]);

    logEvent(DATA_PATH . '/logs/reset.log', [
        'event' => 'factory_reset',
        'actor' => $actor,
        'at' => now_kolkata()->format(DateTime::ATOM),
    ]);
}
