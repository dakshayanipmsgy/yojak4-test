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
    $publishAtRaw = trim((string)($_POST['publish_at'] ?? ''));
    $item = load_content_by_id($id);
    if (!$item) {
        render_error_page('Content not found.');
        return;
    }

    if ($publishAtRaw === '') {
        set_flash('error', 'Publish date is required for scheduling.');
        redirect('/superadmin/content_edit.php?id=' . urlencode($id));
    }

    $publishAt = new DateTimeImmutable($publishAtRaw, new DateTimeZone('Asia/Kolkata'));
    $item['publishAt'] = $publishAt->format(DateTime::ATOM);
    $item['status'] = 'scheduled';
    $item['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    save_content_item($item);
    content_log(['event' => 'content_scheduled', 'id' => $id, 'when' => $item['publishAt'], 'user' => $user['username'] ?? 'unknown']);

    set_flash('success', 'Scheduled for publishing.');
    redirect('/superadmin/content_edit.php?id=' . urlencode($id));
});
