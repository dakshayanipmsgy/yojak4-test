<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('staff_guide_editor');
    require_csrf();

    $id = staff_guide_sanitize_id((string)($_POST['id'] ?? ''));
    $direction = (string)($_POST['direction'] ?? '');

    if (!$id || !in_array($direction, ['up', 'down'], true)) {
        set_flash('error', 'Invalid reorder request.');
        redirect('/superadmin/staff_guide_manage/index.php');
    }

    $index = readJson(staff_guide_index_path());
    $entries = $index['sections'] ?? [];
    $entries = is_array($entries) ? $entries : [];
    usort($entries, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

    $currentIndex = null;
    foreach ($entries as $idx => $entry) {
        if (($entry['id'] ?? '') === $id) {
            $currentIndex = $idx;
            break;
        }
    }

    if ($currentIndex === null) {
        set_flash('error', 'Guide section not found.');
        redirect('/superadmin/staff_guide_manage/index.php');
    }

    $swapWith = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
    if (!isset($entries[$swapWith])) {
        redirect('/superadmin/staff_guide_manage/index.php');
    }

    $temp = $entries[$currentIndex];
    $entries[$currentIndex] = $entries[$swapWith];
    $entries[$swapWith] = $temp;

    foreach ($entries as $idx => &$entry) {
        $entry['order'] = $idx + 1;
    }
    unset($entry);

    $index['sections'] = $entries;
    staff_guide_save_index($index);

    staff_guide_log_event('STAFF_GUIDE_REORDER', [
        'by' => $actor['username'] ?? ($actor['empId'] ?? 'system'),
    ]);

    set_flash('success', 'Staff guide order updated.');
    redirect('/superadmin/staff_guide_manage/index.php');
});
