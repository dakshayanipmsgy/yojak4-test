<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/tender_pack_blueprints.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $id = trim((string)($_POST['id'] ?? ''));
    if ($id === '') {
        render_error_page('Missing blueprint id.');
        return;
    }
    $blueprint = pack_blueprint_load('contractor', $id, $yojId);
    if (!$blueprint) {
        render_error_page('Blueprint not found.');
        return;
    }
    $blueprint['archived'] = true;
    pack_blueprint_save($blueprint, $yojId);
    logEvent(DATA_PATH . '/logs/pack_blueprints.log', [
        'event' => 'pack_blueprint_archived',
        'scope' => 'contractor',
        'yojId' => $yojId,
        'blueprintId' => $id,
    ]);

    set_flash('success', 'Pack blueprint archived.');
    redirect('/contractor/tender_pack_blueprints.php');
});
