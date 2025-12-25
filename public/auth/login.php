<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $title = get_app_config()['appName'] . ' | ' . t('login');
    $errors = [];
    $username = '';
    $employee = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $errors[] = t('login_invalid');
        } else {
            $rateKey = rate_limit_key($username);
            if (!check_rate_limit($rateKey)) {
                $errors[] = t('device_locked');
                logEvent(DATA_PATH . '/logs/auth.log', [
                    'event' => 'login_blocked_rate_limit',
                    'username' => $username,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
            } else {
                $authenticated = false;
                if (authenticate_superadmin($username, $password)) {
                    $record = get_user_record($username);
                    if ($record) {
                        record_rate_limit_attempt($rateKey, true);
                        update_last_login($username);
                        login_user($record);
                        logEvent(DATA_PATH . '/logs/auth.log', [
                            'event' => 'login_success',
                            'username' => $username,
                            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        ]);
                        if (!empty($record['mustResetPassword'])) {
                            redirect('/auth/force_reset.php');
                        }
                        set_flash('success', t('login_success'));
                        redirect('/superadmin/dashboard.php');
                    }
                    $authenticated = true;
                }

                if (!$authenticated) {
                    $employee = authenticate_employee($username, $password);
                    if ($employee) {
                        record_rate_limit_attempt($rateKey, true);
                        update_employee_last_login($employee['empId'] ?? '');
                        login_user($employee);
                        logEvent(DATA_PATH . '/logs/auth.log', [
                            'event' => 'employee_login_success',
                            'username' => $username,
                            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        ]);
                        set_flash('success', t('login_success'));
                        redirect('/staff/dashboard.php');
                    }
                }

                if (!$authenticated && empty($employee)) {
                    record_rate_limit_attempt($rateKey, false);
                    handle_failed_login($username);
                    logEvent(DATA_PATH . '/logs/auth.log', [
                        'event' => 'login_failed',
                        'username' => $username,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    ]);
                    $errors[] = t('login_invalid');
                }
            }
        }
    } else {
        $user = current_user();
        if ($user) {
            if (($user['type'] ?? '') === 'superadmin') {
                if (!empty($user['mustResetPassword'])) {
                    redirect('/auth/force_reset.php');
                }
                redirect('/superadmin/dashboard.php');
            }
            if (($user['type'] ?? '') === 'employee') {
                redirect('/staff/dashboard.php');
            }
        }
    }

    render_layout($title, function () use ($errors, $username) {
        ?>
        <div class="card">
            <h2><?= sanitize(t('login')); ?></h2>
            <p class="muted"><?= sanitize('Sign in with your superadmin or Yojak staff account to proceed.'); ?></p>
            <?php if ($errors): ?>
                <div class="flashes">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="pill"><?= sanitize('Superadmin & approved staff only.'); ?></div>
            <form method="post" action="/auth/login.php">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="username"><?= sanitize(t('username')); ?></label>
                    <input id="username" name="username" value="<?= sanitize($username); ?>" required>
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
