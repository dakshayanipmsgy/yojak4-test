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
    $counter = 1;
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
    $payload = [
        'jobId' => $jobId,
        'status' => 'running',
        'chunks' => [],
        'resultContentId' => null,
        'errorText' => null,
        'meta' => $meta,
        'processing' => false,
        'createdAt' => now_kolkata()->format(DateTime::ATOM),
    ];
    writeJsonAtomic(content_job_path($jobId), $payload);
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

function finalize_job(string $jobId, string $status, ?string $contentId = null, ?string $error = null): void
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
    $type = $meta['type'];
    $prompt = trim($meta['prompt'] ?? '');
    $length = $meta['length'] ?? 'standard';
    $randomPlatform = (bool)($meta['randomPlatform'] ?? false);

    $lengthMap = ['short' => 120, 'standard' => 240, 'long' => 360];
    $targetWords = $lengthMap[$length] ?? 240;

    $systemPrompt = "You are an assistant that generates clear HTML content with semantic paragraphs and headings. Return JSON with keys: title, excerpt (<=35 words), bodyHtml (safe HTML). Do not include scripts.";

    if ($type === 'news' && $prompt === '' && $randomPlatform) {
        $prompt = 'Share platform tips, roadmap teasers, or feature spotlights in a fictional tone. Avoid real-world claims. Keep it optimistic and helpful.';
    }

    $userPrompt = 'Type: ' . $type . "\n" .
        'Target words: ' . $targetWords . "\n" .
        'Tone: modern, concise, trustworthy. ' .
        'Use HTML paragraphs and h2/h3 headings. ' .
        'Never mention confidential data.' . "\n" .
        'Prompt: ' . $prompt;

    $call = ai_call([
        'systemPrompt' => $systemPrompt,
        'userPrompt' => $userPrompt,
        'expectJson' => true,
        'purpose' => 'content_' . $type,
    ]);

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
        ];
    }

    $fallbackBody = '<p>' . sanitize($prompt !== '' ? $prompt : 'Fresh insights from YOJAK platform.') . '</p>';
    $fallbackBody .= '<p>This ' . $type . ' was created without an external AI provider. It highlights practical guidance, responsible usage, and encourages readers to explore more.</p>';
    return [
        'title' => 'YOJAK ' . ucfirst($type) . ' Update',
        'bodyHtml' => $fallbackBody,
        'excerpt' => content_excerpt($fallbackBody, 35),
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

    try {
        $generated = ai_generate_content($meta);
        $title = $generated['title'];
        $body = $generated['bodyHtml'];
        $excerpt = content_excerpt($generated['excerpt']);

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
        ];

        save_content_item($item);
        $outputHash = hash('sha256', $body);
        content_log([
            'event' => 'content_generated',
            'jobId' => $jobId,
            'id' => $contentId,
            'type' => $type,
            'outputHash' => $outputHash,
        ]);
        content_log([
            'event' => 'GEN_DONE',
            'jobId' => $jobId,
            'contentId' => $contentId,
            'outputHash' => $outputHash,
            'type' => $type,
        ]);

        finalize_job($jobId, 'done', $contentId, null);
        $send('Draft created. Ready to edit.');
    } catch (Throwable $e) {
        $message = 'Generation failed: ' . $e->getMessage();
        content_log(['event' => 'content_generation_error', 'jobId' => $jobId, 'error' => $message]);
        finalize_job($jobId, 'error', null, $message);
        $send($message);
    }
}
