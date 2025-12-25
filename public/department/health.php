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
    require_department_permission($user, 'run_health');

    $issues = department_health_scan($deptId);
    $repairs = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $repairs = department_health_repair($deptId, $issues);
        $issues = department_health_scan($deptId);
        if ($repairs) {
            set_flash('success', 'Repair attempted. See log for details.');
        } else {
            set_flash('success', 'No repairs needed.');
        }
    }

    $title = get_app_config()['appName'] . ' | Health';
    render_layout($title, function () use ($issues, $repairs) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Health Check'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Scans and repairs JSON to avoid 500s. Backups stored alongside files.'); ?></p>
                </div>
                <form method="post" action="/department/health.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <button class="btn secondary" type="submit"><?= sanitize('Repair Issues'); ?></button>
                </form>
            </div>
            <h3 style="margin-top:12px;"><?= sanitize('Detected Issues'); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('Path'); ?></th>
                        <th><?= sanitize('Error'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$issues): ?>
                        <tr><td colspan="2" class="muted"><?= sanitize('No issues detected.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($issues as $issue): ?>
                            <tr>
                                <td><?= sanitize($issue['path'] ?? ''); ?></td>
                                <td><?= sanitize($issue['error'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($repairs): ?>
                <h3 style="margin-top:12px;"><?= sanitize('Repairs'); ?></h3>
                <table>
                    <thead>
                        <tr>
                            <th><?= sanitize('Path'); ?></th>
                            <th><?= sanitize('Backup'); ?></th>
                            <th><?= sanitize('Status'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($repairs as $repair): ?>
                            <tr>
                                <td><?= sanitize($repair['path'] ?? ''); ?></td>
                                <td><?= sanitize($repair['backup'] ?? ''); ?></td>
                                <td><?= sanitize($repair['status'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    });
});
