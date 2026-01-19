<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('staff_guide_editor');
    require_csrf();

    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        set_flash('error', 'Title is required.');
        redirect('/superadmin/staff_guide_manage/new.php');
    }

    $summary = trim((string)($_POST['summary'] ?? ''));
    $audience = trim((string)($_POST['audience'] ?? 'staff')) ?: 'staff';
    $published = !empty($_POST['published']);
    $blocks = guide_collect_blocks_from_post($_POST);

    $index = readJson(staff_guide_index_path());
    $entries = $index['sections'] ?? [];
    $existingIds = array_map(fn($entry) => $entry['id'] ?? '', is_array($entries) ? $entries : []);
    $id = staff_guide_generate_id($title, $existingIds);

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

    staff_guide_save_section($section);

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
    staff_guide_save_index($index);

    staff_guide_log_event('STAFF_GUIDE_CREATE', [
        'id' => $id,
        'by' => $actor['username'] ?? ($actor['empId'] ?? 'system'),
    ]);

    set_flash('success', 'Staff guide section created.');
    redirect('/superadmin/staff_guide_manage/edit.php?id=' . urlencode($id));
});
