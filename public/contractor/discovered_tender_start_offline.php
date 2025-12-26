<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/discovered_tenders.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_offline_tender_env($yojId);
    ensure_tender_discovery_env();

    $discId = trim($_POST['discId'] ?? '');
    $record = $discId !== '' ? tender_discovery_load_discovered($discId) : null;
    if (!$record) {
        render_error_page('Discovered tender not found.');
        return;
    }

    $existing = find_offline_tender_by_discovery($yojId, $discId);
    if ($existing) {
        set_flash('success', 'Offline prep already exists.');
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($existing['id']));
        return;
    }

    $offtdId = generate_offtd_id($yojId);
    $now = now_kolkata()->format(DateTime::ATOM);
    $extracted = offline_tender_defaults();
    $extracted['publishDate'] = $record['publishedAt'] ?? null;
    $extracted['submissionDeadline'] = $record['deadlineAt'] ?? null;

    $tender = [
        'yojId' => $yojId,
        'id' => $offtdId,
        'title' => $record['title'] ?? 'Discovered Tender',
        'status' => 'draft',
        'createdAt' => $now,
        'updatedAt' => $now,
        'sourceFiles' => [],
        'ai' => [
            'lastRunAt' => null,
            'rawText' => '',
            'parsedOk' => false,
            'errors' => [],
        ],
        'extracted' => $extracted,
        'checklist' => [],
        'deletedAt' => null,
        'source' => [
            'type' => 'tender_discovery',
            'discId' => $record['discId'] ?? $discId,
            'sourceId' => $record['sourceId'] ?? null,
            'originalUrl' => $record['originalUrl'] ?? null,
            'title' => $record['title'] ?? null,
        ],
        'location' => $record['location'] ?? 'Jharkhand',
    ];

    save_offline_tender($tender);

    tender_discovery_log([
        'event' => 'start_offline_prep',
        'discId' => $discId,
        'yojId' => $yojId,
        'offtdId' => $offtdId,
    ]);

    set_flash('success', 'Offline tender prep started from discovery.');
    redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
});
