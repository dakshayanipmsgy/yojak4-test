<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $title = get_app_config()['appName'] . ' | Contractor Signup';
    $mobile = trim($_SESSION['contractor_signup_mobile'] ?? '');

    render_layout($title, function () use ($mobile) {
        ?>
        <div class="card">
            <h2><?= sanitize('Contractor Signup • Step 1'); ?></h2>
            <p class="muted"><?= sanitize('Verify your mobile number via WhatsApp OTP to start signup.'); ?></p>
            <form method="post" action="/contractor/signup_send_otp.php">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="mobile"><?= sanitize('Mobile (10 digits)'); ?></label>
                    <input id="mobile" name="mobile" inputmode="tel" maxlength="10" value="<?= sanitize($mobile); ?>" required>
                </div>
                <button class="btn" type="submit"><?= sanitize('Send OTP'); ?></button>
                <a class="btn secondary" href="/contractor/login.php"><?= sanitize('Back to Login'); ?></a>
            </form>
        </div>
        <?php
    });
});
