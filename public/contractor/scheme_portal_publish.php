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
    if ($schemeId === '' || $recordId === '' || $entityKey === '') {
        render_error_page('Missing parameters.');
        return;
    }
    if (!scheme_has_access($yojId, $schemeId)) {
        render_error_page('Scheme access not enabled.');
        return;
    }

    $definition = scheme_load_definition($schemeId);
    if (!$definition) {
        render_error_page('Scheme not available.');
        return;
    }

    $portal = $definition['customerPortal'] ?? [];
    if (empty($portal['enabled'])) {
        render_error_page('Customer portal is disabled for this scheme.');
        return;
    }

    $record = scheme_load_record($yojId, $schemeId, $entityKey, $recordId);
    if (!$record) {
        render_error_page('Record not found.');
        return;
    }

    $ttlDays = (int)($portal['tokenTTLdays'] ?? 365);
    $expiresAt = now_kolkata()->modify('+' . $ttlDays . ' days')->format(DateTime::ATOM);
    $contractor = load_contractor($yojId) ?? [];

    $tokenData = scheme_store_customer_token([
        'schemeId' => $schemeId,
        'recordId' => $recordId,
        'entity' => $entityKey,
        'yojId' => $yojId,
        'token' => scheme_generate_customer_token(),
        'createdAt' => now_kolkata()->format(DateTime::ATOM),
        'expiresAt' => $expiresAt,
        'revoked' => false,
        'revokedAt' => null,
        'revokedBy' => null,
        'visibleDocs' => $portal['visibleDocs'] ?? [],
        'customerName' => scheme_get_path_value($record['data'] ?? [], 'customer.name') ?? '',
        'vendorName' => $contractor['firmName'] ?? ($contractor['name'] ?? ''),
    ]);

    $record['portalToken'] = $tokenData['token'];
    scheme_save_record($yojId, $schemeId, $entityKey, $record);

    scheme_log_usage($yojId, $schemeId, 'PORTAL_PUBLISH', [
        'recordId' => $recordId,
        'token' => $tokenData['token'],
    ]);
    scheme_log_portal([
        'event' => 'PUBLISH',
        'schemeId' => $schemeId,
        'recordId' => $recordId,
        'yojId' => $yojId,
        'token' => $tokenData['token'],
    ]);

    redirect('/contractor/scheme_docs.php?schemeId=' . urlencode($schemeId) . '&recordId=' . urlencode($recordId) . '&entity=' . urlencode($entityKey));
});
