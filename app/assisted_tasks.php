<?php
declare(strict_types=1);

const ASSISTED_TASKS_DIR = DATA_PATH . '/support/assisted_tasks';
const ASSISTED_TASKS_LOG = DATA_PATH . '/logs/assisted_tasks.log';

function ensure_assisted_tasks_env(): void
{
    $dirs = [
        ASSISTED_TASKS_DIR,
        ASSISTED_TASKS_DIR . '/tasks',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $indexPath = assisted_tasks_index_path();
    if (!file_exists($indexPath)) {
        writeJsonAtomic($indexPath, [
            'tasks' => [],
            'updatedAt' => now_kolkata()->format(DateTime::ATOM),
        ]);
    }

    if (!file_exists(ASSISTED_TASKS_LOG)) {
        touch(ASSISTED_TASKS_LOG);
    }
}

function assisted_tasks_index_path(): string
{
    return ASSISTED_TASKS_DIR . '/index.json';
}

function assisted_task_path(string $taskId): string
{
    return ASSISTED_TASKS_DIR . '/tasks/' . $taskId . '.json';
}

function assisted_tasks_index(): array
{
    ensure_assisted_tasks_env();
    $data = readJson(assisted_tasks_index_path());
    if (!isset($data['tasks']) || !is_array($data['tasks'])) {
        $data['tasks'] = [];
    }
    $data['updatedAt'] = $data['updatedAt'] ?? now_kolkata()->format(DateTime::ATOM);
    return $data;
}

function assisted_tasks_save_index(array $index): void
{
    $index['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    if (!isset($index['tasks']) || !is_array($index['tasks'])) {
        $index['tasks'] = [];
    }
    writeJsonAtomic(assisted_tasks_index_path(), $index);
}

function assisted_tasks_log(array $context): void
{
    logEvent(ASSISTED_TASKS_LOG, $context);
}

function assisted_task_generate_id(): string
{
    ensure_assisted_tasks_env();
    $date = now_kolkata()->format('Ymd');
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $candidate = 'AST-' . $date . '-' . $suffix;
    } while (file_exists(assisted_task_path($candidate)));

    return $candidate;
}

function assisted_tasks_require_staff(): array
{
    $user = current_user();
    if (!$user) {
        redirect('/auth/login.php');
    }
    $type = $user['type'] ?? '';
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    if ($type === 'superadmin') {
        return $user;
    }
    if ($type === 'employee') {
        $perms = $user['permissions'] ?? [];
        if (in_array('tickets', $perms, true) || in_array('reset_approvals', $perms, true)) {
            return $user;
        }
    }
    render_error_page('Unauthorized access to assisted extraction tasks.');
    exit;
}

function assisted_tasks_actor_label(array $user): string
{
    if (($user['type'] ?? '') === 'superadmin') {
        return 'superadmin';
    }
    if (($user['type'] ?? '') === 'employee') {
        return $user['empId'] ?? ($user['username'] ?? 'employee');
    }
    return $user['username'] ?? 'user';
}

function assisted_tasks_clean_string($value): ?string
{
    $text = trim((string)$value);
    return $text === '' ? null : $text;
}

function assisted_tasks_clean_int($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    return is_numeric($value) ? (int)$value : null;
}

function assisted_tasks_list_from_lines(string $input): array
{
    $lines = preg_split('/\r\n|\r|\n/', $input);
    $items = [];
    foreach ($lines as $line) {
        $clean = trim((string)$line);
        if ($clean !== '') {
            $items[] = $clean;
        }
    }
    return array_values($items);
}

function assisted_tasks_parse_formats(string $input): array
{
    $lines = preg_split('/\r\n|\r|\n/', $input);
    $formats = [];
    foreach ($lines as $line) {
        $raw = trim((string)$line);
        if ($raw === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $raw, 2));
        $formats[] = [
            'name' => $parts[0],
            'notes' => $parts[1] ?? '',
        ];
    }
    return $formats;
}

