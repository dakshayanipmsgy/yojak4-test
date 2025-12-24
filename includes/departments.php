<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function departmentIndexPath(): string {
    return dataPath('departments/index.json');
}

function ensureDepartmentsIndex(): array {
    $path = departmentIndexPath();
    ensureDirectory(dirname($path));
    if (!file_exists($path)) {
        writeJsonAtomic($path, []);
    }

    $list = readJson($path, []);
    return is_array($list) ? $list : [];
}

function saveDepartmentsIndex(array $departments): void {
    writeJsonAtomic(departmentIndexPath(), array_values($departments));
}

function normalizeDeptId(string $deptId): string {
    return strtolower(trim($deptId));
}

function isValidDeptId(string $deptId): bool {
    return (bool)preg_match('/^[a-z0-9]{3,10}$/', $deptId);
}

function isValidAdminShortId(string $shortId): bool {
    return (bool)preg_match('/^[a-z0-9]{3,12}$/', $shortId);
}

function departmentBasePath(string $deptId): string {
    return dataPath('departments/' . $deptId);
}

function departmentJsonPath(string $deptId): string {
    return departmentBasePath($deptId) . '/department.json';
}

function departmentUserPath(string $deptId, string $fullUserId, string $state = 'active'): string {
    $safeState = $state === 'archived' ? 'archived' : 'active';
    return departmentBasePath($deptId) . '/users/' . $safeState . '/' . $fullUserId . '.json';
}

function ensureDepartmentStructure(string $deptId): void {
    ensureDirectory(departmentBasePath($deptId));
    ensureDirectory(departmentBasePath($deptId) . '/users/active');
    ensureDirectory(departmentBasePath($deptId) . '/users/archived');
    ensureDirectory(departmentBasePath($deptId) . '/rbac');
}

function loadDepartment(string $deptId): ?array {
    $path = departmentJsonPath($deptId);
    if (!file_exists($path)) {
        return null;
    }

    $department = readJson($path, []);
    return is_array($department) ? $department : null;
}

function saveDepartment(array $department): void {
    if (empty($department['deptId'])) {
        throw new RuntimeException('Missing deptId');
    }
    $deptId = $department['deptId'];
    ensureDepartmentStructure($deptId);
    $department['updatedAt'] = isoNow();
    writeJsonAtomic(departmentJsonPath($deptId), $department);
}

