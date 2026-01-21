<?php
declare(strict_types=1);

function placeholder_log_path(): string
{
    return DATA_PATH . '/logs/placeholder_migration.log';
}

function placeholder_legacy_key_map(): array
{
    return [
        'contractorname' => 'contractor.firm_name',
        'firmname' => 'contractor.firm_name',
        'firmtype' => 'contractor.firm_type',
        'contactperson' => 'contractor.signatory.name',
        'contactmobile' => 'contractor.contact.mobile',
        'address' => 'contractor.address',
        'placedefault' => 'contractor.place',
        'authorizedsignatory' => 'contractor.signatory.name',
        'signatorydesignation' => 'contractor.signatory.designation',
        'gstnumber' => 'contractor.gst',
        'pannumber' => 'contractor.pan',
        'bankname' => 'contractor.bank.bank_name',
        'bankaccount' => 'contractor.bank.account_no',
        'tenderid' => 'tender.number',
        'tendertitle' => 'tender.title',
        'tendernumber' => 'tender.number',
        'deptname' => 'tender.departmentName',
        'workorderid' => 'tender.workorder_id',
        'docdate' => 'tender.document_date',
        'doctitle' => 'tender.document_title',
        'username' => 'tender.user_name',
        'submissiondeadline' => 'tender.submission_deadline',
        'openingdate' => 'tender.submission_deadline',
        'todaydate' => 'contractor.date',
        'firm.name' => 'contractor.firm_name',
        'firm_name' => 'contractor.firm_name',
        'company_name' => 'contractor.firm_name',
        'dealer_name' => 'contractor.firm_name',
        'contractor_name' => 'contractor.firm_name',
        'contractor_firm_name' => 'contractor.firm_name',
        'firm.type' => 'contractor.firm_type',
        'firm_type' => 'contractor.firm_type',
        'contractor_firm_type' => 'contractor.firm_type',
        'firm.address' => 'contractor.address',
        'contractor_address' => 'contractor.address',
        'company_address' => 'contractor.address',
        'firm.city' => 'contractor.city',
        'firm.state' => 'contractor.state',
        'firm.pincode' => 'contractor.pincode',
        'tax.gst' => 'contractor.gst',
        'gst_no' => 'contractor.gst',
        'gstin' => 'contractor.gst',
        'contractor_gst' => 'contractor.gst',
        'tax.pan' => 'contractor.pan',
        'pan_no' => 'contractor.pan',
        'pan_number' => 'contractor.pan',
        'contractor_pan' => 'contractor.pan',
        'contact.office_phone' => 'contractor.contact.office_phone',
        'office_phone' => 'contractor.contact.office_phone',
        'contact.residence_phone' => 'contractor.contact.residence_phone',
        'residence_phone' => 'contractor.contact.residence_phone',
        'contact.mobile' => 'contractor.contact.mobile',
        'contractor_mobile' => 'contractor.contact.mobile',
        'mobile_no' => 'contractor.contact.mobile',
        'phone' => 'contractor.contact.mobile',
        'mobile' => 'contractor.contact.mobile',
        'contact.fax' => 'contractor.contact.fax',
        'fax' => 'contractor.contact.fax',
        'contact.email' => 'contractor.contact.email',
        'contractor_email' => 'contractor.contact.email',
        'email' => 'contractor.contact.email',
        'bank.bank_name' => 'contractor.bank.bank_name',
        'bank_name' => 'contractor.bank.bank_name',
        'bank.branch' => 'contractor.bank.branch',
        'bank_branch' => 'contractor.bank.branch',
        'bank.account_no' => 'contractor.bank.account_no',
        'bank_account' => 'contractor.bank.account_no',
        'account_no' => 'contractor.bank.account_no',
        'bank.ifsc' => 'contractor.bank.ifsc',
        'ifsc' => 'contractor.bank.ifsc',
        'bank.account_holder' => 'contractor.bank.account_holder',
        'account_holder' => 'contractor.bank.account_holder',
        'signatory.name' => 'contractor.signatory.name',
        'authorized_signatory' => 'contractor.signatory.name',
        'signatory.designation' => 'contractor.signatory.designation',
        'designation' => 'contractor.signatory.designation',
        'place' => 'contractor.place',
        'date' => 'contractor.date',
        'company.core_expertise' => 'contractor.company.core_expertise',
        'company.year_established' => 'contractor.company.year_established',
        'company.key_licenses' => 'contractor.company.key_licenses',
        'turnover_details' => 'contractor.turnover_details',
        'net_worth_as_on' => 'contractor.net_worth_as_on',
        'net_worth_amount' => 'contractor.net_worth_amount',
        'net_worth_in_words' => 'contractor.net_worth_in_words',
        'tender_title' => 'tender.title',
        'tender_number' => 'tender.number',
        'department_name' => 'tender.departmentName',
        'submission_deadline' => 'tender.submission_deadline',
        'emd_text' => 'tender.emd_text',
        'fee_text' => 'tender.fee_text',
        'sd_text' => 'tender.sd_text',
        'pg_text' => 'tender.pg_text',
        'officer_name' => 'tender.officer_name',
        'office_address' => 'tender.office_address',
        'warranty_years' => 'tender.warranty_years',
        'installation_timeline_days' => 'tender.installation_timeline_days',
        'local_content_percent' => 'tender.local_content_percent',
        'annexure_title' => 'tender.annexure_title',
        'annexure_code' => 'tender.annexure_code',
        'case.customer_name' => 'case.customer_name',
        'customer_name' => 'case.customer_name',
    ];
}

