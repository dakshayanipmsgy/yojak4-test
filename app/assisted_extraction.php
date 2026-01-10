<?php
declare(strict_types=1);

const ASSISTED_TASKS_DIR = DATA_PATH . '/assisted_tasks';
const ASSISTED_TASKS_INDEX = ASSISTED_TASKS_DIR . '/index.json';
const ASSISTED_TASKS_LOG = DATA_PATH . '/logs/assisted_tasks.log';
const ASSISTED_LEGACY_DIR = DATA_PATH . '/support/assisted_extraction';

function ensure_assisted_extraction_env(): void
{
    $paths = [
        ASSISTED_TASKS_DIR,
        dirname(ASSISTED_TASKS_LOG),
        DATA_PATH . '/_archive/assisted_extraction_old',
    ];

    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }

    if (is_dir(ASSISTED_LEGACY_DIR)) {
        assisted_archive_legacy_storage();
    }

    if (!file_exists(ASSISTED_TASKS_INDEX)) {
        writeJsonAtomic(ASSISTED_TASKS_INDEX, []);
    }

    if (!file_exists(ASSISTED_TASKS_LOG)) {
        touch(ASSISTED_TASKS_LOG);
    }
}

function assisted_archive_legacy_storage(): void
{
    if (!is_dir(ASSISTED_LEGACY_DIR)) {
        return;
    }

    $archiveRoot = DATA_PATH . '/_archive/assisted_extraction_old';
    if (!is_dir($archiveRoot)) {
        mkdir($archiveRoot, 0775, true);
    }

    $stamp = now_kolkata()->format('Ymd_His');
    $target = $archiveRoot . '/legacy_' . $stamp;
    @rename(ASSISTED_LEGACY_DIR, $target);
}

function assisted_tasks_index(): array
{
    ensure_assisted_extraction_env();
    $index = readJson(ASSISTED_TASKS_INDEX);
    return is_array($index) ? array_values($index) : [];
}

function save_assisted_tasks_index(array $records): void
{
    ensure_assisted_extraction_env();
    writeJsonAtomic(ASSISTED_TASKS_INDEX, array_values($records));
}

function assisted_task_path(string $taskId): string
{
    return ASSISTED_TASKS_DIR . '/' . $taskId . '.json';
}

function assisted_task_snapshot_path(string $taskId): string
{
    return ASSISTED_TASKS_DIR . '/' . $taskId . '.delivered.json';
}

function assisted_generate_task_id(): string
{
    $date = now_kolkata()->format('Ymd');
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = 'AST-' . $date . '-' . $suffix;
    } while (file_exists(assisted_task_path($candidate)));

    return $candidate;
}

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

function assisted_task_form_defaults(): array
{
    return [
        'basics' => [
            'tenderTitle' => null,
            'tenderNumber' => null,
            'issuingAuthority' => null,
            'departmentName' => null,
            'location' => null,
            'completionMonths' => null,
            'bidValidityDays' => null,
        ],
        'dates' => [
            'submissionDeadline' => null,
            'openingDate' => null,
            'preBidDate' => null,
        ],
        'fees' => [
            'tenderFeeText' => null,
            'emdText' => null,
            'sdText' => null,
            'pgText' => null,
        ],
        'lists' => [
            'eligibilityDocs' => [],
            'annexures' => [],
            'formats' => [],
            'restrictedAnnexures' => [],
        ],
        'checklist' => [],
        'notes' => [],
    ];
}

