<?php
declare(strict_types=1);

const ASSISTED_EXTRACTION_DIR = DATA_PATH . '/support/assisted_extraction';
const ASSISTED_EXTRACTION_INDEX = ASSISTED_EXTRACTION_DIR . '/index.json';
const ASSISTED_EXTRACTION_LOG = DATA_PATH . '/logs/assisted_extraction.log';
const ASSISTED_ALLOWED_DOCUMENT_TYPES = ['NIT', 'NIB', 'Tender', 'Unknown'];
const ASSISTED_ALLOWED_CHECKLIST_CATEGORIES = ['Eligibility', 'Fees', 'Forms', 'Technical', 'Submission', 'Declarations', 'Other'];
const ASSISTED_ALLOWED_TEMPLATE_TYPES = ['cover_letter', 'declaration', 'poa', 'turnover', 'net_worth', 'info_sheet', 'undertaking', 'other'];
const ASSISTED_SCHEMA_TOP_KEYS = ['tender', 'lists', 'checklist', 'templates', 'snippets'];

function ensure_assisted_extraction_env(): void
{
    $paths = [
        ASSISTED_EXTRACTION_DIR,
        DATA_PATH . '/defaults',
        dirname(ASSISTED_EXTRACTION_LOG),
    ];

    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }

    if (!file_exists(ASSISTED_EXTRACTION_INDEX)) {
        writeJsonAtomic(ASSISTED_EXTRACTION_INDEX, []);
    }

    if (!file_exists(ASSISTED_EXTRACTION_LOG)) {
        touch(ASSISTED_EXTRACTION_LOG);
    }
}

function assisted_schema_defaults(): array
{
    return [
        'tender' => [
            'documentType' => 'Unknown',
            'tenderTitle' => null,
            'tenderNumber' => null,
            'issuingAuthority' => null,
            'departmentName' => null,
            'location' => null,
            'submissionDeadline' => null,
            'openingDate' => null,
            'completionMonths' => null,
            'validityDays' => null,
        ],
        'lists' => [
            'eligibilityDocs' => [],
            'annexures' => [],
            'formats' => [],
            'restricted' => [],
        ],
        'checklist' => [],
        'templates' => [],
        'snippets' => [],
    ];
}

function assisted_extraction_request_path(string $reqId): string
{
    return ASSISTED_EXTRACTION_DIR . '/' . $reqId . '.json';
}

function assisted_extraction_index(): array
{
    ensure_assisted_extraction_env();
    $index = readJson(ASSISTED_EXTRACTION_INDEX);
    return is_array($index) ? array_values($index) : [];
}

function save_assisted_extraction_index(array $records): void
{
    ensure_assisted_extraction_env();
    writeJsonAtomic(ASSISTED_EXTRACTION_INDEX, array_values($records));
}

function assisted_generate_req_id(): string
{
    $date = now_kolkata()->format('Ymd');
    do {
        $suffix = random_int(1000, 9999);
        $candidate = 'AEX-' . $date . '-' . $suffix;
    } while (file_exists(assisted_extraction_request_path($candidate)));

    return $candidate;
}

function assisted_active_request_for_tender(string $yojId, string $offtdId): ?array
{
    foreach (assisted_extraction_index() as $entry) {
        if (($entry['yojId'] ?? '') !== $yojId || ($entry['offtdId'] ?? '') !== $offtdId) {
            continue;
        }
        if (in_array($entry['status'] ?? '', ['requested', 'in_progress', 'delivered'], true)) {
            $request = assisted_load_request($entry['reqId'] ?? '');
            if ($request) {
                return $request;
            }
        }
    }
    return null;
}

function assisted_recent_request_count(string $yojId, int $days = 7): int
{
    $threshold = now_kolkata()->modify("-{$days} days");
    $count = 0;
    foreach (assisted_extraction_index() as $entry) {
        if (($entry['yojId'] ?? '') !== $yojId) {
            continue;
        }
        try {
            $createdAt = isset($entry['createdAt']) ? new DateTimeImmutable((string)$entry['createdAt']) : null;
            if ($createdAt && $createdAt >= $threshold) {
                $count++;
            }
        } catch (Throwable $e) {
            continue;
        }
    }
    return $count;
}

function assisted_validate_request_limits(string $yojId, string $offtdId): array
{
    $errors = [];
    $active = assisted_active_request_for_tender($yojId, $offtdId);
    if ($active && ($active['status'] ?? '') !== 'closed') {
        $errors[] = 'An assisted extraction request already exists for this tender.';
    }
    if (assisted_recent_request_count($yojId, 7) >= 3) {
        $errors[] = 'Weekly assisted extraction limit reached (3 per week).';
    }
    return $errors;
}

