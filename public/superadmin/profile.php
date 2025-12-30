<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $sessionUser = require_role('superadmin');
    if (!empty($sessionUser['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $record = get_user_record($sessionUser['username']);
    $branding = branding_read_config();
    $title = get_app_config()['appName'] . ' | ' . t('profile');
    render_layout($title, function () use ($record, $branding, $sessionUser) {
        ?>
        <div class="hero">
            <div class="card">
                <h2><?= sanitize(t('superadmin_profile')); ?></h2>
                <p class="muted"><?= sanitize('Core account details for auditing.'); ?></p>
                <ul>
                    <li><strong><?= sanitize(t('username')); ?>:</strong> <?= sanitize($record['username'] ?? ''); ?></li>
                    <li><strong><?= sanitize(t('status_active')); ?>:</strong> <?= sanitize($record['status'] ?? ''); ?></li>
                    <li><strong><?= sanitize(t('last_login')); ?>:</strong> <?= sanitize($record['lastLoginAt'] ?? '—'); ?></li>
                    <li><strong><?= sanitize(t('must_reset')); ?>:</strong> <?= !empty($record['mustResetPassword']) ? 'Yes' : 'No'; ?></li>
                </ul>
            </div>

            <div class="card" id="branding">
                <h2><?= sanitize('Branding'); ?></h2>
                <p class="muted"><?= sanitize('Control the platform logo shown on the superadmin dashboard.'); ?></p>
                <div style="display:flex;align-items:center;gap:12px;margin:12px 0;">
                    <?php if ($branding['logoPublicPath'] && branding_logo_exists($branding['logoPublicPath'])): ?>
                        <div class="brand-logo-image" style="padding:10px;">
                            <img src="<?= sanitize($branding['logoPublicPath']); ?>" alt="<?= sanitize('Current logo'); ?>">
                        </div>
                    <?php else: ?>
                        <div class="brand-logo">YJ</div>
                    <?php endif; ?>
                    <div>
                        <div class="pill"><?= $branding['logoEnabled'] ? sanitize('Enabled') : sanitize('Disabled'); ?></div>
                        <div class="muted" style="margin-top:4px;"><?= sanitize($branding['logoUploadedAt'] ? 'Last upload: ' . $branding['logoUploadedAt'] : 'No logo uploaded yet.'); ?></div>
                    </div>
                </div>
                <div class="buttons" style="flex-direction:column;align-items:flex-start;">
                    <form method="post" action="/superadmin/branding_logo_upload.php" enctype="multipart/form-data" style="width:100%;display:grid;gap:10px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <label class="muted" style="font-weight:600;"><?= sanitize('Upload or replace logo'); ?></label>
                        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" required>
                        <p class="muted" style="margin:0;font-size:12px;"><?= sanitize('Max size 2MB. Allowed: PNG, JPG, JPEG, WEBP.'); ?></p>
                        <button type="submit" class="btn"><?= sanitize('Upload / Replace'); ?></button>
                    </form>
                    <form method="post" action="/superadmin/branding_logo_toggle.php" style="width:100%;display:flex;align-items:center;gap:10px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="enabled" value="<?= $branding['logoEnabled'] ? '0' : '1'; ?>">
                        <button type="submit" class="btn secondary">
                            <?= $branding['logoEnabled'] ? sanitize('Hide logo on dashboard') : sanitize('Show logo on dashboard'); ?>
                        </button>
                        <span class="muted" style="font-size:12px;"><?= sanitize('Toggle visibility without deleting the file.'); ?></span>
                    </form>
                    <form method="post" action="/superadmin/branding_logo_delete.php" style="width:100%;display:flex;align-items:center;gap:10px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <button type="submit" class="btn danger" onclick="return confirm('Delete current logo? This cannot be undone.');">
                            <?= sanitize('Delete logo'); ?>
                        </button>
                        <span class="muted" style="font-size:12px;"><?= sanitize('Removes the file and falls back to default branding.'); ?></span>
                    </form>
                </div>
                <div class="pill" style="margin-top:12px;">
                    <?= sanitize('Updated: ' . ($branding['updatedAt'] ?? '—') . ' by ' . ($branding['updatedBy'] ?? '—')); ?>
                </div>
            </div>
        </div>
        <?php
    });
});
