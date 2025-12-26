<?php
declare(strict_types=1);

const CONTENT_LOG_FILE = DATA_PATH . '/logs/content.log';
const CONTENT_BASE_PATH = DATA_PATH . '/content';

function ensure_content_structure(): void
{
    $directories = [
        CONTENT_BASE_PATH,
        CONTENT_BASE_PATH . '/blog',
        CONTENT_BASE_PATH . '/news',
        CONTENT_BASE_PATH . '/jobs',
        PUBLIC_PATH . '/uploads/content',
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $indexFiles = [
        CONTENT_BASE_PATH . '/blog/index.json',
        CONTENT_BASE_PATH . '/news/index.json',
    ];

    foreach ($indexFiles as $file) {
        if (!file_exists($file)) {
            writeJsonAtomic($file, []);
        }
    }

    if (!file_exists(CONTENT_LOG_FILE)) {
        touch(CONTENT_LOG_FILE);
    }
}

function content_log(array $context): void
{
    logEvent(CONTENT_LOG_FILE, $context);
}

function content_index_path(string $type): string
{
    return CONTENT_BASE_PATH . '/' . $type . '/index.json';
}

function content_item_path(string $type, string $id): string
{
    return CONTENT_BASE_PATH . '/' . $type . '/' . $id . '.json';
}

function content_index_lock_path(string $type): string
{
    return CONTENT_BASE_PATH . '/' . $type . '/index.lock';
}

function content_upload_dir(string $type, string $id): string
{
    return PUBLIC_PATH . '/uploads/content/' . $type . '/' . $id;
}

function load_content_index(string $type): array
{
    return readJson(content_index_path($type));
}

function save_content_index(string $type, array $index): void
{
    writeJsonAtomic(content_index_path($type), $index);
}

function content_generate_id(string $type): string
{
    $prefix = $type === 'news' ? 'NEWS-' : 'BLOG-';
    return $prefix . strtoupper(bin2hex(random_bytes(3)));
}

function generate_unique_job_id(): string
{
    do {
        $jobId = 'JOB-' . strtoupper(bin2hex(random_bytes(4)));
        $jobPath = content_job_path($jobId);
    } while (file_exists($jobPath));

    return $jobId;
}

function content_id_exists(string $type, string $id): bool
{
    $index = load_content_index($type);
    foreach ($index as $row) {
        if (($row['id'] ?? '') === $id) {
            return true;
        }
    }

    return file_exists(content_item_path($type, $id));
}

function generate_unique_content_id(string $type): string
{
    do {
        $id = content_generate_id($type);
    } while (content_id_exists($type, $id));

    return $id;
}

function content_validate_slug(string $slug): bool
{
    return (bool)preg_match('/^[a-z0-9-]{3,80}$/', $slug);
}

function content_slug_exists(string $type, string $slug, ?string $excludeId = null): bool
{
    $index = load_content_index($type);
    foreach ($index as $row) {
        if ($row['slug'] === $slug && (!$excludeId || $row['id'] !== $excludeId) && ($row['status'] ?? '') !== 'deleted') {
            return true;
        }
    }
    return false;
}

function content_slugify(string $title, string $fallback): string
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
    if (!content_validate_slug($slug)) {
        $slug = strtolower($fallback);
    }
    $slug = substr($slug, 0, 80);
    $slug = trim($slug, '-');
    if (strlen($slug) < 3) {
        $slug = strtolower($fallback);
    }
    return $slug;
}

