<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/superadmin/tender_discovery.php');
    }

    require_csrf();
    require_role('superadmin');
    ensure_tender_discovery_env();

    $input = $_POST['sources'] ?? [];
    $names = $input['name'] ?? [];
    $urls = $input['url'] ?? [];
    $types = $input['type'] ?? [];
    $ids = $input['sourceId'] ?? [];
    $actives = $input['active'] ?? [];
    $hints = $input['parseHints'] ?? [];

    $rows = [];
    foreach ($names as $idx => $name) {
        $rows[] = [
            'sourceId' => $ids[$idx] ?? '',
            'name' => $name,
            'type' => $types[$idx] ?? '',
            'url' => $urls[$idx] ?? '',
            'active' => isset($actives[$idx]) && (string)$actives[$idx] === '1',
            'parseHints' => $hints[$idx] ?? '',
        ];
    }

    tender_discovery_save_sources($rows);
    tender_discovery_log([
        'event' => 'sources_saved',
        'count' => count($rows),
    ]);

    set_flash('success', 'Sources saved.');
    redirect('/superadmin/tender_discovery.php');
});
