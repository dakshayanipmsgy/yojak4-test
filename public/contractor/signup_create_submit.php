<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_csrf();
    $mobile = trim($_SESSION['contractor_signup_mobile'] ?? '');
    $verified = !empty($_SESSION['contractor_signup_verified']);
    if ($mobile === '' || !is_valid_mobile($mobile) || !$verified) {
        set_flash('error', 'Please verify your mobile first.');
        redirect('/contractor/signup.php');
    }

    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    if (strlen($password) < 8) {
        set_flash('error', 'Password must be at least 8 characters.');
        redirect('/contractor/signup_create.php');
    }
    if (mobile_exists_in_pending($mobile) || mobile_exists_in_approved($mobile)) {
        set_flash('error', 'An account with this mobile already exists.');
        redirect('/contractor/signup.php');
    }

    create_pending_contractor($mobile, $password, $name);
    unset($_SESSION['contractor_signup_mobile'], $_SESSION['contractor_signup_verified']);
    set_flash('success', 'Signup received. Pending superadmin approval.');
    redirect('/contractor/login.php');
});
