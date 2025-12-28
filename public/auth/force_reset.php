<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_login();
    $errors = [];
    $record = null;
    $redirectAfter = '/auth/login.php';
    $type = $user['type'] ?? '';
    $isDepartment = $type === 'department';
    $isContractor = $type === 'contractor';

    if ($type === 'superadmin') {
        $record = get_user_record($user['username']);
        $redirectAfter = '/superadmin/dashboard.php';
    } elseif ($isDepartment) {
        $record = load_active_department_user($user['username']);
        $redirectAfter = '/department/dashboard.php';
    } elseif ($isContractor) {
        $record = load_contractor($user['yojId'] ?? '');
        $redirectAfter = '/contractor/dashboard.php';
    }

    if (!$record) {
        logout_user();
        redirect('/auth/login.php');
    }
    if (empty($record['mustResetPassword'])) {
        redirect($redirectAfter);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $newPassword = $_POST['password_new'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if ($newPassword !== $confirm) {
            $errors[] = t('password_mismatch');
        }
        if (strlen($newPassword) < 8 || password_verify($newPassword, $record['passwordHash'] ?? '')) {
            $errors[] = t('invalid_password');
        }

        if (!$errors) {
            if (($record['type'] ?? '') === 'superadmin') {
                update_password($record['username'], $newPassword);
            } elseif ($isDepartment) {
                update_department_user_password($record['deptId'], $record['fullUserId'], $newPassword, false, $record['fullUserId']);
            } elseif ($isContractor) {
                update_contractor_password($record['yojId'], $newPassword, 'self');
            }
            logEvent(DATA_PATH . '/logs/auth.log', [
                'event' => 'password_reset',
                'username' => $record['username'] ?? ($record['fullUserId'] ?? ($record['yojId'] ?? 'unknown')),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
            set_flash('success', t('reset_success'));
            redirect($redirectAfter);
        }
    }

    $title = get_app_config()['appName'] . ' | ' . t('force_reset_title');
    render_layout($title, function () use ($errors) {
        ?>
        <div class="card">
            <h2><?= sanitize(t('force_reset_title')); ?></h2>
            <p class="muted"><?= sanitize(t('force_reset_body')); ?></p>
            <p class="pill"><?= sanitize(t('password_requirements')); ?></p>
            <?php if ($errors): ?>
                <div class="flashes">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/auth/force_reset.php">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="password_new"><?= sanitize(t('password_new')); ?></label>
                    <input id="password_new" name="password_new" type="password" required minlength="8">
                </div>
                <div class="field">
                    <label for="password_confirm"><?= sanitize(t('password_confirm')); ?></label>
                    <input id="password_confirm" name="password_confirm" type="password" required minlength="8">
                </div>
                <button class="btn" type="submit"><?= sanitize(t('force_reset_cta')); ?></button>
            </form>
        </div>
        <?php
    });
});
