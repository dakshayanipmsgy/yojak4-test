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

    $section['published'] = !empty($section['published']) ? false : true;
    $section['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    staff_guide_save_section($section);

    $index = readJson(staff_guide_index_path());
    $entries = $index['sections'] ?? [];
    $entries = is_array($entries) ? $entries : [];
    foreach ($entries as &$entry) {
        if (($entry['id'] ?? '') === $id) {
            $entry['published'] = $section['published'];
        }
    }
    unset($entry);

    $index['version'] = $index['version'] ?? 1;
    $index['sections'] = $entries;
    staff_guide_save_index($index);

    staff_guide_log_event('STAFF_GUIDE_PUBLISH_TOGGLE', [
        'id' => $id,
        'by' => $actor['username'] ?? ($actor['empId'] ?? 'system'),
        'published' => $section['published'],
    ]);

    set_flash('success', 'Staff guide publish status updated.');
    redirect('/superadmin/staff_guide_manage/index.php');
});
