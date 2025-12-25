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
    require_department_permission($user, 'manage_users');

    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $action = $_POST['action'] ?? '';
        $fullUserId = strtolower(trim($_POST['fullUserId'] ?? ''));
        if ($action === 'reset_request') {
            if (!$fullUserId) {
                $errors[] = 'User ID required for reset request.';
            } else {
                $req = add_password_reset_request($deptId, $fullUserId, $user['username'] ?? '');
                append_department_audit($deptId, [
                    'by' => $user['username'] ?? '',
                    'action' => 'password_reset_requested',
                    'meta' => ['userId' => $fullUserId, 'requestId' => $req['requestId'] ?? ''],
                ]);
                set_flash('success', 'Password reset request recorded for superadmin approval.');
                redirect('/department/users.php');
            }
        }
    }

    $activeUsers = list_department_users($deptId, false);
    $archivedUsers = list_department_users($deptId, true);
    $resetRequests = load_password_reset_requests($deptId);
    $title = get_app_config()['appName'] . ' | Department Users';

    render_layout($title, function () use ($activeUsers, $archivedUsers, $errors, $resetRequests) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Users'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Manage department users. Format: usershort.role.dept'); ?></p>
                </div>
                <a class="btn" href="/department/user_create.php"><?= sanitize('Create User'); ?></a>
            </div>
            <?php if ($errors): ?>
                <div class="flashes" style="margin-top:12px;">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <h3 style="margin-top:16px;"><?= sanitize('Active Users'); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('User'); ?></th>
                        <th><?= sanitize('Role'); ?></th>
                        <th><?= sanitize('Status'); ?></th>
                        <th><?= sanitize('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$activeUsers): ?>
                        <tr><td colspan="4" class="muted"><?= sanitize('No users yet.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($activeUsers as $item): ?>
                            <tr>
                                <td>
                                    <div><?= sanitize($item['fullUserId'] ?? ''); ?></div>
                                    <div class="muted"><?= sanitize($item['displayName'] ?? ''); ?></div>
                                </td>
                                <td><span class="tag"><?= sanitize($item['roleId'] ?? ''); ?></span></td>
                                <td><span class="tag <?= ($item['status'] ?? '') === 'active' ? 'success' : ''; ?>"><?= sanitize(ucfirst($item['status'] ?? '')); ?></span></td>
                                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <form method="post" action="/department/user_suspend.php">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="fullUserId" value="<?= sanitize($item['fullUserId'] ?? ''); ?>">
                                        <button class="btn secondary" type="submit"><?= sanitize('Suspend'); ?></button>
                                    </form>
                                    <form method="post" action="/department/user_archive.php">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="fullUserId" value="<?= sanitize($item['fullUserId'] ?? ''); ?>">
                                        <button class="btn danger" type="submit"><?= sanitize('Archive'); ?></button>
                                    </form>
                                    <form method="post" action="/department/users.php">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="reset_request">
                                        <input type="hidden" name="fullUserId" value="<?= sanitize($item['fullUserId'] ?? ''); ?>">
                                        <button class="btn secondary" type="submit"><?= sanitize('Request Reset'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h3 style="margin-top:16px;"><?= sanitize('Archived Users'); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('User'); ?></th>
                        <th><?= sanitize('Archived At'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$archivedUsers): ?>
                        <tr><td colspan="2" class="muted"><?= sanitize('No archived users.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($archivedUsers as $item): ?>
                            <tr>
                                <td><?= sanitize($item['fullUserId'] ?? ''); ?></td>
                                <td><?= sanitize($item['archivedAt'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h3 style="margin-top:16px;"><?= sanitize('Password Reset Requests'); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('Request ID'); ?></th>
                        <th><?= sanitize('User'); ?></th>
                        <th><?= sanitize('Status'); ?></th>
                        <th><?= sanitize('Requested At'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$resetRequests): ?>
                        <tr><td colspan="4" class="muted"><?= sanitize('No reset requests.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($resetRequests as $req): ?>
                            <tr>
                                <td><?= sanitize($req['requestId'] ?? ''); ?></td>
                                <td><?= sanitize($req['userId'] ?? ''); ?></td>
                                <td><span class="tag"><?= sanitize($req['status'] ?? ''); ?></span></td>
                                <td><?= sanitize($req['createdAt'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
