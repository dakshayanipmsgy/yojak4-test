<?php
declare(strict_types=1);

function schemes_root(): string
{
    return DATA_PATH . '/schemes';
}

function scheme_base_path(string $schemeCode): string
{
    return schemes_root() . '/' . strtoupper($schemeCode);
}

function scheme_meta_path(string $schemeCode): string
{
    return scheme_base_path($schemeCode) . '/scheme_meta.json';
}

function scheme_draft_path(string $schemeCode): string
{
    return scheme_base_path($schemeCode) . '/draft.json';
}

function scheme_versions_path(string $schemeCode): string
{
    return scheme_base_path($schemeCode) . '/versions';
}

function scheme_audit_path(string $schemeCode): string
{
    return scheme_base_path($schemeCode) . '/audit.jsonl';
}

function contractor_scheme_activation_dir(string $yojId): string
{
    return DATA_PATH . '/contractor_scheme_activations/' . $yojId;
}

function contractor_scheme_enabled_path(string $yojId): string
{
    return contractor_scheme_activation_dir($yojId) . '/enabled.json';
}

function contractor_scheme_requests_dir(string $yojId): string
{
    return contractor_scheme_activation_dir($yojId) . '/requests';
}

function scheme_cases_root(string $schemeCode, string $yojId): string
{
    return DATA_PATH . '/scheme_cases/' . strtoupper($schemeCode) . '/' . $yojId;
}

function scheme_case_dir(string $schemeCode, string $yojId, string $caseId): string
{
    return scheme_cases_root($schemeCode, $yojId) . '/cases/' . $caseId;
}

function scheme_case_fields_path(string $schemeCode, string $yojId, string $caseId): string
{
    return scheme_case_dir($schemeCode, $yojId, $caseId) . '/fields.json';
}

function scheme_case_core_path(string $schemeCode, string $yojId, string $caseId): string
{
    return scheme_case_dir($schemeCode, $yojId, $caseId) . '/case.json';
}

function scheme_case_pack_runtime_path(string $schemeCode, string $yojId, string $caseId, string $packId): string
{
    return scheme_case_dir($schemeCode, $yojId, $caseId) . '/packs/' . $packId . '/pack_runtime.json';
}

function scheme_case_documents_dir(string $schemeCode, string $yojId, string $caseId): string
{
    return scheme_case_dir($schemeCode, $yojId, $caseId) . '/documents';
}

function list_schemes(): array
{
    $root = schemes_root();
    if (!is_dir($root)) {
        return [];
    }
    $dirs = glob($root . '/*', GLOB_ONLYDIR) ?: [];
    $schemes = [];
    foreach ($dirs as $dir) {
        $code = basename($dir);
        $meta = readJson($dir . '/scheme_meta.json');
        if (!$meta) {
            continue;
        }
        $meta['schemeCode'] = $code;
        $schemes[] = $meta;
    }
    usort($schemes, fn($a, $b) => strcmp($a['schemeCode'] ?? '', $b['schemeCode'] ?? ''));
    return $schemes;
}

function load_scheme_meta(string $schemeCode): array
{
    return readJson(scheme_meta_path($schemeCode));
}

