<?php
declare(strict_types=1);

const OFFLINE_TENDER_LOG = DATA_PATH . '/logs/offline_tenders.log';

function ensure_offline_tender_env(string $yojId): void
{
    $root = contractors_approved_path($yojId) . '/tenders_offline';
    $reminderDir = contractors_approved_path($yojId) . '/reminders';
    $paths = [
        $root,
        $reminderDir,
    ];

    foreach ($paths as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $indexPath = offline_tenders_index_path($yojId);
    if (!file_exists($indexPath)) {
        writeJsonAtomic($indexPath, []);
    }

    $reminderIndex = reminders_index_path($yojId);
    if (!file_exists($reminderIndex)) {
        writeJsonAtomic($reminderIndex, []);
    }

    if (!file_exists(OFFLINE_TENDER_LOG)) {
        touch(OFFLINE_TENDER_LOG);
    }
}

function offline_tenders_index_path(string $yojId): string
{
    return contractors_approved_path($yojId) . '/tenders_offline/index.json';
}

function offline_tenders_index(string $yojId): array
{
    $index = readJson(offline_tenders_index_path($yojId));
    return is_array($index) ? array_values($index) : [];
}

function save_offline_tenders_index(string $yojId, array $records): void
{
    writeJsonAtomic(offline_tenders_index_path($yojId), array_values($records));
}

function generate_offtd_id(string $yojId): string
{
    ensure_offline_tender_env($yojId);
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = 'OFFTD-' . $suffix;
    } while (file_exists(offline_tender_path($yojId, $candidate)));

    return $candidate;
}

function offline_tender_dir(string $yojId, string $offtdId): string
{
    return contractors_approved_path($yojId) . '/tenders_offline/' . $offtdId;
}

function offline_tender_path(string $yojId, string $offtdId): string
{
    return offline_tender_dir($yojId, $offtdId) . '/tender.json';
}

function offline_tender_upload_dir(string $yojId, string $offtdId): string
{
    return PUBLIC_PATH . '/uploads/contractors/' . $yojId . '/tenders_offline/' . $offtdId . '/source';
}

function load_offline_tender(string $yojId, string $offtdId): ?array
{
    $path = offline_tender_path($yojId, $offtdId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function save_offline_tender(array $tender): void
{
    if (empty($tender['id']) || empty($tender['yojId'])) {
        throw new InvalidArgumentException('Tender id or contractor id missing');
    }

    $sourceDiscId = $tender['source']['discId'] ?? ($tender['sourceDiscId'] ?? null);
    $sourceOriginalUrl = $tender['source']['originalUrl'] ?? ($tender['sourceOriginalUrl'] ?? null);
    $path = offline_tender_path($tender['yojId'], $tender['id']);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    writeJsonAtomic($path, $tender);

    $index = offline_tenders_index($tender['yojId']);
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['id'] ?? '') === $tender['id']) {
            $entry['title'] = $tender['title'] ?? $entry['title'];
            $entry['status'] = $tender['status'] ?? $entry['status'];
            $entry['submissionDeadline'] = $tender['extracted']['submissionDeadline'] ?? ($entry['submissionDeadline'] ?? null);
            $entry['openingDate'] = $tender['extracted']['openingDate'] ?? ($entry['openingDate'] ?? null);
            $entry['updatedAt'] = $tender['updatedAt'] ?? now_kolkata()->format(DateTime::ATOM);
            $entry['deletedAt'] = $tender['deletedAt'] ?? null;
            $entry['sourceDiscId'] = $sourceDiscId ?? ($entry['sourceDiscId'] ?? null);
            $entry['sourceOriginalUrl'] = $sourceOriginalUrl ?? ($entry['sourceOriginalUrl'] ?? null);
            $found = true;
            break;
        }
    }
    unset($entry);

    if (!$found) {
        $index[] = [
            'id' => $tender['id'],
            'title' => $tender['title'] ?? 'Untitled',
            'status' => $tender['status'] ?? 'draft',
            'submissionDeadline' => $tender['extracted']['submissionDeadline'] ?? null,
            'openingDate' => $tender['extracted']['openingDate'] ?? null,
            'updatedAt' => $tender['updatedAt'] ?? now_kolkata()->format(DateTime::ATOM),
            'deletedAt' => $tender['deletedAt'] ?? null,
            'sourceDiscId' => $sourceDiscId,
            'sourceOriginalUrl' => $sourceOriginalUrl,
        ];
    }

    save_offline_tenders_index($tender['yojId'], $index);
}

