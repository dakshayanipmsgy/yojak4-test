<?php
declare(strict_types=1);

const AI_CONFIG_PATH = DATA_PATH . '/ai/ai_config.json';
const AI_SECRET_PATH = DATA_PATH . '/ai/secret.key';
const AI_LOG_FILE = DATA_PATH . '/logs/ai.log';
const AI_PROVIDER_RAW_LOG = DATA_PATH . '/logs/ai_provider_raw.log';

function ensure_ai_storage(): void
{
    $dir = dirname(AI_CONFIG_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!file_exists(AI_SECRET_PATH)) {
        $secret = base64_encode(random_bytes(32));
        file_put_contents(AI_SECRET_PATH, $secret, LOCK_EX);
    }
}

function ai_secret(): string
{
    ensure_ai_storage();
    $handle = fopen(AI_SECRET_PATH, 'c+');
    if (!$handle) {
        throw new RuntimeException('Unable to open AI secret file.');
    }
    flock($handle, LOCK_SH);
    $secret = trim((string)stream_get_contents($handle));
    flock($handle, LOCK_UN);
    fclose($handle);
    if ($secret === '') {
        $secret = base64_encode(random_bytes(32));
        file_put_contents(AI_SECRET_PATH, $secret, LOCK_EX);
    }
    return $secret;
}

function obfuscate_api_key(string $plain): string
{
    $secret = ai_secret();
    return base64_encode($secret . '|' . $plain);
}

function reveal_api_key(?string $encoded): ?string
{
    if (!$encoded) {
        return null;
    }
    $decoded = base64_decode($encoded, true);
    if ($decoded === false) {
        return null;
    }
    $parts = explode('|', $decoded, 2);
    if (count($parts) !== 2) {
        return null;
    }
    if (!hash_equals($parts[0], ai_secret())) {
        return null;
    }
    return $parts[1];
}

function mask_api_key_display(?string $plain): string
{
    if (!$plain) {
        return 'Not set';
    }
    $visible = min(4, strlen($plain));
    $masked = str_repeat('•', max(4, strlen($plain) - $visible));
    return $masked . substr($plain, -$visible);
}

function ai_config_defaults(): array
{
    return [
        'provider' => '',
        'apiKeyEnc' => '',
        'apiKeyStored' => false,
        'textModel' => '',
        'imageModel' => '',
        'purposeModels' => [
            'offlineTenderExtract' => [
                'primaryModel' => null,
                'fallbackModel' => 'gemini-3-flash-preview',
                'useStreamingFallback' => true,
                'retryOnceOnEmpty' => true,
                'useStructuredJson' => true,
            ],
        ],
        'updatedAt' => null,
    ];
}

function ai_normalize_purpose_models(array $raw): array
{
    $defaults = ai_config_defaults()['purposeModels'];
    $normalized = [];
    foreach ($defaults as $key => $default) {
        $incoming = is_array($raw[$key] ?? null) ? $raw[$key] : [];
        $normalized[$key] = array_merge($default, $incoming);
        $normalized[$key]['primaryModel'] = trim((string)($normalized[$key]['primaryModel'] ?? '')) ?: null;
        $normalized[$key]['fallbackModel'] = trim((string)($normalized[$key]['fallbackModel'] ?? '')) ?: null;
        if (array_key_exists('useStreamingFallback', $default)) {
            $normalized[$key]['useStreamingFallback'] = (bool)($normalized[$key]['useStreamingFallback'] ?? false);
        }
        if (array_key_exists('retryOnceOnEmpty', $default)) {
            $normalized[$key]['retryOnceOnEmpty'] = (bool)($normalized[$key]['retryOnceOnEmpty'] ?? false);
        }
        if (array_key_exists('useStructuredJson', $default)) {
            $normalized[$key]['useStructuredJson'] = (bool)($normalized[$key]['useStructuredJson'] ?? false);
        }
    }

    foreach ($raw as $key => $value) {
        if (isset($normalized[$key])) {
            continue;
        }
        if (is_array($value)) {
            $normalized[$key] = [
                'primaryModel' => trim((string)($value['primaryModel'] ?? '')) ?: null,
                'fallbackModel' => trim((string)($value['fallbackModel'] ?? '')) ?: null,
            ];
        }
    }

    return $normalized;
}

function ai_model_validation_error(string $provider, string $model, string $label): ?string
{
    if ($model === '' || ($provider !== 'openai' && $provider !== 'gemini')) {
        return null;
    }
    $provider = strtolower($provider);
    if ($provider === 'gemini') {
        if (!preg_match('/^(gemini|learnlm)-[a-z0-9][a-z0-9.\-]*$/i', $model)) {
            return $label . ' must be a Gemini model name (gemini-*).';
        }
    } elseif ($provider === 'openai') {
        if (!preg_match('/^(gpt-|o1-|o3-)/i', $model)) {
            return $label . ' must be an OpenAI chat model (gpt-*, o1-*, o3-*).';
        }
    }
    return null;
}

function ai_validate_model_set(string $provider, array $config): array
{
    $errors = [];
    $textModel = trim((string)($config['textModel'] ?? ''));
    $imageModel = trim((string)($config['imageModel'] ?? ''));
    $purposeModels = is_array($config['purposeModels'] ?? null) ? $config['purposeModels'] : [];

    foreach ([['label' => 'Text model', 'value' => $textModel], ['label' => 'Image model', 'value' => $imageModel]] as $entry) {
        $error = ai_model_validation_error($provider, $entry['value'], $entry['label']);
        if ($error !== null) {
            $errors[] = $error;
        }
    }

    foreach ($purposeModels as $purpose => $models) {
        if (!is_array($models)) {
            continue;
        }
        $primary = trim((string)($models['primaryModel'] ?? ''));
        $fallback = trim((string)($models['fallbackModel'] ?? ''));
        foreach ([['label' => 'Purpose ' . $purpose . ': primary model', 'value' => $primary], ['label' => 'Purpose ' . $purpose . ': fallback model', 'value' => $fallback]] as $entry) {
            $error = ai_model_validation_error($provider, $entry['value'], $entry['label']);
            if ($error !== null) {
                $errors[] = $error;
            }
        }
    }

    return array_values(array_unique($errors));
}