function placeholder_legacy_table_map(): array
{
    return [
        'experience_summary_table' => 'experience_summary',
        'manpower_list_table' => 'manpower_list',
        'equipment_list_table' => 'equipment_list',
        'tech_compliance' => 'tech_compliance',
    ];
}

function placeholder_normalize_key(string $raw): string
{
    $key = trim($raw);
    $key = preg_replace('/^{+\s*/', '', $key);
    $key = preg_replace('/\s*}+$/', '', $key);
    $key = strtolower(trim($key));
    $key = preg_replace('/\s+/', '', $key) ?? $key;
    return $key;
}

function placeholder_canonical_key(string $raw): string
{
    $key = placeholder_normalize_key($raw);
    if (str_starts_with($key, 'field:')) {
        $key = substr($key, 6);
    }
    $aliases = placeholder_legacy_key_map();
    return $aliases[$key] ?? $key;
}

function placeholder_canonical_table_key(string $raw): string
{
    $key = placeholder_normalize_key($raw);
    if (str_starts_with($key, 'table:')) {
        $key = substr($key, 6);
    }
    $aliases = placeholder_legacy_table_map();
    return $aliases[$key] ?? $key;
}

function placeholder_registry_system_fields(): array
{
    return [
        'contractor.firm_name' => ['label' => 'Firm name', 'group' => 'Contractor', 'max' => 160, 'type' => 'text'],
        'contractor.firm_type' => ['label' => 'Firm type', 'group' => 'Contractor', 'max' => 80, 'type' => 'text'],
        'contractor.address' => ['label' => 'Firm address', 'group' => 'Contractor', 'max' => 400, 'type' => 'textarea'],
        'contractor.city' => ['label' => 'City', 'group' => 'Contractor', 'max' => 120, 'type' => 'text'],
        'contractor.state' => ['label' => 'State', 'group' => 'Contractor', 'max' => 120, 'type' => 'text'],
        'contractor.pincode' => ['label' => 'Pincode', 'group' => 'Contractor', 'max' => 20, 'type' => 'text'],
        'contractor.gst' => ['label' => 'GST number', 'group' => 'Contractor', 'max' => 80, 'type' => 'text'],
        'contractor.pan' => ['label' => 'PAN number', 'group' => 'Contractor', 'max' => 80, 'type' => 'text'],
        'contractor.contact.office_phone' => ['label' => 'Office phone', 'group' => 'Contractor', 'max' => 30, 'type' => 'text'],
        'contractor.contact.residence_phone' => ['label' => 'Residence phone', 'group' => 'Contractor', 'max' => 30, 'type' => 'text'],
        'contractor.contact.mobile' => ['label' => 'Mobile', 'group' => 'Contractor', 'max' => 30, 'type' => 'text'],
        'contractor.contact.fax' => ['label' => 'Fax', 'group' => 'Contractor', 'max' => 30, 'type' => 'text'],
        'contractor.contact.email' => ['label' => 'Email', 'group' => 'Contractor', 'max' => 120, 'type' => 'text'],
        'contractor.bank.account_no' => ['label' => 'Bank account number', 'group' => 'Contractor', 'max' => 60, 'type' => 'text'],
        'contractor.bank.ifsc' => ['label' => 'IFSC', 'group' => 'Contractor', 'max' => 20, 'type' => 'text'],
        'contractor.bank.bank_name' => ['label' => 'Bank name', 'group' => 'Contractor', 'max' => 120, 'type' => 'text'],
        'contractor.bank.branch' => ['label' => 'Bank branch', 'group' => 'Contractor', 'max' => 120, 'type' => 'text'],
        'contractor.bank.account_holder' => ['label' => 'Account holder', 'group' => 'Contractor', 'max' => 160, 'type' => 'text'],
        'contractor.signatory.name' => ['label' => 'Authorized signatory', 'group' => 'Contractor', 'max' => 160, 'type' => 'text'],
        'contractor.signatory.designation' => ['label' => 'Signatory designation', 'group' => 'Contractor', 'max' => 120, 'type' => 'text'],
        'contractor.place' => ['label' => 'Place', 'group' => 'Contractor', 'max' => 120, 'type' => 'text'],
        'contractor.date' => ['label' => 'Date', 'group' => 'Contractor', 'max' => 40, 'type' => 'date'],
        'contractor.company.core_expertise' => ['label' => 'Core expertise', 'group' => 'Contractor', 'max' => 240, 'type' => 'textarea'],
        'contractor.company.year_established' => ['label' => 'Year of establishment', 'group' => 'Contractor', 'max' => 40, 'type' => 'text'],
        'contractor.company.key_licenses' => ['label' => 'Key licenses', 'group' => 'Contractor', 'max' => 240, 'type' => 'textarea'],
        'tender.title' => ['label' => 'Tender title', 'group' => 'Tender', 'max' => 200, 'type' => 'text', 'readOnly' => true],
        'tender.number' => ['label' => 'Tender number', 'group' => 'Tender', 'max' => 120, 'type' => 'text', 'readOnly' => true],
        'tender.departmentName' => ['label' => 'Department name', 'group' => 'Tender', 'max' => 200, 'type' => 'text', 'readOnly' => true],
        'tender.submission_deadline' => ['label' => 'Submission deadline', 'group' => 'Tender', 'max' => 120, 'type' => 'text', 'readOnly' => true],
        'tender.annexure_title' => ['label' => 'Annexure title', 'group' => 'Tender', 'max' => 200, 'type' => 'text', 'readOnly' => true],
        'tender.annexure_code' => ['label' => 'Annexure code', 'group' => 'Tender', 'max' => 80, 'type' => 'text', 'readOnly' => true],
        'tender.workorder_id' => ['label' => 'Workorder ID', 'group' => 'Tender', 'max' => 120, 'type' => 'text'],
        'tender.document_date' => ['label' => 'Document date', 'group' => 'Tender', 'max' => 120, 'type' => 'text'],
        'tender.document_title' => ['label' => 'Document title', 'group' => 'Tender', 'max' => 200, 'type' => 'text'],
        'tender.user_name' => ['label' => 'User name', 'group' => 'Tender', 'max' => 160, 'type' => 'text'],
        'tender.emd_text' => ['label' => 'EMD details', 'group' => 'Tender', 'max' => 240, 'type' => 'textarea'],
        'tender.fee_text' => ['label' => 'Tender fee details', 'group' => 'Tender', 'max' => 240, 'type' => 'textarea'],
        'tender.sd_text' => ['label' => 'Security deposit details', 'group' => 'Tender', 'max' => 240, 'type' => 'textarea'],
        'tender.pg_text' => ['label' => 'Performance guarantee details', 'group' => 'Tender', 'max' => 240, 'type' => 'textarea'],
        'tender.officer_name' => ['label' => 'Officer name', 'group' => 'Tender', 'max' => 160, 'type' => 'text', 'readOnly' => true],
        'tender.office_address' => ['label' => 'Office address', 'group' => 'Tender', 'max' => 400, 'type' => 'textarea', 'readOnly' => true],
        'tender.warranty_years' => ['label' => 'Warranty (years)', 'group' => 'Tender', 'max' => 40, 'type' => 'text'],
        'tender.installation_timeline_days' => ['label' => 'Installation timeline (days)', 'group' => 'Tender', 'max' => 40, 'type' => 'text'],
        'tender.local_content_percent' => ['label' => 'Local content (%)', 'group' => 'Tender', 'max' => 40, 'type' => 'text'],
    ];
}

