<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        render_error_page('Invalid request.');
        return;
    }
    require_csrf();
    logout_user();
    set_flash('success', t('logout_success'));
    redirect('/contractor/login.php');
});