function ai_get_config(bool $includeKey = false): array
{
    ensure_ai_storage();

    $config = ai_config_defaults();
    $raw = readJson(AI_CONFIG_PATH);
    if ($raw) {
        $config = array_merge($config, $raw);
    }

    if (!isset($config['apiKeyEnc']) && isset($raw['apiKey'])) {
        $config['apiKeyEnc'] = $raw['apiKey'];
    }

    $config['apiKeyStored'] = !empty($config['apiKeyEnc']);
    $config['purposeModels'] = ai_normalize_purpose_models(is_array($config['purposeModels'] ?? null) ? $config['purposeModels'] : []);
    $config['updatedAt'] = $config['updatedAt'] ?? now_kolkata()->format(DateTime::ATOM);

    $apiKey = null;
    if ($includeKey && $config['apiKeyStored']) {
        $apiKey = reveal_api_key($config['apiKeyEnc']);
        if ($apiKey === null || $apiKey === '') {
            $config['apiKeyStored'] = false;
        }
    }

    $config['apiKey'] = $includeKey ? $apiKey : null;
    $config['hasApiKey'] = $config['apiKeyStored'];

    $errors = [];
    if (($config['provider'] ?? '') === '') {
        $errors[] = 'Provider is required.';
    }
    if (($config['textModel'] ?? '') === '') {
        $errors[] = 'Text model is required.';
    }
    if (!$config['apiKeyStored']) {
        $errors[] = 'API key is required.';
    }
    $errors = array_merge($errors, ai_validate_model_set($config['provider'] ?? '', $config));

    return [
        'ok' => empty($errors),
        'config' => $config,
        'errors' => $errors,
    ];
}

function load_ai_config(bool $includeKey = false): array
{
    $result = ai_get_config($includeKey);
    return $result['config'] ?? ai_config_defaults();
}

