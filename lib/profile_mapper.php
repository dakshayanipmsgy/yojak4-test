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
        'firm.name' => ['firmName', 'companyName', 'name', 'firm.name'],
        'firm.type' => ['firmType', 'companyType', 'firm.type'],
        'firm.address' => ['address', 'addressLine1', 'firm.address'],
        'firm.city' => ['city', 'district', 'firm.city'],
        'firm.state' => ['state', 'firm.state'],
        'firm.pincode' => ['pincode', 'pin', 'zip', 'firm.pincode'],
        'tax.gst' => ['gst', 'gstNumber', 'tax.gst'],
        'tax.pan' => ['pan', 'panNumber', 'tax.pan'],
        'contact.mobile' => ['mobile', 'contact.mobile', 'phone'],
        'contact.email' => ['email', 'contact.email'],
        'contact.office_phone' => ['officePhone', 'office_phone', 'contact.office_phone'],
        'contact.residence_phone' => ['residencePhone', 'residence_phone', 'contact.residence_phone'],
        'contact.fax' => ['fax', 'contact.fax'],
        'bank.account_no' => ['bankAccount', 'accountNo', 'account_number', 'bank.account_no'],
        'bank.ifsc' => ['ifsc', 'bank.ifsc'],
        'bank.bank_name' => ['bankName', 'bank.bank_name'],
        'bank.branch' => ['bankBranch', 'bank_branch', 'bank.branch'],
        'bank.account_holder' => ['accountHolder', 'bank.account_holder', 'account_holder'],
        'signatory.name' => ['authorizedSignatoryName', 'authorized_signatory', 'signatory.name'],
        'signatory.designation' => ['authorizedSignatoryDesignation', 'signatory.designation', 'designation'],
        'place' => ['placeDefault', 'place'],
    ];
}

function get_profile_field_value(array $contractorJson, string $fieldKey): string
{
    $contractor = normalize_contractor_profile($contractorJson);
    $aliases = profile_field_aliases();
    $fieldKey = pack_normalize_placeholder_key($fieldKey);
    $paths = $aliases[$fieldKey] ?? [$fieldKey];

    if ($fieldKey === 'firm.address') {
        $address = contractor_profile_address($contractor);
        if ($address !== '') {
            return $address;
        }
    }

    foreach ($paths as $path) {
        $value = profile_value_from_path($contractor, $path);
        if ($value !== '') {
            return $value;
        }
    }

    if ($fieldKey === 'place') {
        $place = trim((string)($contractor['placeDefault'] ?? ''));
        if ($place === '') {
            $place = trim((string)($contractor['district'] ?? ''));
        }
        return $place;
    }

    return '';
}
