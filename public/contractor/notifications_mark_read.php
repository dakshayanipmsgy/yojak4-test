<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    require_csrf();

    $yojId = $user['yojId'] ?? '';
    $notifId = trim($_POST['notifId'] ?? '');
    $action = trim($_POST['action'] ?? '');

    ensure_contractor_notifications_env($yojId);
    $index = contractor_notifications_index($yojId);
    $updated = false;
    $now = now_kolkata()->format(DateTime::ATOM);

    foreach ($index as &$entry) {
        if ($action === 'all' || ($notifId !== '' && ($entry['notifId'] ?? '') === $notifId)) {
            if (empty($entry['readAt'])) {
                $entry['readAt'] = $now;
                $path = contractor_notification_path($yojId, $entry);
                if (file_exists($path)) {
                    $detail = readJson($path);
                    if ($detail) {
                        $detail['readAt'] = $now;
                        writeJsonAtomic($path, $detail);
                    }
                }
                $updated = true;
            }
        }
    }
    unset($entry);

    if ($updated) {
        save_contractor_notifications_index($yojId, $index);
        set_flash('success', 'Notifications updated.');
    }

    redirect('/contractor/notifications.php');
});
