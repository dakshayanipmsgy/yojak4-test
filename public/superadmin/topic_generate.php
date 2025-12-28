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
    if ($count < 4) {
        $count = 4;
    } elseif ($count > 5) {
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
    $config = $configResult['config'] ?? ai_config_defaults();
    $provider = $config['provider'] ?? '';
    $purposeModels = ai_resolve_purpose_models($config, 'contentTopics');

    $jobId = topic_v2_generate_job_id();
    $nonce = strtoupper(bin2hex(random_bytes(6)));
    $startedAt = now_kolkata()->format(DateTime::ATOM);

    $prompts = topic_v2_build_prompts($type, $prompt, $newsLength, $count, $nonce, $provider);

    $baseOptions = [
        'expectJson' => true,
        'temperature' => $type === 'news' ? 0.55 : 0.7,
        'maxTokens' => 600,
        'allowFallback' => false,
    ];

    $attempts = [];
    $retryCount = 0;
    $fallbackUsed = false;
    $fallbackModelUsed = null;
    $requiredMin = 4;
    $requiredMax = 5;

    $aiResult = ai_call_text(
        'contentTopics',
        $prompts['systemPrompt'],
        $prompts['userPrompt'],
        $baseOptions
    );
    $attempts[] = $aiResult;

    $validation = topic_v2_validate_result($aiResult['json'] ?? null, $requiredMin, $requiredMax);
    $results = $validation['topics'] ?? [];
    $errors = array_values(array_unique(array_merge($aiResult['errors'] ?? [], $validation['errors'] ?? [])));
    $ok = ($aiResult['ok'] ?? false) && ($validation['ok'] ?? false);

    $shouldRetry = !$ok && $retryCount < 1;
    $emptyContent = ($aiResult['diagnosticError'] ?? null) === 'empty_content' || trim((string)($aiResult['rawText'] ?? '')) === '';
    if ($shouldRetry) {
        $retryCount++;
        $retryPrompt = $prompts['userPrompt'] . "\nReturn ONLY valid JSON. No markdown. No backticks. Schema: {\"topics\":[{\"title\":\"string\",\"angle\":\"string\",\"keywords\":[\"a\",\"b\"]}]}. Provide 4-5 items. DO NOT RETURN EMPTY. MUST RETURN JSON.";
        $retryOptions = $baseOptions;
        if ($emptyContent) {
            $retryOptions['temperature'] = min(1.0, ($baseOptions['temperature'] ?? 0.7) + 0.1);
            $retryOptions['maxTokens'] = max($baseOptions['maxTokens'], 900);
        }
        $aiResult = ai_call_text(
            'contentTopics',
            $prompts['systemPrompt'],
            $retryPrompt,
            $retryOptions
        );
        $attempts[] = $aiResult;
        $validation = topic_v2_validate_result($aiResult['json'] ?? null, $requiredMin, $requiredMax);
        $results = $validation['topics'] ?? [];
        $errors = array_values(array_unique(array_merge($aiResult['errors'] ?? [], $validation['errors'] ?? [])));
        $ok = ($aiResult['ok'] ?? false) && ($validation['ok'] ?? false);
    }

    if (!$ok && !$fallbackUsed && !empty($purposeModels['fallbackModel'])) {
        $fallbackUsed = true;
        $fallbackModelUsed = $purposeModels['fallbackModel'];
        $fallbackPrompt = $prompts['userPrompt'] . "\nReturn ONLY valid JSON. No markdown. No backticks. Schema: {\"topics\":[{\"title\":\"string\",\"angle\":\"string\",\"keywords\":[\"a\",\"b\"]}]}. Provide 4-5 items. DO NOT RETURN EMPTY. MUST RETURN JSON.";
        $fallbackOptions = $baseOptions;
        $fallbackOptions['modelOverride'] = $fallbackModelUsed;
        $fallbackOptions['temperature'] = min(1.0, ($baseOptions['temperature'] ?? 0.7) + 0.05);
        $fallbackOptions['maxTokens'] = max($baseOptions['maxTokens'], 900);

        $aiResult = ai_call_text(
            'contentTopics',
            $prompts['systemPrompt'],
            $fallbackPrompt,
            $fallbackOptions
        );
        $attempts[] = $aiResult;
        $validation = topic_v2_validate_result($aiResult['json'] ?? null, $requiredMin, $requiredMax);
        $results = $validation['topics'] ?? [];
        $errors = array_values(array_unique(array_merge($aiResult['errors'] ?? [], $validation['errors'] ?? [])));
        $ok = ($aiResult['ok'] ?? false) && ($validation['ok'] ?? false);
    }

    $rawText = (string)($aiResult['rawText'] ?? '');
    $rawSnippet = function_exists('mb_substr') ? mb_substr($rawText, 0, 500, 'UTF-8') : substr($rawText, 0, 500);
    $responseId = $aiResult['responseId'] ?? ($aiResult['rawEnvelope']['responseId'] ?? null);
    $finishReasons = $aiResult['finishReasons'] ?? ($aiResult['rawEnvelope']['finishReasons'] ?? []);
    $blockReason = $aiResult['promptBlockReason'] ?? ($aiResult['rawEnvelope']['blockReason'] ?? null);
    $textLength = strlen($rawText);

    if (!$ok) {
        if (count($results) < $requiredMin) {
            $errors[] = 'AI returned fewer topics than requested (' . $requiredMin . ').';
        }
        if (count($results) === 0) {
            $errors[] = 'No topics were generated.';
        }
        if ($blockReason) {
            $errors[] = 'Block reason: ' . $blockReason;
        }
        if (!empty($finishReasons)) {
            $errors[] = 'Finish reasons: ' . implode(', ', $finishReasons);
        }
        if (($aiResult['diagnosticError'] ?? null) === 'empty_content') {
            $errors[] = 'empty_content';
        }
        $errors[] = 'AI returned empty/invalid JSON. Retried: ' . ($retryCount > 0 ? 'yes' : 'no') . '. Fallback used: ' . ($fallbackUsed ? 'yes' : 'no') . '.';
    }

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
        'responseId' => $responseId,
        'finishReasons' => $finishReasons,
        'blockReason' => $blockReason,
        'textLength' => $textLength,
        'promptHash' => $prompts['promptHash'],
        'nonce' => $nonce,
        'generatedAt' => $startedAt,
        'rawTextSnippet' => $rawSnippet,
        'ok' => $ok,
        'error' => $errorText !== '' ? $errorText : null,
        'retryCount' => $retryCount,
        'fallbackUsed' => $fallbackUsed,
        'fallbackModelUsed' => $fallbackModelUsed,
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
        'responseId' => $aiMeta['responseId'],
        'finishReasons' => $aiMeta['finishReasons'],
        'blockReason' => $aiMeta['blockReason'],
        'textLength' => $aiMeta['textLength'],
        'retryCount' => $retryCount,
        'fallbackUsed' => $fallbackUsed,
        'fallbackModelUsed' => $fallbackModelUsed,
    ];

    content_v2_save_raw_response($jobId, [
        'purpose' => 'contentTopics',
        'provider' => $aiMeta['provider'],
        'modelUsed' => $aiMeta['modelUsed'],
        'httpStatus' => $aiMeta['httpStatus'],
        'requestId' => $aiMeta['requestId'],
        'responseId' => $aiMeta['responseId'],
        'finishReasons' => $aiMeta['finishReasons'],
        'blockReason' => $aiMeta['blockReason'],
        'rawSnippet' => $aiResult['rawBodySnippet'] ?? $rawSnippet,
        'textLen' => $aiMeta['textLength'],
        'errors' => $errors,
        'retryCount' => $retryCount,
        'fallbackUsed' => $fallbackUsed,
        'fallbackModelUsed' => $fallbackModelUsed,
    ]);

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
        'responseId' => $aiMeta['responseId'],
        'promptHash' => $prompts['promptHash'],
        'resultsCount' => count($results),
        'rawSnippet' => $rawSnippet,
        'finishReasons' => $aiMeta['finishReasons'],
        'blockReason' => $aiMeta['blockReason'],
        'textLength' => $aiMeta['textLength'],
        'errors' => $errors,
        'error' => $aiMeta['error'],
        'retryCount' => $retryCount,
        'fallbackUsed' => $fallbackUsed,
        'fallbackModelUsed' => $fallbackModelUsed,
    ]);

    $response = [
        'ok' => $ok,
        'jobId' => $jobId,
        'results' => $results,
        'errors' => $errors,
        'aiMeta' => $aiMeta,
        'newsLength' => $type === 'news' ? $newsLength : null,
        'diagnostic' => !$ok ? [
            'message' => 'AI returned empty/invalid JSON. Retried: ' . ($retryCount > 0 ? 'yes' : 'no') . '. Fallback used: ' . ($fallbackUsed ? 'yes' : 'no') . '.',
            'requestId' => $aiMeta['requestId'],
            'responseId' => $aiMeta['responseId'],
        ] : null,
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
