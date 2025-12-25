<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        render_error_page('Unsupported request.');
        return;
    }

    require_csrf();

    try {
        $result = create_backup_archive($user['username'] ?? 'superadmin');
        set_flash('success', 'Backup created: ' . ($result['filename'] ?? ''));
    } catch (Throwable $e) {
        set_flash('error', 'Backup failed: ' . $e->getMessage());
        logEvent(DATA_PATH . '/logs/backup.log', [
            'event' => 'backup_failed_exception',
            'message' => $e->getMessage(),
        ]);
    }

    redirect('/superadmin/backup.php');
});
