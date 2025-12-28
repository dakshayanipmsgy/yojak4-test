<?php
declare(strict_types=1);

const ASSISTED_EXTRACTION_DIR = DATA_PATH . '/support/assisted_extraction';
const ASSISTED_EXTRACTION_INDEX = ASSISTED_EXTRACTION_DIR . '/index.json';
const ASSISTED_EXTRACTION_LOG = DATA_PATH . '/logs/assisted_extraction.log';
const ASSISTED_REQUIRED_FIELDS = ['submissionDeadline', 'openingDate', 'completionMonths', 'bidValidityDays', 'eligibilityDocs', 'annexures', 'formats'];

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
    return !empty($findings);
}

function assisted_validate_payload(array $payload): array
{
    $mapped = assisted_map_payload_schema($payload);
    $missingKeys = assisted_detect_missing_keys($payload);

    $errors = [];

    foreach (['eligibilityDocs', 'annexures'] as $listField) {
        if ($mapped[$listField] !== null && !is_array($mapped[$listField]) && !is_string($mapped[$listField])) {
            $errors[] = "{$listField} must be an array or newline separated text.";
        }
    }
    if ($mapped['formats'] !== null && !is_array($mapped['formats']) && !is_string($mapped['formats'])) {
        $errors[] = 'formats must be an array, list of objects, or newline separated text.';
    }
    if (!is_array($mapped['checklist'] ?? [])) {
        $errors[] = 'checklist must be an array when provided.';
    }

    if (($mapped['completionMonths'] ?? null) !== null && $mapped['completionMonths'] !== '' && !is_numeric((string)$mapped['completionMonths'])) {
        $errors[] = 'completionMonths must be numeric or null.';
    }
    if (($mapped['bidValidityDays'] ?? null) !== null && $mapped['bidValidityDays'] !== '' && !is_numeric((string)$mapped['bidValidityDays'])) {
        $errors[] = 'bidValidityDays must be numeric or null.';
    }

    $normalized = assisted_normalize_payload($mapped);

    if ($missingKeys) {
        $errors[] = 'Missing required fields: ' . implode(', ', $missingKeys);
    }

    $forbiddenFindings = assisted_detect_forbidden_pricing($mapped);
    if ($forbiddenFindings) {
        $errors[] = 'Pricing/rate content detected. Remove BOQ/quoted rates; tender fee/EMD/security amounts are allowed.';
    }

    return [
        'errors' => $errors,
        'missingKeys' => $missingKeys,
        'forbiddenFindings' => $forbiddenFindings,
        'normalized' => $normalized,
    ];
}

function assisted_normalize_payload(array $payload): array
{
    $normalized = [
        'submissionDeadline' => assisted_clean_string($payload['submissionDeadline'] ?? null) ?: null,
        'openingDate' => assisted_clean_string($payload['openingDate'] ?? null) ?: null,
        'completionMonths' => assisted_clean_numeric($payload['completionMonths'] ?? null),
        'bidValidityDays' => assisted_clean_numeric($payload['bidValidityDays'] ?? null),
        'eligibilityDocs' => assisted_normalize_string_list($payload['eligibilityDocs'] ?? []),
        'annexures' => assisted_normalize_string_list($payload['annexures'] ?? []),
        'formats' => assisted_normalize_formats($payload['formats'] ?? []),
        'checklist' => assisted_normalize_checklist($payload),
    ];

    return $normalized;
}

function assisted_detect_missing_keys(array $payload): array
{
    $checks = [
        'submissionDeadline' => [['submissionDeadline'], ['dates', 'bidSubmissionDeadline'], ['dates', 'submissionDeadline']],
        'openingDate' => [['openingDate'], ['dates', 'bidOpeningDate'], ['dates', 'openingDate']],
        'completionMonths' => [['completionMonths'], ['meta', 'estimatedCompletionMonths'], ['meta', 'completionMonths']],
        'bidValidityDays' => [['bidValidityDays'], ['meta', 'bidValidityDays']],
        'eligibilityDocs' => [['eligibilityDocs'], ['eligibility', 'mandatoryDocuments']],
        'annexures' => [['annexures'], ['formatsAndAnnexures', 'annexures']],
        'formats' => [['formats'], ['formatsAndAnnexures', 'forms'], ['formatsAndAnnexures', 'formats']],
    ];

    $missing = [];
    foreach ($checks as $key => $paths) {
        $present = false;
        foreach ($paths as $path) {
            if (assisted_path_exists($payload, $path)) {
                $present = true;
                break;
            }
        }
        if (!$present) {
            $missing[] = $key;
        }
    }

    return $missing;
}