function assisted_task_template(array $contractor, array $tender): array
{
    $now = now_kolkata()->format(DateTime::ATOM);
    $taskId = assisted_generate_task_id();
    return [
        'taskId' => $taskId,
        'createdAt' => $now,
        'updatedAt' => $now,
        'status' => 'queued',
        'priority' => 'normal',
        'contractor' => [
            'yojId' => $contractor['yojId'] ?? '',
            'name' => $contractor['firmName'] ?? ($contractor['name'] ?? ''),
            'mobile' => $contractor['mobile'] ?? '',
        ],
        'tender' => [
            'offtdId' => $tender['id'] ?? '',
            'title' => $tender['title'] ?? 'Offline Tender',
            'pdfPath' => assisted_pick_tender_pdf($tender) ?? '',
        ],
        'assignedTo' => [
            'userType' => null,
            'userId' => null,
            'name' => null,
        ],
        'history' => [
            [
                'at' => $now,
                'by' => $contractor['yojId'] ?? 'contractor',
                'event' => 'CREATED',
            ],
        ],
        'form' => assisted_task_form_defaults(),
        'ai' => [
            'lastRunAt' => null,
            'provider' => null,
            'model' => null,
            'requestId' => null,
            'status' => 'never',
            'error' => null,
        ],
        'delivered' => [
            'deliveredAt' => null,
            'deliveredBy' => null,
            'snapshotPath' => null,
        ],
    ];
}

function assisted_pick_tender_pdf(array $tender): ?string
{
    $files = $tender['sourceFiles'] ?? [];
    if (!is_array($files)) {
        return null;
    }
    foreach ($files as $file) {
        $path = trim((string)($file['path'] ?? ''));
        if ($path !== '') {
            return $path;
        }
    }
    return null;
}

function assisted_load_task(string $taskId): ?array
{
    ensure_assisted_extraction_env();
    if ($taskId === '') {
        return null;
    }
    $path = assisted_task_path($taskId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return is_array($data) ? $data : null;
}

function assisted_task_summary(array $task): array
{
    return [
        'taskId' => $task['taskId'] ?? null,
        'status' => $task['status'] ?? 'queued',
        'priority' => $task['priority'] ?? 'normal',
        'createdAt' => $task['createdAt'] ?? null,
        'updatedAt' => $task['updatedAt'] ?? null,
        'contractor' => $task['contractor'] ?? [],
        'tender' => $task['tender'] ?? [],
        'assignedTo' => $task['assignedTo'] ?? [],
    ];
}

function assisted_save_task(array $task): void
{
    ensure_assisted_extraction_env();
    if (empty($task['taskId'])) {
        throw new InvalidArgumentException('Task id missing');
    }
    $task['updatedAt'] = $task['updatedAt'] ?? now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(assisted_task_path($task['taskId']), $task);

    $index = assisted_tasks_index();
    $summary = assisted_task_summary($task);
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['taskId'] ?? '') === $task['taskId']) {
            $entry = $summary;
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = $summary;
    }
    usort($index, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));
    save_assisted_tasks_index($index);
}

function assisted_task_for_tender(string $yojId, string $offtdId): ?array
{
    foreach (assisted_tasks_index() as $entry) {
        if (($entry['contractor']['yojId'] ?? '') === $yojId && ($entry['tender']['offtdId'] ?? '') === $offtdId) {
            return assisted_load_task($entry['taskId'] ?? '');
        }
    }
    return null;
}

function assisted_active_task_for_tender(string $yojId, string $offtdId): ?array
{
    foreach (assisted_tasks_index() as $entry) {
        $status = $entry['status'] ?? '';
        if (($entry['contractor']['yojId'] ?? '') === $yojId && ($entry['tender']['offtdId'] ?? '') === $offtdId && in_array($status, ['queued', 'in_progress'], true)) {
            return assisted_load_task($entry['taskId'] ?? '');
        }
    }
    return null;
}

function assisted_is_restricted_financial_label(string $lower): bool
{
    $restricted = [
        'price bid',
        'financial bid',
        'boq',
        'b.o.q',
        'sor',
        'schedule of rates',
        'schedule of rate',
        'rate analysis',
        'quoted rates',
    ];
    foreach ($restricted as $needle) {
        if (str_contains($lower, $needle)) {
            return true;
        }
    }
    return false;
}

