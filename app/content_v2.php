<?php
declare(strict_types=1);

const CONTENT_V2_BASE = DATA_PATH . '/content_v2';
const CONTENT_V2_LOG_FILE = DATA_PATH . '/logs/content_v2.log';

function ensure_content_v2_structure(): void
{
    $directories = [
        CONTENT_V2_BASE,
        CONTENT_V2_BASE . '/topics',
        CONTENT_V2_BASE . '/topics/blog',
        CONTENT_V2_BASE . '/topics/news',
        CONTENT_V2_BASE . '/jobs',
        CONTENT_V2_BASE . '/jobs/topic',
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $indexFiles = [
        CONTENT_V2_BASE . '/topics/blog/index.json',
        CONTENT_V2_BASE . '/topics/news/index.json',
    ];
    foreach ($indexFiles as $file) {
        if (!file_exists($file)) {
            writeJsonAtomic($file, []);
        }
    }

    if (!file_exists(CONTENT_V2_LOG_FILE)) {
        touch(CONTENT_V2_LOG_FILE);
    }
}

function content_v2_log(array $context): void
{
    logEvent(CONTENT_V2_LOG_FILE, $context);
}

function topic_v2_index_path(string $type): string
{
    return CONTENT_V2_BASE . '/topics/' . $type . '/index.json';
}

function topic_v2_item_path(string $type, string $topicId): string
{
    return CONTENT_V2_BASE . '/topics/' . $type . '/' . $topicId . '.json';
}

function topic_v2_index_lock_path(string $type): string
{
    return CONTENT_V2_BASE . '/topics/' . $type . '/index.lock';
}

function topic_v2_job_path(string $jobId): string
{
    return CONTENT_V2_BASE . '/jobs/topic/' . $jobId . '.json';
}

function topic_v2_load_index(string $type): array
{
    return readJson(topic_v2_index_path($type));
}

function topic_v2_save_index(string $type, array $index): void
{
    writeJsonAtomic(topic_v2_index_path($type), $index);
}

function topic_v2_generate_topic_id(): string
{
    do {
        $topicId = 'TOP-' . strtoupper(bin2hex(random_bytes(3)));
    } while (file_exists(topic_v2_item_path('blog', $topicId)) || file_exists(topic_v2_item_path('news', $topicId)));

    return $topicId;
}

function topic_v2_generate_job_id(): string
{
    do {
        $jobId = 'JOB-' . strtoupper(bin2hex(random_bytes(4)));
    } while (file_exists(topic_v2_job_path($jobId)));

    return $jobId;
}

function topic_v2_normalize_title(string $title): string
{
    $normalized = strtolower($title);
    $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    return trim((string)$normalized);
}

function topic_v2_parse_keywords($input): array
{
    $keywords = [];
    if (is_string($input)) {
        $parts = preg_split('/[,;]/', $input) ?: [];
    } elseif (is_array($input)) {
        $parts = $input;
    } else {
        $parts = [];
    }

    foreach ($parts as $part) {
        $word = trim((string)$part);
        if ($word === '') {
            continue;
        }
        $keywords[] = $word;
    }

    $keywords = array_values(array_unique($keywords));
    return array_slice($keywords, 0, 10);
}

function topic_v2_save_record(array $record): void
{
    $type = $record['type'] ?? '';
    if (!in_array($type, ['blog', 'news'], true)) {
        throw new InvalidArgumentException('Invalid topic type.');
    }

    $lockHandle = fopen(topic_v2_index_lock_path($type), 'c');
    if ($lockHandle === false) {
        throw new RuntimeException('Unable to open topic index lock.');
    }
    if (!flock($lockHandle, LOCK_EX)) {
        fclose($lockHandle);
        throw new RuntimeException('Unable to acquire topic index lock.');
    }

    try {
        $index = topic_v2_load_index($type);
        if (!is_array($index)) {
            $index = [];
        }

        $summary = [
            'topicId' => $record['topicId'],
            'type' => $type,
            'topicTitle' => $record['topicTitle'],
            'status' => $record['status'],
            'createdAt' => $record['createdAt'],
            'deletedAt' => $record['deletedAt'],
        ];

        $updated = false;
        foreach ($index as &$row) {
            if (($row['topicId'] ?? '') === $record['topicId']) {
                $row = $summary;
                $updated = true;
                break;
            }
        }
        unset($row);

        if (!$updated) {
            $index[] = $summary;
        }

        topic_v2_save_index($type, $index);
        writeJsonAtomic(topic_v2_item_path($type, $record['topicId']), $record);
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

function topic_v2_load_record(string $type, string $topicId): ?array
{
    if (!in_array($type, ['blog', 'news'], true)) {
        return null;
    }
    $data = readJson(topic_v2_item_path($type, $topicId));
    return $data ?: null;
}

function topic_v2_list(string $type): array
{
    $index = topic_v2_load_index($type);
    $filtered = array_values(array_filter($index, function ($row) {
        return ($row['deletedAt'] ?? null) === null;
    }));
    usort($filtered, function ($a, $b) {
        return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
    });
    return $filtered;
}

function topic_v2_build_prompts(string $type, string $prompt, ?string $newsLength, int $count, string $nonce): array
{
    $baseGuardrails = 'Keep everything fictional, platform-safe, and focused on workflow guidance for Jharkhand government contractors. '
        . 'Do not make real-world claims. Ensure each topic title is unique (no near-duplicates) and 10-120 characters.';

    $systemPrompt = 'You generate unique topic ideas and return JSON only. '
        . 'Schema: {"topics":[{"title":"string","angle":"string","keywords":["a","b","c"]}]}. '
        . 'Avoid markdown, avoid numbering, avoid extra text. Keep keywords concise.';

    $tonePrompt = $type === 'news'
        ? 'Style: bulletin-style news/update, concise, platform tips. Prefer short headlines with a quick angle. '
            . 'Include micro-updates and feature highlights. News length request: ' . ($newsLength ?: 'standard') . '.'
        : 'Style: practical how-to/guide topics for longer-form blogs. Include structure-oriented angles that imply steps or frameworks.';

    $promptBody = implode("\n", [
        'Type: ' . $type,
        'Requested topics: ' . $count,
        'Nonce: ' . $nonce,
        $tonePrompt,
        $baseGuardrails,
        $prompt !== '' ? 'Admin prompt: ' . $prompt : 'Admin prompt not provided. Generate platform-safe internal tips, insights, and workflow guidance without real-world facts.',
        'Return between 4 and 7 diverse options. Avoid repeating wording.',
    ]);

    $promptHash = hash('sha256', $systemPrompt . "\n" . $promptBody);

    return [
        'systemPrompt' => $systemPrompt,
        'userPrompt' => $promptBody,
        'promptHash' => $promptHash,
    ];
}

function topic_v2_parse_results($json, int $count): array
{
    $rawItems = [];
    if (is_array($json)) {
        if (isset($json['topics']) && is_array($json['topics'])) {
            $rawItems = $json['topics'];
        } elseif (isset($json[0])) {
            $rawItems = $json;
        }
    }

    $parsed = [];
    $seen = [];
    foreach ($rawItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = trim((string)($item['title'] ?? ($item['topicTitle'] ?? ($item['topic'] ?? ''))));
        if ($title === '' || strlen($title) < 10 || strlen($title) > 120) {
            continue;
        }
        $normalized = topic_v2_normalize_title($title);
        if ($normalized === '' || isset($seen[$normalized])) {
            continue;
        }

        $angle = trim((string)($item['angle'] ?? ($item['topicAngle'] ?? '')));
        $keywords = topic_v2_parse_keywords($item['keywords'] ?? []);

        $parsed[] = [
            'topicTitle' => $title,
            'topicAngle' => $angle,
            'keywords' => $keywords,
        ];
        $seen[$normalized] = true;

        if (count($parsed) >= $count) {
            break;
        }
    }

    return $parsed;
}

function topic_v2_soft_delete(string $type, string $topicId): bool
{
    $record = topic_v2_load_record($type, $topicId);
    if (!$record) {
        return false;
    }

    $now = now_kolkata()->format(DateTime::ATOM);
    $record['deletedAt'] = $now;
    $record['status'] = 'deleted';
    $record['updatedAt'] = $now;
    topic_v2_save_record($record);
    return true;
}
