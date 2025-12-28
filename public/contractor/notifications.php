<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'] ?? '';
    ensure_contractor_notifications_env($yojId);

    $index = contractor_notifications_index($yojId);
    $notifications = [];
    $updated = false;

    foreach ($index as &$entry) {
        $path = contractor_notifications_dir($yojId) . '/' . ($entry['notifId'] ?? '') . '.json';
        $detail = file_exists($path) ? readJson($path) : null;
        if ($detail) {
            if (empty($entry['readAt']) && empty($detail['readAt'])) {
                $entry['readAt'] = now_kolkata()->format(DateTime::ATOM);
                $detail['readAt'] = $entry['readAt'];
                writeJsonAtomic($path, $detail);
                $updated = true;
            }
            $notifications[] = $detail;
        }
    }
    unset($entry);
    if ($updated) {
        save_contractor_notifications_index($yojId, $index);
    }

    usort($notifications, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));

    $title = get_app_config()['appName'] . ' | Notifications';
    render_layout($title, function () use ($notifications) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0 0 6px 0;"><?= sanitize('Notifications'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Stay updated on department links.'); ?></p>
                </div>
                <a class="btn secondary" href="/contractor/dashboard.php"><?= sanitize('Dashboard'); ?></a>
            </div>
            <?php if (!$notifications): ?>
                <p class="muted" style="margin-top:10px;"><?= sanitize('No notifications yet.'); ?></p>
            <?php else: ?>
                <div style="display:grid;gap:10px;margin-top:12px;">
                    <?php foreach ($notifications as $note): ?>
                        <div class="card" style="background:#0f1520;border:1px solid #1f2a37;">
                            <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                                <div>
                                    <h3 style="margin:0 0 4px 0;"><?= sanitize($note['title'] ?? ''); ?></h3>
                                    <p class="muted" style="margin:0 0 4px 0;"><?= sanitize($note['message'] ?? ''); ?></p>
                                    <?php if (!empty($note['deptId'])): ?>
                                        <span class="pill"><?= sanitize('Dept: ' . $note['deptId']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="muted" style="font-size:12px;"><?= sanitize($note['createdAt'] ?? ''); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    });
});
