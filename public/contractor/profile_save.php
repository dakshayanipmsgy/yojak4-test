<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/profile.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $contractor = load_contractor($user['yojId']);
    if (!$contractor) {
        render_error_page('Contractor profile not found.');
        return;
    }

    $form = [
        'name' => trim((string)($_POST['name'] ?? '')),
        'firmName' => trim((string)($_POST['firmName'] ?? '')),
        'firmType' => trim((string)($_POST['firmType'] ?? '')),
        'addressLine1' => trim((string)($_POST['addressLine1'] ?? '')),
        'addressLine2' => trim((string)($_POST['addressLine2'] ?? '')),
        'district' => trim((string)($_POST['district'] ?? '')),
        'state' => trim((string)($_POST['state'] ?? '')),
        'pincode' => trim((string)($_POST['pincode'] ?? '')),
        'authorizedSignatoryName' => trim((string)($_POST['authorizedSignatoryName'] ?? '')),
        'authorizedSignatoryDesignation' => trim((string)($_POST['authorizedSignatoryDesignation'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
        'gstNumber' => trim((string)($_POST['gstNumber'] ?? '')),
        'panNumber' => strtoupper(trim((string)($_POST['panNumber'] ?? ''))),
        'bankName' => trim((string)($_POST['bankName'] ?? '')),
        'bankAccount' => trim((string)($_POST['bankAccount'] ?? '')),
        'ifsc' => strtoupper(trim((string)($_POST['ifsc'] ?? ''))),
        'placeDefault' => trim((string)($_POST['placeDefault'] ?? '')),
    ];

    $errors = [];
    $allowedFirmTypes = ['Proprietorship', 'Partnership', 'LLP', 'Company', 'Other'];
    if ($form['name'] !== '' && strlen($form['name']) < 2) {
        $errors[] = 'Name must be at least 2 characters.';
    }
    if ($form['firmName'] !== '' && strlen($form['firmName']) < 2) {
        $errors[] = 'Firm name must be at least 2 characters.';
    }
    if ($form['firmType'] !== '' && !in_array($form['firmType'], $allowedFirmTypes, true)) {
        $errors[] = 'Invalid firm type selected.';
    }
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }
    if ($form['pincode'] !== '' && !preg_match('/^[0-9]{6}$/', $form['pincode'])) {
        $errors[] = 'Pincode must be 6 digits.';
    }
    if ($form['panNumber'] !== '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $form['panNumber'])) {
        $errors[] = 'PAN should follow standard format (e.g., ABCDE1234F).';
    }
    if ($form['gstNumber'] !== '' && !preg_match('/^[0-9A-Z]{15}$/', $form['gstNumber'])) {
        $errors[] = 'GST number should be 15 characters.';
    }
    if ($form['ifsc'] !== '' && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $form['ifsc'])) {
        $errors[] = 'Invalid IFSC code.';
    }
    if ($form['bankAccount'] !== '' && strlen($form['bankAccount']) > 30) {
        $errors[] = 'Bank account number is too long.';
    }

    if ($errors) {
        $_SESSION['contractor_profile_form'] = $form;
        $_SESSION['contractor_profile_errors'] = $errors;
        redirect('/contractor/profile.php');
        return;
    }

    $nullable = static function (string $value): ?string {
        return $value === '' ? null : $value;
    };

    $contractor['name'] = $nullable($form['name']);
    $contractor['firmName'] = $nullable($form['firmName']);
    $contractor['firmType'] = $nullable($form['firmType']);
    $contractor['addressLine1'] = $nullable($form['addressLine1']);
    $contractor['addressLine2'] = $nullable($form['addressLine2']);
    $contractor['address'] = trim($form['addressLine1'] . ' ' . $form['addressLine2']);
    $contractor['district'] = $nullable($form['district']);
    $contractor['state'] = $nullable($form['state']);
    $contractor['pincode'] = $nullable($form['pincode']);
    $contractor['authorizedSignatoryName'] = $nullable($form['authorizedSignatoryName']);
    $contractor['authorizedSignatoryDesignation'] = $nullable($form['authorizedSignatoryDesignation']);
    $contractor['email'] = $nullable($form['email']);
    $contractor['gstNumber'] = $nullable($form['gstNumber']);
    $contractor['panNumber'] = $nullable($form['panNumber']);
    $contractor['bankName'] = $nullable($form['bankName']);
    $contractor['bankAccount'] = $nullable($form['bankAccount']);
    $contractor['ifsc'] = $nullable($form['ifsc']);
    $contractor['placeDefault'] = $nullable($form['placeDefault']);

    save_contractor($contractor);
    $_SESSION['user']['displayName'] = $form['firmName'] ?: ($form['name'] ?: ($contractor['mobile'] ?? ''));

    logEvent(DATA_PATH . '/logs/contractor_profile.log', [
        'event' => 'profile_saved',
        'yojId' => $contractor['yojId'],
        'ip' => mask_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    ]);

    set_flash('success', 'Profile updated.');
    redirect('/contractor/profile.php');
});
