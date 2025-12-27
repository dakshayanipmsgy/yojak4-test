<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');

$response = ['ok' => false];

$send = function (array $payload) {
    echo json_encode($payload);
};

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        $send(['ok' => false, 'error' => 'Method not allowed.']);
        return;
    }

    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        $send(['ok' => false, 'error' => 'Password reset required.']);
        return;
    }

    require_csrf();

    $type = $_POST['type'] ?? '';
    if (!in_array($type, ['blog', 'news'], true)) {
        $send(['ok' => false, 'error' => 'Invalid type.']);
        return;
    }

    $prompt = trim((string)($_POST['prompt'] ?? ''));
    $count = (int)($_POST['count'] ?? 5);
    if ($count < 4 || $count > 7) {
        $count = 5;
    }

    $newsLength = null;
    if ($type === 'news') {
        $candidateLength = $_POST['newsLength'] ?? '';
        if (in_array($candidateLength, ['short', 'standard', 'long'], true)) {
            $newsLength = $candidateLength;
        }
    }

    $configResult = ai_get_config();
    if (!$configResult['ok']) {
        $send(['ok' => false, 'error' => 'AI is not configured. Superadmin: set provider, API key, and model in AI Studio.', 'details' => $configResult['errors']]);
        return;
    }

    $jobId = topic_v2_generate_job_id();
    $nonce = strtoupper(bin2hex(random_bytes(6)));
    $startedAt = now_kolkata()->format(DateTime::ATOM);

    $prompts = topic_v2_build_prompts($type, $prompt, $newsLength, $count, $nonce);

    $aiResult = ai_call_text(
        'contentTopics',
        $prompts['systemPrompt'],
        $prompts['userPrompt'],
        [
            'expectJson' => true,
            'temperature' => $type === 'news' ? 0.55 : 0.7,
            'maxTokens' => 600,
        ]
    );

    $results = ($aiResult['ok'] ?? false) ? topic_v2_parse_results($aiResult['json'] ?? null, $count) : [];
    $requiredMin = max(4, min($count, 7));
    $errors = $aiResult['errors'] ?? [];
    $ok = ($aiResult['ok'] ?? false) && count($results) >= $requiredMin;

    if (!$ok) {
        if (count($results) < $requiredMin) {
            $errors[] = 'AI returned fewer topics than requested (' . $requiredMin . ').';
        }
        if (count($results) === 0) {
            $errors[] = 'No topics were generated.';
        }
    }

    $rawText = (string)($aiResult['rawText'] ?? '');
    $rawSnippet = function_exists('mb_substr') ? mb_substr($rawText, 0, 500, 'UTF-8') : substr($rawText, 0, 500);
    $finishedAt = now_kolkata()->format(DateTime::ATOM);

    $errorText = implode(' | ', array_slice($errors, 0, 3));
    if (!$ok && $errorText === '') {
        $errorText = 'AI call failed.';
    }
    $aiMeta = [
        'provider' => $aiResult['provider'] ?? ($aiResult['rawEnvelope']['provider'] ?? ''),
        'modelUsed' => $aiResult['modelUsed'] ?? ($aiResult['rawEnvelope']['model'] ?? ''),
        'httpStatus' => $aiResult['httpStatus'] ?? ($aiResult['rawEnvelope']['httpStatus'] ?? null),
        'requestId' => $aiResult['requestId'] ?? ($aiResult['rawEnvelope']['requestId'] ?? null),
        'promptHash' => $prompts['promptHash'],
        'nonce' => $nonce,
        'generatedAt' => $startedAt,
        'rawTextSnippet' => $rawSnippet,
        'ok' => $ok,
        'error' => $errorText !== '' ? $errorText : null,
    ];

    $jobPayload = [
        'jobId' => $jobId,
        'type' => $type,
        'countRequested' => $count,
        'promptUsed' => $prompt,
        'nonce' => $nonce,
        'aiMeta' => array_merge($aiMeta, [
            'startedAt' => $startedAt,
            'finishedAt' => $finishedAt,
        ]),
        'results' => $results,
        'ok' => $ok,
        'error' => $aiMeta['error'],
        'errors' => $errors,
        'rawText' => $rawText,
        'promptHash' => $prompts['promptHash'],
        'createdAt' => $startedAt,
        'newsLength' => $type === 'news' ? $newsLength : null,
        'provider' => $aiMeta['provider'],
        'modelUsed' => $aiMeta['modelUsed'],
        'httpStatus' => $aiMeta['httpStatus'],
        'requestId' => $aiMeta['requestId'],
    ];

    writeJsonAtomic(topic_v2_job_path($jobId), $jobPayload);

    content_v2_log([
        'event' => 'TOPIC_GEN',
        'jobId' => $jobId,
        'type' => $type,
        'ok' => $ok,
        'provider' => $aiMeta['provider'],
        'modelUsed' => $aiMeta['modelUsed'],
        'httpStatus' => $aiMeta['httpStatus'],
        'requestId' => $aiMeta['requestId'],
        'promptHash' => $prompts['promptHash'],
        'resultsCount' => count($results),
        'rawSnippet' => $rawSnippet,
        'errors' => $errors,
        'error' => $aiMeta['error'],
    ]);

    $response = [
        'ok' => $ok,
        'jobId' => $jobId,
        'results' => $results,
        'errors' => $errors,
        'aiMeta' => $aiMeta,
        'newsLength' => $type === 'news' ? $newsLength : null,
    ];
    $send($response);
} catch (Throwable $e) {
    content_v2_log([
        'event' => 'TOPIC_GEN_ERROR',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    http_response_code(500);
    $send(['ok' => false, 'error' => 'Server error. Please check logs.']);
}
