<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('staff_guide_editor');
    require_csrf();

    $id = staff_guide_sanitize_id((string)($_POST['id'] ?? ''));
    if (!$id) {
        set_flash('error', 'Guide section not found.');
        redirect('/superadmin/staff_guide_manage/index.php');
    }

    $section = staff_guide_load_section($id);
    if (!$section) {
        set_flash('error', 'Guide section not found.');
        redirect('/superadmin/staff_guide_manage/index.php');
    }

    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        set_flash('error', 'Title is required.');
        redirect('/superadmin/staff_guide_manage/edit.php?id=' . urlencode($id));
    }

    $summary = trim((string)($_POST['summary'] ?? ''));
    $audience = trim((string)($_POST['audience'] ?? 'staff')) ?: 'staff';
    $published = !empty($_POST['published']);
    $blocks = guide_collect_blocks_from_post($_POST);

    $section['title'] = $title;
    $section['summary'] = $summary;
    $section['audience'] = $audience;
    $section['published'] = $published;
    $section['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    $section['contentBlocks'] = $blocks;

    staff_guide_save_section($section);

    $index = readJson(staff_guide_index_path());
    $entries = $index['sections'] ?? [];
    $entries = is_array($entries) ? $entries : [];
    foreach ($entries as &$entry) {
        if (($entry['id'] ?? '') === $id) {
            $entry['title'] = $title;
            $entry['published'] = $published;
        }
    }
    unset($entry);

    $index['version'] = $index['version'] ?? 1;
    $index['sections'] = $entries;
    staff_guide_save_index($index);

    staff_guide_log_event('STAFF_GUIDE_UPDATE', [
        'id' => $id,
        'by' => $actor['username'] ?? ($actor['empId'] ?? 'system'),
    ]);

    set_flash('success', 'Staff guide updated.');
    redirect('/superadmin/staff_guide_manage/edit.php?id=' . urlencode($id));
});