function assisted_classify_restricted_annexures(array $annexures, array $formats): array
{
    $restricted = [];
    foreach (array_merge($annexures, array_map(fn($f) => $f['name'] ?? '', $formats)) as $label) {
        $label = trim((string)$label);
        if ($label === '') {
            continue;
        }
        if (assisted_is_restricted_financial_label(mb_strtolower($label))) {
            $restricted[] = $label;
        }
    }
    $restricted = array_values(array_unique($restricted));
    $annexuresFiltered = array_values(array_filter($annexures, fn($label) => !in_array($label, $restricted, true)));
    $formatsFiltered = array_values(array_filter($formats, fn($f) => !in_array($f['name'] ?? '', $restricted, true)));
    return [$annexuresFiltered, $formatsFiltered, $restricted];
}

function assisted_task_form_from_post(array $input, array $existing): array
{
    $form = $existing ?: assisted_task_form_defaults();
    $form['basics'] = [
        'tenderTitle' => assisted_clean_string($input['tender_title'] ?? ''),
        'tenderNumber' => assisted_clean_string($input['tender_number'] ?? ''),
        'issuingAuthority' => assisted_clean_string($input['issuing_authority'] ?? ''),
        'departmentName' => assisted_clean_string($input['department_name'] ?? ''),
        'location' => assisted_clean_string($input['location'] ?? ''),
        'completionMonths' => assisted_clean_numeric($input['completion_months'] ?? null),
        'bidValidityDays' => assisted_clean_numeric($input['bid_validity_days'] ?? null),
    ];
    $form['dates'] = [
        'submissionDeadline' => assisted_clean_string($input['submission_deadline'] ?? ''),
        'openingDate' => assisted_clean_string($input['opening_date'] ?? ''),
        'preBidDate' => assisted_clean_string($input['prebid_date'] ?? ''),
    ];
    $form['fees'] = [
        'tenderFeeText' => assisted_clean_string($input['tender_fee_text'] ?? ''),
        'emdText' => assisted_clean_string($input['emd_text'] ?? ''),
        'sdText' => assisted_clean_string($input['sd_text'] ?? ''),
        'pgText' => assisted_clean_string($input['pg_text'] ?? ''),
    ];

    $eligibilityDocs = assisted_normalize_string_list($input['eligibility_docs'] ?? []);
    $annexures = assisted_normalize_string_list($input['annexures'] ?? []);
    $formats = assisted_normalize_formats($input['formats'] ?? []);

    [$annexures, $formats, $restricted] = assisted_classify_restricted_annexures($annexures, $formats);

    $form['lists'] = [
        'eligibilityDocs' => $eligibilityDocs,
        'annexures' => $annexures,
        'formats' => $formats,
        'restrictedAnnexures' => $restricted,
    ];

    $checklistLines = assisted_normalize_string_list($input['checklist_lines'] ?? []);
    $checklist = [];
    foreach ($checklistLines as $line) {
        $parts = array_map('trim', explode('|', $line));
        $title = $parts[0] ?? '';
        if ($title === '') {
            continue;
        }
        $category = $parts[1] ?? 'Other';
        $requiredInput = mb_strtolower($parts[2] ?? 'yes');
        $required = !in_array($requiredInput, ['no', 'false', '0'], true);
        $notes = $parts[3] ?? '';
        $checklist[] = [
            'title' => $title,
            'category' => $category !== '' ? $category : 'Other',
            'required' => $required,
            'notes' => $notes,
        ];
        if (count($checklist) >= 300) {
            break;
        }
    }
    $form['checklist'] = $checklist;

    $notes = assisted_normalize_string_list($input['notes'] ?? []);
    $form['notes'] = $notes;

    return $form;
}

