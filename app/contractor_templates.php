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
    $templates = [];
    foreach (load_contractor_template_index($yojId) as $entry) {
        $tpl = load_contractor_template($yojId, $entry['tplId'] ?? '');
        if ($tpl) {
            $templates[] = $tpl;
        }
    }
    return $templates;
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
            'body' => "Date: {{field:contractor.date}}\nTo,\n{{field:tender.departmentName}}\nSubject: Submission of tender documents for {{field:tender.title}} ({{field:tender.number}})\n\nDear Sir/Madam,\nWe submit our tender documents for {{field:tender.title}}. All documents enclosed are authentic and complete to the best of our knowledge.\n\nAuthorized Signatory\n{{field:contractor.signatory.name}}\n{{field:contractor.signatory.designation}}\n{{field:contractor.firm_name}}",
            'placeholders' => ['{{field:contractor.date}}','{{field:tender.departmentName}}','{{field:tender.title}}','{{field:tender.number}}','{{field:contractor.signatory.name}}','{{field:contractor.signatory.designation}}','{{field:contractor.firm_name}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'Bid Submission Declaration',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Subject: Declaration for submission of documents ({{field:tender.title}})\n\nWe hereby declare that the documents submitted are true copies of the originals. No bid amounts or rates are disclosed herein. We agree to comply with all tender terms.\n\nSincerely,\n{{field:contractor.firm_name}}\n{{field:contractor.signatory.name}}\n{{field:contractor.contact.mobile}}",
            'placeholders' => ['{{field:tender.title}}','{{field:contractor.firm_name}}','{{field:contractor.signatory.name}}','{{field:contractor.contact.mobile}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'Undertaking – No Blacklisting',
            'category' => 'tender',
            'language' => 'en',
            'body' => "We certify that {{field:contractor.firm_name}} has not been blacklisted or debarred by any government department or PSU as of the date of this undertaking.\n\nAuthorized Signatory\n{{field:contractor.signatory.name}}\n{{field:contractor.firm_name}}",
            'placeholders' => ['{{field:contractor.firm_name}}','{{field:contractor.signatory.name}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'Undertaking – Truthfulness',
            'category' => 'tender',
            'language' => 'en',
            'body' => "We affirm that all information and documents provided for {{field:tender.title}} are true, correct, and complete. We understand that false statements may lead to rejection.\n\nAuthorized Signatory\n{{field:contractor.signatory.name}}\n{{field:contractor.firm_name}}",
            'placeholders' => ['{{field:tender.title}}','{{field:contractor.signatory.name}}','{{field:contractor.firm_name}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'Company Profile Sheet',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Contractor: {{field:contractor.firm_name}}\nContact: {{field:contractor.signatory.name}} | {{field:contractor.contact.mobile}} | {{field:contractor.contact.email}}\nRegistered Address: {{field:contractor.address}}\nGST: {{field:contractor.gst}} | PAN: {{field:contractor.pan}}\n\nCore Expertise: {{field:contractor.company.core_expertise}}\nYear of Establishment: {{field:contractor.company.year_established}}\nKey Licenses: {{field:contractor.company.key_licenses}}\n\n(Attach additional sheets if needed)",
            'placeholders' => ['{{field:contractor.firm_name}}','{{field:contractor.signatory.name}}','{{field:contractor.contact.mobile}}','{{field:contractor.contact.email}}','{{field:contractor.address}}','{{field:contractor.gst}}','{{field:contractor.pan}}','{{field:contractor.company.core_expertise}}','{{field:contractor.company.year_established}}','{{field:contractor.company.key_licenses}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'Experience Summary Table',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Project | Client | Year | Scope | Completion Status\n----------------------------------------------------------------\n{{field:table:experience_summary}}\n\n(Use additional rows as required.)",
            'placeholders' => ['{{field:table:experience_summary}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'Manpower List',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Name | Role | Experience (years) | Qualifications\n--------------------------------------------------\n{{field:table:manpower_list}}\n\nCertified that above personnel are available for deployment on {{field:tender.title}}.",
            'placeholders' => ['{{field:tender.title}}','{{field:table:manpower_list}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'Equipment List',
            'category' => 'tender',
            'language' => 'en',
            'body' => "Equipment | Make/Model | Quantity | Availability\n-------------------------------------------------\n{{field:table:equipment_list}}\n\nWe confirm these resources can be mobilized for {{field:tender.title}}.",
            'placeholders' => ['{{field:tender.title}}','{{field:table:equipment_list}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'Bank Details Letter',
            'category' => 'tender',
            'language' => 'en',
            'body' => "To,\n{{field:tender.departmentName}}\n\nSubject: Bank details for correspondence\n\nAccount Name: {{field:contractor.firm_name}}\nBank: {{field:contractor.bank.bank_name}}\nBranch & IFSC: {{field:contractor.bank.branch}} / {{field:contractor.bank.ifsc}}\nAccount Number: {{field:contractor.bank.account_no}}\n\nThis is for tender-related communications only (no bid values enclosed).\n\nAuthorized Signatory\n{{field:contractor.signatory.name}}\n{{field:contractor.firm_name}}",
            'placeholders' => ['{{field:tender.departmentName}}','{{field:contractor.firm_name}}','{{field:contractor.signatory.name}}','{{field:contractor.bank.bank_name}}','{{field:contractor.bank.branch}}','{{field:contractor.bank.ifsc}}','{{field:contractor.bank.account_no}}'],
            'isDefaultSeeded' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ],
        [
            'name' => 'EMD/SD Declaration',
            'category' => 'tender',
            'language' => 'en',
            'body' => "We acknowledge the EMD/SD requirements of the tender {{field:tender.title}} and undertake to furnish the required security in the prescribed manner if awarded.\n\nAuthorized Signatory\n{{field:contractor.signatory.name}}\n{{field:contractor.firm_name}}",
            'placeholders' => ['{{field:tender.title}}','{{field:contractor.signatory.name}}','{{field:contractor.firm_name}}'],
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
    $context = [
        '{{field:contractor.firm_name}}' => $contractor['firmName'] ?: ($contractor['name'] ?? ''),
        '{{field:contractor.address}}' => $address,
        '{{field:contractor.gst}}' => $contractor['gstNumber'] ?? '',
        '{{field:contractor.pan}}' => $contractor['panNumber'] ?? '',
        '{{field:contractor.signatory.name}}' => $contractor['authorizedSignatoryName'] ?? ($contractor['name'] ?? 'Authorized Signatory'),
        '{{field:contractor.signatory.designation}}' => $contractor['authorizedSignatoryDesignation'] ?? '',
        '{{field:contractor.contact.office_phone}}' => $contractor['officePhone'] ?? ($contractor['office_phone'] ?? ''),
        '{{field:contractor.contact.residence_phone}}' => $contractor['residencePhone'] ?? ($contractor['residence_phone'] ?? ''),
        '{{field:contractor.contact.mobile}}' => $contractor['mobile'] ?? '',
        '{{field:contractor.contact.fax}}' => $contractor['fax'] ?? '',
        '{{field:contractor.contact.email}}' => $contractor['email'] ?? '',
        '{{field:contractor.bank.bank_name}}' => $contractor['bankName'] ?? '',
        '{{field:contractor.bank.branch}}' => $contractor['bankBranch'] ?? ($contractor['bank_branch'] ?? ''),
        '{{field:contractor.bank.account_no}}' => $contractor['bankAccount'] ?? '',
        '{{field:contractor.bank.ifsc}}' => $contractor['ifsc'] ?? '',
        '{{field:contractor.company.core_expertise}}' => $contractor['coreExpertise'] ?? '',
        '{{field:contractor.company.year_established}}' => $contractor['yearEstablished'] ?? '',
        '{{field:contractor.company.key_licenses}}' => $contractor['keyLicenses'] ?? '',
        '{{field:table:experience_summary}}' => '',
        '{{field:table:manpower_list}}' => '',
        '{{field:table:equipment_list}}' => '',
        '{{field:contractor.turnover_details}}' => '',
        '{{field:contractor.net_worth_as_on}}' => '',
        '{{field:contractor.net_worth_amount}}' => '',
        '{{field:contractor.net_worth_in_words}}' => '',
        '{{field:tender.title}}' => $tenderTitle,
        '{{field:tender.number}}' => $tenderNumber,
        '{{field:tender.departmentName}}' => $departmentName,
        '{{field:tender.submission_deadline}}' => $extracted['submissionDeadline'] ?? ($tender['submission_deadline'] ?? ''),
        '{{field:contractor.place}}' => $placeDefault,
        '{{field:contractor.date}}' => now_kolkata()->format('d M Y'),
        '{{contractorName}}' => $contractorName,
        '{{firmName}}' => $contractor['firmName'] ?? ($contractor['name'] ?? ''),
        '{{firmType}}' => $contractor['firmType'] ?? '',
        '{{contactPerson}}' => $contractor['contactPerson'] ?? ($contractor['name'] ?? 'Authorized Signatory'),
        '{{contactMobile}}' => $contractor['mobile'] ?? '',
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

    $profileValues = array_merge(pack_profile_placeholder_values($contractor), pack_profile_memory_values($contractor['yojId'] ?? ''));
    $tenderValues = pack_tender_placeholder_values($tender);
    foreach (array_merge($profileValues, $tenderValues) as $key => $value) {
        $normalized = pack_normalize_placeholder_key((string)$key);
        if ($normalized === '') {
            continue;
        }
        if (str_starts_with($normalized, 'table.')) {
            $tableKey = substr($normalized, 6);
            $context['{{field:table:' . $tableKey . '}}'] = (string)$value;
        } else {
            $context['{{field:' . $normalized . '}}'] = (string)$value;
        }
    }

    return $context;
}

function contractor_fill_template_body(string $body, array $context): string
{
    $stats = [];
    $body = migrate_placeholders_to_canonical($body, $stats);
    return str_replace(array_keys($context), array_values($context), $body);
}
