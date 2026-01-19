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

    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        set_flash('error', 'Title is required.');
        redirect('/superadmin/guide/edit.php?id=' . urlencode($id));
    }

    $section['title'] = $title;
    $section['summary'] = trim((string)($_POST['summary'] ?? ''));
    $section['audience'] = trim((string)($_POST['audience'] ?? 'contractor')) ?: 'contractor';
    $section['published'] = !empty($_POST['published']);
    $section['contentBlocks'] = guide_collect_blocks_from_post($_POST);
    $section['updatedAt'] = now_kolkata()->format(DateTime::ATOM);

    guide_save_section($section);

    $index = readJson(guide_index_path());
    $entries = $index['sections'] ?? [];
    $entries = is_array($entries) ? $entries : [];
    foreach ($entries as &$entry) {
        if (($entry['id'] ?? '') === $id) {
            $entry['title'] = $title;
            $entry['published'] = $section['published'];
            break;
        }
    }
    unset($entry);

    $index['sections'] = $entries;
    guide_save_index($index);

    guide_log_event('GUIDE_UPDATE', [
        'id' => $id,
        'by' => $actor['username'] ?? ($actor['empId'] ?? 'system'),
    ]);

    set_flash('success', 'Guide section updated.');
    redirect('/superadmin/guide/edit.php?id=' . urlencode($id));
});
