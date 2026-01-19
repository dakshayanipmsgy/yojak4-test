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
    $blueprint = $blueprint ?: pack_blueprint_load('global', $id);
    if (!$blueprint) {
        render_error_page('Blueprint not found.');
        return;
    }

    ensure_packs_env($yojId);
    $packId = generate_pack_id($yojId);
    $now = now_kolkata()->format(DateTime::ATOM);
    $checklist = $blueprint['items']['checklist'] ?? [];

    $pack = [
        'packId' => $packId,
        'yojId' => $yojId,
        'title' => $blueprint['title'] ?? 'Tender Pack',
        'tenderTitle' => $blueprint['title'] ?? 'Tender Pack',
        'sourceTender' => [
            'type' => 'BLUEPRINT',
            'id' => $blueprint['id'] ?? '',
        ],
        'source' => 'blueprint',
        'createdAt' => $now,
        'updatedAt' => $now,
        'status' => 'Pending',
        'items' => pack_items_from_checklist($checklist),
        'checklist' => $checklist,
        'requiredFieldKeys' => $blueprint['items']['requiredFieldKeys'] ?? [],
        'templateIds' => $blueprint['items']['templates'] ?? [],
        'printStructure' => $blueprint['printStructure'] ?? [],
        'generatedDocs' => [],
        'defaultTemplatesApplied' => false,
    ];

    save_pack($pack);
    pack_log([
        'event' => 'pack_created_from_blueprint',
        'yojId' => $yojId,
        'packId' => $packId,
        'blueprintId' => $blueprint['id'] ?? '',
    ]);

    set_flash('success', 'Tender pack created from blueprint.');
    redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
});
