<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $employee = require_active_employee();

    $features = [
        ['key' => 'tickets', 'title' => 'Support Tickets', 'description' => 'Assist departments without opening documents.', 'link' => '/staff/tickets.php'],
        ['key' => 'audit_view', 'title' => 'Audit Logs', 'description' => 'View metadata to monitor activity.', 'link' => '/staff/audit.php'],
        ['key' => 'reset_approvals', 'title' => 'Reset Approvals', 'description' => 'Coordinate password reset approvals.', 'link' => '/superadmin/reset_requests.php'],
        ['key' => 'stats_view', 'title' => 'Platform Stats', 'description' => 'Counts only, no content.', 'link' => '/superadmin/stats.php'],
        ['key' => 'can_process_assisted', 'title' => 'Assisted Packs v2', 'description' => 'Process contractor assisted pack requests.', 'link' => '/staff/assisted_v2/queue.php'],
    ];

    $title = get_app_config()['appName'] . ' | Staff Dashboard';
    render_layout($title, function () use ($employee, $features) {
        ?>
        <div class="card">
            <h2 style="margin-bottom:6px;"><?= sanitize('Yojak Staff Workspace'); ?></h2>
            <p class="muted" style="margin-top:0;"><?= sanitize('Welcome, ' . ($employee['username'] ?? '') . '. Access is scoped by permissions. Department documents stay protected.'); ?></p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-top:12px;">
                <?php foreach ($features as $feature): ?>
                    <?php if (employee_has_permission($employee, $feature['key'])): ?>
                        <a class="card" href="<?= sanitize($feature['link']); ?>" style="display:block;border:1px solid var(--border);">
                            <div class="muted" style="text-transform:uppercase;font-size:12px;letter-spacing:0.06em;"><?= sanitize($feature['title']); ?></div>
                            <div style="margin-top:6px;"><?= sanitize($feature['description']); ?></div>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
