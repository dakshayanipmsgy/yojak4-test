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
    $reason = trim($_POST['reason'] ?? 'Rejected by superadmin');

    if ($signupId === '') {
        set_flash('error', 'Missing signup.');
        redirect('/superadmin/contractors.php');
    }

    if (reject_pending_contractor($signupId, $user['username'], $reason)) {
        set_flash('success', 'Signup rejected.');
    } else {
        set_flash('error', 'Unable to reject signup.');
    }
    redirect('/superadmin/contractors.php?tab=pending');
});
