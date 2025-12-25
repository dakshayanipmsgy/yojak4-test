<?php
declare(strict_types=1);

const TENDER_ARCHIVE_LOG = DATA_PATH . '/logs/tender_archive.log';

function ensure_tender_archive_env(string $yojId): void
{
    $root = contractors_approved_path($yojId) . '/tender_archive';
    $templateRoot = contractors_approved_path($yojId) . '/checklist_templates';
    $uploadRoot = PUBLIC_PATH . '/uploads/contractors/' . $yojId . '/tender_archive';

    $directories = [
        $root,
        $templateRoot,
        $uploadRoot,
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $indexPath = tender_archive_index_path($yojId);
    if (!file_exists($indexPath)) {
        writeJsonAtomic($indexPath, []);
    }

    $templateIndex = checklist_templates_index_path($yojId);
    if (!file_exists($templateIndex)) {
        writeJsonAtomic($templateIndex, []);
    }

    if (!file_exists(TENDER_ARCHIVE_LOG)) {
        touch(TENDER_ARCHIVE_LOG);
    }
}

function tender_archive_index_path(string $yojId): string
{
    return contractors_approved_path($yojId) . '/tender_archive/index.json';
}

function tender_archive_index(string $yojId): array
{
    $index = readJson(tender_archive_index_path($yojId));
    return is_array($index) ? array_values($index) : [];
}

function save_tender_archive_index(string $yojId, array $entries): void
{
    writeJsonAtomic(tender_archive_index_path($yojId), array_values($entries));
}

function generate_archtd_id(string $yojId): string
{
    ensure_tender_archive_env($yojId);
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = 'ARCHTD-' . $suffix;
    } while (file_exists(tender_archive_path($yojId, $candidate)));

    return $candidate;
}

function tender_archive_dir(string $yojId, string $archId): string
{
    return contractors_approved_path($yojId) . '/tender_archive/' . $archId;
}

function tender_archive_path(string $yojId, string $archId): string
{
    return tender_archive_dir($yojId, $archId) . '/archive.json';
}

function tender_archive_upload_dir(string $yojId, string $archId): string
{
    return PUBLIC_PATH . '/uploads/contractors/' . $yojId . '/tender_archive/' . $archId;
}

function load_tender_archive(string $yojId, string $archId): ?array
{
    $path = tender_archive_path($yojId, $archId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function tender_archive_ai_defaults(): array
{
    return [
        'lastRunAt' => null,
        'rawText' => '',
        'parsedOk' => false,
        'summaryText' => '',
        'keyLearnings' => [],
        'suggestedChecklist' => [],
    ];
}

function tender_archive_log(array $context): void
{
    logEvent(TENDER_ARCHIVE_LOG, $context);
}

function normalize_archive_outcome(string $value): string
{
    $allowed = ['', 'participated', 'won', 'lost'];
    return in_array($value, $allowed, true) ? $value : '';
}

function normalize_archive_year(?string $yearInput): ?int
{
    if ($yearInput === null || trim($yearInput) === '') {
        return null;
    }
    if (!is_numeric($yearInput)) {
        return null;
    }
    $year = (int)$yearInput;
    $currentYear = (int)now_kolkata()->format('Y');
    if ($year < 2000 || $year > $currentYear) {
        return null;
    }
    return $year;
}

function normalize_archive_checklist(array $items): array
{
    $normalized = [];
    foreach ($items as $item) {
        if (count($normalized) >= 200) {
            break;
        }
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $normalized[] = [
            'title' => $title,
            'description' => trim((string)($item['description'] ?? '')),
            'required' => (bool)($item['required'] ?? false),
        ];
    }
    return $normalized;
}

function normalize_archive_learnings($value): array
{
    $result = [];
    if (is_array($value)) {
        foreach ($value as $line) {
            $text = trim((string)$line);
            if ($text === '') {
                continue;
            }
            $result[] = $text;
            if (count($result) >= 50) {
                break;
            }
        }
    } elseif (is_string($value)) {
        $lines = preg_split('/\r?\n/', $value) ?: [];
        foreach ($lines as $line) {
            $text = trim($line);
            if ($text === '') {
                continue;
            }
            $result[] = $text;
            if (count($result) >= 50) {
                break;
            }
        }
    }
    return $result;
}

function save_tender_archive(array $archive): void
{
    if (empty($archive['id']) || empty($archive['yojId'])) {
        throw new InvalidArgumentException('Archive id or contractor id missing');
    }

    $path = tender_archive_path($archive['yojId'], $archive['id']);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    writeJsonAtomic($path, $archive);

    $index = tender_archive_index($archive['yojId']);
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['id'] ?? '') === $archive['id']) {
            $entry['title'] = $archive['title'] ?? $entry['title'];
            $entry['year'] = $archive['year'] ?? null;
            $entry['departmentName'] = $archive['departmentName'] ?? '';
            $entry['outcome'] = $archive['outcome'] ?? '';
            $entry['updatedAt'] = $archive['updatedAt'] ?? now_kolkata()->format(DateTime::ATOM);
            $entry['deletedAt'] = $archive['deletedAt'] ?? null;
            $found = true;
            break;
        }
    }
    unset($entry);

    if (!$found) {
        $index[] = [
            'id' => $archive['id'],
            'title' => $archive['title'] ?? 'Archived Tender',
            'year' => $archive['year'] ?? null,
            'departmentName' => $archive['departmentName'] ?? '',
            'outcome' => $archive['outcome'] ?? '',
            'updatedAt' => $archive['updatedAt'] ?? now_kolkata()->format(DateTime::ATOM),
            'deletedAt' => $archive['deletedAt'] ?? null,
        ];
    }

    save_tender_archive_index($archive['yojId'], $index);
}

function checklist_templates_index_path(string $yojId): string
{
    return contractors_approved_path($yojId) . '/checklist_templates/index.json';
}

function load_checklist_templates(string $yojId): array
{
    $items = readJson(checklist_templates_index_path($yojId));
    return is_array($items) ? array_values($items) : [];
}

function save_checklist_templates(string $yojId, array $templates): void
{
    writeJsonAtomic(checklist_templates_index_path($yojId), array_values($templates));
}

function generate_template_id(string $yojId): string
{
    ensure_tender_archive_env($yojId);
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = 'TPL-' . $suffix;
        $existing = load_checklist_templates($yojId);
        $exists = false;
        foreach ($existing as $template) {
            if (($template['templateId'] ?? '') === $candidate) {
                $exists = true;
                break;
            }
        }
    } while ($exists);

    return $candidate;
}

function tender_archive_ai_prompt(array $archive): array
{
    $system = 'You are an assistant that summarizes tender documents and extracts reusable checklist items. Respond ONLY with JSON.';

    $lines = [];
    $lines[] = 'Tender meta:';
    $lines[] = 'Title: ' . ($archive['title'] ?? '');
    $lines[] = 'Department: ' . ($archive['departmentName'] ?? '');
    $lines[] = 'Year: ' . ($archive['year'] ?? '');
    $lines[] = 'Outcome: ' . ($archive['outcome'] ?? '');
    $lines[] = 'Source files: ' . implode(', ', array_map(fn($f) => $f['name'] ?? '', $archive['sourceFiles'] ?? []));

    $lines[] = '';
    $lines[] = 'Return JSON with keys summaryText (string), keyLearnings (array of strings), suggestedChecklist (array of {title, description, required}). Keep content concise and practical for future tenders.';

    return [$system, implode("\n", $lines)];
}
