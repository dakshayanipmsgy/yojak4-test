<?php
declare(strict_types=1);

const TEMPLATE_LIBRARY_DIR = DATA_PATH . '/library/templates';

function global_templates_dir(): string
{
    return TEMPLATE_LIBRARY_DIR;
}

function global_templates_index_path(): string
{
    return global_templates_dir() . '/index.json';
}

function global_template_path(string $templateId): string
{
    return global_templates_dir() . '/' . $templateId . '.json';
}

function ensure_global_templates_env(): void
{
    $dir = global_templates_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!file_exists(global_templates_index_path())) {
        writeJsonAtomic(global_templates_index_path(), []);
    }
}

function generate_template_id(string $prefix = 'TPL'): string
{
    $date = now_kolkata()->format('Ymd');
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = $prefix . '-' . $date . '-' . $suffix;
    } while (file_exists(global_template_path($candidate)));

    return $candidate;
}

function template_default_rules(): array
{
    return [
        'allowManualBlanks' => true,
        'printBlanksAsLines' => true,
        'hidePlaceholderTokensOnPrint' => true,
    ];
}

function template_extract_placeholders_used(string $body): array
{
    $matches = [];
    $placeholders = [];
    if (preg_match_all('/{{\s*([^}]+)\s*}}/', $body, $matches)) {
        foreach ($matches[1] as $token) {
            $token = trim((string)$token);
            if ($token === '') {
                continue;
            }
            $placeholders[] = $token;
        }
    }
    return array_values(array_unique($placeholders));
}

function normalize_template_schema(array $template, string $scope, string $yojId = ''): array
{
    $now = now_kolkata()->format(DateTime::ATOM);
    $templateId = $template['templateId'] ?? ($template['tplId'] ?? ($template['id'] ?? ''));
    if ($templateId === '') {
        $templateId = generate_template_id('TPL');
    }
    $title = trim((string)($template['title'] ?? ($template['name'] ?? 'Template')));
    $body = (string)($template['body'] ?? ($template['bodyTemplate'] ?? ($template['renderTemplate'] ?? '')));
    $placeholders = $template['placeholdersUsed'] ?? ($template['placeholders'] ?? template_extract_placeholders_used($body));
    if (!is_array($placeholders)) {
        $placeholders = template_extract_placeholders_used($body);
    }

    $normalized = [
        'templateId' => $templateId,
        'tplId' => $templateId,
        'scope' => $scope,
        'owner' => [
            'yojId' => $yojId !== '' ? $yojId : (string)($template['owner']['yojId'] ?? ''),
        ],
        'title' => $title !== '' ? $title : 'Template',
        'name' => $title !== '' ? $title : 'Template',
        'category' => $template['category'] ?? 'tender',
        'description' => trim((string)($template['description'] ?? '')),
        'bodyType' => $template['bodyType'] ?? 'simple_html',
        'body' => $body,
        'placeholdersUsed' => array_values(array_unique(array_filter(array_map('trim', $placeholders)))),
        'placeholders' => array_values(array_unique(array_filter(array_map('trim', $placeholders)))),
        'tables' => is_array($template['tables'] ?? null) ? $template['tables'] : [],
        'rules' => array_merge(template_default_rules(), is_array($template['rules'] ?? null) ? $template['rules'] : []),
        'createdAt' => $template['createdAt'] ?? $now,
        'updatedAt' => $now,
        'status' => in_array($template['status'] ?? 'active', ['active', 'archived'], true) ? ($template['status'] ?? 'active') : 'active',
    ];

    return $normalized;
}

function contractor_templates_dir(string $yojId): string
{
    return contractors_approved_path($yojId) . '/templates';
}

function contractor_templates_index_path(string $yojId): string
{
    return contractor_templates_dir($yojId) . '/index.json';
}

function contractor_template_path(string $yojId, string $tplId): string
{
    return contractor_templates_dir($yojId) . '/' . $tplId . '.json';
}

function ensure_contractor_templates_env(string $yojId): void
{
    $dir = contractor_templates_dir($yojId);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!file_exists(contractor_templates_index_path($yojId))) {
        writeJsonAtomic(contractor_templates_index_path($yojId), []);
    }
}

