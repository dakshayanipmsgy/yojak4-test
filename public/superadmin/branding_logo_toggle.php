<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    require_csrf();

    set_flash('error', 'Logo visibility toggle has been removed. The logo is always shown when uploaded.');

    redirect('/superadmin/profile.php#branding');
});
