<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');

$respond = function (array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
};

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $respond(['ok' => false, 'error' => 'Method not allowed.'], 405);
        return;
    }

    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        $respond(['ok' => false, 'error' => 'Password reset required.'], 403);
        return;
    }

    require_csrf();

    $type = $_POST['type'] ?? '';
    $sourceType = $_POST['sourceType'] ?? $type;
    $topicId = trim((string)($_POST['topicId'] ?? ''));
    if (!in_array($type, ['blog', 'news'], true) || !in_array($sourceType, ['blog', 'news'], true) || $topicId === '') {
        $respond(['ok' => false, 'error' => 'Invalid type or topic.']);
        return;
    }

    $topic = topic_v2_load_record($sourceType, $topicId);
    if (!$topic || ($topic['deletedAt'] ?? null) !== null) {
        $respond(['ok' => false, 'error' => 'Topic not found or deleted.']);
        return;
    }

    $configResult = ai_get_config();
    if (!$configResult['ok']) {
        $respond(['ok' => false, 'error' => 'AI is not configured. Superadmin: set provider, API key, and model in AI Studio.', 'details' => $configResult['errors']]);
        return;
    }
    $config = $configResult['config'] ?? ai_config_defaults();
    $provider = $config['provider'] ?? '';
    $purposeModels = ai_resolve_purpose_models($config, 'contentDrafts');

    $customTitle = trim((string)($_POST['customTitle'] ?? ''));
    $tone = trim((string)($_POST['tone'] ?? 'modern, confident, concise'));
    $newsLength = $type === 'news' ? ($_POST['newsLength'] ?? '') : null;

    $jobId = content_v2_generate_job_id();
    $contentId = content_v2_generate_content_id($type);
    $nonce = strtoupper(bin2hex(random_bytes(6)));
    $startedAt = now_kolkata()->format(DateTime::ATOM);

    $promptBundle = content_v2_build_generation_prompt($type, $topic, [
        'customTitle' => $customTitle,
        'tone' => $tone,
        'newsLength' => $newsLength,
    ], $nonce, $provider);

    $baseOptions = [
        'expectJson' => true,
        'temperature' => $type === 'news' ? 0.5 : 0.68,
        'maxTokens' => $type === 'news' ? 900 : 1400,
        'allowFallback' => false,
    ];

    $attempts = [];
    $retryCount = 0;
    $fallbackUsed = false;
    $fallbackModelUsed = null;

    $aiResult = ai_call_text(
        'contentDrafts',
        $promptBundle['systemPrompt'],
        $promptBundle['userPrompt'],
        $baseOptions
    );
    $attempts[] = $aiResult;

    $validation = content_v2_validate_draft_payload($aiResult['json'] ?? null);
    $errors = array_values(array_unique(array_merge($aiResult['errors'] ?? [], $validation['errors'] ?? [])));
    $ok = ($aiResult['ok'] ?? false) && ($validation['ok'] ?? false);

    $emptyContent = ($aiResult['diagnosticError'] ?? null) === 'empty_content' || trim((string)($aiResult['rawText'] ?? '')) === '';
    if (!$ok && $retryCount < 1) {
        $retryCount++;
        $retryPrompt = $promptBundle['userPrompt'] . "\nReturn ONLY valid JSON. No markdown. No backticks. Schema: {\"title\":\"string\",\"excerpt\":\"string\",\"bodyHtml\":\"HTML\"}. DO NOT RETURN EMPTY. MUST RETURN JSON.";
        $retryOptions = $baseOptions;
        if ($emptyContent) {
            $retryOptions['temperature'] = min(1.0, ($baseOptions['temperature'] ?? 0.68) + 0.1);
            $retryOptions['maxTokens'] = max($baseOptions['maxTokens'], ($type === 'news' ? 1100 : 1600));
        }
        $aiResult = ai_call_text(
            'contentDrafts',
            $promptBundle['systemPrompt'],
            $retryPrompt,
            $retryOptions
        );
        $attempts[] = $aiResult;
        $validation = content_v2_validate_draft_payload($aiResult['json'] ?? null);
        $errors = array_values(array_unique(array_merge($aiResult['errors'] ?? [], $validation['errors'] ?? [])));
        $ok = ($aiResult['ok'] ?? false) && ($validation['ok'] ?? false);
    }

    if (!$ok && !$fallbackUsed && !empty($purposeModels['fallbackModel'])) {
        $fallbackUsed = true;
        $fallbackModelUsed = $purposeModels['fallbackModel'];
        $fallbackPrompt = $promptBundle['userPrompt'] . "\nReturn ONLY valid JSON. No markdown. No backticks. Schema: {\"title\":\"string\",\"excerpt\":\"string\",\"bodyHtml\":\"HTML\"}. DO NOT RETURN EMPTY. MUST RETURN JSON.";
        $fallbackOptions = $baseOptions;
        $fallbackOptions['modelOverride'] = $fallbackModelUsed;
        $fallbackOptions['temperature'] = min(1.0, ($baseOptions['temperature'] ?? 0.68) + 0.05);
        $fallbackOptions['maxTokens'] = max($baseOptions['maxTokens'], ($type === 'news' ? 1100 : 1600));

        $aiResult = ai_call_text(
            'contentDrafts',
            $promptBundle['systemPrompt'],
            $fallbackPrompt,
            $fallbackOptions
        );
        $attempts[] = $aiResult;
        $validation = content_v2_validate_draft_payload($aiResult['json'] ?? null);
        $errors = array_values(array_unique(array_merge($aiResult['errors'] ?? [], $validation['errors'] ?? [])));
        $ok = ($aiResult['ok'] ?? false) && ($validation['ok'] ?? false);
    }

    $rawText = (string)($aiResult['rawText'] ?? '');
    $rawTextSnippet = function_exists('mb_substr') ? mb_substr($rawText, 0, 800, 'UTF-8') : substr($rawText, 0, 800);
    $responseId = $aiResult['responseId'] ?? ($aiResult['rawEnvelope']['responseId'] ?? null);
    $finishReasons = $aiResult['finishReasons'] ?? ($aiResult['rawEnvelope']['finishReasons'] ?? []);
    $blockReason = $aiResult['promptBlockReason'] ?? ($aiResult['rawEnvelope']['blockReason'] ?? null);
    $textLength = strlen($rawText);
    $errors = $aiResult['errors'] ?? [];

    $json = is_array($aiResult['json'] ?? null) ? $aiResult['json'] : null;
    $title = trim((string)($validation['title'] ?? ($json['title'] ?? '')));
    $bodyHtml = (string)($validation['bodyHtml'] ?? sanitize_body_html((string)($json['bodyHtml'] ?? '')));
    $excerpt = trim((string)($validation['excerpt'] ?? ($json['excerpt'] ?? '')));

    $errorText = '';
    if (!$aiResult['ok'] || $title === '' || $bodyHtml === '') {
        $errors = array_values(array_unique(array_merge($errors, $aiResult['errors'] ?? [], $validation['errors'] ?? [])));
        if ($title === '' || $bodyHtml === '') {
            $errors[] = 'AI response missing required title or bodyHtml.';
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
        $errorText = implode(' | ', array_slice($errors, 0, 3));
        if ($errorText === '') {
            $errorText = 'AI call failed.';
        }
    }

    $generationMeta = [
        'jobId' => $jobId,
        'provider' => $aiResult['provider'] ?? ($aiResult['rawEnvelope']['provider'] ?? ''),
        'modelUsed' => $aiResult['modelUsed'] ?? ($aiResult['rawEnvelope']['model'] ?? ''),
        'httpStatus' => $aiResult['httpStatus'] ?? ($aiResult['rawEnvelope']['httpStatus'] ?? null),
        'requestId' => $aiResult['requestId'] ?? ($aiResult['rawEnvelope']['requestId'] ?? null),
        'responseId' => $responseId,
        'finishReasons' => $finishReasons,
        'blockReason' => $blockReason,
        'textLength' => $textLength,
        'nonce' => $nonce,
        'promptHash' => $promptBundle['promptHash'],
        'outputHash' => null,
        'rawTextSnippet' => $rawTextSnippet,
        'createdAt' => $startedAt,
        'ok' => $errorText === '',
        'error' => $errorText !== '' ? $errorText : null,
        'retryCount' => $retryCount,
        'fallbackUsed' => $fallbackUsed,
        'fallbackModelUsed' => $fallbackModelUsed,
    ];

    $slug = '';
    $outputHash = null;
    $finishedAt = null;

    if ($aiResult['ok'] && $title !== '' && $bodyHtml !== '') {
        if ($excerpt === '') {
            $excerpt = content_excerpt($bodyHtml, 40);
        }

        $slug = content_v2_unique_slug($type, $title, null, null);
        $outputHash = content_output_hash($bodyHtml);
        $finishedAt = now_kolkata()->format(DateTime::ATOM);

        $generationMeta['outputHash'] = $outputHash;
        $generationMeta['ok'] = true;
        $generationMeta['error'] = null;
    }

    $jobPayload = [
        'jobId' => $jobId,
        'type' => $type,
        'topicId' => $topicId,
        'sourceType' => $sourceType,
        'promptUsed' => $promptBundle['userPrompt'],
        'nonce' => $nonce,
        'aiMeta' => array_merge($generationMeta, [
            'startedAt' => $startedAt,
            'finishedAt' => $finishedAt,
        ]),
        'ok' => $generationMeta['ok'],
        'error' => $generationMeta['error'],
        'errors' => $errorText !== '' ? $errors : [],
        'rawText' => $rawText,
        'parsedHtml' => $bodyHtml,
        'promptHash' => $promptBundle['promptHash'],
        'outputHash' => $outputHash,
        'createdAt' => $startedAt,
        'provider' => $generationMeta['provider'],
        'modelUsed' => $generationMeta['modelUsed'],
        'httpStatus' => $generationMeta['httpStatus'],
        'requestId' => $generationMeta['requestId'],
        'responseId' => $generationMeta['responseId'],
        'finishReasons' => $generationMeta['finishReasons'],
        'blockReason' => $generationMeta['blockReason'],
        'textLength' => $generationMeta['textLength'],
        'retryCount' => $retryCount,
        'fallbackUsed' => $fallbackUsed,
        'fallbackModelUsed' => $fallbackModelUsed,
    ];

    content_v2_save_raw_response($jobId, [
        'purpose' => 'contentDrafts',
        'provider' => $generationMeta['provider'],
        'modelUsed' => $generationMeta['modelUsed'],
        'httpStatus' => $generationMeta['httpStatus'],
        'requestId' => $generationMeta['requestId'],
        'responseId' => $generationMeta['responseId'],
        'finishReasons' => $generationMeta['finishReasons'],
        'blockReason' => $generationMeta['blockReason'],
        'rawSnippet' => $aiResult['rawBodySnippet'] ?? $rawTextSnippet,
        'textLen' => $generationMeta['textLength'],
        'errors' => $errorText !== '' ? $errors : [],
        'retryCount' => $retryCount,
        'fallbackUsed' => $fallbackUsed,
        'fallbackModelUsed' => $fallbackModelUsed,
    ]);

    if (!$generationMeta['ok']) {
        writeJsonAtomic(content_v2_job_path($jobId), $jobPayload);
        content_v2_log([
            'event' => 'CONTENT_GEN',
            'jobId' => $jobId,
            'contentId' => $contentId,
            'type' => $type,
            'topicId' => $topicId,
            'sourceType' => $sourceType,
            'ok' => false,
            'provider' => $generationMeta['provider'],
            'modelUsed' => $generationMeta['modelUsed'],
            'httpStatus' => $generationMeta['httpStatus'],
            'requestId' => $generationMeta['requestId'],
            'responseId' => $generationMeta['responseId'],
            'promptHash' => $promptBundle['promptHash'],
            'outputHash' => null,
            'finishReasons' => $generationMeta['finishReasons'],
            'blockReason' => $generationMeta['blockReason'],
            'textLength' => $generationMeta['textLength'],
            'errors' => $errors,
            'error' => $generationMeta['error'],
            'retryCount' => $retryCount,
            'fallbackUsed' => $fallbackUsed,
            'fallbackModelUsed' => $fallbackModelUsed,
        ]);
        $respond([
            'ok' => false,
            'error' => $generationMeta['error'] ?: 'AI returned empty content.',
            'aiMeta' => $generationMeta,
            'jobId' => $jobId,
            'diagnostic' => [
                'message' => 'AI returned empty/invalid JSON. Retried: ' . ($retryCount > 0 ? 'yes' : 'no') . '. Fallback used: ' . ($fallbackUsed ? 'yes' : 'no') . '.',
                'requestId' => $generationMeta['requestId'],
                'responseId' => $generationMeta['responseId'],
            ],
        ]);
        return;
    }

    $generationMeta['finishedAt'] = $finishedAt;

    $draft = [
        'contentId' => $contentId,
        'type' => $type,
        'topicId' => $topicId,
        'topicType' => $sourceType,
        'title' => $title,
        'slug' => $slug,
        'status' => 'draft',
        'bodyHtml' => $bodyHtml,
        'excerpt' => $excerpt,
        'tags' => $topic['keywords'] ?? [],
        'newsLength' => $type === 'news' ? ($promptBundle['newsLength'] ?? null) : null,
        'generation' => $generationMeta,
        'createdAt' => $startedAt,
        'updatedAt' => $finishedAt,
        'deletedAt' => null,
    ];

    $jobPayload['aiMeta'] = array_merge($generationMeta, [
        'startedAt' => $startedAt,
        'finishedAt' => $finishedAt,
    ]);
    $jobPayload['ok'] = true;
    $jobPayload['error'] = null;
    $jobPayload['errors'] = [];
    $jobPayload['outputHash'] = $outputHash;
    $jobPayload['parsedHtml'] = $bodyHtml;
    $jobPayload['modelUsed'] = $generationMeta['modelUsed'];
    $jobPayload['provider'] = $generationMeta['provider'];
    $jobPayload['httpStatus'] = $generationMeta['httpStatus'];
    $jobPayload['requestId'] = $generationMeta['requestId'];

    writeJsonAtomic(content_v2_job_path($jobId), $jobPayload);
    content_v2_save_draft($draft, false);
    content_v2_mark_topic_used($sourceType, $topicId);

    content_v2_log([
        'event' => 'CONTENT_GEN',
        'jobId' => $jobId,
        'contentId' => $contentId,
        'type' => $type,
        'topicId' => $topicId,
        'sourceType' => $sourceType,
        'ok' => true,
        'provider' => $generationMeta['provider'],
        'modelUsed' => $generationMeta['modelUsed'],
        'httpStatus' => $generationMeta['httpStatus'],
        'requestId' => $generationMeta['requestId'],
        'responseId' => $generationMeta['responseId'],
        'promptHash' => $promptBundle['promptHash'],
        'outputHash' => $outputHash,
        'finishReasons' => $generationMeta['finishReasons'],
        'blockReason' => $generationMeta['blockReason'],
        'textLength' => $generationMeta['textLength'],
        'retryCount' => $retryCount,
        'fallbackUsed' => $fallbackUsed,
        'fallbackModelUsed' => $fallbackModelUsed,
    ]);

    $respond([
        'ok' => true,
        'jobId' => $jobId,
        'contentId' => $contentId,
        'aiMeta' => $generationMeta,
        'viewUrl' => '/superadmin/content_draft_view.php?type=' . urlencode($type) . '&contentId=' . urlencode($contentId),
    ]);
} catch (Throwable $e) {
    content_v2_log([
        'event' => 'CONTENT_GEN_ERROR',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error. Please check logs.']);
}