function assisted_pick_tender_pdf(array $tender): ?array
{
    foreach ($tender['sourceFiles'] ?? [] as $file) {
        $path = $file['path'] ?? '';
        if ($path === '') {
            continue;
        }
        $fullPath = PUBLIC_PATH . $path;
        if (!file_exists($fullPath)) {
            continue;
        }
        $mime = mime_content_type($fullPath) ?: ($file['mime'] ?? '');
        return [
            'storedPath' => $path,
            'fileName' => $file['name'] ?? basename($fullPath),
            'size' => (int)($file['sizeBytes'] ?? (file_exists($fullPath) ? filesize($fullPath) : 0)),
            'mime' => $mime ?: 'application/pdf',
        ];
    }
    return null;
}

function assisted_create_request(string $yojId, string $offtdId, string $notes, array $tender): array
{
    ensure_assisted_extraction_env();
    $errors = assisted_validate_request_limits($yojId, $offtdId);
    if ($errors) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $reqId = assisted_generate_req_id();
    $now = now_kolkata()->format(DateTime::ATOM);
    $pdfRef = assisted_pick_tender_pdf($tender);

    $request = [
        'reqId' => $reqId,
        'yojId' => $yojId,
        'offtdId' => $offtdId,
        'status' => 'requested',
        'createdAt' => $now,
        'assignedTo' => null,
        'notesFromContractor' => mb_substr($notes, 0, 500),
        'tenderPdfRef' => $pdfRef,
        'assistantDraft' => [
            'submissionDeadline' => null,
            'openingDate' => null,
            'completionMonths' => null,
            'bidValidityDays' => null,
            'eligibilityDocs' => [],
            'annexures' => [],
            'formats' => [],
            'checklist' => [],
        ],
        'deliveredAt' => null,
        'audit' => [
            [
                'at' => $now,
                'by' => $yojId,
                'action' => 'requested',
                'note' => $notes !== '' ? mb_substr($notes, 0, 500) : null,
            ],
        ],
    ];

    writeJsonAtomic(assisted_extraction_request_path($reqId), $request);

    $index = assisted_extraction_index();
    $index[] = [
        'reqId' => $reqId,
        'yojId' => $yojId,
        'offtdId' => $offtdId,
        'status' => 'requested',
        'createdAt' => $now,
        'assignedTo' => null,
    ];
    save_assisted_extraction_index($index);

    logEvent(ASSISTED_EXTRACTION_LOG, [
        'event' => 'request_created',
        'reqId' => $reqId,
        'yojId' => $yojId,
        'offtdId' => $offtdId,
    ]);

    return $request;
}

function assisted_load_request(string $reqId): ?array
{
    ensure_assisted_extraction_env();
    $path = assisted_extraction_request_path($reqId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function assisted_save_request(array $request): void
{
    ensure_assisted_extraction_env();
    if (empty($request['reqId'])) {
        throw new InvalidArgumentException('Missing request id.');
    }
    $path = assisted_extraction_request_path($request['reqId']);
    writeJsonAtomic($path, $request);

    $index = assisted_extraction_index();
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['reqId'] ?? '') === $request['reqId']) {
            $entry['status'] = $request['status'] ?? $entry['status'];
            $entry['assignedTo'] = $request['assignedTo'] ?? ($entry['assignedTo'] ?? null);
            $entry['createdAt'] = $request['createdAt'] ?? ($entry['createdAt'] ?? now_kolkata()->format(DateTime::ATOM));
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'reqId' => $request['reqId'],
            'yojId' => $request['yojId'] ?? '',
            'offtdId' => $request['offtdId'] ?? '',
            'status' => $request['status'] ?? 'requested',
            'createdAt' => $request['createdAt'] ?? now_kolkata()->format(DateTime::ATOM),
            'assignedTo' => $request['assignedTo'] ?? null,
        ];
    }
    save_assisted_extraction_index($index);
}

function assisted_forbidden_fields_present(array $payload): bool
{
    $findings = assisted_detect_forbidden_pricing($payload);
    foreach ($findings as $finding) {
        if (($finding['blocked'] ?? true) === true) {
            return true;
        }
    }
    return false;
}

