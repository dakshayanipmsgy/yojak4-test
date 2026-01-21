<?php
declare(strict_types=1);

const ASSISTED_V2_REQUESTS_DIR = DATA_PATH . '/assisted_v2/requests';
const ASSISTED_V2_TEMPLATES_DIR = DATA_PATH . '/assisted_v2/templates';
const ASSISTED_V2_TEMPLATES_INDEX = DATA_PATH . '/assisted_v2/templates/index.json';
const ASSISTED_V2_LOG = DATA_PATH . '/logs/assisted_v2.log';
const ASSISTED_V2_PROMPT_PATH = DATA_PATH . '/assisted_v2/prompt.txt';
const ASSISTED_V2_FIELD_KEY_STANDARD_PATH = DATA_PATH . '/assisted_v2/field_key_standard.json';

function assisted_v2_prompt_seed(): string
{
    return <<<'PROMPT'
You are creating a YOJAK Assisted Pack v2 payload from an uploaded tender PDF.

OUTPUT MUST BE ONLY strict JSON. No markdown. No commentary.

CRITICAL RULES:
- Use ONLY these canonical keys exactly: contractor.firm_name, contractor.address, contractor.pan, contractor.gst, contractor.contact.office_phone, contractor.contact.residence_phone, contractor.contact.mobile, contractor.contact.email, contractor.bank.bank_name, contractor.bank.branch, contractor.bank.account_no, contractor.bank.ifsc, contractor.signatory.name, contractor.signatory.designation, contractor.place, contractor.date.
- Do NOT invent synonyms like company_name/pan_no/dealer_name. Use canonical keys only.
- Do NOT include BOQ unit rates, quoted rates, price calculations, L1 amounts.
- You MAY include tender fee / EMD / SD / PG text (non-bid amounts).
- If the tender contains a Commercial/Financial/Price Bid format, produce a FINANCIAL MANUAL table template:
  - Prefill ITEM DESCRIPTION, QTY, UNIT if present in the PDF.
  - Leave RATE blank.
  - AMOUNT must be derivable as QTY × RATE (do not compute; just define columns).

FILLABLE OUTPUT REQUIRED:
- Every blank line must be a field key.
- Every compliance Yes/No row must be a choice field (yes/no/na).
- Every table must include columns + rows and rowIds.
- Use placeholders ONLY as {{field:<key>}} and {{field:table:<tableId>}}.

FINAL JSON SHAPE:
{
  "meta": { "documentType":"NIB", "tenderTitle":"", "tenderNumber":"", "issuingAuthority":"", "departmentName":"", "location":"" },
  "dates": { "nitPublishDate":null, "preBidMeetingDate":null, "submissionDeadline":null, "openingDate":null },
  "duration": { "completionMonths":null, "bidValidityDays":null },
  "fees": { "tenderFeeText":null, "emdText":null, "sdText":null, "pgText":null },

  "eligibilityDocs": [],
  "annexures": [],
  "formats": [],
  "restrictedAnnexures": [],

  "fieldCatalog": [
    { "key":"contractor.firm_name","label":"Firm Name","type":"text" },
    { "key":"contractor.address","label":"Firm Address","type":"textarea" },
    { "key":"contractor.gst","label":"GST","type":"text" },
    { "key":"contractor.pan","label":"PAN","type":"text" },
    { "key":"contractor.signatory.name","label":"Authorized Signatory Name","type":"text" },
    { "key":"contractor.signatory.designation","label":"Designation","type":"text" },
    { "key":"contractor.contact.office_phone","label":"Office Phone","type":"phone" },
    { "key":"contractor.contact.residence_phone","label":"Residence Phone","type":"phone" },
    { "key":"contractor.contact.mobile","label":"Mobile","type":"phone" },
    { "key":"contractor.contact.email","label":"Email","type":"email" },
    { "key":"contractor.bank.bank_name","label":"Bank Name","type":"text" },
    { "key":"contractor.bank.branch","label":"Bank Branch","type":"text" },
    { "key":"contractor.bank.account_no","label":"Bank Account No","type":"text" },
    { "key":"contractor.bank.ifsc","label":"IFSC","type":"ifsc" },
    { "key":"contractor.place","label":"Place","type":"text" },
    { "key":"contractor.date","label":"Date","type":"date" }
  ],

  "checklist": [
    { "title":"","category":"Eligibility|Fees|Forms|Technical|Submission|Declarations|Other","required":true,"notes":"","snippet":"" }
  ],

  "annexureTemplates": [
    {
      "annexureCode":"TECH-BID",
      "title":"Technical Bid Format",
      "templateKind":"standard|compliance|table_form|financial_manual",
      "requiredFieldKeys":["contractor.firm_name","contractor.address","contractor.gst","contractor.pan","contractor.place","contractor.date","contractor.signatory.name","contractor.signatory.designation"],
      "tables":[
        {
          "tableId":"tech_compliance",
          "title":"Technical Compliance",
          "columns":[
            { "key":"parameter","label":"Parameter","type":"text","readOnly":true },
            { "key":"compliance","label":"Compliance","type":"choice","choices":["yes","no","na"] }
          ],
          "rows":[
            { "rowId":"c1","parameter":"Solar modules comply","valueFieldKey":"table.tech_compliance.c1.compliance" }
          ]
        }
      ],
      "body":"... Place: {{field:contractor.place}} Date: {{field:contractor.date}} ... {{field:table:tech_compliance}} ...",
      "notes":""
    }
  ],

  "notes": [],
  "sourceSnippets": []
}

FINANCIAL MANUAL TABLE TEMPLATE REQUIREMENT (if present in PDF):
- Create an annexureTemplate with templateKind="financial_manual" containing a tableId like "financial_bid"
- Columns should include:
  item_description (readOnly), qty (readOnly), unit (readOnly), rate (fillable number), amount (computed)
- Rows should include rowId and prefilled item_description/qty/unit if PDF has them.
- rate field key must be: table.financial_bid.<rowId>.rate
- amount field key can be: table.financial_bid.<rowId>.amount (but YOJAK will compute it from qty×rate)

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

    if (!file_exists(ASSISTED_V2_FIELD_KEY_STANDARD_PATH)) {
        writeJsonAtomic(ASSISTED_V2_FIELD_KEY_STANDARD_PATH, assisted_v2_field_key_standard_seed());
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
    } else {
        $current = file_get_contents(ASSISTED_V2_PROMPT_PATH);
        if ($current !== false && !str_contains($current, 'Use ONLY these canonical keys exactly')) {
            file_put_contents(ASSISTED_V2_PROMPT_PATH, assisted_v2_prompt_seed(), LOCK_EX);
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
    $fieldMeta = assisted_v2_field_meta_from_catalog($payload['fieldCatalog'] ?? []);
    if (!isset($pack['fieldMeta']) || !is_array($pack['fieldMeta'])) {
        $pack['fieldMeta'] = [];
    }
    $pack['fieldMeta'] = array_merge($pack['fieldMeta'], $fieldMeta);

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
    $pack = pack_seed_field_registry($pack, $contractor, $payload['annexureTemplates'] ?? []);
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
            'type' => trim((string)($tpl['type'] ?? ($tpl['templateKind'] ?? 'standard'))),
            'templateKind' => trim((string)($tpl['templateKind'] ?? ($tpl['type'] ?? 'standard'))),
            'bodyTemplate' => (string)($tpl['body'] ?? ($tpl['renderTemplate'] ?? '')),
            'renderTemplate' => (string)($tpl['renderTemplate'] ?? ($tpl['body'] ?? '')),
            'placeholders' => is_array($tpl['placeholders'] ?? null) ? array_values($tpl['placeholders']) : [],
            'requiredFields' => is_array($tpl['requiredFields'] ?? null) ? array_values($tpl['requiredFields']) : [],
            'requiredFieldKeys' => is_array($tpl['requiredFieldKeys'] ?? null) ? array_values($tpl['requiredFieldKeys']) : [],
            'tables' => is_array($tpl['tables'] ?? null) ? array_values($tpl['tables']) : [],
            'notes' => trim((string)($tpl['notes'] ?? '')),
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

function assisted_v2_normalize_field_catalog(array $fieldCatalog, array &$warnings = [], array &$keyMap = []): array
{
    $allowedTypes = ['text', 'textarea', 'date', 'number', 'choice', 'phone', 'email', 'ifsc'];
    $normalized = [];
    $seen = [];

    foreach ($fieldCatalog as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $rawKey = (string)($entry['key'] ?? '');
        $labelInput = assisted_v2_clean_string($entry['label'] ?? '') ?? '';
        $key = assisted_v2_normalize_catalog_key($rawKey, $labelInput, $warnings, $keyMap);
        if ($key === '') {
            $warnings[] = 'Field catalog entry missing key.';
            continue;
        }
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $type = strtolower(trim((string)($entry['type'] ?? 'text')));
        if (!in_array($type, $allowedTypes, true)) {
            $warnings[] = 'Invalid field type for ' . $key . '. Defaulted to text.';
            $type = 'text';
        }
        $label = $labelInput !== '' ? $labelInput : assisted_v2_standard_label_for_key($key);
        if ($label === '') {
            $label = ucwords(str_replace(['.', '_'], ' ', $key));
        }
        $normalizedEntry = [
            'key' => $key,
            'label' => $label,
            'type' => $type,
        ];
        if ($type === 'choice') {
            $choices = $entry['choices'] ?? ['yes', 'no', 'na'];
            if (!is_array($choices) || !$choices) {
                $choices = ['yes', 'no', 'na'];
            }
            $normalizedEntry['choices'] = array_values(array_unique(array_map('strval', $choices)));
        }
        $normalized[] = $normalizedEntry;
    }

    return $normalized;
}

function assisted_v2_field_meta_from_catalog(array $fieldCatalog): array
{
    $meta = [];
    foreach ($fieldCatalog as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $key = pack_normalize_placeholder_key((string)($entry['key'] ?? ''));
        if ($key === '') {
            continue;
        }
        $meta[$key] = [
            'label' => $entry['label'] ?? ucwords(str_replace(['.', '_'], ' ', $key)),
            'group' => $entry['group'] ?? 'Other',
            'max' => (int)($entry['max'] ?? 200),
            'type' => $entry['type'] ?? 'text',
        ];
        if (!empty($entry['choices'])) {
            $meta[$key]['choices'] = array_values(array_unique(array_map('strval', $entry['choices'])));
        }
    }
    return $meta;
}

function assisted_v2_extract_body_placeholders(string $body, array $tableIds, array &$errors = [], array &$tablePlaceholders = []): array
{
    $keys = [];
    if (trim($body) === '') {
        return $keys;
    }
    $matches = [];
    $matched = preg_match_all('/{{\s*([^}]+)\s*}}/i', $body, $matches);
    if ($matched === false) {
        $errors[] = 'Failed to parse placeholders in annexure body.';
        return $keys;
    }
    $tableIds = array_values(array_unique(array_map('strval', $tableIds)));
    foreach ($matches[1] as $raw) {
        $raw = trim((string)$raw);
        if (stripos($raw, 'field:table:') === 0) {
            $tableId = pack_normalize_placeholder_key(substr($raw, 12));
            if ($tableId === '') {
                $errors[] = 'Invalid table placeholder {{' . $raw . '}}.';
                continue;
            }
            if (!in_array($tableId, $tableIds, true)) {
                $errors[] = 'Unknown table placeholder {{' . $raw . '}}.';
                continue;
            }
            $tablePlaceholders[] = $tableId;
            continue;
        }
        if (stripos($raw, 'field:') === 0) {
            $inner = trim(substr($raw, 6));
            $key = pack_normalize_placeholder_key($inner);
            if ($key !== '') {
                $keys[] = $key;
            }
            continue;
        }
        $suggested = pack_normalize_placeholder_key($raw);
        $errors[] = 'Unknown placeholder {{' . $raw . '}}. Use {{field:' . ($suggested !== '' ? $suggested : 'key') . '}} or {{field:table:<tableId>}} instead.';
    }
    return array_values(array_unique($keys));
}

function assisted_v2_table_cell_field_key(array $row, string $columnKey): string
{
    $fieldKey = '';
    if (isset($row['fieldKeys']) && is_array($row['fieldKeys'])) {
        $fieldKey = (string)($row['fieldKeys'][$columnKey] ?? '');
    }
    if ($fieldKey === '' && isset($row[$columnKey . 'FieldKey'])) {
        $fieldKey = (string)$row[$columnKey . 'FieldKey'];
    }
    if ($fieldKey === '' && $columnKey === 'value' && isset($row['valueFieldKey'])) {
        $fieldKey = (string)$row['valueFieldKey'];
    }
    return pack_normalize_placeholder_key($fieldKey);
}

function assisted_v2_table_field_key(string $tableId, string $rowId, string $columnKey): string
{
    return 'table.' . $tableId . '.' . $rowId . '.' . $columnKey;
}

function assisted_v2_canonicalize_table_placeholders(string $body, array &$stats): string
{
    if (trim($body) === '') {
        return $body;
    }
    $body = preg_replace_callback('/{{\s*table:\s*([a-z0-9_.-]+)\s*}}/i', static function (array $match) use (&$stats): string {
        $tableId = pack_normalize_placeholder_key($match[1] ?? '');
        $canonical = '{{field:table:' . $tableId . '}}';
        if ($match[0] !== $canonical) {
            $stats['placeholdersFixed'] = ($stats['placeholdersFixed'] ?? 0) + 1;
        }
        return $canonical;
    }, $body) ?? $body;
    $body = preg_replace_callback('/{{\s*field:\s*table:\s*([a-z0-9_.-]+)\s*}}/i', static function (array $match) use (&$stats): string {
        $tableId = pack_normalize_placeholder_key($match[1] ?? '');
        $canonical = '{{field:table:' . $tableId . '}}';
        if ($match[0] !== $canonical) {
            $stats['placeholdersFixed'] = ($stats['placeholdersFixed'] ?? 0) + 1;
        }
        return $canonical;
    }, $body) ?? $body;
    return $body;
}

function assisted_v2_extract_table_field_keys(array $tables, array &$errors = []): array
{
    $keys = [];
    foreach ($tables as $table) {
        if (!is_array($table)) {
            continue;
        }
        $columns = is_array($table['columns'] ?? null) ? $table['columns'] : [];
        foreach ((array)($table['rows'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($columns as $column) {
                if (!is_array($column)) {
                    continue;
                }
                $colKey = pack_normalize_placeholder_key((string)($column['key'] ?? ''));
                if ($colKey === '') {
                    continue;
                }
                $fieldKey = '';
                if (isset($row['fieldKeys']) && is_array($row['fieldKeys'])) {
                    $fieldKey = (string)($row['fieldKeys'][$colKey] ?? '');
                }
                if ($fieldKey === '' && isset($row[$colKey . 'FieldKey'])) {
                    $fieldKey = (string)$row[$colKey . 'FieldKey'];
                }
                if ($fieldKey === '' && $colKey === 'value' && isset($row['valueFieldKey'])) {
                    $fieldKey = (string)$row['valueFieldKey'];
                }
                $fieldKey = pack_normalize_placeholder_key($fieldKey);
                if ($fieldKey !== '') {
                    $keys[] = $fieldKey;
                } elseif (empty($column['readOnly'])) {
                    $tableId = trim((string)($table['tableId'] ?? $table['title'] ?? 'table'));
                    $rowId = trim((string)($row['rowId'] ?? 'row'));
                    $errors[] = 'Missing field key for ' . $tableId . ':' . $rowId . ':' . $colKey . '.';
                }
            }
        }
    }
    return array_values(array_unique($keys));
}

function assisted_v2_normalize_template_tables(array $tables, array &$catalogMap, array &$warnings, array &$stats, string $templateKind = '', array $keyMap = []): array
{
    $allowedTypes = ['text', 'textarea', 'date', 'number', 'choice', 'phone', 'email', 'ifsc'];
    $normalizedTables = [];
    $generatedCounts = [];

    foreach ($tables as $tableIndex => $table) {
        if (!is_array($table)) {
            continue;
        }
        $tableId = pack_normalize_placeholder_key((string)($table['tableId'] ?? $table['id'] ?? $table['title'] ?? ''));
        if ($tableId === '') {
            $tableId = 'table' . ($tableIndex + 1);
        }
        $isFinancialManual = $templateKind === 'financial_manual';
        if ($isFinancialManual) {
            $tableId = 'financial_bid';
        }
        $table['tableId'] = $tableId;
        $columns = is_array($table['columns'] ?? null) ? $table['columns'] : [];
        $rows = is_array($table['rows'] ?? null) ? $table['rows'] : [];
        $normalizedRows = [];
        $readOnlyColumns = [];
        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $colKey = pack_normalize_placeholder_key((string)($column['key'] ?? ''));
            if (!in_array($colKey, ['item_description', 'qty', 'unit'], true)) {
                continue;
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $value = trim((string)($row[$colKey] ?? ''));
                if ($value !== '') {
                    $readOnlyColumns[$colKey] = true;
                    break;
                }
            }
        }
        foreach ($columns as $idx => $column) {
            if (!is_array($column)) {
                continue;
            }
            $colKey = pack_normalize_placeholder_key((string)($column['key'] ?? ''));
            if ($isFinancialManual && in_array($colKey, ['item_description', 'qty', 'unit', 'amount'], true)) {
                $columns[$idx]['readOnly'] = true;
            }
            if ($isFinancialManual && $colKey === 'rate') {
                $columns[$idx]['readOnly'] = false;
            }
            if (isset($readOnlyColumns[$colKey])) {
                $columns[$idx]['readOnly'] = true;
            }
        }

        foreach ($rows as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowId = pack_normalize_placeholder_key((string)($row['rowId'] ?? ''));
            if ($rowId === '') {
                $rowId = 'r' . ($rowIndex + 1);
            }
            $row['rowId'] = $rowId;
            $fieldKeys = is_array($row['fieldKeys'] ?? null) ? $row['fieldKeys'] : [];
            foreach ($columns as $column) {
                if (!is_array($column)) {
                    continue;
                }
                $colKey = pack_normalize_placeholder_key((string)($column['key'] ?? ''));
                if ($colKey === '') {
                    continue;
                }
                if (!empty($column['readOnly'])) {
                    if (isset($fieldKeys[$colKey])) {
                        unset($fieldKeys[$colKey]);
                    }
                    continue;
                }
                $fieldKey = assisted_v2_table_cell_field_key($row, $colKey);
                if ($fieldKey !== '' && stripos($fieldKey, 'table.') !== 0) {
                    $fieldKey = assisted_v2_normalize_reference_key($fieldKey, $keyMap);
                }
                if ($fieldKey === '') {
                    $fieldKey = assisted_v2_table_field_key($tableId, $rowId, $colKey);
                    $fieldKeys[$colKey] = $fieldKey;
                    $generatedCounts[$tableId] = ($generatedCounts[$tableId] ?? 0) + 1;
                    $stats['tableKeysGenerated'] = ($stats['tableKeysGenerated'] ?? 0) + 1;
                }
                if ($isFinancialManual && $colKey === 'rate') {
                    $fieldKey = 'table.financial_bid.' . $rowId . '.rate';
                    $fieldKeys[$colKey] = $fieldKey;
                }
                if (!isset($catalogMap[$fieldKey])) {
                    $columnLabel = assisted_v2_clean_string($column['label'] ?? $column['key'] ?? $colKey) ?? $colKey;
                    $rowLabel = assisted_v2_clean_string($row['label'] ?? $row['name'] ?? $row['title'] ?? $row[$colKey] ?? '') ?? $rowId;
                    $label = trim($columnLabel . ' - ' . $rowLabel);
                    $type = strtolower(trim((string)($column['type'] ?? 'text')));
                    if (!in_array($type, $allowedTypes, true)) {
                        $type = 'text';
                    }
                    $catalogMap[$fieldKey] = [
                        'key' => $fieldKey,
                        'label' => $label !== '' ? $label : ucwords(str_replace(['.', '_'], ' ', $fieldKey)),
                        'type' => $type,
                    ];
                    if ($type === 'choice') {
                        $choices = $column['choices'] ?? ['yes', 'no', 'na'];
                        if (!is_array($choices) || !$choices) {
                            $choices = ['yes', 'no', 'na'];
                        }
                        $catalogMap[$fieldKey]['choices'] = array_values(array_unique(array_map('strval', $choices)));
                    }
                } elseif (($catalogMap[$fieldKey]['type'] ?? '') !== 'choice' && strtolower((string)($column['type'] ?? '')) === 'choice') {
                    $choices = $column['choices'] ?? ['yes', 'no', 'na'];
                    if (!is_array($choices) || !$choices) {
                        $choices = ['yes', 'no', 'na'];
                    }
                    $catalogMap[$fieldKey]['type'] = 'choice';
                    $catalogMap[$fieldKey]['choices'] = array_values(array_unique(array_map('strval', $choices)));
                }
            }
            if ($fieldKeys) {
                $row['fieldKeys'] = $fieldKeys;
            }
            $normalizedRows[] = $row;
        }

        $table['columns'] = $columns;
        $table['rows'] = $normalizedRows;
        $normalizedTables[] = $table;
    }

    foreach ($generatedCounts as $tableId => $count) {
        $warnings[] = 'Auto-created ' . $count . ' table field keys for ' . $tableId . '.';
    }

    return $normalizedTables;
}

function assisted_v2_normalize_payload(array $payload, array &$warnings = [], array &$stats = []): array
{
    $meta = $payload['meta'] ?? [];
    $dates = $payload['dates'] ?? [];
    $duration = $payload['duration'] ?? [];
    $keyMap = [];
    $fieldCatalog = assisted_v2_normalize_field_catalog((array)($payload['fieldCatalog'] ?? []), $warnings, $keyMap);
    $catalogMap = [];
    foreach ($fieldCatalog as $entry) {
        if (isset($entry['key'])) {
            $catalogMap[$entry['key']] = $entry;
        }
    }
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
        'fieldCatalog' => $fieldCatalog,
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
        $templateKind = assisted_v2_clean_string($tpl['templateKind'] ?? $tpl['type'] ?? '') ?? 'standard';
        $body = assisted_v2_canonicalize_table_placeholders((string)($tpl['body'] ?? ''), $stats);
        $renderTemplate = assisted_v2_canonicalize_table_placeholders((string)($tpl['renderTemplate'] ?? ($tpl['body'] ?? '')), $stats);
        $body = assisted_v2_normalize_field_placeholders($body, $keyMap, $stats);
        $renderTemplate = assisted_v2_normalize_field_placeholders($renderTemplate, $keyMap, $stats);
        $requiredKeys = [];
        foreach ((array)($tpl['requiredFieldKeys'] ?? $tpl['requiredFields'] ?? []) as $key) {
            $normalizedKey = assisted_v2_normalize_reference_key((string)$key, $keyMap);
            if ($normalizedKey !== '') {
                $requiredKeys[] = $normalizedKey;
            }
        }
        $tables = is_array($tpl['tables'] ?? null) ? array_values($tpl['tables']) : [];
        if ($templateKind === 'financial_manual') {
            $rawTableIds = [];
            foreach ($tables as $table) {
                if (!is_array($table)) {
                    continue;
                }
                $rawTableIds[] = pack_normalize_placeholder_key((string)($table['tableId'] ?? $table['id'] ?? $table['title'] ?? ''));
            }
            foreach (array_values(array_unique(array_filter($rawTableIds))) as $rawTableId) {
                if ($rawTableId === 'financial_bid') {
                    continue;
                }
                $pattern = '/{{\s*field:table:\s*' . preg_quote($rawTableId, '/') . '\s*}}/i';
                $body = preg_replace($pattern, '{{field:table:financial_bid}}', $body) ?? $body;
                $renderTemplate = preg_replace($pattern, '{{field:table:financial_bid}}', $renderTemplate) ?? $renderTemplate;
                $stats['placeholdersFixed'] = ($stats['placeholdersFixed'] ?? 0) + 1;
            }
        }
        $tables = assisted_v2_normalize_template_tables($tables, $catalogMap, $warnings, $stats, $templateKind, $keyMap);
        $tableIds = [];
        foreach ($tables as $table) {
            if (!is_array($table)) {
                continue;
            }
            $tableIds[] = pack_normalize_placeholder_key((string)($table['tableId'] ?? $table['title'] ?? ''));
        }
        $tableIds = array_values(array_filter(array_unique($tableIds)));
        $tableErrors = [];
        $tablePlaceholders = [];
        $bodyPlaceholders = assisted_v2_extract_body_placeholders($body, $tableIds, $tableErrors, $tablePlaceholders);
        if ($tableErrors) {
            $warnings = array_merge($warnings, $tableErrors);
        }
        $tableKeys = assisted_v2_extract_table_field_keys($tables);
        $requiredKeys = array_values(array_unique(array_merge($requiredKeys, $bodyPlaceholders, $tableKeys)));
        $normalized['annexureTemplates'][] = [
            'annexureCode' => $code ?? '',
            'title' => $title ?? 'Annexure',
            'type' => $templateKind,
            'templateKind' => $templateKind,
            'body' => $body,
            'renderTemplate' => $renderTemplate,
            'placeholders' => is_array($tpl['placeholders'] ?? null) ? array_values($tpl['placeholders']) : [],
            'requiredFieldKeys' => $requiredKeys,
            'requiredFields' => array_map(static fn($key) => ['key' => $key], $requiredKeys),
            'tables' => $tables,
            'notes' => assisted_v2_clean_string($tpl['notes'] ?? '') ?? '',
        ];
    }

    if ($catalogMap) {
        $normalized['fieldCatalog'] = array_values($catalogMap);
    }
    [$safeAnnexures, $safeFormats, $restricted] = assisted_v2_split_restricted_annexures($normalized['annexures'], $normalized['formats']);
    $normalized['annexures'] = $safeAnnexures;
    $normalized['formats'] = $safeFormats;
    $normalized['restrictedAnnexures'] = array_values(array_unique(array_merge($normalized['restrictedAnnexures'], $restricted)));
    if ($warnings) {
        $normalized['warnings'] = array_values(array_unique($warnings));
    }
    return $normalized;
}

function normalize_assisted_v2_payload(array $payload): array
{
    $warnings = [];
    $stats = [
        'tableKeysGenerated' => 0,
        'placeholdersFixed' => 0,
        'pricingWarnings' => 0,
    ];
    $normalized = assisted_v2_normalize_payload($payload, $warnings, $stats);
    if (!empty($normalized['warnings']) && is_array($normalized['warnings'])) {
        $warnings = array_merge($warnings, $normalized['warnings']);
        unset($normalized['warnings']);
    }
    return [
        'payload' => $normalized,
        'warnings' => array_values(array_unique($warnings)),
        'stats' => $stats,
    ];
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
    $warnings = [];
    $stats = [
        'tableKeysGenerated' => 0,
        'placeholdersFixed' => 0,
        'pricingWarnings' => 0,
    ];
    $requiredKeys = [
        'meta', 'dates', 'duration', 'fees', 'eligibilityDocs', 'annexures', 'formats', 'fieldCatalog',
        'restrictedAnnexures', 'checklist', 'annexureTemplates', 'notes', 'sourceSnippets',
    ];
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $payload)) {
            $errors[] = 'Missing key: ' . $key;
        }
    }
    $normalizedResult = normalize_assisted_v2_payload($payload);
    $normalized = $normalizedResult['payload'];
    $warnings = array_merge($warnings, $normalizedResult['warnings']);
    $stats = array_merge($stats, $normalizedResult['stats']);

    $fieldCatalog = $normalized['fieldCatalog'] ?? [];
    $fieldMeta = assisted_v2_field_meta_from_catalog($fieldCatalog);
    $missingFieldKeys = [];
    foreach ((array)($normalized['annexureTemplates'] ?? []) as $tpl) {
        $body = (string)($tpl['body'] ?? '');
        $placeholderErrors = [];
        $tableIds = [];
        foreach ((array)($tpl['tables'] ?? []) as $table) {
            if (!is_array($table)) {
                continue;
            }
            $tableIds[] = pack_normalize_placeholder_key((string)($table['tableId'] ?? $table['title'] ?? ''));
        }
        $bodyKeys = assisted_v2_extract_body_placeholders($body, array_values(array_unique($tableIds)), $placeholderErrors);
        if ($placeholderErrors) {
            $errors = array_merge($errors, $placeholderErrors);
        }
        $tableErrors = [];
        $tableKeys = assisted_v2_extract_table_field_keys((array)($tpl['tables'] ?? []), $tableErrors);
        if ($tableErrors) {
            $errors = array_merge($errors, $tableErrors);
        }
        $requiredKeys = [];
        foreach ((array)($tpl['requiredFieldKeys'] ?? []) as $key) {
            $normalizedKey = pack_normalize_placeholder_key((string)$key);
            if ($normalizedKey !== '') {
                $requiredKeys[] = $normalizedKey;
            }
        }
        $allKeys = array_values(array_unique(array_merge($requiredKeys, $bodyKeys, $tableKeys)));
        foreach ($allKeys as $key) {
            if (!isset($fieldMeta[$key])) {
                $missingFieldKeys[$key] = true;
                $fieldMeta[$key] = [
                    'label' => ucwords(str_replace(['.', '_'], ' ', $key)),
                    'group' => 'Other',
                    'max' => 200,
                    'type' => 'text',
                ];
            }
        }
    }
    if ($missingFieldKeys) {
        $missingList = implode(', ', array_keys($missingFieldKeys));
        $warnings[] = 'Missing field definitions auto-added: ' . $missingList . '.';
        foreach (array_keys($missingFieldKeys) as $key) {
            $fieldCatalog[] = [
                'key' => $key,
                'label' => ucwords(str_replace(['.', '_'], ' ', $key)),
                'type' => 'text',
            ];
        }
        $normalized['fieldCatalog'] = $fieldCatalog;
    }

    $forbiddenFindings = assisted_v2_detect_forbidden_pricing($normalized);
    $stats['pricingWarnings'] = count(array_filter($forbiddenFindings, static function (array $finding): bool {
        return ($finding['action'] ?? '') === 'warned';
    }));
    assisted_v2_log_event([
        'event' => 'V2_NORMALIZE',
        'tableKeysGenerated' => $stats['tableKeysGenerated'],
        'placeholdersFixed' => $stats['placeholdersFixed'],
        'pricingWarnings' => $stats['pricingWarnings'],
        'at' => now_kolkata()->format(DateTime::ATOM),
    ]);
    foreach ($forbiddenFindings as $finding) {
        if (($finding['action'] ?? '') === 'blocked') {
            $errors[] = 'Forbidden pricing content detected in ' . ($finding['path'] ?? 'payload') . '.';
        }
    }
    return [
        'ok' => !$errors,
        'errors' => $errors,
        'warnings' => array_values(array_unique($warnings)),
        'normalized' => $normalized,
        'findings' => $forbiddenFindings,
        'stats' => $stats,
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
            'warnings' => [],
            'normalized' => null,
            'findings' => [],
        ];
    }
    $decoded = json_decode($sanitized, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'errors' => ['Invalid JSON. Please paste the exact payload.'],
            'warnings' => [],
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
        'fieldCatalogCount' => count($payload['fieldCatalog'] ?? []),
    ];
}

