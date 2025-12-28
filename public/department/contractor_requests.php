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

    $requests = load_department_contractor_requests($deptId);
    usort($requests, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
    $pending = [];
    foreach ($requests as $entry) {
        if (($entry['status'] ?? '') !== 'pending') {
            continue;
        }
        $detail = load_department_contractor_request($deptId, $entry['requestId'] ?? '');
        if ($detail) {
            $pending[] = $detail;
        }
    }

    $title = get_app_config()['appName'] . ' | Contractor Requests';
    render_layout($title, function () use ($pending, $deptId) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0 0 6px 0;"><?= sanitize('Contractor Link Requests'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Review and approve or reject incoming requests.'); ?></p>
                </div>
                <a class="btn secondary" href="/department/dashboard.php"><?= sanitize('Dashboard'); ?></a>
            </div>
            <?php if (!$pending): ?>
                <p class="muted" style="margin-top:10px;"><?= sanitize('No pending requests.'); ?></p>
            <?php else: ?>
                <table style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th><?= sanitize('YOJ ID'); ?></th>
                            <th><?= sanitize('Masked Mobile'); ?></th>
                            <th><?= sanitize('Requested At'); ?></th>
                            <th><?= sanitize('Message'); ?></th>
                            <th><?= sanitize('Actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $request): ?>
                            <tr>
                                <td><?= sanitize($request['yojId'] ?? ''); ?></td>
                                <td><?= sanitize($request['contractorMobileMasked'] ?? ''); ?></td>
                                <td><?= sanitize($request['createdAt'] ?? ''); ?></td>
                                <td>
                                    <?php if (!empty($request['message'])): ?>
                                        <details>
                                            <summary style="cursor:pointer;"><?= sanitize('View'); ?></summary>
                                            <p style="margin:6px 0 0 0;"><?= sanitize($request['message']); ?></p>
                                        </details>
                                    <?php else: ?>
                                        <span class="muted"><?= sanitize('No message'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" action="/department/contractor_request_action.php" style="display:flex;gap:6px;flex-wrap:wrap;">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="requestId" value="<?= sanitize($request['requestId'] ?? ''); ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button class="btn" type="submit"><?= sanitize('Approve'); ?></button>
                                    </form>
                                    <form method="post" action="/department/contractor_request_action.php" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="requestId" value="<?= sanitize($request['requestId'] ?? ''); ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="text" name="note" placeholder="Reason (optional)" style="min-width:180px;">
                                        <button class="btn danger" type="submit"><?= sanitize('Reject'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    });
});
