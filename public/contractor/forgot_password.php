<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $title = get_app_config()['appName'] . ' | Contractor Forgot Password';
    $errors = [];
    $mobile = '';
    $successMessage = 'If your account exists, we received the request. We will notify admin for reset approval.';
    $showInfo = true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $mobile = trim($_POST['mobile'] ?? '');
        $normalized = normalize_mobile($mobile);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        if ($mobile === '' || !is_valid_mobile($mobile)) {
            $errors[] = 'Enter a valid 10-digit mobile number.';
        }

        $deviceKey = 'device_' . hash('sha256', ($ip ?? '') . '|' . $ua);
        $mobileKey = 'mobile_' . $normalized;
        $deviceAllowed = password_reset_rate_limit_allowed($deviceKey, 86400, 10);
        $mobileAllowed = password_reset_rate_limit_allowed($mobileKey, 86400, 3);
        if (!$deviceAllowed || !$mobileAllowed) {
            $errors[] = 'Too many reset requests. Please try again later.';
        }

        if (!$errors) {
            $contractor = find_contractor_by_mobile($mobile);
            $yojId = $contractor['yojId'] ?? null;
            add_contractor_password_reset_request($mobile, $yojId, $ip, $ua);
            password_reset_rate_limit_record($deviceKey, 86400, 10);
            password_reset_rate_limit_record($mobileKey, 86400, 3);
            logEvent(DATA_PATH . '/logs/auth.log', [
                'event' => 'contractor_password_reset_requested',
                'mobile' => $normalized,
                'yojId' => $yojId,
                'ip' => $ip,
            ]);
            set_flash('success', $successMessage);
            redirect('/contractor/reset_requested.php');
        }
        $showInfo = false;
    }

    render_layout($title, function () use ($errors, $mobile, $successMessage, $showInfo) {
        ?>
        <div class="card">
            <h2 style="margin-bottom:6px;"><?= sanitize('Forgot Password'); ?></h2>
            <p class="muted" style="margin-top:0;"><?= sanitize('We will notify admin for reset approval.'); ?></p>
            <?php if ($errors): ?>
                <div class="flashes">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($showInfo): ?>
                <div class="flash" style="border-color:var(--border);"><?= sanitize($successMessage); ?></div>
            <?php endif; ?>
            <form method="post" action="/contractor/forgot_password.php">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="mobile"><?= sanitize('Mobile Number'); ?></label>
                    <input id="mobile" name="mobile" inputmode="tel" maxlength="10" value="<?= sanitize($mobile); ?>" required placeholder="10-digit mobile">
                </div>
                <button class="btn" type="submit"><?= sanitize('Submit Reset Request'); ?></button>
                <a class="btn secondary" href="/contractor/login.php"><?= sanitize('Back to Login'); ?></a>
            </form>
        </div>
        <?php
    });
});