function assisted_validate_payload(array $payload): array
{
    $errors = [];
    $normalized = assisted_normalize_payload($payload);
    $missingKeys = assisted_detect_missing_keys($normalized);

    if (!is_array($normalized['tender'] ?? null)) {
        $errors[] = 'tender must be an object.';
    }

    $lists = $normalized['lists'] ?? [];
    foreach (['eligibilityDocs', 'annexures', 'formats', 'restricted'] as $listKey) {
        if (!is_array($lists[$listKey] ?? null)) {
            $errors[] = "lists.{$listKey} must be an array.";
        }
    }

    if (!is_array($normalized['checklist'] ?? null)) {
        $errors[] = 'checklist must be an array.';
    } else {
        foreach ($normalized['checklist'] as $item) {
            if (!is_array($item)) {
                $errors[] = 'Checklist entries must be objects.';
                break;
            }
            if (assisted_clean_string($item['title'] ?? '') === '') {
                $errors[] = 'Checklist items require a title.';
                break;
            }
        }
    }

    if (!is_array($normalized['templates'] ?? null)) {
        $errors[] = 'templates must be an array.';
    } else {
        foreach ($normalized['templates'] as $template) {
            if (!is_array($template)) {
                $errors[] = 'Template entries must be objects.';
                break;
            }
            if (assisted_clean_string($template['code'] ?? '') === '') {
                $errors[] = 'Template entries require a code.';
                break;
            }
            if (!in_array($template['type'] ?? 'other', ASSISTED_ALLOWED_TEMPLATE_TYPES, true)) {
                $errors[] = 'Template type is invalid.';
                break;
            }
        }
    }

    if ($missingKeys) {
        $errors[] = 'Missing required fields: ' . implode(', ', $missingKeys);
    }

    $forbiddenFindings = assisted_detect_forbidden_pricing($normalized);
    $blockedFindings = array_values(array_filter($forbiddenFindings, static function ($finding) {
        return ($finding['blocked'] ?? true) === true;
    }));
    if ($blockedFindings) {
        $errors[] = 'Pricing/rate content detected. Remove BOQ/quoted rates; tender fee/EMD/security amounts are allowed.';
    }
    $restrictedAnnexuresCount = is_array($normalized['lists']['restricted'] ?? null) ? count($normalized['lists']['restricted']) : 0;

    return [
        'errors' => $errors,
        'missingKeys' => $missingKeys,
        'forbiddenFindings' => $blockedFindings,
        'allFindings' => $forbiddenFindings,
        'restrictedAnnexuresCount' => $restrictedAnnexuresCount,
        'normalized' => $normalized,
    ];
}

function assisted_normalize_payload(array $payload): array
{
    $normalized = assisted_schema_defaults();
    $tenderInput = is_array($payload['tender'] ?? null) ? $payload['tender'] : [];

    $normalized['tender']['documentType'] = assisted_normalize_document_type(
        assisted_pick_first($payload, [['tender', 'documentType'], ['documentType']])
    );
    $normalized['tender']['tenderTitle'] = assisted_clean_nullable_string(assisted_pick_first($payload, [['tender', 'tenderTitle'], ['tenderTitle'], ['title'], ['meta', 'tenderTitle']]));
    $normalized['tender']['tenderNumber'] = assisted_clean_nullable_string(assisted_pick_first($payload, [['tender', 'tenderNumber'], ['tenderNumber'], ['meta', 'tenderNumber']]));
    $normalized['tender']['issuingAuthority'] = assisted_clean_nullable_string(assisted_pick_first($payload, [['tender', 'issuingAuthority'], ['issuingAuthority'], ['meta', 'issuingAuthority']]));
    $normalized['tender']['departmentName'] = assisted_clean_nullable_string(assisted_pick_first($payload, [['tender', 'departmentName'], ['departmentName'], ['deptName'], ['meta', 'departmentName']]));
    $normalized['tender']['location'] = assisted_clean_nullable_string(assisted_pick_first($payload, [['tender', 'location'], ['location'], ['meta', 'location']]));
    $normalized['tender']['submissionDeadline'] = assisted_clean_nullable_string(
        assisted_pick_first($payload, [['tender', 'submissionDeadline'], ['submissionDeadline'], ['dates', 'bidSubmissionDeadline'], ['dates', 'submissionDeadline']])
    );
    $normalized['tender']['openingDate'] = assisted_clean_nullable_string(
        assisted_pick_first($payload, [['tender', 'openingDate'], ['openingDate'], ['dates', 'bidOpeningDate'], ['dates', 'openingDate']])
    );
    $normalized['tender']['completionMonths'] = assisted_clean_numeric(
        assisted_pick_first($payload, [['tender', 'completionMonths'], ['completionMonths'], ['meta', 'estimatedCompletionMonths'], ['meta', 'completionMonths']])
    );
    $normalized['tender']['validityDays'] = assisted_clean_numeric(
        assisted_pick_first($payload, [['tender', 'validityDays'], ['validityDays'], ['bidValidityDays'], ['tender', 'bidValidityDays'], ['meta', 'bidValidityDays']])
    );

    $normalized['lists']['eligibilityDocs'] = assisted_normalize_string_list(
        assisted_pick_first($payload, [['lists', 'eligibilityDocs'], ['eligibilityDocs'], ['eligibility', 'mandatoryDocuments']], [])
    );
    $normalized['lists']['annexures'] = assisted_normalize_string_list(
        assisted_pick_first($payload, [['lists', 'annexures'], ['annexures'], ['formatsAndAnnexures', 'annexures']], [])
    );
    $normalized['lists']['formats'] = assisted_normalize_formats(
        assisted_pick_first($payload, [['lists', 'formats'], ['formats'], ['formatsAndAnnexures', 'forms'], ['formatsAndAnnexures', 'formats']], [])
    );
    $normalized['lists']['restricted'] = assisted_normalize_string_list(
        assisted_pick_first($payload, [['lists', 'restricted'], ['restrictedAnnexures']], [])
    );

    [$safeAnnexures, $safeFormats, $restrictedAnnexures] = assisted_split_restricted_annexures($normalized['lists']['annexures'], $normalized['lists']['formats']);
    $normalized['lists']['annexures'] = $safeAnnexures;
    $normalized['lists']['formats'] = $safeFormats;
    $normalized['lists']['restricted'] = array_values(array_unique(array_merge($normalized['lists']['restricted'], $restrictedAnnexures)));

    $normalized['checklist'] = assisted_normalize_checklist(
        assisted_pick_first($payload, [['checklist']], []),
        $normalized['lists']['eligibilityDocs']
    );

    $normalized['templates'] = assisted_normalize_templates(assisted_pick_first($payload, [['templates']], []));
    $normalized['snippets'] = assisted_normalize_snippets(assisted_pick_first($payload, [['snippets']], []));

    return $normalized;
}

