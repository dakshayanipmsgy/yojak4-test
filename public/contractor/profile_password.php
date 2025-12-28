<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $contractor = load_contractor($user['yojId'] ?? '');
    if (!$contractor) {
        render_error_page('Contractor profile not found.');
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $uaHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $current = $_POST['password_current'] ?? '';
        $newPassword = $_POST['password_new'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if ($current === '' || $newPassword === '' || $confirm === '') {
            $errors[] = 'All password fields are required.';
        }

        if (!$errors && !password_verify($current, $contractor['passwordHash'] ?? '')) {
            $errors[] = 'Current password is incorrect.';
        }

        if (!$errors && strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }

        if (!$errors && $newPassword === $current) {
            $errors[] = 'New password must be different from your current password.';
        }

        if (!$errors && $newPassword !== $confirm) {
            $errors[] = 'New password and confirmation must match.';
        }

        if ($errors) {
            log_auth_attempt([
                'event' => 'contractor_password_change',
                'yojId' => $contractor['yojId'],
                'result' => 'fail',
                'reason' => $errors[0] ?? 'validation_failed',
                'ip' => $ip,
                'uaHash' => $uaHash,
            ]);
            set_flash('error', implode(' ', $errors));
            redirect('/contractor/profile.php');
        }

        $updated = update_contractor_password($contractor['yojId'], $newPassword, 'self');
        log_auth_attempt([
            'event' => 'contractor_password_change',
            'yojId' => $contractor['yojId'],
            'result' => $updated ? 'success' : 'fail',
            'ip' => $ip,
            'uaHash' => $uaHash,
        ]);

        if ($updated) {
            set_flash('success', 'Password updated successfully.');
        } else {
            set_flash('error', 'Unable to update password. Please try again.');
        }

        redirect('/contractor/profile.php');
    }

    redirect('/contractor/profile.php');
});