function reminders_index_path(string $yojId): string
{
    return contractors_approved_path($yojId) . '/reminders/index.json';
}

function load_reminders(string $yojId): array
{
    $data = readJson(reminders_index_path($yojId));
    return is_array($data) ? array_values($data) : [];
}

function save_reminders(string $yojId, array $reminders): void
{
    writeJsonAtomic(reminders_index_path($yojId), array_values($reminders));
}

function add_tender_reminder(string $yojId, string $tenderId, string $title, string $dueAt): bool
{
    $reminders = load_reminders($yojId);
    foreach ($reminders as $reminder) {
        if (($reminder['type'] ?? '') === 'tender_deadline'
            && ($reminder['refId'] ?? '') === $tenderId
            && ($reminder['dueAt'] ?? '') === $dueAt) {
            return false;
        }
    }

    $reminders[] = [
        'reminderId' => 'REM-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
        'type' => 'tender_deadline',
        'refId' => $tenderId,
        'title' => $title,
        'dueAt' => $dueAt,
        'createdAt' => now_kolkata()->format(DateTime::ATOM),
        'status' => 'active',
    ];

    save_reminders($yojId, $reminders);
    return true;
}

function offline_tender_log(array $context): void
{
    logEvent(OFFLINE_TENDER_LOG, $context);
}

function find_offline_tender_by_discovery(string $yojId, string $discId): ?array
{
    $index = offline_tenders_index($yojId);
    foreach ($index as $entry) {
        if (($entry['sourceDiscId'] ?? null) === $discId) {
            return load_offline_tender($yojId, $entry['id'] ?? '');
        }
    }
    return null;
}

function offline_tender_defaults(): array
{
    return [
        'publishDate' => null,
        'submissionDeadline' => null,
        'openingDate' => null,
        'fees' => [
            'tenderFee' => '',
            'emd' => '',
            'other' => '',
        ],
        'completionMonths' => null,
        'bidValidityDays' => null,
        'eligibilityDocs' => [],
        'annexures' => [],
        'restrictedAnnexures' => [],
        'formats' => [],
        'checklist' => [],
    ];
}

function offline_tender_checklist_item(string $title, string $description = '', bool $required = true, string $source = 'ai'): array
{
    return [
        'itemId' => 'CHK-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)),
        'title' => $title,
        'description' => $description,
        'required' => $required,
        'status' => 'pending',
        'source' => $source,
    ];
}

function offline_tender_extract_text(array $sourceFiles): string
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

function offline_tender_schema_properties(): array
{
    return [
        'publishDate' => [
            'type' => 'string',
            'description' => 'Publish date as ISO string or null',
            'nullable' => true,
        ],
        'submissionDeadline' => [
            'type' => 'string',
            'description' => 'Submission deadline as ISO string or null',
            'nullable' => true,
        ],
        'openingDate' => [
            'type' => 'string',
            'description' => 'Opening date as ISO string or null',
            'nullable' => true,
        ],
        'fees' => [
            'type' => 'object',
            'properties' => [
                'tenderFee' => ['type' => 'string', 'description' => 'Tender fee text'],
                'emd' => ['type' => 'string', 'description' => 'EMD text'],
                'other' => ['type' => 'string', 'description' => 'Other fee notes'],
            ],
            'required' => ['tenderFee', 'emd', 'other'],
            'additionalProperties' => false,
        ],
        'completionMonths' => [
            'type' => 'integer',
            'description' => 'Completion period in months or null',
            'nullable' => true,
        ],
        'bidValidityDays' => [
            'type' => 'integer',
            'description' => 'Bid validity in days or null',
            'nullable' => true,
        ],
        'eligibilityDocs' => [
            'type' => 'array',
            'items' => ['type' => 'string'],
            'description' => 'Eligibility document list',
            'nullable' => true,
        ],
        'annexures' => [
            'type' => 'array',
            'items' => ['type' => 'string'],
            'description' => 'Annexure list',
            'nullable' => true,
        ],
        'formats' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'notes' => ['type' => 'string'],
                ],
                'required' => ['name', 'notes'],
                'additionalProperties' => false,
            ],
            'description' => 'Submission format list',
            'nullable' => true,
        ],
        'checklist' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'required' => ['type' => 'boolean'],
                ],
                'required' => ['title', 'description', 'required'],
                'additionalProperties' => false,
            ],
            'description' => 'Checklist items derived from tender',
            'nullable' => true,
        ],
    ];
}