function assisted_detect_missing_keys(array $payload): array
{
    $requiredPaths = [
        'tender' => [['tender']],
        'tender.submissionDeadline' => [['tender', 'submissionDeadline']],
        'tender.openingDate' => [['tender', 'openingDate']],
        'tender.completionMonths' => [['tender', 'completionMonths']],
        'tender.validityDays' => [['tender', 'validityDays']],
        'lists.eligibilityDocs' => [['lists', 'eligibilityDocs']],
        'lists.annexures' => [['lists', 'annexures']],
        'lists.formats' => [['lists', 'formats']],
        'checklist' => [['checklist']],
        'templates' => [['templates']],
        'snippets' => [['snippets']],
    ];

    $missing = [];
    foreach ($requiredPaths as $label => $paths) {
        $present = false;
        foreach ($paths as $path) {
            if (assisted_path_exists($payload, $path)) {
                $present = true;
                break;
            }
        }
        if (!$present) {
            $missing[] = $label;
        }
    }

    return $missing;
}

function assisted_pick_first(array $payload, array $paths, $default = null)
{
    foreach ($paths as $path) {
        if (assisted_path_exists($payload, $path)) {
            return assisted_get_path_value($payload, $path);
        }
    }
    return $default;
}

function assisted_get_path_value(array $payload, array $path)
{
    $cursor = $payload;
    foreach ($path as $segment) {
        if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
            return null;
        }
        $cursor = $cursor[$segment];
    }
    return $cursor;
}

function assisted_path_exists(array $payload, array $path): bool
{
    $cursor = $payload;
    foreach ($path as $segment) {
        if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
            return false;
        }
        $cursor = $cursor[$segment];
    }
    return true;
}

function assisted_clean_string($value): string
{
    if (!is_string($value) && !is_numeric($value)) {
        return '';
    }
    $string = (string)$value;
    $string = preg_replace('/^\xEF\xBB\xBF/', '', $string);
    $string = preg_replace('/[\x{2028}\x{2029}]/u', "\n", $string);
    $string = preg_replace('/[^\P{C}\n\r\t]+/u', ' ', $string);
    return trim((string)$string);
}

function assisted_clean_numeric($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_numeric((string)$value)) {
        return (int)$value;
    }
    return null;
}

