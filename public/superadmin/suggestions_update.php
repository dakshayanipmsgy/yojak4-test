<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/suggestions.php');
    }

    require_csrf();

    $id = (string)($_POST['id'] ?? '');
    $status = (string)($_POST['status'] ?? '');

    if ($id === '' || $status === '') {
        render_error_page('Missing suggestion update data.');
        return;
    }

    $updated = suggestion_update_status($id, $status);
    if (!$updated) {
        render_error_page('Unable to update suggestion status.');
        return;
    }

    redirect('/superadmin/suggestions.php?id=' . urlencode($id));
});
