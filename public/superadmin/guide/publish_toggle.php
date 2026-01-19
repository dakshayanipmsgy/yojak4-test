<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('guide_editor');
    require_csrf();

    $id = guide_sanitize_id((string)($_POST['id'] ?? ''));
    if (!$id) {
        set_flash('error', 'Missing guide id.');
        redirect('/superadmin/guide/index.php');
    }

    $section = guide_load_section($id);
    if (!$section) {
        set_flash('error', 'Guide section not found.');
        redirect('/superadmin/guide/index.php');
    }

    $section['published'] = empty($section['published']);
    $section['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    if (!empty($section['archivedAt'])) {
        $section['published'] = false;
    }
    guide_save_section($section);

    $index = readJson(guide_index_path());
    $entries = $index['sections'] ?? [];
    $entries = is_array($entries) ? $entries : [];
    foreach ($entries as &$entry) {
        if (($entry['id'] ?? '') === $id) {
            $entry['published'] = $section['published'];
            break;
        }
    }
    unset($entry);

    $index['sections'] = $entries;
    guide_save_index($index);

    guide_log_event('GUIDE_PUBLISH_TOGGLE', [
        'id' => $id,
        'published' => $section['published'],
        'by' => $actor['username'] ?? ($actor['empId'] ?? 'system'),
    ]);

    set_flash('success', $section['published'] ? 'Guide published.' : 'Guide unpublished.');
    redirect('/superadmin/guide/index.php');
});
