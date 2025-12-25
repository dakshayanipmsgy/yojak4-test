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
    require_department_permission($user, 'manage_workorders');

    $workorders = load_department_workorders($deptId);
    $title = get_app_config()['appName'] . ' | Workorders';
    render_layout($title, function () use ($workorders) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Workorders'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Link workorders to tenders.'); ?></p>
                </div>
                <a class="btn" href="/department/workorder_create.php"><?= sanitize('Create Workorder'); ?></a>
            </div>
            <table style="margin-top:12px;">
                <thead>
                    <tr>
                        <th><?= sanitize('ID'); ?></th>
                        <th><?= sanitize('Title'); ?></th>
                        <th><?= sanitize('Tender'); ?></th>
                        <th><?= sanitize('Updated'); ?></th>
                        <th><?= sanitize('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$workorders): ?>
                        <tr><td colspan="5" class="muted"><?= sanitize('No workorders yet.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($workorders as $wo): ?>
                            <tr>
                                <td><?= sanitize($wo['woId'] ?? ''); ?></td>
                                <td><?= sanitize($wo['title'] ?? ''); ?></td>
                                <td><?= sanitize($wo['tenderId'] ?? ''); ?></td>
                                <td><?= sanitize($wo['updatedAt'] ?? ''); ?></td>
                                <td><a class="btn secondary" href="/department/workorder_view.php?id=<?= urlencode($wo['woId'] ?? ''); ?>"><?= sanitize('View'); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
