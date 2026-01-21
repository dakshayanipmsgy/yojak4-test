<?php
declare(strict_types=1);

function assisted_v2_field_key_standard_path(): string
{
    return DATA_PATH . '/assisted_v2/field_key_standard.json';
}

function assisted_v2_field_key_standard_seed(): array
{
    return [
        'canonical' => [
            ['key' => 'contractor.firm_name', 'label' => 'Firm/Company/Dealer Name', 'type' => 'text'],
            ['key' => 'contractor.firm_type', 'label' => 'Firm Type', 'type' => 'text'],
            ['key' => 'contractor.address', 'label' => 'Registered Address', 'type' => 'textarea'],
            ['key' => 'contractor.city', 'label' => 'City', 'type' => 'text'],
            ['key' => 'contractor.state', 'label' => 'State', 'type' => 'text'],
            ['key' => 'contractor.pincode', 'label' => 'Pincode', 'type' => 'text'],
            ['key' => 'contractor.pan', 'label' => 'PAN', 'type' => 'text'],
            ['key' => 'contractor.gst', 'label' => 'GST', 'type' => 'text'],
            ['key' => 'contractor.contact.office_phone', 'label' => 'Office Phone', 'type' => 'phone'],
            ['key' => 'contractor.contact.residence_phone', 'label' => 'Residence Phone', 'type' => 'phone'],
            ['key' => 'contractor.contact.mobile', 'label' => 'Mobile', 'type' => 'phone'],
            ['key' => 'contractor.contact.email', 'label' => 'Email', 'type' => 'email'],
            ['key' => 'contractor.contact.fax', 'label' => 'Fax', 'type' => 'text'],
            ['key' => 'contractor.bank.bank_name', 'label' => 'Bank Name', 'type' => 'text'],
            ['key' => 'contractor.bank.branch', 'label' => 'Branch', 'type' => 'text'],
            ['key' => 'contractor.bank.account_no', 'label' => 'Account No', 'type' => 'text'],
            ['key' => 'contractor.bank.ifsc', 'label' => 'IFSC', 'type' => 'ifsc'],
            ['key' => 'contractor.bank.account_holder', 'label' => 'Account Holder', 'type' => 'text'],
            ['key' => 'contractor.signatory.name', 'label' => 'Authorized Signatory', 'type' => 'text'],
            ['key' => 'contractor.signatory.designation', 'label' => 'Designation', 'type' => 'text'],
            ['key' => 'contractor.place', 'label' => 'Place', 'type' => 'text'],
            ['key' => 'contractor.date', 'label' => 'Date', 'type' => 'date'],
        ],
        'aliases' => [
            'contractor.firm_name' => [
                'company name',
                'dealer name',
                'name of dealer',
                'name of company',
                'name of the dealer',
                'name of the party',
                'bidder name',
                'vendor name',
                'contractor name',
                'firm name',
                'agency name',
                'registered firm name',
            ],
            'contractor.address' => [
                'registered address',
                'address',
                'office address',
                'firm address',
                'company address',
                'registered office address',
            ],
            'contractor.pan' => [
                'pan',
                'pan no',
                'pan number',
                'pan no of the party',
                'party pan',
                'pan/tan',
                'pan card number',
            ],
            'contractor.gst' => [
                'gst',
                'gst no',
                'gst number',
                'gstin',
                'gst registration',
                'gst registration number',
            ],
            'contractor.bank.bank_name' => ['bank name', 'name of bank'],
            'contractor.bank.branch' => ['branch', 'bank branch', 'branch name'],
            'contractor.bank.account_no' => [
                'account no',
                'account number',
                'current account no',
                'a/c no',
                'current account',
                'bank account',
            ],
            'contractor.bank.ifsc' => ['ifsc', 'ifsc code'],
            'contractor.bank.account_holder' => ['account holder', 'account holder name', 'account name'],
            'contractor.contact.office_phone' => ['office phone', 'telephone office', 'tele no office', 'phone office'],
            'contractor.contact.residence_phone' => ['residence phone', 'telephone residence', 'tele no residence', 'phone residence'],
            'contractor.contact.mobile' => ['mobile', 'mobile no', 'phone', 'contact no', 'phone/fax/mobile'],
            'contractor.contact.email' => ['email', 'e-mail'],
            'contractor.contact.fax' => ['fax', 'fax no', 'fax number'],
            'contractor.signatory.name' => [
                'authorized signatory',
                'signature of authorized person',
                'name of authorized person',
                'signing authority',
            ],
            'contractor.signatory.designation' => ['designation', 'capacity', 'title'],
            'contractor.place' => ['place', 'place of signing'],
            'contractor.date' => ['date', 'date of signing'],
        ],
    ];
}

function assisted_v2_field_key_standard(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $data = readJson(assisted_v2_field_key_standard_path());
    if (!is_array($data) || empty($data['canonical'])) {
        $data = assisted_v2_field_key_standard_seed();
    }
    $data['canonical'] = is_array($data['canonical'] ?? null) ? $data['canonical'] : [];
    $data['aliases'] = is_array($data['aliases'] ?? null) ? $data['aliases'] : [];
    $cache = $data;
    return $cache;
}