function assisted_tasks_generate_checklist_id(): string
{
    return 'CHK-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function assisted_tasks_normalize_checklist(array $items): array
{
    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = assisted_tasks_clean_string($item['title'] ?? '');
        if ($title === null) {
            continue;
        }
        $category = trim((string)($item['category'] ?? 'Other'));
        if (!in_array($category, ['Eligibility', 'Fees', 'Forms', 'Technical', 'Submission', 'Declarations', 'Other'], true)) {
            $category = 'Other';
        }
        $normalized[] = [
            'id' => $item['id'] ?? assisted_tasks_generate_checklist_id(),
            'title' => $title,
            'category' => $category,
            'required' => !empty($item['required']),
            'notes' => trim((string)($item['notes'] ?? '')),
            'snippet' => trim((string)($item['snippet'] ?? '')),
        ];
    }
    return $normalized;
}

function assisted_tasks_split_restricted(array $annexures, array $formats): array
{
    $safeAnnexures = [];
    $safeFormats = [];
    $restricted = [];

    foreach ($annexures as $annexure) {
        $label = is_array($annexure) ? ($annexure['name'] ?? $annexure['title'] ?? '') : (string)$annexure;
        if ($label === '') {
            continue;
        }
        if (assisted_is_restricted_financial_label(mb_strtolower($label))) {
            $restricted[] = $label;
        } else {
            $safeAnnexures[] = $annexure;
        }
    }

    foreach ($formats as $format) {
        $label = is_array($format) ? ($format['name'] ?? $format['title'] ?? '') : (string)$format;
        if ($label === '') {
            continue;
        }
        if (assisted_is_restricted_financial_label(mb_strtolower($label))) {
            $restricted[] = $label;
        } else {
            $safeFormats[] = $format;
        }
    }

    return [$safeAnnexures, $safeFormats, array_values(array_unique($restricted))];
}

function assisted_tasks_default_form(): array
{
    return [
        'tenderTitle' => null,
        'tenderNumber' => null,
        'issuingAuthority' => null,
        'departmentName' => null,
        'location' => null,
        'submissionDeadline' => null,
        'openingDate' => null,
        'preBidDate' => null,
        'completionMonths' => null,
        'bidValidityDays' => null,
        'fees' => [
            'tenderFeeText' => null,
            'emdText' => null,
            'sdText' => null,
            'pgText' => null,
        ],
        'eligibilityDocs' => [],
        'annexures' => [],
        'formats' => [],
        'restrictedAnnexures' => [],
        'checklist' => [
            [
                'id' => 'CHK-001',
                'title' => '',
                'category' => 'Other',
                'required' => true,
                'notes' => '',
                'snippet' => '',
            ],
        ],
        'notes' => [],
    ];
}

function assisted_tasks_build_form(array $input): array
{
    $form = assisted_tasks_default_form();
    $form['tenderTitle'] = assisted_tasks_clean_string($input['tenderTitle'] ?? '');
    $form['tenderNumber'] = assisted_tasks_clean_string($input['tenderNumber'] ?? '');
    $form['issuingAuthority'] = assisted_tasks_clean_string($input['issuingAuthority'] ?? '');
    $form['departmentName'] = assisted_tasks_clean_string($input['departmentName'] ?? '');
    $form['location'] = assisted_tasks_clean_string($input['location'] ?? '');
    $form['submissionDeadline'] = assisted_tasks_clean_string($input['submissionDeadline'] ?? '');
    $form['openingDate'] = assisted_tasks_clean_string($input['openingDate'] ?? '');
    $form['preBidDate'] = assisted_tasks_clean_string($input['preBidDate'] ?? '');
    $form['completionMonths'] = assisted_tasks_clean_int($input['completionMonths'] ?? '');
    $form['bidValidityDays'] = assisted_tasks_clean_int($input['bidValidityDays'] ?? '');

    $fees = $input['fees'] ?? [];
    $form['fees'] = [
        'tenderFeeText' => assisted_tasks_clean_string($fees['tenderFeeText'] ?? ($input['tenderFeeText'] ?? '')),
        'emdText' => assisted_tasks_clean_string($fees['emdText'] ?? ($input['emdText'] ?? '')),
        'sdText' => assisted_tasks_clean_string($fees['sdText'] ?? ($input['sdText'] ?? '')),
        'pgText' => assisted_tasks_clean_string($fees['pgText'] ?? ($input['pgText'] ?? '')),
    ];

    $eligibility = assisted_tasks_list_from_lines((string)($input['eligibilityDocs'] ?? ''));
    $annexures = assisted_tasks_list_from_lines((string)($input['annexures'] ?? ''));
    $formats = assisted_tasks_parse_formats((string)($input['formats'] ?? ''));
    [$safeAnnexures, $safeFormats, $restricted] = assisted_tasks_split_restricted($annexures, $formats);
    $form['eligibilityDocs'] = $eligibility;
    $form['annexures'] = $safeAnnexures;
    $form['formats'] = $safeFormats;
    $form['restrictedAnnexures'] = $restricted;
    $form['checklist'] = assisted_tasks_normalize_checklist($input['checklist'] ?? []);
    $form['notes'] = assisted_tasks_list_from_lines((string)($input['notes'] ?? ''));

    return $form;
}

