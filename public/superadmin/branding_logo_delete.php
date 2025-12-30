<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    require_csrf();

    $actor = $user['username'] ?? 'superadmin';
    try {
        branding_handle_delete($actor);
        set_flash('success', 'Logo deleted and default branding restored.');
    } catch (Throwable $e) {
        branding_log('delete_logo', $actor, 'failed', ['message' => $e->getMessage()]);
        set_flash('error', 'Unable to delete logo: ' . $e->getMessage());
    }

    redirect('/superadmin/profile.php#branding');
});
