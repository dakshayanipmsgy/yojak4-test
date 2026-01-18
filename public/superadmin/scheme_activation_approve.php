<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_scheme_approver();
    require_csrf();

    $requestId = trim($_POST['requestId'] ?? '');
    if ($requestId === '') {
        render_error_page('Request ID missing.');
        return;
    }

    $request = scheme_update_request($requestId, [
        'status' => 'approved',
        'decidedAt' => now_kolkata()->format(DateTime::ATOM),
        'decidedBy' => $user['username'] ?? 'superadmin',
    ]);

    if (!$request) {
        render_error_page('Request not found.');
        return;
    }

    $yojId = $request['yojId'] ?? '';
    $schemeId = $request['schemeId'] ?? '';
    if ($yojId && $schemeId) {
        $access = [
            'schemeId' => $schemeId,
            'enabled' => true,
            'enabledAt' => now_kolkata()->format(DateTime::ATOM),
            'enabledBy' => $user['username'] ?? 'superadmin',
            'role' => 'vendor',
        ];
        writeJsonAtomic(scheme_access_path($yojId, $schemeId), $access);
    }

    set_flash('success', 'Scheme access approved.');
    redirect('/superadmin/scheme_activation_requests.php');
});