function generate_contractor_template_id(string $yojId): string
{
    ensure_contractor_templates_env($yojId);
    do {
        $candidate = generate_template_id('TPL');
    } while (file_exists(contractor_template_path($yojId, $candidate)));

    return $candidate;
}

function load_contractor_template_index(string $yojId): array
{
    ensure_contractor_templates_env($yojId);
    $index = readJson(contractor_templates_index_path($yojId));
    return is_array($index) ? array_values($index) : [];
}

function save_contractor_template_index(string $yojId, array $records): void
{
    ensure_contractor_templates_env($yojId);
    writeJsonAtomic(contractor_templates_index_path($yojId), array_values($records));
}

function load_contractor_template(string $yojId, string $tplId): ?array
{
    ensure_contractor_templates_env($yojId);
    $path = contractor_template_path($yojId, $tplId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ? normalize_template_schema($data, 'contractor', $yojId) : null;
}

function load_contractor_templates_full(string $yojId): array
{
    $templates = [];
    foreach (load_contractor_template_index($yojId) as $entry) {
        $tpl = load_contractor_template($yojId, $entry['tplId'] ?? '');
        if ($tpl) {
            $templates[] = normalize_template_schema($tpl, 'contractor', $yojId);
        }
    }
    return $templates;
}

function save_contractor_template(string $yojId, array $template): void
{
    $template = normalize_template_schema($template, 'contractor', $yojId);
    if (empty($template['templateId'])) {
        throw new InvalidArgumentException('tplId missing');
    }
    ensure_contractor_templates_env($yojId);
    $now = now_kolkata()->format(DateTime::ATOM);
    $template['createdAt'] = $template['createdAt'] ?? $now;
    $template['updatedAt'] = $now;
    $template['language'] = $template['language'] ?? 'en';
    writeJsonAtomic(contractor_template_path($yojId, $template['templateId']), $template);

    $index = load_contractor_template_index($yojId);
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['tplId'] ?? '') === $template['templateId']) {
            $entry['name'] = $template['title'] ?? $entry['name'];
            $entry['category'] = $template['category'];
            $entry['language'] = $template['language'];
            $entry['isDefaultSeeded'] = $template['scope'] === 'global';
            $entry['updatedAt'] = $template['updatedAt'];
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'tplId' => $template['templateId'],
            'name' => $template['title'] ?? 'Template',
            'category' => $template['category'],
            'language' => $template['language'],
            'isDefaultSeeded' => $template['scope'] === 'global',
            'updatedAt' => $template['updatedAt'],
        ];
    }
    save_contractor_template_index($yojId, $index);
}

function default_contractor_templates_path(): string
{
    return DATA_PATH . '/defaults/contractor_templates.json';
}

