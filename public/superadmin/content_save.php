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
    $title = trim((string)($_POST['title'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));
    $excerpt = trim((string)($_POST['excerpt'] ?? ''));
    $body = (string)($_POST['body'] ?? '');
    $publishAtRaw = $_POST['publish_at'] ?? '';

    $item = load_content_by_id($id);
    if (!$item) {
        render_error_page('Content not found.');
        return;
    }

    if (!content_validate_slug($slug)) {
        set_flash('error', 'Invalid slug format.');
        redirect('/superadmin/content_edit.php?id=' . urlencode($id));
    }

    $slug = ensure_slug_unique($item['type'], $slug, $id);

    $item['title'] = $title !== '' ? $title : $item['title'];
    $item['slug'] = $slug;
    $item['excerpt'] = $excerpt !== '' ? content_excerpt($excerpt) : content_excerpt($body);
    $item['bodyHtml'] = sanitize_body_html($body);
    $item['publishAt'] = $publishAtRaw ? (new DateTime($publishAtRaw, new DateTimeZone('Asia/Kolkata')))->format(DateTime::ATOM) : null;
    $item['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    if ($item['status'] === 'published') {
        $item['status'] = 'draft';
        $item['publishedAt'] = null;
    }

    save_content_item($item);
    content_log(['event' => 'content_saved', 'id' => $id, 'user' => $user['username'] ?? 'unknown']);
    set_flash('success', 'Saved as draft.');
    redirect('/superadmin/content_edit.php?id=' . urlencode($id));
});
