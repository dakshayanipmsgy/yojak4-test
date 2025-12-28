<?php
declare(strict_types=1);

function build_login_debug_report(string $loginId, ?string $limitDeptId = null): array
{
    $normalized = normalize_login_identifier($loginId);
    $report = [
        'input' => $loginId,
        'normalized' => $normalized,
        'parsed' => null,
        'expectedPath' => null,
        'pathExists' => false,
        'rolePresent' => false,
        'roleMessage' => null,
        'userStatus' => null,
        'userMessage' => null,
    ];

    if ($normalized === '') {
        $report['userMessage'] = 'Please provide a department login identifier.';
        return $report;
    }

    $parsed = parse_department_login_identifier($normalized);
    if (!$parsed) {
        $report['userMessage'] = 'Format error: expected userShortId.roleId.deptId (lowercase letters/numbers).';
        return $report;
    }

    if (!is_valid_dept_id($parsed['deptId'])) {
        $report['parsed'] = $parsed;
        $report['userMessage'] = 'Invalid department id format.';
        return $report;
    }

    if ($limitDeptId !== null && $parsed['deptId'] !== normalize_dept_id($limitDeptId)) {
        $report['parsed'] = $parsed;
        $report['userMessage'] = 'Format error: deptId must match your department.';
        return $report;
    }

    $report['parsed'] = $parsed;
    $report['expectedPath'] = department_user_path($parsed['deptId'], $parsed['fullUserId'], false);
    $report['pathExists'] = file_exists($report['expectedPath']);

    $rolesPath = department_roles_path($parsed['deptId']);
    $rolePresent = false;
    if (file_exists($rolesPath)) {
        $roles = readJson($rolesPath);
        if (is_array($roles)) {
            foreach ($roles as $role) {
                if (($role['roleId'] ?? '') === $parsed['roleId']) {
                    $rolePresent = true;
                    break;
                }
            }
        }
    }
    $report['rolePresent'] = $rolePresent;
    $report['roleMessage'] = $rolePresent ? 'Role found in roles.json.' : 'roles.json missing roleId.';

    if (!$report['pathExists']) {
        $report['userStatus'] = 'missing';
        $report['userMessage'] = 'User JSON not found at expected path.';
        return $report;
    }

    $record = readJson($report['expectedPath']);
    if (!is_array($record) || empty($record)) {
        $report['userStatus'] = 'missing';
        $report['userMessage'] = 'User JSON not found at expected path.';
        return $report;
    }

    $report['userStatus'] = $record['status'] ?? 'unknown';

    return $report;
}

function log_login_debug(array $report, array $actor): void
{
    $actorId = $actor['username'] ?? ($actor['fullUserId'] ?? 'unknown');
    logEvent(DATA_PATH . '/logs/auth.log', [
        'event' => 'login_debug_check',
        'actorType' => $actor['type'] ?? 'unknown',
        'actorId' => $actorId,
        'input' => $report['normalized'] ?? '',
        'deptId' => $report['parsed']['deptId'] ?? null,
        'roleId' => $report['parsed']['roleId'] ?? null,
        'pathExists' => $report['pathExists'] ?? false,
        'rolePresent' => $report['rolePresent'] ?? false,
        'userStatus' => $report['userStatus'] ?? null,
        'note' => $report['userMessage'] ?? null,
    ]);
}
