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

    $index = readJson(guide_index_path());
    $entries = $index['sections'] ?? [];
    $entries = is_array($entries) ? $entries : [];
    $archived = false;

    foreach ($entries as &$entry) {
        if (($entry['id'] ?? '') === $id) {
            $entry['archived'] = empty($entry['archived']);
            $archived = $entry['archived'];
            if ($entry['archived']) {
                $entry['published'] = false;
            }
            break;
        }
    }
    unset($entry);

    $section['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    if ($archived) {
        $section['archivedAt'] = $section['updatedAt'];
        $section['published'] = false;
    } else {
        $section['archivedAt'] = null;
    }

    guide_save_section($section);

    $index['sections'] = $entries;
    guide_save_index($index);

    guide_log_event('GUIDE_UPDATE', [
        'id' => $id,
        'by' => $actor['username'] ?? ($actor['empId'] ?? 'system'),
        'action' => $archived ? 'archived' : 'restored',
    ]);

    set_flash('success', $archived ? 'Guide section archived.' : 'Guide section restored.');
    redirect('/superadmin/guide/index.php');
});
