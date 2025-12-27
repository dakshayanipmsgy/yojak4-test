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

    $config = load_ai_config(true);
    if (($config['provider'] ?? '') === '' || empty($config['apiKey']) || (($config['textModel'] ?? '') === '')) {
        $send(['ok' => false, 'error' => 'AI Studio configuration missing. Add provider, API key, and text model.']);
        return;
    }

    $jobId = topic_v2_generate_job_id();
    $nonce = strtoupper(bin2hex(random_bytes(6)));
    $startedAt = now_kolkata()->format(DateTime::ATOM);

    $prompts = topic_v2_build_prompts($type, $prompt, $newsLength, $count, $nonce);

    $aiResult = ai_call([
        'systemPrompt' => $prompts['systemPrompt'],
        'userPrompt' => $prompts['userPrompt'],
        'expectJson' => true,
        'purpose' => 'content_topic_v2',
        'temperature' => $type === 'news' ? 0.55 : 0.7,
        'maxTokens' => 600,
    ]);

    $results = topic_v2_parse_results($aiResult['json'] ?? null, $count);
    $requiredMin = max(4, min($count, 7));
    $ok = ($aiResult['ok'] ?? false) && count($results) >= 4;

    $errors = $aiResult['errors'] ?? [];
    if (count($results) < $requiredMin) {
        $errors[] = 'AI returned fewer topics than requested (' . $requiredMin . ').';
        $ok = false;
    }
    if (count($results) === 0) {
        $errors[] = 'No topics were generated.';
    }

    $rawText = (string)($aiResult['rawText'] ?? '');
    $rawSnippet = function_exists('mb_substr') ? mb_substr($rawText, 0, 500, 'UTF-8') : substr($rawText, 0, 500);
    $finishedAt = now_kolkata()->format(DateTime::ATOM);

    $aiMeta = [
        'provider' => $aiResult['provider'] ?? ($aiResult['rawEnvelope']['provider'] ?? ''),
        'model' => $aiResult['modelUsed'] ?? ($aiResult['rawEnvelope']['model'] ?? ''),
        'httpStatus' => $aiResult['httpStatus'] ?? ($aiResult['rawEnvelope']['httpStatus'] ?? null),
        'requestId' => $aiResult['requestId'] ?? ($aiResult['rawEnvelope']['requestId'] ?? null),
        'promptHash' => $prompts['promptHash'],
        'nonce' => $nonce,
        'generatedAt' => $startedAt,
        'rawTextSnippet' => $rawSnippet,
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
        'errors' => $errors,
        'rawText' => $rawText,
        'promptHash' => $prompts['promptHash'],
        'createdAt' => $startedAt,
        'newsLength' => $type === 'news' ? $newsLength : null,
    ];

    writeJsonAtomic(topic_v2_job_path($jobId), $jobPayload);

    content_v2_log([
        'event' => 'TOPIC_GEN',
        'jobId' => $jobId,
        'type' => $type,
        'ok' => $ok,
        'provider' => $aiMeta['provider'],
        'model' => $aiMeta['model'],
        'httpStatus' => $aiMeta['httpStatus'],
        'requestId' => $aiMeta['requestId'],
        'promptHash' => $prompts['promptHash'],
        'resultsCount' => count($results),
        'rawSnippet' => $rawSnippet,
        'errors' => $errors,
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