function save_scheme_meta(string $schemeCode, array $meta): void
{
    $meta['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(scheme_meta_path($schemeCode), $meta);
}

function scheme_default_draft(string $schemeCode, array $meta): array
{
    return [
        'schemeCode' => $schemeCode,
        'name' => $meta['name'] ?? $schemeCode,
        'description' => $meta['description'] ?? '',
        'caseLabel' => $meta['caseLabel'] ?? 'Beneficiary',
        'status' => 'draft',
        'roles' => [
            ['roleId' => 'vendor_admin', 'label' => 'Vendor Admin'],
            ['roleId' => 'vendor_staff', 'label' => 'Vendor Staff'],
            ['roleId' => 'customer', 'label' => 'Customer'],
            ['roleId' => 'authority', 'label' => 'Authority'],
        ],
        'modules' => [
            ['moduleId' => 'application', 'label' => 'Application'],
            ['moduleId' => 'compliance', 'label' => 'Compliance'],
        ],
        'fieldDictionary' => [],
        'documents' => [],
        'packs' => [],
        'toggles' => [
            'customerPortalEnabled' => false,
            'autoCreateTasks' => false,
        ],
        'publishedAt' => null,
        'version' => 'draft',
    ];
}

function load_scheme_draft(string $schemeCode): array
{
    $draft = readJson(scheme_draft_path($schemeCode));
    if (!$draft) {
        $meta = load_scheme_meta($schemeCode);
        if (!$meta) {
            return [];
        }
        $draft = scheme_default_draft($schemeCode, $meta);
        writeJsonAtomic(scheme_draft_path($schemeCode), $draft);
    }
    return $draft;
}

function save_scheme_draft(string $schemeCode, array $draft): void
{
    $draft['status'] = 'draft';
    $draft['version'] = 'draft';
    writeJsonAtomic(scheme_draft_path($schemeCode), $draft);
}

function list_scheme_versions(string $schemeCode): array
{
    $dir = scheme_versions_path($schemeCode);
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/v*.json') ?: [];
    $versions = [];
    foreach ($files as $file) {
        $name = basename($file, '.json');
        $versions[] = $name;
    }
    usort($versions, fn($a, $b) => version_compare(ltrim($b, 'v'), ltrim($a, 'v')));
    return $versions;
}

function load_scheme_version(string $schemeCode, string $version): array
{
    if ($version === 'draft') {
        return load_scheme_draft($schemeCode);
    }
    return readJson(scheme_versions_path($schemeCode) . '/' . $version . '.json');
}

function next_scheme_version(string $schemeCode): string
{
    $versions = list_scheme_versions($schemeCode);
    $max = 0;
    foreach ($versions as $version) {
        $num = (int)ltrim($version, 'v');
        if ($num > $max) {
            $max = $num;
        }
    }
    return 'v' . ($max + 1);
}

function publish_scheme_version(string $schemeCode, array $draft, string $actor): string
{
    $version = next_scheme_version($schemeCode);
    $draft['status'] = 'published';
    $draft['version'] = $version;
    $draft['publishedAt'] = now_kolkata()->format(DateTime::ATOM);
    $path = scheme_versions_path($schemeCode) . '/' . $version . '.json';
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }
    writeJsonAtomic($path, $draft);
    scheme_log_audit($schemeCode, 'publish', $actor, ['version' => $version]);
    return $version;
}

function scheme_log_audit(string $schemeCode, string $event, string $actor, array $payload = []): void
{
    $record = array_merge([
        'event' => $event,
        'actor' => $actor,
        'schemeCode' => $schemeCode,
    ], $payload);
    logEvent(scheme_audit_path($schemeCode), $record);
    logEvent(DATA_PATH . '/logs/scheme_builder.log', $record);
}

function scheme_log_runtime_error(array $payload): void
{
    logEvent(DATA_PATH . '/logs/scheme_runtime.log', $payload);
}

function scheme_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    $value = trim((string)$value, '_');
    return $value ?: 'field';
}

function scheme_generate_field_key(string $label, array $existing, string $moduleId = ''): string
{
    $prefix = $moduleId !== '' ? $moduleId : 'case';
    $base = $prefix . '.' . scheme_slugify($label);
    $key = $base;
    $counter = 2;
    $existingKeys = array_map('strtolower', $existing);
    while (in_array(strtolower($key), $existingKeys, true)) {
        $key = $base . '_' . $counter;
        $counter++;
    }
    return $key;
}

function scheme_add_field(array $draft, array $payload): array
{
    $fields = $draft['fieldDictionary'] ?? [];
    $keys = array_map(fn($field) => $field['key'] ?? '', $fields);
    $key = scheme_generate_field_key($payload['label'], $keys, $payload['moduleId'] ?? '');
    $fields[] = [
        'key' => $key,
        'label' => $payload['label'],
        'type' => $payload['type'],
        'required' => $payload['required'],
        'validation' => [
            'minLen' => $payload['minLen'],
            'maxLen' => $payload['maxLen'],
            'pattern' => $payload['pattern'],
            'min' => $payload['min'],
            'max' => $payload['max'],
            'dateMin' => $payload['dateMin'],
            'dateMax' => $payload['dateMax'],
            'options' => $payload['options'],
        ],
        'visibility' => [
            'view' => $payload['viewRoles'],
            'edit' => $payload['editRoles'],
        ],
        'moduleId' => $payload['moduleId'],
        'unique' => $payload['unique'],
    ];
    $draft['fieldDictionary'] = $fields;
    return $draft;
}