function placeholder_registry_system_tables(): array
{
    return [
        'experience_summary' => ['label' => 'Experience summary table', 'group' => 'Tables'],
        'manpower_list' => ['label' => 'Manpower list', 'group' => 'Tables'],
        'equipment_list' => ['label' => 'Equipment list', 'group' => 'Tables'],
        'tech_compliance' => ['label' => 'Technical compliance table', 'group' => 'Tables'],
    ];
}

function placeholder_registry(array $options = []): array
{
    $fields = placeholder_registry_system_fields();
    $tables = placeholder_registry_system_tables();

    $memory = $options['memory'] ?? null;
    if (is_array($memory['fields'] ?? null)) {
        foreach ($memory['fields'] as $key => $entry) {
            $normalized = placeholder_canonical_key((string)$key);
            if ($normalized === '') {
                continue;
            }
            if (!str_starts_with($normalized, 'custom.')) {
                $normalized = 'custom.' . $normalized;
            }
            if (!isset($fields[$normalized])) {
                $fields[$normalized] = [
                    'label' => $entry['label'] ?? profile_memory_label_from_key($normalized),
                    'group' => 'Custom Saved Fields',
                    'max' => profile_memory_max_length((string)($entry['type'] ?? 'text')),
                    'type' => $entry['type'] ?? 'text',
                ];
            }
        }
    }

    $scheme = $options['scheme'] ?? null;
    $schemeFields = is_array($scheme['fieldDictionary'] ?? null) ? $scheme['fieldDictionary'] : [];
    foreach ($schemeFields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $rawKey = (string)($field['key'] ?? '');
        $normalized = placeholder_canonical_key($rawKey);
        if ($normalized === '') {
            continue;
        }
        if (!isset($fields[$normalized])) {
            $fields[$normalized] = [
                'label' => $field['label'] ?? $normalized,
                'group' => 'Case/Scheme',
                'max' => (int)($field['validation']['maxLen'] ?? 200),
                'type' => $field['type'] ?? 'text',
            ];
        }
    }

    $pack = $options['pack'] ?? null;
    if (is_array($pack['fieldRegistry'] ?? null)) {
        foreach ($pack['fieldRegistry'] as $key => $value) {
            $normalized = placeholder_canonical_key((string)$key);
            if ($normalized === '' || isset($fields[$normalized])) {
                continue;
            }
            $fields[$normalized] = [
                'label' => ucwords(str_replace(['.', '_'], ' ', $normalized)),
                'group' => 'Custom Saved Fields',
                'max' => 200,
                'type' => 'text',
            ];
        }
    }

    return [
        'fields' => $fields,
        'tables' => $tables,
    ];
}