function default_contractor_templates(): array
{
    ensure_assisted_v2_env();
    $path = default_contractor_templates_path();
    $existing = readJson($path);
    if ($existing) {
        return is_array($existing) ? $existing : [];
    }

    $now = now_kolkata()->format(DateTime::ATOM);
    $defaults = [
        [
            'title' => 'Covering Letter (Tender Submission)',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Date: {{date}}\nTo,\n{{department_name}}\nSubject: Submission of tender documents for {{tender_title}} ({{tender_number}})\n\nDear Sir/Madam,\nWe submit our tender documents for {{tender_title}}. All documents enclosed are authentic and complete to the best of our knowledge.\n\nAuthorized Signatory\n{{authorized_signatory}}\n{{designation}}\n{{contractor_firm_name}}",
            'placeholdersUsed' => ['date','department_name','tender_title','tender_number','authorized_signatory','designation','contractor_firm_name'],
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'title' => 'Bid Submission Declaration',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Subject: Declaration for submission of documents ({{tender_title}})\n\nWe hereby declare that the documents submitted are true copies of the originals. No bid amounts or rates are disclosed herein. We agree to comply with all tender terms.\n\nSincerely,\n{{contractor_firm_name}}\n{{authorized_signatory}}\n{{contact.mobile}}",
            'placeholdersUsed' => ['tender_title','contractor_firm_name','authorized_signatory','contact.mobile'],
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'title' => 'Undertaking – No Blacklisting',
            'category' => 'tender',
            'language' => 'en',
            'body' => "We certify that {{contractor_firm_name}} has not been blacklisted or debarred by any government department or PSU as of the date of this undertaking.\n\nAuthorized Signatory\n{{authorized_signatory}}\n{{contractor_firm_name}}",
            'placeholdersUsed' => ['contractor_firm_name','authorized_signatory'],
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'title' => 'Undertaking – Truthfulness',
            'category' => 'tender',
            'language' => 'en',
            'body' => "We affirm that all information and documents provided for {{tender_title}} are true, correct, and complete. We understand that false statements may lead to rejection.\n\nAuthorized Signatory\n{{authorized_signatory}}\n{{contractor_firm_name}}",
            'placeholdersUsed' => ['tender_title','authorized_signatory','contractor_firm_name'],
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'title' => 'Company Profile Sheet',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Contractor: {{contractor_firm_name}}\nContact: {{authorized_signatory}} | {{contact.mobile}} | {{contact.email}}\nRegistered Address: {{contractor_address}}\nGST: {{contractor_gst}} | PAN: {{contractor_pan}}\n\nCore Expertise: {{company.core_expertise}}\nYear of Establishment: {{company.year_established}}\nKey Licenses: {{company.key_licenses}}\n\n(Attach additional sheets if needed)",
            'placeholdersUsed' => ['contractor_firm_name','authorized_signatory','contact.mobile','contact.email','contractor_address','contractor_gst','contractor_pan','company.core_expertise','company.year_established','company.key_licenses'],
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'title' => 'Experience Summary Table',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Project | Client | Year | Scope | Completion Status\n----------------------------------------------------------------\n{{experience_summary_table}}\n\n(Use additional rows as required.)",
            'placeholdersUsed' => ['experience_summary_table'],
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'title' => 'Manpower List',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Name | Role | Experience (years) | Qualifications\n--------------------------------------------------\n{{manpower_list_table}}\n\nCertified that above personnel are available for deployment on {{tender_title}}.",
            'placeholdersUsed' => ['tender_title','manpower_list_table'],
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'title' => 'Equipment List',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Equipment | Make/Model | Quantity | Availability\n-------------------------------------------------\n{{equipment_list_table}}\n\nWe confirm these resources can be mobilized for {{tender_title}}.",
            'placeholdersUsed' => ['tender_title','equipment_list_table'],
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'title' => 'Bank Details Letter',
            'category' => 'tender',
            'language' => 'en',
            'body' => "To,\n{{department_name}}\n\nSubject: Bank details for correspondence\n\nAccount Name: {{contractor_firm_name}}\nBank: {{bank.bank_name}}\nBranch & IFSC: {{bank.branch}} / {{bank.ifsc}}\nAccount Number: {{bank.account_no}}\n\nThis is for tender-related communications only (no bid values enclosed).\n\nAuthorized Signatory\n{{authorized_signatory}}\n{{contractor_firm_name}}",
            'placeholdersUsed' => ['department_name','contractor_firm_name','authorized_signatory','bank.bank_name','bank.branch','bank.ifsc','bank.account_no'],
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'title' => 'EMD/SD Declaration',
            'category' => 'tender',
            'language' => 'en',
            'body' => "We acknowledge the EMD/SD requirements of the tender {{tender_title}} and undertake to furnish the required security in the prescribed manner if awarded.\n\nAuthorized Signatory\n{{authorized_signatory}}\n{{contractor_firm_name}}",
            'placeholdersUsed' => ['tender_title','authorized_signatory','contractor_firm_name'],
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
    ];

    writeJsonAtomic($path, $defaults);
    return $defaults;
}

function seed_default_contractor_templates(string $yojId): array
{
    ensure_contractor_templates_env($yojId);
    $defaults = default_contractor_templates();
    $existing = load_contractor_template_index($yojId);
    $existingNames = [];
    foreach ($existing as $tpl) {
        $existingNames[strtolower($tpl['name'] ?? '')] = true;
    }

    $created = [];
    foreach ($defaults as $tpl) {
        $nameKey = strtolower($tpl['title'] ?? '');
        if ($nameKey === '' || isset($existingNames[$nameKey])) {
            continue;
        }
        $tplId = generate_contractor_template_id($yojId);
        $template = [
            'templateId' => $tplId,
            'scope' => 'contractor',
            'owner' => ['yojId' => $yojId],
            'title' => $tpl['title'],
            'category' => $tpl['category'] ?? 'tender',
            'description' => '',
            'bodyType' => 'simple_html',
            'body' => $tpl['body'] ?? '',
            'placeholdersUsed' => $tpl['placeholdersUsed'] ?? [],
            'tables' => [],
            'rules' => template_default_rules(),
            'createdAt' => now_kolkata()->format(DateTime::ATOM),
            'updatedAt' => now_kolkata()->format(DateTime::ATOM),
            'status' => 'active',
        ];
        save_contractor_template($yojId, $template);
        $created[] = $template;
        $existingNames[$nameKey] = true;
    }
    return $created;
}

function load_global_template_index(): array
{
    ensure_global_templates_env();
    $index = readJson(global_templates_index_path());
    return is_array($index) ? array_values($index) : [];
}

function save_global_template_index(array $records): void
{
    ensure_global_templates_env();
    writeJsonAtomic(global_templates_index_path(), array_values($records));
}

function load_global_template(string $templateId): ?array
{
    ensure_global_templates_env();
    $path = global_template_path($templateId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ? normalize_template_schema($data, 'global') : null;
}

function load_global_templates_full(): array
{
    $templates = [];
    foreach (load_global_template_index() as $entry) {
        $tpl = load_global_template($entry['templateId'] ?? '');
        if ($tpl) {
            $templates[] = $tpl;
        }
    }
    return $templates;
}

function save_global_template(array $template): void
{
    $template = normalize_template_schema($template, 'global');
    ensure_global_templates_env();
    writeJsonAtomic(global_template_path($template['templateId']), $template);

    $index = load_global_template_index();
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['templateId'] ?? '') === $template['templateId']) {
            $entry['title'] = $template['title'];
            $entry['category'] = $template['category'];
            $entry['status'] = $template['status'];
            $entry['updatedAt'] = $template['updatedAt'];
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'templateId' => $template['templateId'],
            'title' => $template['title'],
            'category' => $template['category'],
            'status' => $template['status'],
            'updatedAt' => $template['updatedAt'],
        ];
    }
    save_global_template_index($index);
}

