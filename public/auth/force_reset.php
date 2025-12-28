<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_login();
    $auth = $_SESSION['auth'] ?? [];
    $errors = [];

    $type = $auth['type'] ?? ($user['type'] ?? '');
    $redirectAfter = '/auth/login.php';
    $record = null;
    $identifier = null;

    if ($type === 'superadmin') {
        $identifier = 'superadmin';
        $record = get_user_record($identifier);
        $redirectAfter = '/superadmin/dashboard.php';
    } elseif ($type === 'contractor') {
        $identifier = $user['yojId'] ?? null;
        if ($identifier) {
            $record = load_contractor($identifier);
        }
        $redirectAfter = '/contractor/dashboard.php';
    } elseif ($type === 'department') {
        $identifier = $user['fullUserId'] ?? ($user['username'] ?? null);
        $deptId = $user['deptId'] ?? null;
        if ($identifier && $deptId) {
            $record = load_active_department_user($identifier);
            if ($record && ($record['deptId'] ?? null) !== $deptId) {
                $record = null;
            }
        }
        $redirectAfter = '/department/dashboard.php';
    }

    if (!$record || !$identifier) {
        logout_user();
        redirect('/auth/login.php');
    }

    if (empty($record['mustResetPassword'])) {
        redirect($redirectAfter);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $newPassword = trim($_POST['password_new'] ?? '');
        $confirm = trim($_POST['password_confirm'] ?? '');

        if ($newPassword === '' || $confirm === '') {
            $errors[] = t('invalid_password');
        }
        if ($newPassword !== $confirm) {
            $errors[] = t('password_mismatch');
        }
        if (strlen($newPassword) < 8 || password_verify($newPassword, $record['passwordHash'] ?? '')) {
            $errors[] = t('invalid_password');
        }

        if (!$errors) {
            try {
                if ($type === 'superadmin') {
                    update_password($record['username'], $newPassword);
                } elseif ($type === 'department') {
                    update_department_user_password($record['deptId'], $record['fullUserId'], $newPassword, false, $record['fullUserId']);
                } elseif ($type === 'contractor') {
                    $updated = update_contractor_password($record['yojId'], $newPassword, 'self');
                    if (!$updated) {
                        throw new RuntimeException('Contractor password update failed.');
                    }
                } else {
                    throw new RuntimeException('Unsupported account type for reset.');
                }
            } catch (Throwable $e) {
                logEvent(DATA_PATH . '/logs/auth.log', [
                    'event' => 'password_reset_failed',
                    'type' => $type,
                    'identifier' => $identifier,
                    'reason' => $e->getMessage(),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
                $errors[] = t('invalid_password');
            }

            if (!$errors) {
                logEvent(DATA_PATH . '/logs/auth.log', [
                    'event' => 'password_reset',
                    'type' => $type,
                    'identifier' => $identifier,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
                set_flash('success', t('reset_success'));
                redirect($redirectAfter);
            }
        }
    }

    $title = get_app_config()['appName'] . ' | ' . t('force_reset_title');
    render_layout($title, function () use ($errors) {
        ?>
        <div class="card" style="max-width:480px;margin:0 auto;">
            <div class="pill" style="margin-bottom:12px;display:inline-block;"><?= sanitize(t('force_reset_notice')); ?></div>
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
            <form method="post" action="/auth/force_reset.php" class="stacked" style="display:flex;flex-direction:column;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="password_new"><?= sanitize(t('password_new')); ?></label>
                    <input id="password_new" name="password_new" type="password" required minlength="8" autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="password_confirm"><?= sanitize(t('password_confirm')); ?></label>
                    <input id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password">
                </div>
                <button class="btn" type="submit" style="width:100%;"><?= sanitize(t('force_reset_cta')); ?></button>
            </form>
        </div>
        <?php
    });
});