function scheme_update_field(array $draft, string $key, array $payload): array
{
    $fields = $draft['fieldDictionary'] ?? [];
    foreach ($fields as &$field) {
        if (($field['key'] ?? '') === $key) {
            $field['label'] = $payload['label'];
            $field['type'] = $payload['type'];
            $field['required'] = $payload['required'];
            $field['validation'] = [
                'minLen' => $payload['minLen'],
                'maxLen' => $payload['maxLen'],
                'pattern' => $payload['pattern'],
                'min' => $payload['min'],
                'max' => $payload['max'],
                'dateMin' => $payload['dateMin'],
                'dateMax' => $payload['dateMax'],
                'options' => $payload['options'],
            ];
            $field['visibility'] = [
                'view' => $payload['viewRoles'],
                'edit' => $payload['editRoles'],
            ];
            $field['moduleId'] = $payload['moduleId'];
            $field['unique'] = $payload['unique'];
        }
    }
    unset($field);
    $draft['fieldDictionary'] = $fields;
    return $draft;
}

function scheme_delete_field(array $draft, string $key): array
{
    $draft['fieldDictionary'] = array_values(array_filter($draft['fieldDictionary'] ?? [], fn($field) => ($field['key'] ?? '') !== $key));
    return $draft;
}

function scheme_add_pack(array $draft, array $payload): array
{
    $packs = $draft['packs'] ?? [];
    $packs[] = [
        'packId' => $payload['packId'],
        'label' => $payload['label'],
        'moduleId' => $payload['moduleId'],
        'requiredFieldKeys' => $payload['requiredFieldKeys'],
        'documentIds' => $payload['documentIds'],
        'workflow' => [
            'enabled' => $payload['workflowEnabled'],
            'states' => $payload['workflowStates'] ?: ['Draft', 'Submitted', 'Approved', 'Completed'],
            'transitions' => $payload['workflowTransitions'],
            'defaultState' => $payload['workflowDefaultState'] ?? '',
        ],
    ];
    $draft['packs'] = $packs;
    return $draft;
}

function scheme_update_pack(array $draft, string $packId, array $payload): array
{
    $packs = $draft['packs'] ?? [];
    foreach ($packs as &$pack) {
        if (($pack['packId'] ?? '') === $packId) {
            $pack['label'] = $payload['label'];
            $pack['packId'] = $payload['packId'];
            $pack['moduleId'] = $payload['moduleId'];
            $pack['requiredFieldKeys'] = $payload['requiredFieldKeys'];
            $pack['documentIds'] = $payload['documentIds'];
            $pack['workflow'] = [
                'enabled' => $payload['workflowEnabled'],
                'states' => $payload['workflowStates'] ?: ['Draft', 'Submitted', 'Approved', 'Completed'],
                'transitions' => $payload['workflowTransitions'],
                'defaultState' => $payload['workflowDefaultState'] ?? '',
            ];
        }
    }
    unset($pack);
    $draft['packs'] = $packs;
    return $draft;
}

function scheme_delete_pack(array $draft, string $packId): array
{
    $draft['packs'] = array_values(array_filter($draft['packs'] ?? [], fn($pack) => ($pack['packId'] ?? '') !== $packId));
    return $draft;
}

function scheme_add_document(array $draft, array $payload): array
{
    $docs = $draft['documents'] ?? [];
    $docs[] = [
        'docId' => $payload['docId'],
        'label' => $payload['label'],
        'templateType' => 'simple_html',
        'templateBody' => $payload['templateBody'],
        'generation' => [
            'auto' => $payload['autoGenerate'],
            'allowManual' => $payload['allowManual'],
            'allowRegen' => $payload['allowRegen'],
            'lockAfterGen' => $payload['lockAfterGen'],
        ],
        'visibility' => [
            'vendor' => $payload['vendorVisible'],
            'customerDownload' => $payload['customerDownload'],
            'authorityOnly' => $payload['authorityOnly'],
        ],
    ];
    $draft['documents'] = $docs;
    return $draft;
}

function scheme_update_document(array $draft, string $docId, array $payload): array
{
    $docs = $draft['documents'] ?? [];
    foreach ($docs as &$doc) {
        if (($doc['docId'] ?? '') === $docId) {
            $doc['docId'] = $payload['docId'];
            $doc['label'] = $payload['label'];
            $doc['templateBody'] = $payload['templateBody'];
            $doc['generation'] = [
                'auto' => $payload['autoGenerate'],
                'allowManual' => $payload['allowManual'],
                'allowRegen' => $payload['allowRegen'],
                'lockAfterGen' => $payload['lockAfterGen'],
            ];
            $doc['visibility'] = [
                'vendor' => $payload['vendorVisible'],
                'customerDownload' => $payload['customerDownload'],
                'authorityOnly' => $payload['authorityOnly'],
            ];
        }
    }
    unset($doc);
    $draft['documents'] = $docs;
    return $draft;
}

