<?php
declare(strict_types=1);

function template_library_global_dir(): string
{
    return DATA_PATH . '/library/templates/global';
}

function pack_library_global_dir(): string
{
    return DATA_PATH . '/library/packs/global';
}

function template_library_contractor_dir(string $yojId): string
{
    return contractors_approved_path($yojId) . '/templates';
}

function pack_library_contractor_dir(string $yojId): string
{
    return contractors_approved_path($yojId) . '/packs_library';
}

function template_request_dir(string $yojId): string
{
    return DATA_PATH . '/template_requests/' . $yojId;
}

function template_request_upload_dir(string $yojId, string $requestId): string
{
    return template_request_dir($yojId) . '/uploads/' . $requestId;
}

function ensure_contractor_library_env(string $yojId): void
{
    $dirs = [
        template_library_global_dir(),
        pack_library_global_dir(),
        template_library_contractor_dir($yojId),
        pack_library_contractor_dir($yojId),
        template_request_dir($yojId),
        template_request_dir($yojId) . '/uploads',
        DATA_PATH . '/logs',
    ];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $logs = [
        DATA_PATH . '/logs/contractor_templates.log',
        DATA_PATH . '/logs/contractor_packs.log',
        DATA_PATH . '/logs/template_requests.log',
    ];

    foreach ($logs as $log) {
        if (!file_exists($log)) {
            touch($log);
        }
    }
}

