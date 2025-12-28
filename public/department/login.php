<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $errors = [];
    $loginId = '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $uaHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $loginId = normalize_login_identifier((string)($_POST['loginId'] ?? ''));
        $password = $_POST['password'] ?? '';

        if ($loginId === '' || $password === '') {
            $errors[] = t('login_invalid');
        } else {
            $rateKey = rate_limit_key($loginId);
            if (!check_rate_limit($rateKey)) {
                $errors[] = t('device_locked');
                $parsed = parse_department_login_identifier($loginId);
                log_auth_attempt([
                    'type' => 'dept_login',
                    'loginId' => $loginId,
                    'deptId' => $parsed['deptId'] ?? null,
                    'ip' => $ip,
                    'uaHash' => $uaHash,
                    'result' => 'blocked',
                    'reasonCode' => 'rate_limited',
                ]);
            } else {
                $authResult = authenticate_department_user($loginId, $password);
                $record = $authResult['record'] ?? null;
                $reason = $authResult['error'] ?? null;
                $parsed = $authResult['parsed'] ?? parse_department_login_identifier($loginId);
                if ($record) {
                    record_rate_limit_attempt($rateKey, true);
                    login_user($record);
                    update_department_last_login($record['fullUserId']);
                    log_auth_attempt([
                        'type' => 'dept_login',
                        'loginId' => $loginId,
                        'deptId' => $record['deptId'] ?? ($parsed['deptId'] ?? null),
                        'ip' => $ip,
                        'uaHash' => $uaHash,
                        'result' => 'success',
                        'reasonCode' => 'ok',
                    ]);
                    if (!empty($record['mustResetPassword'])) {
                        redirect('/auth/force_reset.php');
                    }
                    set_flash('success', t('login_success'));
                    redirect('/department/dashboard.php');
                } else {
                    record_rate_limit_attempt($rateKey, false);
                    $result = 'fail';
                    $message = t('login_invalid');
                    if ($reason === 'suspended') {
                        $message = 'Account suspended.';
                        $result = 'suspended';
                    } elseif ($reason === 'role_missing') {
                        $message = 'Role misconfigured; contact admin.';
                        $result = 'role_missing';
                    }
                    log_auth_attempt([
                        'type' => 'dept_login',
                        'loginId' => $loginId,
                        'deptId' => $parsed['deptId'] ?? null,
                        'ip' => $ip,
                        'uaHash' => $uaHash,
                        'result' => $result,
                        'reasonCode' => $reason ?? 'invalid_credentials',
                    ]);
                    $errors[] = $message;
                }
            }
        }
    } else {
        $user = current_user();
        if ($user && ($user['type'] ?? '') === 'department') {
            if (!empty($user['mustResetPassword'])) {
                redirect('/auth/force_reset.php');
            }
            redirect('/department/dashboard.php');
        }
    }

    $title = get_app_config()['appName'] . ' | Department Login';
    render_layout($title, function () use ($errors, $loginId) {
        ?>
        <div class="card">
            <h2><?= sanitize('Department Login'); ?></h2>
            <p class="muted"><?= sanitize('Sign in using your department user ID and password.'); ?></p>
            <?php if ($errors): ?>
                <div class="flashes">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/department/login.php">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="loginId"><?= sanitize('Login ID'); ?></label>
                    <input id="loginId" name="loginId" value="<?= sanitize($loginId); ?>" required placeholder="usershort.role.dept">
                    <p class="muted" style="margin:6px 0 0;font-size:0.9rem;"><?= sanitize('Example: ramesh.admin.jhdpw or sita.engineer.jhdpw'); ?></p>
                </div>
                <div class="field">
                    <label for="password"><?= sanitize(t('password')); ?></label>
                    <input id="password" name="password" type="password" required>
                </div>
                <button class="btn" type="submit"><?= sanitize(t('submit')); ?></button>
            </form>
        </div>
        <?php
    });
});