function validate_placeholders(string $templateBody, array $contextRegistry): array
{
    $results = [
        'invalidTokens' => [],
        'unknownKeys' => [],
        'deprecatedTokens' => [],
    ];
    if ($templateBody === '') {
        return $results;
    }
    $tokens = [];
    preg_match_all('/{{\s*[^}]+\s*}}/', $templateBody, $tokens);
    foreach ($tokens[0] ?? [] as $token) {
        if (preg_match('/^{{\s*field:table:([a-z0-9_.-]+)\s*}}$/i', $token, $match)) {
            $rawKey = $match[1] ?? '';
            $canonical = placeholder_canonical_table_key($rawKey);
            $canonicalToken = '{{field:table:' . $canonical . '}}';
            if ($canonical !== strtolower($rawKey)) {
                $results['deprecatedTokens'][] = $token . ' → ' . $canonicalToken;
            }
            if (!isset($contextRegistry['tables'][$canonical])) {
                $results['unknownKeys'][] = $canonicalToken;
            }
            continue;
        }
        if (preg_match('/^{{\s*field:([a-z0-9_.-]+)\s*}}$/i', $token, $match)) {
            $rawKey = $match[1] ?? '';
            $canonical = placeholder_canonical_key($rawKey);
            $canonicalToken = '{{field:' . $canonical . '}}';
            if ($canonical !== strtolower($rawKey)) {
                $results['deprecatedTokens'][] = $token . ' → ' . $canonicalToken;
            }
            if (!isset($contextRegistry['fields'][$canonical])) {
                $results['unknownKeys'][] = $canonicalToken;
            }
            continue;
        }
        $results['invalidTokens'][] = $token;
    }
    $results['invalidTokens'] = array_values(array_unique($results['invalidTokens']));
    $results['unknownKeys'] = array_values(array_unique($results['unknownKeys']));
    $results['deprecatedTokens'] = array_values(array_unique($results['deprecatedTokens']));
    return $results;
}