function ai_save_config(array $input): array
{
    ensure_ai_storage();

    $provider = trim((string)($input['provider'] ?? ''));
    $textModel = trim((string)($input['textModel'] ?? ''));
    $imageModel = trim((string)($input['imageModel'] ?? ''));
    $apiKeyInput = trim((string)($input['apiKey'] ?? ''));
    $purposeModels = is_array($input['purposeModels'] ?? null) ? $input['purposeModels'] : [];

    $existing = ai_get_config(true);
    $existingConfig = $existing['config'] ?? ai_config_defaults();
    $existingKey = $existingConfig['apiKey'] ?? '';
    unset($purposeModels['contentTopics'], $purposeModels['contentDrafts']);
    unset($existingConfig['purposeModels']['contentTopics'], $existingConfig['purposeModels']['contentDrafts']);

    $finalKey = $apiKeyInput !== '' ? $apiKeyInput : $existingKey;

    $errors = [];
    if ($provider === '' || !in_array($provider, ['openai', 'gemini'], true)) {
        $errors[] = 'Provider is required.';
    }
    if ($textModel === '') {
        $errors[] = 'Text model is required.';
    }
    if ($finalKey === '') {
        $errors[] = 'API key is required.';
    }

    $payload = ai_config_defaults();
    $payload['provider'] = $provider;
    $payload['apiKeyEnc'] = $finalKey !== '' ? obfuscate_api_key($finalKey) : '';
    $payload['apiKeyStored'] = $finalKey !== '';
    $payload['textModel'] = $textModel;
    $payload['imageModel'] = $imageModel;
    $payload['purposeModels'] = ai_normalize_purpose_models($purposeModels + ($existingConfig['purposeModels'] ?? []));
    $payload['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    $errors = array_merge($errors, ai_validate_model_set($provider, $payload));

    if (empty($errors)) {
        writeJsonAtomic(AI_CONFIG_PATH, $payload);
    }

    return [
        'ok' => empty($errors),
        'errors' => $errors,
        'config' => $payload,
    ];
}

function save_ai_config(string $provider, string $apiKey, string $textModel, string $imageModel, array $purposeModels = []): void
{
    ai_save_config([
        'provider' => $provider,
        'apiKey' => $apiKey,
        'textModel' => $textModel,
        'imageModel' => $imageModel,
        'purposeModels' => $purposeModels,
    ]);
}

function ai_purpose_key(string $purpose): string
{
    switch ($purpose) {
        case 'offline_tender_extract':
        case 'offlineTenderExtract':
            return 'offlineTenderExtract';
        default:
            return $purpose;
    }
}

function ai_resolve_purpose_models(array $config, string $purpose): array
{
    $defaults = ai_config_defaults();
    $purposeModels = ai_normalize_purpose_models(is_array($config['purposeModels'] ?? null) ? $config['purposeModels'] : []);
    $resolved = array_merge($defaults['purposeModels'], $purposeModels);
    $purposeKey = ai_purpose_key($purpose);
    $selected = $resolved[$purposeKey] ?? [
        'primaryModel' => null,
        'fallbackModel' => null,
        'useStreamingFallback' => false,
        'retryOnceOnEmpty' => false,
        'useStructuredJson' => false,
    ];
    $primary = ($selected['primaryModel'] ?? null) ?: ($config['textModel'] ?? '');
    $fallback = $selected['fallbackModel'] ?? null;
    return [
        'primaryModel' => $primary,
        'fallbackModel' => $fallback ?: null,
        'useStreamingFallback' => (bool)($selected['useStreamingFallback'] ?? false),
        'retryOnceOnEmpty' => (bool)($selected['retryOnceOnEmpty'] ?? false),
        'useStructuredJson' => (bool)($selected['useStructuredJson'] ?? false),
    ];
}

function strip_code_fences(string $text): string
{
    $text = preg_replace('/^```[a-zA-Z0-9]*\s*/m', '', $text ?? '');
    return preg_replace('/```$/m', '', $text);
}

function normalize_quotes(string $text): string
{
    $replacements = [
        '“' => '"',
        '”' => '"',
        '‘' => "'",
        '’' => "'",
        '„' => '"',
    ];
    return strtr($text, $replacements);
}

function strip_bom_and_labels(string $text): string
{
    $text = preg_replace('/^\xEF\xBB\xBF/u', '', $text);
    return preg_replace('/^\s*json\s*[:=]?\s*/i', '', $text);
}

function extract_first_json_block(string $text): ?string
{
    $text = trim($text);
    $length = strlen($text);
    $stack = [];
    $inString = false;
    $escape = false;
    $start = null;

    for ($i = 0; $i < $length; $i++) {
        $char = $text[$i];
        if ($inString) {
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($char === '\\') {
                $escape = true;
                continue;
            }
            if ($char === '"') {
                $inString = false;
            }
            continue;
        }
        if ($char === '"') {
            $inString = true;
            continue;
        }
        if ($char === '{' || $char === '[') {
            if ($start === null) {
                $start = $i;
            }
            $stack[] = $char;
        } elseif ($char === '}' || $char === ']') {
            if ($stack) {
                $open = array_pop($stack);
                if (($open === '{' && $char === '}') || ($open === '[' && $char === ']')) {
                    if (empty($stack) && $start !== null) {
                        return substr($text, (int)$start, $i - (int)$start + 1);
                    }
                }
            }
        }
    }

    return null;
}

function repair_json_text(string $text): string
{
    $text = strip_bom_and_labels(normalize_quotes(strip_code_fences($text)));
    $text = preg_replace('/,(\s*[}\]])/', '$1', $text);
    return $text;
}

function parse_ai_json(string $text): array
{
    $result = [
        'json' => null,
        'errors' => [],
        'rawCandidate' => '',
        'parseStage' => 'fallback_manual',
    ];

    $strict = strip_bom_and_labels(trim($text));
    $result['rawCandidate'] = $strict;
    $decoded = json_decode($strict, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $result['json'] = $decoded;
        $result['parseStage'] = 'strict_json';
        return $result;
    }
    $result['errors'][] = 'Strict parse failed: ' . json_last_error_msg();

    $block = extract_first_json_block($strict);
    if ($block !== null) {
        $result['rawCandidate'] = $block;
        $decoded = json_decode($block, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $result['json'] = $decoded;
            $result['parseStage'] = 'extract_block';
            return $result;
        }
        $result['errors'][] = 'Block parse failed: ' . json_last_error_msg();
    } else {
        $result['errors'][] = 'No JSON object or array detected.';
    }

    $repaired = repair_json_text($strict);
    $candidate = extract_first_json_block($repaired) ?? $repaired;
    $result['rawCandidate'] = $candidate;
    $decoded = json_decode($candidate, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $result['json'] = $decoded;
        $result['parseStage'] = 'repair';
        return $result;
    }

    $result['errors'][] = 'Repair attempt failed: ' . json_last_error_msg();
    $result['parseStage'] = 'fallback_manual';

    return $result;
}

function ai_schema_validation(string $purpose, ?array $json): array
{
    $result = [
        'enabled' => false,
        'passed' => true,
        'errors' => [],
    ];

    if ($json === null) {
        return $result;
    }

    if ($purpose === 'offline_tender_extract') {
        $validation = offline_tender_validate_extraction_schema($json);
        $result['enabled'] = true;
        $result['passed'] = (bool)($validation['ok'] ?? false);
        $result['errors'] = $validation['errors'] ?? [];
    }

    return $result;
}

function ai_apply_provider_result(array &$result, array $providerResult, array $config, bool $expectJson, float $temperature, int $maxTokens, string $provider, string $purpose): void
{
    $result['httpStatus'] = $providerResult['httpStatus'] ?? $result['httpStatus'];
    $result['rawText'] = $providerResult['text'] ?? '';
    $result['text'] = $result['rawText'];
    $result['requestId'] = $providerResult['requestId'] ?? $result['requestId'];
    $result['responseId'] = $providerResult['responseId'] ?? $result['responseId'] ?? $result['requestId'];
    if (!$result['requestId'] && $result['responseId']) {
        $result['requestId'] = $result['responseId'];
    }
    $result['finishReason'] = $providerResult['finishReason'] ?? $result['finishReason'];
    $result['finishReasons'] = array_values(array_filter(array_unique(array_merge(
        $result['finishReasons'],
        is_array($providerResult['finishReasons'] ?? null) ? $providerResult['finishReasons'] : [],
        $result['finishReason'] ? [$result['finishReason']] : []
    ))));
    $result['promptBlockReason'] = $providerResult['blockReason'] ?? $result['promptBlockReason'];
    $result['safetyRatingsSummary'] = $providerResult['safetySummary'] ?? ($result['safetyRatingsSummary'] ?? '');
    $result['providerError'] = $providerResult['providerError'] ?? null;
    $result['providerOk'] = (bool)($providerResult['ok'] ?? false);
    $result['rawBody'] = $providerResult['rawBody'] ?? $result['rawBody'];
    $result['latencyMs'] = $providerResult['latencyMs'] ?? $result['latencyMs'];
    $result['modelUsed'] = $providerResult['modelUsed'] ?? ($config['textModel'] ?? null);
    $result['temperatureUsed'] = $providerResult['temperatureUsed'] ?? $temperature;
    $result['rawEnvelope'] = [
        'provider' => $provider,
        'model' => $result['modelUsed'],
        'httpStatus' => $providerResult['httpStatus'] ?? null,
        'requestId' => $providerResult['requestId'] ?? null,
        'latencyMs' => $providerResult['latencyMs'] ?? $result['latencyMs'],
        'temperature' => $providerResult['temperatureUsed'] ?? $temperature,
        'maxTokens' => $providerResult['maxTokensUsed'] ?? $maxTokens,
        'attemptType' => $providerResult['attemptType'] ?? 'primary',
        'stream' => $providerResult['stream'] ?? false,
        'structured' => $providerResult['structured'] ?? false,
        'responseMimeType' => $providerResult['responseMimeType'] ?? null,
        'responseId' => $result['responseId'],
        'finishReasons' => $result['finishReasons'],
        'blockReason' => $result['promptBlockReason'],
        'diagnosticError' => $providerResult['diagnosticError'] ?? null,
    ];
    $result['diagnosticError'] = $providerResult['diagnosticError'] ?? $result['diagnosticError'];

    if ($result['providerError']) {
        $result['errors'][] = $result['providerError'];
    }

    $result['parsedOk'] = false;
    $result['json'] = null;
    $result['parseStage'] = 'fallback_manual';
    $result['schemaValidation'] = ['enabled' => false, 'passed' => true, 'errors' => []];

    if ($result['rawText'] !== '') {
        if ($expectJson) {
            $parsed = parse_ai_json($result['rawText']);
            if ($parsed['json'] !== null) {
                $result['json'] = $parsed['json'];
                $result['parsedOk'] = true;
                $result['parseStage'] = $parsed['parseStage'];
                $schemaResult = ai_schema_validation($purpose, $result['json']);
                $result['schemaValidation'] = $schemaResult;
                if ($schemaResult['enabled'] && !$schemaResult['passed']) {
                    $result['parsedOk'] = false;
                    $result['parseStage'] = 'schema_validation';
                    $result['errors'] = array_merge($result['errors'], $schemaResult['errors']);
                }
            } else {
                $result['errors'] = array_merge($result['errors'], $parsed['errors']);
                $result['parseStage'] = $parsed['parseStage'];
            }
        } else {
            $result['parsedOk'] = $result['providerOk'];
        }
    } elseif ($result['providerOk']) {
        $result['errors'][] = $providerResult['diagnosticError'] ?? 'empty_content';
        $result['diagnosticError'] = $providerResult['diagnosticError'] ?? 'empty_content';
    }

    $result['ok'] = $result['parsedOk'] || (!$expectJson && $result['providerOk']);
}

function ai_log(array $context): void
{
    logEvent(AI_LOG_FILE, $context);
}

function ai_provider_log_raw(array $context): void
{
    $context['at'] = $context['at'] ?? now_kolkata()->format(DateTime::ATOM);
    logEvent(AI_PROVIDER_RAW_LOG, $context);
}

function ai_detect_provider_error_payload(array $decoded): ?string
{
    if (isset($decoded['error'])) {
        $error = $decoded['error'];
        if (is_string($error)) {
            return $error;
        }
        if (is_array($error)) {
            $message = $error['message'] ?? $error['code'] ?? $error['reason'] ?? null;
            if (is_string($message)) {
                return $message;
            }
            return 'Provider error payload present.';
        }
    }

    if (isset($decoded['errors'])) {
        $errors = $decoded['errors'];
        if (is_string($errors)) {
            return $errors;
        }
        if (is_array($errors)) {
            if (isset($errors[0])) {
                $first = $errors[0];
                if (is_string($first)) {
                    return $first;
                }
                if (is_array($first)) {
                    $message = $first['message'] ?? $first['detail'] ?? $first['code'] ?? null;
                    if (is_string($message)) {
                        return $message;
                    }
                }
            }
            $message = $errors['message'] ?? $errors['detail'] ?? null;
            if (is_string($message)) {
                return $message;
            }
            return 'Provider errors payload present.';
        }
    }

    return null;
}

function ai_request_id_from_headers(array $headers): ?string
{
    $keys = [
        'x-request-id',
        'x-goog-request-id',
        'x-goog-trace-id',
        'x-b3-traceid',
        'traceparent',
        'x-cloud-trace-context',
    ];

    foreach ($keys as $key) {
        $value = $headers[$key] ?? ($headers[strtolower($key)] ?? null);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return null;
}

function ai_flatten_text_parts(array $parts): string
{
    $collected = [];
    foreach ($parts as $part) {
        if (is_string($part)) {
            $collected[] = trim($part);
            continue;
        }
        if (!is_array($part)) {
            continue;
        }
        if (isset($part['text']) && $part['text'] !== '') {
            $collected[] = trim((string)$part['text']);
            continue;
        }
        if (isset($part['json']) && $part['json'] !== '') {
            $jsonValue = $part['json'];
            $collected[] = is_string($jsonValue) ? trim($jsonValue) : json_encode($jsonValue, JSON_UNESCAPED_SLASHES);
            continue;
        }
        if (isset($part['functionCall']['args'])) {
            $args = $part['functionCall']['args'];
            $collected[] = is_string($args) ? trim($args) : json_encode($args, JSON_UNESCAPED_SLASHES);
        }
    }

    $collected = array_values(array_filter($collected, static fn(string $value): bool => $value !== ''));
    return trim(implode("\n", $collected));
}

function ai_extract_openai_text(array $decoded): string
{
    if (empty($decoded['choices']) || !is_array($decoded['choices'])) {
        return '';
    }

    foreach ($decoded['choices'] as $choice) {
        if (!is_array($choice) || empty($choice['message']) || !is_array($choice['message'])) {
            continue;
        }

        $message = $choice['message'];
        $content = $message['content'] ?? null;
        $text = '';

        if (is_string($content)) {
            $text = trim($content);
        } elseif (is_array($content)) {
            $text = ai_flatten_text_parts($content);
        }

        if ($text === '' && isset($message['tool_calls'][0]['function']['arguments'])) {
            $text = trim((string)$message['tool_calls'][0]['function']['arguments']);
        }

        if ($text !== '') {
            return $text;
        }
    }

    return '';
}

function ai_extract_gemini_text(array $decoded): string
{
    $candidates = $decoded['candidates'] ?? null;
    if (!is_array($candidates)) {
        return '';
    }

    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }

        $contents = [];
        if (isset($candidate['content']['parts'])) {
            $contents[] = $candidate['content'];
        } elseif (is_array($candidate['content'] ?? null)) {
            foreach ($candidate['content'] as $contentBlock) {
                if (is_array($contentBlock) && isset($contentBlock['parts'])) {
                    $contents[] = $contentBlock;
                }
            }
        }

        foreach ($contents as $content) {
            $parts = $content['parts'] ?? [];
            if (!is_array($parts)) {
                continue;
            }
            $text = ai_flatten_text_parts($parts);
            if ($text !== '') {
                return $text;
            }
        }
    }

    return '';
}

