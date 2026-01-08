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

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'templates_seeded',
        'yojId' => $yojId,
        'createdCount' => count($created),
        'ip' => mask_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    ]);

    redirect('/contractor/templates.php');
});