function template_library_generate_id(): string
{
    $date = now_kolkata()->format('Ymd');
    return 'TPL-' . $date . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function pack_library_generate_id(): string
{
    $date = now_kolkata()->format('Ymd');
    return 'PACKLIB-' . $date . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function template_request_generate_id(): string
{
    $date = now_kolkata()->format('Ymd');
    return 'REQTPL-' . $date . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function template_library_is_valid_record(array $data): bool
{
    return isset($data['id'], $data['title'], $data['body']) && is_string($data['id']);
}

function pack_library_is_valid_record(array $data): bool
{
    return isset($data['id'], $data['title']) && is_string($data['id']);
}

function template_library_list_from_dir(string $dir, string $scope): array
{
    if (!is_dir($dir)) {
        return [];
    }

    $records = [];
    foreach (glob($dir . '/*.json') ?: [] as $path) {
        $data = readJson($path);
        if (!is_array($data) || !template_library_is_valid_record($data)) {
            continue;
        }
        if (($data['scope'] ?? '') !== $scope) {
            continue;
        }
        $records[] = $data;
    }

    usort($records, function (array $a, array $b): int {
        return strcmp((string)($b['updatedAt'] ?? ''), (string)($a['updatedAt'] ?? ''));
    });

    return $records;
}

function pack_library_list_from_dir(string $dir, string $scope): array
{
    if (!is_dir($dir)) {
        return [];
    }

    $records = [];
    foreach (glob($dir . '/*.json') ?: [] as $path) {
        $data = readJson($path);
        if (!is_array($data) || !pack_library_is_valid_record($data)) {
            continue;
        }
        if (($data['scope'] ?? '') !== $scope) {
            continue;
        }
        $records[] = $data;
    }

    usort($records, function (array $a, array $b): int {
        return strcmp((string)($b['updatedAt'] ?? ''), (string)($a['updatedAt'] ?? ''));
    });

    return $records;
}

function template_library_load_global(): array
{
    return template_library_list_from_dir(template_library_global_dir(), 'global');
}

function template_library_load_contractor(string $yojId): array
{
    return template_library_list_from_dir(template_library_contractor_dir($yojId), 'contractor');
}

function pack_library_load_global(): array
{
    return pack_library_list_from_dir(pack_library_global_dir(), 'global');
}

function pack_library_load_contractor(string $yojId): array
{
    return pack_library_list_from_dir(pack_library_contractor_dir($yojId), 'contractor');
}

function template_library_load_by_id(string $yojId, string $id, string $scope): ?array
{
    $dir = $scope === 'global' ? template_library_global_dir() : template_library_contractor_dir($yojId);
    $path = $dir . '/' . $id . '.json';
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    if (!is_array($data) || !template_library_is_valid_record($data)) {
        return null;
    }
    if (($data['scope'] ?? '') !== $scope) {
        return null;
    }
    return $data;
}

function pack_library_load_by_id(string $yojId, string $id, string $scope): ?array
{
    $dir = $scope === 'global' ? pack_library_global_dir() : pack_library_contractor_dir($yojId);
    $path = $dir . '/' . $id . '.json';
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    if (!is_array($data) || !pack_library_is_valid_record($data)) {
        return null;
    }
    if (($data['scope'] ?? '') !== $scope) {
        return null;
    }
    return $data;
}

function template_library_save_contractor(string $yojId, array $template): void
{
    ensure_contractor_library_env($yojId);
    $now = now_kolkata()->format(DateTime::ATOM);
    $template['scope'] = 'contractor';
    $template['ownerYojId'] = $yojId;
    $template['createdAt'] = $template['createdAt'] ?? $now;
    $template['updatedAt'] = $now;
    $path = template_library_contractor_dir($yojId) . '/' . $template['id'] . '.json';
    writeJsonAtomic($path, $template);
}

function pack_library_save_contractor(string $yojId, array $pack): void
{
    ensure_contractor_library_env($yojId);
    $now = now_kolkata()->format(DateTime::ATOM);
    $pack['scope'] = 'contractor';
    $pack['ownerYojId'] = $yojId;
    $pack['createdAt'] = $pack['createdAt'] ?? $now;
    $pack['updatedAt'] = $now;
    $path = pack_library_contractor_dir($yojId) . '/' . $pack['id'] . '.json';
    writeJsonAtomic($path, $pack);
}

function template_request_list(string $yojId): array
{
    $dir = template_request_dir($yojId);
    if (!is_dir($dir)) {
        return [];
    }
    $requests = [];
    foreach (glob($dir . '/*.json') ?: [] as $path) {
        $data = readJson($path);
        if (!is_array($data) || empty($data['requestId'])) {
            continue;
        }
        $requests[] = $data;
    }
    usort($requests, function (array $a, array $b): int {
        return strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? ''));
    });
    return $requests;
}

function template_request_save(string $yojId, array $request): void
{
    ensure_contractor_library_env($yojId);
    $path = template_request_dir($yojId) . '/' . $request['requestId'] . '.json';
    writeJsonAtomic($path, $request);
}

function template_library_field_groups(array $contractor, array $memory): array
{
    $contractor = normalize_contractor_profile($contractor);
    $address = contractor_profile_address($contractor);

    $profileFields = [
        ['key' => 'firm.name', 'label' => 'Firm Name', 'sample' => $contractor['firmName'] ?? 'Firm Name'],
        ['key' => 'firm.type', 'label' => 'Firm Type', 'sample' => $contractor['firmType'] ?? 'Firm Type'],
        ['key' => 'firm.address', 'label' => 'Firm Address', 'sample' => $address !== '' ? $address : 'Firm Address'],
        ['key' => 'tax.pan', 'label' => 'PAN', 'sample' => $contractor['panNumber'] ?? 'PAN'],
        ['key' => 'tax.gst', 'label' => 'GST', 'sample' => $contractor['gstNumber'] ?? 'GST'],
        ['key' => 'contact.name', 'label' => 'Authorized Signatory', 'sample' => $contractor['authorizedSignatoryName'] ?? 'Authorized Signatory'],
        ['key' => 'contact.designation', 'label' => 'Designation', 'sample' => $contractor['authorizedSignatoryDesignation'] ?? 'Designation'],
        ['key' => 'contact.mobile', 'label' => 'Mobile Number', 'sample' => $contractor['mobile'] ?? 'Mobile'],
        ['key' => 'contact.email', 'label' => 'Email', 'sample' => $contractor['email'] ?? 'Email'],
        ['key' => 'bank.name', 'label' => 'Bank Name', 'sample' => $contractor['bankName'] ?? 'Bank Name'],
        ['key' => 'bank.branch', 'label' => 'Bank Branch', 'sample' => $contractor['bankBranch'] ?? 'Branch'],
        ['key' => 'bank.account', 'label' => 'Account Number', 'sample' => $contractor['bankAccount'] ?? 'Account Number'],
        ['key' => 'bank.ifsc', 'label' => 'IFSC', 'sample' => $contractor['ifsc'] ?? 'IFSC'],
    ];

    $tenderFields = [
        ['key' => 'tender.number', 'label' => 'Tender Number', 'sample' => 'TN-12345'],
        ['key' => 'tender.title', 'label' => 'Tender Title', 'sample' => 'Tender Title'],
        ['key' => 'tender.open_date', 'label' => 'Tender Open Date', 'sample' => now_kolkata()->format('d M Y')],
        ['key' => 'tender.due_date', 'label' => 'Tender Due Date', 'sample' => now_kolkata()->modify('+7 days')->format('d M Y')],
        ['key' => 'tender.department', 'label' => 'Department Name', 'sample' => 'Department Name'],
    ];

    $memoryFields = [];
    foreach (($memory['fields'] ?? []) as $key => $entry) {
        $label = (string)($entry['label'] ?? $key);
        $memoryFields[] = [
            'key' => $key,
            'label' => $label,
            'sample' => (string)($entry['value'] ?? ''),
            'type' => (string)($entry['type'] ?? 'text'),
        ];
    }

    return [
        [
            'label' => 'Profile Fields',
            'fields' => $profileFields,
        ],
        [
            'label' => 'Tender Fields',
            'fields' => $tenderFields,
        ],
        [
            'label' => 'Smart Profile Memory',
            'fields' => $memoryFields,
        ],
    ];
}

function pack_library_normalize_items(array $rawItems): array
{
    $items = [];
    foreach ($rawItems as $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $type = (string)($raw['type'] ?? '');
        $required = !empty($raw['required']);
        if ($type === 'checklist_item') {
            $title = trim((string)($raw['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $items[] = [
                'type' => 'checklist_item',
                'title' => $title,
                'required' => $required,
            ];
        } elseif ($type === 'vault_doc_tag') {
            $tag = trim((string)($raw['tag'] ?? ''));
            if ($tag === '') {
                continue;
            }
            $items[] = [
                'type' => 'vault_doc_tag',
                'tag' => $tag,
                'required' => $required,
            ];
        } elseif ($type === 'template_ref') {
            $templateId = trim((string)($raw['templateId'] ?? ''));
            if ($templateId === '') {
                continue;
            }
            $items[] = [
                'type' => 'template_ref',
                'templateId' => $templateId,
                'required' => $required,
            ];
        } elseif ($type === 'upload_slot') {
            $title = trim((string)($raw['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $items[] = [
                'type' => 'upload_slot',
                'title' => $title,
                'required' => $required,
            ];
        }
    }
    return $items;
}