function assisted_v2_preview_bundle(array $payload, array $tender, array $contractor): array
{
    $meta = $payload['meta'] ?? [];
    $dates = $payload['dates'] ?? [];
    $pack = [
        'title' => $meta['tenderTitle'] ?? ($tender['title'] ?? 'Tender Pack'),
        'tenderTitle' => $meta['tenderTitle'] ?? ($tender['title'] ?? 'Tender Pack'),
        'tenderNumber' => $meta['tenderNumber'] ?? ($tender['tenderNumber'] ?? ''),
        'departmentName' => $meta['departmentName'] ?? ($meta['issuingAuthority'] ?? ($tender['departmentName'] ?? '')),
        'deptName' => $meta['departmentName'] ?? ($meta['issuingAuthority'] ?? ($tender['departmentName'] ?? '')),
        'dates' => [
            'submission' => $dates['submissionDeadline'] ?? '',
            'opening' => $dates['openingDate'] ?? '',
        ],
        'submissionDeadline' => $dates['submissionDeadline'] ?? '',
        'openingDate' => $dates['openingDate'] ?? '',
        'fees' => $payload['fees'] ?? [],
        'fieldRegistry' => [],
    ];
    $annexures = $payload['annexureTemplates'] ?? [];
    $catalog = pack_field_meta_catalog($pack, $annexures);
    $previewKeys = [];
    foreach ((array)($payload['fieldCatalog'] ?? []) as $entry) {
        if (is_array($entry) && isset($entry['key'])) {
            $previewKeys[] = pack_normalize_placeholder_key((string)$entry['key']);
        }
    }
    foreach ($annexures as $tpl) {
        if (!is_array($tpl)) {
            continue;
        }
        foreach ((array)($tpl['requiredFieldKeys'] ?? []) as $key) {
            $previewKeys[] = pack_normalize_placeholder_key((string)$key);
        }
    }
    $previewKeys = array_values(array_filter(array_unique($previewKeys)));
    $fields = [];
    foreach ($previewKeys as $key) {
        $metaInfo = $catalog[$key] ?? ['label' => $key];
        $value = pack_resolve_field_value($key, $pack, $contractor, true);
        $fields[] = [
            'key' => $key,
            'label' => $metaInfo['label'] ?? $key,
            'value' => $value,
            'missing' => trim((string)$value) === '',
        ];
    }
    $mappingDiagnostics = profile_mapping_diagnostics($contractor, $previewKeys);
    $tables = [];
    foreach ($annexures as $tpl) {
        if (!is_array($tpl)) {
            continue;
        }
        foreach ((array)($tpl['tables'] ?? []) as $table) {
            if (!is_array($table)) {
                continue;
            }
            $tables[] = [
                'templateTitle' => $tpl['title'] ?? 'Annexure',
                'templateKind' => $tpl['templateKind'] ?? $tpl['type'] ?? '',
                'tableTitle' => $table['title'] ?? ($table['tableId'] ?? 'Table'),
                'columns' => $table['columns'] ?? [],
                'rows' => $table['rows'] ?? [],
            ];
        }
    }

    return [
        'fields' => $fields,
        'mappingDiagnostics' => $mappingDiagnostics,
        'tables' => $tables,
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

    if (assisted_v2_is_label_title_allowlist_path($path)) {
        $hasCurrencyDigits = assisted_v2_contains_currency_with_digits($value);
        $hasRateContext = assisted_v2_contains_pricing_context_keyword($value) || assisted_v2_contains_numeric_rate_pattern($value);
        if ($hasCurrencyDigits && $hasRateContext) {
            assisted_v2_log_restricted_validation($path, 'blocked', 'BLOCK_FORBIDDEN_PRICING_EVIDENCE');
            return [
                'path' => $path,
                'action' => 'blocked',
                'snippet' => assisted_v2_redact_snippet($value),
            ];
        }
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
    $hasPricingEvidence = $hasCurrency || $hasCurrencyMarker || $hasNumericRatePattern;

    $isRestrictedPath = !empty($context['restrictedPath']);
    $isChecklistItem = !empty($context['checklistItem']);
    $checklistItemData = $context['checklistItemData'] ?? null;
    $isFeePath = str_contains($path, '.fees.') || str_ends_with($path, '.fees');
    $allowsCurrency = $isChecklistItem && assisted_v2_checklist_item_allows_currency($checklistItemData);
    if ($isFeePath) {
        $allowsCurrency = true;
    }

    if ($isRestrictedPath) {
        if (($hasBlockMarker && $hasPricingEvidence) || ($hasCurrency && $hasNumericRatePattern)) {
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
        if (($hasBlockMarker && $hasPricingEvidence) || ($hasCurrency && $hasNumericRatePattern && $hasExplicitPricingPhrase)) {
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

    if (($hasBlockMarker && $hasPricingEvidence) || ($hasCurrency && $hasNumericRatePattern && !$hasAllowMarker)) {
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

function assisted_v2_contains_currency_with_digits(string $value): bool
{
    return (bool)preg_match('/(₹|rs\.?|inr|rupees)[^0-9]*\d/i', $value);
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

function assisted_v2_is_label_title_allowlist_path(string $path): bool
{
    $lower = strtolower($path);
    if (str_starts_with($lower, 'root.annexures') || str_starts_with($lower, 'root.formats') || str_starts_with($lower, 'root.restrictedannexures')) {
        return true;
    }
    if (str_starts_with($lower, 'root.fieldcatalog') && str_ends_with($lower, '.label')) {
        return true;
    }
    if (str_starts_with($lower, 'root.annexuretemplates') && str_ends_with($lower, '.title')) {
        return true;
    }
    if (str_contains($lower, '.tables.') && str_ends_with($lower, '.title')) {
        return true;
    }
    if (str_contains($lower, '.columns.') && str_ends_with($lower, '.label')) {
        return true;
    }
    return false;
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
