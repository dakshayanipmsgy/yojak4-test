<?php
declare(strict_types=1);

const ASSISTED_EXTRACTION_DIR = DATA_PATH . '/support/assisted_extraction';
const ASSISTED_EXTRACTION_INDEX = ASSISTED_EXTRACTION_DIR . '/index.json';
const ASSISTED_EXTRACTION_LOG = DATA_PATH . '/logs/assisted_extraction.log';

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
    $forbiddenKeys = ['bidamount', 'quotedrate', 'rate', 'amount', 'price', 'financial'];
    foreach ($payload as $key => $value) {
        $keyString = strtolower((string)$key);
        foreach ($forbiddenKeys as $forbidden) {
            if (str_contains($keyString, $forbidden)) {
                return true;
            }
        }
        if (is_array($value) && assisted_forbidden_fields_present($value)) {
            return true;
        }
    }
    return false;
}

function assisted_validate_payload(array $payload): array
{
    $errors = [];
    $requiredKeys = ['submissionDeadline', 'openingDate', 'completionMonths', 'bidValidityDays', 'eligibilityDocs', 'annexures', 'formats', 'checklist'];
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $payload)) {
            $errors[] = "Missing field: {$key}";
        }
    }

    if (isset($payload['completionMonths']) && $payload['completionMonths'] !== null && !is_numeric($payload['completionMonths'])) {
        $errors[] = 'completionMonths must be numeric or null.';
    }
    if (isset($payload['bidValidityDays']) && $payload['bidValidityDays'] !== null && !is_numeric($payload['bidValidityDays'])) {
        $errors[] = 'bidValidityDays must be numeric or null.';
    }

    if (!isset($payload['checklist']) || !is_array($payload['checklist'])) {
        $errors[] = 'checklist must be an array.';
    } else {
        if (count($payload['checklist']) < 3) {
            $errors[] = 'checklist must contain at least 3 items.';
        }
        foreach ($payload['checklist'] as $item) {
            if (!is_array($item)) {
                $errors[] = 'checklist entries must be objects.';
                break;
            }
            if (trim((string)($item['title'] ?? '')) === '') {
                $errors[] = 'Each checklist item needs a title.';
                break;
            }
            if (!array_key_exists('required', $item) || !is_bool($item['required'])) {
                $errors[] = 'Each checklist item must specify required as boolean.';
                break;
            }
        }
    }

    foreach (['eligibilityDocs', 'annexures'] as $listField) {
        if (isset($payload[$listField]) && !is_array($payload[$listField]) && !is_string($payload[$listField])) {
            $errors[] = "{$listField} must be an array or newline separated text.";
        }
    }

    if (isset($payload['formats']) && !is_array($payload['formats']) && !is_string($payload['formats'])) {
        $errors[] = 'formats must be an array or pipe-separated lines.';
    }

    if (assisted_forbidden_fields_present($payload)) {
        $errors[] = 'Payload contains forbidden bid/rate fields.';
    }

    return $errors;
}

function assisted_normalize_payload(array $payload): array
{
    $normalized = [
        'submissionDeadline' => $payload['submissionDeadline'] ?? null,
        'openingDate' => $payload['openingDate'] ?? null,
        'completionMonths' => isset($payload['completionMonths']) && $payload['completionMonths'] !== null ? (int)$payload['completionMonths'] : null,
        'bidValidityDays' => isset($payload['bidValidityDays']) && $payload['bidValidityDays'] !== null ? (int)$payload['bidValidityDays'] : null,
        'eligibilityDocs' => normalize_string_list($payload['eligibilityDocs'] ?? []),
        'annexures' => normalize_string_list($payload['annexures'] ?? []),
        'formats' => normalize_formats($payload['formats'] ?? []),
        'checklist' => [],
    ];

    $checklist = is_array($payload['checklist'] ?? null) ? $payload['checklist'] : [];
    foreach ($checklist as $item) {
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $normalized['checklist'][] = [
            'title' => $title,
            'description' => trim((string)($item['description'] ?? '')),
            'required' => (bool)($item['required'] ?? true),
        ];
        if (count($normalized['checklist']) >= 300) {
            break;
        }
    }

    return $normalized;
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
