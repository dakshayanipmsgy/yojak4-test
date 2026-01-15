<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $sessionUser = require_role('superadmin');
    if (!empty($sessionUser['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    require_csrf();

    $mobile = trim($_POST['mobile'] ?? '');
    if (!is_valid_mobile($mobile)) {
        set_flash('error', 'Enter a valid 10-digit mobile number.');
        redirect('/superadmin/comm_settings.php');
    }

    $config = get_comm_config();
    if (empty($config['whatsapp']['enabled'])) {
        set_flash('error', 'WhatsApp OTP is disabled.');
        redirect('/superadmin/comm_settings.php');
    }

    $phoneId = $config['whatsapp']['phoneNumberId'] ?? '';
    $template = $config['whatsapp']['authTemplateName'] ?? '';
    $tokenEnc = $config['whatsapp']['accessTokenEnc'] ?? '';
    if ($phoneId === '' || $template === '' || $tokenEnc === '') {
        set_flash('error', 'WhatsApp settings are incomplete.');
        redirect('/superadmin/comm_settings.php');
    }

    $otpCode = otp_generate_code();
    $mobileE164 = '91' . normalize_mobile($mobile);
    $result = whatsapp_send_otp($mobileE164, $otpCode, $config);

    if ($result['success']) {
        set_flash('success', 'Test OTP sent via WhatsApp.');
    } else {
        set_flash('error', 'Failed to send test OTP. Please verify settings.');
    }

    redirect('/superadmin/comm_settings.php');
});
