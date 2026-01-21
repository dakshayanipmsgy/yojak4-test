<?php
declare(strict_types=1);

function profile_value_from_path(array $data, string $path): string
{
    $segments = array_filter(explode('.', $path), static fn($part) => $part !== '');
    $value = $data;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return '';
        }
        $value = $value[$segment];
    }
    return trim((string)$value);
}

function profile_field_aliases(): array
{
    return [
        'contractor.firm_name' => [
            'firmName',
            'companyName',
            'dealerName',
            'contractorName',
            'name',
            'profile.company',
            'profile.companyName',
            'firm.name',
        ],
        'contractor.firm_type' => ['firmType', 'companyType', 'firm.type'],
        'contractor.address' => [
            'address',
            'addressLine1',
            'registeredAddress',
            'officeAddress',
            'profile.address',
            'firm.address',
        ],
        'contractor.city' => ['city', 'district', 'firm.city'],
        'contractor.state' => ['state', 'firm.state'],
        'contractor.pincode' => ['pincode', 'pin', 'zip', 'firm.pincode'],
        'contractor.gst' => ['gst', 'gstin', 'gstNumber', 'tax.gst'],
        'contractor.pan' => ['pan', 'panNo', 'panNumber', 'tax.pan'],
        'contractor.contact.mobile' => ['mobile', 'phone', 'contact.mobile', 'contact.phone'],
        'contractor.contact.email' => ['email', 'contact.email'],
        'contractor.contact.office_phone' => ['officePhone', 'office_phone', 'contact.office_phone', 'contact.officePhone'],
        'contractor.contact.residence_phone' => ['residencePhone', 'residence_phone', 'contact.residence_phone', 'contact.residencePhone'],
        'contractor.contact.fax' => ['fax', 'contact.fax'],
        'contractor.bank.account_no' => ['bankAccount', 'accountNo', 'accountNumber', 'account_number', 'bank.account_no'],
        'contractor.bank.ifsc' => ['ifsc', 'ifscCode', 'bank.ifsc'],
        'contractor.bank.bank_name' => ['bankName', 'bank.bank_name'],
        'contractor.bank.branch' => ['bankBranch', 'bank_branch', 'bank.branch'],
        'contractor.bank.account_holder' => ['accountHolder', 'bank.account_holder', 'account_holder'],
        'contractor.signatory.name' => ['authorizedSignatoryName', 'authorized_signatory', 'signatory.name'],
        'contractor.signatory.designation' => ['authorizedSignatoryDesignation', 'signatory.designation', 'designation'],
        'contractor.place' => ['placeDefault', 'place'],
    ];
}

function get_profile_value_by_canonical_key(array $contractorJson, string $canonicalKey, array &$diagnostics = []): string
{
    $contractor = normalize_contractor_profile($contractorJson);
    $aliases = profile_field_aliases();
    $canonicalKey = pack_normalize_placeholder_key($canonicalKey);
    $paths = $aliases[$canonicalKey] ?? [$canonicalKey];

    if ($canonicalKey === 'contractor.address') {
        $address = contractor_profile_address($contractor);
        if ($address !== '') {
            $diagnostics[] = $canonicalKey . ' ← contractor_profile_address()';
            return $address;
        }
    }

    foreach ($paths as $path) {
        $value = profile_value_from_path($contractor, $path);
        if ($value !== '') {
            $diagnostics[] = $canonicalKey . ' ← contractorJson.' . $path;
            return $value;
        }
    }

    if ($canonicalKey === 'contractor.place') {
        $place = trim((string)($contractor['placeDefault'] ?? ''));
        $source = 'placeDefault';
        if ($place === '') {
            $place = trim((string)($contractor['district'] ?? ''));
            $source = 'district';
        }
        if ($place !== '') {
            $diagnostics[] = $canonicalKey . ' ← contractorJson.' . $source;
        }
        return $place;
    }

    return '';
}

function get_profile_field_value(array $contractorJson, string $fieldKey): string
{
    $diagnostics = [];
    return get_profile_value_by_canonical_key($contractorJson, $fieldKey, $diagnostics);
}

function profile_mapping_diagnostics(array $contractorJson, array $keys): array
{
    $diagnostics = [];
    foreach ($keys as $key) {
        $local = [];
        get_profile_value_by_canonical_key($contractorJson, (string)$key, $local);
        foreach ($local as $entry) {
            $diagnostics[] = $entry;
        }
    }
    return array_values(array_unique($diagnostics));
}
