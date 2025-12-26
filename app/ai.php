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
        'apiKey' => null,
        'textModel' => '',
        'imageModel' => '',
        'updatedAt' => null,
    ];
}

function load_ai_config(bool $includeKey = false): array
{
    ensure_ai_storage();
    $config = readJson(AI_CONFIG_PATH);
    if (!$config) {
        $config = ai_config_defaults();
    } else {
        $config = array_merge(ai_config_defaults(), $config);
    }
    $config['hasApiKey'] = !empty($config['apiKey']);
    if ($includeKey) {
        $config['apiKey'] = reveal_api_key($config['apiKey']);
    } else {
        unset($config['apiKey']);
    }
    return $config;
}

function save_ai_config(string $provider, string $apiKey, string $textModel, string $imageModel): void
{
    ensure_ai_storage();
    $payload = [
        'provider' => $provider,
        'apiKey' => obfuscate_api_key($apiKey),
        'textModel' => $textModel,
        'imageModel' => $imageModel,
        'updatedAt' => now_kolkata()->format(DateTime::ATOM),
    ];
    writeJsonAtomic(AI_CONFIG_PATH, $payload);
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

function ai_log(array $context): void
{
    logEvent(AI_LOG_FILE, $context);
}

function ai_provider_log_raw(array $context): void
{
    logEvent(AI_PROVIDER_RAW_LOG, $context);
}

function ai_provider_response_openai(array $config, string $systemPrompt, string $userPrompt): array
{
    $payload = [
        'model' => $config['textModel'],
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
        'temperature' => 0.2,
        'max_tokens' => 500,
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

    $providerError = null;
    $text = '';
    $decoded = null;
    $requestId = null;

    if ($response === false) {
        $providerError = 'Request failed: ' . $curlError;
    } else {
        $decoded = json_decode((string)$response, true);
        $requestId = $decoded['id'] ?? null;
        if ($httpCode >= 200 && $httpCode < 300) {
            if (isset($decoded['error']['message'])) {
                $providerError = 'Provider signaled error: ' . $decoded['error']['message'];
            } else {
                $text = (string)($decoded['choices'][0]['message']['content'] ?? '');
                if ($text === '') {
                    $providerError = 'Provider returned HTTP ' . $httpCode . ' but no content payload.';
                }
            }
        } else {
            $providerError = $decoded['error']['message'] ?? ('HTTP ' . $httpCode);
        }
    }

    return [
        'provider' => 'openai',
        'httpStatus' => $httpCode ?? 0,
        'rawBody' => (string)$response,
        'text' => $text,
        'providerError' => $providerError,
        'requestId' => $requestId,
    ];
}

function ai_provider_response_gemini(array $config, string $systemPrompt, string $userPrompt): array
{
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($config['textModel']) . ':generateContent?key=' . urlencode((string)($config['apiKey'] ?? ''));
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
            'temperature' => 0.2,
            'max_output_tokens' => 500,
        ],
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $providerError = null;
    $text = '';
    $decoded = null;
    $requestId = null;

    if ($response === false) {
        $providerError = 'Request failed: ' . $curlError;
    } else {
        $decoded = json_decode((string)$response, true);
        $requestId = $decoded['responseId'] ?? null;
        if ($httpCode >= 200 && $httpCode < 300) {
            if (isset($decoded['error']['message'])) {
                $providerError = 'Provider signaled error: ' . $decoded['error']['message'];
            } else {
                $text = (string)($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');
                if ($text === '') {
                    $providerError = 'Provider returned HTTP ' . $httpCode . ' but no content payload.';
                }
            }
        } else {
            $providerError = $decoded['error']['message'] ?? ('HTTP ' . $httpCode);
        }
    }

    return [
        'provider' => 'gemini',
        'httpStatus' => $httpCode ?? 0,
        'rawBody' => (string)$response,
        'text' => $text,
        'providerError' => $providerError,
        'requestId' => $requestId,
    ];
}

function ai_call(array $params): array
{
    $result = [
        'ok' => false,
        'providerOk' => false,
        'parsedOk' => false,
        'rawText' => '',
        'json' => null,
        'errors' => [],
        'httpStatus' => null,
        'requestId' => null,
        'parseStage' => 'fallback_manual',
        'providerError' => null,
        'rawEnvelope' => null,
    ];

    $config = load_ai_config(true);
    $provider = $config['provider'] ?? '';
    $apiKey = $config['apiKey'] ?? '';
    if (($config['provider'] ?? '') === '' || ($config['textModel'] ?? '') === '') {
        $result['errors'][] = 'AI provider configuration missing.';
    }
    if (!$apiKey) {
        $result['errors'][] = 'AI API key not configured.';
    }
    if (!function_exists('curl_init')) {
        $result['errors'][] = 'cURL extension is required.';
    }

    $systemPrompt = $params['systemPrompt'] ?? '';
    $userPrompt = $params['userPrompt'] ?? '';
    $expectJson = (bool)($params['expectJson'] ?? false);
    $purpose = $params['purpose'] ?? 'general';
    $runMode = $params['runMode'] ?? 'strict';

    $startedAt = microtime(true);
    $providerResult = null;

    if (empty($result['errors'])) {
        try {
            if ($provider === 'openai') {
                $providerResult = ai_provider_response_openai($config, $systemPrompt, $userPrompt);
            } elseif ($provider === 'gemini') {
                $providerResult = ai_provider_response_gemini($config, $systemPrompt, $userPrompt);
            } else {
                $result['errors'][] = 'Unsupported provider.';
            }
        } catch (Throwable $e) {
            $result['errors'][] = 'Unexpected error: ' . $e->getMessage();
        }
    }

    if ($providerResult !== null) {
        $result['httpStatus'] = $providerResult['httpStatus'];
        $result['rawText'] = $providerResult['text'] ?? '';
        $result['requestId'] = $providerResult['requestId'] ?? null;
        $result['providerError'] = $providerResult['providerError'] ?? null;
        $result['providerOk'] = ($providerResult['providerError'] ?? null) === null && ($providerResult['httpStatus'] ?? 0) >= 200 && ($providerResult['httpStatus'] ?? 0) < 300;
        $result['rawEnvelope'] = [
            'provider' => $provider,
            'model' => $config['textModel'],
            'httpStatus' => $providerResult['httpStatus'] ?? null,
            'requestId' => $providerResult['requestId'] ?? null,
            'latencyMs' => null,
        ];

        if ($result['providerError']) {
            $result['errors'][] = $result['providerError'];
        }

        if ($result['rawText'] === '' && $result['providerOk']) {
            $result['errors'][] = 'Provider succeeded but returned empty content.';
        }

        if ($result['rawText'] !== '') {
            if ($expectJson) {
                $parsed = parse_ai_json($result['rawText']);
                if ($parsed['json'] !== null) {
                    $result['json'] = $parsed['json'];
                    $result['parsedOk'] = true;
                    $result['parseStage'] = $parsed['parseStage'];
                } else {
                    $result['errors'] = array_merge($result['errors'], $parsed['errors']);
                    $result['parseStage'] = $parsed['parseStage'];
                }
            } else {
                $result['parsedOk'] = $result['providerOk'];
            }
        }
    }

    $durationMs = (int)round((microtime(true) - $startedAt) * 1000);
    $result['ok'] = $result['parsedOk'];

    if (is_array($result['rawEnvelope'])) {
        $result['rawEnvelope']['latencyMs'] = $durationMs;
    }

    ai_log([
        'event' => 'ai_call',
        'purpose' => $purpose,
        'provider' => $provider,
        'model' => $config['textModel'] ?? '',
        'expectJson' => $expectJson,
        'ok' => $result['ok'],
        'parsedOk' => $result['parsedOk'],
        'providerOk' => $result['providerOk'],
        'errorCount' => count($result['errors']),
        'errors' => array_slice($result['errors'], 0, 5),
        'durationMs' => $durationMs,
        'httpStatus' => $result['httpStatus'],
        'runMode' => $runMode,
    ]);

    if ($providerResult !== null) {
        ai_provider_log_raw([
            'event' => 'ai_provider_raw',
            'provider' => $provider,
            'model' => $config['textModel'] ?? '',
            'httpStatus' => $providerResult['httpStatus'] ?? null,
            'requestId' => $providerResult['requestId'] ?? null,
            'responseSnippet' => substr($providerResult['rawBody'] ?? ($result['rawText'] ?? ''), 0, 500),
            'parsedOk' => $result['parsedOk'],
            'errors' => array_slice($result['errors'], 0, 5),
            'latencyMs' => $durationMs,
        ]);
    }

    return $result;
}
