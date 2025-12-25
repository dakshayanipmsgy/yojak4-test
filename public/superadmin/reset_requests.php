<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('reset_approvals');
    if (($actor['type'] ?? '') === 'superadmin' && !empty($actor['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $requests = load_all_password_reset_requests();
    usort($requests, fn($a, $b) => strcmp($b['requestedAt'] ?? '', $a['requestedAt'] ?? ''));

    $title = get_app_config()['appName'] . ' | Reset Approvals';
    render_layout($title, function () use ($requests) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Password Reset Requests'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Approve or reject department admin/user reset requests. No document access involved.'); ?></p>
                </div>
                <div class="pill"><?= sanitize('Pending only: secure approvals & audit.'); ?></div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('Request'); ?></th>
                        <th><?= sanitize('User'); ?></th>
                        <th><?= sanitize('Dept'); ?></th>
                        <th><?= sanitize('Status'); ?></th>
                        <th><?= sanitize('Requested At'); ?></th>
                        <th><?= sanitize('Decided'); ?></th>
                        <th><?= sanitize('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$requests): ?>
                        <tr><td colspan="7" class="muted"><?= sanitize('No reset requests found.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td>
                                    <div><?= sanitize($req['requestId'] ?? ''); ?></div>
                                    <div class="muted"><?= sanitize($req['requestedBy'] ?? ''); ?></div>
                                </td>
                                <td><?= sanitize($req['fullUserId'] ?? ''); ?></td>
                                <td><span class="tag"><?= sanitize($req['deptId'] ?? ''); ?></span></td>
                                <td><span class="tag <?= ($req['status'] ?? '') === 'pending' ? 'success' : ''; ?>"><?= sanitize(ucfirst($req['status'] ?? '')); ?></span></td>
                                <td><?= sanitize($req['requestedAt'] ?? ''); ?></td>
                                <td>
                                    <?php if (!empty($req['decidedAt'])): ?>
                                        <div><?= sanitize($req['decidedAt']); ?></div>
                                        <div class="muted"><?= sanitize($req['decidedBy'] ?? ''); ?></div>
                                    <?php else: ?>
                                        <span class="muted"><?= sanitize('Pending'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <?php if (($req['status'] ?? '') === 'pending'): ?>
                                        <form method="post" action="/superadmin/reset_approve.php">
                                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                            <input type="hidden" name="requestId" value="<?= sanitize($req['requestId'] ?? ''); ?>">
                                            <button class="btn" type="submit"><?= sanitize('Approve'); ?></button>
                                        </form>
                                        <form method="post" action="/superadmin/reset_reject.php">
                                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                            <input type="hidden" name="requestId" value="<?= sanitize($req['requestId'] ?? ''); ?>">
                                            <button class="btn secondary" type="submit"><?= sanitize('Reject'); ?></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted"><?= sanitize('Closed'); ?></span>
                                    <?php endif; ?>
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
