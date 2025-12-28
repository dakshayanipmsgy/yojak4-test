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

function department_log_path(string $deptId): string
{
    return DATA_PATH . '/logs/department_' . normalize_dept_id($deptId) . '.log';
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

function department_reset_requests_path(string $deptId): string
{
    return department_path($deptId) . '/users/reset_requests.json';
}

function department_templates_path(string $deptId): string
{
    return department_path($deptId) . '/templates/dept';
}

function department_templates_cache_path(string $deptId): string
{
    return department_path($deptId) . '/templates/global_cache';
}

function department_generated_docs_path(string $deptId): string
{
    return department_path($deptId) . '/generated_docs';
}

function department_docs_path(string $deptId): string
{
    return department_path($deptId) . '/docs';
}

function department_tenders_path(string $deptId): string
{
    return department_path($deptId) . '/tenders';
}

function department_workorders_path(string $deptId): string
{
    return department_path($deptId) . '/workorders';
}

function department_requirements_path(string $deptId): string
{
    return department_path($deptId) . '/requirements';
}

function department_dak_path(string $deptId): string
{
    return department_path($deptId) . '/dak';
}

function department_audit_log_path(string $deptId): string
{
    return department_path($deptId) . '/audit/audit.log';
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

    ensure_department_env($deptId);

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

function resolve_department_admin_account(string $deptId): array
{
    $deptId = normalize_dept_id($deptId);
    $department = load_department($deptId);
    if (!$department) {
        return ['ok' => false, 'reason' => 'department_missing'];
    }

    $activeAdminUserId = strtolower((string)($department['activeAdminUserId'] ?? ''));
    if ($activeAdminUserId === '') {
        return ['ok' => false, 'reason' => 'active_admin_missing', 'department' => $department];
    }

    $parsed = parse_department_login_identifier($activeAdminUserId);
    if (!$parsed || ($parsed['roleId'] ?? '') !== 'admin' || ($parsed['deptId'] ?? '') !== $deptId) {
        return [
            'ok' => false,
            'reason' => 'active_admin_invalid',
            'department' => $department,
            'activeAdminUserId' => $activeAdminUserId,
        ];
    }

    $record = load_active_department_user($activeAdminUserId);
    if (!$record) {
        return [
            'ok' => false,
            'reason' => 'admin_record_missing',
            'department' => $department,
            'activeAdminUserId' => $activeAdminUserId,
        ];
    }
    if (($record['type'] ?? '') !== 'department' || ($record['roleId'] ?? '') !== 'admin') {
        return [
            'ok' => false,
            'reason' => 'admin_record_mismatch',
            'department' => $department,
            'activeAdminUserId' => $activeAdminUserId,
            'record' => $record,
        ];
    }

    return [
        'ok' => true,
        'department' => $department,
        'activeAdminUserId' => $activeAdminUserId,
        'record' => $record,
    ];
}

function parse_department_login_identifier(string $identifier): ?array
{
    $normalized = strtolower(trim($identifier));
    if (!preg_match('/^([a-z0-9]{3,12})\.([a-z0-9_]{2,20})\.([a-z0-9]{3,10})$/', $normalized, $matches)) {
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

function update_department_user_password(string $deptId, string $fullUserId, string $newPassword, bool $mustReset = false, ?string $resetBy = null): void
{
    $record = load_active_department_user($fullUserId);
    if (!$record || $record['deptId'] !== $deptId) {
        throw new RuntimeException('User not found for password update.');
    }

    $record['passwordHash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $record['mustResetPassword'] = $mustReset;
    $record['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    $record['lastPasswordResetAt'] = $record['updatedAt'];
    if ($resetBy !== null) {
        $record['passwordResetBy'] = $resetBy;
    }

    writeJsonAtomic(department_user_path($deptId, $fullUserId, false), $record);
    if (!$mustReset) {
        $_SESSION['user']['mustResetPassword'] = false;
    }
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

function ensure_department_env(string $deptId): void
{
    $base = department_path($deptId);
    $paths = [
        $base,
        $base . '/users/active',
        $base . '/users/archived',
        $base . '/rbac',
        department_templates_path($deptId),
        department_templates_cache_path($deptId),
        department_generated_docs_path($deptId),
        department_docs_path($deptId),
        department_tenders_path($deptId),
        department_workorders_path($deptId),
        department_requirements_path($deptId),
        department_dak_path($deptId),
        dirname(department_audit_log_path($deptId)),
    ];

    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }

    if (!file_exists(department_roles_path($deptId))) {
        $now = now_kolkata()->format(DateTime::ATOM);
        writeJsonAtomic(department_roles_path($deptId), [
            [
                'roleId' => 'admin',
                'nameEn' => 'Department Admin',
                'nameHi' => 'Department Admin',
                'permissions' => ['*'],
                'createdAt' => $now,
            ],
        ]);
    }

    if (!file_exists(department_requirements_path($deptId) . '/sets.json')) {
        writeJsonAtomic(department_requirements_path($deptId) . '/sets.json', []);
    }

    if (!file_exists(department_dak_path($deptId) . '/index.json')) {
        writeJsonAtomic(department_dak_path($deptId) . '/index.json', []);
    }

    $audit = department_audit_log_path($deptId);
    if (!file_exists($audit)) {
        touch($audit);
    }

    $deptLog = department_log_path($deptId);
    if (!file_exists($deptLog)) {
        touch($deptLog);
    }
}

function department_permission_keys(): array
{
    return [
        'manage_roles',
        'manage_users',
        'manage_templates',
        'generate_docs',
        'docs_workflow',
        'manage_tenders',
        'manage_workorders',
        'manage_requirements',
        'manage_dak',
        'run_health',
    ];
}

function sanitize_permissions(array $permissions): array
{
    $keys = department_permission_keys();
    $clean = [];
    foreach ($permissions as $permission) {
        $permission = trim((string)$permission);
        if ($permission === '*') {
            return ['*'];
        }
        if (in_array($permission, $keys, true) && !in_array($permission, $clean, true)) {
            $clean[] = $permission;
        }
    }
    return $clean;
}

function load_department_roles(string $deptId): array
{
    ensure_department_env($deptId);
    $roles = readJson(department_roles_path($deptId));
    return is_array($roles) ? array_values($roles) : [];
}

function save_department_roles(string $deptId, array $roles): void
{
    ensure_department_env($deptId);
    writeJsonAtomic(department_roles_path($deptId), array_values($roles));
}

function find_department_role(string $deptId, string $roleId): ?array
{
    $roles = load_department_roles($deptId);
    foreach ($roles as $role) {
        if (($role['roleId'] ?? '') === $roleId) {
            return $role;
        }
    }
    return null;
}

function require_department_permission(array $user, string $permission): void
{
    if (($user['type'] ?? '') !== 'department') {
        redirect('/department/login.php');
    }
    $deptId = $user['deptId'] ?? '';
    $roleId = $user['roleId'] ?? '';
    $role = find_department_role($deptId, $roleId);
    $perms = $role['permissions'] ?? [];
    if (in_array('*', $perms, true) || in_array($permission, $perms, true)) {
        return;
    }
    render_error_page('Unauthorized');
    exit;
}

function list_department_users(string $deptId, bool $archived = false): array
{
    ensure_department_env($deptId);
    $dir = department_users_path($deptId, $archived);
    $users = [];
    if (!is_dir($dir)) {
        return $users;
    }
    $files = scandir($dir) ?: [];
    foreach ($files as $file) {
        if (!str_ends_with((string)$file, '.json')) {
            continue;
        }
        $data = readJson($dir . '/' . $file);
        if ($data) {
            $users[] = $data;
        }
    }
    return $users;
}

function save_department_user(array $user, bool $archived = false): void
{
    if (empty($user['deptId'])) {
        throw new InvalidArgumentException('Missing department user details.');
    }
    $fullUserId = strtolower(trim((string)($user['fullUserId'] ?? '')));
    if ($fullUserId === '' && !empty($user['userShortId']) && !empty($user['roleId'])) {
        $fullUserId = strtolower(trim((string)$user['userShortId']) . '.' . trim((string)$user['roleId']) . '.' . normalize_dept_id((string)$user['deptId']));
    }
    $parsed = parse_department_login_identifier($fullUserId);
    if (!$parsed || $parsed['deptId'] !== normalize_dept_id((string)$user['deptId'])) {
        throw new InvalidArgumentException('Invalid department user identifier.');
    }

    $user['deptId'] = $parsed['deptId'];
    $user['userShortId'] = $parsed['userShortId'];
    $user['roleId'] = $parsed['roleId'];
    $user['fullUserId'] = $parsed['fullUserId'];

    ensure_department_env($parsed['deptId']);
    $path = department_user_path($parsed['deptId'], $parsed['fullUserId'], $archived);
    writeJsonAtomic($path, $user);
}

function suspend_department_user(string $deptId, string $fullUserId): bool
{
    $user = load_active_department_user($fullUserId);
    if (!$user || $user['deptId'] !== $deptId) {
        return false;
    }
    $user['status'] = 'suspended';
    $user['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    save_department_user($user, false);
    return true;
}

function archive_department_user(string $deptId, string $fullUserId): bool
{
    $user = load_active_department_user($fullUserId);
    if (!$user || $user['deptId'] !== $deptId) {
        return false;
    }
    $user['status'] = 'archived';
    $user['archivedAt'] = now_kolkata()->format(DateTime::ATOM);
    save_department_user($user, true);
    $activePath = department_user_path($deptId, $fullUserId, false);
    if (file_exists($activePath)) {
        unlink($activePath);
    }
    return true;
}

function department_template_path(string $deptId, string $templateId): string
{
    return department_templates_path($deptId) . '/' . $templateId . '.json';
}

function load_department_templates(string $deptId): array
{
    ensure_department_env($deptId);
    $dir = department_templates_path($deptId);
    $templates = [];
    $files = scandir($dir) ?: [];
    foreach ($files as $file) {
        if (!str_ends_with((string)$file, '.json')) {
            continue;
        }
        $data = readJson($dir . '/' . $file);
        if ($data) {
            $templates[] = $data;
        }
    }
    return $templates;
}

function load_global_templates_cache(string $deptId): array
{
    ensure_department_env($deptId);
    $dir = department_templates_cache_path($deptId);
    $templates = [];
    if (!is_dir($dir)) {
        return $templates;
    }
    $files = scandir($dir) ?: [];
    foreach ($files as $file) {
        if (!str_ends_with((string)$file, '.json')) {
            continue;
        }
        $data = readJson($dir . '/' . $file);
        if ($data) {
            $templates[] = $data;
        }
    }
    return $templates;
}

function department_template_placeholders(): array
{
    return [
        '{{deptName}}',
        '{{tenderId}}',
        '{{workorderId}}',
        '{{docDate}}',
        '{{userName}}',
        '{{docTitle}}',
    ];
}

function validate_template_placeholders(array $placeholders): array
{
    $allowed = department_template_placeholders();
    $valid = [];
    foreach ($placeholders as $ph) {
        $ph = trim((string)$ph);
        if (in_array($ph, $allowed, true) && !in_array($ph, $valid, true)) {
            $valid[] = $ph;
        }
    }
    return $valid;
}

function save_department_template(string $deptId, array $template): void
{
    if (empty($template['templateId'])) {
        throw new InvalidArgumentException('templateId required');
    }
    ensure_department_env($deptId);
    $template['placeholders'] = validate_template_placeholders($template['placeholders'] ?? []);
    $template['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(department_template_path($deptId, $template['templateId']), $template);
}

function sync_global_templates(string $deptId): void
{
    ensure_department_env($deptId);
    $globalDir = DATA_PATH . '/templates_global';
    if (!is_dir($globalDir)) {
        return;
    }
    $cacheDir = department_templates_cache_path($deptId);
    $files = scandir($globalDir) ?: [];
    foreach ($files as $file) {
        if (!str_ends_with((string)$file, '.json')) {
            continue;
        }
        $source = $globalDir . '/' . $file;
        $target = $cacheDir . '/' . $file;
        $data = readJson($source);
        if ($data) {
            writeJsonAtomic($target, $data);
        }
    }
}

function department_generated_doc_path(string $deptId, string $docId): string
{
    return department_generated_docs_path($deptId) . '/' . $docId . '.json';
}

function save_generated_doc(string $deptId, array $doc): void
{
    ensure_department_env($deptId);
    writeJsonAtomic(department_generated_doc_path($deptId, $doc['docId']), $doc);
}

function department_doc_path(string $deptId, string $docId): string
{
    return department_docs_path($deptId) . '/' . $docId . '/doc.json';
}

function list_department_docs(string $deptId): array
{
    ensure_department_env($deptId);
    $dir = department_docs_path($deptId);
    $docs = [];
    if (!is_dir($dir)) {
        return $docs;
    }
    $entries = scandir($dir) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry . '/doc.json';
        if (!file_exists($path)) {
            continue;
        }
        $doc = readJson($path);
        if ($doc) {
            $docs[] = $doc;
        }
    }
    usort($docs, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));
    return $docs;
}

function load_department_doc(string $deptId, string $docId): ?array
{
    $path = department_doc_path($deptId, $docId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function save_department_doc(string $deptId, array $doc): void
{
    if (empty($doc['docId'])) {
        throw new InvalidArgumentException('docId required');
    }
    ensure_department_env($deptId);
    $path = department_doc_path($deptId, $doc['docId']);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    writeJsonAtomic($path, $doc);
}

function append_department_audit(string $deptId, array $entry): void
{
    $entry['at'] = $entry['at'] ?? now_kolkata()->format(DateTime::ATOM);
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES);
    $path = department_audit_log_path($deptId);
    $handle = fopen($path, 'a');
    if ($handle) {
        flock($handle, LOCK_EX);
        fwrite($handle, $line . PHP_EOL);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function department_log(string $deptId, array $context): void
{
    logEvent(department_log_path($deptId), $context);
}

function department_tender_index_path(string $deptId): string
{
    return department_tenders_path($deptId) . '/index.json';
}

function department_workorder_index_path(string $deptId): string
{
    return department_workorders_path($deptId) . '/index.json';
}

function ensure_tender_index(string $deptId): void
{
    ensure_department_env($deptId);
    if (!file_exists(department_tender_index_path($deptId))) {
        writeJsonAtomic(department_tender_index_path($deptId), []);
    }
}

function ensure_workorder_index(string $deptId): void
{
    ensure_department_env($deptId);
    if (!file_exists(department_workorder_index_path($deptId))) {
        writeJsonAtomic(department_workorder_index_path($deptId), []);
    }
}

function load_department_tenders(string $deptId): array
{
    ensure_tender_index($deptId);
    $data = readJson(department_tender_index_path($deptId));
    return is_array($data) ? array_values($data) : [];
}

function save_department_tenders(string $deptId, array $records): void
{
    ensure_tender_index($deptId);
    writeJsonAtomic(department_tender_index_path($deptId), array_values($records));
}

function generate_ytd_id(string $deptId): string
{
    ensure_department_env($deptId);
    do {
        $suffix = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $candidate = 'YTD-' . $suffix;
    } while (file_exists(department_tenders_path($deptId) . '/' . $candidate . '/tender.json'));
    return $candidate;
}

function department_tender_path(string $deptId, string $tenderId): string
{
    return department_tenders_path($deptId) . '/' . $tenderId . '/tender.json';
}

function save_department_tender(string $deptId, array $tender): void
{
    ensure_department_env($deptId);
    ensure_tender_index($deptId);
    if (empty($tender['id'])) {
        $tender['id'] = generate_ytd_id($deptId);
    }
    $dir = dirname(department_tender_path($deptId, $tender['id']));
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $tender['updatedAt'] = $tender['updatedAt'] ?? now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(department_tender_path($deptId, $tender['id']), $tender);

    $index = load_department_tenders($deptId);
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['id'] ?? '') === $tender['id']) {
            $entry = array_merge($entry, [
                'title' => $tender['title'] ?? ($tender['id'] ?? ''),
                'status' => $tender['status'] ?? 'draft',
                'publishDate' => $tender['dates']['publish'] ?? null,
                'submissionDate' => $tender['dates']['submission'] ?? null,
                'openingDate' => $tender['dates']['opening'] ?? null,
                'updatedAt' => $tender['updatedAt'],
            ]);
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'id' => $tender['id'],
            'title' => $tender['title'] ?? $tender['id'],
            'status' => $tender['status'] ?? 'draft',
            'publishDate' => $tender['dates']['publish'] ?? null,
            'submissionDate' => $tender['dates']['submission'] ?? null,
            'openingDate' => $tender['dates']['opening'] ?? null,
            'updatedAt' => $tender['updatedAt'],
        ];
    }
    save_department_tenders($deptId, $index);
}

function load_department_tender(string $deptId, string $tenderId): ?array
{
    $path = department_tender_path($deptId, $tenderId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function department_workorder_path(string $deptId, string $woId): string
{
    return department_workorders_path($deptId) . '/' . $woId . '/workorder.json';
}

function generate_department_workorder_id(string $deptId): string
{
    ensure_department_env($deptId);
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = 'DWO-' . $suffix;
    } while (file_exists(department_workorder_path($deptId, $candidate)));
    return $candidate;
}

function save_department_workorder(string $deptId, array $workorder): void
{
    ensure_department_env($deptId);
    ensure_workorder_index($deptId);
    if (empty($workorder['woId'])) {
        $workorder['woId'] = generate_department_workorder_id($deptId);
    }
    $dir = dirname(department_workorder_path($deptId, $workorder['woId']));
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $workorder['updatedAt'] = $workorder['updatedAt'] ?? now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(department_workorder_path($deptId, $workorder['woId']), $workorder);

    $index = load_department_workorders($deptId);
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['woId'] ?? '') === $workorder['woId']) {
            $entry = array_merge($entry, [
                'title' => $workorder['title'] ?? $workorder['woId'],
                'tenderId' => $workorder['tenderId'] ?? null,
                'updatedAt' => $workorder['updatedAt'],
            ]);
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'woId' => $workorder['woId'],
            'title' => $workorder['title'] ?? $workorder['woId'],
            'tenderId' => $workorder['tenderId'] ?? null,
            'updatedAt' => $workorder['updatedAt'],
        ];
    }
    save_department_workorders($deptId, $index);
}

function load_department_workorders(string $deptId): array
{
    ensure_workorder_index($deptId);
    $data = readJson(department_workorder_index_path($deptId));
    return is_array($data) ? array_values($data) : [];
}

function load_department_workorder(string $deptId, string $woId): ?array
{
    $path = department_workorder_path($deptId, $woId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function load_requirement_sets(string $deptId): array
{
    ensure_department_env($deptId);
    $path = department_requirements_path($deptId) . '/sets.json';
    $sets = readJson($path);
    return is_array($sets) ? array_values($sets) : [];
}

function save_requirement_sets(string $deptId, array $sets): void
{
    ensure_department_env($deptId);
    $path = department_requirements_path($deptId) . '/sets.json';
    writeJsonAtomic($path, array_values($sets));
}

function create_requirement_set(string $deptId, string $title, array $items): array
{
    $sets = load_requirement_sets($deptId);
    $set = [
        'setId' => 'REQ-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
        'title' => $title,
        'items' => array_values(array_map('trim', $items)),
        'createdAt' => now_kolkata()->format(DateTime::ATOM),
        'updatedAt' => now_kolkata()->format(DateTime::ATOM),
    ];
    $sets[] = $set;
    save_requirement_sets($deptId, $sets);
    return $set;
}

function update_requirement_set(string $deptId, string $setId, string $title, array $items): bool
{
    $sets = load_requirement_sets($deptId);
    $found = false;
    foreach ($sets as &$set) {
        if (($set['setId'] ?? '') === $setId) {
            $set['title'] = $title;
            $set['items'] = array_values(array_map('trim', $items));
            $set['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
            $found = true;
            break;
        }
    }
    unset($set);
    if ($found) {
        save_requirement_sets($deptId, $sets);
    }
    return $found;
}

function load_dak_index(string $deptId): array
{
    ensure_department_env($deptId);
    $path = department_dak_path($deptId) . '/index.json';
    $items = readJson($path);
    return is_array($items) ? array_values($items) : [];
}

function save_dak_index(string $deptId, array $items): void
{
    ensure_department_env($deptId);
    $path = department_dak_path($deptId) . '/index.json';
    writeJsonAtomic($path, array_values($items));
}

function add_dak_item(string $deptId, string $fileRef, string $location): array
{
    $index = load_dak_index($deptId);
    $record = [
        'dakId' => 'DAK-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
        'fileRef' => $fileRef,
        'currentLocation' => $location,
        'movementHistory' => [
            [
                'at' => now_kolkata()->format(DateTime::ATOM),
                'by' => current_user()['username'] ?? 'system',
                'action' => 'created',
                'location' => $location,
            ],
        ],
    ];
    $index[] = $record;
    save_dak_index($deptId, $index);
    return $record;
}

function move_dak_item(string $deptId, string $dakId, string $location): bool
{
    $index = load_dak_index($deptId);
    $found = false;
    foreach ($index as &$item) {
        if (($item['dakId'] ?? '') === $dakId) {
            $item['currentLocation'] = $location;
            $item['movementHistory'][] = [
                'at' => now_kolkata()->format(DateTime::ATOM),
                'by' => current_user()['username'] ?? 'system',
                'action' => 'move',
                'location' => $location,
            ];
            $found = true;
            break;
        }
    }
    unset($item);
    if ($found) {
        save_dak_index($deptId, $index);
    }
    return $found;
}

function password_reset_index_path(): string
{
    return DATA_PATH . '/security/password_resets/index.json';
}

function ensure_password_reset_index(): void
{
    $dir = dirname(password_reset_index_path());
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!file_exists(password_reset_index_path())) {
        writeJsonAtomic(password_reset_index_path(), []);
    }
}

function load_all_password_reset_requests(): array
{
    ensure_password_reset_index();
    $requests = readJson(password_reset_index_path());
    $requests = is_array($requests) ? array_values($requests) : [];

    return array_map(function ($req) {
        $userType = $req['userType'] ?? 'dept_admin';
        $req['userType'] = $userType;
        if ($userType === 'dept_admin') {
            if (!isset($req['adminUserId']) && isset($req['fullUserId'])) {
                $req['adminUserId'] = $req['fullUserId'];
            }
        } else {
            if (isset($req['adminUserId'])) {
                unset($req['adminUserId']);
            }
        }
        if (!array_key_exists('updatedAt', $req)) {
            $req['updatedAt'] = $req['requestedAt'] ?? now_kolkata()->format(DateTime::ATOM);
        }
        return $req;
    }, $requests);
}

function save_all_password_reset_requests(array $requests): void
{
    ensure_password_reset_index();
    writeJsonAtomic(password_reset_index_path(), array_values($requests));
}

function find_password_reset_request(string $requestId): ?array
{
    foreach (load_all_password_reset_requests() as $request) {
        if (($request['requestId'] ?? '') === $requestId) {
            return $request;
        }
    }
    return null;
}

function load_password_reset_requests(string $deptId): array
{
    $requests = load_all_password_reset_requests();
    return array_values(array_filter($requests, fn($req) => ($req['deptId'] ?? '') === $deptId));
}

function add_password_reset_request(
    string $deptId,
    string $fullUserId,
    string $requestedBy,
    ?string $contact = null,
    ?string $message = null,
    ?string $requesterIp = null,
    ?string $userAgent = null,
    ?string $adminUserIdOverride = null
): array
{
    $deptId = normalize_dept_id($deptId);
    if (!is_valid_dept_id($deptId)) {
        throw new InvalidArgumentException('Invalid department id.');
    }
    $department = load_department($deptId);
    if (!$department) {
        throw new RuntimeException('Department not found.');
    }

    ensure_department_env($deptId);
    $requests = load_all_password_reset_requests();
    $normalizedUserId = strtolower($fullUserId);
    $parsedUser = parse_department_login_identifier($normalizedUserId);
    if (!$parsedUser || $parsedUser['deptId'] !== $deptId) {
        throw new InvalidArgumentException('Invalid department user identifier.');
    }
    $userType = ($parsedUser['roleId'] ?? '') === 'admin' ? 'dept_admin' : 'dept_user';
    $adminUserId = $userType === 'dept_admin' ? strtolower($adminUserIdOverride ?? $normalizedUserId) : null;
    $existing = array_values(array_filter(
        $requests,
        function ($req) use ($deptId, $userType, $adminUserId, $normalizedUserId) {
            if (($req['deptId'] ?? '') !== $deptId) {
                return false;
            }
            if (($req['status'] ?? '') !== 'pending') {
                return false;
            }
            if (($req['userType'] ?? 'dept_admin') !== $userType) {
                return false;
            }
            if ($userType === 'dept_admin') {
                return ($req['adminUserId'] ?? $req['fullUserId'] ?? '') === ($adminUserId ?? '')
                    || ($req['fullUserId'] ?? '') === $normalizedUserId;
            }
            return ($req['fullUserId'] ?? '') === $normalizedUserId;
        }
    ));
    if ($existing) {
        $existing[0]['contact'] = $existing[0]['contact'] ?? $contact;
        $existing[0]['message'] = $existing[0]['message'] ?? $message;
        $existing[0]['requesterIp'] = $existing[0]['requesterIp'] ?? $requesterIp;
        $existing[0]['requesterUaHash'] = $existing[0]['requesterUaHash'] ?? ($userAgent ? hash('sha256', $userAgent) : null);
        $existing[0]['userType'] = $userType;
        save_password_reset_request($existing[0]);
        return $existing[0];
    }
    $req = [
        'requestId' => 'RESET-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
        'deptId' => $deptId,
        'fullUserId' => $parsedUser['fullUserId'],
        'status' => 'pending',
        'requestedBy' => $requestedBy,
        'requestedAt' => now_kolkata()->format(DateTime::ATOM),
        'updatedAt' => now_kolkata()->format(DateTime::ATOM),
        'decidedAt' => null,
        'decidedBy' => null,
        'userType' => $userType,
        'contact' => $contact,
        'message' => $message,
        'requesterIp' => $requesterIp,
        'requesterUaHash' => $userAgent ? hash('sha256', $userAgent) : null,
        'tempPasswordHash' => null,
        'tempPasswordIssuedAt' => null,
        'tempPasswordDelivery' => 'show_once',
    ];
    if ($userType === 'dept_admin') {
        $req['adminUserId'] = $adminUserId;
    }
    $requests[] = $req;
    save_all_password_reset_requests($requests);
    return $req;
}

function update_password_reset_status(string $requestId, string $status, string $decidedBy): ?array
{
    $requests = load_all_password_reset_requests();
    $updated = null;
    foreach ($requests as &$req) {
        if (($req['requestId'] ?? '') === $requestId) {
            $req['status'] = $status;
            $req['decidedAt'] = now_kolkata()->format(DateTime::ATOM);
            $req['decidedBy'] = $decidedBy;
            $req['updatedAt'] = $req['decidedAt'];
            $updated = $req;
            break;
        }
    }
    unset($req);
    if ($updated) {
        save_all_password_reset_requests($requests);
    }
    return $updated;
}

function save_password_reset_request(array $request): void
{
    $requests = load_all_password_reset_requests();
    $found = false;
    foreach ($requests as &$req) {
        if (($req['requestId'] ?? '') === ($request['requestId'] ?? '')) {
            $req = $request;
            $found = true;
            break;
        }
    }
    unset($req);
    if (!$found) {
        $requests[] = $request;
    }
    save_all_password_reset_requests($requests);
}

function add_contractor_password_reset_request(string $mobile, ?string $yojId, string $requesterIp, string $userAgent): array
{
    ensure_contractors_root();
    ensure_password_reset_index();
    $requests = load_all_password_reset_requests();
    $normalizedMobile = normalize_mobile($mobile);
    $now = now_kolkata();

    $recentPending = array_values(array_filter(
        $requests,
        function ($req) use ($normalizedMobile, $now) {
            if (($req['userType'] ?? '') !== 'contractor') {
                return false;
            }
            if (($req['mobile'] ?? '') !== $normalizedMobile) {
                return false;
            }
            if (($req['status'] ?? '') !== 'pending') {
                return false;
            }
            $created = isset($req['createdAt']) ? strtotime((string)$req['createdAt']) : 0;
            return ($now->getTimestamp() - $created) <= 900;
        }
    ));
    if ($recentPending) {
        return $recentPending[0];
    }

    $requestId = 'PR-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $createdAt = $now->format(DateTime::ATOM);
    $request = [
        'requestId' => $requestId,
        'userType' => 'contractor',
        'status' => 'pending',
        'createdAt' => $createdAt,
        'updatedAt' => $createdAt,
        'requesterIp' => $requesterIp,
        'requesterUaHash' => hash('sha256', $userAgent),
        'mobile' => $normalizedMobile,
        'yojId' => $yojId,
        'decidedAt' => null,
        'decidedBy' => null,
        'decisionNote' => null,
        'tempPasswordHash' => null,
        'tempPasswordIssuedAt' => null,
        'tempPasswordDelivery' => 'show_once',
    ];

    $requests[] = $request;
    save_all_password_reset_requests($requests);
    return $request;
}

function department_health_scan(string $deptId): array
{
    ensure_department_env($deptId);
    $targets = [
        department_roles_path($deptId),
        department_requirements_path($deptId) . '/sets.json',
        department_dak_path($deptId) . '/index.json',
        department_tender_index_path($deptId),
        department_workorder_index_path($deptId),
    ];

    $issues = [];
    foreach ($targets as $path) {
        if (!file_exists($path)) {
            continue;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            $issues[] = ['path' => $path, 'error' => 'unable_to_read'];
            continue;
        }
        $decoded = json_decode((string)$raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $issues[] = ['path' => $path, 'error' => json_last_error_msg(), 'raw' => $raw];
        }
    }
    return $issues;
}

function department_health_repair(string $deptId, array $issues): array
{
    $repaired = [];
    foreach ($issues as $issue) {
        $path = $issue['path'];
        if (!file_exists($path)) {
            continue;
        }
        $backup = $path . '.bak-' . date('YmdHis');
        copy($path, $backup);
        $writeOk = false;
        try {
            $data = readJson($path);
            if ($data === []) {
                $writeOk = true;
            } else {
                $writeOk = true;
            }
            if ($writeOk) {
                writeJsonAtomic($path, is_array($data) ? $data : []);
                $repaired[] = ['path' => $path, 'backup' => $backup, 'status' => 'repaired'];
                department_log($deptId, ['event' => 'health_repair', 'path' => $path, 'backup' => $backup]);
            }
        } catch (Throwable $e) {
            department_log($deptId, ['event' => 'health_repair_failed', 'path' => $path, 'message' => $e->getMessage()]);
        }
    }
    return $repaired;
}