function assisted_task_form_to_tender_extract(array $form): array
{
    $defaults = offline_tender_defaults();
    return [
        'publishDate' => $defaults['publishDate'],
        'submissionDeadline' => $form['dates']['submissionDeadline'] ?? null,
        'openingDate' => $form['dates']['openingDate'] ?? null,
        'fees' => [
            'tenderFee' => (string)($form['fees']['tenderFeeText'] ?? ''),
            'emd' => (string)($form['fees']['emdText'] ?? ''),
            'other' => implode(' | ', array_filter([
                (string)($form['fees']['sdText'] ?? ''),
                (string)($form['fees']['pgText'] ?? ''),
            ])),
        ],
        'completionMonths' => $form['basics']['completionMonths'] ?? null,
        'bidValidityDays' => $form['basics']['bidValidityDays'] ?? null,
        'eligibilityDocs' => $form['lists']['eligibilityDocs'] ?? [],
        'annexures' => $form['lists']['annexures'] ?? [],
        'restrictedAnnexures' => $form['lists']['restrictedAnnexures'] ?? [],
        'formats' => $form['lists']['formats'] ?? [],
        'checklist' => $form['checklist'] ?? [],
    ];
}

function assisted_task_form_to_checklist(array $form): array
{
    $items = [];
    foreach ($form['checklist'] ?? [] as $item) {
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $items[] = [
            'itemId' => 'CHK-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)),
            'title' => $title,
            'description' => trim((string)($item['notes'] ?? '')),
            'required' => (bool)($item['required'] ?? true),
            'status' => 'pending',
            'category' => $item['category'] ?? 'Other',
            'source' => 'assisted_task',
        ];
    }
    return $items;
}

function assisted_task_can_deliver(array $task): bool
{
    $form = $task['form'] ?? [];
    $dates = $form['dates'] ?? [];
    $checklist = $form['checklist'] ?? [];
    return !empty($dates['submissionDeadline']) || !empty($dates['openingDate']) || count($checklist) >= 5;
}

function assisted_task_log(array $context): void
{
    logEvent(ASSISTED_TASKS_LOG, $context);
}

function assisted_require_staff_access(): array
{
    $user = current_user();
    if ($user && ($user['type'] ?? '') === 'superadmin') {
        return $user;
    }

    $employee = current_employee_record();
    if ($employee) {
        $role = $employee['role'] ?? '';
        $permissions = $employee['permissions'] ?? [];
        $allowedRole = in_array($role, ['support', 'approvals'], true);
        $allowedPerm = in_array('tickets', $permissions, true) || in_array('reset_approvals', $permissions, true) || in_array('*', $permissions, true);
        if ($allowedRole || $allowedPerm) {
            return $employee;
        }
    }

    redirect('/auth/login.php');
}

function assisted_actor_identity(array $actor): array
{
    if (($actor['type'] ?? '') === 'superadmin') {
        return [
            'userType' => 'superadmin',
            'userId' => $actor['username'] ?? 'superadmin',
            'name' => $actor['username'] ?? 'Superadmin',
        ];
    }
    return [
        'userType' => 'employee',
        'userId' => $actor['empId'] ?? '',
        'name' => $actor['displayName'] ?? ($actor['username'] ?? ''),
    ];
}

