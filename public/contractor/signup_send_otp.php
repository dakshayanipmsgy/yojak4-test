<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_csrf();

    $mobile = trim($_POST['mobile'] ?? '');
    if (!is_valid_mobile($mobile)) {
        set_flash('error', 'Enter a valid 10-digit mobile number.');
        redirect('/contractor/signup.php');
    }
    if (mobile_exists_in_pending($mobile) || mobile_exists_in_approved($mobile)) {
        set_flash('error', 'An account with this mobile already exists.');
        redirect('/contractor/signup.php');
    }

    $config = get_comm_config();
    if (empty($config['whatsapp']['enabled'])) {
        set_flash('error', 'WhatsApp OTP is not available right now. Please contact support.');
        redirect('/contractor/signup.php');
    }

    $phoneId = $config['whatsapp']['phoneNumberId'] ?? '';
    $template = $config['whatsapp']['authTemplateName'] ?? '';
    $tokenEnc = $config['whatsapp']['accessTokenEnc'] ?? '';
    if ($phoneId === '' || $template === '' || $tokenEnc === '') {
        set_flash('error', 'WhatsApp settings are incomplete. Please contact support.');
        redirect('/contractor/signup.php');
    }

    $mobileE164 = '91' . normalize_mobile($mobile);
    $deviceFingerprint = otp_device_fingerprint();
    [$allowed, $reason] = otp_rate_limit_allowed($mobileE164, $deviceFingerprint);
    if (!$allowed) {
        set_flash('error', $reason);
        redirect('/contractor/signup.php');
    }

    $otpCode = otp_generate_code();
    $record = otp_create_record($mobileE164, $otpCode, $deviceFingerprint);
    $sendResult = whatsapp_send_otp($mobileE164, $otpCode, $config);

    $record['meta']['waMessageId'] = $sendResult['messageId'] ?? null;
    $record['meta']['providerStatus'] = $sendResult['status'] ?? 'failed';
    $record['meta']['providerError'] = $sendResult['error'] ?? null;
    $record['status'] = $sendResult['success'] ? 'sent' : 'failed';
    otp_store_record($record);

    if (!$sendResult['success']) {
        set_flash('error', 'Could not send OTP. Please retry.');
        redirect('/contractor/signup.php');
    }

    $_SESSION['contractor_signup_mobile'] = normalize_mobile($mobile);
    $_SESSION['contractor_signup_verified'] = false;

    set_flash('success', 'OTP sent via WhatsApp. Please verify.');
    redirect('/contractor/signup_verify.php');
});
