<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['ok' => false, 'error' => 'Invalid method.']);
        return;
    }

    require_csrf();
    $type = $_POST['type'] ?? 'blog';
    if (!in_array($type, ['blog', 'news'], true)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid type.']);
        return;
    }

    $prompt = trim((string)($_POST['prompt'] ?? ''));
    $length = $_POST['length'] ?? 'standard';
    $variation = $_POST['variation'] ?? 'high';
    $randomPlatform = !empty($_POST['random_platform']);

    $allowedLengths = ['short', 'standard', 'long'];
    if ($type === 'news' && !in_array($length, $allowedLengths, true)) {
        echo json_encode(['ok' => false, 'error' => 'News length is required.']);
        return;
    }
    if ($type === 'blog') {
        $length = 'standard';
    }

    if (!in_array($variation, ['low', 'medium', 'high'], true)) {
        $variation = 'high';
    }

    $jobId = generate_unique_job_id();
    $contentId = generate_unique_content_id($type);
    $nonce = strtoupper(bin2hex(random_bytes(6)));

    try {
        $jobId = create_content_job([
            'type' => $type,
            'prompt' => $prompt,
            'length' => $length,
            'randomPlatform' => $randomPlatform,
            'variation' => $variation,
            'user' => $user['username'] ?? 'unknown',
            'contentId' => $contentId,
            'nonce' => $nonce,
        ], $jobId);
    } catch (Throwable $e) {
        content_log(['event' => 'GEN_START_FAILED', 'jobId' => $jobId, 'error' => $e->getMessage()]);
        echo json_encode(['ok' => false, 'error' => 'Unable to create a new job. Please try again.']);
        return;
    }

    $startLine = sprintf('GEN_START jobId=%s contentId=%s type=%s nonce=%s', $jobId, $contentId, $type, $nonce);
    content_log([
        'event' => 'GEN_START',
        'jobId' => $jobId,
        'contentId' => $contentId,
        'type' => $type,
        'length' => $length,
        'nonce' => $nonce,
        'variation' => $variation,
        'user' => $user['username'] ?? 'unknown',
        'message' => $startLine,
    ]);
    content_log(['event' => 'generation_requested', 'jobId' => $jobId, 'type' => $type, 'length' => $length, 'user' => $user['username'] ?? 'unknown']);

    echo json_encode(['ok' => true, 'jobId' => $jobId]);
});
