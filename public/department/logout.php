<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        logout_user();
        set_flash('success', t('logout_success'));
        redirect('/department/login.php');
    }

    $title = get_app_config()['appName'] . ' | ' . t('logout');
    render_layout($title, function () {
        ?>
        <div class="card">
            <h2><?= sanitize(t('logout')); ?></h2>
            <p class="muted"><?= sanitize('Confirm to sign out securely.'); ?></p>
            <form method="post" action="/department/logout.php">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="buttons">
                    <button class="btn danger" type="submit"><?= sanitize(t('logout')); ?></button>
                    <a class="btn secondary" href="/department/dashboard.php"><?= sanitize('Stay Signed In'); ?></a>
                </div>
            </form>
        </div>
        <?php
    });
});
