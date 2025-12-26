<?php
declare(strict_types=1);

const TENDER_DISCOVERY_LOG = DATA_PATH . '/logs/tender_discovery.log';

function tender_discovery_sources_path(): string
{
    return DATA_PATH . '/discovery/sources.json';
}

function tender_discovery_state_path(): string
{
    return DATA_PATH . '/discovery/state.json';
}

function tender_discovery_index_path(): string
{
    return DATA_PATH . '/discovery/discovered/index.json';
}

function tender_discovery_discovered_path(string $discId): string
{
    return DATA_PATH . '/discovery/discovered/' . $discId . '.json';
}

function ensure_tender_discovery_env(): void
{
    $directories = [
        DATA_PATH . '/discovery',
        DATA_PATH . '/discovery/discovered',
        DATA_PATH . '/discovery/discovered_deleted',
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    if (!file_exists(tender_discovery_sources_path())) {
        writeJsonAtomic(tender_discovery_sources_path(), []);
    }

    if (!file_exists(tender_discovery_index_path())) {
        writeJsonAtomic(tender_discovery_index_path(), []);
    }

    if (!file_exists(tender_discovery_state_path())) {
        $defaultState = [
            'lastRunAt' => null,
            'lastHash' => null,
            'cronToken' => 'TD-' . bin2hex(random_bytes(8)),
            'lastSummary' => null,
        ];
        writeJsonAtomic(tender_discovery_state_path(), $defaultState);
    }

    if (!file_exists(TENDER_DISCOVERY_LOG)) {
        touch(TENDER_DISCOVERY_LOG);
    }
}

function tender_discovery_log(array $context): void
{
    logEvent(TENDER_DISCOVERY_LOG, $context);
}

function tender_discovery_sources(): array
{
    ensure_tender_discovery_env();
    $data = readJson(tender_discovery_sources_path());
    $sources = is_array($data) ? array_values($data) : [];
    return array_values(array_filter($sources, function ($src) {
        return !empty($src['sourceId']) && !empty($src['name']) && !empty($src['url']);
    }));
}

function tender_discovery_save_sources(array $sources): void
{
    ensure_tender_discovery_env();
    $cleaned = [];
    foreach ($sources as $source) {
        $name = trim((string)($source['name'] ?? ''));
        $url = trim((string)($source['url'] ?? ''));
        $type = $source['type'] ?? '';
        $active = !empty($source['active']);
        $sourceId = trim((string)($source['sourceId'] ?? ''));

        if ($name === '' || $url === '' || !in_array($type, ['rss', 'html', 'json'], true)) {
            continue;
        }

        if (!preg_match('/^https?:\/\//i', $url)) {
            continue;
        }

        if ($sourceId === '') {
            $sourceId = 'SRC-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        }

        $parseHintsRaw = $source['parseHints'] ?? null;
        $parseHints = [];
        if (is_string($parseHintsRaw) && trim($parseHintsRaw) !== '') {
            $decoded = json_decode($parseHintsRaw, true);
            if (is_array($decoded)) {
                $parseHints = $decoded;
            }
        } elseif (is_array($parseHintsRaw)) {
            $parseHints = $parseHintsRaw;
        }

        $cleaned[] = [
            'sourceId' => $sourceId,
            'name' => $name,
            'type' => $type,
            'url' => $url,
            'active' => $active,
            'parseHints' => $parseHints,
        ];
    }

    writeJsonAtomic(tender_discovery_sources_path(), $cleaned);
}

function tender_discovery_state(): array
{
    ensure_tender_discovery_env();
    $state = readJson(tender_discovery_state_path());
    if (!isset($state['cronToken']) || !is_string($state['cronToken']) || $state['cronToken'] === '') {
        $state['cronToken'] = 'TD-' . bin2hex(random_bytes(8));
        writeJsonAtomic(tender_discovery_state_path(), $state);
    }
    return $state;
}

function tender_discovery_save_state(array $state): void
{
    ensure_tender_discovery_env();
    writeJsonAtomic(tender_discovery_state_path(), $state);
}

function tender_discovery_index(): array
{
    ensure_tender_discovery_env();
    $index = readJson(tender_discovery_index_path());
    $index = is_array($index) ? array_values($index) : [];
    foreach ($index as &$entry) {
        if (!array_key_exists('deletedAt', $entry)) {
            $entry['deletedAt'] = null;
        }
    }
    unset($entry);
    return $index;
}

function tender_discovery_save_index(array $entries): void
{
    ensure_tender_discovery_env();
    writeJsonAtomic(tender_discovery_index_path(), array_values($entries));
}

function tender_discovery_generate_disc_id(): string
{
    do {
        $candidate = 'DT-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    } while (file_exists(tender_discovery_discovered_path($candidate)));

    return $candidate;
}

function tender_discovery_dedupe_key(string $title, string $url, ?string $deadline): string
{
    $normalizedDeadline = $deadline ? strtolower(trim($deadline)) : '';
    return hash('sha256', strtolower(trim($title)) . '|' . trim($url) . '|' . $normalizedDeadline);
}

function tender_discovery_fetch_url(string $url): string
{
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('Invalid URL scheme');
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'header' => "User-Agent: YOJAKTenderDiscovery/1.0\r\n",
        ],
        'https' => [
            'method' => 'GET',
            'timeout' => 12,
            'header' => "User-Agent: YOJAKTenderDiscovery/1.0\r\n",
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        throw new RuntimeException('Failed to fetch source: ' . $url);
    }

    return $raw;
}

function tender_discovery_collect_from_source(array $source): array
{
    $type = $source['type'] ?? '';
    $url = $source['url'] ?? '';
    $raw = tender_discovery_fetch_url($url);

    if ($type === 'rss') {
        return tender_discovery_parse_rss($raw, $url);
    }

    if ($type === 'json') {
        return tender_discovery_parse_json($raw);
    }

    return tender_discovery_parse_html($raw, $url, $source['parseHints'] ?? []);
}

function tender_discovery_parse_rss(string $raw, string $fallbackUrl): array
{
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);
    if ($xml === false) {
        throw new RuntimeException('Unable to parse RSS feed');
    }

    $items = [];
    foreach ($xml->channel->item as $item) {
        $title = trim((string)($item->title ?? ''));
        $link = trim((string)($item->link ?? $fallbackUrl));
        if ($title === '' || $link === '') {
            continue;
        }

        $pubDateRaw = (string)($item->pubDate ?? '');
        $publishedAt = null;
        if ($pubDateRaw !== '') {
            try {
                $publishedAt = (new DateTimeImmutable($pubDateRaw))->format(DateTime::ATOM);
            } catch (Throwable $e) {
                $publishedAt = null;
            }
        }

        $items[] = [
            'title' => $title,
            'originalUrl' => $link,
            'publishedAt' => $publishedAt,
            'deadlineAt' => null,
            'dept' => null,
            'location' => 'Jharkhand',
            'raw' => [
                'title' => $title,
                'link' => $link,
                'description' => isset($item->description) ? substr((string)$item->description, 0, 400) : '',
            ],
        ];
    }

    return $items;
}

function tender_discovery_parse_json(string $raw): array
{
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('JSON source did not return an object/array');
    }

    if (isset($decoded[0])) {
        $candidates = $decoded;
    } elseif (isset($decoded['tenders']) && is_array($decoded['tenders'])) {
        $candidates = $decoded['tenders'];
    } elseif (isset($decoded['items']) && is_array($decoded['items'])) {
        $candidates = $decoded['items'];
    } else {
        $candidates = [];
    }

    $items = [];
    foreach ($candidates as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = trim((string)($item['title'] ?? ''));
        $link = trim((string)($item['url'] ?? ($item['link'] ?? '')));
        if ($title === '' || $link === '') {
            continue;
        }

        $deadline = tender_discovery_normalize_datetime($item['deadline'] ?? ($item['deadlineAt'] ?? null));
        $publishedAt = tender_discovery_normalize_datetime($item['publishedAt'] ?? ($item['publishDate'] ?? null));
        $items[] = [
            'title' => $title,
            'originalUrl' => $link,
            'publishedAt' => $publishedAt,
            'deadlineAt' => $deadline,
            'dept' => isset($item['department']) ? trim((string)$item['department']) : null,
            'location' => trim((string)($item['location'] ?? 'Jharkhand')) ?: 'Jharkhand',
            'raw' => $item,
        ];
    }

    return $items;
}

