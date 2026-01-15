<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $sessionUser = require_role('superadmin');
    if (!empty($sessionUser['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    require_csrf();

    $config = get_comm_config();
    $errors = [];

    $waEnabled = isset($_POST['wa_enabled']);
    $waPhone = trim($_POST['wa_phoneNumberId'] ?? '');
    $waToken = trim($_POST['wa_accessToken'] ?? '');
    $waTemplate = trim($_POST['wa_authTemplateName'] ?? '');
    $waLang = trim($_POST['wa_templateLang'] ?? 'en_US');
    $waSender = trim($_POST['wa_senderDisplay'] ?? 'YOJAK');

    if ($waEnabled) {
        if ($waPhone === '') {
            $errors[] = 'WhatsApp Phone Number ID is required when enabled.';
        }
        if ($waTemplate === '') {
            $errors[] = 'WhatsApp Auth Template Name is required when enabled.';
        }
        if ($waToken === '' && ($config['whatsapp']['accessTokenEnc'] ?? '') === '') {
            $errors[] = 'WhatsApp Access Token is required when enabled.';
        }
    }

    $emailEnabled = isset($_POST['email_enabled']);
    $smtpHost = trim($_POST['smtp_host'] ?? '');
    $smtpPort = (int)($_POST['smtp_port'] ?? 587);
    $smtpEnc = trim($_POST['smtp_encryption'] ?? 'tls');
    $smtpUser = trim($_POST['smtp_user'] ?? '');
    $smtpPass = trim($_POST['smtp_pass'] ?? '');
    $fromName = trim($_POST['from_name'] ?? 'YOJAK');
    $fromEmail = trim($_POST['from_email'] ?? 'connect@yojak.co.in');

    if ($emailEnabled) {
        if ($smtpHost === '') {
            $errors[] = 'SMTP host is required when email is enabled.';
        }
        if ($smtpPort <= 0) {
            $errors[] = 'SMTP port must be a valid number.';
        }
        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'From email must be valid.';
        }
        if ($smtpPass === '' && ($config['email']['smtpPassEnc'] ?? '') === '' && $smtpUser !== '') {
            $errors[] = 'SMTP password is required when username is provided.';
        }
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/superadmin/comm_settings.php');
    }

    $config['whatsapp']['enabled'] = $waEnabled;
    $config['whatsapp']['phoneNumberId'] = $waPhone;
    $config['whatsapp']['authTemplateName'] = $waTemplate;
    $config['whatsapp']['templateLang'] = $waLang !== '' ? $waLang : 'en_US';
    $config['whatsapp']['senderDisplay'] = $waSender !== '' ? $waSender : 'YOJAK';
    if ($waToken !== '') {
        $config['whatsapp']['accessTokenEnc'] = comm_encrypt($waToken);
    }

    $config['email']['enabled'] = $emailEnabled;
    $config['email']['smtpHost'] = $smtpHost;
    $config['email']['smtpPort'] = $smtpPort > 0 ? $smtpPort : 587;
    $config['email']['smtpEncryption'] = in_array($smtpEnc, ['tls', 'ssl', 'none'], true) ? $smtpEnc : 'tls';
    $config['email']['smtpUser'] = $smtpUser;
    $config['email']['fromName'] = $fromName !== '' ? $fromName : 'YOJAK';
    $config['email']['fromEmail'] = $fromEmail !== '' ? $fromEmail : 'connect@yojak.co.in';
    if ($smtpPass !== '') {
        $config['email']['smtpPassEnc'] = comm_encrypt($smtpPass);
    }

    save_comm_config($config);
    set_flash('success', 'Communication settings saved.');
    redirect('/superadmin/comm_settings.php');
});
