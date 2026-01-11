<?php
declare(strict_types=1);

const ASSISTED_V2_REQUESTS_DIR = DATA_PATH . '/assisted_v2/requests';
const ASSISTED_V2_TEMPLATES_DIR = DATA_PATH . '/assisted_v2/templates';
const ASSISTED_V2_TEMPLATES_INDEX = DATA_PATH . '/assisted_v2/templates/index.json';
const ASSISTED_V2_LOG = DATA_PATH . '/logs/assisted_v2.log';
const ASSISTED_V2_PROMPT_PATH = DATA_PATH . '/assisted_v2/prompt.txt';

function assisted_v2_prompt_seed(): string
{
    return <<<'PROMPT'
You are preparing a YOJAK Assisted Pack payload from a tender NIB/NIT PDF. Output MUST be ONLY strict JSON (no markdown, no commentary).

RULES:
- Do NOT include BOQ/unit rates/quoted rates/financial bid amounts/L1 values.
- You MAY include Tender Fee / EMD / Security Deposit / Performance Guarantee (non-bid fees), even with currency.
- If tender mentions “Price Bid/Financial Bid/BOQ/SOR”, list it only in restrictedAnnexures (title only) and DO NOT generate any pricing templates.

OUTPUT JSON EXACT SHAPE:
{
  "meta": {
    "documentType": "NIB",
    "tenderTitle": "",
    "tenderNumber": "",
    "issuingAuthority": "",
    "departmentName": "",
    "location": ""
  },
  "dates": {
    "nitPublishDate": null,
    "preBidMeetingDate": null,
    "submissionDeadline": null,
    "openingDate": null
  },
  "duration": { "completionMonths": null, "bidValidityDays": null },
  "fees": { "tenderFeeText": null, "emdText": null, "sdText": null, "pgText": null },

  "eligibilityDocs": [],
  "annexures": [],
  "formats": [],
  "restrictedAnnexures": [],

  "checklist": [
    { "title": "", "category": "Eligibility|Fees|Forms|Technical|Submission|Declarations|Other", "required": true, "notes": "", "snippet": "" }
  ],

  "annexureTemplates": [
    {
      "annexureCode": "Annexure-1",
      "title": "",
      "type": "cover_letter|declaration|poa|turnover_certificate|net_worth_certificate|info_sheet|undertaking|other",
      "body": "",
      "placeholders": ["{{contractor_firm_name}}","{{contractor_address}}","{{contractor_gst}}","{{contractor_pan}}","{{authorized_signatory}}","{{designation}}","{{tender_title}}","{{tender_number}}","{{department_name}}","{{place}}","{{date}}","{{submission_deadline}}","{{emd_text}}","{{fee_text}}"]
    }
  ],

  "notes": [],
  "sourceSnippets": []
}

CHECKLIST:
- Provide 15–30 items if possible.
- snippet should be an exact short quote from the PDF (max 160 chars) or "".

ANNEXURE TEMPLATES:
- Generate full printable bodies (English) for annexures referenced in the tender:
  Covering letters, declarations, PoA, turnover/net worth certificates, MSME undertaking, bidder info sheet, etc.
- Use placeholders; leave blanks where unknown. Do not include pricing/rates templates.

Now read the uploaded PDF and output ONLY the JSON.
PROMPT;
}