function tender_discovery_parse_html(string $raw, string $baseUrl, $parseHints): array
{
    $items = [];
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$dom->loadHTML($raw)) {
        return $items;
    }

    $xpath = new DOMXPath($dom);
    $selector = null;
    if (is_array($parseHints) && isset($parseHints['xpath'])) {
        $selector = $parseHints['xpath'];
    }

    $nodes = $selector ? $xpath->query($selector) : $dom->getElementsByTagName('a');
    if (!$nodes) {
        return $items;
    }

    $baseParts = parse_url($baseUrl) ?: [];
    $baseRoot = '';
    if (!empty($baseParts['scheme']) && !empty($baseParts['host'])) {
        $baseRoot = $baseParts['scheme'] . '://' . $baseParts['host'];
        if (!empty($baseParts['port'])) {
            $baseRoot .= ':' . $baseParts['port'];
        }
    }

    foreach ($nodes as $node) {
        $href = trim((string)$node->getAttribute('href'));
        $title = trim($node->textContent ?? '');
        if ($href === '' || $title === '') {
            continue;
        }

        if (str_starts_with($href, '//')) {
            $href = ($baseParts['scheme'] ?? 'https') . ':' . $href;
        } elseif (str_starts_with($href, '/')) {
            $href = rtrim($baseRoot, '/') . $href;
        } elseif (!preg_match('/^https?:\\/\\//i', $href) && $baseRoot !== '') {
            $href = rtrim($baseRoot, '/') . '/' . ltrim($href, '/');
        }

        $deadlineAttr = is_array($parseHints) ? ($parseHints['deadlineAttr'] ?? 'data-deadline') : 'data-deadline';
        $deadlineRaw = $node->getAttribute($deadlineAttr);
        $deadlineAt = tender_discovery_normalize_datetime($deadlineRaw !== '' ? $deadlineRaw : null);

        $items[] = [
            'title' => $title,
            'originalUrl' => $href,
            'publishedAt' => null,
            'deadlineAt' => $deadlineAt,
            'dept' => null,
            'location' => 'Jharkhand',
            'raw' => [
                'title' => $title,
                'href' => $href,
                'deadline' => $deadlineRaw,
            ],
        ];
    }

    return $items;
}