function normalize_content_text(string $html): string
{
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strtolower($text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function normalized_first_segment(string $normalized, int $length = 200): string
{
    if ($normalized === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($normalized, 0, $length, 'UTF-8');
    }
    return substr($normalized, 0, $length);
}

function content_output_hash(string $html): string
{
    return hash('sha256', normalize_content_text($html));
}

function content_top_keywords(string $normalized, int $limit = 8): array
{
    $words = preg_split('/[^a-z0-9]+/i', $normalized) ?: [];
    $stop = [
        'the', 'and', 'for', 'that', 'with', 'this', 'from', 'have', 'your', 'about', 'into', 'will',
        'what', 'when', 'where', 'which', 'while', 'their', 'they', 'them', 'you', 'our', 'ours',
        'a', 'an', 'but', 'not', 'are', 'was', 'were', 'been', 'being', 'than', 'then', 'than', 'too',
    ];
    $stopLookup = array_fill_keys($stop, true);
    $freq = [];
    foreach ($words as $word) {
        $word = trim($word);
        if ($word === '' || strlen($word) < 4 || isset($stopLookup[$word])) {
            continue;
        }
        $freq[$word] = ($freq[$word] ?? 0) + 1;
    }
    arsort($freq);
    return array_slice(array_keys($freq), 0, $limit);
}

function build_content_signature(string $bodyHtml): array
{
    $normalized = normalize_content_text($bodyHtml);
    return [
        'normalized' => $normalized,
        'outputHash' => hash('sha256', $normalized),
        'firstSegment' => normalized_first_segment($normalized, 200),
        'keywords' => content_top_keywords($normalized),
    ];
}

function load_recent_content_pool(string $primaryType, int $primaryLimit = 20, int $crossLimit = 10, ?string $excludeId = null): array
{
    $pool = [];
    $targets = [
        $primaryType => $primaryLimit,
        $primaryType === 'blog' ? 'news' : 'blog' => $crossLimit,
    ];

    foreach ($targets as $type => $limit) {
        if ($limit <= 0) {
            continue;
        }
        $index = load_content_index($type);
        if (!is_array($index)) {
            continue;
        }
        usort($index, function ($a, $b) {
            return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
        });

        foreach (array_slice($index, 0, $limit) as $row) {
            $id = $row['id'] ?? null;
            if (!$id || $id === $excludeId || (($row['status'] ?? '') === 'deleted')) {
                continue;
            }
            $item = load_content_item($type, $id);
            if (!$item) {
                continue;
            }
            $signature = build_content_signature((string)($item['bodyHtml'] ?? ''));
            if ($signature['normalized'] === '') {
                continue;
            }
            $pool[] = array_merge($signature, [
                'id' => $id,
                'type' => $type,
                'title' => $item['title'] ?? '',
            ]);
        }
    }

    return $pool;
}

function evaluate_duplicate(array $candidate, array $pool): array
{
    $result = [
        'duplicate' => false,
        'nearDuplicate' => false,
        'matchId' => null,
        'matchType' => null,
        'basis' => null,
        'score' => 0.0,
    ];

    foreach ($pool as $entry) {
        if (($entry['outputHash'] ?? '') === ($candidate['outputHash'] ?? '')) {
            return [
                'duplicate' => true,
                'nearDuplicate' => true,
                'matchId' => $entry['id'] ?? null,
                'matchType' => $entry['type'] ?? null,
                'basis' => 'hash',
                'score' => 1.0,
            ];
        }

        $segmentPercent = 0.0;
        if (($candidate['firstSegment'] ?? '') !== '' && ($entry['firstSegment'] ?? '') !== '') {
            similar_text((string)$candidate['firstSegment'], (string)$entry['firstSegment'], $segmentPercent);
        }

        $overlap = array_intersect($candidate['keywords'] ?? [], $entry['keywords'] ?? []);
        $keywordScore = 0.0;
        if (!empty($candidate['keywords']) && !empty($entry['keywords'])) {
            $keywordScore = count($overlap) / max(1, min(count($candidate['keywords']), count($entry['keywords'])));
        }

        $isNearBySegment = $segmentPercent >= 88.0;
        $isNearByKeywords = $keywordScore >= 0.6 && count($overlap) >= 3;

        if ($isNearBySegment || $isNearByKeywords) {
            $score = max($segmentPercent / 100, $keywordScore);
            if ($score > $result['score']) {
                $result = [
                    'duplicate' => false,
                    'nearDuplicate' => true,
                    'matchId' => $entry['id'] ?? null,
                    'matchType' => $entry['type'] ?? null,
                    'basis' => $isNearBySegment && $isNearByKeywords ? 'segment+keywords' : ($isNearBySegment ? 'segment' : 'keywords'),
                    'score' => $score,
                ];
            }
        }
    }

    return $result;
}

function sanitize_body_html(string $html): string
{
    $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
    $html = preg_replace('#<style(.*?)>(.*?)</style>#is', '', $html);
    $allowed = '<p><h1><h2><h3><h4><h5><ul><ol><li><strong><em><b><i><blockquote><br><hr><img><a>'; 
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/on\w+="[^"]*"/i', '', $html);
    $html = preg_replace('/on\w+=\"[^\"]*\"/i', '', $html);
    $html = preg_replace('/javascript:/i', '', $html);
    return trim($html);
}

function content_excerpt(string $html, int $wordLimit = 60): string
{
    $text = strip_tags($html);
    $text = preg_replace('/\s+/', ' ', $text);
    $words = explode(' ', trim($text));
    if (count($words) > $wordLimit) {
        $words = array_slice($words, 0, $wordLimit);
        $text = implode(' ', $words) . '...';
    }
    return $text;
}

function save_content_item(array $item): void
{
    $type = $item['type'];
    $lockHandle = fopen(content_index_lock_path($type), 'c');
    if ($lockHandle === false) {
        throw new RuntimeException('Unable to open content index lock.');
    }
    if (!flock($lockHandle, LOCK_EX)) {
        fclose($lockHandle);
        throw new RuntimeException('Unable to acquire content index lock.');
    }

    try {
        $index = load_content_index($type);
        if (!is_array($index)) {
            $index = [];
        }

        $found = false;
        foreach ($index as &$row) {
            if ($row['id'] === $item['id']) {
                $row = [
                    'id' => $item['id'],
                    'type' => $item['type'],
                    'title' => $item['title'],
                    'slug' => $item['slug'],
                    'status' => $item['status'],
                    'excerpt' => $item['excerpt'],
                    'coverImagePath' => $item['coverImagePath'],
                    'publishAt' => $item['publishAt'] ?? null,
                    'publishedAt' => $item['publishedAt'] ?? null,
                    'createdAt' => $item['createdAt'],
                    'updatedAt' => $item['updatedAt'],
                ];
                $found = true;
                break;
            }
        }
        unset($row);
        if (!$found) {
            $index[] = [
                'id' => $item['id'],
                'type' => $item['type'],
                'title' => $item['title'],
                'slug' => $item['slug'],
                'status' => $item['status'],
                'excerpt' => $item['excerpt'],
                'coverImagePath' => $item['coverImagePath'],
                'publishAt' => $item['publishAt'] ?? null,
                'publishedAt' => $item['publishedAt'] ?? null,
                'createdAt' => $item['createdAt'],
                'updatedAt' => $item['updatedAt'],
            ];
        }

        save_content_index($type, $index);
        writeJsonAtomic(content_item_path($type, $item['id']), $item);
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

function load_content_item(string $type, string $id): ?array
{
    $path = content_item_path($type, $id);
    $data = readJson($path);
    return $data ?: null;
}

function load_content_by_id(string $id): ?array
{
    $type = str_starts_with($id, 'NEWS-') ? 'news' : 'blog';
    return load_content_item($type, $id);
}

function load_content_by_slug(string $type, string $slug): ?array
{
    $index = load_content_index($type);
    foreach ($index as $row) {
        if ($row['slug'] === $slug && ($row['status'] ?? '') === 'published') {
            return load_content_item($type, $row['id']);
        }
    }
    return null;
}

function list_content(string $type, array $allowedStatuses = ['draft', 'published', 'scheduled']): array
{
    $index = load_content_index($type);
    $filtered = array_values(array_filter($index, function ($row) use ($allowedStatuses) {
        return in_array($row['status'] ?? '', $allowedStatuses, true);
    }));
    usort($filtered, function ($a, $b) {
        return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
    });
    return $filtered;
}

function ensure_slug_unique(string $type, string $candidate, string $id): string
{
    $slug = $candidate;
    $counter = 2;
    while (content_slug_exists($type, $slug, $id)) {
        $slug = $candidate . '-' . $counter;
        $counter++;
    }
    return $slug;
}

function content_job_path(string $jobId): string
{
    return CONTENT_BASE_PATH . '/jobs/' . $jobId . '.json';
}

function create_content_job(array $meta, ?string $jobId = null): string
{
    $jobId = $jobId ?: generate_unique_job_id();
    $path = content_job_path($jobId);
    if (file_exists($path)) {
        throw new RuntimeException('Job already exists for id ' . $jobId);
    }

    $typeRequested = in_array($meta['type'] ?? '', ['blog', 'news'], true) ? $meta['type'] : 'blog';
    $lengthRequested = in_array($meta['length'] ?? '', ['short', 'standard', 'long'], true) ? ($meta['length'] ?? null) : null;
    $nonce = $meta['nonce'] ?? strtoupper(bin2hex(random_bytes(6)));
    $createdAt = now_kolkata()->format(DateTime::ATOM);

    $payload = [
        'jobId' => $jobId,
        'status' => 'running',
        'chunks' => [],
        'resultContentId' => null,
        'errorText' => null,
        'meta' => array_merge($meta, [
            'typeRequested' => $typeRequested,
            'lengthRequested' => $lengthRequested,
            'nonce' => $nonce,
        ]),
        'processing' => false,
        'createdAt' => $createdAt,
        'startedAt' => $createdAt,
        'finishedAt' => null,
        'typeRequested' => $typeRequested,
        'lengthRequested' => $lengthRequested,
        'nonce' => $nonce,
    ];
    writeJsonAtomic($path, $payload);
    return $jobId;
}

function append_job_chunk(string $jobId, string $text): void
{
    $path = content_job_path($jobId);
    $job = readJson($path);
    if (!$job || ($job['jobId'] ?? '') !== $jobId) {
        return;
    }
    $job['chunks'][] = [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'text' => $text,
    ];
    writeJsonAtomic($path, $job);
}

function mark_job_processing(string $jobId): bool
{
    $path = content_job_path($jobId);
    $job = readJson($path);
    if (!$job || ($job['jobId'] ?? '') !== $jobId) {
        return false;
    }
    if (($job['processing'] ?? false) === true || ($job['status'] ?? '') !== 'running') {
        return false;
    }
    $job['processing'] = true;
    writeJsonAtomic($path, $job);
    return true;
}

function finalize_job(string $jobId, string $status, ?string $contentId = null, ?string $error = null, array $extra = []): void
{
    $path = content_job_path($jobId);
    $job = readJson($path);
    if (!$job || ($job['jobId'] ?? '') !== $jobId) {
        return;
    }
    $job['status'] = $status;
    $job['resultContentId'] = $contentId;
    $job['errorText'] = $error;
    $job['processing'] = false;
    $job['finishedAt'] = now_kolkata()->format(DateTime::ATOM);
    if (!empty($extra)) {
        $job = array_merge($job, $extra);
    }
    writeJsonAtomic($path, $job);
}

function update_job_meta(string $jobId, callable $updater): void
{
    $path = content_job_path($jobId);
    $job = readJson($path);
    if (!$job || ($job['jobId'] ?? '') !== $jobId) {
        return;
    }
    $job = $updater($job) ?? $job;
    writeJsonAtomic($path, $job);
}

function ai_generate_image(string $prompt, string $type, string $id): ?string
{
    $config = load_ai_config(true);
    $uploadDir = content_upload_dir($type, $id);
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $imageData = null;

    if (($config['provider'] ?? '') === 'openai' && ($config['apiKey'] ?? '')) {
        $payload = [
            'model' => $config['imageModel'],
            'prompt' => $prompt,
            'size' => '1024x1024',
            'response_format' => 'b64_json',
        ];
        $ch = curl_init('https://api.openai.com/v1/images/generations');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['apiKey'],
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($response !== false) {
            $decoded = json_decode((string)$response, true);
            if ($httpCode >= 200 && $httpCode < 300 && isset($decoded['data'][0]['b64_json'])) {
                $imageData = base64_decode((string)$decoded['data'][0]['b64_json']);
            } else {
                content_log(['event' => 'image_generation_failed', 'error' => $decoded['error']['message'] ?? ('HTTP ' . $httpCode)]);
            }
        } else {
            content_log(['event' => 'image_generation_failed', 'error' => $curlError]);
        }
    }

    if (!$imageData) {
        $img = imagecreatetruecolor(800, 480);
        if ($img !== false) {
            $bg = imagecolorallocate($img, 17, 24, 39);
            $fg = imagecolorallocate($img, 79, 70, 229);
            imagefilledrectangle($img, 0, 0, 800, 480, $bg);
            imagestring($img, 5, 24, 220, 'AI Cover: ' . substr($prompt, 0, 24), $fg);
            ob_start();
            imagepng($img);
            $imageData = ob_get_clean();
            imagedestroy($img);
        } else {
            $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/P3N3KQAAAABJRU5ErkJggg==');
        }
    }

    $filePath = $uploadDir . '/cover.png';
    file_put_contents($filePath, $imageData, LOCK_EX);
    return str_replace(PUBLIC_PATH, '', $filePath);
}

function ai_generate_content(array $meta): array
{
    $type = in_array($meta['type'] ?? '', ['blog', 'news'], true) ? $meta['type'] : 'blog';
    $prompt = trim($meta['prompt'] ?? '');
    $length = $meta['length'] ?? 'standard';
    $randomPlatform = (bool)($meta['randomPlatform'] ?? false);
    $nonce = $meta['nonce'] ?? strtoupper(bin2hex(random_bytes(6)));
    $variation = in_array($meta['variation'] ?? '', ['low', 'medium', 'high'], true) ? $meta['variation'] : 'high';
    $extraDirectives = array_values(array_filter($meta['extraDirectives'] ?? [], 'is_string'));

    $lengthMap = ['short' => 120, 'standard' => 240, 'long' => 360];
    $targetWords = $lengthMap[$length] ?? 240;

    $freshAngles = [
        'compliance', 'time-saving', 'document quality', 'contractor workflow',
        'department workflow', 'audit readiness', 'reminders', 'governance guardrails',
        'risk mitigation', 'collaboration', 'checklist discipline',
    ];
    $angle = $freshAngles[array_rand($freshAngles)];
    $variationText = [
        'low' => 'Keep tone steady but still introduce noticeably different phrasings.',
        'medium' => 'Rotate verbs, swap sentence patterns, and alter headings for freshness.',
        'high' => 'Boldly vary structure, voice, and examples. Avoid any phrasing overlap.',
    ][$variation] ?? 'Boldly vary structure, voice, and examples. Avoid any phrasing overlap.';

    $now = now_kolkata()->format('Y-m-d H:i T');
    $topicSeed = $prompt;

    if ($type === 'news' && $prompt === '' && $randomPlatform) {
        $platformTopics = [
            'smart tender reminders', 'workflow approvals', 'document clean-up helpers',
            'policy rollouts', 'audit-ready evidence lockers', 'contractor portal refresh',
            'schedule insights', 'compliance nudges', 'inbox declutter tips',
        ];
        $newsFormats = ['bulletin', 'quick hits', 'launch digest', 'field note', 'weekly recap'];
        $titleSeeds = ['Pulse', 'Radar', 'Spotlight', 'Signals', 'Briefing', 'Fast Track'];
        $topicSeed = $platformTopics[array_rand($platformTopics)];
        $prompt = 'Randomized platform news. Topic: ' . $topicSeed .
            '. Format: ' . $newsFormats[array_rand($newsFormats)] .
            '. Title seed: ' . $titleSeeds[array_rand($titleSeeds)] .
            '. Keep it fictional, upbeat, and focused on platform guidance only. Avoid real-world claims.';
    }

    $systemPrompt = "You are an assistant that generates clear HTML content with semantic paragraphs and headings. "
        . "Return JSON with keys: title, excerpt (<=35 words), bodyHtml (safe HTML). Do not include scripts or inline events. "
        . "Stay fictional and platform-focused.";

    $baseDirectives = [
        'Nonce: ' . $nonce,
        'Date (Asia/Kolkata): ' . $now,
        'Fresh angle: ' . $angle,
        'Do not reuse phrasing from the last 10 outputs.',
        'Use a new outline and fresh examples.',
        'Avoid repeating the same headings used recently.',
        'Always keep facts fictional and about internal platform guidance.',
        'Rewrite completely with new outline and new title when requested.',
    ];
    if ($extraDirectives) {
        $baseDirectives = array_merge($baseDirectives, $extraDirectives);
    }

    $typeSpecific = '';
    if ($type === 'blog') {
        $typeSpecific = "Blog template: opening hook, H2 for context, H2 for 3-5 practical tips with bullets, H2 for narrative example, H2 for wrap-up. "
            . "Write in modern, structured prose with smooth transitions. Add skimmable subheadings and concise bullet lists.";
    } else {
        $typeSpecific = "News template: choose " . $length . " length (short=concise bulletin, standard=memo-style, long=in-depth recap). "
            . "Use h2/h3 for sections like 'What changed', 'Why it matters', 'Fast takeaways', and a micro CTA. Prefer bullet points and short paragraphs.";
    }

    $userPrompt = implode("\n", [
        'Type: ' . $type,
        'Target words: ' . $targetWords,
        'Tone: modern, concise, trustworthy.',
        'Variation level: ' . $variation . ' — ' . $variationText,
        $typeSpecific,
        'Prompt/brief: ' . $prompt,
        'Fresh angle directive: Pick one new angle from: compliance, time-saving, document quality, contractor workflow, department workflow, audit readiness, reminders.',
        'Regeneration guardrails: Rewrite completely with new outline and new title. Do not reuse any headings. Use a different angle than before.',
        'Safety: keep everything fictional and internal-facing; do not claim real-world facts.',
        'Non-repetition: ' . implode(' ', $baseDirectives),
        'Include the nonce and angle in your reasoning to stay unique.',
    ]);

    $temperatureMap = ['low' => 0.3, 'medium' => 0.5, 'high' => 0.72];
    $temperature = $temperatureMap[$variation] ?? 0.72;

    $call = ai_call([
        'systemPrompt' => $systemPrompt,
        'userPrompt' => $userPrompt,
        'expectJson' => true,
        'purpose' => 'content_' . $type,
        'temperature' => $temperature,
        'maxTokens' => 1200,
    ]);

    $promptHash = hash('sha256', $systemPrompt . "\n" . $userPrompt);

    $provider = $call['rawEnvelope']['provider'] ?? ($call['provider'] ?? '');
    $model = $call['modelUsed'] ?? '';
    $parsedOk = (bool)($call['ok'] ?? false);

    if ($call['ok'] && is_array($call['json'])) {
        $data = $call['json'];
        $title = trim((string)($data['title'] ?? '')) ?: 'Untitled ' . ucfirst($type);
        $body = sanitize_body_html((string)($data['bodyHtml'] ?? ''));
        if ($body === '') {
            $body = '<p>' . sanitize($prompt !== '' ? $prompt : 'Generated ' . $type) . '</p>';
        }
        $excerpt = trim((string)($data['excerpt'] ?? ''));
        if ($excerpt === '') {
            $excerpt = content_excerpt($body, 35);
        }
        return [
            'title' => $title,
            'bodyHtml' => $body,
            'excerpt' => $excerpt,
            'promptHash' => $promptHash,
            'provider' => $provider,
            'model' => $model,
            'temperature' => $temperature,
            'parsedOk' => $parsedOk,
            'finalPrompt' => $systemPrompt . "\n\n" . $userPrompt,
        ];
    }

    $fallbackBody = '<p>' . sanitize($prompt !== '' ? $prompt : 'Fresh insights from YOJAK platform.') . '</p>';
    $fallbackBody .= '<p>This ' . $type . ' was created without an external AI provider. It highlights practical guidance, responsible usage, and encourages readers to explore more.</p>';
    return [
        'title' => 'YOJAK ' . ucfirst($type) . ' Update',
        'bodyHtml' => $fallbackBody,
        'excerpt' => content_excerpt($fallbackBody, 35),
        'promptHash' => $promptHash,
        'provider' => $provider,
        'model' => $model,
        'temperature' => $temperature,
        'parsedOk' => $parsedOk,
        'finalPrompt' => $systemPrompt . "\n\n" . $userPrompt,
    ];
}

function get_content_cron_token(): string
{
    $path = DATA_PATH . '/config/content_cron.token';
    if (!file_exists($path)) {
        $token = bin2hex(random_bytes(16));
        file_put_contents($path, $token, LOCK_EX);
    }
    return trim((string)file_get_contents($path));
}

function process_content_job(string $jobId, ?callable $emit = null): void
{
    $jobPath = content_job_path($jobId);
    $job = readJson($jobPath);
    if (!$job || ($job['status'] ?? '') !== 'running') {
        return;
    }

    $meta = $job['meta'] ?? [];
    $type = in_array($meta['type'] ?? '', ['blog', 'news'], true) ? $meta['type'] : 'blog';
    $prompt = trim((string)($meta['prompt'] ?? ''));
    $contentId = is_string($meta['contentId'] ?? null) ? (string)$meta['contentId'] : generate_unique_content_id($type);
    $length = in_array($meta['length'] ?? '', ['short', 'standard', 'long'], true) ? $meta['length'] : 'standard';
    $randomPlatform = (bool)($meta['randomPlatform'] ?? false);
    $nonce = $meta['nonce'] ?? strtoupper(bin2hex(random_bytes(6)));
    $variation = in_array($meta['variation'] ?? '', ['low', 'medium', 'high'], true) ? $meta['variation'] : 'high';

    $send = function (string $text) use ($jobId, $emit): void {
        append_job_chunk($jobId, $text);
        if ($emit) {
            $emit($text);
        }
    };

    if (content_id_exists($type, $contentId)) {
        $contentId = generate_unique_content_id($type);
        update_job_meta($jobId, function (array $job) use ($contentId): array {
            $job['meta']['contentId'] = $contentId;
            return $job;
        });
        $send('Content ID collision avoided. Using ' . $contentId . '.');
    }

    $send('Starting generation for ' . strtoupper($type) . '...');

    $generated = [];
    try {
        $generated = ai_generate_content([
            'type' => $type,
            'prompt' => $prompt,
            'length' => $length,
            'randomPlatform' => $randomPlatform,
            'nonce' => $nonce,
            'variation' => $variation,
            'jobId' => $jobId,
            'contentId' => $contentId,
        ]);
        $title = $generated['title'];
        $body = $generated['bodyHtml'];
        $excerpt = content_excerpt($generated['excerpt'] ?? $body);
        $signature = build_content_signature($body);
        $pool = load_recent_content_pool($type, 20, 10, $contentId);
        $dupCheck = evaluate_duplicate($signature, $pool);

        $duplicationMeta = [
            'dupDetected' => ($dupCheck['duplicate'] ?? false) || ($dupCheck['nearDuplicate'] ?? false),
            'dupFlag' => false,
            'dupOfContentId' => $dupCheck['matchId'] ?? null,
            'dupMatchType' => $dupCheck['matchType'] ?? null,
            'dupBasis' => $dupCheck['basis'] ?? null,
            'similarityScore' => $dupCheck['score'] ?? 0.0,
            'regenAttempted' => false,
            'regenAuto' => false,
        ];

        if ($duplicationMeta['dupDetected']) {
            $duplicationMeta['regenAttempted'] = true;
            $duplicationMeta['regenAuto'] = true;
            $send('Draft looks similar to recent content. Regenerating once with stronger variation...');
            content_log([
                'event' => 'duplication_detected',
                'jobId' => $jobId,
                'contentId' => $contentId,
                'type' => $type,
                'dupDetected' => true,
                'dupOfContentId' => $duplicationMeta['dupOfContentId'],
                'dupBasis' => $duplicationMeta['dupBasis'],
                'similarityScore' => $duplicationMeta['similarityScore'],
                'regenAttempted' => true,
            ]);

            $nonce = strtoupper(bin2hex(random_bytes(6)));
            $regenerated = ai_generate_content([
                'type' => $type,
                'prompt' => $prompt,
                'length' => $length,
                'randomPlatform' => $randomPlatform,
                'nonce' => $nonce,
                'variation' => 'high',
                'jobId' => $jobId,
                'contentId' => $contentId,
                'extraDirectives' => [
                    'Rewrite completely with new outline and new title.',
                    'Do not reuse any headings.',
                    'Use a different angle.',
                    'Swap to fresh examples and a new CTA to avoid repetition.',
                ],
            ]);
            $generated = $regenerated;
            $title = $generated['title'];
            $body = $generated['bodyHtml'];
            $excerpt = content_excerpt($generated['excerpt'] ?? $body);
            $signature = build_content_signature($body);
            $dupCheck = evaluate_duplicate($signature, $pool);
            $duplicationMeta['dupFlag'] = ($dupCheck['duplicate'] ?? false) || ($dupCheck['nearDuplicate'] ?? false);
            if ($duplicationMeta['dupFlag']) {
                $duplicationMeta['dupOfContentId'] = $dupCheck['matchId'] ?? $duplicationMeta['dupOfContentId'];
                $duplicationMeta['dupMatchType'] = $dupCheck['matchType'] ?? $duplicationMeta['dupMatchType'];
                $duplicationMeta['dupBasis'] = $dupCheck['basis'] ?? $duplicationMeta['dupBasis'];
                $duplicationMeta['similarityScore'] = $dupCheck['score'] ?? $duplicationMeta['similarityScore'];
                $send('Output still resembles recent content. Marking draft with a warning banner.');
            } else {
                $duplicationMeta['dupDetected'] = true;
                $duplicationMeta['dupOfContentId'] = null;
                $duplicationMeta['dupBasis'] = null;
                $duplicationMeta['similarityScore'] = 0.0;
                $send('Fresh draft generated after regeneration.');
            }
        }

        content_log([
            'event' => 'duplication_result',
            'jobId' => $jobId,
            'contentId' => $contentId,
            'type' => $type,
            'dupDetected' => $duplicationMeta['dupDetected'],
            'dupFlag' => $duplicationMeta['dupFlag'],
            'dupOfContentId' => $duplicationMeta['dupOfContentId'],
            'dupBasis' => $duplicationMeta['dupBasis'],
            'similarityScore' => $duplicationMeta['similarityScore'],
            'regenAttempted' => $duplicationMeta['regenAttempted'],
        ]);

        $slugCandidate = content_slugify($title, $contentId);
        $slug = ensure_slug_unique($type, $slugCandidate, $contentId);
        $now = now_kolkata()->format(DateTime::ATOM);

        $send('Creating draft item...');

        $coverPath = ai_generate_image($title . ' ' . $prompt, $type, $contentId);

        $item = [
            'id' => $contentId,
            'type' => $type,
            'lang' => 'en',
            'title' => $title,
            'slug' => $slug,
            'status' => 'draft',
            'promptUsed' => $prompt,
            'bodyHtml' => $body,
            'excerpt' => $excerpt,
            'coverImagePath' => $coverPath,
            'createdAt' => $now,
            'updatedAt' => $now,
            'publishAt' => null,
            'publishedAt' => null,
            'generation' => [
                'jobId' => $jobId,
                'typeRequested' => $type,
                'lengthRequested' => $type === 'news' ? $length : null,
                'nonce' => $nonce,
                'promptHash' => $generated['promptHash'],
                'outputHash' => $signature['outputHash'],
                'provider' => $generated['provider'],
                'model' => $generated['model'],
                'temperature' => $generated['temperature'],
                'createdAt' => $now,
                'dupDetected' => $duplicationMeta['dupDetected'],
                'dupFlag' => $duplicationMeta['dupFlag'],
                'dupOfContentId' => $duplicationMeta['dupOfContentId'],
                'dupBasis' => $duplicationMeta['dupBasis'],
                'similarityScore' => $duplicationMeta['similarityScore'],
                'regenAttempted' => $duplicationMeta['regenAttempted'],
                'regenAuto' => $duplicationMeta['regenAuto'],
            ],
        ];

        save_content_item($item);
        $outputHash = $item['generation']['outputHash'];
        content_log([
            'event' => 'content_generated',
            'jobId' => $jobId,
            'id' => $contentId,
            'contentId' => $contentId,
            'type' => $type,
            'outputHash' => $outputHash,
            'length' => $type === 'news' ? $length : null,
            'nonce' => $nonce,
            'promptHash' => $generated['promptHash'],
            'provider' => $generated['provider'],
            'model' => $generated['model'],
            'temperature' => $generated['temperature'],
            'parsedOk' => $generated['parsedOk'],
            'dupDetected' => $duplicationMeta['dupDetected'],
            'dupFlag' => $duplicationMeta['dupFlag'],
            'dupOfContentId' => $duplicationMeta['dupOfContentId'],
            'dupBasis' => $duplicationMeta['dupBasis'],
            'similarityScore' => $duplicationMeta['similarityScore'],
            'regenAttempted' => $duplicationMeta['regenAttempted'],
        ]);
        content_log([
            'event' => 'GEN_DONE',
            'jobId' => $jobId,
            'contentId' => $contentId,
            'outputHash' => $outputHash,
            'type' => $type,
            'length' => $type === 'news' ? $length : null,
            'nonce' => $nonce,
            'promptHash' => $generated['promptHash'],
            'provider' => $generated['provider'],
            'model' => $generated['model'],
            'temperature' => $generated['temperature'],
            'parsedOk' => $generated['parsedOk'],
            'dupDetected' => $duplicationMeta['dupDetected'],
            'dupFlag' => $duplicationMeta['dupFlag'],
            'dupOfContentId' => $duplicationMeta['dupOfContentId'],
            'dupBasis' => $duplicationMeta['dupBasis'],
            'similarityScore' => $duplicationMeta['similarityScore'],
            'regenAttempted' => $duplicationMeta['regenAttempted'],
        ]);

        finalize_job($jobId, 'done', $contentId, null, [
            'generation' => [
                'contentId' => $contentId,
                'promptHash' => $generated['promptHash'],
                'outputHash' => $outputHash,
                'provider' => $generated['provider'],
                'model' => $generated['model'],
                'temperature' => $generated['temperature'],
                'nonce' => $nonce,
                'typeRequested' => $type,
                'lengthRequested' => $type === 'news' ? $length : null,
                'parsedOk' => $generated['parsedOk'],
                'promptText' => $generated['finalPrompt'],
                'dupDetected' => $duplicationMeta['dupDetected'],
                'dupFlag' => $duplicationMeta['dupFlag'],
                'dupOfContentId' => $duplicationMeta['dupOfContentId'],
                'dupBasis' => $duplicationMeta['dupBasis'],
                'similarityScore' => $duplicationMeta['similarityScore'],
                'regenAttempted' => $duplicationMeta['regenAttempted'],
            ],
        ]);
        $send('Draft created. Ready to edit.');
    } catch (Throwable $e) {
        $message = 'Generation failed: ' . $e->getMessage();
        content_log([
            'event' => 'content_generation_error',
            'jobId' => $jobId,
            'type' => $type,
            'length' => $type === 'news' ? $length : null,
            'contentId' => $contentId,
            'nonce' => $nonce,
            'promptHash' => $generated['promptHash'] ?? null,
            'provider' => $generated['provider'] ?? null,
            'model' => $generated['model'] ?? null,
            'parsedOk' => $generated['parsedOk'] ?? false,
            'error' => $message,
        ]);
        finalize_job($jobId, 'error', null, $message);
        $send($message);
    }
}
