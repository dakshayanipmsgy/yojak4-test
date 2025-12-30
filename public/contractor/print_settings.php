<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $settings = load_contractor_print_settings($user['yojId']);
    $title = get_app_config()['appName'] . ' | Print Settings';

    render_layout($title, function () use ($settings) {
        ?>
        <div class="card" style="display:grid;gap:14px;max-width:760px;margin:0 auto;">
            <div>
                <h2 style="margin:0 0 6px 0;"><?= sanitize('Print Header / Footer'); ?></h2>
                <p class="muted" style="margin:0;"><?= sanitize('Configure reusable header, footer and logo for contractor printouts. Reserved space is always kept so your letterhead fits.'); ?></p>
            </div>
            <form method="post" action="/contractor/print_settings_save.php" style="display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <label class="field">
                    <span><?= sanitize('Header text (max 300 chars)'); ?></span>
                    <textarea name="headerText" rows="3" maxlength="300"><?= sanitize($settings['headerText'] ?? ''); ?></textarea>
                    <div class="muted" style="font-size:12px;"><?= sanitize('Enabled setting reserves 30mm top space; left blank if disabled.'); ?></div>
                </label>
                <label class="field" style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="headerEnabled" value="1" <?= !empty($settings['headerEnabled']) ? 'checked' : ''; ?>>
                    <span><?= sanitize('Enable header text'); ?></span>
                </label>
                <label class="field">
                    <span><?= sanitize('Footer text (max 300 chars)'); ?></span>
                    <textarea name="footerText" rows="3" maxlength="300"><?= sanitize($settings['footerText'] ?? ''); ?></textarea>
                    <div class="muted" style="font-size:12px;"><?= sanitize('Footer area reserves 20mm even when disabled.'); ?></div>
                </label>
                <label class="field" style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="footerEnabled" value="1" <?= !empty($settings['footerEnabled']) ? 'checked' : ''; ?>>
                    <span><?= sanitize('Enable footer text'); ?></span>
                </label>
                <label class="field" style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="logoEnabled" value="1" <?= !empty($settings['logoEnabled']) ? 'checked' : ''; ?>>
                    <span><?= sanitize('Show uploaded logo in header'); ?></span>
                </label>
                <label class="field">
                    <span><?= sanitize('Logo alignment'); ?></span>
                    <select name="logoAlign">
                        <?php foreach (['left','center','right'] as $align): ?>
                            <option value="<?= sanitize($align); ?>" <?= ($settings['logoAlign'] ?? 'left') === $align ? 'selected' : ''; ?>><?= sanitize(ucfirst($align)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <button class="btn" type="submit"><?= sanitize('Save settings'); ?></button>
                    <a class="btn secondary" href="/contractor/packs.php" style="color:var(--text);"><?= sanitize('Back to packs'); ?></a>
                </div>
            </form>
        </div>

        <div class="card" style="display:grid;gap:12px;max-width:760px;margin:16px auto 0 auto;">
            <div>
                <h3 style="margin:0 0 6px 0;"><?= sanitize('Upload Logo'); ?></h3>
                <p class="muted" style="margin:0;"><?= sanitize('PNG/JPG/WebP up to 2MB. Resized to fit 35mm x 20mm box for clean prints.'); ?></p>
            </div>
            <?php if (!empty($settings['logoPathPublic'])): ?>
                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <img src="<?= sanitize($settings['logoPathPublic']); ?>" alt="<?= sanitize('Current logo'); ?>" style="max-height:60px;max-width:180px;object-fit:contain;border:1px solid #30363d;border-radius:8px;padding:6px;background:#0f1520;">
                    <span class="pill"><?= sanitize('Logo enabled: ' . (!empty($settings['logoEnabled']) ? 'Yes' : 'No')); ?></span>
                </div>
            <?php endif; ?>
            <form method="post" action="/contractor/print_logo_upload.php" enctype="multipart/form-data" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <label class="btn secondary" style="cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                    <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" required style="position:absolute;opacity:0;width:1px;height:1px;">
                    <?= sanitize('Choose logo'); ?>
                </label>
                <button class="btn" type="submit"><?= sanitize('Upload & Resize'); ?></button>
            </form>
        </div>
        <?php
    });
});