function upsertDepartmentIndexEntry(array $entry): void {
    $departments = ensureDepartmentsIndex();
    $updated = false;
    foreach ($departments as $idx => $dept) {
        if (($dept['deptId'] ?? '') === ($entry['deptId'] ?? '')) {
            $departments[$idx] = array_merge($dept, $entry);
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        $departments[] = $entry;
    }

    saveDepartmentsIndex($departments);
}

function seedDepartmentRoles(string $deptId): void {
    ensureDepartmentStructure($deptId);
    $rolesPath = departmentBasePath($deptId) . '/rbac/roles.json';
    if (file_exists($rolesPath)) {
        return;
    }

    $now = isoNow();
    $roles = [
        [
            'roleId' => 'admin',
            'name' => 'Administrator',
            'permissions' => ['*'],
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
    ];

    writeJsonAtomic($rolesPath, $roles);
}

function parseDepartmentUserId(string $fullUserId): ?array {
    $normalized = strtolower(trim($fullUserId));
    if (!preg_match('/^([a-z0-9]{3,12})\.([a-z0-9]{3,12})\.([a-z0-9]{3,10})$/', $normalized, $matches)) {
        return null;
    }

    if (!isValidDeptId($matches[3])) {
        return null;
    }

    return [
        'userShortId' => $matches[1],
        'roleId' => $matches[2],
        'deptId' => $matches[3],
        'fullUserId' => $normalized,
    ];
}

function loadDepartmentUser(string $deptId, string $fullUserId, string $state = 'active'): ?array {
    $path = departmentUserPath($deptId, $fullUserId, $state);
    if (!file_exists($path)) {
        return null;
    }

    $user = readJson($path, []);
    return is_array($user) ? $user : null;
}

function archiveDepartmentAdmin(string $deptId, string $fullUserId): void {
    $activePath = departmentUserPath($deptId, $fullUserId, 'active');
    if (!file_exists($activePath)) {
        return;
    }

    $data = readJson($activePath, []);
    $data['status'] = 'archived';
    $data['archivedAt'] = isoNow();

    $archiveName = $fullUserId . '-' . time() . '.json';
    $archivedPath = departmentBasePath($deptId) . '/users/archived/' . $archiveName;
    writeJsonAtomic($archivedPath, $data);
    @unlink($activePath);
}

function activeDepartmentAdmin(string $deptId): ?array {
    $department = loadDepartment($deptId);
    if (!$department || empty($department['activeAdminUserId'])) {
        return null;
    }
    $fullUserId = $department['activeAdminUserId'];
    return loadDepartmentUser($deptId, $fullUserId, 'active');
}

function createDepartment(array $payload): array {
    $deptId = normalizeDeptId($payload['deptId'] ?? '');
    $nameEn = trim($payload['nameEn'] ?? '');
    $nameHi = trim($payload['nameHi'] ?? '');
    $address = trim($payload['address'] ?? '');
    $contactEmail = trim($payload['contactEmail'] ?? '');
    $errors = [];

    if (!isValidDeptId($deptId)) {
        $errors[] = 'Department ID must be 3-10 lowercase alphanumeric characters.';
    }
    if ($nameEn === '') {
        $errors[] = 'English name is required.';
    }
    if ($nameHi === '') {
        $errors[] = 'Hindi name is required.';
    }

    $index = ensureDepartmentsIndex();
    foreach ($index as $dept) {
        if (($dept['deptId'] ?? '') === $deptId) {
            $errors[] = 'Department ID already exists.';
            break;
        }
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    ensureDepartmentStructure($deptId);
    $now = isoNow();
    $department = [
        'deptId' => $deptId,
        'nameEn' => $nameEn,
        'nameHi' => $nameHi,
        'address' => $address !== '' ? $address : null,
        'contactEmail' => $contactEmail !== '' ? $contactEmail : null,
        'status' => 'active',
        'createdAt' => $now,
        'updatedAt' => $now,
        'activeAdminUserId' => null,
        'adminHistory' => [],
    ];

    saveDepartment($department);
    upsertDepartmentIndexEntry([
        'deptId' => $deptId,
        'nameEn' => $nameEn,
        'nameHi' => $nameHi,
        'status' => 'active',
        'createdAt' => $now,
    ]);
    seedDepartmentRoles($deptId);
    logEvent('departments.log', [
        'event' => 'department_created',
        'deptId' => $deptId,
        'nameEn' => $nameEn,
        'nameHi' => $nameHi,
    ]);

    return ['success' => true, 'department' => $department];
}

function createOrReplaceDepartmentAdmin(string $deptId, string $adminShortId, string $displayName, string $password): array {
    $deptId = normalizeDeptId($deptId);
    $adminShortId = strtolower(trim($adminShortId));
    $displayName = trim($displayName);
    $errors = [];

    $department = loadDepartment($deptId);
    if (!$department) {
        return ['success' => false, 'errors' => ['Department not found.']];
    }

    if (!isValidAdminShortId($adminShortId)) {
        $errors[] = 'Admin ID must be 3-12 lowercase alphanumeric characters.';
    }
    if ($displayName === '') {
        $errors[] = 'Display name is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    $fullUserId = $adminShortId . '.admin.' . $deptId;
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    $existingAdminId = $department['activeAdminUserId'] ?? null;
    if ($existingAdminId && $existingAdminId !== $fullUserId) {
        archiveDepartmentAdmin($deptId, $existingAdminId);
        $department['adminHistory'][] = $existingAdminId;
    }

    $now = isoNow();
    $adminData = [
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

    writeJsonAtomic(departmentUserPath($deptId, $fullUserId, 'active'), $adminData);
    $department['activeAdminUserId'] = $fullUserId;
    saveDepartment($department);
    seedDepartmentRoles($deptId);

    logEvent('departments.log', [
        'event' => 'department_admin_created',
        'deptId' => $deptId,
        'adminUserId' => $fullUserId,
        'replacedAdmin' => $existingAdminId,
    ]);

    return ['success' => true, 'user' => $adminData];
}
