<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/packs_library.php');
    }
    require_csrf();

    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packTemplateId = trim((string)($_POST['packTemplateId'] ?? ''));
    if ($packTemplateId === '') {
        render_error_page('Pack template ID missing.');
        return;
    }

    if (archive_pack_template_record('contractor', $yojId, $packTemplateId)) {
        logEvent(DATA_PATH . '/logs/packs.log', [
            'event' => 'pack_template_archived',
            'packTemplateId' => $packTemplateId,
            'scope' => 'contractor',
            'yojId' => $yojId,
            'ip' => mask_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        ]);
        set_flash('success', 'Pack template archived.');
    }

    redirect('/contractor/packs_library.php');
});
