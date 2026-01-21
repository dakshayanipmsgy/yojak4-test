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

function department_memory_path(string $deptId): string
{
    return department_path($deptId) . '/dept_memory.json';
}

function department_memory_defaults(): array
{
    return [
        'version' => 1,
        'fields' => [],
        'lastUpdatedAt' => null,
    ];
}

function department_memory_label_from_key(string $key): string
{
    return profile_memory_label_from_key($key);
}

function load_department_memory(string $deptId): array
{
    $path = department_memory_path($deptId);
    $data = readJson($path);
    if (!is_array($data)) {
        $data = [];
    }
    $memory = array_merge(department_memory_defaults(), $data);
    if (!is_array($memory['fields'] ?? null)) {
        $memory['fields'] = [];
    }
    return $memory;
}

function save_department_memory(string $deptId, array $memory): void
{
    writeJsonAtomic(department_memory_path($deptId), $memory);
}

function department_memory_is_eligible_key(string $key, string $value): bool
{
    $normalized = pack_normalize_placeholder_key($key);
    if ($normalized === '') {
        return false;
    }
    if (preg_match('/^table\\..+\\.(rate|amount)$/', $normalized)) {
        return false;
    }
    if (profile_memory_value_contains_pricing($value)) {
        return false;
    }
    return true;
}

function department_memory_upsert_entries(string $deptId, array $entries, string $source): int
{
    if (!$entries) {
        return 0;
    }
    $memory = load_department_memory($deptId);
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
        if (!department_memory_is_eligible_key($normalized, $value)) {
            continue;
        }
        if (!preg_match('/^(department|tender|user|custom)\\./', $normalized)) {
            $normalized = 'custom.' . $normalized;
        }
        $label = trim((string)($entry['label'] ?? ''));
        if ($label === '') {
            $label = department_memory_label_from_key($normalized);
        }
        $fields[$normalized] = [
            'label' => $label,
            'value' => $value,
            'type' => $type,
            'updatedAt' => $now,
            'source' => $source,
        ];
        $updated++;
    }

    $memory['fields'] = $fields;
    $memory['lastUpdatedAt'] = $now;
    save_department_memory($deptId, $memory);
    return $updated;
}

function department_memory_values(string $deptId): array
{
    $memory = load_department_memory($deptId);
    $values = [];
    foreach (($memory['fields'] ?? []) as $key => $entry) {
        $normalized = pack_normalize_placeholder_key((string)$key);
        if ($normalized === '') {
            continue;
        }
        $value = trim((string)($entry['value'] ?? ''));
        if ($value !== '') {
            $values[$normalized] = $value;
        }
    }
    return $values;
}

function department_docs_path(string $deptId): string
{
    return department_path($deptId) . '/docs';
}

function department_tenders_path(string $deptId): string
{
    return department_path($deptId) . '/tenders';
}

function department_public_tenders_path(string $deptId): string
{
    return department_path($deptId) . '/public_tenders';
}

function department_public_tenders_index_path(string $deptId): string
{
    return department_public_tenders_path($deptId) . '/index.json';
}

function department_public_tender_snapshot_path(string $deptId, string $ytdId): string
{
    return department_public_tenders_path($deptId) . '/' . $ytdId . '.json';
}

function department_public_tender_attachment_dir(string $deptId, string $ytdId): string
{
    return department_public_tenders_path($deptId) . '/files/' . $ytdId;
}

function department_requirement_sets_path_v2(string $deptId): string
{
    return department_path($deptId) . '/requirement_sets';
}

function department_requirement_sets_index_path(string $deptId): string
{
    return department_requirement_sets_path_v2($deptId) . '/index.json';
}

