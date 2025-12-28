<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/tenders.php');
    }
    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'] ?? '';
    ensure_packs_env($yojId);
    ensure_contractor_links_env($yojId);

    $src = trim($_POST['src'] ?? '');
    if ($src !== 'dept') {
        render_error_page('Unsupported source.');
        return;
    }

    $deptId = normalize_dept_id(trim($_POST['deptId'] ?? ''));
    $ytdId = trim($_POST['ytdId'] ?? '');
    if (!is_valid_dept_id($deptId) || $ytdId === '') {
        render_error_page('Missing tender details.');
        return;
    }

    $existing = find_pack_by_source($yojId, 'DEPT', $deptId . '|' . $ytdId);
    if ($existing) {
        set_flash('success', 'Pack already exists for this tender.');
        redirect('/contractor/pack_view.php?packId=' . urlencode($existing['packId']));
        return;
    }

    $snapshot = load_public_tender_snapshot($deptId, $ytdId);
    if (!$snapshot) {
        render_error_page('Tender not available.');
        return;
    }

    $link = load_contractor_link($yojId, $deptId);
    $linked = $link && ($link['status'] ?? '') === 'active';
    $requirementSetId = $snapshot['requirementSetId'] ?? null;
    $requirementSet = null;
    $items = [];

    if ($requirementSetId && $linked) {
        foreach (load_requirement_sets($deptId) as $set) {
            if (($set['setId'] ?? '') === $requirementSetId) {
                $requirementSet = $set;
                break;
            }
        }
        if ($requirementSet) {
            $items = pack_items_from_requirement_set($requirementSet);
        }
    }

    if (!$items) {
        $items = pack_items_from_checklist([]);
    }

    $packId = generate_pack_id($yojId);
    $now = now_kolkata()->format(DateTime::ATOM);
    $pack = [
        'packId' => $packId,
        'yojId' => $yojId,
        'title' => $snapshot['title'] ?? 'Tender Pack',
        'sourceTender' => [
            'type' => 'DEPT',
            'id' => $deptId . '|' . $ytdId,
            'deptId' => $deptId,
            'ytdId' => $ytdId,
            'source' => 'dept',
            'prefillApplied' => $linked,
        ],
        'source' => 'dept',
        'deptId' => $deptId,
        'ytdId' => $ytdId,
        'requirementSetId' => $requirementSetId,
        'prefillApplied' => $linked,
        'requirementSetApplied' => $linked && $requirementSet !== null,
        'officialChecklistLocked' => !$linked && $requirementSetId !== null,
        'createdAt' => $now,
        'updatedAt' => $now,
        'status' => 'Pending',
        'items' => $items,
        'generatedDocs' => [],
    ];

    save_pack($pack);
    pack_log([
        'event' => 'pack_created',
        'yojId' => $yojId,
        'packId' => $packId,
        'sourceType' => 'DEPT',
        'deptId' => $deptId,
        'ytdId' => $ytdId,
        'prefillApplied' => $linked,
    ]);

    set_flash('success', 'Tender pack created.');
    redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
});
