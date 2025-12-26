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
    $randomPlatform = !empty($_POST['random_platform']);

    $jobId = generate_unique_job_id();
    $contentId = generate_unique_content_id($type);
    $nonce = strtoupper(bin2hex(random_bytes(6)));

    $jobId = create_content_job([
        'type' => $type,
        'prompt' => $prompt,
        'length' => $length,
        'randomPlatform' => $randomPlatform,
        'user' => $user['username'] ?? 'unknown',
        'contentId' => $contentId,
        'nonce' => $nonce,
    ], $jobId);

    $startLine = sprintf('GEN_START jobId=%s contentId=%s type=%s nonce=%s', $jobId, $contentId, $type, $nonce);
    content_log(['event' => 'GEN_START', 'jobId' => $jobId, 'contentId' => $contentId, 'type' => $type, 'nonce' => $nonce, 'user' => $user['username'] ?? 'unknown', 'message' => $startLine]);
    content_log(['event' => 'generation_requested', 'jobId' => $jobId, 'type' => $type, 'user' => $user['username'] ?? 'unknown']);

    echo json_encode(['ok' => true, 'jobId' => $jobId]);
});
