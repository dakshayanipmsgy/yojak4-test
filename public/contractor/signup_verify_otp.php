<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_csrf();
    $mobile = trim($_SESSION['contractor_signup_mobile'] ?? '');
    if ($mobile === '' || !is_valid_mobile($mobile)) {
        set_flash('error', 'Please start signup again.');
        redirect('/contractor/signup.php');
    }

    $otp = trim($_POST['otp'] ?? '');
    if (!preg_match('/^[0-9]{6}$/', $otp)) {
        set_flash('error', 'Enter a valid 6-digit OTP.');
        redirect('/contractor/signup_verify.php');
    }

    $mobileE164 = '91' . normalize_mobile($mobile);
    $result = otp_verify_code($mobileE164, $otp);
    if (!$result['success']) {
        set_flash('error', $result['message']);
        redirect('/contractor/signup_verify.php');
    }

    $_SESSION['contractor_signup_verified'] = true;
    set_flash('success', 'Mobile verified. Continue signup.');
    redirect('/contractor/signup_create.php');
});