function assisted_normalize_string_list($value): array
{
    $items = [];
    if (is_array($value)) {
        $items = $value;
    } elseif (is_string($value)) {
        $items = preg_split('/\r?\n/', $value) ?: [];
    }
    $clean = [];
    foreach ($items as $item) {
        if (is_array($item)) {
            $text = assisted_clean_string($item['name'] ?? ($item['title'] ?? ''));
        } else {
            $text = assisted_clean_string((string)$item);
        }
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

function assisted_clean_nullable_string($value): ?string
{
    $clean = assisted_clean_string($value);
    return $clean === '' ? null : $clean;
}

function assisted_normalize_document_type($value): string
{
    $clean = strtoupper(assisted_clean_string($value));
    foreach (ASSISTED_ALLOWED_DOCUMENT_TYPES as $type) {
        if (strtoupper($type) === $clean) {
            return $type;
        }
    }
    return 'Unknown';
}

function assisted_normalize_checklist_category($value): string
{
    $clean = assisted_clean_string($value);
    $candidate = ucwords(strtolower($clean));
    if (in_array($candidate, ASSISTED_ALLOWED_CHECKLIST_CATEGORIES, true)) {
        return $candidate;
    }
    return 'Other';
}

function assisted_normalize_formats($value): array
{
    $result = [];
    if (is_array($value)) {
        foreach ($value as $entry) {
            if (is_array($entry)) {
                $name = assisted_clean_string($entry['name'] ?? '');
                $notes = assisted_clean_string($entry['notes'] ?? '');
            } else {
                $name = assisted_clean_string((string)$entry);
                $notes = '';
            }
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
            $name = assisted_clean_string($line);
            if ($name === '') {
                continue;
            }
            $result[] = ['name' => $name, 'notes' => ''];
            if (count($result) >= 200) {
                break;
            }
        }
    }
    return $result;
}

function assisted_normalize_checklist($checklist, array $eligibilityDocs = []): array
{
    $normalized = [];

    if (is_array($checklist)) {
        foreach ($checklist as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = assisted_clean_string($item['title'] ?? '');
            if ($title === '') {
                continue;
            }
            $normalized[] = [
                'title' => $title,
                'category' => assisted_normalize_checklist_category($item['category'] ?? ''),
                'required' => (bool)($item['required'] ?? true),
                'notes' => assisted_clean_string($item['notes'] ?? ($item['description'] ?? '')),
                'source' => assisted_clean_string($item['source'] ?? ''),
                'sourceSnippet' => assisted_clean_string($item['sourceSnippet'] ?? ''),
            ];
            if (count($normalized) >= 300) {
                break;
            }
        }
    }

    if (empty($normalized)) {
        $eligibilityList = assisted_normalize_string_list($eligibilityDocs);
        foreach ($eligibilityList as $doc) {
            $normalized[] = [
                'title' => $doc,
                'category' => 'Eligibility',
                'required' => true,
                'notes' => '',
                'source' => 'assisted',
                'sourceSnippet' => '',
            ];
            if (count($normalized) >= 50) {
                break;
            }
        }
    }

    return $normalized;
}

function assisted_normalize_template_type($value): string
{
    $clean = strtolower(assisted_clean_string($value));
    foreach (ASSISTED_ALLOWED_TEMPLATE_TYPES as $type) {
        if ($clean === strtolower($type)) {
            return $type;
        }
    }
    return 'other';
}

function assisted_normalize_template_placeholders($placeholders): array
{
    $normalized = [];
    if (!is_array($placeholders)) {
        return $normalized;
    }
    foreach ($placeholders as $placeholder) {
        $prefill = true;
        if (is_array($placeholder)) {
            $key = assisted_clean_string($placeholder['key'] ?? '');
            if (array_key_exists('prefill', $placeholder)) {
                $prefill = (bool)$placeholder['prefill'];
            } elseif (!empty($placeholder['manual'])) {
                $prefill = false;
            }
        } else {
            $key = assisted_clean_string((string)$placeholder);
        }
        if ($key === '') {
            continue;
        }
        $normalized[] = ['key' => $key, 'prefill' => $prefill];
        if (count($normalized) >= 50) {
            break;
        }
    }
    return $normalized;
}

function assisted_normalize_templates($value): array
{
    $result = [];
    if (!is_array($value)) {
        return $result;
    }
    $index = 1;
    foreach ($value as $entry) {
        if (is_string($entry)) {
            $entry = ['name' => $entry, 'body' => ''];
        }
        if (!is_array($entry)) {
            continue;
        }
        $code = assisted_clean_string($entry['code'] ?? ($entry['id'] ?? ''));
        if ($code === '') {
            $code = 'Annexure-' . $index;
        }
        $name = assisted_clean_string($entry['name'] ?? ($entry['title'] ?? $code));
        $result[] = [
            'code' => $code,
            'name' => $name !== '' ? $name : $code,
            'type' => assisted_normalize_template_type($entry['type'] ?? 'other'),
            'placeholders' => assisted_normalize_template_placeholders($entry['placeholders'] ?? []),
            'body' => assisted_clean_string($entry['body'] ?? ''),
        ];
        $index++;
        if (count($result) >= 100) {
            break;
        }
    }
    return $result;
}

function assisted_normalize_snippets($value): array
{
    $snippets = [];
    if (is_array($value)) {
        foreach ($value as $entry) {
            if (is_array($entry)) {
                $text = assisted_clean_string($entry['text'] ?? ($entry['snippet'] ?? ''));
            } else {
                $text = assisted_clean_string((string)$entry);
            }
            if ($text === '') {
                continue;
            }
            $snippets[] = $text;
            if (count($snippets) >= 200) {
                break;
            }
        }
    } elseif (is_string($value)) {
        $text = assisted_clean_string($value);
        if ($text !== '') {
            $snippets[] = $text;
        }
    }
    return $snippets;
}

function assisted_split_restricted_annexures(array $annexures, array $formats): array
{
    $restricted = [];
    $safeAnnexures = [];
    foreach ($annexures as $annexure) {
        $label = is_array($annexure) ? ($annexure['name'] ?? ($annexure['title'] ?? '')) : (string)$annexure;
        if ($label === '') {
            continue;
        }
        if (assisted_is_restricted_financial_label(mb_strtolower($label))) {
            $restricted[] = $label;
            continue;
        }
        $safeAnnexures[] = is_array($annexure) ? $annexure : $label;
        if (count($safeAnnexures) >= 200) {
            break;
        }
    }

    $safeFormats = [];
    foreach ($formats as $format) {
        $name = $format['name'] ?? ($format['title'] ?? '');
        if (assisted_is_restricted_financial_label(mb_strtolower($name))) {
            $restricted[] = $name;
            continue;
        }
        $safeFormats[] = $format;
        if (count($safeFormats) >= 200) {
            break;
        }
    }

    return [$safeAnnexures, $safeFormats, $restricted];
}

function assisted_detect_forbidden_pricing(array $payload, string $path = 'root', array $context = []): array
{
    $findings = [];
    $skipKeys = ['bidvaliditydays', 'validitydays'];
    $allowPathSegments = ['fees', 'emd', 'tenderfee', 'securitydeposit', 'performanceguarantee', 'pg', 'sd', 'security', 'guarantee', 'deposit', 'earnest', 'bankguarantee'];
    $blockPathTokens = ['boq', 'rate', 'quoted', 'financialbid', 'priceschedule', 'bidamount', 'l1', 'pricebid', 'sor'];

    foreach ($payload as $key => $value) {
        $keyString = (string)$key;
        $keyLower = strtolower($keyString);
        $currentPath = $path === '' ? $keyString : $path . '.' . $keyString;

        if (in_array($keyLower, $skipKeys, true)) {
            continue;
        }

        $pathSegments = explode('.', strtolower($currentPath));
        $pathHasAllowContext = assisted_path_has_allowed_segment($pathSegments, $allowPathSegments) || !empty($context['allowCurrency']);
        $isAnnexurePath = assisted_is_annexure_like_path($pathSegments);

        if (!$pathHasAllowContext) {
            foreach ($blockPathTokens as $token) {
                if (str_contains($keyLower, $token)) {
                    $findings[] = [
                        'path' => $currentPath,
                        'reasonCode' => 'BLOCK_RATE_CONTEXT',
                        'snippet' => assisted_redact_snippet($keyString),
                        'blocked' => true,
                    ];
                    break;
                }
            }
        }

        if (is_string($value)) {
            $stringFinding = assisted_evaluate_string_forbidden($value, $currentPath, $pathHasAllowContext, [
                'allowCurrency' => !empty($context['allowCurrency']),
                'isAnnexure' => $isAnnexurePath,
            ]);
            if ($stringFinding) {
                $findings[] = $stringFinding;
            }
        } elseif (is_array($value)) {
            $nextContext = $context;
            if (assisted_path_is_checklist_item($pathSegments) && assisted_checklist_item_allows_currency($value)) {
                $nextContext['allowCurrency'] = true;
            }
            if ($pathHasAllowContext) {
                $nextContext['allowCurrency'] = true;
            }
            $findings = array_merge($findings, assisted_detect_forbidden_pricing($value, $currentPath, $nextContext));
        }
    }

    return $findings;
}

function assisted_evaluate_string_forbidden(string $value, string $path, bool $pathHasAllowContext, array $context = []): ?array
{
    $lower = mb_strtolower($value);
    $hasBlock = assisted_contains_block_marker($lower);
    $hasCurrency = assisted_contains_currency_marker($lower);
    $hasAllowContext = $pathHasAllowContext || assisted_contains_allow_marker($lower) || !empty($context['allowCurrency']);
    $isAnnexure = !empty($context['isAnnexure']);
    $isRestrictedLabel = assisted_is_restricted_financial_label($lower);

    if ($isRestrictedLabel && $isAnnexure) {
        return [
            'path' => $path,
            'reasonCode' => 'RESTRICTED_FINANCIAL_ANNEXURE',
            'snippet' => assisted_redact_snippet($value),
            'blocked' => false,
        ];
    }

    if ($hasBlock) {
        return [
            'path' => $path,
            'reasonCode' => 'BLOCK_RATE_CONTEXT',
            'snippet' => assisted_redact_snippet($value),
            'blocked' => true,
        ];
    }

    if (!$hasCurrency) {
        return null;
    }

    if ($hasAllowContext) {
        return [
            'path' => $path,
            'reasonCode' => 'CURRENCY_ALLOWED_FEE_CONTEXT',
            'snippet' => assisted_redact_snippet($value),
            'blocked' => false,
        ];
    }

    return [
        'path' => $path,
        'reasonCode' => 'CURRENCY_UNKNOWN_CONTEXT',
        'snippet' => assisted_redact_snippet($value),
        'blocked' => true,
    ];
}

function assisted_contains_currency_marker(string $value): bool
{
    return (bool)preg_match('/(₹|\bRs\.?\b|\bINR\b)/iu', $value);
}

function assisted_contains_allow_marker(string $value): bool
{
    $patterns = [
        '/\bemd\b/i',
        '/earnest\s+money/i',
        '/earnest\s+money\s+deposit/i',
        '/bid\s+security/i',
        '/security\s+money/i',
        '/\bdeposit\b/i',
        '/\bsd\b/i',
        '/security\s+deposit/i',
        '/\bpg\b/i',
        '/performance\s+guarantee/i',
        '/\bbg\b/i',
        '/tender\s+fee/i',
        '/tender\s+cost/i',
        '/cost\s+of\s+tender/i',
        '/document\s+fee/i',
        '/processing\s+fee/i',
        '/\bgst\b/i',
        '/bank\s+guarantee/i',
        '/performance\s+security/i',
        '/security\s+amount/i',
        '/security\s+fee/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $value)) {
            return true;
        }
    }

    return false;
}

function assisted_contains_block_marker(string $value): bool
{
    $patterns = [
        '/\bboq\b/i',
        '/bill\s+of\s+quantity/i',
        '/schedule\s+of\s+rates/i',
        '/\bsor\b/i',
        '/item\s+rate/i',
        '/unit\s+rate/i',
        '/rate\s+quoted/i',
        '/quoted\s+rate/i',
        '/financial\s+bid/i',
        '/price\s+bid/i',
        '/bid\s+value/i',
        '/total\s+value/i',
        '/contract\s+value/i',
        '/\bl1\b/i',
        '/lowest/i',
        '/comparative\s+statement/i',
        '/\bcs\b/i',
        '/price\s+schedule/i',
        '/rate\s+sheet/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $value)) {
            return true;
        }
    }

    return false;
}

