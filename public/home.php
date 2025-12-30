<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

safe_page(function () {
    $user = current_user();
    if (!$user) {
        log_home_redirect('guest', '/site/index.php', 'guest');
        redirect('/site/index.php');
    }

    $target = resolve_user_dashboard($user);
    if (!$target) {
        log_home_redirect($user['type'] ?? 'unknown', null, 'unknown_type');
        logout_user();
        redirect('/site/index.php');
    }

    log_home_redirect($user['type'] ?? 'unknown', $target, 'ok');
    redirect($target);
});