function scheme_delete_document(array $draft, string $docId): array
{
    $draft['documents'] = array_values(array_filter($draft['documents'] ?? [], fn($doc) => ($doc['docId'] ?? '') !== $docId));
    return $draft;
}

function scheme_update_pack_workflow(array $draft, string $packId, array $workflow): array
{
    $packs = $draft['packs'] ?? [];
    foreach ($packs as &$pack) {
        if (($pack['packId'] ?? '') === $packId) {
            $pack['workflow'] = $workflow;
        }
    }
    unset($pack);
    $draft['packs'] = $packs;
    return $draft;
}

function scheme_add_workflow_transition(array $draft, string $packId, array $transition): array
{
    $packs = $draft['packs'] ?? [];
    foreach ($packs as &$pack) {
        if (($pack['packId'] ?? '') === $packId) {
            $workflow = $pack['workflow'] ?? ['enabled' => true, 'states' => [], 'transitions' => []];
            $workflow['transitions'] = array_values(array_merge($workflow['transitions'] ?? [], [$transition]));
            $pack['workflow'] = $workflow;
        }
    }
    unset($pack);
    $draft['packs'] = $packs;
    return $draft;
}

function scheme_render_template(string $template, array $values, array $missingKeys = []): string
{
    return preg_replace_callback('/\{\{\s*field:([a-zA-Z0-9._-]+)\s*\}\}/', function ($matches) use ($values, $missingKeys) {
        $key = $matches[1] ?? '';
        if (in_array($key, $missingKeys, true)) {
            return '<span class="placeholder-line">__________</span>';
        }
        return htmlspecialchars((string)($values[$key] ?? ''), ENT_QUOTES, 'UTF-8');
    }, $template) ?? $template;
}

function scheme_case_values(string $schemeCode, string $yojId, string $caseId): array
{
    $fields = readJson(scheme_case_fields_path($schemeCode, $yojId, $caseId));
    return $fields['values'] ?? [];
}

function scheme_pack_runtime_from_values(array $pack, array $values): array
{
    $required = $pack['requiredFieldKeys'] ?? [];
    $missing = [];
    foreach ($required as $key) {
        $val = $values[$key] ?? null;
        if ($val === null || $val === '') {
            $missing[] = $key;
        }
    }
    $status = empty($missing) ? 'ready' : 'not_ready';
    return [
        'packId' => $pack['packId'],
        'status' => $status,
        'missingFields' => $missing,
        'generatedDocs' => [],
        'workflowState' => ($pack['workflow']['defaultState'] ?? $pack['workflow']['states'][0] ?? 'Draft'),
        'updatedAt' => now_kolkata()->format(DateTime::ATOM),
    ];
}

