<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/discovered_tenders.php');
    }

    require_csrf();
    $user = require_role('superadmin');
    ensure_tender_discovery_env();

    $discId = trim((string)($_POST['discId'] ?? ''));
    if ($discId === '') {
        render_error_page('Invalid discovered tender.');
        return;
    }

    $record = tender_discovery_load_discovered($discId);
    if (!$record) {
        render_error_page('Discovered tender not found.');
        return;
    }

    $deleted = tender_discovery_soft_delete($discId);
    if ($deleted) {
        tender_discovery_log([
            'event' => 'soft_delete',
            'discId' => $discId,
            'username' => $user['username'] ?? 'superadmin',
        ]);
        set_flash('success', 'Discovered tender deleted.');
    } else {
        set_flash('error', 'Unable to delete discovered tender.');
    }

    redirect('/superadmin/discovered_tenders.php');
});