function migrate_placeholders_to_canonical(string $body, array &$stats = []): string
{
    if ($body === '') {
        return $body;
    }
    $stats['migrated'] = $stats['migrated'] ?? 0;
    $log = static function (string $from, string $to) use (&$stats): void {
        $stats['migrated']++;
        logEvent(placeholder_log_path(), [
            'event' => 'placeholder_migrated',
            'from' => $from,
            'to' => $to,
        ]);
    };

    $body = preg_replace_callback('/{{\s*table:\s*([a-z0-9_.-]+)\s*}}/i', static function (array $match) use ($log): string {
        $rawKey = $match[1] ?? '';
        $canonical = placeholder_canonical_table_key($rawKey);
        $from = $match[0];
        $to = '{{field:table:' . $canonical . '}}';
        if ($from !== $to) {
            $log($from, $to);
        }
        return $to;
    }, $body) ?? $body;

    $body = preg_replace_callback('/{{\s*field:table:([a-z0-9_.-]+)\s*}}/i', static function (array $match) use ($log): string {
        $rawKey = $match[1] ?? '';
        $canonical = placeholder_canonical_table_key($rawKey);
        $from = $match[0];
        $to = '{{field:table:' . $canonical . '}}';
        if ($from !== $to) {
            $log($from, $to);
        }
        return $to;
    }, $body) ?? $body;

    $body = preg_replace_callback('/{{\s*field:([a-z0-9_.-]+)\s*}}/i', static function (array $match) use ($log): string {
        $rawKey = $match[1] ?? '';
        $canonical = placeholder_canonical_key($rawKey);
        $from = $match[0];
        $to = '{{field:' . $canonical . '}}';
        if ($from !== $to) {
            $log($from, $to);
        }
        return $to;
    }, $body) ?? $body;

    $body = preg_replace_callback('/{{\s*([a-z0-9_.-]+)\s*}}/i', static function (array $match) use ($log): string {
        $raw = $match[1] ?? '';
        $tableKey = placeholder_canonical_table_key($raw);
        $legacyTables = placeholder_legacy_table_map();
        if (isset($legacyTables[$raw]) || isset($legacyTables[$tableKey])) {
            $from = $match[0];
            $to = '{{field:table:' . $tableKey . '}}';
            if ($from !== $to) {
                $log($from, $to);
            }
            return $to;
        }
        $canonical = placeholder_canonical_key($raw);
        $from = $match[0];
        $to = '{{field:' . $canonical . '}}';
        if ($from !== $to) {
            $log($from, $to);
        }
        return $to;
    }, $body) ?? $body;

    return $body;
}