function assisted_is_restricted_financial_label(string $lower): bool
{
    $patterns = [
        '/price\s+bid/i',
        '/financial\s+bid/i',
        '/\bboq\b/i',
        '/bill\s+of\s+quantity/i',
        '/schedule\s+of\s+rates/i',
        '/\bsor\b/i',
        '/rate\s+sheet/i',
        '/price\s+schedule/i',
        '/price\s+quotation/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $lower)) {
            return true;
        }
    }
    return false;
}

function assisted_is_annexure_like_path(array $segments): bool
{
    return in_array('annexures', $segments, true)
        || in_array('formats', $segments, true)
        || in_array('formatsandannexures', $segments, true)
        || in_array('restricted', $segments, true);
}

function assisted_path_is_checklist_item(array $segments): bool
{
    return in_array('checklist', $segments, true);
}

function assisted_checklist_item_allows_currency(array $item): bool
{
    $category = mb_strtolower(trim((string)($item['category'] ?? '')));
    if ($category !== '' && $category === 'fees') {
        $title = mb_strtolower((string)($item['title'] ?? ''));
        $desc = mb_strtolower((string)($item['description'] ?? ($item['notes'] ?? '')));
        $quote = mb_strtolower((string)($item['sourceQuote'] ?? ''));
        if (assisted_contains_allow_marker($title) || assisted_contains_allow_marker($desc) || assisted_contains_allow_marker($quote)) {
            return true;
        }
    }
    return false;
}

