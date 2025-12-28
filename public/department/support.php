<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('department');
    $title = get_app_config()['appName'] . ' | Support';
    render_layout($title, function () use ($user) {
        support_render_form('Support', 'department');
    });
});
