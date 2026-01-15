<?php
declare(strict_types=1);

function superadmin_dashboard_counts(): array
{
    $now = now_kolkata();
    $todayStart = $now->setTime(0, 0, 0);
    $last24hStart = $now->sub(new DateInterval('PT24H'));

    $counts = [
        'contractorPendingApprovals' => 0,
        'contractorApprovedTotal' => 0,
        'departmentsTotal' => 0,
        'departmentsAdminIssues' => 0,
        'deptPendingLinkRequests' => 0,
        'employeesTotal' => 0,
        'employeesDisabled' => 0,
        'resetApprovalsPending' => 0,
        'resetApprovalsToday' => 0,
        'supportOpen' => 0,
        'assistedNew' => 0,
        'assistedInProgress' => 0,
        'assistedFailed' => 0,
        'assistedLastAt' => null,
        'errorsToday' => 0,
        'errors24h' => 0,
        'lastErrorAt' => null,
        'lastErrorSource' => null,
        'tenderLastRunAt' => null,
        'tenderLastRunStatus' => 'never',
        'tenderNewFoundLastRun' => 0,
        'backupsLastAt' => null,
        'backupsCount' => 0,
        'backupsLastStatus' => null,
    ];

    try {
        $counts['contractorPendingApprovals'] = count(dashboard_safe_glob(DATA_PATH . '/contractors/pending/*.json'));
        $counts['contractorApprovedTotal'] = count(contractors_index());

        $departments = departments_index();
        $counts['departmentsTotal'] = count($departments);
        foreach ($departments as $department) {
            $deptId = $department['deptId'] ?? '';
            if ($deptId === '') {
                continue;
            }
            $resolved = resolve_department_admin_account($deptId);
            if (empty($resolved['ok'])) {
                $counts['departmentsAdminIssues']++;
            }
        }

        $linkIndexes = dashboard_safe_glob(DATA_PATH . '/departments/*/contractor_requests/index.json');
        foreach ($linkIndexes as $indexPath) {
            $entries = readJson($indexPath);
            if (!is_array($entries)) {
                continue;
            }
            foreach ($entries as $entry) {
                if (($entry['status'] ?? '') === 'pending') {
                    $counts['deptPendingLinkRequests']++;
                }
            }
        }

        $employees = staff_employee_index();
        $counts['employeesTotal'] = count($employees);
        foreach ($employees as $employee) {
            if (($employee['status'] ?? 'active') !== 'active') {
                $counts['employeesDisabled']++;
            }
        }

        $resetRequests = load_all_password_reset_requests();
        foreach ($resetRequests as $request) {
            if (($request['status'] ?? '') === 'pending') {
                $counts['resetApprovalsPending']++;
            }
            $requestedAt = $request['updatedAt'] ?? $request['requestedAt'] ?? $request['createdAt'] ?? null;
            if ($requestedAt && dashboard_is_today($requestedAt, $todayStart)) {
                $counts['resetApprovalsToday']++;
            }
        }

        $supportIndex = support_load_index();
        foreach ($supportIndex as $ticket) {
            if (($ticket['status'] ?? 'open') !== 'closed') {
                $counts['supportOpen']++;
            }
        }

        $assistedRequests = assisted_v2_list_requests();
        foreach ($assistedRequests as $request) {
            $status = $request['status'] ?? 'pending';
            if ($status === 'pending') {
                $counts['assistedNew']++;
            } elseif ($status === 'in_progress') {
                $counts['assistedInProgress']++;
            } elseif (in_array($status, ['failed', 'rejected'], true)) {
                $counts['assistedFailed']++;
            }

            $timestamp = $request['updatedAt'] ?? $request['createdAt'] ?? null;
            if ($timestamp) {
                $parsed = dashboard_parse_datetime($timestamp);
                if ($parsed && ($counts['assistedLastAt'] === null || $parsed > $counts['assistedLastAt'])) {
                    $counts['assistedLastAt'] = $parsed;
                }
            }
        }

        $errorSummary = dashboard_collect_error_summary($todayStart, $last24hStart);
        $counts['errorsToday'] = $errorSummary['errorsToday'];
        $counts['errors24h'] = $errorSummary['errors24h'];
        $counts['lastErrorAt'] = $errorSummary['lastErrorAt'];
        $counts['lastErrorSource'] = $errorSummary['lastErrorSource'];

        $state = tender_discovery_state();
        $counts['tenderLastRunAt'] = $state['lastRunAt'] ?? null;
        $summary = $state['lastSummary'] ?? null;
        if (is_array($summary)) {
            $counts['tenderNewFoundLastRun'] = (int)($summary['newCount'] ?? 0);
            $errors = $summary['errors'] ?? [];
            $counts['tenderLastRunStatus'] = empty($errors) ? 'ok' : 'failed';
        } else {
            $counts['tenderLastRunStatus'] = $counts['tenderLastRunAt'] ? 'ok' : 'never';
        }

        $backups = list_backups();
        $counts['backupsCount'] = count($backups);
        if ($backups) {
            $counts['backupsLastAt'] = $backups[0]['createdAt'] ?? null;
        }
        $counts['backupsLastStatus'] = dashboard_last_backup_status(DATA_PATH . '/logs/backup.log');
    } catch (Throwable $e) {
        logEvent(DATA_PATH . '/logs/site.log', [
            'event' => 'DASH_COUNTS_ERROR',
            'path' => 'superadmin_dashboard_counts',
            'message' => $e->getMessage(),
        ]);
    }

    return $counts;
}