function ai_gemini_finish_reason(array $decoded): ?string
{
    $candidates = $decoded['candidates'] ?? [];
    if (is_array($candidates)) {
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            if (!empty($candidate['finishReason'])) {
                return (string)$candidate['finishReason'];
            }
        }
    }
    return null;
}

function ai_gemini_block_reason(array $decoded): ?string
{
    if (!empty($decoded['promptFeedback']['blockReason'])) {
        return (string)$decoded['promptFeedback']['blockReason'];
    }
    $candidates = $decoded['candidates'] ?? [];
    if (is_array($candidates)) {
        foreach ($candidates as $candidate) {
            if (!empty($candidate['safetyRatings'])) {
                foreach ((array)$candidate['safetyRatings'] as $rating) {
                    if (!empty($rating['blocked'])) {
                        return 'safety_blocked';
                    }
                }
            }
        }
    }
    return null;
}

function ai_gemini_safety_summary($ratings): string
{
    if (!is_array($ratings)) {
        return '';
    }
    $summary = [];
    foreach ($ratings as $rating) {
        if (!is_array($rating)) {
            continue;
        }
        $category = $rating['category'] ?? ($rating['harmCategory'] ?? ($rating['name'] ?? 'unknown'));
        $probability = $rating['probability'] ?? ($rating['probabilityScore'] ?? ($rating['severity'] ?? ($rating['blocked'] ?? '')));
        $entry = trim((string)$category);
        if ($probability !== '' && $probability !== null) {
            $entry .= ':' . (is_bool($probability) ? ($probability ? 'blocked' : 'ok') : (string)$probability);
        }
        if ($entry !== '') {
            $summary[] = $entry;
        }
    }
    return implode(', ', $summary);
}

