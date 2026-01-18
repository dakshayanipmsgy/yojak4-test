<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    require_csrf();
    $yojId = $user['yojId'];

    $schemeId = trim($_POST['schemeId'] ?? '');
    $recordId = trim($_POST['recordId'] ?? '');
    $entityKey = trim($_POST['entity'] ?? '');
    $token = trim($_POST['token'] ?? '');
    if ($schemeId === '' || $recordId === '' || $entityKey === '' || $token === '') {
        render_error_page('Missing parameters.');
        return;
    }
    if (!scheme_has_access($yojId, $schemeId)) {
        render_error_page('Scheme access not enabled.');
        return;
    }

    scheme_revoke_customer_token($token, $yojId);

    $record = scheme_load_record($yojId, $schemeId, $entityKey, $recordId);
    if ($record) {
        $record['portalToken'] = null;
        scheme_save_record($yojId, $schemeId, $entityKey, $record);
    }

    scheme_log_portal([
        'event' => 'REVOKE',
        'schemeId' => $schemeId,
        'recordId' => $recordId,
        'yojId' => $yojId,
        'token' => $token,
    ]);

    redirect('/contractor/scheme_docs.php?schemeId=' . urlencode($schemeId) . '&recordId=' . urlencode($recordId) . '&entity=' . urlencode($entityKey));
});