function assisted_v2_standard_label_for_key(string $key): string
{
    $key = pack_normalize_placeholder_key($key);
    $standard = assisted_v2_field_key_standard();
    foreach ($standard['canonical'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $entryKey = pack_normalize_placeholder_key((string)($entry['key'] ?? ''));
        if ($entryKey === $key) {
            return (string)($entry['label'] ?? '');
        }
    }
    return '';
}

function assisted_v2_canonical_key_set(): array
{
    static $set = null;
    if ($set !== null) {
        return $set;
    }
    $standard = assisted_v2_field_key_standard();
    $set = [];
    foreach ($standard['canonical'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $key = pack_normalize_placeholder_key((string)($entry['key'] ?? ''));
        if ($key !== '') {
            $set[$key] = true;
        }
    }
    return $set;
}

function assisted_v2_normalize_alias_token(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? '';
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return trim($value);
}

function assisted_v2_alias_lookup(): array
{
    static $lookup = null;
    if ($lookup !== null) {
        return $lookup;
    }
    $standard = assisted_v2_field_key_standard();
    $lookup = [];
    foreach ($standard['canonical'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $key = pack_normalize_placeholder_key((string)($entry['key'] ?? ''));
        $label = assisted_v2_normalize_alias_token((string)($entry['label'] ?? ''));
        if ($key !== '') {
            $lookup[assisted_v2_normalize_alias_token($key)] = $key;
        }
        if ($label !== '') {
            $lookup[$label] = $key;
        }
    }
    foreach ($standard['aliases'] as $canonical => $aliases) {
        $canonical = pack_normalize_placeholder_key((string)$canonical);
        if ($canonical === '') {
            continue;
        }
        foreach ((array)$aliases as $alias) {
            $token = assisted_v2_normalize_alias_token((string)$alias);
            if ($token !== '') {
                $lookup[$token] = $canonical;
            }
        }
    }
    return $lookup;
}

function assisted_v2_match_canonical_key(string $value): string
{
    $token = assisted_v2_normalize_alias_token($value);
    if ($token === '') {
        return '';
    }
    $lookup = assisted_v2_alias_lookup();
    return $lookup[$token] ?? '';
}

function assisted_v2_make_custom_key(string $value): string
{
    $slug = assisted_v2_normalize_alias_token($value);
    $slug = str_replace(' ', '_', $slug);
    if ($slug === '') {
        $slug = 'field';
    }
    return 'custom.' . $slug;
}

function assisted_v2_normalize_catalog_key(string $rawKey, string $label, array &$warnings, array &$keyMap): string
{
    $normalizedKey = pack_normalize_placeholder_key($rawKey);
    $canonicalSet = assisted_v2_canonical_key_set();
    $byKey = '';
    if ($normalizedKey !== '' && isset($canonicalSet[$normalizedKey])) {
        $byKey = $normalizedKey;
    } else {
        $byKey = assisted_v2_match_canonical_key(str_replace(['.', '_', '-'], ' ', $normalizedKey));
    }
    $byLabel = $label !== '' ? assisted_v2_match_canonical_key($label) : '';
    $canonical = '';
    if ($byLabel !== '' && ($byKey === '' || $byKey !== $byLabel)) {
        $canonical = $byLabel;
        if ($byKey !== '' && $byKey !== $byLabel) {
            $warnings[] = 'Field key "' . $rawKey . '" normalized to "' . $canonical . '" using label "' . $label . '".';
        }
    } elseif ($byKey !== '') {
        $canonical = $byKey;
    }
    if ($canonical === '') {
        $canonical = assisted_v2_make_custom_key($label !== '' ? $label : $normalizedKey);
    }
    $keyMap[$rawKey] = $canonical;
    if ($normalizedKey !== '' && !isset($keyMap[$normalizedKey])) {
        $keyMap[$normalizedKey] = $canonical;
    }
    return $canonical;
}

function assisted_v2_normalize_reference_key(string $rawKey, array $keyMap): string
{
    $normalized = pack_normalize_placeholder_key($rawKey);
    if (isset($keyMap[$rawKey])) {
        return $keyMap[$rawKey];
    }
    if ($normalized !== '' && isset($keyMap[$normalized])) {
        return $keyMap[$normalized];
    }
    $canonical = assisted_v2_match_canonical_key(str_replace(['.', '_', '-'], ' ', $normalized));
    if ($canonical !== '') {
        return $canonical;
    }
    return $normalized !== '' ? $normalized : assisted_v2_make_custom_key($rawKey);
}

function assisted_v2_normalize_field_placeholders(string $body, array $keyMap, array &$stats): string
{
    if (trim($body) === '') {
        return $body;
    }
    $body = preg_replace_callback('/{{\s*(field:)?\s*([a-z0-9_.-]+)\s*}}/i', static function (array $match) use ($keyMap, &$stats): string {
        $prefix = $match[1] ?? '';
        $rawKey = $match[2] ?? '';
        if (stripos($rawKey, 'table:') === 0) {
            return $match[0];
        }
        $normalized = assisted_v2_normalize_reference_key($rawKey, $keyMap);
        if ($normalized !== pack_normalize_placeholder_key($rawKey)) {
            $stats['placeholdersFixed'] = ($stats['placeholdersFixed'] ?? 0) + 1;
        }
        $prefix = $prefix !== '' ? 'field:' : '';
        return '{{' . $prefix . $normalized . '}}';
    }, $body) ?? $body;
    return $body;
}