function ai_provider_response_openai(array $config, string $systemPrompt, string $userPrompt, float $temperature = 0.2, int $maxTokens = 500, ?string $modelOverride = null): array
{
    $startedAt = microtime(true);
    $model = $modelOverride ?: ($config['textModel'] ?? '');
    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
    ];
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . ($config['apiKey'] ?? ''),
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $latencyMs = (int)round((microtime(true) - $startedAt) * 1000);

    $providerError = null;
    $text = '';
    $decoded = null;
    $requestId = null;
    $modelUsed = $model;
    $rawBody = (string)($response === false ? '' : $response);

    if ($response === false) {
        $providerError = 'Request failed: ' . ($curlError !== '' ? $curlError : 'transport error');
    } else {
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded)) {
            $requestId = $decoded['id'] ?? null;
            $modelUsed = $decoded['model'] ?? $modelUsed;
            $errorPayload = ai_detect_provider_error_payload($decoded);
            if ($httpCode >= 200 && $httpCode < 300) {
                $providerError = $errorPayload;
                $text = ai_extract_openai_text($decoded);
            } else {
                $providerError = $errorPayload ?? ('HTTP ' . $httpCode);
            }
        } elseif ($httpCode < 200 || $httpCode >= 300) {
            $providerError = 'HTTP ' . $httpCode;
        }
    }

    $ok = $providerError === null && $httpCode >= 200 && $httpCode < 300;

    return [
        'provider' => 'openai',
        'httpStatus' => $httpCode ?? 0,
        'rawBody' => $rawBody,
        'text' => $text,
        'providerError' => $providerError,
        'requestId' => $requestId,
        'responseId' => $requestId,
        'ok' => $ok,
        'modelUsed' => $modelUsed,
        'latencyMs' => $latencyMs,
        'temperatureUsed' => $temperature,
        'maxTokensUsed' => $maxTokens,
    ];
}

function ai_provider_response_gemini(array $config, string $systemPrompt, string $userPrompt, float $temperature = 0.2, int $maxTokens = 500, array $options = []): array
{
    $startedAt = microtime(true);
    $model = $options['modelOverride'] ?? ($config['textModel'] ?? '');
    $stream = (bool)($options['stream'] ?? false);
    $attemptType = $options['attemptType'] ?? 'primary';
    $structured = (bool)($options['structured'] ?? false);
    $responseSchema = $options['responseSchema'] ?? null;
    $urlBase = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model);
    $url = $urlBase . ($stream ? ':streamGenerateContent' : ':generateContent') . '?key=' . urlencode((string)($config['apiKey'] ?? ''));
    $payload = [
        'system_instruction' => [
            'parts' => [
                ['text' => $systemPrompt],
            ],
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $userPrompt],
                ],
            ],
        ],
        'generation_config' => [
            'temperature' => $temperature,
            'max_output_tokens' => $maxTokens,
        ],
    ];
    if ($structured && is_array($responseSchema)) {
        $payload['generation_config']['response_mime_type'] = 'application/json';
        $payload['generation_config']['response_schema'] = $responseSchema;
        $payload['generation_config']['response_json_schema'] = $responseSchema;
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $responseHeaders = [];
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($chResource, string $header) use (&$responseHeaders): int {
        $len = strlen($header);
        $header = trim($header);
        if ($header === '' || strpos($header, ':') === false) {
            return $len;
        }
        [$name, $value] = array_map('trim', explode(':', $header, 2));
        $responseHeaders[strtolower($name)] = $value;
        return $len;
    });
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: ' . ($stream ? 'text/event-stream' : 'application/json'),
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $latencyMs = (int)round((microtime(true) - $startedAt) * 1000);

    $providerError = null;
    $text = '';
    $decoded = null;
    $requestId = null;
    $responseId = null;
    $modelUsed = $model;
    $rawBody = (string)($response === false ? '' : $response);
    $finishReason = null;
    $finishReasons = [];
    $blockReason = null;
    $safetySummary = '';
    $diagnosticError = null;

    if ($response === false) {
        $providerError = 'Request failed: ' . ($curlError !== '' ? $curlError : 'transport error');
    } else {
        if ($stream) {
            $lines = preg_split('/\\r?\\n/', $rawBody) ?: [];
            foreach ($lines as $line) {
                $line = trim((string)$line);
                if ($line === '' || $line === 'data: [DONE]') {
                    continue;
                }
                $jsonLine = strpos($line, 'data:') === 0 ? trim(substr($line, 5)) : $line;
                $candidateDecoded = json_decode($jsonLine, true);
                if (!is_array($candidateDecoded)) {
                    continue;
                }
                $decoded = $candidateDecoded;
                $responseId = $candidateDecoded['responseId'] ?? $responseId;
                $modelUsed = $candidateDecoded['model'] ?? $modelUsed;
                $chunkText = ai_extract_gemini_text($candidateDecoded);
                if ($chunkText !== '') {
                    $text .= ($text !== '' ? "\n" : '') . $chunkText;
                }
                $finishReason = $finishReason ?: ai_gemini_finish_reason($candidateDecoded);
                $candidateFinish = ai_gemini_finish_reason($candidateDecoded);
                if ($candidateFinish) {
                    $finishReasons[] = $candidateFinish;
                }
                $blockReason = $blockReason ?: ai_gemini_block_reason($candidateDecoded);
                $safetySummary = $safetySummary ?: ai_gemini_safety_summary($candidateDecoded['promptFeedback']['safetyRatings'] ?? ($candidateDecoded['candidates'][0]['safetyRatings'] ?? []));
                $errorPayload = ai_detect_provider_error_payload($candidateDecoded);
                if ($errorPayload && $providerError === null) {
                    $providerError = $errorPayload;
                }
            }
            $requestId = ai_request_id_from_headers($responseHeaders) ?? $responseId ?? $requestId;
            if ($httpCode < 200 || $httpCode >= 300) {
                $providerError = $providerError ?? ('HTTP ' . $httpCode);
            } elseif ($providerError === null && $blockReason) {
                $providerError = 'Prompt blocked: ' . $blockReason;
            }
        } else {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $responseId = $decoded['responseId'] ?? null;
                $requestId = ai_request_id_from_headers($responseHeaders) ?? $responseId ?? ($decoded['id'] ?? null);
                $modelUsed = $decoded['model'] ?? $modelUsed;
                $finishReason = ai_gemini_finish_reason($decoded);
                $finishReasons = $finishReason ? [$finishReason] : [];
                if (!empty($decoded['candidates']) && is_array($decoded['candidates'])) {
                    foreach ($decoded['candidates'] as $candidate) {
                        if (!is_array($candidate)) {
                            continue;
                        }
                        if (!empty($candidate['finishReason'])) {
                            $finishReasons[] = (string)$candidate['finishReason'];
                        }
                    }
                }
                $finishReasons = array_values(array_filter(array_unique($finishReasons)));
                $blockReason = ai_gemini_block_reason($decoded);
                $safetySummary = ai_gemini_safety_summary($decoded['promptFeedback']['safetyRatings'] ?? ($decoded['candidates'][0]['safetyRatings'] ?? []));
                $errorPayload = ai_detect_provider_error_payload($decoded);
                $hasCandidates = !empty($decoded['candidates']) && is_array($decoded['candidates']);
                if ($httpCode >= 200 && $httpCode < 300) {
                    if ($blockReason) {
                        $providerError = 'Prompt blocked: ' . $blockReason;
                    } elseif (!$hasCandidates && !empty($decoded['promptFeedback'])) {
                        $providerError = 'Prompt feedback indicated an issue.';
                    } else {
                        $providerError = $errorPayload;
                        $text = ai_extract_gemini_text($decoded);
                    }
                } else {
                    $providerError = $errorPayload ?? ('HTTP ' . $httpCode);
                }
            } elseif ($httpCode < 200 || $httpCode >= 300) {
                $providerError = 'HTTP ' . $httpCode;
            } else {
                $providerError = 'Invalid JSON response from provider.';
            }
        }
    }
    $requestId = $requestId ?? ai_request_id_from_headers($responseHeaders);

    if ($finishReason && !in_array($finishReason, $finishReasons, true)) {
        $finishReasons[] = $finishReason;
    }
    $finishReasons = array_values(array_unique(array_filter($finishReasons)));
    if ($text === '' && $providerError === null && $blockReason === null && $httpCode >= 200 && $httpCode < 300) {
        $diagnosticError = 'empty_content';
    }

    $ok = $providerError === null && $httpCode >= 200 && $httpCode < 300 && !$blockReason;

    return [
        'provider' => 'gemini',
        'httpStatus' => $httpCode ?? 0,
        'rawBody' => $rawBody,
        'text' => $text,
        'providerError' => $providerError,
        'requestId' => $requestId,
        'responseId' => $responseId,
        'ok' => $ok,
        'modelUsed' => $modelUsed,
        'latencyMs' => $latencyMs,
        'finishReason' => $finishReason,
        'finishReasons' => $finishReasons,
        'blockReason' => $blockReason,
        'safetySummary' => $safetySummary,
        'attemptType' => $attemptType,
        'stream' => $stream,
        'temperatureUsed' => $temperature,
        'maxTokensUsed' => $maxTokens,
        'structured' => $structured,
        'responseMimeType' => $structured ? 'application/json' : null,
        'schemaApplied' => $structured && is_array($responseSchema),
        'responseSchema' => $structured ? $responseSchema : null,
        'diagnosticError' => $diagnosticError,
        'rawHeaders' => $responseHeaders,
    ];
}

