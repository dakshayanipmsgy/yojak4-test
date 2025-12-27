<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    try {
        $user = require_role('superadmin');
        if (!empty($user['mustResetPassword'])) {
            redirect('/auth/force_reset.php');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            render_error_page('Invalid method.');
            return;
        }

        require_csrf();

        $type = $_POST['type'] ?? '';
        $contentId = trim((string)($_POST['contentId'] ?? ''));
        if (!in_array($type, ['blog', 'news'], true) || $contentId === '') {
            render_error_page('Invalid draft request.');
            return;
        }

        $draft = content_v2_load_draft($type, $contentId);
        if (!$draft || ($draft['deletedAt'] ?? null) !== null) {
            render_error_page('Draft not found.');
            return;
        }

        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            render_error_page('Title is required.');
            return;
        }

        $inputSlug = trim((string)($_POST['slug'] ?? ''));
        $slug = content_v2_slugify($inputSlug !== '' ? $inputSlug : $title);
        $slug = content_v2_unique_slug($type, $title, $slug, $contentId);

        $excerpt = trim((string)($_POST['excerpt'] ?? ''));
        $body = sanitize_body_html((string)($_POST['body'] ?? ''));
        if ($body === '') {
            render_error_page('Body cannot be empty after sanitization.');
            return;
        }
        if ($excerpt === '') {
            $excerpt = content_excerpt($body, 40);
        }

        $now = now_kolkata()->format(DateTime::ATOM);
        $outputHash = content_output_hash($body);
        $draft['title'] = $title;
        $draft['slug'] = $slug;
        $draft['excerpt'] = $excerpt;
        $draft['bodyHtml'] = $body;
        $draft['updatedAt'] = $now;
        $draft['generation']['outputHash'] = $outputHash;

        content_v2_save_draft($draft, true);

        content_v2_log([
            'event' => 'CONTENT_DRAFT_SAVE',
            'contentId' => $contentId,
            'type' => $type,
            'slug' => $slug,
            'user' => $user['username'] ?? 'unknown',
        ]);

        set_flash('success', 'Draft saved.');
        redirect('/superadmin/content_draft_view.php?type=' . urlencode($type) . '&contentId=' . urlencode($contentId));
    } catch (Throwable $e) {
        content_v2_log([
            'event' => 'CONTENT_DRAFT_SAVE_ERROR',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        render_error_page('Server error. Please check logs.');
    }
});
