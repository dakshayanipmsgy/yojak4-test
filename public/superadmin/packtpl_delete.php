<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/packs_blueprints.php');
    }

    require_csrf();
    require_staff_actor();

    $packTplId = trim((string)($_POST['id'] ?? ''));
    $existing = load_packtpl_record('global', $packTplId);
    if (!$existing) {
        render_error_page('Pack preset not found.');
        return;
    }

    delete_packtpl_record('global', $packTplId);

    logEvent(DATA_PATH . '/logs/packs_blueprints.log', [
        'event' => 'global_packtpl_deleted',
        'packTplId' => $packTplId,
    ]);

    set_flash('success', 'Global pack preset deleted.');
    redirect('/superadmin/packs_blueprints.php');
});
