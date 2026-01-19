<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    try {
        require_role('superadmin');

        $pendingContractors = list_pending_contractors();
        $approvedContractors = contractors_index();
        $departments = departments_index();
        $employees = staff_employee_index();

        $departmentAdminIssues = 0;
        foreach ($departments as $deptEntry) {
            $deptId = $deptEntry['deptId'] ?? '';
            if ($deptId === '') {
                continue;
            }
            $department = load_department($deptId);
            if (!$department) {
                continue;
            }
            if (empty($department['activeAdminUserId'])) {
                $departmentAdminIssues++;
            }
        }

        $title = get_app_config()['appName'] . ' | Users Hub';

        render_layout($title, function () use ($pendingContractors, $approvedContractors, $departments, $departmentAdminIssues, $employees) {
            ?>
        <style>
            .users-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 16px;
                margin-top: 16px;
            }
            .users-card {
                display: grid;
                gap: 10px;
                padding: 18px;
                border-radius: 16px;
                border: 1px solid var(--border);
                background: #fff;
                box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
            }
            .users-card h3 { margin: 0; }
            .users-meta { margin: 0; color: var(--muted); font-size: 13px; }
            .users-pill {
                display: inline-flex;
                align-items: center;
                padding: 4px 10px;
                border-radius: 999px;
                background: #eef2ff;
                color: #3730a3;
                font-size: 12px;
                font-weight: 700;
            }
            .users-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        </style>

        <div class="card" style="margin-bottom:16px;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Users Hub</h2>
                    <p class="muted" style="margin:6px 0 0;">Manage superadmin profile, departments, contractors, and employees in one place.</p>
                </div>
            </div>
        </div>

        <div class="users-grid">
            <div class="users-card">
                <h3>Superadmin</h3>
                <p class="users-meta">Your account, security basics, and branding settings.</p>
                <span class="users-pill">Profile & Branding</span>
                <div class="users-actions">
                    <a class="btn" href="/superadmin/profile.php">Open</a>
                </div>
            </div>
            <div class="users-card">
                <h3>Departments</h3>
                <p class="users-meta">Departments onboarded for tender publication and contractor visibility.</p>
                <span class="users-pill">Total: <?= count($departments); ?></span>
                <span class="users-pill" style="background:#fef3c7; color:#92400e;">Admin issues: <?= $departmentAdminIssues; ?></span>
                <div class="users-actions">
                    <a class="btn" href="/superadmin/departments.php">Open</a>
                </div>
            </div>
            <div class="users-card">
                <h3>Contractors</h3>
                <p class="users-meta">Approve new contractors and review active contractors.</p>
                <span class="users-pill">Pending approvals: <?= count($pendingContractors); ?></span>
                <span class="users-pill" style="background:#ecfdf3; color:#166534;">Total: <?= count($approvedContractors); ?></span>
                <div class="users-actions">
                    <a class="btn" href="/superadmin/contractors.php">Open</a>
                </div>
            </div>
            <div class="users-card">
                <h3>Employees</h3>
                <p class="users-meta">Internal employees with RBAC for assisted workflows.</p>
                <span class="users-pill">Total: <?= count($employees); ?></span>
                <div class="users-actions">
                    <a class="btn" href="/superadmin/employees.php">Open</a>
                </div>
            </div>
        </div>
            <?php
        });
    } catch (Throwable $e) {
        logEvent(DATA_PATH . '/logs/site.log', [
            'event' => 'superadmin_users_hub_error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user' => current_user(),
        ]);
        throw $e;
    }
});
