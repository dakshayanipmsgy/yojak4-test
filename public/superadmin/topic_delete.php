<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');

$reply = function (array $payload): void {
    echo json_encode($payload);
};

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        $reply(['ok' => false, 'error' => 'Method not allowed.']);
        return;
    }

    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        $reply(['ok' => false, 'error' => 'Password reset required.']);
        return;
    }

    require_csrf();

    $type = $_POST['type'] ?? '';
    $topicId = trim((string)($_POST['topicId'] ?? ''));
    if (!in_array($type, ['blog', 'news'], true) || $topicId === '') {
        $reply(['ok' => false, 'error' => 'Invalid request.']);
        return;
    }

    $deleted = topic_v2_soft_delete($type, $topicId);
    if (!$deleted) {
        $reply(['ok' => false, 'error' => 'Topic not found.']);
        return;
    }

    content_v2_log([
        'event' => 'TOPIC_DELETE',
        'topicId' => $topicId,
        'type' => $type,
    ]);

    $reply(['ok' => true]);
} catch (Throwable $e) {
    content_v2_log([
        'event' => 'TOPIC_DELETE_ERROR',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    http_response_code(500);
    $reply(['ok' => false, 'error' => 'Server error. Please check logs.']);
}
