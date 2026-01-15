<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../lib/superadmin_dashboard_counts.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $counts = superadmin_dashboard_counts();
    $timezone = get_app_config()['timezone'] ?? 'Asia/Kolkata';
    $now = now_kolkata();

    $title = get_app_config()['appName'] . ' | ' . t('dashboard');
    render_layout($title, function () use ($user, $counts, $timezone, $now) {
        $assistedWaiting = $counts['assistedNew'] + $counts['assistedInProgress'];
        $lastErrorAt = $counts['lastErrorAt'] instanceof DateTimeImmutable
            ? $counts['lastErrorAt']->format('d M Y, H:i')
            : ($counts['lastErrorAt'] ? (string)$counts['lastErrorAt'] : '');
        $assistedLastAt = $counts['assistedLastAt'] instanceof DateTimeImmutable
            ? $counts['assistedLastAt']->format('d M Y, H:i')
            : ($counts['assistedLastAt'] ? (string)$counts['assistedLastAt'] : '');
        $formatDate = function (?string $value): ?string {
            if (!$value) {
                return null;
            }
            try {
                return (new DateTimeImmutable($value, new DateTimeZone('Asia/Kolkata')))->format('d M Y, H:i');
            } catch (Throwable $e) {
                return null;
            }
        };
        $tenderLastRunAt = $formatDate(is_string($counts['tenderLastRunAt']) ? $counts['tenderLastRunAt'] : null);
        $backupsLastAt = $formatDate(is_string($counts['backupsLastAt']) ? $counts['backupsLastAt'] : null);

        $badgeClass = function (int $value, int $warnThreshold = 1, int $dangerThreshold = 5): string {
            if ($value >= $dangerThreshold) {
                return 'badge-danger';
            }
            if ($value >= $warnThreshold) {
                return 'badge-warn';
            }
            return 'badge-ok';
        };

        $cards = [
            [
                'title' => 'Contractor Approvals',
                'value' => $counts['contractorPendingApprovals'],
                'description' => 'Pending contractor signups awaiting review.',
                'secondary' => 'Approved total: ' . $counts['contractorApprovedTotal'],
                'link' => '/superadmin/contractors.php?tab=pending',
                'badge' => $badgeClass((int)$counts['contractorPendingApprovals'], 1, 5),
            ],
            [
                'title' => 'Reset Approvals',
                'value' => $counts['resetApprovalsPending'],
                'description' => 'Password reset approvals waiting for action.',
                'secondary' => 'Requested today: ' . $counts['resetApprovalsToday'],
                'link' => '/superadmin/reset_requests.php?status=pending',
                'badge' => $badgeClass((int)$counts['resetApprovalsPending'], 1, 4),
            ],
            [
                'title' => 'Assisted Pack v2 Queue',
                'value' => $assistedWaiting,
                'description' => 'New + in-progress assisted pack requests.',
                'secondary' => $assistedLastAt ? 'Last request: ' . $assistedLastAt : 'No recent requests.',
                'link' => '/superadmin/assisted_v2/queue.php?status=pending',
                'badge' => $badgeClass($assistedWaiting, 1, 6),
            ],
            [
                'title' => 'Assisted Pack v2 Failed',
                'value' => $counts['assistedFailed'],
                'description' => 'Requests marked failed or rejected.',
                'secondary' => 'Review and reassign quickly.',
                'link' => '/superadmin/assisted_v2/queue.php?status=rejected',
                'badge' => $badgeClass((int)$counts['assistedFailed'], 1, 3),
            ],
            [
                'title' => 'Support Inbox',
                'value' => $counts['supportOpen'],
                'description' => 'Open support tickets needing responses.',
                'secondary' => 'Handle escalations promptly.',
                'link' => '/superadmin/support_dashboard.php',
                'badge' => $badgeClass((int)$counts['supportOpen'], 1, 5),
            ],
            [
                'title' => 'Errors (Last 24h)',
                'value' => $counts['errors24h'],
                'description' => 'System/runtime errors in the past 24 hours.',
                'secondary' => $lastErrorAt ? 'Last error: ' . $lastErrorAt . ' (' . ($counts['lastErrorSource'] ?? 'log') . ')' : 'No recent errors logged.',
                'link' => '/superadmin/error_log.php',
                'badge' => $badgeClass((int)$counts['errors24h'], 1, 4),
            ],
            [
                'title' => 'Tender Discovery',
                'value' => $counts['tenderNewFoundLastRun'],
                'description' => 'New tenders found in the last discovery run.',
                'secondary' => $tenderLastRunAt ? 'Last run: ' . $tenderLastRunAt : 'Never run yet.',
                'link' => '/superadmin/tender_discovery.php',
                'badge' => match ($counts['tenderLastRunStatus']) {
                    'failed' => 'badge-danger',
                    'never' => 'badge-warn',
                    default => 'badge-ok',
                },
            ],
            [
                'title' => 'Department Admin Issues',
                'value' => $counts['departmentsAdminIssues'],
                'description' => 'Departments missing or mismatched admin records.',
                'secondary' => 'Total departments: ' . $counts['departmentsTotal'],
                'link' => '/superadmin/departments.php',
                'badge' => $badgeClass((int)$counts['departmentsAdminIssues'], 1, 3),
            ],
            [
                'title' => 'Department Link Requests',
                'value' => $counts['deptPendingLinkRequests'],
                'description' => 'Pending contractor link requests across departments.',
                'secondary' => 'Review department-contractor links.',
                'link' => '/superadmin/departments.php',
                'badge' => $badgeClass((int)$counts['deptPendingLinkRequests'], 1, 4),
            ],
            [
                'title' => 'Employees',
                'value' => $counts['employeesTotal'],
                'description' => 'Active employee accounts on the platform.',
                'secondary' => $counts['employeesDisabled'] > 0
                    ? 'Disabled: ' . $counts['employeesDisabled']
                    : 'All employees active.',
                'link' => '/superadmin/employees.php',
                'badge' => $badgeClass((int)$counts['employeesDisabled'], 1, 3),
            ],
            [
                'title' => 'Backups',
                'value' => $counts['backupsCount'],
                'description' => 'Backup archives stored in /data/backups.',
                'secondary' => $backupsLastAt ? 'Last backup: ' . $backupsLastAt : 'No backups created yet.',
                'link' => '/superadmin/backup.php',
                'badge' => $counts['backupsLastStatus'] === 'failed'
                    ? 'badge-danger'
                    : ($counts['backupsLastAt'] ? 'badge-ok' : 'badge-warn'),
            ],
        ];
        ?>
        <style>
            .dashboard-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
            }
            .dashboard-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 16px;
                margin-top: 16px;
            }
            .dashboard-card {
                display: flex;
                flex-direction: column;
                gap: 10px;
                height: 100%;
            }
            .dashboard-card h3 {
                margin: 0;
                font-size: 1.05rem;
            }
            .dashboard-value {
                font-size: 2.1rem;
                font-weight: 700;
                color: #0f172a;
            }
            .dashboard-meta {
                font-size: 0.88rem;
                color: var(--muted);
            }
            .badge {
                display: inline-flex;
                align-items: center;
                padding: 4px 10px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.04em;
                text-transform: uppercase;
            }
            .badge-ok {
                background: #ecfdf3;
                color: #166534;
                border: 1px solid #86efac;
            }
            .badge-warn {
                background: #fff7ed;
                color: #9a3412;
                border: 1px solid #fdba74;
            }
            .badge-danger {
                background: #fef2f2;
                color: #991b1b;
                border: 1px solid #fecaca;
            }
            @media (max-width: 720px) {
                .dashboard-value {
                    font-size: 1.8rem;
                }
            }
        </style>
        <div class="card">
            <div class="dashboard-header">
                <div>
                    <h2 style="margin-bottom:6px;">Superadmin Dashboard</h2>
                    <p class="muted" style="margin:0;">Welcome back, <?= sanitize($user['username'] ?? ''); ?>. Monitor pending actions and system health at a glance.</p>
                </div>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <span class="pill">Timezone: <?= sanitize($timezone); ?> • <?= sanitize($now->format('d M Y, H:i')); ?></span>
                    <a class="btn secondary" href="/superadmin/dashboard.php">Refresh</a>
                </div>
            </div>
        </div>
        <div class="card" style="margin-top:16px;">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h3 style="margin-bottom:4px;">Pending Actions</h3>
                    <p class="muted" style="margin:0;">Focus on items that require immediate attention across the platform.</p>
                </div>
                <span class="pill">Live counts from filesystem JSON</span>
            </div>
            <div class="dashboard-grid">
                <?php foreach ($cards as $card): ?>
                    <div class="card dashboard-card" style="box-shadow:none;">
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
                            <h3><?= sanitize($card['title']); ?></h3>
                            <span class="badge <?= sanitize($card['badge']); ?>"><?= sanitize($card['badge'] === 'badge-ok' ? 'OK' : ($card['badge'] === 'badge-warn' ? 'Attention' : 'Critical')); ?></span>
                        </div>
                        <div class="dashboard-value"><?= sanitize((string)$card['value']); ?></div>
                        <div class="dashboard-meta"><?= sanitize($card['description']); ?></div>
                        <div class="dashboard-meta"><?= sanitize($card['secondary']); ?></div>
                        <div style="margin-top:auto;">
                            <a class="btn secondary" href="<?= sanitize($card['link']); ?>">View</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
