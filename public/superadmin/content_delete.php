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
    $id = $_POST['id'] ?? '';
    $item = load_content_by_id($id);
    if (!$item) {
        render_error_page('Content not found.');
        return;
    }

    $item['status'] = 'deleted';
    $item['publishAt'] = null;
    $item['publishedAt'] = null;
    $item['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    save_content_item($item);
    content_log(['event' => 'content_deleted', 'id' => $id, 'user' => $user['username'] ?? 'unknown']);

    set_flash('success', 'Content deleted.');
    redirect('/superadmin/content_studio.php');
});