function assisted_task_apply_ai_payload(array $form, array $payload): array
{
    $form = $form ?: assisted_task_form_defaults();
    $form['basics']['tenderTitle'] = assisted_clean_string($payload['tenderTitle'] ?? $form['basics']['tenderTitle'] ?? null);
    $form['basics']['tenderNumber'] = assisted_clean_string($payload['tenderNumber'] ?? $form['basics']['tenderNumber'] ?? null);
    $form['basics']['issuingAuthority'] = assisted_clean_string($payload['issuingAuthority'] ?? $form['basics']['issuingAuthority'] ?? null);
    $form['basics']['departmentName'] = assisted_clean_string($payload['departmentName'] ?? $form['basics']['departmentName'] ?? null);
    $form['basics']['location'] = assisted_clean_string($payload['location'] ?? $form['basics']['location'] ?? null);
    $form['basics']['completionMonths'] = assisted_clean_numeric($payload['completionMonths'] ?? $form['basics']['completionMonths'] ?? null);
    $form['basics']['bidValidityDays'] = assisted_clean_numeric($payload['bidValidityDays'] ?? $form['basics']['bidValidityDays'] ?? null);

    $form['dates']['submissionDeadline'] = assisted_clean_string($payload['submissionDeadline'] ?? $form['dates']['submissionDeadline'] ?? null);
    $form['dates']['openingDate'] = assisted_clean_string($payload['openingDate'] ?? $form['dates']['openingDate'] ?? null);
    $form['dates']['preBidDate'] = assisted_clean_string($payload['preBidDate'] ?? $form['dates']['preBidDate'] ?? null);

    $fees = $payload['fees'] ?? [];
    $form['fees']['tenderFeeText'] = assisted_clean_string($fees['tenderFeeText'] ?? $form['fees']['tenderFeeText'] ?? null);
    $form['fees']['emdText'] = assisted_clean_string($fees['emdText'] ?? $form['fees']['emdText'] ?? null);
    $form['fees']['sdText'] = assisted_clean_string($fees['sdText'] ?? $form['fees']['sdText'] ?? null);
    $form['fees']['pgText'] = assisted_clean_string($fees['pgText'] ?? $form['fees']['pgText'] ?? null);

    $annexures = assisted_normalize_string_list($payload['annexures'] ?? $form['lists']['annexures'] ?? []);
    $eligibilityDocs = assisted_normalize_string_list($payload['eligibilityDocs'] ?? $form['lists']['eligibilityDocs'] ?? []);
    $formats = assisted_normalize_formats($payload['formats'] ?? $form['lists']['formats'] ?? []);
    [$annexures, $formats, $restricted] = assisted_classify_restricted_annexures($annexures, $formats);

    $form['lists']['annexures'] = $annexures;
    $form['lists']['eligibilityDocs'] = $eligibilityDocs;
    $form['lists']['formats'] = $formats;
    $form['lists']['restrictedAnnexures'] = $restricted;

    $checklist = [];
    if (isset($payload['checklist']) && is_array($payload['checklist'])) {
        foreach ($payload['checklist'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = assisted_clean_string($item['title'] ?? '') ?? '';
            if ($title === '') {
                continue;
            }
            $checklist[] = [
                'title' => $title,
                'category' => assisted_clean_string($item['category'] ?? '') ?? 'Other',
                'required' => !isset($item['required']) || (bool)$item['required'],
                'notes' => assisted_clean_string($item['notes'] ?? '') ?? '',
            ];
            if (count($checklist) >= 300) {
                break;
            }
        }
    }
    if ($checklist) {
        $form['checklist'] = $checklist;
    }

    $notes = assisted_normalize_string_list($payload['notes'] ?? $form['notes'] ?? []);
    $form['notes'] = $notes;

    return $form;
}

function assisted_task_ai_prompt(array $task): array
{
    $tender = $task['tender'] ?? [];
    $form = $task['form'] ?? [];

    $system = "You are assisting with tender extraction. Fill the form fields only. Do NOT include bid rates/BOQ pricing. Fees text is allowed.";
    $user = "Tender title: " . ($tender['title'] ?? '') . "\n"
        . "Tender ID: " . ($tender['offtdId'] ?? '') . "\n"
        . "PDF path (may be needed for reference): " . ($tender['pdfPath'] ?? '') . "\n\n"
        . "Return JSON with keys: tenderTitle, tenderNumber, issuingAuthority, departmentName, location, completionMonths, bidValidityDays, submissionDeadline, openingDate, preBidDate, fees{tenderFeeText,emdText,sdText,pgText}, eligibilityDocs[], annexures[], formats[{name,notes}], checklist[{title,category,required,notes}], notes[].\n\n"
        . "Current form (may be partially filled): " . json_encode($form, JSON_UNESCAPED_SLASHES);

    return [$system, $user];
}