function scheme_pack_status_label(string $status): string
{
    return match ($status) {
        'not_ready' => 'Not Ready',
        'ready' => 'Ready',
        'generated' => 'Generated',
        'approved' => 'Approved',
        'submitted' => 'Submitted',
        'completed' => 'Completed',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

function scheme_pack_status_from_runtime(array $pack, array $missingFields, array $generatedDocs, string $workflowState): string
{
    $workflowEnabled = (bool)($pack['workflow']['enabled'] ?? false);
    $state = strtolower($workflowState);
    if ($workflowEnabled && in_array($state, ['submitted'], true)) {
        return 'submitted';
    }
    if ($workflowEnabled && in_array($state, ['approved', 'completed'], true)) {
        return 'approved';
    }
    if (!empty($missingFields)) {
        return 'not_ready';
    }
    if (!empty($generatedDocs)) {
        return 'generated';
    }
    return 'ready';
}

function scheme_update_pack_runtime(string $schemeCode, string $yojId, string $caseId, array $pack, array $values): array
{
    $path = scheme_case_pack_runtime_path($schemeCode, $yojId, $caseId, $pack['packId']);
    $runtime = readJson($path);
    $computed = scheme_pack_runtime_from_values($pack, $values);
    $runtime = array_merge($computed, $runtime);
    $runtime['missingFields'] = $computed['missingFields'];
    $runtime['workflowState'] = $runtime['workflowState'] ?? $computed['workflowState'];
    $runtime['status'] = scheme_pack_status_from_runtime($pack, $runtime['missingFields'], $runtime['generatedDocs'] ?? [], $runtime['workflowState'] ?? '');
    $runtime['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic($path, $runtime);
    return $runtime;
}

function scheme_pack_documents(array $scheme, array $pack): array
{
    $docs = [];
    if (!empty($pack['documents'])) {
        return $pack['documents'];
    }
    $library = $scheme['documents'] ?? [];
    $documentIds = $pack['documentIds'] ?? [];
    foreach ($documentIds as $docId) {
        foreach ($library as $doc) {
            if (($doc['docId'] ?? '') === $docId) {
                $docs[] = $doc;
                break;
            }
        }
    }
    return $docs;
}

function scheme_template_field_keys(string $template): array
{
    preg_match_all('/\{\{\s*field:([a-zA-Z0-9._-]+)\s*\}\}/', $template, $matches);
    return array_values(array_unique($matches[1] ?? []));
}

function scheme_missing_template_values(array $templateKeys, array $values): array
{
    $missing = [];
    foreach ($templateKeys as $key) {
        $val = $values[$key] ?? null;
        if ($val === null || $val === '') {
            $missing[] = $key;
        }
    }
    return $missing;
}

function scheme_document_generation_path(string $schemeCode, string $yojId, string $caseId, string $docId, string $genId): string
{
    return scheme_case_documents_dir($schemeCode, $yojId, $caseId) . '/' . $docId . '/' . $genId . '.json';
}

function scheme_document_latest_generation(string $schemeCode, string $yojId, string $caseId, string $docId): array
{
    $dir = scheme_case_documents_dir($schemeCode, $yojId, $caseId) . '/' . $docId;
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.json') ?: [];
    if (!$files) {
        return [];
    }
    rsort($files);
    return readJson($files[0]);
}

function scheme_append_timeline(string $schemeCode, string $yojId, string $caseId, array $payload): void
{
    $path = scheme_case_dir($schemeCode, $yojId, $caseId) . '/timeline.jsonl';
    logEvent($path, $payload);
}

function scheme_document_generate(array $scheme, array $case, array $pack, array $doc, array $values, string $yojId): array
{
    $template = $doc['templateBody'] ?? '';
    $keys = scheme_template_field_keys($template);
    $missing = scheme_missing_template_values($keys, $values);
    if ($missing) {
        return ['error' => 'missing_fields', 'missing' => $missing];
    }
    $rendered = scheme_render_template($template, $values, []);
    $genId = 'GEN-' . now_kolkata()->format('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
    $payload = [
        'docId' => $doc['docId'],
        'label' => $doc['label'] ?? $doc['docId'],
        'schemeVersion' => $case['schemeVersion'] ?? '',
        'generatedAt' => now_kolkata()->format(DateTime::ATOM),
        'generatedBy' => $yojId,
        'renderedHtml' => $rendered,
    ];
    $path = scheme_document_generation_path($case['schemeCode'] ?? '', $yojId, $case['caseId'] ?? '', $doc['docId'], $genId);
    writeJsonAtomic($path, $payload);
    return ['generation' => $payload, 'genId' => $genId];
}

function scheme_pack_next_state(array $pack, string $currentState): string
{
    $states = $pack['workflow']['states'] ?? [];
    $index = array_search($currentState, $states, true);
    if ($index === false) {
        return $states[0] ?? '';
    }
    return $states[$index + 1] ?? '';
}

function ensure_contract_scheme_activation_env(string $yojId): void
{
    $dir = contractor_scheme_activation_dir($yojId);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!is_dir(contractor_scheme_requests_dir($yojId))) {
        mkdir(contractor_scheme_requests_dir($yojId), 0775, true);
    }
    if (!file_exists(contractor_scheme_enabled_path($yojId))) {
        writeJsonAtomic(contractor_scheme_enabled_path($yojId), []);
    }
}

function contractor_enabled_schemes(string $yojId): array
{
    ensure_contract_scheme_activation_env($yojId);
    return readJson(contractor_scheme_enabled_path($yojId));
}

function contractor_set_enabled_scheme(string $yojId, string $schemeCode, string $version): void
{
    $enabled = contractor_enabled_schemes($yojId);
    $enabled[strtoupper($schemeCode)] = $version;
    writeJsonAtomic(contractor_scheme_enabled_path($yojId), $enabled);
}

function create_activation_request(string $yojId, string $schemeCode, string $version): array
{
    ensure_contract_scheme_activation_env($yojId);
    $requestId = 'REQ-' . now_kolkata()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $payload = [
        'requestId' => $requestId,
        'yojId' => $yojId,
        'schemeCode' => strtoupper($schemeCode),
        'requestedVersion' => $version,
        'status' => 'pending',
        'createdAt' => now_kolkata()->format(DateTime::ATOM),
        'decisionAt' => null,
        'decisionBy' => null,
        'notes' => '',
    ];
    $path = contractor_scheme_requests_dir($yojId) . '/' . $requestId . '.json';
    writeJsonAtomic($path, $payload);
    return $payload;
}

function list_activation_requests(string $status = ''): array
{
    $root = DATA_PATH . '/contractor_scheme_activations';
    if (!is_dir($root)) {
        return [];
    }
    $records = [];
    $dirs = glob($root . '/*', GLOB_ONLYDIR) ?: [];
    foreach ($dirs as $dir) {
        $files = glob($dir . '/requests/REQ-*.json') ?: [];
        foreach ($files as $file) {
            $record = readJson($file);
            if (!$record) {
                continue;
            }
            if ($status && ($record['status'] ?? '') !== $status) {
                continue;
            }
            $record['_path'] = $file;
            $records[] = $record;
        }
    }
    usort($records, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
    return $records;
}

function update_activation_request(string $path, array $record): void
{
    writeJsonAtomic($path, $record);
}

function scheme_case_create(string $schemeCode, string $version, string $yojId, string $caseLabel, string $title): array
{
    $caseId = 'CASE-' . now_kolkata()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $now = now_kolkata()->format(DateTime::ATOM);
    $case = [
        'caseId' => $caseId,
        'schemeCode' => strtoupper($schemeCode),
        'schemeVersion' => $version,
        'yojId' => $yojId,
        'caseLabel' => $caseLabel,
        'title' => $title ?: ($caseLabel . ' ' . $caseId),
        'status' => 'active',
        'createdAt' => $now,
        'updatedAt' => $now,
    ];
    $caseDir = scheme_case_dir($schemeCode, $yojId, $caseId);
    if (!is_dir($caseDir)) {
        mkdir($caseDir, 0775, true);
    }
    writeJsonAtomic($caseDir . '/case.json', $case);
    writeJsonAtomic($caseDir . '/fields.json', [
        'values' => [],
        'updatedAt' => $now,
    ]);
    $scheme = load_scheme_version($schemeCode, $version);
    foreach ($scheme['packs'] ?? [] as $pack) {
        scheme_update_pack_runtime($schemeCode, $yojId, $caseId, $pack, []);
    }
    foreach ($scheme['packs'] ?? [] as $pack) {
        $docs = scheme_pack_documents($scheme, $pack);
        foreach ($docs as $doc) {
            if (!empty($doc['generation']['auto'])) {
                $result = scheme_document_generate($scheme, $case, $pack, $doc, [], $yojId);
                if (!empty($result['generation'])) {
                    $runtimePath = scheme_case_pack_runtime_path($schemeCode, $yojId, $caseId, $pack['packId']);
                    $runtime = readJson($runtimePath);
                    $generated = $runtime['generatedDocs'] ?? [];
                    if (!in_array($doc['docId'], $generated, true)) {
                        $generated[] = $doc['docId'];
                    }
                    $runtime['generatedDocs'] = $generated;
                    $runtime['status'] = scheme_pack_status_from_runtime($pack, $runtime['missingFields'] ?? [], $generated, $runtime['workflowState'] ?? '');
                    writeJsonAtomic($runtimePath, $runtime);
                }
            }
        }
    }
    scheme_append_timeline($schemeCode, $yojId, $caseId, ['event' => 'CASE_CREATED', 'caseId' => $caseId]);
    return $case;
}

function list_scheme_cases(string $schemeCode, string $yojId): array
{
    $dir = scheme_cases_root($schemeCode, $yojId) . '/cases';
    if (!is_dir($dir)) {
        return [];
    }
    $cases = [];
    $caseDirs = glob($dir . '/*', GLOB_ONLYDIR) ?: [];
    foreach ($caseDirs as $caseDir) {
        $case = readJson($caseDir . '/case.json');
        if ($case) {
            $cases[] = $case;
        }
    }
    usort($cases, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
    return $cases;
}
