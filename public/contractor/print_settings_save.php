<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/print_settings.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $headerText = trim((string)($_POST['headerText'] ?? ''));
    $footerText = trim((string)($_POST['footerText'] ?? ''));
    $headerEnabled = !empty($_POST['headerEnabled']);
    $footerEnabled = !empty($_POST['footerEnabled']);
    $logoEnabled = !empty($_POST['logoEnabled']);
    $logoAlign = trim((string)($_POST['logoAlign'] ?? 'left'));
    if (!in_array($logoAlign, ['left', 'center', 'right'], true)) {
        $logoAlign = 'left';
    }

    if (mb_strlen($headerText) > 300 || mb_strlen($footerText) > 300) {
        set_flash('error', 'Header and footer text must be within 300 characters.');
        redirect('/contractor/print_settings.php');
        return;
    }

    $settings = load_contractor_print_settings($yojId);
    $settings['headerText'] = $headerText;
    $settings['footerText'] = $footerText;
    $settings['headerEnabled'] = $headerEnabled;
    $settings['footerEnabled'] = $footerEnabled;
    $settings['logoEnabled'] = $logoEnabled && !empty($settings['logoPublicPath']);
    $settings['logoAlign'] = $logoAlign;
    save_contractor_print_settings($yojId, $settings);

    logEvent(PACK_PRINT_LOG, [
        'event' => 'print_settings_saved',
        'yojId' => $yojId,
        'ip' => mask_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    ]);

    set_flash('success', 'Print settings updated.');
    redirect('/contractor/print_settings.php');
});
