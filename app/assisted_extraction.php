<?php
declare(strict_types=1);

const ASSISTED_EXTRACTION_DIR = DATA_PATH . '/support/assisted_extraction';
const ASSISTED_EXTRACTION_INDEX = ASSISTED_EXTRACTION_DIR . '/index.json';
const ASSISTED_EXTRACTION_LOG = DATA_PATH . '/logs/assisted_extraction.log';
const ASSISTED_REQUIRED_FIELDS = ['tender', 'lists', 'checklist'];

function assisted_clean_string($value): ?string
{
    if (is_array($value)) {
        return null;
    }
    $text = trim((string)$value);
    if ($text === '') {
        return null;
    }
    if (mb_strlen($text) > 1000) {
        $text = mb_substr($text, 0, 1000);
    }
    return $text;
}

function assisted_clean_numeric($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_numeric($value)) {
        return (int)round((float)$value);
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
        $text = assisted_clean_string($item);
        if ($text === null) {
            continue;
        }
        $key = mb_strtolower($text);
        if (isset($clean[$key])) {
            continue;
        }
        $clean[$key] = $text;
        if (count($clean) >= 200) {
            break;
        }
    }
    return array_values($clean);
}

function assisted_normalize_formats($value): array
{
    $result = [];
    if (is_array($value)) {
        foreach ($value as $entry) {
            if (is_string($entry)) {
                $name = assisted_clean_string($entry);
                if ($name === null) {
                    continue;
                }
                $result[] = ['name' => $name, 'notes' => ''];
            } elseif (is_array($entry)) {
                $name = assisted_clean_string($entry['name'] ?? '');
                $notes = assisted_clean_string($entry['notes'] ?? '') ?? '';
                if ($name === null) {
                    continue;
                }
                $result[] = ['name' => $name, 'notes' => $notes];
            }
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

function assisted_normalize_fees($value): array
{
    $fees = is_array($value) ? $value : [];
    return [
        'tenderFeeText' => assisted_clean_string($fees['tenderFeeText'] ?? ''),
        'emdText' => assisted_clean_string($fees['emdText'] ?? ''),
        'sdText' => assisted_clean_string($fees['sdText'] ?? ''),
        'pgText' => assisted_clean_string($fees['pgText'] ?? ''),
    ];
}

function assisted_map_payload_schema(array $payload): array
{
    return [
        'submissionDeadline' => $payload['submissionDeadline'] ?? null,
        'openingDate' => $payload['openingDate'] ?? null,
        'completionMonths' => $payload['completionMonths'] ?? null,
        'bidValidityDays' => $payload['bidValidityDays'] ?? null,
        'eligibilityDocs' => $payload['eligibilityDocs'] ?? [],
        'annexures' => $payload['annexures'] ?? [],
        'formats' => $payload['formats'] ?? [],
        'checklist' => $payload['checklist'] ?? [],
        'fees' => $payload['fees'] ?? [],
    ];
}

function assisted_schema_v2_structure(): array
{
    return [
        'tender' => [
            'documentType' => 'string',
            'tenderTitle' => 'string|null',
            'tenderNumber' => 'string|null',
            'issuingAuthority' => 'string|null',
            'departmentName' => 'string|null',
            'location' => 'string|null',
            'submissionDeadline' => 'string|null',
            'openingDate' => 'string|null',
            'completionMonths' => 'number|null',
            'validityDays' => 'number|null',
        ],
        'lists' => [
            'eligibilityDocs' => 'array',
            'annexures' => 'array',
            'formats' => 'array',
            'restricted' => 'array',
        ],
        'fees' => 'object',
        'checklist' => 'array',
        'templates' => 'array',
        'snippets' => 'array',
    ];
}

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

function assisted_deliver_payload(string $reqId, string $yojId, string $offtdId, array $payload, string $staffYojId): void
{
    // 1. Validate (should be clean if staff pasted it, but double check)
    $validation = assisted_validate_payload($payload);
    if (!empty($validation['errors'])) {
        throw new RuntimeException('Payload validation failed: ' . implode(', ', $validation['errors']));
    }
    
    // 2. Define path
    // /data/contractors/approved/<yojId>/tenders_offline/<offtdId>/assisted.json
    $targetDir = contractors_approved_path($yojId) . '/tenders_offline/' . $offtdId;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }
    $targetPath = $targetDir . '/assisted.json';
    
    $finalPayload = $validation['normalized'];
    $finalPayload['meta'] = [
        'deliveredAt' => now_kolkata()->format(DateTime::ATOM),
        'deliveredBy' => $staffYojId,
        'reqId' => $reqId,
    ];
    
    // 3. Save Atomic
    writeJsonAtomic($targetPath, $finalPayload);
    
    // 4. Update Tender to link it
    $tender = load_offline_tender($yojId, $offtdId);
    if ($tender) {
        // Merge payload into tender
        $tender['assisted'] = [
            'deliveredAt' => now_kolkata()->format(DateTime::ATOM),
            'deliveredBy' => $staffYojId,
            'payloadPath' => $targetPath,
        ];
        
        // Map V2 Payload to Tender Fields
        $tData = $finalPayload['tender'] ?? [];
        $lData = $finalPayload['lists'] ?? [];
        
        $tender['title'] = !empty($tData['tenderTitle']) ? $tData['tenderTitle'] : ($tender['title'] ?? 'Untitled');
        $tender['location'] = $tData['location'] ?? ($tender['location'] ?? null);
        $tender['tenderNumber'] = $tData['tenderNumber'] ?? ($tender['tenderNumber'] ?? null);
        $tender['authority'] = $tData['issuingAuthority'] ?? ($tender['authority'] ?? null);
        
        if (!isset($tender['extracted'])) { $tender['extracted'] = []; }
        $tender['extracted']['submissionDeadline'] = $tData['submissionDeadline'] ?? ($tender['extracted']['submissionDeadline'] ?? null);
        $tender['extracted']['openingDate'] = $tData['openingDate'] ?? ($tender['extracted']['openingDate'] ?? null);
        $tender['extracted']['completionMonths'] = $tData['completionMonths'] ?? ($tender['extracted']['completionMonths'] ?? null);
        $tender['extracted']['bidValidityDays'] = $tData['validityDays'] ?? ($tender['extracted']['bidValidityDays'] ?? null);

        $tender['checklist'] = $finalPayload['checklist'] ?? ($tender['checklist'] ?? []);
        $tender['annexures'] = $lData['annexures'] ?? ($tender['annexures'] ?? []);
        $tender['formats'] = $lData['formats'] ?? ($tender['formats'] ?? []);
        $tender['eligibilityDocs'] = $lData['eligibilityDocs'] ?? ($tender['eligibilityDocs'] ?? []);
        $tender['restrictedAnnexures'] = $lData['restricted'] ?? ($tender['restrictedAnnexures'] ?? []);

        // Also store templates if any (though usually pack handles them)
        if (!empty($finalPayload['templates'])) {
             $tender['suggestedTemplates'] = $finalPayload['templates'];
        }

        save_offline_tender($tender);
    }
    
    // 5. Update Request Status
    $request = assisted_load_request($reqId);
    if ($request) {
        $request['status'] = 'delivered';
        $request['deliveredAt'] = now_kolkata()->format(DateTime::ATOM);
        $request['assistantDraft'] = $finalPayload; // Also save copy in request for history/debugging
        $request['assistantDeliveredPayload'] = $finalPayload;
        $request['restrictedAnnexures'] = $finalPayload['lists']['restricted'] ?? [];
        assisted_save_request($request);
    }
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
    // Normalize first to handle legacy/flat structures if needed
    $normalized = assisted_normalize_payload($payload);
    
    $errors = [];
    $missingKeys = [];
    
    // Check top-level structure
    $schema = assisted_schema_v2_structure();
    foreach ($schema as $key => $type) {
        if (!array_key_exists($key, $normalized)) {
             // For tolerance, we populate missing keys in normalize, but strictly speaking they are part of the schema
             // If normalized failed to populate them, it's an error.
             $errors[] = "Missing top-level key: $key";
             $missingKeys[] = $key;
        }
    }

    if (!empty($errors)) {
        return [
            'errors' => $errors,
            'missingKeys' => $missingKeys,
            'forbiddenFindings' => [],
            'allFindings' => [],
            'restrictedAnnexuresCount' => 0,
            'normalized' => $normalized,
        ];
    }

    // Deep validation
    // Tender fields
    $tender = $normalized['tender'] ?? [];
    if (!is_array($tender)) { $errors[] = 'tender must be an object'; }
    
    // Lists
    $lists = $normalized['lists'] ?? [];
    if (!is_array($lists)) { $errors[] = 'lists must be an object'; }
    
    // Forbidden Check (Pricing)
    $forbiddenFindings = assisted_detect_forbidden_pricing($normalized);
    $blockedFindings = array_values(array_filter($forbiddenFindings, static function ($finding) {
        return ($finding['blocked'] ?? true) === true;
    }));
    
    if ($blockedFindings) {
        $errors[] = 'Pricing/rate content detected. Remove BOQ/quoted rates; tender fee/EMD/security amounts are allowed.';
    }

    $restrictedAnnexuresCount = count($lists['restricted'] ?? []);

    return [
        'errors' => $errors,
        'missingKeys' => [],
        'forbiddenFindings' => $blockedFindings,
        'allFindings' => $forbiddenFindings,
        'restrictedAnnexuresCount' => $restrictedAnnexuresCount,
        'normalized' => $normalized,
    ];
}

function assisted_normalize_payload(array $payload): array
{
    // Handle V2 vs V1 inputs
    $isV2 = isset($payload['tender']) && isset($payload['lists']);
    
    // Defaults structure
    $normalized = [
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
        'fees' => assisted_normalize_fees($payload['fees'] ?? []),
        'checklist' => [],
        'templates' => [],
        'snippets' => $payload['snippets'] ?? [],
    ];

    if ($isV2) {
        // Direct mapping with sanitization
        $srcTender = $payload['tender'] ?? [];
        $srcLists = $payload['lists'] ?? [];
        
        $normalized['tender']['documentType'] = assisted_clean_string($srcTender['documentType'] ?? 'Unknown');
        $normalized['tender']['tenderTitle'] = assisted_clean_string($srcTender['tenderTitle'] ?? null);
        $normalized['tender']['tenderNumber'] = assisted_clean_string($srcTender['tenderNumber'] ?? null);
        $normalized['tender']['issuingAuthority'] = assisted_clean_string($srcTender['issuingAuthority'] ?? null);
        $normalized['tender']['departmentName'] = assisted_clean_string($srcTender['departmentName'] ?? null);
        $normalized['tender']['location'] = assisted_clean_string($srcTender['location'] ?? null);
        $normalized['tender']['submissionDeadline'] = assisted_clean_string($srcTender['submissionDeadline'] ?? null);
        $normalized['tender']['openingDate'] = assisted_clean_string($srcTender['openingDate'] ?? null);
        $normalized['tender']['completionMonths'] = assisted_clean_numeric($srcTender['completionMonths'] ?? null);
        $normalized['tender']['validityDays'] = assisted_clean_numeric($srcTender['validityDays'] ?? null);
        
        $annexures = assisted_normalize_string_list($srcLists['annexures'] ?? []);
        $formats = assisted_normalize_formats($srcLists['formats'] ?? []);
        $restrictedExisting = assisted_normalize_string_list($srcLists['restricted'] ?? []);
        
        // Split restricted/safe
        [$safeAnnexures, $safeFormats, $newRestricted] = assisted_split_restricted_annexures($annexures, $formats);
        
        $normalized['lists']['eligibilityDocs'] = assisted_normalize_string_list($srcLists['eligibilityDocs'] ?? []);
        $normalized['lists']['annexures'] = $safeAnnexures;
        $normalized['lists']['formats'] = $safeFormats;
        $normalized['lists']['restricted'] = array_values(array_unique(array_merge($restrictedExisting, $newRestricted)));
        $normalized['fees'] = assisted_normalize_fees($payload['fees'] ?? ($payload['tender']['fees'] ?? []));
        
        $normalized['checklist'] = assisted_normalize_checklist($payload['checklist'] ?? []);
        $normalized['templates'] = $payload['templates'] ?? [];

    } else {
        // Legacy Map
        $mapped = assisted_map_payload_schema($payload);
        
        $normalized['tender']['submissionDeadline'] = assisted_clean_string($mapped['submissionDeadline'] ?? null);
        $normalized['tender']['openingDate'] = assisted_clean_string($mapped['openingDate'] ?? null);
        $normalized['tender']['completionMonths'] = assisted_clean_numeric($mapped['completionMonths'] ?? null);
        $normalized['tender']['validityDays'] = assisted_clean_numeric($mapped['bidValidityDays'] ?? null);
        
        $normalized['lists']['eligibilityDocs'] = assisted_normalize_string_list($mapped['eligibilityDocs'] ?? []);
        
        $annexures = assisted_normalize_string_list($mapped['annexures'] ?? []);
        $formats = assisted_normalize_formats($mapped['formats'] ?? []);
        
        [$safeAnnexures, $safeFormats, $restricted] = assisted_split_restricted_annexures($annexures, $formats);
        
        $normalized['lists']['annexures'] = $safeAnnexures;
        $normalized['lists']['formats'] = $safeFormats;
        $normalized['lists']['restricted'] = $restricted;
        $normalized['fees'] = assisted_normalize_fees($mapped['fees'] ?? []);
        
        $normalized['checklist'] = assisted_normalize_checklist($mapped);
    }

    return $normalized;
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

function assisted_split_restricted_annexures(array $annexures, array $formats): array
{
    $restricted = [];
    $safeAnnexures = [];
    foreach ($annexures as $annexure) {
        if (assisted_is_restricted_financial_label(mb_strtolower($annexure))) {
            $restricted[] = $annexure;
            continue;
        }
        $safeAnnexures[] = $annexure;
        if (count($safeAnnexures) >= 200) {
            break;
        }
    }

    $safeFormats = [];
    foreach ($formats as $format) {
        $name = $format['name'] ?? '';
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
    $skipKeys = ['validitydays', 'bidvaliditydays', 'completionmonths']; // Safe numeric fields
    
    // Explicitly safe context words (fees)
    $allowContextTokens = ['tenderfee', 'emd', 'earnestmoney', 'security', 'deposit', 'performance', 'guarantee', 'bg', 'sd', 'pg', 'gst', 'tax', 'levy', 'turnover', 'networth', 'financialyear', 'annual'];
    
    // Explicitly blocked context words (pricing)
    $blockContextTokens = ['boq', 'billofquantity', 'priceschedule', 'financialbid', 'pricebid', 'ratesheet', 'quotedrate', 'itemrate', 'unitrate', 'l1', 'lowestbid'];
    
    foreach ($payload as $key => $value) {
        $keyString = (string)$key;
        $keyLower = strtolower(preg_replace('/[^a-z0-9]/', '', $keyString));
        $currentPath = $path === '' ? $keyString : $path . '.' . $keyString;
        
        if (in_array($keyLower, $skipKeys, true)) {
            continue;
        }

        // Check key for Blocked tokens
        foreach ($blockContextTokens as $token) {
            if (str_contains($keyLower, $token)) {
                $findings[] = [
                    'path' => $currentPath,
                    'reasonCode' => 'BLOCK_KEY',
                    'snippet' => $keyString,
                    'blocked' => true,
                ];
                // Don't break, keep checking value but we already know this node is bad
            }
        }
        
        $isSafeContext = false;
        foreach ($allowContextTokens as $token) {
            if (str_contains($keyLower, $token)) {
                $isSafeContext = true;
                break;
            }
        }
        
        if (is_string($value)) {
             // String check
             $finding = assisted_evaluate_string_forbidden($value, $currentPath, $isSafeContext, $context);
             if ($finding) {
                 $findings[] = $finding;
             }
        } elseif (is_array($value)) {
             // Recurse
             $nextContext = $context;
             if ($isSafeContext) {
                 $nextContext['allowCurrency'] = true;
             }
             $findings = array_merge($findings, assisted_detect_forbidden_pricing($value, $currentPath, $nextContext));
        }
    }
    return $findings;
}

function assisted_evaluate_string_forbidden(string $value, string $path, bool $safeContext, array $context = []): ?array
{
    $lower = mb_strtolower($value);
    
    // Explicit Block Markers (Strong)
    $blockMarkers = ['financial bid', 'price bid', 'quoted rate', 'boq', 'bill of quantity', 'rate sheet', 'price schedule'];
    foreach ($blockMarkers as $marker) {
        if (str_contains($lower, $marker)) {
             // Special case: if it's just mentioning "Cover 2: Financial Bid" in a checklist, it might be okay?
             // But the rules say "Must block... restricted".
             // We'll mark as restricted if it's an annexure label, else block.
             // Actually, if it's in a checklist item as "Submit Financial Bid", we should NOT block import, just maybe flag it.
             // The prompt says "Must block... BOQ... numeric rate lines". "If annexures... include Price Bid... do not fail import... move into lists.restricted".
             
             // So here we only return 'blocked' if it looks like actual DATA (numbers + rates), or if we want to flag usage.
             // But detecting context is hard.
             
             // Refined rule: If the string contains currency AND "rate"/"price", it's dangerous.
             // If it's just "Financial Bid" title, it's safe-ish (will be handled by restricted logic).
        }
    }
    
    // Real pricing detection: Currency + Number + Rate Context
    $hasCurrency = (bool)preg_match('/(₹|rs\.?|inr)\s*\d+/i', $lower); // Currency followed by digit
    $hasCurrencySymbol = (bool)preg_match('/(₹|rs\.?|inr)/i', $lower);
    
    // If context is explicitly safe (Fee/EMD/Turnover), allow currency.
    if ($safeContext || !empty($context['allowCurrency'])) {
        return null; 
    }
    
    // If context is NOT safe, and we see currency with numbers -> Block.
    if ($hasCurrency) {
         return [
            'path' => $path,
            'reasonCode' => 'BLOCK_CURRENCY_NO_CONTEXT',
            'snippet' => assisted_redact_snippet($value),
            'blocked' => true,
        ];
    }
    
    return null;
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
    return in_array('annexures', $segments, true) || in_array('formats', $segments, true) || in_array('formatsandannexures', $segments, true);
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
        $desc = mb_strtolower((string)($item['description'] ?? ''));
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