function department_requirement_set_path(string $deptId, string $setId): string
{
    return department_requirement_sets_path_v2($deptId) . '/' . $setId . '.json';
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
    if (!$data) {
        return null;
    }
    if (!array_key_exists('visibleToContractors', $data)) {
        $data['visibleToContractors'] = true;
    }
    if (!array_key_exists('acceptingLinkRequests', $data)) {
        $data['acceptingLinkRequests'] = true;
    }
    if (!array_key_exists('activeAdminUserId', $data)) {
        $data['activeAdminUserId'] = null;
    }
    if (!array_key_exists('district', $data)) {
        $data['district'] = '';
    }
    if (!array_key_exists('nameHi', $data)) {
        $data['nameHi'] = '';
    }
    return $data;
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
        'contactPhone' => '',
        'district' => '',
        'visibleToContractors' => true,
        'acceptingLinkRequests' => true,
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
        department_requirement_sets_path_v2($deptId),
        department_public_tenders_path($deptId),
        department_dak_path($deptId),
        dirname(department_audit_log_path($deptId)),
        department_path($deptId) . '/contractor_requests',
        department_path($deptId) . '/contractors',
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

    if (!file_exists(department_requirement_sets_index_path($deptId))) {
        writeJsonAtomic(department_requirement_sets_index_path($deptId), []);
    }

    if (!file_exists(department_public_tenders_index_path($deptId))) {
        writeJsonAtomic(department_public_tenders_index_path($deptId), []);
    }

    if (!file_exists(department_dak_path($deptId) . '/index.json')) {
        writeJsonAtomic(department_dak_path($deptId) . '/index.json', []);
    }

    $contractorRequestIndex = department_path($deptId) . '/contractor_requests/index.json';
    if (!file_exists($contractorRequestIndex)) {
        writeJsonAtomic($contractorRequestIndex, []);
    }

    $contractorIndex = department_path($deptId) . '/contractors/index.json';
    if (!file_exists($contractorIndex)) {
        writeJsonAtomic($contractorIndex, []);
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
        '{{field:tender.departmentName}}',
        '{{field:tender.number}}',
        '{{field:tender.workorder_id}}',
        '{{field:tender.document_date}}',
        '{{field:tender.user_name}}',
        '{{field:tender.document_title}}',
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

function log_link_event(array $context): void
{
    $entry = array_merge(['at' => now_kolkata()->format(DateTime::ATOM)], $context);
    $file = DATA_PATH . '/logs/linking.log';
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $handle = fopen($file, 'a');
    if ($handle) {
        flock($handle, LOCK_EX);
        fwrite($handle, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function department_contractor_requests_dir(string $deptId): string
{
    return department_path($deptId) . '/contractor_requests';
}

function department_contractor_requests_index_path(string $deptId): string
{
    return department_contractor_requests_dir($deptId) . '/index.json';
}

function department_contractor_request_path(string $deptId, string $requestId): string
{
    return department_contractor_requests_dir($deptId) . '/' . $requestId . '.json';
}

function load_department_contractor_requests(string $deptId): array
{
    ensure_department_env($deptId);
    $data = readJson(department_contractor_requests_index_path($deptId));
    return is_array($data) ? array_values($data) : [];
}

function save_department_contractor_requests(string $deptId, array $requests): void
{
    ensure_department_env($deptId);
    writeJsonAtomic(department_contractor_requests_index_path($deptId), array_values($requests));
}

function load_department_contractor_request(string $deptId, string $requestId): ?array
{
    ensure_department_env($deptId);
    $path = department_contractor_request_path($deptId, $requestId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function save_department_contractor_request(string $deptId, array $request): void
{
    ensure_department_env($deptId);
    $path = department_contractor_request_path($deptId, $request['requestId'] ?? '');
    writeJsonAtomic($path, $request);

    $index = load_department_contractor_requests($deptId);
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['requestId'] ?? '') === ($request['requestId'] ?? '')) {
            $entry['status'] = $request['status'] ?? $entry['status'];
            $entry['yojId'] = $request['yojId'] ?? $entry['yojId'];
            $entry['createdAt'] = $request['createdAt'] ?? $entry['createdAt'];
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'requestId' => $request['requestId'] ?? '',
            'yojId' => $request['yojId'] ?? '',
            'status' => $request['status'] ?? 'pending',
            'createdAt' => $request['createdAt'] ?? now_kolkata()->format(DateTime::ATOM),
        ];
    }
    save_department_contractor_requests($deptId, $index);
}

function department_contractors_dir(string $deptId): string
{
    return department_path($deptId) . '/contractors';
}

function department_contractors_index_path(string $deptId): string
{
    return department_contractors_dir($deptId) . '/index.json';
}

function department_contractor_link_path(string $deptId, string $yojId): string
{
    return department_contractors_dir($deptId) . '/' . $yojId . '.json';
}

function load_department_contractor_links(string $deptId): array
{
    ensure_department_env($deptId);
    $data = readJson(department_contractors_index_path($deptId));
    return is_array($data) ? array_values($data) : [];
}

function save_department_contractor_links(string $deptId, array $links): void
{
    ensure_department_env($deptId);
    writeJsonAtomic(department_contractors_index_path($deptId), array_values($links));
}

function load_department_contractor_link(string $deptId, string $yojId): ?array
{
    ensure_department_env($deptId);
    $path = department_contractor_link_path($deptId, $yojId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function generate_department_contractor_id(string $deptId): string
{
    ensure_department_env($deptId);
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $candidate = $deptId . '.CON-' . $suffix;
        $exists = false;
        foreach (load_department_contractor_links($deptId) as $link) {
            if (($link['deptContractorId'] ?? '') === $candidate) {
                $exists = true;
                break;
            }
        }
    } while ($exists);
    return $candidate;
}

function ensure_department_contractor_link(string $deptId, string $yojId, string $linkedBy): array
{
    ensure_department_env($deptId);
    ensure_contractor_links_env($yojId);
    $existing = load_department_contractor_link($deptId, $yojId);
    $now = now_kolkata()->format(DateTime::ATOM);
    $department = load_department($deptId) ?? [];

    $link = [
        'deptId' => $deptId,
        'yojId' => $yojId,
        'deptContractorId' => $existing['deptContractorId'] ?? generate_department_contractor_id($deptId),
        'status' => 'active',
        'scopes' => [
            'canViewPublishedTenders' => true,
            'canViewAssignedWorkorders' => true,
        ],
        'linkedBy' => $existing['linkedBy'] ?? $linkedBy,
        'linkedAt' => $existing['linkedAt'] ?? $now,
        'updatedAt' => $now,
    ];

    writeJsonAtomic(department_contractor_link_path($deptId, $yojId), $link);

    $index = load_department_contractor_links($deptId);
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['yojId'] ?? '') === $yojId) {
            $entry['deptContractorId'] = $link['deptContractorId'];
            $entry['status'] = $link['status'];
            $entry['linkedAt'] = $link['linkedAt'];
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'yojId' => $yojId,
            'deptContractorId' => $link['deptContractorId'],
            'status' => $link['status'],
            'linkedAt' => $link['linkedAt'],
        ];
    }
    save_department_contractor_links($deptId, $index);

    $mirror = [
        'deptId' => $deptId,
        'deptContractorId' => $link['deptContractorId'],
        'status' => $link['status'],
        'linkedAt' => $link['linkedAt'],
        'updatedAt' => $link['updatedAt'],
        'deptPublicSnapshot' => [
            'nameEn' => $department['nameEn'] ?? '',
            'district' => $department['district'] ?? '',
        ],
    ];
    save_contractor_link_file($yojId, $mirror);

    $contractorLinks = load_contractor_links($yojId);
    $mirrorFound = false;
    foreach ($contractorLinks as &$entry) {
        if (($entry['deptId'] ?? '') === $deptId) {
            $entry = $mirror;
            $mirrorFound = true;
            break;
        }
    }
    unset($entry);
    if (!$mirrorFound) {
        $contractorLinks[] = $mirror;
    }
    save_contractor_links($yojId, $contractorLinks);

    return $link;
}

function update_department_contractor_link_status(string $deptId, string $yojId, string $status): bool
{
    $link = load_department_contractor_link($deptId, $yojId);
    if (!$link) {
        return false;
    }
    if (!in_array($status, ['active', 'suspended', 'revoked'], true)) {
        return false;
    }

    $link['status'] = $status;
    $link['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(department_contractor_link_path($deptId, $yojId), $link);

    $index = load_department_contractor_links($deptId);
    foreach ($index as &$entry) {
        if (($entry['yojId'] ?? '') === $yojId) {
            $entry['status'] = $status;
            break;
        }
    }
    unset($entry);
    save_department_contractor_links($deptId, $index);

    $mirror = load_contractor_link($yojId, $deptId);
    if ($mirror) {
        $mirror['status'] = $status;
        $mirror['updatedAt'] = $link['updatedAt'];
        save_contractor_link_file($yojId, $mirror);
        $contractorLinks = load_contractor_links($yojId);
        foreach ($contractorLinks as &$entry) {
            if (($entry['deptId'] ?? '') === $deptId) {
                $entry['status'] = $status;
                $entry['updatedAt'] = $mirror['updatedAt'];
                break;
            }
        }
        unset($entry);
        save_contractor_links($yojId, $contractorLinks);
    }

    return true;
}

function contractor_has_pending_request(string $yojId, string $deptId): bool
{
    $requests = load_department_contractor_requests($deptId);
    foreach ($requests as $request) {
        if (($request['yojId'] ?? '') === $yojId && ($request['status'] ?? '') === 'pending') {
            return true;
        }
    }
    return false;
}

function contractor_pending_requests_count(string $yojId): int
{
    $count = 0;
    $dirs = glob(DATA_PATH . '/departments/*/contractor_requests/index.json') ?: [];
    foreach ($dirs as $indexPath) {
        $list = readJson($indexPath);
        if (!is_array($list)) {
            continue;
        }
        foreach ($list as $entry) {
            if (($entry['yojId'] ?? '') === $yojId && ($entry['status'] ?? '') === 'pending') {
                $count++;
            }
        }
    }
    return $count;
}

function generate_contractor_request_id(string $deptId): string
{
    ensure_department_env($deptId);
    do {
        $suffix = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $candidate = 'LREQ-' . (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Ymd') . '-' . $suffix;
    } while (file_exists(department_contractor_request_path($deptId, $candidate)));
    return $candidate;
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

function load_department_published_tenders(string $deptId): array
{
    $visible = [];
    foreach (load_department_tenders($deptId) as $indexEntry) {
        $tenderId = $indexEntry['id'] ?? '';
        if ($tenderId === '') {
            continue;
        }
        $tender = load_department_tender($deptId, $tenderId);
        if ($tender && !empty($tender['publishedToContractors'])) {
            $visible[] = [
                'id' => $tender['id'] ?? $tenderId,
                'title' => $tender['title'] ?? $tenderId,
                'publishDate' => $tender['dates']['publish'] ?? '',
                'submissionDate' => $tender['dates']['submission'] ?? '',
                'openingDate' => $tender['dates']['opening'] ?? '',
                'publishedAt' => $tender['publishedAt'] ?? null,
            ];
        }
    }
    usort($visible, fn($a, $b) => strcmp($b['publishDate'] ?? '', $a['publishDate'] ?? ''));
    return $visible;
}

function public_tender_index(string $deptId): array
{
    ensure_department_env($deptId);
    $index = readJson(department_public_tenders_index_path($deptId));
    return is_array($index) ? array_values($index) : [];
}

function save_public_tender_index(string $deptId, array $entries): void
{
    ensure_department_env($deptId);
    writeJsonAtomic(department_public_tenders_index_path($deptId), array_values($entries));
}

function sanitize_public_attachment(string $deptId, string $ytdId, array $file): ?array
{
    $name = trim((string)($file['name'] ?? ''));
    $storedPath = trim((string)($file['storedPath'] ?? ''));
    $mime = trim((string)($file['mime'] ?? ''));
    $size = (int)($file['size'] ?? 0);
    if ($name === '' || $storedPath === '') {
        return null;
    }

    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    $safePath = preg_replace('/[^a-zA-Z0-9._\\/-]/', '_', $storedPath);
    return [
        'name' => $safeName,
        'storedPath' => $safePath,
        'mime' => $mime !== '' ? $mime : 'application/octet-stream',
        'size' => max(0, $size),
        'deptId' => $deptId,
        'ytdId' => $ytdId,
    ];
}

function save_public_attachments(string $deptId, string $ytdId, array $files, array $existing = []): array
{
    $attachments = array_values($existing);
    $destDir = department_public_tender_attachment_dir($deptId, $ytdId);
    if (!is_dir($destDir)) {
        mkdir($destDir, 0775, true);
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $count = is_array($files['name'] ?? null) ? count($files['name']) : 0;
    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }
        $original = $files['name'][$i] ?? ('file_' . $i);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($original));
        if ($safeName === '' || $safeName === '.' || $safeName === '..') {
            $safeName = 'public_' . $i . '.bin';
        }
        $tmpPath = $files['tmp_name'][$i] ?? null;
        if (!$tmpPath || !file_exists($tmpPath)) {
            continue;
        }
        $mime = $finfo->file($tmpPath) ?: 'application/octet-stream';
        $size = (int)($files['size'][$i] ?? 0);
        $destPath = $destDir . '/' . $safeName;
        if (!move_uploaded_file($tmpPath, $destPath)) {
            continue;
        }
        $attachments[] = [
            'name' => $safeName,
            'storedPath' => 'files/' . $ytdId . '/' . $safeName,
            'mime' => $mime,
            'size' => $size,
            'deptId' => $deptId,
            'ytdId' => $ytdId,
        ];
    }
    return $attachments;
}

function build_public_tender_snapshot(array $department, array $tender, array $requirementSets, array $attachmentsPublic): array
{
    $deptId = $department['deptId'] ?? '';
    $ytdId = $tender['id'] ?? '';
    $title = $tender['contractorVisibleSummary']['titlePublic'] ?? $tender['title'] ?? $ytdId;
    $summaryPublic = $tender['contractorVisibleSummary']['summaryPublic'] ?? '';
    $snapshot = [
        'deptId' => $deptId,
        'deptPublic' => [
            'nameEn' => $department['nameEn'] ?? $deptId,
            'district' => $department['district'] ?? '',
            'deptId' => $deptId,
        ],
        'ytdId' => $ytdId,
        'title' => $title,
        'tenderNumber' => $tender['tenderNumberFormat']['prefix'] ?? '',
        'publishedAt' => $tender['publishedAt'] ?? now_kolkata()->format(DateTime::ATOM),
        'submissionDeadline' => $tender['dates']['submission'] ?? null,
        'openingDate' => $tender['dates']['opening'] ?? null,
        'completionMonths' => $tender['completionMonths'] ?? null,
        'emd' => $tender['emdText'] ?? '',
        'tenderFee' => $tender['tenderFee'] ?? '',
        'summaryPublic' => $summaryPublic,
        'attachmentsPublic' => $attachmentsPublic,
        'requirementSetId' => $tender['requirementSetId'] ?? null,
    ];
    return $snapshot;
}

function write_public_tender_snapshot(array $department, array $tender, array $requirementSets, array $attachmentsPublic): void
{
    $deptId = $department['deptId'] ?? '';
    $ytdId = $tender['id'] ?? '';
    if ($deptId === '' || $ytdId === '') {
        return;
    }
    $snapshot = build_public_tender_snapshot($department, $tender, $requirementSets, $attachmentsPublic);
    $path = department_public_tender_snapshot_path($deptId, $ytdId);
    writeJsonAtomic($path, $snapshot);

    $index = public_tender_index($deptId);
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['ytdId'] ?? '') === $ytdId) {
            $entry = [
                'deptId' => $deptId,
                'ytdId' => $ytdId,
                'title' => $snapshot['title'],
                'submissionDeadline' => $snapshot['submissionDeadline'],
                'publishedAt' => $snapshot['publishedAt'],
            ];
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'deptId' => $deptId,
            'ytdId' => $ytdId,
            'title' => $snapshot['title'],
            'submissionDeadline' => $snapshot['submissionDeadline'],
            'publishedAt' => $snapshot['publishedAt'],
        ];
    }
    save_public_tender_index($deptId, $index);
}

