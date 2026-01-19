<?php
declare(strict_types=1);

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
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $candidate = 'TPL-' . $suffix;
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
    return $data ?: null;
}

function load_contractor_templates_full(string $yojId): array
{
    $legacy = load_contractor_templates_full_legacy($yojId);
    $catalog = load_contractor_template_catalog($yojId);
    $combined = [];
    $seen = [];
    foreach (array_merge($legacy, $catalog) as $tpl) {
        $tplId = (string)($tpl['tplId'] ?? '');
        if ($tplId === '' || isset($seen[$tplId])) {
            continue;
        }
        $combined[] = $tpl;
        $seen[$tplId] = true;
    }
    return $combined;
}

function save_contractor_template(string $yojId, array $template): void
{
    if (empty($template['tplId'])) {
        throw new InvalidArgumentException('tplId missing');
    }
    ensure_contractor_templates_env($yojId);
    $now = now_kolkata()->format(DateTime::ATOM);
    $template['createdAt'] = $template['createdAt'] ?? $now;
    $template['updatedAt'] = $now;
    $template['category'] = $template['category'] ?? 'tender';
    $template['language'] = $template['language'] ?? 'en';
    $template['placeholders'] = array_values(array_unique(array_filter(array_map('trim', $template['placeholders'] ?? []))));
    writeJsonAtomic(contractor_template_path($yojId, $template['tplId']), $template);

    $index = load_contractor_template_index($yojId);
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['tplId'] ?? '') === $template['tplId']) {
            $entry['name'] = $template['name'] ?? $entry['name'];
            $entry['category'] = $template['category'];
            $entry['language'] = $template['language'];
            $entry['isDefaultSeeded'] = $template['isDefaultSeeded'] ?? ($entry['isDefaultSeeded'] ?? false);
            $entry['updatedAt'] = $template['updatedAt'];
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'tplId' => $template['tplId'],
            'name' => $template['name'] ?? 'Template',
            'category' => $template['category'],
            'language' => $template['language'],
            'isDefaultSeeded' => $template['isDefaultSeeded'] ?? false,
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
            'name' => 'Covering Letter (Tender Submission)',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Date: {{date}}\nTo,\n{{department_name}}\nSubject: Submission of tender documents for {{tender_title}} ({{tender_number}})\n\nDear Sir/Madam,\nWe submit our tender documents for {{tender_title}}. All documents enclosed are authentic and complete to the best of our knowledge.\n\nAuthorized Signatory\n{{authorized_signatory}}\n{{designation}}\n{{contractor_firm_name}}",
            'placeholders' => ['{{date}}','{{department_name}}','{{tender_title}}','{{tender_number}}','{{authorized_signatory}}','{{designation}}','{{contractor_firm_name}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'Bid Submission Declaration',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Subject: Declaration for submission of documents ({{tender_title}})\n\nWe hereby declare that the documents submitted are true copies of the originals. No bid amounts or rates are disclosed herein. We agree to comply with all tender terms.\n\nSincerely,\n{{contractor_firm_name}}\n{{authorized_signatory}}\n{{contact.mobile}}",
            'placeholders' => ['{{tender_title}}','{{contractor_firm_name}}','{{authorized_signatory}}','{{contact.mobile}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'Undertaking – No Blacklisting',
            'category' => 'tender',
            'language' => 'en',
            'body' => "We certify that {{contractor_firm_name}} has not been blacklisted or debarred by any government department or PSU as of the date of this undertaking.\n\nAuthorized Signatory\n{{authorized_signatory}}\n{{contractor_firm_name}}",
            'placeholders' => ['{{contractor_firm_name}}','{{authorized_signatory}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'Undertaking – Truthfulness',
            'category' => 'tender',
            'language' => 'en',
            'body' => "We affirm that all information and documents provided for {{tender_title}} are true, correct, and complete. We understand that false statements may lead to rejection.\n\nAuthorized Signatory\n{{authorized_signatory}}\n{{contractor_firm_name}}",
            'placeholders' => ['{{tender_title}}','{{authorized_signatory}}','{{contractor_firm_name}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'Company Profile Sheet',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Contractor: {{contractor_firm_name}}\nContact: {{authorized_signatory}} | {{contact.mobile}} | {{contact.email}}\nRegistered Address: {{contractor_address}}\nGST: {{contractor_gst}} | PAN: {{contractor_pan}}\n\nCore Expertise: {{company.core_expertise}}\nYear of Establishment: {{company.year_established}}\nKey Licenses: {{company.key_licenses}}\n\n(Attach additional sheets if needed)",
            'placeholders' => ['{{contractor_firm_name}}','{{authorized_signatory}}','{{contact.mobile}}','{{contact.email}}','{{contractor_address}}','{{contractor_gst}}','{{contractor_pan}}','{{company.core_expertise}}','{{company.year_established}}','{{company.key_licenses}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'Experience Summary Table',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Project | Client | Year | Scope | Completion Status\n----------------------------------------------------------------\n{{experience_summary_table}}\n\n(Use additional rows as required.)",
            'placeholders' => ['{{experience_summary_table}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'Manpower List',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Name | Role | Experience (years) | Qualifications\n--------------------------------------------------\n{{manpower_list_table}}\n\nCertified that above personnel are available for deployment on {{tender_title}}.",
            'placeholders' => ['{{tender_title}}','{{manpower_list_table}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'Equipment List',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Equipment | Make/Model | Quantity | Availability\n-------------------------------------------------\n{{equipment_list_table}}\n\nWe confirm these resources can be mobilized for {{tender_title}}.",
            'placeholders' => ['{{tender_title}}','{{equipment_list_table}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'Bank Details Letter',
            'category' => 'tender',
            'language' => 'en',
            'body' => "To,\n{{department_name}}\n\nSubject: Bank details for correspondence\n\nAccount Name: {{contractor_firm_name}}\nBank: {{bank.bank_name}}\nBranch & IFSC: {{bank.branch}} / {{bank.ifsc}}\nAccount Number: {{bank.account_no}}\n\nThis is for tender-related communications only (no bid values enclosed).\n\nAuthorized Signatory\n{{authorized_signatory}}\n{{contractor_firm_name}}",
            'placeholders' => ['{{department_name}}','{{contractor_firm_name}}','{{authorized_signatory}}','{{bank.bank_name}}','{{bank.branch}}','{{bank.ifsc}}','{{bank.account_no}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'EMD/SD Declaration',
            'category' => 'tender',
            'language' => 'en',
            'body' => "We acknowledge the EMD/SD requirements of the tender {{tender_title}} and undertake to furnish the required security in the prescribed manner if awarded.\n\nAuthorized Signatory\n{{authorized_signatory}}\n{{contractor_firm_name}}",
            'placeholders' => ['{{tender_title}}','{{authorized_signatory}}','{{contractor_firm_name}}'],
            'isDefaultSeeded' => true,
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
        $nameKey = strtolower($tpl['name'] ?? '');
        if ($nameKey === '' || isset($existingNames[$nameKey])) {
            continue;
        }
        $tplId = generate_contractor_template_id($yojId);
        $template = [
            'tplId' => $tplId,
            'name' => $tpl['name'],
            'category' => $tpl['category'] ?? 'tender',
            'language' => $tpl['language'] ?? 'en',
            'body' => $tpl['body'] ?? '',
            'placeholders' => $tpl['placeholders'] ?? [],
            'isDefaultSeeded' => true,
            'createdAt' => now_kolkata()->format(DateTime::ATOM),
            'updatedAt' => now_kolkata()->format(DateTime::ATOM),
        ];
        save_contractor_template($yojId, $template);
        $created[] = $template;
        $existingNames[$nameKey] = true;
    }
    return $created;
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
    $rendered = preg_replace_callback('/{{\s*(?:field:)?([a-z0-9._-]+)\s*}}/i', static function (array $matches) use ($context): string {
        $raw = $matches[0] ?? '';
        if ($raw !== '' && array_key_exists($raw, $context)) {
            return (string)$context[$raw];
        }
        $key = pack_normalize_placeholder_key((string)($matches[1] ?? ''));
        if ($key !== '') {
            $tokens = [
                '{{' . $key . '}}',
                '{{field:' . $key . '}}',
            ];
            foreach ($tokens as $token) {
                if (array_key_exists($token, $context)) {
                    return (string)$context[$token];
                }
            }
        }
        return '__________';
    }, $body);

    return $rendered ?? $body;
}

function templates_global_dir(): string
{
    return DATA_PATH . '/templates/global';
}

function templates_contractor_dir(string $yojId): string
{
    return DATA_PATH . '/templates/contractors/' . $yojId;
}

function templates_global_index_path(): string
{
    return templates_global_dir() . '/index.json';
}

function templates_contractor_index_path(string $yojId): string
{
    return templates_contractor_dir($yojId) . '/index.json';
}

function template_request_dir(): string
{
    return DATA_PATH . '/template_requests';
}

function template_request_upload_dir(string $requestId): string
{
    return template_request_dir() . '/uploads/' . $requestId;
}

function template_request_index_path(): string
{
    return template_request_dir() . '/index.json';
}

function normalize_template_index(array $data): array
{
    if (isset($data['templates']) && is_array($data['templates'])) {
        return array_values($data['templates']);
    }
    return array_values($data);
}

function ensure_templates_env(string $yojId = ''): void
{
    $paths = [
        templates_global_dir(),
        template_request_dir(),
        template_request_dir() . '/uploads',
    ];
    if ($yojId !== '') {
        $paths[] = templates_contractor_dir($yojId);
    }
    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }

    if (!file_exists(templates_global_index_path())) {
        writeJsonAtomic(templates_global_index_path(), ['templates' => [], 'updatedAt' => now_kolkata()->format(DateTime::ATOM)]);
    }
    if ($yojId !== '' && !file_exists(templates_contractor_index_path($yojId))) {
        writeJsonAtomic(templates_contractor_index_path($yojId), ['templates' => [], 'updatedAt' => now_kolkata()->format(DateTime::ATOM)]);
    }
    if (!file_exists(template_request_index_path())) {
        writeJsonAtomic(template_request_index_path(), ['requests' => [], 'updatedAt' => now_kolkata()->format(DateTime::ATOM)]);
    }
}

function template_record_path(string $scope, string $templateId, string $yojId = ''): string
{
    if ($scope === 'global') {
        return templates_global_dir() . '/' . $templateId . '.json';
    }
    return templates_contractor_dir($yojId) . '/' . $templateId . '.json';
}

function load_template_index(string $scope, string $yojId = ''): array
{
    ensure_templates_env($yojId);
    $path = $scope === 'global' ? templates_global_index_path() : templates_contractor_index_path($yojId);
    $index = readJson($path);
    return normalize_template_index($index);
}

function save_template_index(string $scope, array $records, string $yojId = ''): void
{
    ensure_templates_env($yojId);
    $path = $scope === 'global' ? templates_global_index_path() : templates_contractor_index_path($yojId);
    writeJsonAtomic($path, ['templates' => array_values($records), 'updatedAt' => now_kolkata()->format(DateTime::ATOM)]);
}

function generate_template_id(string $scope = 'contractor', string $yojId = ''): string
{
    ensure_templates_env($yojId);
    $date = now_kolkata()->format('Ymd');
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = 'TPL-' . $date . '-' . $suffix;
    } while (file_exists(template_record_path($scope, $candidate, $yojId)));
    return $candidate;
}

function load_template_record(string $templateId, string $yojId = ''): ?array
{
    if ($templateId === '') {
        return null;
    }
    ensure_templates_env($yojId);
    $paths = [
        template_record_path('contractor', $templateId, $yojId),
        template_record_path('global', $templateId),
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            $data = readJson($path);
            return $data ?: null;
        }
    }
    return null;
}

function load_template_record_by_scope(string $scope, string $templateId, string $yojId = ''): ?array
{
    if ($templateId === '') {
        return null;
    }
    $path = template_record_path($scope, $templateId, $yojId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function save_template_record(string $scope, array $template, string $yojId = ''): void
{
    if (empty($template['templateId'])) {
        throw new InvalidArgumentException('templateId required.');
    }
    $now = now_kolkata()->format(DateTime::ATOM);
    $template['scope'] = $scope;
    $template['createdAt'] = $template['createdAt'] ?? $now;
    $template['updatedAt'] = $now;
    writeJsonAtomic(template_record_path($scope, $template['templateId'], $yojId), $template);

    $index = load_template_index($scope, $yojId);
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['templateId'] ?? '') === $template['templateId']) {
            $entry['title'] = $template['title'] ?? $entry['title'];
            $entry['category'] = $template['category'] ?? $entry['category'];
            $entry['scope'] = $scope;
            $entry['updatedAt'] = $template['updatedAt'];
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'templateId' => $template['templateId'],
            'title' => $template['title'] ?? 'Template',
            'category' => $template['category'] ?? 'Other',
            'scope' => $scope,
            'updatedAt' => $template['updatedAt'],
        ];
    }
    save_template_index($scope, $index, $yojId);
}

function delete_template_record(string $scope, string $templateId, string $yojId = ''): void
{
    $path = template_record_path($scope, $templateId, $yojId);
    if (file_exists($path)) {
        unlink($path);
    }
    $index = load_template_index($scope, $yojId);
    $index = array_values(array_filter($index, static fn($entry) => ($entry['templateId'] ?? '') !== $templateId));
    save_template_index($scope, $index, $yojId);
}

function template_allowed_placeholder_keys(array $contractor, ?array $pack = null): array
{
    $catalog = pack_default_field_meta();
    $keys = array_keys($catalog);
    $aliases = pack_field_aliases();
    foreach (array_keys($aliases) as $alias) {
        $keys[] = $alias;
    }
    $memory = pack_profile_memory_values((string)($contractor['yojId'] ?? ''));
    foreach (array_keys($memory) as $key) {
        $keys[] = $key;
    }
    if ($pack) {
        $tenderValues = pack_tender_placeholder_values($pack);
        foreach (array_keys($tenderValues) as $key) {
            $keys[] = $key;
        }
    }
    $keys[] = 'custom';
    return array_values(array_unique(array_filter(array_map('pack_normalize_placeholder_key', $keys))));
}

function template_extract_placeholders(string $bodyHtml): array
{
    $matches = [];
    preg_match_all('/{{\s*(?:field:)?([a-z0-9._-]+)\s*}}/i', $bodyHtml, $matches);
    $keys = [];
    foreach ($matches[1] ?? [] as $raw) {
        $key = pack_normalize_placeholder_key((string)$raw);
        if ($key !== '') {
            $keys[] = $key;
        }
    }
    return array_values(array_unique($keys));
}

function template_validate_payload(array $payload, array $contractor, ?array $pack = null, bool $allowUnknownCustom = true): array
{
    $errors = [];
    $title = trim((string)($payload['title'] ?? ''));
    $bodyHtml = (string)($payload['bodyHtml'] ?? '');
    $category = trim((string)($payload['category'] ?? 'Other'));

    if (strlen($title) < 3 || strlen($title) > 80) {
        $errors[] = 'Title must be between 3 and 80 characters.';
    }
    if (trim($bodyHtml) === '') {
        $errors[] = 'Template body is required.';
    }
    if (strlen($bodyHtml) > 200000) {
        $errors[] = 'Template body exceeds maximum size.';
    }

    $placeholders = template_extract_placeholders($bodyHtml);
    if (template_contains_forbidden_pricing($placeholders)) {
        $errors[] = 'Template contains forbidden pricing placeholders.';
    }
    $allowed = template_allowed_placeholder_keys($contractor, $pack);
    foreach ($placeholders as $key) {
        $normalized = pack_normalize_placeholder_key($key);
        if (in_array($normalized, $allowed, true)) {
            continue;
        }
        if ($allowUnknownCustom && str_starts_with($normalized, 'custom.')) {
            continue;
        }
        $errors[] = 'Unknown placeholder detected: ' . $normalized;
    }

    $template = [
        'title' => $title,
        'category' => $category !== '' ? $category : 'Other',
        'description' => trim((string)($payload['description'] ?? '')),
        'bodyHtml' => $bodyHtml,
        'placeholdersUsed' => $placeholders,
        'visibility' => [
            'contractorEditable' => true,
        ],
    ];

    return ['errors' => $errors, 'template' => $template];
}

function template_contains_forbidden_pricing(array $placeholders): bool
{
    $blocked = ['rate', 'price', 'amount', 'boq', 'bid'];
    foreach ($placeholders as $key) {
        $normalized = pack_normalize_placeholder_key((string)$key);
        foreach ($blocked as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }
    }
    return false;
}

function template_validate_advanced_json(array $payload, array $contractor, ?array $pack = null): array
{
    $errors = [];
    if (empty($payload['templateId']) || !is_string($payload['templateId'])) {
        $errors[] = 'templateId is required.';
    }
    if (empty($payload['title']) || !is_string($payload['title'])) {
        $errors[] = 'title is required.';
    }
    if (!isset($payload['bodyHtml']) || !is_string($payload['bodyHtml'])) {
        $errors[] = 'bodyHtml must be a string.';
    }

    $placeholders = template_extract_placeholders((string)($payload['bodyHtml'] ?? ''));
    if (template_contains_forbidden_pricing($placeholders)) {
        $errors[] = 'Template contains forbidden pricing placeholders.';
    }
    $allowed = template_allowed_placeholder_keys($contractor, $pack);
    foreach ($placeholders as $key) {
        $normalized = pack_normalize_placeholder_key($key);
        if (in_array($normalized, $allowed, true) || str_starts_with($normalized, 'custom.')) {
            continue;
        }
        $errors[] = 'Unknown placeholder detected: ' . $normalized;
    }

    $payload['placeholdersUsed'] = $placeholders;
    return ['errors' => $errors, 'template' => $payload];
}

function template_payload_for_pack(array $template): array
{
    return [
        'tplId' => $template['templateId'] ?? '',
        'name' => $template['title'] ?? ($template['name'] ?? 'Template'),
        'category' => strtolower((string)($template['category'] ?? 'tender')),
        'language' => 'en',
        'body' => $template['bodyHtml'] ?? ($template['body'] ?? ''),
        'placeholders' => $template['placeholdersUsed'] ?? ($template['placeholders'] ?? []),
        'templateScope' => $template['scope'] ?? 'contractor',
    ];
}

function load_contractor_template_catalog(string $yojId): array
{
    migrate_legacy_templates_to_new($yojId);
    $templates = [];
    foreach (load_template_index('global') as $entry) {
        $record = load_template_record_by_scope('global', $entry['templateId'] ?? '');
        if ($record) {
            $templates[] = template_payload_for_pack($record);
        }
    }
    foreach (load_template_index('contractor', $yojId) as $entry) {
        $record = load_template_record_by_scope('contractor', $entry['templateId'] ?? '', $yojId);
        if ($record) {
            $templates[] = template_payload_for_pack($record);
        }
    }
    return $templates;
}

function load_contractor_templates_full_legacy(string $yojId): array
{
    $templates = [];
    foreach (load_contractor_template_index($yojId) as $entry) {
        $tpl = load_contractor_template($yojId, $entry['tplId'] ?? '');
        if ($tpl) {
            $templates[] = $tpl;
        }
    }
    return $templates;
}

function migrate_legacy_templates_to_new(string $yojId): void
{
    $legacy = load_contractor_templates_full_legacy($yojId);
    if (!$legacy) {
        return;
    }
    $existing = load_template_index('contractor', $yojId);
    $existingIds = [];
    foreach ($existing as $entry) {
        $existingIds[$entry['templateId'] ?? ''] = true;
    }
    foreach ($legacy as $tpl) {
        $templateId = $tpl['tplId'] ?? '';
        if ($templateId === '' || isset($existingIds[$templateId])) {
            continue;
        }
        $record = [
            'templateId' => $templateId,
            'scope' => 'contractor',
            'owner' => ['yojId' => $yojId],
            'title' => $tpl['name'] ?? 'Template',
            'category' => $tpl['category'] ?? 'Other',
            'description' => '',
            'bodyHtml' => $tpl['body'] ?? '',
            'placeholdersUsed' => template_extract_placeholders((string)($tpl['body'] ?? '')),
            'createdAt' => $tpl['createdAt'] ?? now_kolkata()->format(DateTime::ATOM),
            'updatedAt' => $tpl['updatedAt'] ?? now_kolkata()->format(DateTime::ATOM),
            'visibility' => ['contractorEditable' => true],
        ];
        save_template_record('contractor', $record, $yojId);
        $existingIds[$templateId] = true;
    }
}

function ensure_global_templates_seeded(): void
{
    $index = load_template_index('global');
    if ($index) {
        return;
    }
    $defaults = default_contractor_templates();
    foreach ($defaults as $tpl) {
        $templateId = generate_template_id('global');
        $record = [
            'templateId' => $templateId,
            'scope' => 'global',
            'owner' => ['yojId' => 'YOJAK'],
            'title' => $tpl['name'] ?? 'Template',
            'category' => $tpl['category'] ?? 'Other',
            'description' => '',
            'bodyHtml' => $tpl['body'] ?? '',
            'placeholdersUsed' => template_extract_placeholders((string)($tpl['body'] ?? '')),
            'createdAt' => now_kolkata()->format(DateTime::ATOM),
            'updatedAt' => now_kolkata()->format(DateTime::ATOM),
            'visibility' => ['contractorEditable' => false],
        ];
        save_template_record('global', $record);
    }
}

function template_request_generate_id(): string
{
    ensure_templates_env();
    $date = now_kolkata()->format('Ymd');
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = 'REQ-' . $date . '-' . $suffix;
        $path = template_request_dir() . '/' . $candidate . '.json';
    } while (file_exists($path));
    return $candidate;
}

function template_placeholder_groups(array $contractor, string $yojId, ?array $pack = null): array
{
    $meta = pack_default_field_meta();
    $groups = [];
    foreach ($meta as $key => $spec) {
        $group = (string)($spec['group'] ?? 'Other');
        $groups[$group][] = [
            'label' => $spec['label'] ?? $key,
            'key' => $key,
            'token' => '{{field:' . $key . '}}',
        ];
    }

    if ($yojId !== '') {
        $memory = load_profile_memory($yojId);
        $customFields = [];
        foreach (($memory['fields'] ?? []) as $key => $entry) {
            $label = $entry['label'] ?? $key;
            $customFields[] = [
                'label' => $label,
                'key' => pack_normalize_placeholder_key((string)$key),
                'token' => '{{field:' . pack_normalize_placeholder_key((string)$key) . '}}',
            ];
        }
        if ($customFields) {
            $groups['Custom Saved Fields'] = $customFields;
        }
    }

    if ($pack) {
        $tenderFields = [];
        foreach (pack_tender_placeholder_values($pack) as $key => $value) {
            $tenderFields[] = [
                'label' => ucwords(str_replace(['_', '.'], ' ', $key)),
                'key' => $key,
                'token' => '{{field:' . $key . '}}',
            ];
        }
        if ($tenderFields) {
            $groups['Tender Context'] = $tenderFields;
        }
    }

    $aliases = [];
    foreach (pack_field_aliases() as $alias => $canonical) {
        $aliases[] = [
            'label' => $alias . ' → ' . $canonical,
            'key' => $alias,
            'token' => '{{field:' . $alias . '}}',
        ];
    }
    if ($aliases) {
        $groups['Aliases & Synonyms'] = $aliases;
    }

    ksort($groups);
    return $groups;
}

function template_request_path(string $requestId): string
{
    return template_request_dir() . '/' . $requestId . '.json';
}

function load_template_request(string $requestId): ?array
{
    $path = template_request_path($requestId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function load_template_requests_index(): array
{
    ensure_templates_env();
    $index = readJson(template_request_index_path());
    if (isset($index['requests']) && is_array($index['requests'])) {
        return array_values($index['requests']);
    }
    return array_values($index);
}

function save_template_request_index(array $records): void
{
    ensure_templates_env();
    writeJsonAtomic(template_request_index_path(), ['requests' => array_values($records), 'updatedAt' => now_kolkata()->format(DateTime::ATOM)]);
}

function save_template_request(array $request): void
{
    if (empty($request['requestId'])) {
        throw new InvalidArgumentException('requestId required.');
    }
    $request['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(template_request_path($request['requestId']), $request);

    $index = load_template_requests_index();
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['requestId'] ?? '') === $request['requestId']) {
            $entry['title'] = $request['title'] ?? $entry['title'];
            $entry['status'] = $request['status'] ?? $entry['status'];
            $entry['updatedAt'] = $request['updatedAt'];
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'requestId' => $request['requestId'],
            'yojId' => $request['yojId'] ?? '',
            'title' => $request['title'] ?? 'Template request',
            'status' => $request['status'] ?? 'pending',
            'createdAt' => $request['createdAt'] ?? now_kolkata()->format(DateTime::ATOM),
            'updatedAt' => $request['updatedAt'],
        ];
    }
    save_template_request_index($index);
}