function ensure_global_templates_seeded(): void
{
    ensure_global_templates_env();
    $index = load_global_template_index();
    if ($index) {
        return;
    }
    $defaults = default_contractor_templates();
    foreach ($defaults as $tpl) {
        $global = normalize_template_schema($tpl, 'global');
        save_global_template($global);
    }
}

function contractor_template_context(array $contractor, array $tender): array
{
    $extracted = $tender['extracted'] ?? offline_tender_defaults();
    $address = contractor_profile_address($contractor);
    $contractorName = $contractor['firmName'] ?: ($contractor['name'] ?? 'Contractor');
    $departmentName = $tender['departmentName'] ?? ($tender['department_name'] ?? ($tender['deptName'] ?? ($tender['location'] ?? 'Department')));
    $tenderTitle = $tender['tenderTitle'] ?? ($tender['tender_title'] ?? ($tender['title'] ?? 'Tender'));
    $tenderNumber = $tender['tenderNumber'] ?? ($tender['tender_number'] ?? ($tender['id'] ?? ''));
    $placeDefault = $tender['place'] ?? ($contractor['placeDefault'] ?? ($contractor['district'] ?? ''));
    return [
        '{{contractor_firm_name}}' => $contractor['firmName'] ?: ($contractor['name'] ?? ''),
        '{{contractor_address}}' => $address,
        '{{contractor_gst}}' => $contractor['gstNumber'] ?? '',
        '{{contractor_pan}}' => $contractor['panNumber'] ?? '',
        '{{authorized_signatory}}' => $contractor['authorizedSignatoryName'] ?? ($contractor['name'] ?? 'Authorized Signatory'),
        '{{designation}}' => $contractor['authorizedSignatoryDesignation'] ?? '',
        '{{mobile}}' => $contractor['mobile'] ?? '',
        '{{email}}' => $contractor['email'] ?? '',
        '{{contact.office_phone}}' => $contractor['officePhone'] ?? ($contractor['office_phone'] ?? ''),
        '{{contact.residence_phone}}' => $contractor['residencePhone'] ?? ($contractor['residence_phone'] ?? ''),
        '{{contact.mobile}}' => $contractor['mobile'] ?? '',
        '{{contact.fax}}' => $contractor['fax'] ?? '',
        '{{contact.email}}' => $contractor['email'] ?? '',
        '{{bank.bank_name}}' => $contractor['bankName'] ?? '',
        '{{bank.branch}}' => $contractor['bankBranch'] ?? ($contractor['bank_branch'] ?? ''),
        '{{bank.account_no}}' => $contractor['bankAccount'] ?? '',
        '{{bank.ifsc}}' => $contractor['ifsc'] ?? '',
        '{{company.core_expertise}}' => $contractor['coreExpertise'] ?? '',
        '{{company.year_established}}' => $contractor['yearEstablished'] ?? '',
        '{{company.key_licenses}}' => $contractor['keyLicenses'] ?? '',
        '{{experience_summary_table}}' => '',
        '{{manpower_list_table}}' => '',
        '{{equipment_list_table}}' => '',
        '{{turnover_details}}' => '',
        '{{net_worth_as_on}}' => '',
        '{{net_worth_amount}}' => '',
        '{{net_worth_in_words}}' => '',
        '{{tender_title}}' => $tenderTitle,
        '{{tender_number}}' => $tenderNumber,
        '{{department_name}}' => $departmentName,
        '{{submission_deadline}}' => $extracted['submissionDeadline'] ?? ($tender['submission_deadline'] ?? ''),
        '{{place}}' => $placeDefault,
        '{{date}}' => now_kolkata()->format('d M Y'),
        '{{contractorName}}' => $contractorName,
        '{{firmName}}' => $contractor['firmName'] ?? ($contractor['name'] ?? ''),
        '{{firmType}}' => $contractor['firmType'] ?? '',
        '{{contactPerson}}' => $contractor['contactPerson'] ?? ($contractor['name'] ?? 'Authorized Signatory'),
        '{{contactMobile}}' => $contractor['mobile'] ?? '',
        '{{email}}' => $contractor['email'] ?? '',
        '{{address}}' => $address,
        '{{placeDefault}}' => $contractor['placeDefault'] ?? '',
        '{{authorizedSignatory}}' => $contractor['authorizedSignatoryName'] ?? ($contractor['name'] ?? 'Authorized Signatory'),
        '{{signatoryDesignation}}' => $contractor['authorizedSignatoryDesignation'] ?? '',
        '{{gstNumber}}' => $contractor['gstNumber'] ?? '',
        '{{panNumber}}' => $contractor['panNumber'] ?? '',
        '{{bankName}}' => $contractor['bankName'] ?? '',
        '{{bankAccount}}' => $contractor['bankAccount'] ?? '',
        '{{ifsc}}' => $contractor['ifsc'] ?? '',
        '{{deptName}}' => $departmentName,
        '{{tenderTitle}}' => $tenderTitle,
        '{{tenderId}}' => $tenderNumber,
        '{{tenderNumber}}' => $tenderNumber,
        '{{submissionDeadline}}' => $extracted['submissionDeadline'] ?? '',
        '{{openingDate}}' => $extracted['openingDate'] ?? '',
        '{{todayDate}}' => now_kolkata()->format('Y-m-d'),
    ];
}

function contractor_fill_template_body(string $body, array $context): string
{
    return str_replace(array_keys($context), array_values($context), $body);
}

function template_guidance_fields(string $yojId): array
{
    $contractor = load_contractor($yojId) ?? [];
    $profile = pack_profile_placeholder_values($contractor);
    $memory = pack_profile_memory_values($yojId);
    $tenderFields = array_keys(pack_tender_placeholder_values([
        'title' => '',
        'tenderTitle' => '',
        'tenderNumber' => '',
    ]));

    $fields = [];
    foreach (array_keys($profile) as $key) {
        $fields[$key] = assisted_v2_standard_label_for_key($key) ?: ucwords(str_replace(['.', '_'], ' ', $key));
    }
    foreach (array_keys($memory) as $key) {
        $fields[$key] = profile_memory_label_from_key($key);
    }
    foreach ($tenderFields as $key) {
        $fields[$key] = assisted_v2_standard_label_for_key($key) ?: ucwords(str_replace(['.', '_'], ' ', $key));
    }

    ksort($fields);
    $result = [];
    foreach ($fields as $key => $label) {
        $result[] = [
            'key' => $key,
            'label' => $label !== '' ? $label : $key,
        ];
    }
    return $result;
}
