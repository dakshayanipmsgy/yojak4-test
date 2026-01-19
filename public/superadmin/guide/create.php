<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('guide_editor');
    require_csrf();

    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        set_flash('error', 'Title is required.');
        redirect('/superadmin/guide/new.php');
    }

    $summary = trim((string)($_POST['summary'] ?? ''));
    $audience = trim((string)($_POST['audience'] ?? 'contractor')) ?: 'contractor';
    $published = !empty($_POST['published']);
    $blocks = guide_collect_blocks_from_post($_POST);

    $index = readJson(guide_index_path());
    $entries = $index['sections'] ?? [];
    $existingIds = array_map(fn($entry) => $entry['id'] ?? '', is_array($entries) ? $entries : []);
    $id = guide_generate_id($title, $existingIds);

    $now = now_kolkata()->format(DateTime::ATOM);
    $section = [
        'id' => $id,
        'title' => $title,
        'summary' => $summary,
        'audience' => $audience,
        'published' => $published,
        'updatedAt' => $now,
        'contentBlocks' => $blocks,
    ];

    guide_save_section($section);

    $entries = is_array($entries) ? $entries : [];
    $maxOrder = 0;
    foreach ($entries as $entry) {
        $maxOrder = max($maxOrder, (int)($entry['order'] ?? 0));
    }
    $entries[] = [
        'id' => $id,
        'title' => $title,
        'published' => $published,
        'order' => $maxOrder + 1,
        'archived' => false,
    ];

    $index['version'] = $index['version'] ?? 1;
    $index['sections'] = $entries;
    guide_save_index($index);

    guide_log_event('GUIDE_CREATE', [
        'id' => $id,
        'by' => $actor['username'] ?? ($actor['empId'] ?? 'system'),
    ]);

    set_flash('success', 'Guide section created.');
    redirect('/superadmin/guide/edit.php?id=' . urlencode($id));
});
