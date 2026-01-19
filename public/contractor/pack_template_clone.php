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

    $source = load_pack_template_record('global', null, $packTemplateId);
    if (!$source) {
        render_error_page('Pack template not found.');
        return;
    }

    $newPack = $source;
    $newPack['packTemplateId'] = generate_pack_template_id();
    $newPack['scope'] = 'contractor';
    $newPack['owner'] = ['yojId' => $yojId];
    $newPack['status'] = 'active';

    save_pack_template_record($newPack, 'contractor', $yojId);

    logEvent(DATA_PATH . '/logs/packs.log', [
        'event' => 'pack_template_cloned',
        'packTemplateId' => $newPack['packTemplateId'],
        'sourcePackTemplateId' => $packTemplateId,
        'scope' => 'contractor',
        'yojId' => $yojId,
        'ip' => mask_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    ]);

    set_flash('success', 'Pack template cloned to My Packs.');
    redirect('/contractor/packs_library.php');
});
