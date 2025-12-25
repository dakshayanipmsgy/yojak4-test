<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $confirm = trim($_POST['confirm'] ?? '');
        if ($confirm !== 'RESET YOJAK') {
            set_flash('error', 'Confirmation phrase mismatch. Type RESET YOJAK to proceed.');
            redirect('/superadmin/factory_reset.php');
        }

        try {
            perform_factory_reset($user['username'] ?? 'superadmin');
            logout_user();
            start_app_session();
            set_flash('success', 'Factory reset completed. Default superadmin password restored to pass123 (must reset on login).');
            redirect('/auth/login.php');
        } catch (Throwable $e) {
            set_flash('error', 'Factory reset failed: ' . $e->getMessage());
            logEvent(DATA_PATH . '/logs/superadmin.log', [
                'event' => 'factory_reset_failed',
                'message' => $e->getMessage(),
            ]);
        }
    }

    $title = get_app_config()['appName'] . ' | Factory Reset';
    render_layout($title, function () {
        ?>
        <div class="card" style="border-color:#f85149;">
            <h2 style="margin-bottom:6px;"><?= sanitize('Factory Reset (Test Only)'); ?></h2>
            <p class="muted" style="margin-top:0;"><?= sanitize('This will wipe /data except minimal config. Default superadmin will be restored. Action is logged.'); ?></p>
            <div class="pill" style="border-color:#f85149;color:#f77676;"><?= sanitize('Irreversible wipe for testing. Ensure backups are downloaded.'); ?></div>
            <form method="post" action="/superadmin/factory_reset.php" style="margin-top:14px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="confirm"><?= sanitize('Type RESET YOJAK to confirm'); ?></label>
                    <input id="confirm" name="confirm" placeholder="RESET YOJAK" required>
                </div>
                <div class="buttons">
                    <button class="btn danger" type="submit"><?= sanitize('Confirm Factory Reset'); ?></button>
                    <a class="btn secondary" href="/superadmin/dashboard.php"><?= sanitize('Cancel'); ?></a>
                </div>
            </form>
        </div>
        <?php
    });
});