function assisted_tasks_normalize_ai_payload(array $payload): array
{
    $form = assisted_tasks_default_form();
    $form['tenderTitle'] = assisted_tasks_clean_string($payload['tenderTitle'] ?? '');
    $form['tenderNumber'] = assisted_tasks_clean_string($payload['tenderNumber'] ?? '');
    $form['issuingAuthority'] = assisted_tasks_clean_string($payload['issuingAuthority'] ?? '');
    $form['departmentName'] = assisted_tasks_clean_string($payload['departmentName'] ?? '');
    $form['location'] = assisted_tasks_clean_string($payload['location'] ?? '');
    $form['submissionDeadline'] = assisted_tasks_clean_string($payload['submissionDeadline'] ?? '');
    $form['openingDate'] = assisted_tasks_clean_string($payload['openingDate'] ?? '');
    $form['preBidDate'] = assisted_tasks_clean_string($payload['preBidDate'] ?? '');
    $form['completionMonths'] = assisted_tasks_clean_int($payload['completionMonths'] ?? null);
    $form['bidValidityDays'] = assisted_tasks_clean_int($payload['bidValidityDays'] ?? null);

    $fees = $payload['fees'] ?? [];
    $form['fees'] = [
        'tenderFeeText' => assisted_tasks_clean_string($fees['tenderFeeText'] ?? ''),
        'emdText' => assisted_tasks_clean_string($fees['emdText'] ?? ''),
        'sdText' => assisted_tasks_clean_string($fees['sdText'] ?? ''),
        'pgText' => assisted_tasks_clean_string($fees['pgText'] ?? ''),
    ];

    $eligibility = is_array($payload['eligibilityDocs'] ?? null) ? $payload['eligibilityDocs'] : [];
    $annexures = is_array($payload['annexures'] ?? null) ? $payload['annexures'] : [];
    $formatsRaw = $payload['formats'] ?? [];
    $formats = [];
    if (is_array($formatsRaw)) {
        foreach ($formatsRaw as $entry) {
            if (is_array($entry)) {
                $name = trim((string)($entry['name'] ?? $entry['title'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $formats[] = [
                    'name' => $name,
                    'notes' => trim((string)($entry['notes'] ?? '')),
                ];
            } elseif (is_string($entry) && trim($entry) !== '') {
                $formats[] = [
                    'name' => trim($entry),
                    'notes' => '',
                ];
            }
        }
    }

    [$safeAnnexures, $safeFormats, $restricted] = assisted_tasks_split_restricted($annexures, $formats);
    $form['eligibilityDocs'] = array_values(array_filter(array_map('trim', $eligibility), fn($v) => $v !== ''));
    $form['annexures'] = $safeAnnexures;
    $form['formats'] = $safeFormats;
    $form['restrictedAnnexures'] = $restricted;

    $checklistRaw = is_array($payload['checklist'] ?? null) ? $payload['checklist'] : [];
    $checklist = [];
    foreach ($checklistRaw as $item) {
        if (!is_array($item)) {
            continue;
        }
        $checklist[] = [
            'id' => $item['id'] ?? assisted_tasks_generate_checklist_id(),
            'title' => assisted_tasks_clean_string($item['title'] ?? '') ?? '',
            'category' => $item['category'] ?? 'Other',
            'required' => !empty($item['required']),
            'notes' => trim((string)($item['notes'] ?? '')),
            'snippet' => trim((string)($item['snippet'] ?? '')),
        ];
    }
    $form['checklist'] = assisted_tasks_normalize_checklist($checklist);
    $form['notes'] = is_array($payload['notes'] ?? null) ? array_values(array_filter(array_map('trim', $payload['notes']), fn($v) => $v !== '')) : [];

    return $form;
}

function assisted_tasks_merge_form(array $base, array $incoming): array
{
    $merged = $base;
    foreach (['tenderTitle','tenderNumber','issuingAuthority','departmentName','location','submissionDeadline','openingDate','preBidDate'] as $key) {
        if (!empty($incoming[$key])) {
            $merged[$key] = $incoming[$key];
        }
    }
    foreach (['completionMonths','bidValidityDays'] as $key) {
        if (isset($incoming[$key]) && $incoming[$key] !== null) {
            $merged[$key] = $incoming[$key];
        }
    }
    foreach (['tenderFeeText','emdText','sdText','pgText'] as $feeKey) {
        if (!empty($incoming['fees'][$feeKey] ?? null)) {
            $merged['fees'][$feeKey] = $incoming['fees'][$feeKey];
        }
    }
    foreach (['eligibilityDocs','annexures','formats','restrictedAnnexures','checklist','notes'] as $key) {
        if (!empty($incoming[$key])) {
            $merged[$key] = $incoming[$key];
        }
    }
    return $merged;
}

function assisted_tasks_pick_tender_pdf(array $tender): ?array
{
    foreach ($tender['sourceFiles'] ?? [] as $file) {
        $path = $file['path'] ?? '';
        if ($path === '' || !str_ends_with(strtolower($path), '.pdf')) {
            continue;
        }
        $publicPath = $path;
        $fullPath = PUBLIC_PATH . $path;
        return [
            'path' => $fullPath,
            'publicPath' => $publicPath,
            'originalName' => $file['name'] ?? basename($fullPath),
        ];
    }
    return null;
}

function assisted_tasks_task_entry(array $task): array
{
    return [
        'taskId' => $task['taskId'],
        'createdAt' => $task['createdAt'] ?? null,
        'status' => $task['status'] ?? 'requested',
        'priority' => $task['priority'] ?? 'normal',
        'yojId' => $task['yojId'] ?? '',
        'offtdId' => $task['offtdId'] ?? '',
        'packId' => $task['packId'] ?? '',
        'assignedTo' => $task['assignedTo'] ?? null,
        'lastUpdatedAt' => $task['lastUpdatedAt'] ?? ($task['updatedAt'] ?? null),
    ];
}

function assisted_tasks_save_task(array $task): void
{
    $task['lastUpdatedAt'] = $task['lastUpdatedAt'] ?? now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(assisted_task_path($task['taskId']), $task);

    $index = assisted_tasks_index();
    $tasks = $index['tasks'] ?? [];
    $updated = false;
    foreach ($tasks as &$entry) {
        if (($entry['taskId'] ?? '') === $task['taskId']) {
            $entry = array_merge($entry, assisted_tasks_task_entry($task));
            $updated = true;
            break;
        }
    }
    unset($entry);
    if (!$updated) {
        $tasks[] = assisted_tasks_task_entry($task);
    }
    $index['tasks'] = $tasks;
    assisted_tasks_save_index($index);
}

function assisted_tasks_load_task(string $taskId): ?array
{
    ensure_assisted_tasks_env();
    $path = assisted_task_path($taskId);
    if (!file_exists($path)) {
        return null;
    }
    return readJson($path);
}

function assisted_tasks_active_for_tender(string $yojId, string $offtdId): ?array
{
    $index = assisted_tasks_index();
    $tasks = $index['tasks'] ?? [];
    $match = null;
    foreach ($tasks as $entry) {
        if (($entry['yojId'] ?? '') === $yojId && ($entry['offtdId'] ?? '') === $offtdId) {
            $status = $entry['status'] ?? 'requested';
            if ($status !== 'closed') {
                $match = $entry;
            }
        }
    }
    if (!$match) {
        return null;
    }
    return assisted_tasks_load_task($match['taskId'] ?? '');
}

function assisted_tasks_append_history(array &$task, string $by, string $action): void
{
    if (!isset($task['history']) || !is_array($task['history'])) {
        $task['history'] = [];
    }
    $task['history'][] = [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'by' => $by,
        'action' => $action,
    ];
}

function assisted_tasks_ensure_pack(array $tender): string
{
    $yojId = $tender['yojId'] ?? '';
    $offtdId = $tender['id'] ?? '';
    if ($yojId === '' || $offtdId === '') {
        throw new RuntimeException('Missing tender identifiers for pack creation.');
    }
    $existing = find_pack_by_source($yojId, 'OFFTD', $offtdId);
    if ($existing) {
        return $existing['packId'] ?? '';
    }
    $now = now_kolkata()->format(DateTime::ATOM);
    $packId = generate_pack_id($yojId);
    $pack = [
        'packId' => $packId,
        'yojId' => $yojId,
        'title' => $tender['title'] ?? 'Offline Tender Pack',
        'sourceTender' => [
            'type' => 'OFFTD',
            'id' => $offtdId,
            'source' => 'offline_assisted_v2',
        ],
        'source' => 'offline_assisted_v2',
        'createdAt' => $now,
        'status' => 'Pending',
        'items' => [],
        'generatedDocs' => [],
        'defaultTemplatesApplied' => false,
    ];
    save_pack($pack);
    return $packId;
}

function assisted_tasks_create(string $yojId, string $offtdId, array $createdBy): array
{
    ensure_assisted_tasks_env();
    $tender = load_offline_tender($yojId, $offtdId);
    if (!$tender) {
        throw new RuntimeException('Tender not found for assisted task creation.');
    }
    $now = now_kolkata()->format(DateTime::ATOM);
    $taskId = assisted_task_generate_id();
    $packId = assisted_tasks_ensure_pack($tender);
    $pdf = assisted_tasks_pick_tender_pdf($tender);
    $extractForm = assisted_tasks_default_form();
    $extractForm['tenderTitle'] = assisted_tasks_clean_string($tender['title'] ?? '');
    $extractForm['tenderNumber'] = assisted_tasks_clean_string($tender['tenderNumber'] ?? '');
    $extractForm['submissionDeadline'] = assisted_tasks_clean_string($tender['submissionDeadline'] ?? '');
    $extractForm['openingDate'] = assisted_tasks_clean_string($tender['openingDate'] ?? '');

    $task = [
        'taskId' => $taskId,
        'status' => 'requested',
        'createdAt' => $now,
        'createdBy' => [
            'type' => $createdBy['type'] ?? 'contractor',
            'yojId' => $createdBy['yojId'] ?? $yojId,
        ],
        'assignedTo' => null,
        'yojId' => $yojId,
        'offtdId' => $offtdId,
        'packId' => $packId,
        'tenderPdf' => $pdf ?? [
            'path' => null,
            'originalName' => null,
        ],
        'extractForm' => $extractForm,
        'aiAssist' => [
            'lastRunAt' => null,
            'provider' => null,
            'model' => null,
            'requestId' => null,
            'status' => 'never',
            'error' => null,
        ],
        'history' => [],
        'lastUpdatedAt' => $now,
        'priority' => 'normal',
    ];
    assisted_tasks_append_history($task, $createdBy['yojId'] ?? $yojId, 'created');
    assisted_tasks_save_task($task);
    assisted_tasks_log([
        'event' => 'task_created',
        'taskId' => $taskId,
        'yojId' => $yojId,
        'offtdId' => $offtdId,
        'packId' => $packId,
    ]);
    return $task;
}

function assisted_tasks_minimum_checklist(array $checklist): array
{
    $defaults = [
        ['title' => 'Signed tender submission letter', 'category' => 'Forms'],
        ['title' => 'Tender fee receipt / instrument', 'category' => 'Fees'],
        ['title' => 'EMD / Bid Security instrument', 'category' => 'Fees'],
        ['title' => 'PAN, GST and registration documents', 'category' => 'Eligibility'],
        ['title' => 'Signed declarations and undertakings', 'category' => 'Declarations'],
    ];
    $filled = $checklist;
    foreach ($defaults as $entry) {
        if (count($filled) >= 5) {
            break;
        }
        $filled[] = [
            'id' => assisted_tasks_generate_checklist_id(),
            'title' => $entry['title'],
            'category' => $entry['category'],
            'required' => true,
            'notes' => '',
            'snippet' => '',
        ];
    }
    return $filled;
}

function assisted_tasks_validate_minimum(array $form): array
{
    $errors = [];
    if (empty($form['tenderTitle']) && empty($form['tenderNumber']) && empty($form['submissionDeadline'])) {
        $errors[] = 'Provide at least tender title, tender number, or submission deadline before delivery.';
    }
    return $errors;
}

function assisted_tasks_apply_extract_to_pack(array $pack, array $form, array $tender): array
{
    $pack['title'] = $form['tenderTitle'] ?? ($pack['title'] ?? $tender['title'] ?? 'Tender Pack');
    $pack['tenderTitle'] = $pack['title'];
    $pack['tenderNumber'] = $form['tenderNumber'] ?? ($pack['tenderNumber'] ?? '');
    $pack['departmentName'] = $form['departmentName'] ?? ($pack['departmentName'] ?? '');
    $pack['deptName'] = $pack['departmentName'];
    $pack['issuingAuthority'] = $form['issuingAuthority'] ?? ($pack['issuingAuthority'] ?? '');
    $pack['location'] = $form['location'] ?? ($pack['location'] ?? '');
    $pack['submissionDeadline'] = $form['submissionDeadline'] ?? ($pack['submissionDeadline'] ?? '');
    $pack['openingDate'] = $form['openingDate'] ?? ($pack['openingDate'] ?? '');
    $pack['preBidDate'] = $form['preBidDate'] ?? ($pack['preBidDate'] ?? '');
    if (!isset($pack['dates']) || !is_array($pack['dates'])) {
        $pack['dates'] = [];
    }
    $pack['dates']['submission'] = $pack['submissionDeadline'] ?? '';
    $pack['dates']['opening'] = $pack['openingDate'] ?? '';
    $pack['dates']['prebid'] = $pack['preBidDate'] ?? '';
    $pack['completionMonths'] = $form['completionMonths'] ?? ($pack['completionMonths'] ?? null);
    $pack['bidValidityDays'] = $form['bidValidityDays'] ?? ($pack['bidValidityDays'] ?? null);
    $pack['fees'] = $form['fees'] ?? ($pack['fees'] ?? []);
    $pack['tenderFeeText'] = $form['fees']['tenderFeeText'] ?? ($pack['tenderFeeText'] ?? null);
    $pack['emdText'] = $form['fees']['emdText'] ?? ($pack['emdText'] ?? null);
    $pack['sdText'] = $form['fees']['sdText'] ?? ($pack['sdText'] ?? null);
    $pack['pgText'] = $form['fees']['pgText'] ?? ($pack['pgText'] ?? null);
    $pack['annexures'] = $form['annexures'] ?? [];
    $pack['annexureList'] = $pack['annexures'];
    $pack['formats'] = $form['formats'] ?? [];
    $pack['restrictedAnnexures'] = $form['restrictedAnnexures'] ?? [];

    $packChecklist = [];
    foreach ($form['checklist'] ?? [] as $item) {
        $packChecklist[] = [
            'itemId' => $item['id'] ?? assisted_tasks_generate_checklist_id(),
            'title' => $item['title'] ?? '',
            'description' => '',
            'required' => !empty($item['required']),
            'status' => 'pending',
            'category' => $item['category'] ?? 'Other',
            'notes' => $item['notes'] ?? '',
            'sourceSnippet' => $item['snippet'] ?? '',
        ];
    }
    $pack['checklist'] = $packChecklist;
    $pack['items'] = pack_items_from_checklist($packChecklist);

    if (!isset($pack['sourceTender']) || !is_array($pack['sourceTender'])) {
        $pack['sourceTender'] = [];
    }
    $pack['sourceTender']['id'] = $tender['id'] ?? ($pack['sourceTender']['id'] ?? '');
    $pack['sourceTender']['type'] = 'OFFTD';
    $pack['sourceTender']['departmentName'] = $pack['departmentName'] ?? '';
    $pack['sourceTender']['issuingAuthority'] = $pack['issuingAuthority'] ?? '';
    $pack['sourceTender']['submissionDeadline'] = $pack['submissionDeadline'] ?? '';
    $pack['sourceTender']['openingDate'] = $pack['openingDate'] ?? '';
    $pack['sourceTender']['preBidDate'] = $pack['preBidDate'] ?? '';

    return $pack;
}
