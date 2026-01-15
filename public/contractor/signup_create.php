<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $mobile = trim($_SESSION['contractor_signup_mobile'] ?? '');
    $verified = !empty($_SESSION['contractor_signup_verified']);
    if ($mobile === '' || !is_valid_mobile($mobile) || !$verified) {
        set_flash('error', 'Please verify your mobile first.');
        redirect('/contractor/signup.php');
    }

    $title = get_app_config()['appName'] . ' | Contractor Signup';
    $displayMobile = '+91 ' . $mobile;
    $name = '';

    render_layout($title, function () use ($displayMobile, $name) {
        ?>
        <div class="card">
            <h2><?= sanitize('Contractor Signup • Step 3'); ?></h2>
            <p class="muted"><?= sanitize('Create your account for ' . $displayMobile . '.'); ?></p>
            <form method="post" action="/contractor/signup_create_submit.php">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="name"><?= sanitize('Full Name (optional)'); ?></label>
                    <input id="name" name="name" value="<?= sanitize($name); ?>">
                </div>
                <div class="field">
                    <label for="password"><?= sanitize('Password'); ?></label>
                    <input id="password" name="password" type="password" minlength="8" required>
                    <small class="muted"><?= sanitize('Minimum 8 characters.'); ?></small>
                </div>
                <button class="btn" type="submit"><?= sanitize('Create Account'); ?></button>
                <a class="btn secondary" href="/contractor/login.php"><?= sanitize('Back to Login'); ?></a>
            </form>
        </div>
        <?php
    });
});
