<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $deptId = $user['deptId'] ?? '';
    ensure_department_env($deptId);
    $role = find_department_role($deptId, $user['roleId'] ?? '');
    $permissions = is_array($role['permissions'] ?? null) ? $role['permissions'] : [];
    $hasPermission = function (string $permission) use ($permissions): bool {
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    };
    $isAdmin = (($role['roleId'] ?? ($user['roleId'] ?? '')) === 'admin') || in_array('*', $permissions, true);
    $now = now_kolkata();
    $timezone = get_app_config()['timezone'] ?? 'Asia/Kolkata';

    $pendingLinks = 0;
    if ($isAdmin) {
        foreach (load_department_contractor_requests($deptId) as $request) {
            if (($request['status'] ?? '') === 'pending') {
                $pendingLinks++;
            }
        }
    }

    $docsInbox = 0;
    $docsAwaitingSign = 0;
    if ($hasPermission('docs_workflow')) {
        foreach (list_department_docs($deptId) as $doc) {
            $status = $doc['status'] ?? '';
            if ($status === 'inbox') {
                $docsInbox++;
            }
            $needsSign = !empty($doc['requiresSign']) || !empty($doc['needsSign']) || !empty($doc['signRequired']) || !empty($doc['awaitingSignature']);
            if ($needsSign || in_array($status, ['awaiting_signature', 'needs_sign', 'pending_sign', 'sign_required'], true)) {
                $docsAwaitingSign++;
            }
        }
    }

    $tendersClosingSoon = 0;
    if ($hasPermission('manage_tenders')) {
        $cutoff = $now->modify('+7 days');
        foreach (load_department_tenders($deptId) as $tender) {
            $status = strtolower((string)($tender['status'] ?? ''));
            if (in_array($status, ['archived', 'closed', 'cancelled'], true)) {
                continue;
            }
            $submission = $tender['submissionDate'] ?? ($tender['dates']['submission'] ?? null);
            if (!$submission) {
                continue;
            }
            try {
                $submissionAt = new DateTimeImmutable((string)$submission, new DateTimeZone('Asia/Kolkata'));
            } catch (Throwable $e) {
                continue;
            }
            if ($submissionAt >= $now && $submissionAt <= $cutoff) {
                $tendersClosingSoon++;
            }
        }
    }

    $workordersActive = 0;
    if ($hasPermission('manage_workorders')) {
        foreach (load_department_workorders($deptId) as $entry) {
            $woId = $entry['woId'] ?? '';
            if ($woId === '') {
                continue;
            }
            $workorder = load_department_workorder($deptId, $woId) ?? $entry;
            $status = strtolower((string)($workorder['status'] ?? 'active'));
            if (!in_array($status, ['closed', 'completed', 'archived'], true)) {
                $workordersActive++;
            }
        }
    }

    $userRequests = 0;
    if ($hasPermission('manage_users')) {
        foreach (load_password_reset_requests($deptId) as $request) {
            if (($request['status'] ?? '') === 'pending') {
                $userRequests++;
            }
        }
    }

    $templatesPending = 0;
    if ($hasPermission('manage_templates')) {
        foreach (load_department_templates($deptId) as $template) {
            $status = strtolower((string)($template['status'] ?? ''));
            if (in_array($status, ['draft', 'pending', 'review'], true)) {
                $templatesPending++;
            }
        }
    }

    $dakPending = 0;
    if ($hasPermission('manage_dak')) {
        foreach (load_dak_index($deptId) as $item) {
            $status = strtolower((string)($item['status'] ?? ''));
            $location = strtolower((string)($item['currentLocation'] ?? ''));
            if ($status === 'pending' || in_array($location, ['pending', 'inbox', 'new'], true)) {
                $dakPending++;
            }
        }
    }

    logEvent(DATA_PATH . '/logs/department_dashboard.log', [
        'at' => $now->format(DateTime::ATOM),
        'deptId' => $deptId,
        'event' => 'DASH_COUNTS',
        'pendingLinks' => $pendingLinks,
        'docsInbox' => $docsInbox,
        'docsAwaitingSign' => $docsAwaitingSign,
        'tendersClosingSoon' => $tendersClosingSoon,
        'workordersActive' => $workordersActive,
    ]);

    $cards = [];
    if ($isAdmin) {
        $cards[] = [
            'title' => 'Contractor Link Requests Pending',
            'value' => $pendingLinks,
            'hint' => 'Review pending contractor link approvals.',
            'link' => '/department/contractor_requests.php?filter=pending',
        ];
    }
    if ($hasPermission('docs_workflow')) {
        $cards[] = [
            'title' => 'Docs Inbox Pending',
            'value' => $docsInbox,
            'hint' => 'Incoming documents awaiting your action.',
            'link' => '/department/docs_inbox.php?filter=pending',
        ];
        $cards[] = [
            'title' => 'Docs Awaiting Signature',
            'value' => $docsAwaitingSign,
            'hint' => 'Documents flagged for signature.',
            'link' => '/department/docs_inbox.php?filter=need_sign',
        ];
    }
    if ($hasPermission('manage_tenders')) {
        $cards[] = [
            'title' => 'Tenders Closing Soon (7 days)',
            'value' => $tendersClosingSoon,
            'hint' => 'Submission deadlines within the next week.',
            'link' => '/department/tenders.php?filter=closing_soon',
        ];
    }
    if ($hasPermission('manage_workorders')) {
        $cards[] = [
            'title' => 'Workorders Active',
            'value' => $workordersActive,
            'hint' => 'Active workorders with open milestones.',
            'link' => '/department/workorders.php?filter=active',
        ];
    }
    if ($hasPermission('manage_users')) {
        $cards[] = [
            'title' => 'User Requests',
            'value' => $userRequests,
            'hint' => 'Pending password reset or user requests.',
            'link' => '/department/users.php?filter=requests',
        ];
    }
    if ($hasPermission('manage_templates')) {
        $cards[] = [
            'title' => 'Templates Pending Review',
            'value' => $templatesPending,
            'hint' => 'Draft templates awaiting publish.',
            'link' => '/department/templates.php?filter=drafts',
        ];
    }
    if ($hasPermission('manage_dak')) {
        $cards[] = [
            'title' => 'DAK Pending Movement',
            'value' => $dakPending,
            'hint' => 'Incoming DAK items awaiting movement.',
            'link' => '/department/dak.php?filter=pending',
        ];
    }

    $title = get_app_config()['appName'] . ' | Department Dashboard';
    render_layout($title, function () use ($user, $role, $cards, $timezone, $now, $hasPermission, $isAdmin) {
        ?>
        <style>
            .dept-dashboard-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
            }
            .dept-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            .dept-actions .btn {
                white-space: nowrap;
            }
            .dept-cards {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 16px;
                margin-top: 16px;
            }
            .dept-card {
                display: flex;
                flex-direction: column;
                gap: 8px;
                height: 100%;
            }
            .dept-card-title {
                font-size: 1rem;
                margin: 0;
                color: #0f172a;
            }
            .dept-card-value {
                font-size: 2rem;
                font-weight: 700;
                color: #111827;
            }
            .dept-card-hint {
                font-size: 0.9rem;
                color: var(--muted);
            }
            @media (max-width: 720px) {
                .dept-card-value {
                    font-size: 1.7rem;
                }
            }
            .suggestion-cta {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 16px;
                flex-wrap: wrap;
            }
            .suggestion-cta h3 {
                margin: 0 0 6px 0;
                color: #0f172a;
            }
            .suggestion-cta p {
                margin: 0;
                color: var(--muted);
            }
        </style>
        <div class="card">
            <div class="dept-dashboard-header">
                <div>
                    <h2 style="margin-bottom:6px;"><?= sanitize('Department Dashboard'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Welcome back, ' . ($user['displayName'] ?? $user['username'] ?? '')); ?></p>
                </div>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <span class="pill" style="background:#eef2ff;color:#1f2a44;font-weight:600;">
                        <?= sanitize('Role: ' . ($role['roleId'] ?? ($user['roleId'] ?? 'Unknown'))); ?>
                    </span>
                    <span class="pill"><?= sanitize('Timezone: ' . $timezone . ' • ' . $now->format('d M Y, H:i')); ?></span>
                </div>
            </div>
            <div style="display:grid;gap:12px;margin-top:12px;">
                <div class="pill" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                    <span><?= sanitize('Department: ' . ($user['deptId'] ?? '')); ?></span>
                    <span style="opacity:0.7;">•</span>
                    <span><?= sanitize('User ID: ' . ($user['username'] ?? '')); ?></span>
                </div>
                <div class="pill secondary" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                    <span><?= sanitize('Last login: ' . (($user['lastLoginAt'] ?? '') !== '' ? ($user['lastLoginAt']) : 'First login')); ?></span>
                    <?php if (!empty($role['nameEn'])): ?>
                        <span style="opacity:0.7;">•</span>
                        <span><?= sanitize('Role Name: ' . ($role['nameEn'] ?? '')); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <p class="muted" style="margin-top:14px;"><?= sanitize('Use the quick actions and pending cards to stay on top of department operations.'); ?></p>
        </div>
        <div class="card" style="margin-top:16px;">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h3 style="margin-bottom:4px;"><?= sanitize('Quick Actions'); ?></h3>
                    <p class="muted" style="margin:0;"><?= sanitize('Create new items fast based on your permissions.'); ?></p>
                </div>
                <div class="dept-actions">
                    <?php if ($hasPermission('manage_tenders')): ?>
                        <a class="btn" href="/department/tender_create.php"><?= sanitize('Create Tender'); ?></a>
                    <?php endif; ?>
                    <?php if ($hasPermission('manage_workorders')): ?>
                        <a class="btn" href="/department/workorder_create.php"><?= sanitize('Create Workorder'); ?></a>
                    <?php endif; ?>
                    <?php if ($hasPermission('generate_docs')): ?>
                        <a class="btn" href="/department/create_docs.php"><?= sanitize('Create Docs'); ?></a>
                        <a class="btn" href="/department/quick_doc.php"><?= sanitize('Quick Doc Studio'); ?></a>
                    <?php endif; ?>
                    <?php if ($isAdmin && $hasPermission('manage_users')): ?>
                        <a class="btn secondary" href="/department/user_create.php"><?= sanitize('Create User'); ?></a>
                    <?php endif; ?>
                    <a class="btn secondary" href="/department/support.php"><?= sanitize('Report Issue'); ?></a>
                </div>
            </div>
        </div>
        <div class="card" style="margin-top:16px;">
            <div class="suggestion-cta">
                <div>
                    <h3><?= sanitize('Help us improve YOJAK'); ?></h3>
                    <p><?= sanitize('We’re building YOJAK to fulfill all your needs. Please share what features you want, or what is confusing, so we can improve.'); ?></p>
                </div>
                <a class="btn" href="/suggestions/new.php?page=/department/dashboard.php"><?= sanitize('Share a Suggestion'); ?></a>
            </div>
        </div>
        <div class="card" style="margin-top:16px;">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h3 style="margin-bottom:4px;"><?= sanitize('Pending Actions'); ?></h3>
                    <p class="muted" style="margin:0;"><?= sanitize('Operational queues visible to your role.'); ?></p>
                </div>
                <span class="pill"><?= sanitize('Live counts from filesystem JSON'); ?></span>
            </div>
            <div class="dept-cards">
                <?php if (!$cards): ?>
                    <div class="card dept-card" style="box-shadow:none;">
                        <h3 class="dept-card-title"><?= sanitize('No pending actions'); ?></h3>
                        <div class="dept-card-hint"><?= sanitize('You do not have access to operational queues or there are no pending items.'); ?></div>
                    </div>
                <?php else: ?>
                    <?php foreach ($cards as $card): ?>
                        <div class="card dept-card" style="box-shadow:none;">
                            <h3 class="dept-card-title"><?= sanitize($card['title']); ?></h3>
                            <div class="dept-card-value"><?= sanitize((string)$card['value']); ?></div>
                            <div class="dept-card-hint"><?= sanitize($card['hint']); ?></div>
                            <div style="margin-top:auto;">
                                <a class="btn secondary" href="<?= sanitize($card['link']); ?>"><?= sanitize('Open'); ?></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    });
});