function offline_tender_response_schema(): array
{
    $properties = offline_tender_schema_properties();
    return [
        'type' => 'object',
        'properties' => $properties,
        'required' => array_keys($properties),
        'additionalProperties' => false,
        'description' => 'Offline tender extraction payload (no bid values/rates).',
    ];
}

function offline_tender_validate_extraction_schema(array $data): array
{
    $errors = [];
    $properties = offline_tender_schema_properties();
    $requiredKeys = array_keys($properties);

    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $data)) {
            $errors[] = "Missing key: {$key}";
        }
    }

    if (!isset($data['fees']) || !is_array($data['fees'])) {
        $errors[] = 'Fees must be an object with tenderFee, emd, and other fields.';
    } else {
        foreach (['tenderFee', 'emd', 'other'] as $feeKey) {
            if (!array_key_exists($feeKey, $data['fees'])) {
                $errors[] = "Missing fee field: {$feeKey}";
            }
        }
    }

    $numericFields = [
        'completionMonths',
        'bidValidityDays',
    ];
    foreach ($numericFields as $field) {
        if (array_key_exists($field, $data) && $data[$field] !== null && !is_numeric($data[$field])) {
            $errors[] = "{$field} must be numeric or null.";
        }
    }

    $listFields = [
        'eligibilityDocs',
        'annexures',
    ];
    foreach ($listFields as $field) {
        if (array_key_exists($field, $data) && $data[$field] !== null && !is_array($data[$field])) {
            $errors[] = "{$field} must be an array of strings.";
        }
    }

    if (array_key_exists('formats', $data) && $data['formats'] !== null) {
        if (!is_array($data['formats'])) {
            $errors[] = 'formats must be an array of objects.';
        } else {
            foreach ($data['formats'] as $format) {
                if (!is_array($format)) {
                    $errors[] = 'formats entries must be objects.';
                    break;
                }
                if (!array_key_exists('name', $format) || trim((string)$format['name']) === '') {
                    $errors[] = 'formats entries require a name.';
                    break;
                }
                if (!array_key_exists('notes', $format)) {
                    $errors[] = 'formats entries require notes (can be empty string).';
                    break;
                }
            }
        }
    }

    if (array_key_exists('checklist', $data) && $data['checklist'] !== null) {
        if (!is_array($data['checklist'])) {
            $errors[] = 'checklist must be an array of objects.';
        } else {
            foreach ($data['checklist'] as $item) {
                if (!is_array($item)) {
                    $errors[] = 'checklist entries must be objects.';
                    break;
                }
                if (!array_key_exists('title', $item) || trim((string)$item['title']) === '') {
                    $errors[] = 'checklist entries require a title.';
                    break;
                }
                if (!array_key_exists('required', $item)) {
                    $errors[] = 'checklist entries require the required flag.';
                    break;
                }
                if (array_key_exists('required', $item) && !is_bool($item['required'])) {
                    $errors[] = 'checklist entries must specify required as a boolean.';
                    break;
                }
            }
        }
    }

    return [
        'ok' => empty($errors),
        'errors' => $errors,
    ];
}