function dashboard_safe_glob(string $pattern): array
{
    $matches = glob($pattern);
    return $matches ? array_values($matches) : [];
}

function dashboard_is_today(string $timestamp, DateTimeImmutable $todayStart): bool
{
    $parsed = dashboard_parse_datetime($timestamp);
    if (!$parsed) {
        return false;
    }
    return $parsed >= $todayStart;
}

function dashboard_parse_datetime(string $timestamp): ?DateTimeImmutable
{
    $trimmed = trim($timestamp);
    if ($trimmed === '') {
        return null;
    }
    try {
        return new DateTimeImmutable($trimmed, new DateTimeZone('Asia/Kolkata'));
    } catch (Throwable $e) {
        return null;
    }
}

function dashboard_collect_error_summary(DateTimeImmutable $todayStart, DateTimeImmutable $last24hStart): array
{
    $summary = [
        'errorsToday' => 0,
        'errors24h' => 0,
        'lastErrorAt' => null,
        'lastErrorSource' => null,
    ];

    $today = $todayStart->format('Y-m-d');
    $yesterday = $todayStart->sub(new DateInterval('P1D'))->format('Y-m-d');

    $paths = [
        DATA_PATH . '/logs/site.log',
        DATA_PATH . '/logs/assisted_v2.log',
        DATA_PATH . '/logs/print.log',
        DATA_PATH . '/logs/tender_discovery.log',
        DATA_PATH . '/logs/php_errors.log',
        DATA_PATH . '/logs/runtime_errors/' . $today . '.jsonl',
        DATA_PATH . '/logs/runtime_errors/' . $yesterday . '.jsonl',
    ];

    foreach ($paths as $path) {
        if (!file_exists($path)) {
            continue;
        }
        $handle = fopen($path, 'r');
        if (!$handle) {
            continue;
        }
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            $isRuntime = str_contains($path, '/runtime_errors/');
            $isError = $isRuntime || dashboard_line_is_error($decoded, $line);
            if (!$isError) {
                continue;
            }
            $timestamp = dashboard_extract_log_timestamp($decoded, $line);
            if ($timestamp) {
                if ($timestamp >= $todayStart) {
                    $summary['errorsToday']++;
                }
                if ($timestamp >= $last24hStart) {
                    $summary['errors24h']++;
                }
                if ($summary['lastErrorAt'] === null || $timestamp > $summary['lastErrorAt']) {
                    $summary['lastErrorAt'] = $timestamp;
                    $summary['lastErrorSource'] = basename($path);
                }
            }
        }
        fclose($handle);
    }

    return $summary;
}

function dashboard_line_is_error($decoded, string $line): bool
{
    if (is_array($decoded)) {
        $level = strtolower((string)($decoded['level'] ?? ''));
        $event = strtolower((string)($decoded['event'] ?? ''));
        $message = strtolower((string)($decoded['message'] ?? ''));
        if (in_array($level, ['error', 'fatal', 'exception'], true)) {
            return true;
        }
        if ($event !== '' && (str_contains($event, 'error') || str_contains($event, 'fail'))) {
            return true;
        }
        if ($message !== '' && (str_contains($message, 'error') || str_contains($message, 'fatal') || str_contains($message, 'exception'))) {
            return true;
        }
        return false;
    }

    return (bool)preg_match('/(error|fatal|exception|parse)/i', $line);
}

function dashboard_extract_log_timestamp($decoded, string $line): ?DateTimeImmutable
{
    if (is_array($decoded)) {
        $candidates = [
            $decoded['timestamp'] ?? null,
            $decoded['at'] ?? null,
            $decoded['createdAt'] ?? null,
            $decoded['updatedAt'] ?? null,
            $decoded['finishedAt'] ?? null,
            $decoded['startedAt'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate)) {
                $parsed = dashboard_parse_datetime($candidate);
                if ($parsed) {
                    return $parsed;
                }
            }
        }
    }

    if (preg_match('/(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})/', $line, $matches)) {
        return dashboard_parse_datetime($matches[1]);
    }

    if (preg_match('/\[(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
        return dashboard_parse_datetime($matches[1]);
    }

    return null;
}

function dashboard_last_backup_status(string $logPath): ?string
{
    if (!file_exists($logPath)) {
        return null;
    }
    $handle = fopen($logPath, 'r');
    if (!$handle) {
        return null;
    }
    $lastEvent = null;
    while (($line = fgets($handle)) !== false) {
        $decoded = json_decode(trim($line), true);
        if (is_array($decoded)) {
            $lastEvent = $decoded['event'] ?? $lastEvent;
        }
    }
    fclose($handle);

    if ($lastEvent === 'backup_failed') {
        return 'failed';
    }
    if ($lastEvent === 'backup_completed') {
        return 'ok';
    }
    if ($lastEvent === 'backup_started') {
        return 'running';
    }
    return $lastEvent ? 'unknown' : null;
}
