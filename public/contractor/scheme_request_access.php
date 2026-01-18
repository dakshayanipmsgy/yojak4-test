<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    require_csrf();

    $schemeId = trim($_POST['schemeId'] ?? '');
    if ($schemeId === '') {
        render_error_page('Scheme ID missing.');
        return;
    }

    if (scheme_has_access($user['yojId'], $schemeId)) {
        set_flash('success', 'Scheme access already enabled.');
        redirect('/contractor/schemes.php');
    }

    foreach (scheme_requests_all() as $request) {
        if (($request['yojId'] ?? '') === $user['yojId'] && ($request['schemeId'] ?? '') === $schemeId && ($request['status'] ?? '') === 'pending') {
            set_flash('success', 'Access request already pending.');
            redirect('/contractor/schemes.php');
        }
    }

    scheme_request_access($schemeId, $user['yojId']);
    set_flash('success', 'Access request submitted.');
    redirect('/contractor/schemes.php');
});
