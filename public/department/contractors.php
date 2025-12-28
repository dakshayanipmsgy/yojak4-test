<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    if (($user['roleId'] ?? '') !== 'admin') {
        render_error_page('Admin access required.');
        return;
    }
    $deptId = $user['deptId'] ?? '';
    ensure_department_env($deptId);

    $links = load_department_contractor_links($deptId);
    usort($links, fn($a, $b) => strcmp($b['linkedAt'] ?? '', $a['linkedAt'] ?? ''));

    $title = get_app_config()['appName'] . ' | Contractors';
    render_layout($title, function () use ($links) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0 0 6px 0;"><?= sanitize('Linked Contractors'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Manage contractor access and status.'); ?></p>
                </div>
                <a class="btn secondary" href="/department/dashboard.php"><?= sanitize('Dashboard'); ?></a>
            </div>
            <form method="post" action="/department/contractor_link_by_yoj.php" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="text" name="yojId" placeholder="YOJ-XXXXX" required style="min-width:180px;">
                <button class="btn" type="submit"><?= sanitize('Link contractor'); ?></button>
            </form>
            <table style="margin-top:14px;">
                <thead>
                    <tr>
                        <th><?= sanitize('YOJ ID'); ?></th>
                        <th><?= sanitize('Dept Contractor ID'); ?></th>
                        <th><?= sanitize('Status'); ?></th>
                        <th><?= sanitize('Linked At'); ?></th>
                        <th><?= sanitize('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$links): ?>
                        <tr><td colspan="5" class="muted"><?= sanitize('No contractors linked yet.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($links as $link): ?>
                            <tr>
                                <td><?= sanitize($link['yojId'] ?? ''); ?></td>
                                <td><?= sanitize($link['deptContractorId'] ?? ''); ?></td>
                                <td><?= sanitize(ucfirst($link['status'] ?? '')); ?></td>
                                <td><?= sanitize($link['linkedAt'] ?? ''); ?></td>
                                <td>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                        <?php if (($link['status'] ?? '') === 'active'): ?>
                                            <form method="post" action="/department/contractor_link_action.php">
                                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                                <input type="hidden" name="yojId" value="<?= sanitize($link['yojId'] ?? ''); ?>">
                                                <input type="hidden" name="action" value="suspend">
                                                <button class="btn secondary" type="submit"><?= sanitize('Suspend'); ?></button>
                                            </form>
                                            <form method="post" action="/department/contractor_link_action.php">
                                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                                <input type="hidden" name="yojId" value="<?= sanitize($link['yojId'] ?? ''); ?>">
                                                <input type="hidden" name="action" value="revoke">
                                                <button class="btn danger" type="submit"><?= sanitize('Revoke'); ?></button>
                                            </form>
                                        <?php elseif (($link['status'] ?? '') === 'suspended' || ($link['status'] ?? '') === 'revoked'): ?>
                                            <form method="post" action="/department/contractor_link_action.php">
                                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                                <input type="hidden" name="yojId" value="<?= sanitize($link['yojId'] ?? ''); ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button class="btn" type="submit"><?= sanitize('Activate'); ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
