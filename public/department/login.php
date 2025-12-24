<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $errors = [];
    $loginId = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $loginId = trim($_POST['loginId'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($loginId === '' || $password === '') {
            $errors[] = t('login_invalid');
        } else {
            $rateKey = rate_limit_key($loginId);
            if (!check_rate_limit($rateKey)) {
                $errors[] = t('device_locked');
            } else {
                $record = authenticate_department_user($loginId, $password);
                if ($record) {
                    record_rate_limit_attempt($rateKey, true);
                    login_user($record);
                    update_department_last_login($record['fullUserId']);
                    logEvent(DATA_PATH . '/logs/departments.log', [
                        'event' => 'department_admin_login',
                        'user' => $record['fullUserId'],
                        'deptId' => $record['deptId'],
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    ]);
                    if (!empty($record['mustResetPassword'])) {
                        redirect('/auth/force_reset.php');
                    }
                    set_flash('success', t('login_success'));
                    redirect('/department/dashboard.php');
                } else {
                    record_rate_limit_attempt($rateKey, false);
                    logEvent(DATA_PATH . '/logs/departments.log', [
                        'event' => 'department_admin_login_failed',
                        'loginId' => $loginId,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    ]);
                    $errors[] = t('login_invalid');
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
            <p class="muted"><?= sanitize('Admin login only. Use format adminid.admin.deptid'); ?></p>
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
                    <input id="loginId" name="loginId" value="<?= sanitize($loginId); ?>" required>
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
