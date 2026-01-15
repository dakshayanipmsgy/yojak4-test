<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $mobile = trim($_SESSION['contractor_signup_mobile'] ?? '');
    if ($mobile === '' || !is_valid_mobile($mobile)) {
        set_flash('error', 'Please start signup again.');
        redirect('/contractor/signup.php');
    }
    $title = get_app_config()['appName'] . ' | Contractor Signup';
    $displayMobile = '+91 ' . $mobile;

    render_layout($title, function () use ($displayMobile, $mobile) {
        ?>
        <div class="card">
            <h2><?= sanitize('Contractor Signup • Step 2'); ?></h2>
            <p class="muted"><?= sanitize('Enter the 6-digit OTP sent to ' . $displayMobile . '.'); ?></p>
            <p class="muted"><?= sanitize('OTP expires in 10 minutes.'); ?></p>
            <form method="post" action="/contractor/signup_verify_otp.php">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="otp"><?= sanitize('OTP'); ?></label>
                    <input id="otp" name="otp" inputmode="numeric" maxlength="6" required>
                </div>
                <button class="btn" type="submit"><?= sanitize('Verify OTP'); ?></button>
                <a class="btn secondary" href="/contractor/signup.php"><?= sanitize('Change Mobile'); ?></a>
            </form>
            <form method="post" action="/contractor/signup_send_otp.php" style="margin-top:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="mobile" value="<?= sanitize($mobile); ?>">
                <button class="btn secondary" type="submit"><?= sanitize('Resend OTP'); ?></button>
            </form>
        </div>
        <?php
    });
});
