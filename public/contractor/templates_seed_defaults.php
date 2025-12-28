<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/templates.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_contractor_templates_env($yojId);

    $created = seed_default_contractor_templates($yojId);
    if ($created) {
        set_flash('success', count($created) . ' templates added.');
    } else {
        set_flash('success', 'Defaults already present.');
    }

    redirect('/contractor/templates.php');
});
