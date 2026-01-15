<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $sessionUser = require_role('superadmin');
    if (!empty($sessionUser['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    require_csrf();

    $email = trim($_POST['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Enter a valid email address.');
        redirect('/superadmin/comm_settings.php');
    }

    $config = get_comm_config();
    if (empty($config['email']['enabled'])) {
        set_flash('error', 'Email is disabled.');
        redirect('/superadmin/comm_settings.php');
    }

    $smtp = $config['email'] ?? [];
    if (($smtp['smtpHost'] ?? '') === '' || ($smtp['fromEmail'] ?? '') === '') {
        set_flash('error', 'SMTP settings are incomplete.');
        redirect('/superadmin/comm_settings.php');
    }

    $subject = 'YOJAK SMTP Test';
    $body = "Hello,\n\nThis is a test email from YOJAK.\n\nRegards,\nYOJAK";
    $result = smtp_send_email($smtp, $email, $subject, $body);

    logEvent(DATA_PATH . '/logs/email.log', [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => 'EMAIL_SEND_TEST',
        'to' => $email,
        'status' => $result['success'] ? 'sent' : 'failed',
        'error' => $result['success'] ? null : $result['error'],
    ]);

    if ($result['success']) {
        set_flash('success', 'Test email sent.');
    } else {
        set_flash('error', 'Failed to send test email: ' . $result['error']);
    }

    redirect('/superadmin/comm_settings.php');
});