function offline_tender_ai_prompt(array $tender, bool $lenient = false): array
{
    $jsonDiscipline = $lenient
        ? 'Provide one clean JSON object; brief context text is acceptable but keep the JSON block valid and free of code fences.'
        : 'Return ONLY JSON with no prose, code fences, or prefixes.';
    $system = 'You are a tender extraction assistant. ' . $jsonDiscipline . ' '
        . 'Ensure all keys exist. Dates must be ISO8601 strings.';

    $expected = [
        'tender' => [
            'documentType' => 'NIT|NIB|Tender|Unknown',
            'tenderTitle' => 'string|null (extracted title)',
            'tenderNumber' => 'string|null (NIT index/ref)',
            'issuingAuthority' => 'string|null',
            'departmentName' => 'string|null',
            'location' => 'string|null',
            'submissionDeadline' => 'datetime string|null',
            'openingDate' => 'datetime string|null',
            'completionMonths' => 'number|null',
            'validityDays' => 'number|null (bid validity)',
        ],
        'lists' => [
            'eligibilityDocs' => ['array of strings (mandatory documents)'],
            'annexures' => ['array of strings (all annexures mentioned)'],
            'formats' => [['name' => 'string', 'notes' => 'string']],
            'restricted' => ['array of strings (financial/price bid annexures which should NOT be generated)'],
        ],
        'checklist' => [
            ['title' => 'string', 'category' => 'Eligibility|Fees|Forms|Technical|Submission|Declarations|Other', 'required' => true, 'notes' => 'string', 'source' => 'ai']
        ],
        'templates' => [
            ['code' => 'Annexure-1', 'name' => 'Cover Letter', 'type' => 'cover_letter|declaration|poa|turnover|net_worth|info_sheet|undertaking|other', 'placeholders' => [], 'body' => 'template text with handlebars {{...}}']
        ],
        'snippets' => ['string (context snippets)'],
    ];

    $userPrompt = "Extract key tender fields, checklist items, and annexure lists from the provided text."
        . " Return strict JSON matching this schema: " . json_encode($expected) . "."
        . " RULES:\n"
        . " 1. Block: Do NOT extract rates/prices/BOQ data. If a financial annexure exists, list it in 'lists.restricted' and do NOT create a template for it.\n"
        . " 2. Fees: Extract Tender Fee and EMD amounts into checklist items (category: Fees).\n"
        . " 3. Templates: For each required annexure/format, generate a generic text template with placeholders (e.g. {{field:contractor.firm_name}}, {{field:tender.title}}).\n"
        . " 4. Timezone: Use Asia/Kolkata context for dates.\n"
        . " 5. Validity: validityDays = Bid Validity (not completion).\n"
        . " Do not wrap the JSON in markdown."
        . ($lenient ? " You may include short notes, but include one standalone JSON object we can parse." : " Respond with only the JSON object.")
        . " Source text:\n" . offline_tender_extract_text($tender['sourceFiles'] ?? []);

    return [$system, $userPrompt];
}

function normalize_string_list($value): array
{
    $items = [];
    if (is_array($value)) {
        $items = $value;
    } elseif (is_string($value)) {
        $items = preg_split('/\r?\n/', $value) ?: [];
    }
    $clean = [];
    foreach ($items as $item) {
        $text = trim((string)$item);
        if ($text === '') {
            continue;
        }
        $clean[] = $text;
        if (count($clean) >= 200) {
            break;
        }
    }
    return $clean;
}

function normalize_formats($value): array
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
            if (count($result) >= 200) {
                break;
            }
        }
    } elseif (is_string($value)) {
        $lines = preg_split('/\r?\n/', $value) ?: [];
        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            if ($parts[0] === '') {
                continue;
            }
            $result[] = ['name' => $parts[0], 'notes' => $parts[1] ?? ''];
            if (count($result) >= 200) {
                break;
            }
        }
    }
    return $result;
}

function merge_checklist(array $existing, array $incoming): array
{
    $merged = [];
    $seenTitles = [];
    foreach ($existing as $item) {
        if (count($merged) >= 200) {
            break;
        }
        if (!isset($item['itemId'])) {
            $item['itemId'] = 'CHK-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        }
        $merged[] = $item;
        $seenTitles[strtolower($item['title'] ?? '')] = true;
    }

    foreach ($incoming as $item) {
        if (count($merged) >= 200) {
            break;
        }
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        if (isset($seenTitles[strtolower($title)])) {
            continue;
        }
        $merged[] = [
            'itemId' => 'CHK-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)),
            'title' => $title,
            'description' => trim((string)($item['description'] ?? '')),
            'required' => (bool)($item['required'] ?? true),
            'status' => in_array($item['status'] ?? '', ['pending', 'uploaded', 'done'], true) ? $item['status'] : 'pending',
            'source' => $item['source'] ?? 'ai',
        ];
        $seenTitles[strtolower($title)] = true;
    }

    return $merged;
}
