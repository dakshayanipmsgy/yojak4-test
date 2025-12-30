<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    require_csrf();

    $actor = $user['username'] ?? 'superadmin';
    $enabled = ($_POST['enabled'] ?? '') === '1';

    try {
        branding_handle_toggle($enabled, $actor);
        set_flash('success', $enabled ? 'Logo enabled for dashboard.' : 'Logo hidden from dashboard.');
    } catch (Throwable $e) {
        branding_log('toggle_logo', $actor, 'failed', ['message' => $e->getMessage(), 'enabled' => $enabled]);
        set_flash('error', 'Unable to update branding toggle: ' . $e->getMessage());
    }

    redirect('/superadmin/profile.php#branding');
});
