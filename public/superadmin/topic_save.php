<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');

$respond = function (array $payload): void {
    echo json_encode($payload);
};

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        $respond(['ok' => false, 'error' => 'Method not allowed.']);
        return;
    }

    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        $respond(['ok' => false, 'error' => 'Password reset required.']);
        return;
    }

    require_csrf();

    $type = $_POST['type'] ?? '';
    if (!in_array($type, ['blog', 'news'], true)) {
        $respond(['ok' => false, 'error' => 'Invalid type.']);
        return;
    }

    $topicTitle = trim((string)($_POST['topicTitle'] ?? ''));
    if ($topicTitle === '' || strlen($topicTitle) < 10 || strlen($topicTitle) > 120) {
        $respond(['ok' => false, 'error' => 'Topic title must be between 10 and 120 characters.']);
        return;
    }

    $topicAngle = trim((string)($_POST['topicAngle'] ?? ''));
    $audience = trim((string)($_POST['audience'] ?? ''));
    if ($audience === '') {
        $audience = 'Jharkhand government contractors';
    }

    $newsLength = null;
    if ($type === 'news') {
        $len = $_POST['newsLength'] ?? '';
        if (in_array($len, ['short', 'standard', 'long'], true)) {
            $newsLength = $len;
        }
    }

    $keywords = topic_v2_parse_keywords($_POST['keywords'] ?? []);
    $source = ($_POST['source'] ?? '') === 'manual' ? 'manual' : 'ai';
    $now = now_kolkata()->format(DateTime::ATOM);
    $topicId = topic_v2_generate_topic_id();
    $jobId = trim((string)($_POST['jobId'] ?? ''));

    $aiMeta = null;
    if ($source === 'ai') {
        $rawSnippet = (string)($_POST['rawTextSnippet'] ?? '');
        $modelUsed = trim((string)($_POST['modelUsed'] ?? ($_POST['model'] ?? '')));
        $httpStatus = isset($_POST['httpStatus']) && $_POST['httpStatus'] !== '' ? (int)$_POST['httpStatus'] : null;
        $aiOk = ($_POST['aiOk'] ?? '') !== '' ? (bool)(int)$_POST['aiOk'] : null;
        $aiError = trim((string)($_POST['aiError'] ?? ''));
        $aiMeta = [
            'provider' => trim((string)($_POST['provider'] ?? '')),
            'modelUsed' => $modelUsed,
            'model' => $modelUsed,
            'requestId' => trim((string)($_POST['requestId'] ?? '')),
            'httpStatus' => $httpStatus,
            'promptHash' => trim((string)($_POST['promptHash'] ?? '')),
            'nonce' => trim((string)($_POST['nonce'] ?? '')),
            'generatedAt' => trim((string)($_POST['generatedAt'] ?? $now)),
            'rawTextSnippet' => function_exists('mb_substr') ? mb_substr($rawSnippet, 0, 500, 'UTF-8') : substr($rawSnippet, 0, 500),
            'ok' => $aiOk,
            'error' => $aiError !== '' ? $aiError : null,
        ];
    }

    $record = [
        'topicId' => $topicId,
        'type' => $type,
        'topicTitle' => $topicTitle,
        'topicAngle' => $topicAngle,
        'audience' => $audience,
        'keywords' => $keywords,
        'newsLength' => $type === 'news' ? $newsLength : null,
        'status' => 'draft',
        'source' => $source,
        'aiMeta' => $source === 'ai' ? $aiMeta : null,
        'createdAt' => $now,
        'updatedAt' => $now,
        'deletedAt' => null,
    ];

    topic_v2_save_record($record);

    content_v2_log([
        'event' => 'TOPIC_SAVE',
        'topicId' => $topicId,
        'type' => $type,
        'source' => $source,
        'jobId' => $jobId,
        'provider' => $aiMeta['provider'] ?? null,
        'promptHash' => $aiMeta['promptHash'] ?? null,
    ]);

    $respond([
        'ok' => true,
        'topicId' => $topicId,
        'saved' => [
            'topicId' => $topicId,
            'topicTitle' => $topicTitle,
            'status' => 'draft',
            'createdAt' => $now,
            'type' => $type,
        ],
    ]);
} catch (Throwable $e) {
    content_v2_log([
        'event' => 'TOPIC_SAVE_ERROR',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    http_response_code(500);
    $respond(['ok' => false, 'error' => 'Server error. Please check logs.']);
}
