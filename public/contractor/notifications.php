<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'] ?? '';
    ensure_contractor_notifications_env($yojId);

    $index = contractor_notifications_index($yojId);
    $notifications = [];
    foreach ($index as $entry) {
        $path = contractor_notification_path($yojId, $entry);
        $detail = file_exists($path) ? readJson($path) : null;
        if ($detail) {
            $notifications[] = $detail;
        }
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
                <div style="display:flex;gap:10px;align-items:center;">
                    <form method="post" action="/contractor/notifications_mark_read.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="action" value="all">
                        <button class="btn secondary" type="submit"><?= sanitize('Mark all read'); ?></button>
                    </form>
                    <a class="btn secondary" href="/contractor/dashboard.php"><?= sanitize('Dashboard'); ?></a>
                </div>
            </div>
            <?php if (!$notifications): ?>
                <p class="muted" style="margin-top:10px;"><?= sanitize('No notifications yet.'); ?></p>
            <?php else: ?>
                <div style="display:grid;gap:10px;margin-top:12px;">
                    <?php foreach ($notifications as $note): ?>
                        <?php $isUnread = empty($note['readAt']); ?>
                        <div class="card" style="background:var(--surface-2);border:1px solid var(--border);">
                            <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                                <div>
                                    <h3 style="margin:0 0 4px 0;display:flex;align-items:center;gap:6px;">
                                        <?= sanitize($note['title'] ?? ''); ?>
                                        <?php if ($isUnread): ?>
                                            <span class="pill"><?= sanitize('New'); ?></span>
                                        <?php endif; ?>
                                    </h3>
                                    <p class="muted" style="margin:0 0 4px 0;"><?= sanitize($note['message'] ?? ''); ?></p>
                                    <?php if (!empty($note['deptId'])): ?>
                                        <span class="pill"><?= sanitize('Dept: ' . $note['deptId']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
                                    <div class="muted" style="font-size:12px;"><?= sanitize($note['createdAt'] ?? ''); ?></div>
                                    <?php if ($isUnread): ?>
                                        <form method="post" action="/contractor/notifications_mark_read.php">
                                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                            <input type="hidden" name="notifId" value="<?= sanitize($note['notifId'] ?? ''); ?>">
                                            <button class="btn secondary" type="submit" style="padding:6px 10px;font-size:12px;">
                                                <?= sanitize('Mark read'); ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    });
});
