<?php
declare(strict_types=1);

const AI_CONFIG_PATH = DATA_PATH . '/ai/ai_config.json';
const AI_SECRET_PATH = DATA_PATH . '/ai/secret.key';
const AI_LOG_FILE = DATA_PATH . '/logs/ai.log';

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

function extract_first_json_block(string $text): ?string
{
    $text = trim($text);
    $length = strlen($text);
    $depth = 0;
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
        if ($char === '{') {
            if ($start === null) {
                $start = $i;
            }
            $depth++;
        } elseif ($char === '}') {
            if ($depth > 0) {
                $depth--;
                if ($depth === 0 && $start !== null) {
                    return substr($text, (int)$start, $i - (int)$start + 1);
                }
            }
        }
    }

    return null;
}

function repair_json_text(string $text): string
{
    $text = normalize_quotes(strip_code_fences($text));
    $text = preg_replace('/,(\s*[}\]])/', '$1', $text);
    return $text;
}

function parse_ai_json(string $text): array
{
    $result = [
        'json' => null,
        'errors' => [],
        'rawCandidate' => '',
    ];

    $cleanText = repair_json_text($text);
    $candidate = extract_first_json_block($cleanText) ?? $cleanText;
    $result['rawCandidate'] = $candidate;
    $decoded = json_decode($candidate, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $result['json'] = $decoded;
        return $result;
    }

    $result['errors'][] = 'Initial parse failed: ' . json_last_error_msg();

    $secondPass = extract_first_json_block(repair_json_text($candidate)) ?? $candidate;
    $decoded = json_decode($secondPass, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $result['json'] = $decoded;
        return $result;
    }

    $result['errors'][] = 'Repair attempt failed: ' . json_last_error_msg();
    return $result;
}

function ai_log(array $context): void
{
    logEvent(AI_LOG_FILE, $context);
}

function ai_call(array $params): array
{
    $result = [
        'ok' => false,
        'rawText' => '',
        'json' => null,
        'errors' => [],
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

    $startedAt = microtime(true);

    if (empty($result['errors'])) {
        try {
            if ($provider === 'openai') {
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
                    'Authorization: Bearer ' . $apiKey,
                ]);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                $response = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($response === false) {
                    $result['errors'][] = 'Request failed: ' . $curlError;
                } else {
                    $decoded = json_decode((string)$response, true);
                    if ($httpCode >= 200 && $httpCode < 300 && isset($decoded['choices'][0]['message']['content'])) {
                        $result['rawText'] = (string)$decoded['choices'][0]['message']['content'];
                    } else {
                        $result['errors'][] = 'Provider error: ' . ($decoded['error']['message'] ?? ('HTTP ' . $httpCode));
                    }
                }
            } elseif ($provider === 'gemini') {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($config['textModel']) . ':generateContent?key=' . urlencode($apiKey);
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

                if ($response === false) {
                    $result['errors'][] = 'Request failed: ' . $curlError;
                } else {
                    $decoded = json_decode((string)$response, true);
                    if ($httpCode >= 200 && $httpCode < 300 && isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                        $result['rawText'] = (string)$decoded['candidates'][0]['content']['parts'][0]['text'];
                    } else {
                        $result['errors'][] = 'Provider error: ' . ($decoded['error']['message'] ?? ('HTTP ' . $httpCode));
                    }
                }
            } else {
                $result['errors'][] = 'Unsupported provider.';
            }

            if ($result['rawText'] !== '') {
                if ($expectJson) {
                    $parsed = parse_ai_json($result['rawText']);
                    if ($parsed['json'] !== null) {
                        $result['json'] = $parsed['json'];
                        $result['ok'] = true;
                    } else {
                        $result['errors'] = array_merge($result['errors'], $parsed['errors']);
                    }
                } else {
                    $result['ok'] = true;
                }
            }
        } catch (Throwable $e) {
            $result['errors'][] = 'Unexpected error: ' . $e->getMessage();
        }
    }

    ai_log([
        'event' => 'ai_call',
        'purpose' => $purpose,
        'provider' => $provider,
        'model' => $config['textModel'],
        'expectJson' => $expectJson,
        'ok' => $result['ok'],
        'errorCount' => count($result['errors']),
        'errors' => array_slice($result['errors'], 0, 5),
        'durationMs' => (int)round((microtime(true) - $startedAt) * 1000),
    ]);

    return $result;
}
