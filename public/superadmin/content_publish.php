<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        render_error_page('Invalid method.');
        return;
    }

    require_csrf();

    $v2Type = $_POST['type'] ?? '';
    $v2ContentId = trim((string)($_POST['contentId'] ?? ''));
    if (in_array($v2Type, ['blog', 'news'], true) && $v2ContentId !== '') {
        $draft = content_v2_load_draft($v2Type, $v2ContentId);
        if (!$draft) {
            render_error_page('Draft not found.');
            return;
        }
        $now = now_kolkata()->format(DateTime::ATOM);
        $draft['status'] = 'published';
        $draft['publishedAt'] = $now;
        $draft['updatedAt'] = $now;
        content_v2_save_draft($draft, true);
        content_v2_log([
            'event' => 'CONTENT_PUBLISH',
            'contentId' => $v2ContentId,
            'type' => $v2Type,
            'user' => $user['username'] ?? 'unknown',
        ]);
        set_flash('success', 'Draft marked as published.');
        redirect('/superadmin/content_draft_view.php?type=' . urlencode($v2Type) . '&contentId=' . urlencode($v2ContentId));
        return;
    }

    $id = $_POST['id'] ?? '';
    $item = load_content_by_id($id);
    if (!$item) {
        render_error_page('Content not found.');
        return;
    }

    $item['status'] = 'published';
    $item['publishedAt'] = now_kolkata()->format(DateTime::ATOM);
    $item['publishAt'] = null;
    $item['updatedAt'] = $item['publishedAt'];
    save_content_item($item);
    content_log(['event' => 'content_published', 'id' => $id, 'user' => $user['username'] ?? 'unknown']);

    set_flash('success', 'Published.');
    redirect('/superadmin/content_edit.php?id=' . urlencode($id));
});
