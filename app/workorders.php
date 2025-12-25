<?php
declare(strict_types=1);

const WORKORDER_LOG = DATA_PATH . '/logs/workorders.log';

function workorders_root(string $yojId): string
{
    return contractors_approved_path($yojId) . '/workorders';
}

function workorders_index_path(string $yojId): string
{
    return workorders_root($yojId) . '/index.json';
}

function workorder_dir(string $yojId, string $woId): string
{
    return workorders_root($yojId) . '/' . $woId;
}

function workorder_path(string $yojId, string $woId): string
{
    return workorder_dir($yojId, $woId) . '/workorder.json';
}

function workorder_upload_dir(string $yojId, string $woId): string
{
    return PUBLIC_PATH . '/uploads/contractors/' . $yojId . '/workorders/' . $woId . '/source';
}

function ensure_workorder_env(string $yojId): void
{
    $paths = [
        workorders_root($yojId),
        workorder_upload_dir($yojId, 'sample'),
        contractors_approved_path($yojId) . '/packs_workorder',
        reminders_index_path($yojId),
    ];

    foreach ($paths as $path) {
        if (str_ends_with($path, '.json')) {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        } elseif (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }

    if (!file_exists(workorders_index_path($yojId))) {
        writeJsonAtomic(workorders_index_path($yojId), []);
    }

    if (!file_exists(reminders_index_path($yojId))) {
        writeJsonAtomic(reminders_index_path($yojId), []);
    }

    if (!file_exists(WORKORDER_LOG)) {
        touch(WORKORDER_LOG);
    }
}

function workorders_index(string $yojId): array
{
    $index = readJson(workorders_index_path($yojId));
    return is_array($index) ? array_values($index) : [];
}

function save_workorders_index(string $yojId, array $records): void
{
    writeJsonAtomic(workorders_index_path($yojId), array_values($records));
}

function generate_workorder_id(string $yojId): string
{
    ensure_workorder_env($yojId);
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = 'WO-' . $suffix;
    } while (file_exists(workorder_path($yojId, $candidate)));

    return $candidate;
}

function load_workorder(string $yojId, string $woId): ?array
{
    $path = workorder_path($yojId, $woId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function workorder_defaults(): array
{
    return [
        'woId' => '',
        'source' => 'manual',
        'title' => '',
        'deptName' => '',
        'projectLocation' => '',
        'createdAt' => null,
        'updatedAt' => null,
        'ai' => [
            'lastRunAt' => null,
            'rawText' => '',
            'parsedOk' => false,
            'errors' => [],
        ],
        'obligationsChecklist' => [],
        'requiredDocs' => [],
        'timeline' => [],
        'linkedPackId' => null,
        'sourceFiles' => [],
    ];
}

function save_workorder(array $workorder): void
{
    if (empty($workorder['woId']) || empty($workorder['yojId'])) {
        throw new InvalidArgumentException('Missing workorder id or contractor id');
    }

    ensure_workorder_env($workorder['yojId']);

    $path = workorder_path($workorder['yojId'], $workorder['woId']);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    writeJsonAtomic($path, $workorder);

    $index = workorders_index($workorder['yojId']);
    $summary = [
        'woId' => $workorder['woId'],
        'title' => $workorder['title'] ?? 'Workorder',
        'deptName' => $workorder['deptName'] ?? '',
        'projectLocation' => $workorder['projectLocation'] ?? '',
        'source' => $workorder['source'] ?? 'manual',
        'linkedPackId' => $workorder['linkedPackId'] ?? null,
        'updatedAt' => $workorder['updatedAt'] ?? now_kolkata()->format(DateTime::ATOM),
    ];
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['woId'] ?? '') === $workorder['woId']) {
            $entry = array_merge($entry, $summary);
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = $summary;
    }
    save_workorders_index($workorder['yojId'], $index);
}

function workorder_log(array $context): void
{
    logEvent(WORKORDER_LOG, $context);
}