function remove_public_tender_snapshot(string $deptId, string $ytdId): void
{
    $path = department_public_tender_snapshot_path($deptId, $ytdId);
    if (file_exists($path)) {
        unlink($path);
    }
    $index = public_tender_index($deptId);
    $index = array_values(array_filter($index, fn($entry) => ($entry['ytdId'] ?? '') !== $ytdId));
    save_public_tender_index($deptId, $index);
}

function load_public_tender_snapshot(string $deptId, string $ytdId): ?array
{
    $path = department_public_tender_snapshot_path($deptId, $ytdId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    if (!$data) {
        return null;
    }
    return $data;
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
    if (!array_key_exists('requirementSetId', $tender)) {
        $tender['requirementSetId'] = null;
    }
    if (!array_key_exists('contractorVisibleSummary', $tender) || !is_array($tender['contractorVisibleSummary'])) {
        $tender['contractorVisibleSummary'] = [
            'titlePublic' => null,
            'summaryPublic' => '',
            'attachmentsPublic' => [],
        ];
    }
    if (!array_key_exists('publishedToContractors', $tender)) {
        $tender['publishedToContractors'] = false;
    }
    if (!array_key_exists('publishedAt', $tender)) {
        $tender['publishedAt'] = null;
    }
    if (!empty($tender['publishedToContractors']) && empty($tender['publishedAt'])) {
        $tender['publishedAt'] = now_kolkata()->format(DateTime::ATOM);
    }
    if (empty($tender['publishedToContractors'])) {
        $tender['publishedAt'] = null;
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
                'publishedToContractors' => !empty($tender['publishedToContractors']),
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
            'publishedToContractors' => !empty($tender['publishedToContractors']),
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
    if (!$data) {
        return null;
    }
    if (!array_key_exists('requirementSetId', $data)) {
        $data['requirementSetId'] = null;
    }
    if (!array_key_exists('contractorVisibleSummary', $data) || !is_array($data['contractorVisibleSummary'])) {
        $data['contractorVisibleSummary'] = [
            'titlePublic' => null,
            'summaryPublic' => '',
            'attachmentsPublic' => [],
        ];
    } else {
        if (!array_key_exists('attachmentsPublic', $data['contractorVisibleSummary']) || !is_array($data['contractorVisibleSummary']['attachmentsPublic'])) {
            $data['contractorVisibleSummary']['attachmentsPublic'] = [];
        }
    }
    if (!array_key_exists('publishedToContractors', $data)) {
        $data['publishedToContractors'] = false;
    }
    if (!array_key_exists('publishedAt', $data)) {
        $data['publishedAt'] = null;
    }
    return $data;
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
    $index = readJson(department_requirement_sets_index_path($deptId));
    $index = is_array($index) ? array_values($index) : [];
    $sets = [];
    foreach ($index as $meta) {
        $setId = $meta['setId'] ?? '';
        if ($setId === '') {
            continue;
        }
        $path = department_requirement_set_path($deptId, $setId);
        $record = readJson($path);
        if (!$record) {
            continue;
        }
        $record['setId'] = $setId;
        if (!array_key_exists('visibleToContractors', $record)) {
            $record['visibleToContractors'] = true;
        }
        if (!array_key_exists('items', $record) || !is_array($record['items'])) {
            $record['items'] = [];
        }
        $sets[] = $record;
    }

    return $sets;
}

function save_requirement_sets(string $deptId, array $sets): void
{
    ensure_department_env($deptId);
    $index = [];
    foreach ($sets as $set) {
        if (empty($set['setId'])) {
            continue;
        }
        $path = department_requirement_set_path($deptId, $set['setId']);
        writeJsonAtomic($path, $set);
        $index[] = [
            'setId' => $set['setId'],
            'name' => $set['name'] ?? ($set['title'] ?? $set['setId']),
            'visibleToContractors' => !empty($set['visibleToContractors']),
            'updatedAt' => $set['updatedAt'] ?? now_kolkata()->format(DateTime::ATOM),
        ];
    }
    writeJsonAtomic(department_requirement_sets_index_path($deptId), array_values($index));
}

function create_requirement_set(string $deptId, string $title, array $items): array
{
    $sets = load_requirement_sets($deptId);
    $setId = 'REQ-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $normalizedItems = [];
    foreach ($items as $idx => $item) {
        $titleItem = is_array($item) ? trim((string)($item['title'] ?? '')) : trim((string)$item);
        if ($titleItem === '') {
            continue;
        }
        $normalizedItems[] = [
            'key' => 'REQITEM-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
            'title' => $titleItem,
            'description' => is_array($item) ? trim((string)($item['description'] ?? '')) : '',
            'required' => (bool)($item['required'] ?? true),
            'category' => is_array($item) ? trim((string)($item['category'] ?? '')) : '',
        ];
        if ($idx >= 200) {
            break;
        }
    }

    $now = now_kolkata()->format(DateTime::ATOM);
    $set = [
        'setId' => $setId,
        'name' => $title,
        'description' => '',
        'visibleToContractors' => true,
        'items' => $normalizedItems,
        'createdAt' => $now,
        'updatedAt' => $now,
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
            $normalizedItems = [];
            foreach ($items as $idx => $item) {
                $titleItem = is_array($item) ? trim((string)($item['title'] ?? '')) : trim((string)$item);
                if ($titleItem === '') {
                    continue;
                }
                $normalizedItems[] = [
                    'key' => $item['key'] ?? ('REQITEM-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6))),
                    'title' => $titleItem,
                    'description' => is_array($item) ? trim((string)($item['description'] ?? '')) : '',
                    'required' => (bool)($item['required'] ?? true),
                    'category' => is_array($item) ? trim((string)($item['category'] ?? '')) : '',
                ];
                if ($idx >= 200) {
                    break;
                }
            }

            $set['name'] = $title;
            $set['title'] = $title;
            $set['items'] = $normalizedItems;
            $set['visibleToContractors'] = !empty($set['visibleToContractors']);
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

function delete_requirement_set(string $deptId, string $setId): bool
{
    $sets = load_requirement_sets($deptId);
    $filtered = [];
    $deleted = false;
    foreach ($sets as $set) {
        if (($set['setId'] ?? '') === $setId) {
            $deleted = true;
            continue;
        }
        $filtered[] = $set;
    }
    if ($deleted) {
        $setPath = department_requirement_set_path($deptId, $setId);
        if (file_exists($setPath)) {
            unlink($setPath);
        }
        save_requirement_sets($deptId, $filtered);
    }
    return $deleted;
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