function ensure_assisted_v2_env(): void
{
    $paths = [
        DATA_PATH . '/assisted_v2',
        ASSISTED_V2_REQUESTS_DIR,
        ASSISTED_V2_TEMPLATES_DIR,
        DATA_PATH . '/logs',
    ];
    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }

    if (!file_exists(ASSISTED_V2_LOG)) {
        touch(ASSISTED_V2_LOG);
    }

    if (!file_exists(ASSISTED_V2_TEMPLATES_INDEX)) {
        writeJsonAtomic(ASSISTED_V2_TEMPLATES_INDEX, [
            'templates' => [],
            'updatedAt' => now_kolkata()->format(DateTime::ATOM),
        ]);
    }

    if (!file_exists(ASSISTED_V2_PROMPT_PATH)) {
        $handle = fopen(ASSISTED_V2_PROMPT_PATH, 'c');
        if ($handle) {
            flock($handle, LOCK_EX);
            ftruncate($handle, 0);
            fwrite($handle, assisted_v2_prompt_seed());
            fflush($handle);
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

function assisted_v2_prompt_text(): string
{
    ensure_assisted_v2_env();
    $content = file_get_contents(ASSISTED_V2_PROMPT_PATH);
    $content = $content === false ? assisted_v2_prompt_seed() : $content;
    return trim($content);
}

function assisted_v2_request_path(string $reqId): string
{
    return ASSISTED_V2_REQUESTS_DIR . '/' . $reqId . '.json';
}

function assisted_v2_generate_req_id(): string
{
    ensure_assisted_v2_env();
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $candidate = 'ASR-' . now_kolkata()->format('Ymd') . '-' . $suffix;
    } while (file_exists(assisted_v2_request_path($candidate)));

    return $candidate;
}

function assisted_v2_list_requests(): array
{
    ensure_assisted_v2_env();
    $files = glob(ASSISTED_V2_REQUESTS_DIR . '/*.json') ?: [];
    $requests = [];
    foreach ($files as $file) {
        $data = readJson($file);
        if ($data) {
            $requests[] = $data;
        }
    }
    usort($requests, static fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
    return $requests;
}

function assisted_v2_load_request(string $reqId): ?array
{
    ensure_assisted_v2_env();
    $path = assisted_v2_request_path($reqId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function assisted_v2_save_request(array $request): void
{
    ensure_assisted_v2_env();
    if (empty($request['reqId'])) {
        throw new InvalidArgumentException('Missing request id.');
    }
    $request['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(assisted_v2_request_path($request['reqId']), $request);
}

function assisted_v2_active_request_for_tender(string $yojId, string $offtdId): ?array
{
    foreach (assisted_v2_list_requests() as $request) {
        if (($request['contractor']['yojId'] ?? '') !== $yojId) {
            continue;
        }
        if (($request['source']['offtdId'] ?? '') !== $offtdId) {
            continue;
        }
        if (in_array($request['status'] ?? '', ['pending', 'in_progress'], true)) {
            return $request;
        }
    }
    return null;
}

function assisted_v2_latest_request_for_tender(string $yojId, string $offtdId): ?array
{
    $latest = null;
    foreach (assisted_v2_list_requests() as $request) {
        if (($request['contractor']['yojId'] ?? '') !== $yojId) {
            continue;
        }
        if (($request['source']['offtdId'] ?? '') !== $offtdId) {
            continue;
        }
        if (!$latest || strcmp($request['createdAt'] ?? '', $latest['createdAt'] ?? '') > 0) {
            $latest = $request;
        }
    }
    return $latest;
}

function assisted_v2_pick_tender_pdf(array $tender): ?array
{
    $files = $tender['sourceFiles'] ?? [];
    foreach ($files as $file) {
        $path = $file['path'] ?? '';
        if ($path !== '') {
            return $file;
        }
    }
    return null;
}

function assisted_v2_create_request(string $yojId, string $offtdId, array $tender): array
{
    ensure_assisted_v2_env();
    $active = assisted_v2_active_request_for_tender($yojId, $offtdId);
    if ($active) {
        throw new RuntimeException('An assisted pack request is already active for this tender.');
    }

    $pdfRef = assisted_v2_pick_tender_pdf($tender);
    if (!$pdfRef) {
        throw new RuntimeException('Upload at least one tender PDF before requesting assisted pack.');
    }

    $contractor = load_contractor($yojId) ?? [];
    $now = now_kolkata()->format(DateTime::ATOM);
    $reqId = assisted_v2_generate_req_id();
    $request = [
        'reqId' => $reqId,
        'createdAt' => $now,
        'updatedAt' => $now,
        'status' => 'pending',
        'createdBy' => [
            'userType' => 'contractor',
            'yojId' => $yojId,
        ],
        'contractor' => [
            'yojId' => $yojId,
            'name' => $contractor['firmName'] ?? ($contractor['name'] ?? 'Contractor'),
            'mobile' => $contractor['mobile'] ?? '',
        ],
        'source' => [
            'type' => 'offline_tender',
            'offtdId' => $offtdId,
            'tenderTitle' => $tender['title'] ?? '',
            'tenderNumber' => $tender['tenderNumber'] ?? '',
            'tenderPdfPath' => $pdfRef['path'] ?? '',
        ],
        'staff' => [
            'assignedTo' => null,
            'processedBy' => null,
            'processedAt' => null,
        ],
        'result' => [
            'packId' => null,
            'templateUsedId' => null,
            'savedTemplateId' => null,
        ],
        'audit' => [
            [
                'at' => $now,
                'event' => 'REQUEST_CREATED',
                'actor' => 'contractor:' . $yojId,
            ],
        ],
        'reject' => [
            'reason' => null,
        ],
        'draftPayload' => null,
    ];
    assisted_v2_save_request($request);
    assisted_v2_log_event([
        'event' => 'request_created',
        'reqId' => $reqId,
        'yojId' => $yojId,
        'offtdId' => $offtdId,
    ]);
    return $request;
}

function assisted_v2_append_audit(array &$request, string $actor, string $event): void
{
    if (!isset($request['audit']) || !is_array($request['audit'])) {
        $request['audit'] = [];
    }
    $request['audit'][] = [
        'at' => now_kolkata()->format(DateTime::ATOM),
        'event' => $event,
        'actor' => $actor,
    ];
}

function assisted_v2_actor_label(array $actor): string
{
    if (($actor['type'] ?? '') === 'superadmin') {
        return 'superadmin';
    }
    if (($actor['type'] ?? '') === 'employee') {
        return 'employee:' . ($actor['empId'] ?? ($actor['username'] ?? ''));
    }
    return $actor['type'] ?? 'unknown';
}

function assisted_v2_assign_request(array &$request, array $actor): void
{
    $request['staff']['assignedTo'] = assisted_v2_actor_label($actor);
    if (($request['status'] ?? '') === 'pending') {
        $request['status'] = 'in_progress';
    }
}

function assisted_v2_log_event(array $payload): void
{
    logEvent(ASSISTED_V2_LOG, $payload);
}

function assisted_v2_template_index(): array
{
    ensure_assisted_v2_env();
    $data = readJson(ASSISTED_V2_TEMPLATES_INDEX);
    if (!$data || !isset($data['templates']) || !is_array($data['templates'])) {
        $data = ['templates' => [], 'updatedAt' => now_kolkata()->format(DateTime::ATOM)];
    }
    return $data;
}

function assisted_v2_save_template_index(array $data): void
{
    ensure_assisted_v2_env();
    $data['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(ASSISTED_V2_TEMPLATES_INDEX, $data);
}

function assisted_v2_template_path(string $templateId): string
{
    return ASSISTED_V2_TEMPLATES_DIR . '/' . $templateId . '/template.json';
}

function assisted_v2_generate_template_id(): string
{
    ensure_assisted_v2_env();
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $candidate = 'TPL-' . $suffix;
    } while (file_exists(assisted_v2_template_path($candidate)));
    return $candidate;
}

function assisted_v2_load_template(string $templateId): ?array
{
    $path = assisted_v2_template_path($templateId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function assisted_v2_save_template(array $template): void
{
    if (empty($template['templateId'])) {
        throw new InvalidArgumentException('Missing template id.');
    }
    $dir = dirname(assisted_v2_template_path($template['templateId']));
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    writeJsonAtomic(assisted_v2_template_path($template['templateId']), $template);
}

function assisted_v2_auto_template_name(array $payload): array
{
    $meta = $payload['meta'] ?? [];
    $source = trim((string)($meta['issuingAuthority'] ?? ''));
    if ($source === '') {
        $source = trim((string)($meta['departmentName'] ?? ''));
    }
    $docType = trim((string)($meta['documentType'] ?? 'NIB'));
    $base = trim($source);
    if ($base === '') {
        $base = 'Department';
    }
    $baseName = $base . ' ' . ($docType !== '' ? $docType : 'NIB');

    $index = assisted_v2_template_index();
    $existingNames = array_map(static fn($tpl) => (string)($tpl['name'] ?? ''), $index['templates'] ?? []);
    $version = 1;
    $name = $baseName . ' v' . $version;
    while (in_array($name, $existingNames, true)) {
        $version++;
        $name = $baseName . ' v' . $version;
    }

    $hints = [];
    foreach ([$meta['issuingAuthority'] ?? '', $meta['departmentName'] ?? ''] as $hint) {
        $hint = trim((string)$hint);
        if ($hint !== '') {
            $hints[] = $hint;
        }
    }
    $hints = array_values(array_unique($hints));

    return [
        'name' => $name,
        'departmentHints' => $hints,
        'version' => $version,
        'docTypes' => array_values(array_filter([$docType])),
    ];
}

function assisted_v2_create_template_from_payload(array $payload, string $actor): string
{
    $templateId = assisted_v2_generate_template_id();
    $now = now_kolkata()->format(DateTime::ATOM);
    $auto = assisted_v2_auto_template_name($payload);
    $template = [
        'templateId' => $templateId,
        'name' => $auto['name'],
        'departmentHints' => $auto['departmentHints'],
        'docTypes' => $auto['docTypes'],
        'version' => $auto['version'],
        'createdAt' => $now,
        'updatedAt' => $now,
        'packBlueprint' => [
            'checklistDefaults' => $payload['checklist'] ?? [],
            'annexureTemplates' => $payload['annexureTemplates'] ?? [],
            'restrictedPatterns' => $payload['restrictedAnnexures'] ?? [],
        ],
    ];
    assisted_v2_save_template($template);
    $index = assisted_v2_template_index();
    $index['templates'][] = [
        'templateId' => $templateId,
        'name' => $template['name'],
        'departmentHints' => $template['departmentHints'],
        'docTypes' => $template['docTypes'],
        'createdAt' => $now,
        'updatedAt' => $now,
        'createdBy' => $actor,
        'version' => $template['version'],
    ];
    assisted_v2_save_template_index($index);
    assisted_v2_log_event([
        'event' => 'template_saved',
        'templateId' => $templateId,
        'name' => $template['name'],
        'actor' => $actor,
    ]);
    return $templateId;
}

function assisted_v2_apply_template_to_pack(array $template, array $tender, array $contractor, ?string $templateId = null): array
{
    $extracted = is_array($tender['extracted'] ?? null) ? $tender['extracted'] : [];
    $payload = [
        'meta' => [
            'documentType' => $template['docTypes'][0] ?? 'NIB',
            'tenderTitle' => $tender['title'] ?? '',
            'tenderNumber' => $tender['tenderNumber'] ?? '',
            'issuingAuthority' => $tender['issuingAuthority'] ?? '',
            'departmentName' => $tender['departmentName'] ?? '',
            'location' => $tender['location'] ?? '',
        ],
        'dates' => [
            'nitPublishDate' => $extracted['publishDate'] ?? null,
            'preBidMeetingDate' => $extracted['preBidMeetingDate'] ?? null,
            'submissionDeadline' => $extracted['submissionDeadline'] ?? null,
            'openingDate' => $extracted['openingDate'] ?? null,
        ],
        'duration' => [
            'completionMonths' => $extracted['completionMonths'] ?? null,
            'bidValidityDays' => $extracted['bidValidityDays'] ?? null,
        ],
        'fees' => [
            'tenderFeeText' => $extracted['tenderFeeText'] ?? null,
            'emdText' => $extracted['emdText'] ?? null,
            'sdText' => $extracted['sdText'] ?? null,
            'pgText' => $extracted['pgText'] ?? null,
        ],
        'eligibilityDocs' => [],
        'annexures' => [],
        'formats' => [],
        'restrictedAnnexures' => [],
        'checklist' => $template['packBlueprint']['checklistDefaults'] ?? [],
        'annexureTemplates' => $template['packBlueprint']['annexureTemplates'] ?? [],
        'notes' => [],
        'sourceSnippets' => [],
    ];
    $normalized = assisted_v2_normalize_payload($payload);
    return assisted_v2_build_pack_from_payload($normalized, $tender, $contractor, $templateId);
}

function assisted_v2_build_pack_from_payload(array $payload, array $tender, array $contractor, ?string $templateId = null): array
{
    $yojId = $tender['yojId'] ?? '';
    $offtdId = $tender['id'] ?? '';
    if ($yojId === '' || $offtdId === '') {
        throw new RuntimeException('Invalid tender context.');
    }
    $context = 'tender';
    ensure_packs_env($yojId, $context);

    $existing = find_pack_by_source($yojId, 'OFFTD', $offtdId, $context);
    $now = now_kolkata()->format(DateTime::ATOM);
    if ($existing) {
        $pack = $existing;
    } else {
        $packId = generate_pack_id($yojId, $context);
        $pack = [
            'packId' => $packId,
            'yojId' => $yojId,
            'title' => $tender['title'] ?? 'Tender Pack',
            'sourceTender' => [
                'type' => 'OFFTD',
                'id' => $offtdId,
                'source' => 'assisted_v2',
            ],
            'source' => 'assisted_v2',
            'createdAt' => $now,
            'status' => 'Pending',
            'items' => [],
            'generatedDocs' => [],
            'defaultTemplatesApplied' => false,
        ];
    }

    $meta = $payload['meta'] ?? [];
    $dates = $payload['dates'] ?? [];
    $duration = $payload['duration'] ?? [];
    if (!isset($pack['dates']) || !is_array($pack['dates'])) {
        $pack['dates'] = [];
    }
    $pack['updatedAt'] = $now;
    $pack['title'] = $meta['tenderTitle'] ?? ($tender['title'] ?? $pack['title'] ?? 'Tender Pack');
    $pack['tenderTitle'] = $pack['title'];
    $pack['tenderNumber'] = $meta['tenderNumber'] ?? ($tender['tenderNumber'] ?? '');
    $pack['departmentName'] = $meta['departmentName'] ?? ($meta['issuingAuthority'] ?? ($tender['departmentName'] ?? ''));
    $pack['deptName'] = $pack['departmentName'];
    $pack['sourceTender']['id'] = $offtdId;
    $pack['sourceTender']['type'] = 'OFFTD';
    $pack['sourceTender']['departmentName'] = $pack['departmentName'];
    $pack['dates']['submission'] = $dates['submissionDeadline'] ?? '';
    $pack['dates']['opening'] = $dates['openingDate'] ?? '';
    $pack['dates']['prebid'] = $dates['preBidMeetingDate'] ?? '';
    $pack['submissionDeadline'] = $dates['submissionDeadline'] ?? '';
    $pack['openingDate'] = $dates['openingDate'] ?? '';
    $pack['completionMonths'] = $duration['completionMonths'] ?? null;
    $pack['bidValidityDays'] = $duration['bidValidityDays'] ?? null;
    $pack['fees'] = $payload['fees'] ?? [];

    $pack['checklist'] = assisted_v2_checklist_for_pack($payload['checklist'] ?? []);
    $pack['items'] = pack_items_from_checklist($pack['checklist']);
    $pack['annexures'] = $payload['annexures'] ?? [];
    $pack['annexureList'] = $pack['annexures'];
    $pack['formats'] = $payload['formats'] ?? [];
    $pack['restrictedAnnexures'] = $payload['restrictedAnnexures'] ?? [];
    if ($templateId) {
        $pack['templateUsedId'] = $templateId;
    }

    $pack = pack_apply_schema_defaults($pack);
    assisted_v2_replace_pack_annexures($pack, $payload['annexureTemplates'] ?? [], $context);
    save_pack($pack, $context);

    pack_log([
        'event' => 'assisted_v2_pack_sync',
        'yojId' => $yojId,
        'packId' => $pack['packId'],
        'offtdId' => $offtdId,
        'annexures' => count($pack['annexureList'] ?? []),
        'templatesGenerated' => count($payload['annexureTemplates'] ?? []),
        'restrictedCount' => count($pack['restrictedAnnexures'] ?? []),
    ]);

    return $pack;
}

function assisted_v2_replace_pack_annexures(array $pack, array $templates, string $context = 'tender'): void
{
    $yojId = $pack['yojId'];
    $packId = $pack['packId'];
    ensure_pack_annexure_env($yojId, $packId, $context);

    $annexDir = pack_annexures_dir($yojId, $packId, $context);
    foreach (glob($annexDir . '/*.json') ?: [] as $file) {
        @unlink($file);
    }
    $index = [];
    $seenCodes = [];
    foreach ($templates as $tpl) {
        $code = trim((string)($tpl['annexureCode'] ?? ''));
        if ($code === '') {
            $code = 'Annexure-' . (count($index) + 1);
        }
        if (isset($seenCodes[$code])) {
            continue;
        }
        $seenCodes[$code] = true;
        $annexId = pack_annexure_generate_id($yojId, $packId, $context);
        $record = [
            'annexId' => $annexId,
            'annexureCode' => $code,
            'title' => trim((string)($tpl['title'] ?? 'Annexure')),
            'type' => trim((string)($tpl['type'] ?? 'other')),
            'bodyTemplate' => (string)($tpl['body'] ?? ''),
            'placeholders' => is_array($tpl['placeholders'] ?? null) ? array_values($tpl['placeholders']) : [],
            'createdAt' => now_kolkata()->format(DateTime::ATOM),
        ];
        $index[] = [
            'annexId' => $annexId,
            'annexureCode' => $record['annexureCode'],
            'title' => $record['title'],
            'type' => $record['type'],
            'createdAt' => $record['createdAt'],
        ];
        writeJsonAtomic(pack_annexure_path($yojId, $packId, $annexId, $context), $record);
    }
    save_pack_annexure_index($yojId, $packId, $index, $context);
}

function assisted_v2_checklist_for_pack(array $checklist): array
{
    $out = [];
    foreach ($checklist as $item) {
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $out[] = [
            'itemId' => generate_pack_item_id(),
            'title' => $title,
            'description' => trim((string)($item['notes'] ?? '')),
            'required' => !empty($item['required']),
            'status' => 'pending',
            'notes' => trim((string)($item['notes'] ?? '')),
            'sourceSnippet' => trim((string)($item['snippet'] ?? '')),
            'category' => $item['category'] ?? 'Other',
        ];
    }
    return $out;
}

function assisted_v2_clean_string($value): ?string
{
    $text = trim((string)$value);
    if ($text === '') {
        return null;
    }
    return $text;
}

function assisted_v2_clean_numeric($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_numeric($value)) {
        return (int)$value;
    }
    return null;
}

function assisted_v2_normalize_string_list($value): array
{
    $out = [];
    if (is_string($value)) {
        $value = [$value];
    }
    foreach ((array)$value as $item) {
        if (is_array($item)) {
            $text = assisted_v2_clean_string($item['title'] ?? ($item['name'] ?? ''));
        } else {
            $text = assisted_v2_clean_string($item);
        }
        if ($text !== null) {
            $out[] = $text;
        }
    }
    return array_values(array_unique($out));
}

function assisted_v2_normalize_formats($value): array
{
    $out = [];
    if (is_string($value)) {
        $value = [$value];
    }
    foreach ((array)$value as $entry) {
        if (is_string($entry)) {
            $name = assisted_v2_clean_string($entry);
            if ($name !== null) {
                $out[] = ['name' => $name, 'notes' => ''];
            }
        } elseif (is_array($entry)) {
            $name = assisted_v2_clean_string($entry['name'] ?? '');
            $notes = assisted_v2_clean_string($entry['notes'] ?? '') ?? '';
            if ($name !== null) {
                $out[] = ['name' => $name, 'notes' => $notes];
            }
        }
    }
    return $out;
}

function assisted_v2_normalize_fees(array $fees): array
{
    return [
        'tenderFeeText' => assisted_v2_clean_string($fees['tenderFeeText'] ?? '') ?? null,
        'emdText' => assisted_v2_clean_string($fees['emdText'] ?? '') ?? null,
        'sdText' => assisted_v2_clean_string($fees['sdText'] ?? '') ?? null,
        'pgText' => assisted_v2_clean_string($fees['pgText'] ?? '') ?? null,
    ];
}

function assisted_v2_normalize_payload(array $payload): array
{
    $meta = $payload['meta'] ?? [];
    $dates = $payload['dates'] ?? [];
    $duration = $payload['duration'] ?? [];
    $normalized = [
        'meta' => [
            'documentType' => assisted_v2_clean_string($meta['documentType'] ?? 'NIB') ?? 'NIB',
            'tenderTitle' => assisted_v2_clean_string($meta['tenderTitle'] ?? '') ?? '',
            'tenderNumber' => assisted_v2_clean_string($meta['tenderNumber'] ?? '') ?? '',
            'issuingAuthority' => assisted_v2_clean_string($meta['issuingAuthority'] ?? '') ?? '',
            'departmentName' => assisted_v2_clean_string($meta['departmentName'] ?? '') ?? '',
            'location' => assisted_v2_clean_string($meta['location'] ?? '') ?? '',
        ],
        'dates' => [
            'nitPublishDate' => assisted_v2_clean_string($dates['nitPublishDate'] ?? null),
            'preBidMeetingDate' => assisted_v2_clean_string($dates['preBidMeetingDate'] ?? null),
            'submissionDeadline' => assisted_v2_clean_string($dates['submissionDeadline'] ?? null),
            'openingDate' => assisted_v2_clean_string($dates['openingDate'] ?? null),
        ],
        'duration' => [
            'completionMonths' => assisted_v2_clean_numeric($duration['completionMonths'] ?? null),
            'bidValidityDays' => assisted_v2_clean_numeric($duration['bidValidityDays'] ?? null),
        ],
        'fees' => assisted_v2_normalize_fees($payload['fees'] ?? []),
        'eligibilityDocs' => assisted_v2_normalize_string_list($payload['eligibilityDocs'] ?? []),
        'annexures' => assisted_v2_normalize_string_list($payload['annexures'] ?? []),
        'formats' => assisted_v2_normalize_formats($payload['formats'] ?? []),
        'restrictedAnnexures' => assisted_v2_normalize_string_list($payload['restrictedAnnexures'] ?? []),
        'checklist' => [],
        'annexureTemplates' => [],
        'notes' => assisted_v2_normalize_string_list($payload['notes'] ?? []),
        'sourceSnippets' => assisted_v2_normalize_string_list($payload['sourceSnippets'] ?? []),
    ];

    $allowedCategories = ['Eligibility', 'Fees', 'Forms', 'Technical', 'Submission', 'Declarations', 'Other'];
    foreach ((array)($payload['checklist'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = assisted_v2_clean_string($item['title'] ?? '');
        if ($title === null) {
            continue;
        }
        $category = assisted_v2_clean_string($item['category'] ?? 'Other') ?? 'Other';
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'Other';
        }
        $normalized['checklist'][] = [
            'title' => $title,
            'category' => $category,
            'required' => !empty($item['required']),
            'notes' => assisted_v2_clean_string($item['notes'] ?? '') ?? '',
            'snippet' => assisted_v2_clean_string($item['snippet'] ?? '') ?? '',
        ];
    }

    foreach ((array)($payload['annexureTemplates'] ?? []) as $tpl) {
        if (!is_array($tpl)) {
            continue;
        }
        $title = assisted_v2_clean_string($tpl['title'] ?? '');
        $code = assisted_v2_clean_string($tpl['annexureCode'] ?? '');
        if ($title === null && $code === null) {
            continue;
        }
        $normalized['annexureTemplates'][] = [
            'annexureCode' => $code ?? '',
            'title' => $title ?? 'Annexure',
            'type' => assisted_v2_clean_string($tpl['type'] ?? '') ?? 'other',
            'body' => (string)($tpl['body'] ?? ''),
            'placeholders' => is_array($tpl['placeholders'] ?? null) ? array_values($tpl['placeholders']) : [],
        ];
    }

    [$safeAnnexures, $safeFormats, $restricted] = assisted_v2_split_restricted_annexures($normalized['annexures'], $normalized['formats']);
    $normalized['annexures'] = $safeAnnexures;
    $normalized['formats'] = $safeFormats;
    $normalized['restrictedAnnexures'] = array_values(array_unique(array_merge($normalized['restrictedAnnexures'], $restricted)));
    return $normalized;
}

function assisted_v2_split_restricted_annexures(array $annexures, array $formats): array
{
    $safeAnnexures = [];
    $safeFormats = [];
    $restricted = [];
    foreach ($annexures as $annex) {
        $label = is_array($annex) ? ($annex['title'] ?? ($annex['name'] ?? '')) : (string)$annex;
        if (assisted_v2_is_restricted_financial_label(mb_strtolower($label))) {
            $restricted[] = $label;
        } else {
            $safeAnnexures[] = $annex;
        }
    }
    foreach ($formats as $fmt) {
        $label = is_array($fmt) ? ($fmt['name'] ?? ($fmt['title'] ?? '')) : (string)$fmt;
        if (assisted_v2_is_restricted_financial_label(mb_strtolower($label))) {
            $restricted[] = $label;
        } else {
            $safeFormats[] = $fmt;
        }
    }
    return [$safeAnnexures, $safeFormats, array_values(array_unique($restricted))];
}

function assisted_v2_validate_payload(array $payload): array
{
    $errors = [];
    $requiredKeys = [
        'meta', 'dates', 'duration', 'fees', 'eligibilityDocs', 'annexures', 'formats',
        'restrictedAnnexures', 'checklist', 'annexureTemplates', 'notes', 'sourceSnippets',
    ];
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $payload)) {
            $errors[] = 'Missing key: ' . $key;
        }
    }
    $normalized = assisted_v2_normalize_payload($payload);
    $forbiddenFindings = assisted_v2_detect_forbidden_pricing($normalized);
    foreach ($forbiddenFindings as $finding) {
        if (($finding['action'] ?? '') === 'blocked') {
            $errors[] = 'Forbidden pricing content detected in ' . ($finding['path'] ?? 'payload') . '.';
        }
    }
    return [
        'ok' => !$errors,
        'errors' => $errors,
        'normalized' => $normalized,
        'findings' => $forbiddenFindings,
    ];
}

function assisted_v2_sanitize_json_input(string $input): string
{
    $input = preg_replace('/\xEF\xBB\xBF/', '', $input);
    $input = str_replace(["\u{2028}", "\u{2029}"], '', $input);
    return trim($input);
}

function assisted_v2_parse_json_payload(string $input): array
{
    $sanitized = assisted_v2_sanitize_json_input($input);
    if ($sanitized === '') {
        return [
            'ok' => false,
            'errors' => ['Paste JSON payload from external AI.'],
            'normalized' => null,
            'findings' => [],
        ];
    }
    $decoded = json_decode($sanitized, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'errors' => ['Invalid JSON. Please paste the exact payload.'],
            'normalized' => null,
            'findings' => [],
        ];
    }
    return assisted_v2_validate_payload($decoded);
}

function assisted_v2_payload_summary(array $payload): array
{
    return [
        'checklistCount' => count($payload['checklist'] ?? []),
        'annexureTemplatesCount' => count($payload['annexureTemplates'] ?? []),
        'annexureCount' => count($payload['annexures'] ?? []),
        'formatCount' => count($payload['formats'] ?? []),
        'restrictedCount' => count($payload['restrictedAnnexures'] ?? []),
    ];
}

function assisted_v2_detect_forbidden_pricing(array $payload, string $path = 'root', array $context = []): array
{
    $findings = [];
    foreach ($payload as $key => $value) {
        $currentPath = $path . '.' . $key;
        $currentContext = $context;
        $segments = explode('.', $currentPath);
        $isRestrictedPath = assisted_v2_is_restricted_path($currentPath) || (!empty($currentContext['restrictedPath']));
        if ($key === 'restrictedAnnexures' || assisted_v2_is_annexure_like_path($segments)) {
            $currentContext['restrictedPath'] = true;
            $isRestrictedPath = true;
        }
        if (is_string($value)) {
            $finding = assisted_v2_evaluate_string_forbidden($value, $currentPath, [
                'restrictedPath' => $isRestrictedPath,
                'checklistItem' => assisted_v2_path_is_checklist_item($segments),
                'checklistItemData' => $currentContext['checklistItemData'] ?? null,
            ]);
            if ($finding) {
                $findings[] = $finding;
            }
        } elseif (is_array($value)) {
            $nextContext = $currentContext;
            if (assisted_v2_path_is_checklist_item($segments) && isset($value['category'])) {
                $nextContext['checklistItemData'] = $value;
            }
            $findings = array_merge($findings, assisted_v2_detect_forbidden_pricing($value, $currentPath, $nextContext));
        }
    }
    return $findings;
}

function assisted_v2_evaluate_string_forbidden(string $value, string $path, array $context = []): ?array
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $isRestrictedInfoPath = assisted_v2_is_restricted_info_path($path) || (!empty($context['restrictedInfoPath']));
    if ($isRestrictedInfoPath) {
        $hasPricingEvidence = assisted_v2_contains_pricing_numeric_evidence($value);
        $hasPricingContext = assisted_v2_contains_pricing_context_keyword($value);
        if ($hasPricingEvidence && $hasPricingContext) {
            assisted_v2_log_restricted_validation($path, 'blocked', 'BLOCK_FORBIDDEN_PRICING_EVIDENCE');
            return [
                'path' => $path,
                'action' => 'blocked',
                'snippet' => assisted_v2_redact_snippet($value),
            ];
        }
        assisted_v2_log_restricted_validation($path, 'allowed_restricted', 'RESTRICTED_REFERENCE_ALLOWED');
        return null;
    }

    $hasCurrency = assisted_v2_contains_currency_amount($value);
    $hasCurrencyMarker = assisted_v2_contains_currency_marker($value);
    $hasNumericRatePattern = assisted_v2_contains_numeric_rate_pattern($value);
    $hasAllowMarker = assisted_v2_contains_allow_marker($value);
    $hasBlockMarker = assisted_v2_contains_block_marker($value);
    $hasExplicitPricingPhrase = assisted_v2_contains_explicit_pricing_phrase($value);

    $isRestrictedPath = !empty($context['restrictedPath']);
    $isChecklistItem = !empty($context['checklistItem']);
    $checklistItemData = $context['checklistItemData'] ?? null;
    $isFeePath = str_contains($path, '.fees.') || str_ends_with($path, '.fees');
    $allowsCurrency = $isChecklistItem && assisted_v2_checklist_item_allows_currency($checklistItemData);
    if ($isFeePath) {
        $allowsCurrency = true;
    }

    if ($isRestrictedPath) {
        if ($hasBlockMarker || ($hasCurrency && $hasNumericRatePattern)) {
            assisted_v2_log_restricted_validation($path, 'blocked', 'RESTRICTED_PRICING_BLOCKED');
            return [
                'path' => $path,
                'action' => 'blocked',
                'snippet' => assisted_v2_redact_snippet($value),
            ];
        }
        assisted_v2_log_restricted_validation($path, 'allowed', 'RESTRICTED_REFERENCE_ALLOWED');
        return null;
    }

    if ($allowsCurrency) {
        if ($hasBlockMarker || ($hasCurrency && $hasNumericRatePattern && $hasExplicitPricingPhrase)) {
            assisted_v2_log_restricted_validation($path, 'blocked', 'RESTRICTED_PRICING_BLOCKED');
            return [
                'path' => $path,
                'action' => 'blocked',
                'snippet' => assisted_v2_redact_snippet($value),
            ];
        }
        if ($hasCurrency || $hasCurrencyMarker) {
            assisted_v2_log_restricted_validation($path, 'allowed', 'FEE_TEXT_ALLOWED');
            return null;
        }
    }

    if ($hasBlockMarker || ($hasCurrency && $hasNumericRatePattern && !$hasAllowMarker)) {
        assisted_v2_log_restricted_validation($path, 'blocked', 'RESTRICTED_PRICING_BLOCKED');
        return [
            'path' => $path,
            'action' => 'blocked',
            'snippet' => assisted_v2_redact_snippet($value),
        ];
    }

    if ($hasExplicitPricingPhrase && $hasNumericRatePattern) {
        assisted_v2_log_restricted_validation($path, 'blocked', 'RESTRICTED_PRICING_BLOCKED');
        return [
            'path' => $path,
            'action' => 'blocked',
            'snippet' => assisted_v2_redact_snippet($value),
        ];
    }

    if ($hasExplicitPricingPhrase || $hasCurrency || $hasCurrencyMarker) {
        assisted_v2_log_restricted_validation($path, 'warned', 'RESTRICTED_PRICING_WARNING');
        return [
            'path' => $path,
            'action' => 'warned',
            'snippet' => assisted_v2_redact_snippet($value),
        ];
    }

    return null;
}

function assisted_v2_contains_currency_marker(string $value): bool
{
    return (bool)preg_match('/(₹|rs\.?|inr|rupees)/i', $value);
}

function assisted_v2_contains_currency_amount(string $value): bool
{
    return (bool)preg_match('/(₹|rs\.?|inr)\s*\d[\d,\.]*/i', $value);
}

function assisted_v2_contains_pricing_numeric_evidence(string $value): bool
{
    if (assisted_v2_contains_currency_amount($value)) {
        return true;
    }
    if (assisted_v2_contains_numeric_rate_pattern($value)) {
        return true;
    }
    $hasDigits = (bool)preg_match('/\d/', $value);
    $hasRateWord = (bool)preg_match('/\b(rate|per|unit rate)\b/i', $value);
    return $hasDigits && ($hasRateWord || assisted_v2_contains_currency_marker($value));
}

function assisted_v2_contains_pricing_context_keyword(string $value): bool
{
    return (bool)preg_match('/(boq|bill of quant|sor|schedule of rates|quoted rate|price schedule|financial bid amount|financial bid|price bid|commercial bid|rate sheet|quote|quoted)/i', $value);
}

function assisted_v2_contains_allow_marker(string $value): bool
{
    return (bool)preg_match('/(emd|tender fee|security deposit|performance guarantee|pg)/i', $value);
}

function assisted_v2_contains_block_marker(string $value): bool
{
    return (bool)preg_match('/(boq|schedule of rates|sor|unit rate|quoted rate|l1|financial bid|price bid)/i', $value);
}

function assisted_v2_contains_explicit_pricing_phrase(string $value): bool
{
    return (bool)preg_match('/(quoted rate|unit rate|rate per|per unit|financial offer|price offer|bid price|amount quoted|bill of quantities)/i', $value);
}

function assisted_v2_is_restricted_financial_label(string $lower): bool
{
    return str_contains($lower, 'financial') || str_contains($lower, 'price bid') || str_contains($lower, 'boq') || str_contains($lower, 'sor');
}

function assisted_v2_contains_numeric_rate_pattern(string $value): bool
{
    return (bool)preg_match('/\d+\s*(per|\/)\s*(unit|sqm|sq\.?m|meter|km|kg|ton|day|month)/i', $value);
}

function assisted_v2_is_restricted_info_path(string $path): bool
{
    $lower = strtolower($path);
    $targets = [
        'root.restrictedannexures',
        'root.lists.restricted',
        'root.notes',
        'root.sourcesnippets',
    ];
    foreach ($targets as $target) {
        if ($lower === $target || str_starts_with($lower, $target . '.')) {
            return true;
        }
    }
    return false;
}

function assisted_v2_is_restricted_path(string $path): bool
{
    return str_contains($path, 'restrictedAnnexures');
}

function assisted_v2_log_restricted_validation(string $path, string $action, string $reasonCode = 'RESTRICTED_REFERENCE_ALLOWED'): void
{
    assisted_v2_log_event([
        'event' => 'V2_VALIDATE',
        'path' => $path,
        'action' => $action,
        'reasonCode' => $reasonCode,
        'at' => now_kolkata()->format(DateTime::ATOM),
    ]);
}

function assisted_v2_is_annexure_like_path(array $segments): bool
{
    return in_array('restrictedAnnexures', $segments, true) || in_array('annexures', $segments, true) || in_array('formats', $segments, true);
}

function assisted_v2_path_is_checklist_item(array $segments): bool
{
    return in_array('checklist', $segments, true);
}

function assisted_v2_checklist_item_allows_currency(?array $item): bool
{
    if (!$item) {
        return false;
    }
    $category = strtolower((string)($item['category'] ?? ''));
    if ($category === 'fees') {
        return true;
    }
    $title = strtolower((string)($item['title'] ?? ''));
    $desc = strtolower((string)($item['notes'] ?? ''));
    $quote = strtolower((string)($item['snippet'] ?? ''));
    if (assisted_v2_contains_allow_marker($title) || assisted_v2_contains_allow_marker($desc) || assisted_v2_contains_allow_marker($quote)) {
        return true;
    }
    return false;
}

function assisted_v2_redact_snippet(string $value): string
{
    $clean = assisted_v2_clean_string($value);
    if ($clean === null) {
        return '';
    }
    $clean = preg_replace('/\d/', 'X', $clean);
    return mb_substr($clean, 0, 200);
}