function assisted_path_has_allowed_segment(array $segments, array $allowed): bool
{
    foreach ($segments as $segment) {
        if (in_array($segment, $allowed, true)) {
            return true;
        }
    }
    return false;
}

function assisted_redact_snippet(string $value): string
{
    $clean = assisted_clean_string($value);
    if (mb_strlen($clean) > 160) {
        return mb_substr($clean, 0, 157) . '...';
    }
    return $clean;
}

function assisted_sanitize_json_input(string $input): string
{
    $result = sanitize_ai_json_input($input);
    return $result['sanitized'] ?? (string)$input;
}

function assisted_payload_path(string $yojId, string $offtdId): string
{
    return offline_tender_dir($yojId, $offtdId) . '/assisted.json';
}

function assisted_payload_history_path(string $yojId, string $offtdId, string $reqId): string
{
    return offline_tender_dir($yojId, $offtdId) . '/assisted_history/' . $reqId . '.json';
}

function assisted_persist_payload(string $yojId, string $offtdId, string $reqId, array $payload, array $actor): array
{
    $meta = [
        'reqId' => $reqId,
        'payloadPath' => assisted_payload_path($yojId, $offtdId),
        'deliveredAt' => now_kolkata()->format(DateTime::ATOM),
        'deliveredBy' => assisted_actor_label($actor),
        'restrictedCount' => count($payload['lists']['restricted'] ?? []),
    ];

    writeJsonAtomic($meta['payloadPath'], [
        'meta' => $meta,
        'payload' => $payload,
    ]);
    writeJsonAtomic(assisted_payload_history_path($yojId, $offtdId, $reqId), [
        'meta' => $meta,
        'payload' => $payload,
    ]);

    return $meta;
}