function assisted_map_payload_schema(array $payload): array
{
    $mapped = [
        'submissionDeadline' => assisted_pick_first($payload, [['submissionDeadline'], ['dates', 'bidSubmissionDeadline'], ['dates', 'submissionDeadline']]),
        'openingDate' => assisted_pick_first($payload, [['openingDate'], ['dates', 'bidOpeningDate'], ['dates', 'openingDate']]),
        'completionMonths' => assisted_pick_first($payload, [['completionMonths'], ['meta', 'estimatedCompletionMonths'], ['meta', 'completionMonths']]),
        'bidValidityDays' => assisted_pick_first($payload, [['bidValidityDays'], ['meta', 'bidValidityDays']]),
        'eligibilityDocs' => assisted_pick_first($payload, [['eligibilityDocs'], ['eligibility', 'mandatoryDocuments']], []),
        'annexures' => assisted_pick_first($payload, [['annexures'], ['formatsAndAnnexures', 'annexures']], []),
        'formats' => assisted_pick_first($payload, [['formats'], ['formatsAndAnnexures', 'forms'], ['formatsAndAnnexures', 'formats']], []),
        'checklist' => assisted_pick_first($payload, [['checklist']], []),
    ];

    return $mapped;
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
        $text = assisted_clean_string((string)$item);
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

function assisted_normalize_checklist(array $payload): array
{
    $checklist = $payload['checklist'] ?? [];
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
                'description' => assisted_clean_string($item['description'] ?? ''),
                'required' => (bool)($item['required'] ?? true),
            ];
            if (count($normalized) >= 300) {
                break;
            }
        }
    }

    if (empty($normalized)) {
        $eligibilityDocs = assisted_normalize_string_list($payload['eligibilityDocs'] ?? []);
        foreach ($eligibilityDocs as $doc) {
            $normalized[] = [
                'title' => $doc,
                'description' => '',
                'required' => true,
            ];
            if (count($normalized) >= 50) {
                break;
            }
        }
    }

    return $normalized;
}

function assisted_detect_forbidden_pricing(array $payload, string $path = 'root'): array
{
    $findings = [];
    $skipKeys = ['bidvaliditydays'];
    $allowPathSegments = ['fees', 'emd', 'tenderfee', 'securitydeposit', 'performanceguarantee', 'pg', 'sd'];
    $blockPathTokens = ['boq', 'rate', 'quoted', 'financialbid', 'priceschedule', 'bidamount', 'l1'];

    foreach ($payload as $key => $value) {
        $keyString = (string)$key;
        $keyLower = strtolower($keyString);
        $currentPath = $path === '' ? $keyString : $path . '.' . $keyString;

        if (in_array($keyLower, $skipKeys, true)) {
            continue;
        }

        $pathSegments = explode('.', strtolower($currentPath));
        $pathHasAllowContext = assisted_path_has_allowed_segment($pathSegments, $allowPathSegments);

        if (!$pathHasAllowContext) {
            foreach ($blockPathTokens as $token) {
                if (str_contains($keyLower, $token)) {
                    $findings[] = [
                        'path' => $currentPath,
                        'reasonCode' => 'BLOCK_RATE_CONTEXT',
                        'snippet' => assisted_redact_snippet($keyString),
                    ];
                    break;
                }
            }
        }

        if (is_string($value)) {
            $stringFinding = assisted_evaluate_string_forbidden($value, $currentPath, $pathHasAllowContext);
            if ($stringFinding) {
                $findings[] = $stringFinding;
            }
        } elseif (is_array($value)) {
            $findings = array_merge($findings, assisted_detect_forbidden_pricing($value, $currentPath));
        }
    }

    return $findings;
}

function assisted_evaluate_string_forbidden(string $value, string $path, bool $pathHasAllowContext): ?array
{
    $lower = mb_strtolower($value);
    $hasBlock = assisted_contains_block_marker($lower);
    $hasCurrency = assisted_contains_currency_marker($lower);
    $hasAllowContext = $pathHasAllowContext || assisted_contains_allow_marker($lower);

    if ($hasBlock) {
        return [
            'path' => $path,
            'reasonCode' => 'BLOCK_RATE_CONTEXT',
            'snippet' => assisted_redact_snippet($value),
        ];
    }

    if (!$hasCurrency) {
        return null;
    }

    if ($hasAllowContext) {
        return null;
    }

    return [
        'path' => $path,
        'reasonCode' => 'CURRENCY_UNKNOWN_CONTEXT',
        'snippet' => assisted_redact_snippet($value),
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
        '/bid\s+security/i',
        '/security\s+money/i',
        '/\bdeposit\b/i',
        '/\bsd\b/i',
        '/security\s+deposit/i',
        '/\bpg\b/i',
        '/performance\s+guarantee/i',
        '/tender\s+fee/i',
        '/tender\s+cost/i',
        '/cost\s+of\s+tender/i',
        '/document\s+fee/i',
        '/processing\s+fee/i',
        '/\bgst\b/i',
        '/bank\s+guarantee/i',
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
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $value)) {
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
    $sanitized = preg_replace('/^\xEF\xBB\xBF/', '', $input);
    $sanitized = preg_replace('/[\x{2028}\x{2029}]/u', "\n", (string)$sanitized);
    return (string)$sanitized;
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
