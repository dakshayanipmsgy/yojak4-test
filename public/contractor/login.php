<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $title = get_app_config()['appName'] . ' | Contractor Login';
    $errors = [];
    $mobile = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $mobile = trim($_POST['mobile'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($mobile === '' || $password === '') {
            $errors[] = 'Mobile and password are required.';
        } elseif (!is_valid_mobile($mobile)) {
            $errors[] = 'Enter a valid 10-digit mobile number.';
        } else {
            $rateKey = rate_limit_key($mobile);
            if (!check_rate_limit($rateKey)) {
                $errors[] = t('device_locked');
            } else {
                $contractor = authenticate_contractor_user($mobile, $password);
                if ($contractor) {
                    record_rate_limit_attempt($rateKey, true);
                    update_contractor_last_login($contractor['yojId']);
                    login_user([
                        'username' => $contractor['mobile'],
                        'type' => 'contractor',
                        'yojId' => $contractor['yojId'],
                        'displayName' => $contractor['name'] ?: $contractor['mobile'],
                        'mustResetPassword' => $contractor['mustResetPassword'] ?? false,
                        'lastLoginAt' => $contractor['lastLoginAt'] ?? null,
                    ]);
                    set_flash('success', t('login_success'));
                    if (!empty($contractor['mustResetPassword'])) {
                        redirect('/auth/force_reset.php');
                    }
                    redirect('/contractor/dashboard.php');
                } else {
                    record_rate_limit_attempt($rateKey, false);
                    $exists = find_contractor_by_mobile($mobile);
                    if ($exists && ($exists['status'] ?? '') !== 'approved') {
                        $errors[] = 'Account pending approval. Please wait for superadmin review.';
                    } else {
                        $errors[] = t('login_invalid');
                    }
                }
            }
        }
    } else {
        $user = current_user();
        if ($user && ($user['type'] ?? '') === 'contractor') {
            redirect('/contractor/dashboard.php');
        }
    }

    render_layout($title, function () use ($errors, $mobile) {
        ?>
        <div class="card">
            <h2><?= sanitize('Contractor Login'); ?></h2>
            <p class="muted"><?= sanitize('Access your contractor workspace and digital vault.'); ?></p>
            <?php if ($errors): ?>
                <div class="flashes">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/contractor/login.php">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="mobile"><?= sanitize('Mobile'); ?></label>
                    <input id="mobile" name="mobile" inputmode="tel" maxlength="10" value="<?= sanitize($mobile); ?>" required>
                </div>
                <div class="field">
                    <label for="password"><?= sanitize('Password'); ?></label>
                    <input id="password" name="password" type="password" required>
                </div>
                <button class="btn" type="submit"><?= sanitize(t('login')); ?></button>
                <a class="btn secondary" href="/contractor/signup.php"><?= sanitize('Create account'); ?></a>
            </form>
        </div>
        <?php
    });
});
