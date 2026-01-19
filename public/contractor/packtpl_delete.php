<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/packs_blueprints.php?tab=mine');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packTplId = trim((string)($_POST['id'] ?? ''));

    $existing = load_packtpl_record('contractor', $packTplId, $yojId);
    if (!$existing || (($existing['owner']['yojId'] ?? '') !== $yojId)) {
        render_error_page('Pack preset not found.');
        return;
    }

    delete_packtpl_record('contractor', $packTplId, $yojId);

    logEvent(DATA_PATH . '/logs/packs_blueprints.log', [
        'event' => 'packtpl_deleted',
        'yojId' => $yojId,
        'packTplId' => $packTplId,
    ]);

    set_flash('success', 'Pack preset deleted.');
    redirect('/contractor/packs_blueprints.php?tab=mine');
});
