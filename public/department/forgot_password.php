<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $title = get_app_config()['appName'] . ' | Department Forgot Password';
    $errors = [];
    $deptId = '';
    $loginId = '';
    $contact = '';
    $message = '';
    $successMessage = 'If the account exists, your reset request was received. Superadmin will review it.';
    $showInfo = true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $deptId = strtolower(trim($_POST['deptId'] ?? ''));
        $loginId = strtolower(trim($_POST['loginId'] ?? ''));
        $contact = trim($_POST['contact'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        if (!is_valid_dept_id($deptId)) {
            $errors[] = 'Enter a valid department ID.';
        }
        if ($loginId === '') {
            $errors[] = 'Login ID is required.';
        }

        $deviceKey = 'device_' . hash('sha256', ($ip ?? '') . '|' . $ua);
        $deptKey = 'dept_' . $deptId . '_' . hash('sha256', $ip . '|' . $ua);
        $deviceAllowed = password_reset_rate_limit_allowed($deviceKey, 86400, 8);
        $deptAllowed = password_reset_rate_limit_allowed($deptKey, 86400, 5);
        if (!$deviceAllowed || !$deptAllowed) {
            $errors[] = 'Too many reset requests. Please try again later.';
        }

        $fullUserId = $loginId;
        if (!str_contains($fullUserId, '.')) {
            $fullUserId = $loginId . '.admin.' . $deptId;
        }
        $parsed = parse_department_login_identifier($fullUserId);
        if (!$parsed || ($parsed['roleId'] ?? '') !== 'admin' || ($parsed['deptId'] ?? '') !== $deptId) {
            $errors[] = 'Invalid admin login ID.';
        }

        if (!$errors) {
            $record = load_active_department_user($parsed['fullUserId']);
            $exists = $record && ($record['type'] ?? '') === 'department' && ($record['roleId'] ?? '') === 'admin';
            if ($exists) {
                add_password_reset_request(
                    $deptId,
                    $parsed['fullUserId'],
                    'self_service',
                    $contact !== '' ? $contact : null,
                    $message !== '' ? $message : null,
                    $ip,
                    $ua
                );
                logEvent(DATA_PATH . '/logs/auth.log', [
                    'event' => 'dept_admin_password_reset_requested',
                    'deptId' => $deptId,
                    'adminUserId' => $parsed['fullUserId'],
                    'ip' => $ip,
                ]);
            } else {
                logEvent(DATA_PATH . '/logs/auth.log', [
                    'event' => 'dept_admin_password_reset_skipped',
                    'deptId' => $deptId,
                    'adminUserId' => $parsed['fullUserId'],
                    'ip' => $ip,
                    'reason' => 'not_found',
                ]);
            }
            password_reset_rate_limit_record($deviceKey, 86400, 8);
            password_reset_rate_limit_record($deptKey, 86400, 5);
            set_flash('success', $successMessage);
            redirect('/department/reset_requested.php');
        }
        $showInfo = false;
    }

    render_layout($title, function () use ($errors, $deptId, $loginId, $successMessage, $showInfo, $contact, $message) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:6px;"><?= sanitize('Forgot Password'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Send a reset request to superadmin.'); ?></p>
                </div>
                <a class="btn secondary" href="/department/login.php"><?= sanitize('Back to Login'); ?></a>
            </div>
            <?php if ($errors): ?>
                <div class="flashes" style="margin-top:12px;">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($showInfo): ?>
                <div class="flash" style="margin-top:12px;border-color:var(--border);"><?= sanitize($successMessage); ?></div>
            <?php endif; ?>
            <form method="post" style="margin-top:12px;display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="deptId"><?= sanitize('Department ID'); ?></label>
                    <input id="deptId" name="deptId" value="<?= sanitize($deptId); ?>" required minlength="3" maxlength="10" pattern="[a-z0-9]+">
                </div>
                <div class="field">
                    <label for="loginId"><?= sanitize('Admin Login ID'); ?></label>
                    <input id="loginId" name="loginId" value="<?= sanitize($loginId); ?>" required placeholder="user.admin.dept or user short id">
                    <p class="muted" style="margin:6px 0 0;font-size:0.9rem;"><?= sanitize('Example: ramesh.admin.jhdpw'); ?></p>
                </div>
                <div class="field">
                    <label for="contact"><?= sanitize('Contact (optional)'); ?></label>
                    <input id="contact" name="contact" value="<?= sanitize($contact ?? ''); ?>" placeholder="Email or phone to reach you">
                </div>
                <div class="field">
                    <label for="message"><?= sanitize('Message to Superadmin (optional)'); ?></label>
                    <textarea id="message" name="message" rows="3" placeholder="Reason for reset or any note"><?= sanitize($message ?? ''); ?></textarea>
                </div>
                <button class="btn" type="submit"><?= sanitize('Submit Reset Request'); ?></button>
            </form>
        </div>
        <?php
    });
});