function workorder_extract_text(array $sourceFiles): string
{
    $snippets = [];
    foreach ($sourceFiles as $file) {
        $path = $file['path'] ?? '';
        if ($path === '') {
            continue;
        }
        $fullPath = PUBLIC_PATH . $path;
        if (!file_exists($fullPath)) {
            continue;
        }
        $raw = @file_get_contents($fullPath);
        if ($raw === false) {
            continue;
        }
        $text = preg_replace('/[^\P{C}\n\r\t]+/u', ' ', $raw);
        $text = preg_replace('/\s+/', ' ', (string)$text);
        $snippets[] = 'File: ' . ($file['name'] ?? basename($fullPath)) . ' | Preview: ' . substr((string)$text, 0, 4000);
    }
    return implode("\n", $snippets);
}

function workorder_ai_prompt(array $workorder): array
{
    $system = 'You are a workorder extraction assistant. Return ONLY JSON. No explanations. '
        . 'Ensure all keys exist. Dates must be ISO8601 strings in Asia/Kolkata or null if missing.';

    $expected = [
        'title' => 'string',
        'deptName' => 'string or null',
        'projectLocation' => 'string or null',
        'obligationsChecklist' => [['title' => 'string', 'description' => 'string', 'dueAt' => 'datetime string or null', 'status' => 'pending']],
        'requiredDocs' => [['name' => 'string', 'notes' => 'string']],
        'timeline' => [['milestone' => 'string', 'dueAt' => 'datetime string or null']],
    ];

    $userPrompt = 'Extract obligations, required documents, and timeline milestones from this workorder. '
        . 'Use Asia/Kolkata timezone for any inferred dates. '
        . 'Return strict JSON matching this schema: ' . json_encode($expected) . '. '
        . 'Source text:' . "\n" . workorder_extract_text($workorder['sourceFiles'] ?? []);

    return [$system, $userPrompt];
}

function normalize_workorder_due(?string $value): ?string
{
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

function merge_workorder_obligations(array $existing, array $incoming): array
{
    $merged = [];
    $seen = [];
    foreach ($existing as $item) {
        if (!isset($item['itemId'])) {
            $item['itemId'] = 'WCHK-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        }
        $merged[] = $item;
        $seen[strtolower($item['title'] ?? '')] = true;
    }

    foreach ($incoming as $item) {
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '' || isset($seen[strtolower($title)])) {
            continue;
        }
        $merged[] = [
            'itemId' => 'WCHK-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)),
            'title' => $title,
            'description' => trim((string)($item['description'] ?? '')),
            'dueAt' => normalize_workorder_due(isset($item['dueAt']) ? (string)$item['dueAt'] : ''),
            'status' => in_array($item['status'] ?? '', ['pending', 'in_progress', 'done'], true) ? $item['status'] : 'pending',
        ];
        $seen[strtolower($title)] = true;
    }

    return array_slice($merged, 0, 300);
}

function normalize_required_docs($value): array
{
    $result = [];
    if (is_array($value)) {
        foreach ($value as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $name = trim((string)($entry['name'] ?? ''));
            $notes = trim((string)($entry['notes'] ?? ''));
            if ($name === '') {
                continue;
            }
            $result[] = ['name' => $name, 'notes' => $notes];
        }
    }
    return array_slice($result, 0, 300);
}

function normalize_timeline($value): array
{
    $result = [];
    if (is_array($value)) {
        foreach ($value as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $milestone = trim((string)($entry['milestone'] ?? ''));
            $due = normalize_workorder_due(isset($entry['dueAt']) ? (string)$entry['dueAt'] : '');
            if ($milestone === '') {
                continue;
            }
            $result[] = [
                'milestone' => $milestone,
                'dueAt' => $due,
            ];
        }
    }
    return array_slice($result, 0, 300);
}

function add_workorder_reminder(string $yojId, string $woId, string $title, string $dueAt): bool
{
    $reminders = load_reminders($yojId);
    foreach ($reminders as $reminder) {
        if (($reminder['type'] ?? '') === 'workorder_deadline'
            && ($reminder['refId'] ?? '') === $woId
            && ($reminder['dueAt'] ?? '') === $dueAt) {
            return false;
        }
    }

    $reminders[] = [
        'reminderId' => 'REM-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
        'type' => 'workorder_deadline',
        'refId' => $woId,
        'title' => $title,
        'dueAt' => $dueAt,
        'createdAt' => now_kolkata()->format(DateTime::ATOM),
        'status' => 'active',
    ];

    save_reminders($yojId, $reminders);
    return true;
}