function tender_discovery_normalize_datetime($value): ?string
{
    if ($value === null) {
        return null;
    }
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    try {
        $dt = new DateTimeImmutable($value, new DateTimeZone('Asia/Kolkata'));
        return $dt->format(DateTime::ATOM);
    } catch (Throwable $e) {
        return null;
    }
}

function tender_discovery_save_discovered(array $record, array &$index): void
{
    ensure_tender_discovery_env();
    $path = tender_discovery_discovered_path($record['discId']);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    if (!array_key_exists('deletedAt', $record)) {
        $record['deletedAt'] = null;
    }
    if (!array_key_exists('seenByAdminAt', $record)) {
        $record['seenByAdminAt'] = null;
    }

    writeJsonAtomic($path, $record);

    $index[] = [
        'discId' => $record['discId'],
        'title' => $record['title'],
        'sourceId' => $record['sourceId'],
        'deadlineAt' => $record['deadlineAt'] ?? null,
        'createdAt' => $record['createdAt'],
        'dedupeKey' => $record['dedupeKey'],
        'location' => $record['location'] ?? 'Jharkhand',
        'deletedAt' => $record['deletedAt'],
    ];
    tender_discovery_save_index($index);
}

function tender_discovery_load_discovered(string $discId): ?array
{
    ensure_tender_discovery_env();
    $path = tender_discovery_discovered_path($discId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    if (!$data) {
        return null;
    }
    if (!array_key_exists('deletedAt', $data)) {
        $data['deletedAt'] = null;
    }
    if (!array_key_exists('seenByAdminAt', $data)) {
        $data['seenByAdminAt'] = null;
    }
    return $data;
}

function tender_discovery_soft_delete(string $discId): bool
{
    ensure_tender_discovery_env();
    $record = tender_discovery_load_discovered($discId);
    if (!$record) {
        return false;
    }
    if (!empty($record['deletedAt'])) {
        return true;
    }

    $timestamp = now_kolkata()->format(DateTime::ATOM);
    $record['deletedAt'] = $timestamp;
    writeJsonAtomic(tender_discovery_discovered_path($discId), $record);

    $index = tender_discovery_index();
    foreach ($index as &$entry) {
        if (($entry['discId'] ?? '') === $discId) {
            $entry['deletedAt'] = $timestamp;
            break;
        }
    }
    unset($entry);
    tender_discovery_save_index($index);

    return true;
}

function tender_discovery_mark_seen_by_admin(string $discId): void
{
    ensure_tender_discovery_env();
    $record = tender_discovery_load_discovered($discId);
    if (!$record) {
        return;
    }
    if (!empty($record['seenByAdminAt'])) {
        return;
    }
    $record['seenByAdminAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(tender_discovery_discovered_path($discId), $record);
}

function tender_discovery_run(array $sources): array
{
    ensure_tender_discovery_env();
    $index = tender_discovery_index();
    $dedupeMap = [];
    foreach ($index as $entry) {
        if (!empty($entry['dedupeKey'])) {
            $dedupeMap[$entry['dedupeKey']] = $entry['discId'];
        }
    }

    $summary = [
        'startedAt' => now_kolkata()->format(DateTime::ATOM),
        'finishedAt' => null,
        'totalFetched' => 0,
        'newCount' => 0,
        'perSource' => [],
        'errors' => [],
    ];

    foreach ($sources as $source) {
        if (empty($source['active'])) {
            continue;
        }
        $perSource = [
            'sourceId' => $source['sourceId'] ?? '',
            'name' => $source['name'] ?? '',
            'fetched' => 0,
            'new' => 0,
            'errors' => [],
        ];

        try {
            $items = tender_discovery_collect_from_source($source);
            foreach ($items as $item) {
                $title = trim((string)($item['title'] ?? ''));
                $url = trim((string)($item['originalUrl'] ?? ''));
                if ($title === '' || $url === '') {
                    continue;
                }
                $deadlineAt = $item['deadlineAt'] ?? null;
                $dedupeKey = tender_discovery_dedupe_key($title, $url, $deadlineAt);
                $perSource['fetched']++;
                $summary['totalFetched']++;

                if (isset($dedupeMap[$dedupeKey])) {
                    continue;
                }

                $discId = tender_discovery_generate_disc_id();
                $now = now_kolkata()->format(DateTime::ATOM);
                $record = [
                    'discId' => $discId,
                    'sourceId' => $source['sourceId'] ?? '',
                    'title' => $title,
                    'dept' => $item['dept'] ?? null,
                    'location' => $item['location'] ?? 'Jharkhand',
                    'publishedAt' => $item['publishedAt'] ?? null,
                    'deadlineAt' => $deadlineAt,
                    'originalUrl' => $url,
                    'dedupeKey' => $dedupeKey,
                    'raw' => $item['raw'] ?? [],
                    'createdAt' => $now,
                    'deletedAt' => null,
                    'seenByAdminAt' => null,
                ];
                tender_discovery_save_discovered($record, $index);
                $dedupeMap[$dedupeKey] = $discId;
                $perSource['new']++;
                $summary['newCount']++;
            }
        } catch (Throwable $e) {
            $perSource['errors'][] = $e->getMessage();
            $summary['errors'][] = [
                'sourceId' => $source['sourceId'] ?? '',
                'message' => $e->getMessage(),
            ];
            tender_discovery_log([
                'event' => 'source_error',
                'sourceId' => $source['sourceId'] ?? '',
                'message' => $e->getMessage(),
            ]);
        }

        $summary['perSource'][] = $perSource;
    }

    $summary['finishedAt'] = now_kolkata()->format(DateTime::ATOM);

    $state = tender_discovery_state();
    $state['lastRunAt'] = $summary['finishedAt'];
    $state['lastHash'] = hash('sha256', json_encode(array_keys($dedupeMap)));
    $state['lastSummary'] = $summary;
    tender_discovery_save_state($state);

    tender_discovery_log([
        'event' => 'discovery_run',
        'startedAt' => $summary['startedAt'],
        'finishedAt' => $summary['finishedAt'],
        'totalFetched' => $summary['totalFetched'],
        'newCount' => $summary['newCount'],
        'errors' => $summary['errors'],
    ]);

    return $summary;
}
