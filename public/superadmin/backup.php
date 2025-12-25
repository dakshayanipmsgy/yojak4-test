<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    if (isset($_GET['download'])) {
        $file = basename((string)$_GET['download']);
        $path = DATA_PATH . '/backups/' . $file;
        if (is_file($path)) {
            header('Content-Type: application/zip');
            header('Content-Length: ' . filesize($path));
            header('Content-Disposition: attachment; filename="' . $file . '"');
            readfile($path);
            exit;
        }
        set_flash('error', 'Backup not found.');
        redirect('/superadmin/backup.php');
    }

    $backups = list_backups();
    $title = get_app_config()['appName'] . ' | Backups';
    render_layout($title, function () use ($backups) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Backups'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Create and download secure backups of /data (backups folder excluded).'); ?></p>
                </div>
                <form method="post" action="/superadmin/backup_create.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <button class="btn" type="submit"><?= sanitize('Create Backup'); ?></button>
                </form>
            </div>
            <div class="pill" style="margin-top:10px;"><?= sanitize('Backups are stored under /data/backups. Handle securely.'); ?></div>
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('File'); ?></th>
                        <th><?= sanitize('Size'); ?></th>
                        <th><?= sanitize('Created'); ?></th>
                        <th><?= sanitize('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$backups): ?>
                        <tr><td colspan="4" class="muted"><?= sanitize('No backups yet.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td><?= sanitize($backup['filename'] ?? ''); ?></td>
                                <td><?= sanitize(number_format((int)($backup['size'] ?? 0)) . ' bytes'); ?></td>
                                <td><?= sanitize($backup['createdAt'] ?? ''); ?></td>
                                <td><a class="btn secondary" href="/superadmin/backup.php?download=<?= urlencode($backup['filename'] ?? ''); ?>"><?= sanitize('Download'); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
