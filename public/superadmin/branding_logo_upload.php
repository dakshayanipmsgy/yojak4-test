<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    require_csrf();

    $actor = $user['username'] ?? 'superadmin';
    try {
        branding_handle_upload($_FILES['logo'] ?? [], $actor);
        set_flash('success', 'Logo updated successfully.');
    } catch (Throwable $e) {
        branding_log('upload_logo', $actor, 'failed', ['message' => $e->getMessage()]);
        set_flash('error', 'Unable to upload logo: ' . $e->getMessage());
    }

    redirect('/superadmin/profile.php#branding');
});
