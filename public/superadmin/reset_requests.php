<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('reset_approvals');
    if (($actor['type'] ?? '') === 'superadmin' && !empty($actor['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $requests = load_all_password_reset_requests();
    usort($requests, fn($a, $b) => strcmp(($b['updatedAt'] ?? $b['requestedAt'] ?? $b['createdAt'] ?? ''), ($a['updatedAt'] ?? $a['requestedAt'] ?? $a['createdAt'] ?? '')));

    $statusFilter = $_GET['status'] ?? 'pending';
    $statusFilter = in_array($statusFilter, ['pending', 'approved', 'rejected'], true) ? $statusFilter : 'pending';
    $filtered = array_values(array_filter($requests, fn($r) => ($r['status'] ?? '') === $statusFilter));
    $tempDisplay = $_SESSION['temp_password_once'] ?? null;
    unset($_SESSION['temp_password_once']);

    $title = get_app_config()['appName'] . ' | Reset Approvals';
    render_layout($title, function () use ($filtered, $statusFilter, $tempDisplay) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Password Reset Requests'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Approve or reject reset requests from department admins, users, or contractors.'); ?></p>
                </div>
                <div class="pill"><?= sanitize('Secure approvals with audit trail.'); ?></div>
            </div>
            <div style="display:flex;gap:8px;margin:12px 0;flex-wrap:wrap;">
                <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label): ?>
                    <a class="pill" style="border-color: <?= $statusFilter === $key ? 'var(--primary)' : 'var(--border)'; ?>; color: <?= $statusFilter === $key ? '#fff' : 'var(--muted)'; ?>; background: <?= $statusFilter === $key ? '#1f6feb22' : 'var(--surface-2)'; ?>" href="/superadmin/reset_requests.php?status=<?= sanitize($key); ?>"><?= sanitize($label); ?></a>
                <?php endforeach; ?>
            </div>
            <?php if ($tempDisplay): ?>
                <div class="card" style="margin-bottom:12px;border-color:var(--primary);">
                    <h3 style="margin-top:0;"><?= sanitize('Temporary password generated'); ?></h3>
                    <p class="muted" style="margin:4px 0 10px;"><?= sanitize('Share securely. User must reset on first login.'); ?></p>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-bottom:10px;">
                        <div>
                            <div class="muted" style="font-size:0.9rem;"><?= sanitize('Reset applied to'); ?></div>
                            <div style="font-weight:700;"><?= sanitize($tempDisplay['user'] ?? ''); ?></div>
                        </div>
                        <?php if (!empty($tempDisplay['deptId'])): ?>
                            <div>
                                <div class="muted" style="font-size:0.9rem;"><?= sanitize('Department'); ?></div>
                                <div style="font-weight:700;"><?= sanitize($tempDisplay['deptId']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <input id="tempPw" value="<?= sanitize($tempDisplay['password'] ?? ''); ?>" readonly style="flex:1;min-width:220px;">
                        <button class="btn" type="button" onclick="navigator.clipboard.writeText(document.getElementById('tempPw').value)"><?= sanitize('Copy'); ?></button>
                    </div>
                    <div class="pill" style="margin-top:8px;"><?= sanitize('Request ' . ($tempDisplay['requestId'] ?? '')); ?></div>
                    <?php if (!empty($tempDisplay['user'] ?? '')): ?>
                        <div class="pill" style="margin-top:8px;background:#1f6feb22;border-color:#1f6feb;"><?= sanitize($tempDisplay['user']); ?></div>
                    <?php endif; ?>
                    <details style="margin-top:12px;">
                        <summary style="cursor:pointer;font-weight:600;"><?= sanitize('Debug details'); ?></summary>
                        <div class="muted" style="margin-top:6px;font-size:0.9rem;">
                            <div><?= sanitize('Updated file: ' . ($tempDisplay['updatedPath'] ?? '')); ?></div>
                            <div><?= sanitize('mustResetPassword set to true'); ?></div>
                        </div>
                    </details>
                </div>
            <?php endif; ?>
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('Request'); ?></th>
                        <th><?= sanitize('User'); ?></th>
                        <th><?= sanitize('Details'); ?></th>
                        <th><?= sanitize('Status'); ?></th>
                        <th><?= sanitize('Requested At'); ?></th>
                        <th><?= sanitize('Decided'); ?></th>
                        <th><?= sanitize('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$filtered): ?>
                        <tr><td colspan="7" class="muted"><?= sanitize('No reset requests found.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($filtered as $req): ?>
                            <tr>
                                <td>
                                    <div><?= sanitize($req['requestId'] ?? ''); ?></div>
                                    <div class="muted"><?= sanitize(strtoupper($req['userType'] ?? '')); ?></div>
                                </td>
                                <td style="min-width:180px;">
                                    <?php if (($req['userType'] ?? '') === 'contractor'): ?>
                                        <div><?= sanitize($req['mobile'] ?? ''); ?></div>
                                        <div class="muted"><?= sanitize($req['yojId'] ?? ''); ?></div>
                                    <?php else: ?>
                                        <div><?= sanitize($req['fullUserId'] ?? $req['adminUserId'] ?? ''); ?></div>
                                        <div class="muted"><?= sanitize('Dept: ' . ($req['deptId'] ?? '')); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="muted"><?= sanitize('IP ' . mask_ip($req['requesterIp'] ?? '')); ?></div>
                                    <div class="pill"><?= sanitize('UA ' . substr($req['requesterUaHash'] ?? '', 0, 10) . '...'); ?></div>
                                    <?php if (!empty($req['contact'])): ?>
                                        <div class="pill" style="margin-top:6px;"><?= sanitize('Contact: ' . $req['contact']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($req['message'])): ?>
                                        <div class="muted" style="margin-top:4px;font-size:0.9rem;"><?= sanitize($req['message']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="tag <?= ($req['status'] ?? '') === 'pending' ? 'success' : ''; ?>"><?= sanitize(ucfirst($req['status'] ?? '')); ?></span></td>
                                <td><?= sanitize($req['requestedAt'] ?? $req['createdAt'] ?? ''); ?></td>
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
