<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        render_error_page('Invalid request.');
        return;
    }
    $user = require_role('superadmin');
    require_csrf();

    $signupId = trim($_POST['signupId'] ?? '');
    if ($signupId === '') {
        set_flash('error', 'Missing signup.');
        redirect('/superadmin/contractors.php');
    }

    $approved = approve_pending_contractor($signupId, $user['username']);
    if ($approved) {
        set_flash('success', 'Approved contractor: ' . $approved['yojId']);
    } else {
        set_flash('error', 'Unable to approve signup.');
    }
    redirect('/superadmin/contractors.php?tab=pending');
});
