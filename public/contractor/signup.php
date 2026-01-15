<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $title = get_app_config()['appName'] . ' | Contractor Signup';
    $errors = [];
    $mobile = '';
    $name = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $mobile = trim($_POST['mobile'] ?? '');
        $password = $_POST['password'] ?? '';
        $name = trim($_POST['name'] ?? '');

        if (!is_valid_mobile($mobile)) {
            $errors[] = 'Enter a valid 10-digit mobile number.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (mobile_exists_in_pending($mobile) || mobile_exists_in_approved($mobile)) {
            $errors[] = 'An account with this mobile already exists.';
        }

        if (!$errors) {
            create_pending_contractor($mobile, $password, $name);
            set_flash('success', 'Signup received. Pending superadmin approval.');
            redirect('/contractor/login.php');
        }
    }

    render_layout($title, function () use ($errors, $mobile, $name) {
        ?>
        <div class="card">
            <h2><?= sanitize('Contractor Signup'); ?></h2>
            <p class="muted"><?= sanitize('Create your contractor account and await approval.'); ?></p>
            <?php if ($errors): ?>
                <div class="flashes">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/contractor/signup.php">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="mobile"><?= sanitize('Mobile (10 digits)'); ?></label>
                    <input id="mobile" name="mobile" inputmode="tel" maxlength="10" value="<?= sanitize($mobile); ?>" required>
                </div>
                <div class="field">
                    <label for="name"><?= sanitize('Full Name (optional)'); ?></label>
                    <input id="name" name="name" value="<?= sanitize($name); ?>">
                </div>
                <div class="field">
                    <label for="password"><?= sanitize('Password'); ?></label>
                    <input id="password" name="password" type="password" minlength="8" required>
                    <small class="muted"><?= sanitize('Minimum 8 characters.'); ?></small>
                </div>
                <button class="btn" type="submit"><?= sanitize('Submit'); ?></button>
                <a class="btn secondary" href="/contractor/login.php"><?= sanitize('Back to Login'); ?></a>
            </form>
        </div>
        <?php
    });
});
