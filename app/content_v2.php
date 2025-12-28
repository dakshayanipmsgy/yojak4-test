<?php
declare(strict_types=1);

const CONTENT_V2_BASE = DATA_PATH . '/content_v2';
const CONTENT_V2_LOG_FILE = DATA_PATH . '/logs/content_v2.log';
const CONTENT_V2_DEBUG_DIR = CONTENT_V2_BASE . '/debug/raw_responses';

function ensure_content_v2_structure(): void
{
    $directories = [
        CONTENT_V2_BASE,
        CONTENT_V2_BASE . '/topics',
        CONTENT_V2_BASE . '/topics/blog',
        CONTENT_V2_BASE . '/topics/news',
        CONTENT_V2_BASE . '/jobs',
        CONTENT_V2_BASE . '/jobs/topic',
        CONTENT_V2_BASE . '/jobs/content',
        CONTENT_V2_BASE . '/drafts',
        CONTENT_V2_BASE . '/drafts/blog',
        CONTENT_V2_BASE . '/drafts/news',
        CONTENT_V2_DEBUG_DIR,
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $indexFiles = [
        CONTENT_V2_BASE . '/topics/blog/index.json',
        CONTENT_V2_BASE . '/topics/news/index.json',
        CONTENT_V2_BASE . '/drafts/blog/index.json',
        CONTENT_V2_BASE . '/drafts/news/index.json',
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

function content_v2_raw_response_path(string $jobId): string
{
    return CONTENT_V2_DEBUG_DIR . '/' . $jobId . '.json';
}

function content_v2_save_raw_response(string $jobId, array $payload): void
{
    $payload['savedAt'] = now_kolkata()->format(DateTime::ATOM);
    $payload['rawSnippet'] = function_exists('mb_substr')
        ? mb_substr((string)($payload['rawSnippet'] ?? ''), 0, 800, 'UTF-8')
        : substr((string)($payload['rawSnippet'] ?? ''), 0, 800);
    writeJsonAtomic(content_v2_raw_response_path($jobId), $payload);
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
            'newsLength' => $record['newsLength'] ?? null,
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

function content_v2_draft_index_path(string $type): string
{
    return CONTENT_V2_BASE . '/drafts/' . $type . '/index.json';
}

function content_v2_draft_item_path(string $type, string $contentId): string
{
    return CONTENT_V2_BASE . '/drafts/' . $type . '/' . $contentId . '.json';
}

function content_v2_draft_lock_path(string $type): string
{
    return CONTENT_V2_BASE . '/drafts/' . $type . '/index.lock';
}

function content_v2_job_path(string $jobId): string
{
    return CONTENT_V2_BASE . '/jobs/content/' . $jobId . '.json';
}

function content_v2_load_draft_index(string $type): array
{
    return readJson(content_v2_draft_index_path($type));
}

function content_v2_save_draft_index(string $type, array $index): void
{
    writeJsonAtomic(content_v2_draft_index_path($type), $index);
}

function content_v2_generate_content_id(string $type): string
{
    $prefix = $type === 'news' ? 'NEWS-' : 'BLOG-';
    do {
        $contentId = $prefix . strtoupper(bin2hex(random_bytes(3)));
    } while (file_exists(content_v2_draft_item_path('blog', $contentId)) || file_exists(content_v2_draft_item_path('news', $contentId)));

    return $contentId;
}

function content_v2_generate_job_id(): string
{
    do {
        $jobId = 'JOB-' . strtoupper(bin2hex(random_bytes(4)));
    } while (file_exists(content_v2_job_path($jobId)));

    return $jobId;
}

function content_v2_slugify(string $title): string
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = substr((string)$slug, 0, 80);
    $slug = trim((string)$slug, '-');
    if ($slug === '' || strlen($slug) < 3) {
        $slug = strtolower(substr(bin2hex(random_bytes(3)), 0, 6));
    }
    return $slug;
}

function content_v2_slug_exists(string $type, string $slug, ?string $excludeId = null): bool
{
    $index = content_v2_load_draft_index($type);
    foreach ($index as $row) {
        if (($row['slug'] ?? '') === $slug && ($row['contentId'] ?? '') !== $excludeId && ($row['deletedAt'] ?? null) === null) {
            return true;
        }
    }
    return false;
}

function content_v2_unique_slug(string $type, string $title, ?string $preferred, ?string $excludeId = null): string
{
    $base = $preferred !== '' ? $preferred : $title;
    $slug = content_v2_slugify($base);
    if (!content_v2_slug_exists($type, $slug, $excludeId)) {
        return $slug;
    }
    $suffix = 2;
    while (true) {
        $candidate = substr($slug, 0, max(0, 80 - strlen((string)$suffix) - 1)) . '-' . $suffix;
        if (!content_v2_slug_exists($type, $candidate, $excludeId)) {
            return $candidate;
        }
        $suffix++;
    }
}

function content_v2_save_draft(array $record, bool $allowUpdate = false): void
{
    $type = $record['type'] ?? '';
    if (!in_array($type, ['blog', 'news'], true)) {
        throw new InvalidArgumentException('Invalid draft type.');
    }
    $contentId = $record['contentId'] ?? '';
    if ($contentId === '') {
        throw new InvalidArgumentException('Missing contentId.');
    }

    $lockHandle = fopen(content_v2_draft_lock_path($type), 'c');
    if ($lockHandle === false) {
        throw new RuntimeException('Unable to open draft index lock.');
    }
    if (!flock($lockHandle, LOCK_EX)) {
        fclose($lockHandle);
        throw new RuntimeException('Unable to acquire draft index lock.');
    }

    try {
        $index = content_v2_load_draft_index($type);
        if (!is_array($index)) {
            $index = [];
        }

        $existingIndexKey = null;
        foreach ($index as $idx => $row) {
            if (($row['contentId'] ?? '') === $contentId) {
                $existingIndexKey = $idx;
                break;
            }
        }

        if ($existingIndexKey !== null && !$allowUpdate) {
            throw new RuntimeException('Draft already exists.');
        }
        if ($existingIndexKey === null && file_exists(content_v2_draft_item_path($type, $contentId))) {
            throw new RuntimeException('Draft file already exists.');
        }

        $summary = [
            'contentId' => $contentId,
            'type' => $type,
            'title' => $record['title'] ?? '',
            'slug' => $record['slug'] ?? '',
            'status' => $record['status'] ?? 'draft',
            'createdAt' => $record['createdAt'] ?? now_kolkata()->format(DateTime::ATOM),
            'updatedAt' => $record['updatedAt'] ?? $record['createdAt'] ?? now_kolkata()->format(DateTime::ATOM),
            'deletedAt' => $record['deletedAt'] ?? null,
        ];

        if ($existingIndexKey !== null) {
            $index[$existingIndexKey] = $summary;
        } else {
            $index[] = $summary;
        }

        content_v2_save_draft_index($type, $index);
        writeJsonAtomic(content_v2_draft_item_path($type, $contentId), $record);
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

function content_v2_load_draft(string $type, string $contentId): ?array
{
    if (!in_array($type, ['blog', 'news'], true)) {
        return null;
    }
    $data = readJson(content_v2_draft_item_path($type, $contentId));
    return $data ?: null;
}

function content_v2_list_drafts(string $type): array
{
    $index = content_v2_load_draft_index($type);
    $filtered = array_values(array_filter($index, function ($row) {
        return ($row['deletedAt'] ?? null) === null;
    }));
    usort($filtered, function ($a, $b) {
        return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
    });
    return $filtered;
}

function content_v2_mark_topic_used(string $type, string $topicId): void
{
    $topic = topic_v2_load_record($type, $topicId);
    if (!$topic) {
        return;
    }
    $now = now_kolkata()->format(DateTime::ATOM);
    $topic['status'] = 'used';
    $topic['updatedAt'] = $now;
    topic_v2_save_record($topic);
}

function content_v2_build_generation_prompt(string $type, array $topic, array $overrides, string $nonce): array
{
    $now = now_kolkata()->format('Y-m-d H:i T');
    $baseTitle = trim((string)($overrides['customTitle'] ?? ''));
    $topicTitle = $baseTitle !== '' ? $baseTitle : ($topic['topicTitle'] ?? '');
    if ($topicTitle === '') {
        $topicTitle = 'Untitled ' . ucfirst($type) . ' Topic';
    }
    $tone = trim((string)($overrides['tone'] ?? 'modern, confident, concise'));
    $newsLength = $type === 'news' ? ($overrides['newsLength'] ?? ($topic['newsLength'] ?? 'standard')) : null;
    $newsLength = in_array($newsLength, ['short', 'standard', 'long'], true) ? $newsLength : 'standard';

    $systemPrompt = 'You are an assistant that returns STRICT JSON only: {"title":"string","excerpt":"<=40 words","bodyHtml":"HTML"} with no code fences. '
        . 'Sanitize output: no scripts, inline events, or unsafe URLs. Always use fresh wording and a new outline.';

    $directives = [
        'Nonce: ' . $nonce,
        'Current date (Asia/Kolkata): ' . $now,
        'Do not reuse wording or headings from previous outputs.',
        'Use a new outline and different phrasing.',
        'Keep everything fictional, internal-facing, and platform-safe.',
    ];

    if ($type === 'blog') {
        $template = 'Blog structure: intro hook, H2 sections for context, H2 with 3-5 practical tips (bulleted), H2 narrative example, H2 wrap-up. '
            . 'End with an ordered checklist titled "Checklist" containing 4-6 items.';
    } else {
        $template = 'News structure: start with a clear headline, then 4-7 bullet points covering what changed, why it matters, and fast takeaways. '
            . 'Close with a short 2-3 sentence conclusion. Adjust density for length = ' . $newsLength . '.';
    }

    $topicInfo = implode("\n", array_filter([
        'Topic title: ' . $topicTitle,
        !empty($topic['topicAngle']) ? 'Angle: ' . $topic['topicAngle'] : '',
        !empty($topic['audience']) ? 'Audience: ' . $topic['audience'] : 'Audience: Jharkhand government contractors',
        !empty($topic['keywords']) ? 'Keywords: ' . implode(', ', $topic['keywords']) : '',
    ]));

    $userPrompt = implode("\n", [
        'Type: ' . $type,
        'Tone: ' . $tone,
        $type === 'news' ? 'News length: ' . $newsLength : 'Blog length: standard',
        'Topic seed: ' . $topicInfo,
        $template,
        'Body requirements: semantic HTML with <p>, <h2>, <h3>, <ul>/<ol>. No scripts or inline events.',
        'Checklist: ' . ($type === 'blog' ? 'Include a final Checklist section with actionable items.' : 'Not required for news unless it helps clarity.'),
        implode(' ', $directives),
        'Do not include notes outside JSON.',
    ]);

    $promptHash = hash('sha256', $systemPrompt . "\n" . $userPrompt);

    return [
        'systemPrompt' => $systemPrompt,
        'userPrompt' => $userPrompt,
        'promptHash' => $promptHash,
        'newsLength' => $newsLength,
        'topicTitle' => $topicTitle,
    ];
}
