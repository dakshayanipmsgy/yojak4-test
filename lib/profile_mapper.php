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
        'firm.name' => [
            'firmName',
            'companyName',
            'dealerName',
            'contractorName',
            'name',
            'profile.company',
            'profile.companyName',
            'firm.name',
        ],
        'firm.type' => ['firmType', 'companyType', 'firm.type'],
        'firm.address' => [
            'address',
            'addressLine1',
            'registeredAddress',
            'officeAddress',
            'profile.address',
            'firm.address',
        ],
        'firm.city' => ['city', 'district', 'firm.city'],
        'firm.state' => ['state', 'firm.state'],
        'firm.pincode' => ['pincode', 'pin', 'zip', 'firm.pincode'],
        'tax.gst' => ['gst', 'gstin', 'gstNumber', 'tax.gst'],
        'tax.pan' => ['pan', 'panNo', 'panNumber', 'tax.pan'],
        'contact.mobile' => ['mobile', 'phone', 'contact.mobile', 'contact.phone'],
        'contact.email' => ['email', 'contact.email'],
        'contact.office_phone' => ['officePhone', 'office_phone', 'contact.office_phone', 'contact.officePhone'],
        'contact.residence_phone' => ['residencePhone', 'residence_phone', 'contact.residence_phone', 'contact.residencePhone'],
        'contact.fax' => ['fax', 'contact.fax'],
        'bank.account_no' => ['bankAccount', 'accountNo', 'accountNumber', 'account_number', 'bank.account_no'],
        'bank.ifsc' => ['ifsc', 'ifscCode', 'bank.ifsc'],
        'bank.bank_name' => ['bankName', 'bank.bank_name'],
        'bank.branch' => ['bankBranch', 'bank_branch', 'bank.branch'],
        'bank.account_holder' => ['accountHolder', 'bank.account_holder', 'account_holder'],
        'signatory.name' => ['authorizedSignatoryName', 'authorized_signatory', 'signatory.name'],
        'signatory.designation' => ['authorizedSignatoryDesignation', 'signatory.designation', 'designation'],
        'place' => ['placeDefault', 'place'],
    ];
}

function get_profile_value_by_canonical_key(array $contractorJson, string $canonicalKey, array &$diagnostics = []): string
{
    $contractor = normalize_contractor_profile($contractorJson);
    $aliases = profile_field_aliases();
    $canonicalKey = pack_normalize_placeholder_key($canonicalKey);
    $paths = $aliases[$canonicalKey] ?? [$canonicalKey];

    if ($canonicalKey === 'firm.address') {
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

    if ($canonicalKey === 'place') {
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
