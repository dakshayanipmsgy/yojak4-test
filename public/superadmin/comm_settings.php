<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $sessionUser = require_role('superadmin');
    if (!empty($sessionUser['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $config = get_comm_config();
    $title = get_app_config()['appName'] . ' | Communication Settings';

    render_layout($title, function () use ($config) {
        $wa = $config['whatsapp'] ?? [];
        $email = $config['email'] ?? [];
        ?>
        <style>
            .comm-grid {
                display: grid;
                gap: 16px;
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            }
            .field-row {
                display: grid;
                gap: 12px;
            }
            .inline-field {
                display: grid;
                gap: 6px;
            }
            .helper {
                font-size: 12px;
                color: var(--muted);
            }
        </style>

        <div class="card">
            <h2 style="margin:0 0 6px 0;"><?= sanitize('Communication Settings'); ?></h2>
            <p class="muted" style="margin:0;">
                <?= sanitize('Configure WhatsApp OTP for contractor signup and SMTP email for system notifications.'); ?>
            </p>
            <p class="muted" style="margin:8px 0 0;">
                <?= sanitize('Last saved: ' . ($config['updatedAt'] ?? '—')); ?>
            </p>
        </div>

        <form method="post" action="/superadmin/comm_settings_save.php" class="comm-grid" style="margin-top:16px;">
            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">

            <div class="card">
                <h3 style="margin:0 0 6px 0;"><?= sanitize('WhatsApp OTP (Signup Auth)'); ?></h3>
                <p class="muted" style="margin:0 0 12px 0;">
                    <?= sanitize('Used only for contractor signup verification.'); ?>
                </p>
                <div class="field-row">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="wa_enabled" <?= !empty($wa['enabled']) ? 'checked' : ''; ?>>
                        <?= sanitize('Enable WhatsApp OTP'); ?>
                    </label>
                    <div class="inline-field">
                        <label for="wa_phone"><?= sanitize('Phone Number ID'); ?></label>
                        <input id="wa_phone" name="wa_phoneNumberId" value="<?= sanitize((string)($wa['phoneNumberId'] ?? '')); ?>">
                    </div>
                    <div class="inline-field">
                        <label for="wa_token"><?= sanitize('Access Token'); ?></label>
                        <input id="wa_token" name="wa_accessToken" type="password" placeholder="<?= sanitize(($wa['accessTokenEnc'] ?? '') ? 'Saved token (leave blank to keep)' : 'Enter access token'); ?>">
                        <span class="helper"><?= sanitize('Stored encrypted. Never displayed.'); ?></span>
                    </div>
                    <div class="inline-field">
                        <label for="wa_template"><?= sanitize('Auth Template Name'); ?></label>
                        <input id="wa_template" name="wa_authTemplateName" value="<?= sanitize((string)($wa['authTemplateName'] ?? '')); ?>">
                    </div>
                    <div class="inline-field">
                        <label for="wa_lang"><?= sanitize('Template Language'); ?></label>
                        <select id="wa_lang" name="wa_templateLang">
                            <?php
                            $langOptions = ['en_US', 'en', 'hi_IN'];
                            $currentLang = (string)($wa['templateLang'] ?? 'en_US');
                            foreach ($langOptions as $lang) {
                                $selected = $lang === $currentLang ? 'selected' : '';
                                echo '<option value="' . sanitize($lang) . '" ' . $selected . '>' . sanitize($lang) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="inline-field">
                        <label for="wa_sender"><?= sanitize('Sender Display'); ?></label>
                        <input id="wa_sender" name="wa_senderDisplay" value="<?= sanitize((string)($wa['senderDisplay'] ?? 'YOJAK')); ?>">
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 style="margin:0 0 6px 0;"><?= sanitize('Email (SMTP)'); ?></h3>
                <p class="muted" style="margin:0 0 12px 0;">
                    <?= sanitize('Used for password resets and system notifications.'); ?>
                </p>
                <div class="field-row">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="email_enabled" <?= !empty($email['enabled']) ? 'checked' : ''; ?>>
                        <?= sanitize('Enable Email'); ?>
                    </label>
                    <div class="inline-field">
                        <label for="smtp_host"><?= sanitize('SMTP Host'); ?></label>
                        <input id="smtp_host" name="smtp_host" value="<?= sanitize((string)($email['smtpHost'] ?? '')); ?>">
                    </div>
                    <div class="inline-field">
                        <label for="smtp_port"><?= sanitize('SMTP Port'); ?></label>
                        <input id="smtp_port" name="smtp_port" type="number" value="<?= sanitize((string)($email['smtpPort'] ?? 587)); ?>">
                    </div>
                    <div class="inline-field">
                        <label for="smtp_enc"><?= sanitize('Encryption'); ?></label>
                        <select id="smtp_enc" name="smtp_encryption">
                            <?php
                            $smtpEnc = (string)($email['smtpEncryption'] ?? 'tls');
                            foreach (['tls', 'ssl', 'none'] as $option) {
                                $selected = $option === $smtpEnc ? 'selected' : '';
                                echo '<option value="' . sanitize($option) . '" ' . $selected . '>' . strtoupper($option) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="inline-field">
                        <label for="smtp_user"><?= sanitize('SMTP Username'); ?></label>
                        <input id="smtp_user" name="smtp_user" value="<?= sanitize((string)($email['smtpUser'] ?? '')); ?>">
                    </div>
                    <div class="inline-field">
                        <label for="smtp_pass"><?= sanitize('SMTP Password'); ?></label>
                        <input id="smtp_pass" name="smtp_pass" type="password" placeholder="<?= sanitize(($email['smtpPassEnc'] ?? '') ? 'Saved password (leave blank to keep)' : 'Enter SMTP password'); ?>">
                        <span class="helper"><?= sanitize('Stored encrypted.'); ?></span>
                    </div>
                    <div class="inline-field">
                        <label for="from_name"><?= sanitize('From Name'); ?></label>
                        <input id="from_name" name="from_name" value="<?= sanitize((string)($email['fromName'] ?? 'YOJAK')); ?>">
                    </div>
                    <div class="inline-field">
                        <label for="from_email"><?= sanitize('From Email'); ?></label>
                        <input id="from_email" name="from_email" type="email" value="<?= sanitize((string)($email['fromEmail'] ?? 'connect@yojak.co.in')); ?>">
                    </div>
                </div>
            </div>

            <div class="card" style="grid-column:1/-1;display:flex;justify-content:flex-end;">
                <button class="btn" type="submit"><?= sanitize('Save Settings'); ?></button>
            </div>
        </form>

        <div class="comm-grid" style="margin-top:16px;">
            <div class="card">
                <h3 style="margin:0 0 8px 0;"><?= sanitize('Send Test WhatsApp OTP'); ?></h3>
                <form method="post" action="/superadmin/comm_test_whatsapp.php" style="display:grid;gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <div class="inline-field">
                        <label for="test_mobile"><?= sanitize('Mobile (10 digits)'); ?></label>
                        <input id="test_mobile" name="mobile" inputmode="tel" maxlength="10" required>
                    </div>
                    <button class="btn secondary" type="submit"><?= sanitize('Send Test OTP'); ?></button>
                </form>
            </div>

            <div class="card">
                <h3 style="margin:0 0 8px 0;"><?= sanitize('Send Test Email'); ?></h3>
                <form method="post" action="/superadmin/comm_test_email.php" style="display:grid;gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <div class="inline-field">
                        <label for="test_email"><?= sanitize('Recipient Email'); ?></label>
                        <input id="test_email" name="email" type="email" required>
                    </div>
                    <button class="btn secondary" type="submit"><?= sanitize('Send Test Email'); ?></button>
                </form>
            </div>
        </div>
        <?php
    });
});