function ai_call(array $params): array
{
    $result = [
        'ok' => false,
        'providerOk' => false,
        'parsedOk' => false,
        'rawText' => '',
        'text' => '',
        'json' => null,
        'errors' => [],
        'httpStatus' => null,
        'requestId' => null,
        'responseId' => null,
        'parseStage' => 'fallback_manual',
        'providerError' => null,
        'rawEnvelope' => null,
        'rawBody' => '',
        'rawBodySnippet' => '',
        'latencyMs' => null,
        'modelUsed' => null,
        'temperatureUsed' => null,
        'finishReason' => null,
        'finishReasons' => [],
        'promptBlockReason' => null,
        'safetyRatingsSummary' => '',
        'retryCount' => 0,
        'fallbackUsed' => false,
        'fallbackModelUsed' => null,
        'attempts' => [],
        'schemaValidation' => [
            'enabled' => false,
            'passed' => true,
            'errors' => [],
        ],
        'diagnosticError' => null,
        'error' => null,
    ];

    $configResult = ai_get_config(true);
    $config = $configResult['config'] ?? ai_config_defaults();
    $provider = $config['provider'] ?? '';
    $apiKey = $config['apiKey'] ?? '';
    $systemPrompt = $params['systemPrompt'] ?? '';
    $userPrompt = $params['userPrompt'] ?? '';
    $expectJson = (bool)($params['expectJson'] ?? false);
    $purpose = $params['purpose'] ?? 'general';
    $runMode = $params['runMode'] ?? 'strict';
    $modelOverride = isset($params['modelOverride']) ? trim((string)$params['modelOverride']) : null;
    $fallbackModelOverride = isset($params['fallbackModelOverride']) ? trim((string)$params['fallbackModelOverride']) : null;
    $allowFallback = !isset($params['allowFallback']) ? true : (bool)$params['allowFallback'];
    $purposeModels = ai_resolve_purpose_models($config, $purpose);
    if ($modelOverride !== null && $modelOverride !== '') {
        $purposeModels['primaryModel'] = $modelOverride;
    }
    if ($fallbackModelOverride !== null) {
        $purposeModels['fallbackModel'] = $fallbackModelOverride !== '' ? $fallbackModelOverride : null;
    }
    $structuredOutput = ($config['provider'] ?? '') === 'gemini'
        && ai_purpose_key($purpose) === 'offlineTenderExtract'
        && !empty($purposeModels['useStructuredJson']);
    $responseSchema = $structuredOutput && function_exists('offline_tender_response_schema') ? offline_tender_response_schema() : null;
    $temperature = isset($params['temperature']) ? max(0.1, min(1.0, (float)$params['temperature'])) : 0.2;
    $maxTokens = isset($params['maxTokens']) ? max(200, (int)$params['maxTokens']) : 500;

    $startedAt = microtime(true);
    $providerResult = null;

    if (!function_exists('curl_init')) {
        $result['errors'][] = 'cURL extension is required.';
    }

    $configErrors = $configResult['errors'] ?? [];
    if (!empty($configErrors)) {
        $result['errors'] = array_merge($result['errors'], $configErrors);
        $result['errors'][] = 'AI is not configured. Superadmin: set provider, API key, and model in AI Studio.';
    }

    if (($config['textModel'] ?? '') === '' && ($purposeModels['primaryModel'] ?? '') === '') {
        $result['errors'][] = 'Text model is required for AI calls.';
    }

    if (empty($result['errors'])) {
        try {
            if ($provider === 'openai') {
                $providerResult = ai_provider_response_openai($config, $systemPrompt, $userPrompt, $temperature, $maxTokens, $purposeModels['primaryModel'] ?? null);
                $providerResult['attemptType'] = 'primary';
                $result['attempts'][] = $providerResult;
                if (!$providerResult['ok'] && !empty($purposeModels['fallbackModel']) && $allowFallback) {
                    $result['fallbackUsed'] = true;
                    $result['fallbackModelUsed'] = $purposeModels['fallbackModel'];
                    $fallbackAttempt = ai_provider_response_openai(
                        $config,
                        $systemPrompt,
                        $userPrompt,
                        max($temperature, 0.5),
                        max($maxTokens, 800),
                        $purposeModels['fallbackModel']
                    );
                    $fallbackAttempt['attemptType'] = 'fallback_model';
                    $result['attempts'][] = $fallbackAttempt;
                    $providerResult = $fallbackAttempt;
                }
            } elseif ($provider === 'gemini') {
                $retryCount = 0;
                $primary = ai_provider_response_gemini(
                    $config,
                    $systemPrompt,
                    $userPrompt,
                    $temperature,
                    $maxTokens,
                    [
                        'modelOverride' => $purposeModels['primaryModel'] ?? null,
                        'attemptType' => 'primary',
                        'structured' => $structuredOutput,
                        'responseSchema' => $responseSchema,
                    ]
                );
                $result['attempts'][] = $primary;
                $providerResult = $primary;
                if (($primary['ok'] ?? false) && trim((string)($primary['text'] ?? '')) === '' && $allowFallback) {
                    if (!empty($purposeModels['useStreamingFallback'])) {
                        $streamAttempt = ai_provider_response_gemini(
                            $config,
                            $systemPrompt,
                            $userPrompt,
                            $temperature,
                            $maxTokens,
                            [
                                'modelOverride' => $purposeModels['primaryModel'] ?? null,
                                'stream' => true,
                                'attemptType' => 'stream_fallback',
                                'structured' => $structuredOutput,
                                'responseSchema' => $responseSchema,
                            ]
                        );
                        $result['attempts'][] = $streamAttempt;
                        $providerResult = $streamAttempt;
                    }
                    if (trim((string)($providerResult['text'] ?? '')) === '' && !empty($purposeModels['retryOnceOnEmpty']) && $allowFallback) {
                        $retryCount++;
                        $retryAttempt = ai_provider_response_gemini(
                            $config,
                            $systemPrompt,
                            $userPrompt,
                            0.7,
                            max($maxTokens, 1024),
                            [
                                'modelOverride' => $purposeModels['primaryModel'] ?? null,
                                'attemptType' => 'retry',
                                'structured' => $structuredOutput,
                                'responseSchema' => $responseSchema,
                            ]
                        );
                        $result['attempts'][] = $retryAttempt;
                        $providerResult = $retryAttempt;
                    }
                    if (trim((string)($providerResult['text'] ?? '')) === '' && !empty($purposeModels['fallbackModel']) && $allowFallback) {
                        $result['fallbackUsed'] = true;
                        $result['fallbackModelUsed'] = $purposeModels['fallbackModel'];
                        $fallbackAttempt = ai_provider_response_gemini(
                            $config,
                            $systemPrompt,
                            $userPrompt,
                            0.7,
                            max($maxTokens, 1024),
                            [
                                'modelOverride' => $purposeModels['fallbackModel'],
                                'attemptType' => 'fallback_model',
                                'structured' => $structuredOutput,
                                'responseSchema' => $responseSchema,
                            ]
                        );
                        $result['attempts'][] = $fallbackAttempt;
                        $providerResult = $fallbackAttempt;
                    }
                } elseif (!($primary['ok'] ?? false) && !empty($purposeModels['fallbackModel']) && $allowFallback) {
                    $result['fallbackUsed'] = true;
                    $result['fallbackModelUsed'] = $purposeModels['fallbackModel'];
                    $fallbackAttempt = ai_provider_response_gemini(
                        $config,
                        $systemPrompt,
                        $userPrompt,
                        0.6,
                        $maxTokens,
                        [
                            'modelOverride' => $purposeModels['fallbackModel'],
                            'attemptType' => 'fallback_model',
                            'structured' => $structuredOutput,
                            'responseSchema' => $responseSchema,
                        ]
                    );
                    $result['attempts'][] = $fallbackAttempt;
                    $providerResult = $fallbackAttempt;
                }
                $result['retryCount'] = $retryCount ?? 0;
            } else {
                $result['errors'][] = 'Unsupported provider.';
            }
        } catch (Throwable $e) {
            $result['errors'][] = 'Unexpected error: ' . $e->getMessage();
        }
    }

    if ($providerResult !== null) {
        ai_apply_provider_result($result, $providerResult, $config, $expectJson, $temperature, $maxTokens, $provider, $purpose);
    }

    if ($provider === 'gemini'
        && $expectJson
        && ($result['schemaValidation']['enabled'] ?? false)
        && !($result['schemaValidation']['passed'] ?? true)
        && !empty($purposeModels['fallbackModel'])
        && !$result['fallbackUsed']
        && $allowFallback
    ) {
        $result['errors'][] = 'Schema validation failed; attempting fallback model.';
        $result['fallbackUsed'] = true;
        $result['fallbackModelUsed'] = $purposeModels['fallbackModel'];
        $fallbackAttempt = ai_provider_response_gemini(
            $config,
            $systemPrompt,
            $userPrompt,
            0.6,
            max($maxTokens, 1024),
            [
                'modelOverride' => $purposeModels['fallbackModel'],
                'attemptType' => 'fallback_schema',
                'structured' => $structuredOutput,
                'responseSchema' => $responseSchema,
            ]
        );
        $result['attempts'][] = $fallbackAttempt;
        $providerResult = $fallbackAttempt;
        ai_apply_provider_result($result, $providerResult, $config, $expectJson, $temperature, $maxTokens, $provider, $purpose);
    }

    $durationMs = (int)round((microtime(true) - $startedAt) * 1000);

    if (is_array($result['rawEnvelope'])) {
        $result['rawEnvelope']['latencyMs'] = $result['rawEnvelope']['latencyMs'] ?? $durationMs;
    }

    if ($result['httpStatus'] !== null) {
        if (in_array($result['httpStatus'], [401, 403], true)) {
            $result['errors'][] = 'Invalid API key or not authorized for model.';
        } elseif ($result['httpStatus'] === 404) {
            $result['errors'][] = 'Model name incorrect or not available.';
        } elseif ($result['httpStatus'] === 429) {
            $result['errors'][] = 'Rate limit hit. Please slow down and retry.';
        } elseif ($result['httpStatus'] >= 500) {
            $result['errors'][] = 'Upstream provider returned a server error.';
        }
    }

    if ($result['providerOk'] && trim((string)$result['text']) === '') {
        $result['errors'][] = 'Empty response anomaly.';
    }

    $result['rawBodySnippet'] = substr($result['rawBody'] ?: $result['rawText'], 0, 800);
    $result['provider'] = $provider;
    $result['error'] = $result['errors'][0] ?? ($result['providerError'] ?? ($result['diagnosticError'] ?? null));

    foreach ($result['attempts'] as $attempt) {
        ai_log([
            'event' => 'ai_call_attempt',
            'purpose' => $purpose,
            'provider' => $provider,
            'model' => $attempt['modelUsed'] ?? ($config['textModel'] ?? ''),
            'attemptType' => $attempt['attemptType'] ?? 'primary',
            'expectJson' => $expectJson,
            'ok' => $attempt['ok'] ?? false,
            'textLen' => strlen((string)($attempt['text'] ?? '')),
            'finishReason' => $attempt['finishReason'] ?? null,
            'finishReasons' => $attempt['finishReasons'] ?? [],
            'blockReason' => $attempt['blockReason'] ?? null,
            'requestId' => $attempt['requestId'] ?? null,
            'responseId' => $attempt['responseId'] ?? null,
            'errorCount' => !empty($attempt['providerError']) ? 1 : 0,
            'errors' => array_filter([$attempt['providerError'] ?? null]),
            'httpStatus' => $attempt['httpStatus'] ?? null,
            'runMode' => $runMode,
            'temperature' => $attempt['temperatureUsed'] ?? $temperature,
            'latencyMs' => $attempt['latencyMs'] ?? $durationMs,
            'structured' => $attempt['structured'] ?? false,
            'responseMimeType' => $attempt['responseMimeType'] ?? null,
            'schemaApplied' => $attempt['schemaApplied'] ?? false,
            'diagnosticError' => $attempt['diagnosticError'] ?? null,
        ]);

        ai_provider_log_raw([
            'event' => 'ai_provider_raw',
            'provider' => $provider,
            'modelUsed' => $attempt['modelUsed'] ?? ($config['textModel'] ?? ''),
            'attemptType' => $attempt['attemptType'] ?? 'primary',
            'httpStatus' => $attempt['httpStatus'] ?? null,
            'requestId' => $attempt['requestId'] ?? null,
            'responseId' => $attempt['responseId'] ?? null,
            'rawSnippet' => substr($attempt['rawBody'] ?? ($attempt['text'] ?? ''), 0, 800),
            'textLen' => strlen((string)($attempt['text'] ?? '')),
            'finishReasons' => $attempt['finishReasons'] ?? [],
            'blockReason' => $attempt['blockReason'] ?? null,
            'ok' => $attempt['ok'] ?? false,
            'error' => $attempt['providerError'] ?? ($attempt['diagnosticError'] ?? null),
            'parsedOk' => $result['parsedOk'],
            'errors' => array_slice($result['errors'], 0, 5),
            'latencyMs' => $attempt['latencyMs'] ?? $durationMs,
            'structured' => $attempt['structured'] ?? false,
            'responseMimeType' => $attempt['responseMimeType'] ?? null,
            'purpose' => $purpose,
        ]);
    }

    ai_log([
        'event' => 'ai_call',
        'purpose' => $purpose,
        'provider' => $provider,
        'model' => $result['modelUsed'] ?? ($config['textModel'] ?? ''),
        'expectJson' => $expectJson,
        'ok' => $result['ok'],
        'parsedOk' => $result['parsedOk'],
        'providerOk' => $result['providerOk'],
        'errorCount' => count($result['errors']),
        'errors' => array_slice($result['errors'], 0, 5),
        'durationMs' => $durationMs,
        'httpStatus' => $result['httpStatus'],
        'runMode' => $runMode,
        'temperature' => $temperature,
        'finishReason' => $result['finishReason'],
        'finishReasons' => $result['finishReasons'],
        'blockReason' => $result['promptBlockReason'],
        'requestId' => $result['requestId'],
        'responseId' => $result['responseId'],
        'retryCount' => $result['retryCount'],
        'fallbackUsed' => $result['fallbackUsed'],
        'structured' => is_array($result['rawEnvelope']) ? ($result['rawEnvelope']['structured'] ?? false) : false,
        'schemaValidation' => $result['schemaValidation'],
        'responseMimeType' => is_array($result['rawEnvelope']) ? ($result['rawEnvelope']['responseMimeType'] ?? null) : null,
        'diagnosticError' => $result['diagnosticError'],
    ]);

    return $result;
}

function ai_call_text(string $purpose, string $systemPrompt, string $userPrompt, array $options = []): array
{
    $params = [
        'purpose' => $purpose,
        'systemPrompt' => $systemPrompt,
        'userPrompt' => $userPrompt,
        'expectJson' => (bool)($options['expectJson'] ?? false),
        'runMode' => $options['runMode'] ?? 'strict',
    ];

    if (isset($options['temperature'])) {
        $params['temperature'] = $options['temperature'];
    }
    if (isset($options['maxTokens'])) {
        $params['maxTokens'] = $options['maxTokens'];
    }
    if (isset($options['modelOverride'])) {
        $params['modelOverride'] = $options['modelOverride'];
    }
    if (isset($options['fallbackModelOverride'])) {
        $params['fallbackModelOverride'] = $options['fallbackModelOverride'];
    }
    if (isset($options['allowFallback'])) {
        $params['allowFallback'] = $options['allowFallback'];
    }

    return ai_call($params);
}