function assisted_link_payload_to_entities(string $yojId, string $offtdId, array $meta): void
{
    $tender = load_offline_tender($yojId, $offtdId);
    if ($tender) {
        $tender['assisted'] = [
            'reqId' => $meta['reqId'] ?? null,
            'deliveredAt' => $meta['deliveredAt'] ?? null,
            'deliveredBy' => $meta['deliveredBy'] ?? null,
            'payloadPath' => $meta['payloadPath'] ?? null,
            'restrictedCount' => $meta['restrictedCount'] ?? 0,
        ];
        $tender['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
        save_offline_tender($tender);
    }

    $packSummary = find_pack_by_source($yojId, 'OFFTD', $offtdId);
    if ($packSummary && !empty($packSummary['packId'])) {
        $pack = load_pack($yojId, $packSummary['packId']);
        if ($pack) {
            $pack['assisted'] = $meta;
            save_pack($pack);
        }
    }
}

function assisted_append_audit(array &$request, string $by, string $action, ?string $note = null): void
{
    $request['audit'][] = [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'by' => $by,
        'action' => $action,
        'note' => $note ? mb_substr($note, 0, 500) : null,
    ];
}

function assisted_staff_actor(): array
{
    $user = current_user();
    if ($user && ($user['type'] ?? '') === 'superadmin') {
        return ['type' => 'superadmin', 'id' => $user['username'] ?? 'superadmin'];
    }
    $employee = current_employee_record();
    if ($employee && (employee_has_permission($employee, 'tickets') || employee_has_permission($employee, 'reset_approvals'))) {
        return ['type' => 'employee', 'id' => $employee['empId'] ?? 'employee', 'record' => $employee];
    }
    redirect('/auth/login.php');
}

function assisted_actor_label(array $actor): string
{
    if (($actor['type'] ?? '') === 'superadmin') {
        return 'superadmin';
    }
    if (($actor['type'] ?? '') === 'employee') {
        $record = $actor['record'] ?? [];
        return ($record['username'] ?? 'employee') . ' (' . ($record['role'] ?? '') . ')';
    }
    return 'system';
}

function assisted_assign_request(array &$request, array $actor): void
{
    if (($request['assignedTo'] ?? null) === null) {
        $request['assignedTo'] = $actor['id'] ?? null;
    }
}

function assisted_deliver_notification(array $request): void
{
    if (empty($request['yojId']) || empty($request['offtdId'])) {
        return;
    }
    create_contractor_notification($request['yojId'], [
        'type' => 'info',
        'title' => 'Assisted extraction delivered',
        'message' => 'Checklist ready for tender ' . ($request['offtdId'] ?? ''),
    ]);
}

function assisted_external_prompt(array $tender = []): string
{
    $schema = [
        'tender' => [
            'documentType' => 'NIT|NIB|Tender|Unknown',
            'tenderTitle' => 'Tender name',
            'tenderNumber' => 'NIT/NIB no.',
            'issuingAuthority' => 'Issuing authority',
            'departmentName' => 'Department',
            'location' => 'District/Location',
            'submissionDeadline' => '2025-01-10T15:00:00+05:30',
            'openingDate' => '2025-01-12T11:00:00+05:30',
            'completionMonths' => 12,
            'validityDays' => 90,
        ],
        'lists' => [
            'eligibilityDocs' => ['GST certificate', 'PAN', 'Work completion certificates'],
            'annexures' => ['Annexure I – Declaration', 'Power of Attorney'],
            'formats' => ['Technical format', 'Experience format'],
            'restricted' => ['Financial Bid / BOQ'],
        ],
        'checklist' => [
            [
                'title' => 'Upload GST certificate',
                'category' => 'Eligibility',
                'required' => true,
                'notes' => 'Valid and readable copy',
                'source' => 'tender_pdf',
            ],
        ],
        'templates' => [
            [
                'code' => 'Annexure-1',
                'name' => 'Cover Letter',
                'type' => 'cover_letter',
                'placeholders' => ['firmName', 'tenderTitle', 'tenderNumber', 'departmentName', 'signatory', 'designation', 'date', 'place'],
                'body' => "To,\n{{departmentName}}\nSubject: Submission of {{tenderTitle}} ({{tenderNumber}})\n\nRespected Sir/Madam,\nWe, {{firmName}}, are submitting our documents for the above tender.\n\nAuthorized Signatory\n{{signatory}}\n{{designation}}\nDate: {{date}}\nPlace: {{place}}",
            ],
        ],
        'snippets' => ['Tender fee Rs. 5,000 online', 'Portal: https://example-portal.in/tender/123'],
    ];

    $contextParts = [];
    if (!empty($tender['title'])) {
        $contextParts[] = 'Tender: ' . $tender['title'];
    }
    if (!empty($tender['id'])) {
        $contextParts[] = 'Offline ID: ' . $tender['id'];
    }
    if (!empty($tender['location'])) {
        $contextParts[] = 'Location: ' . $tender['location'];
    }

    $context = $contextParts ? 'Context: ' . implode(' | ', $contextParts) . ".\n" : '';

    return $context . 'Extract tender details and return ONLY JSON using this schema: '
        . json_encode($schema, JSON_UNESCAPED_SLASHES)
        . '. Rules: timezone Asia/Kolkata; leave values null if unknown; include all top-level keys even when empty; '
        . 'never include quoted rates/BOQ/price schedules. If any annexure/format mentions Price Bid/Financial Bid/BOQ/SOR, move the label into lists.restricted and keep annexures clean. '
        . 'Tender fee/EMD/deposit amounts are allowed; do not ask for bidder’s quoted rates. No markdown, no prose. '
        . 'Snippets array must be single-line strings (escape newlines as \\n). Do not use smart quotes.';
}
