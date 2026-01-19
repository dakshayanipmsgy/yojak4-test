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

function scheme_snapshots_dir(string $schemeCode): string
{
    return scheme_base_path($schemeCode) . '/snapshots';
}

function scheme_versions_path(string $schemeCode): string
{
    return scheme_base_path($schemeCode) . '/versions';
}

function scheme_audit_path(string $schemeCode): string
{
    return scheme_base_path($schemeCode) . '/audit.jsonl';
}

function scheme_prompt_templates_path(): string
{
    return schemes_root() . '/_prompt_templates.json';
}

function scheme_default_prompt_templates(): array
{
    return [
        'overview' => "TITLE: Generate Scheme Overview JSON for YOJAK (Overview Section Only)\n\nYou are generating JSON for YOJAK Scheme Builder.\nOutput ONLY raw JSON (no markdown, no comments).\nThis JSON will be pasted into the \"Overview\" section.\n\nSchema:\n{\n  \"name\": \"Scheme Name\",\n  \"description\": \"Short summary\",\n  \"caseLabel\": \"Beneficiary\",\n  \"toggles\": {\n    \"customerPortalEnabled\": false,\n    \"autoCreateTasks\": false\n  },\n  \"status\": \"draft\"\n}\n\nRules:\n- Use strings for name/description/caseLabel\n- toggles values must be true/false\n- Do not include any pricing, bid, rate, BOQ, or amount fields\n\nHere is the feature description from user: <<<USER_DESCRIPTION>>>\nNow generate the JSON.",
        'roles' => "TITLE: Generate Scheme Roles JSON for YOJAK (Roles Section Only)\n\nYou are generating JSON for YOJAK Scheme Builder.\nOutput ONLY raw JSON (no markdown, no comments).\nThis JSON will be pasted into the \"Roles\" section.\n\nSchema:\n{\n  \"roles\": [\n    {\n      \"roleId\": \"vendor_admin\",\n      \"label\": \"Vendor Admin\"\n    }\n  ]\n}\n\nRules:\n- roleId values must be unique\n- Use lowercase snake_case for roleId\n- Do not include any pricing, bid, rate, BOQ, or amount fields\n\nHere is the feature description from user: <<<USER_DESCRIPTION>>>\nNow generate the JSON.",
        'modules' => "TITLE: Generate Scheme Modules JSON for YOJAK (Modules Section Only)\n\nYou are generating JSON for YOJAK Scheme Builder.\nOutput ONLY raw JSON (no markdown, no comments).\nThis JSON will be pasted into the \"Modules\" section.\n\nSchema:\n{\n  \"modules\": [\n    {\n      \"moduleId\": \"application\",\n      \"label\": \"Application\"\n    }\n  ]\n}\n\nRules:\n- moduleId values must be unique\n- Use lowercase snake_case for moduleId\n- Do not include any pricing, bid, rate, BOQ, or amount fields\n\nHere is the feature description from user: <<<USER_DESCRIPTION>>>\nNow generate the JSON.",
        'fields' => "TITLE: Generate Scheme Fields JSON for YOJAK (Fields Section Only)\n\nYou are generating JSON for YOJAK Scheme Builder.\nOutput ONLY raw JSON (no markdown, no comments).\nThis JSON will be pasted into the \"Fields\" section.\n\nSchema:\n{\n  \"fieldDictionary\": [\n    {\n      \"key\": \"case.customer_name\",\n      \"label\": \"Customer Name\",\n      \"type\": \"text|number|date|dropdown|file|textarea|yesno\",\n      \"required\": true,\n      \"validation\": { \"minLen\": 2, \"maxLen\": 100, \"pattern\": null, \"min\": null, \"max\": null, \"dateMin\": null, \"dateMax\": null, \"options\": [] },\n      \"visibility\": { \"view\": [\"vendor_admin\"], \"edit\": [\"vendor_admin\"] },\n      \"moduleId\": \"application\"\n    }\n  ]\n}\n\nRules:\n- keys must be unique\n- moduleId must match existing module ids (or empty for case-level)\n- visibility role ids must exist\n- Do not create any pricing/bid/rate/BOQ/amount fields\n\nHere is the feature description from user: <<<USER_DESCRIPTION>>>\nNow generate the JSON.",
        'packs' => "TITLE: Generate Scheme Packs JSON for YOJAK (Packs Section Only)\n\nYou are generating JSON for YOJAK Scheme Builder.\nOutput ONLY raw JSON (no markdown, no comments).\nThis JSON will be pasted into the \"Packs\" section.\n\nSchema:\n{\n  \"packs\": [\n    {\n      \"packId\": \"application_pack\",\n      \"label\": \"Application Pack\",\n      \"moduleId\": \"application\",\n      \"requiredFieldKeys\": [\"application.applicant_name\"],\n      \"documentIds\": [\"application_form\"],\n      \"workflow\": {\n        \"enabled\": true,\n        \"states\": [\"Draft\", \"Submitted\", \"Approved\", \"Completed\"],\n        \"transitions\": [],\n        \"defaultState\": \"Draft\"\n      }\n    }\n  ]\n}\n\nRules:\n- packId values must be unique\n- moduleId must match existing modules\n- requiredFieldKeys must exist in fieldDictionary\n- documentIds must exist in documents\n- Do not include any pricing/bid/rate/BOQ/amount fields\n\nHere is the feature description from user: <<<USER_DESCRIPTION>>>\nNow generate the JSON.",
        'documents' => "TITLE: Generate Scheme Documents JSON for YOJAK (Documents Section Only)\n\nYou are generating JSON for YOJAK Scheme Builder.\nOutput ONLY raw JSON (no markdown, no comments).\nThis JSON will be pasted into the \"Documents\" section.\n\nSchema:\n{\n  \"documents\": [\n    {\n      \"docId\": \"application_form\",\n      \"label\": \"Application Form\",\n      \"templateType\": \"simple_html\",\n      \"templateBody\": \"<h1>Application</h1>\",\n      \"generation\": {\n        \"auto\": false,\n        \"allowManual\": true,\n        \"allowRegen\": true,\n        \"lockAfterGen\": false\n      },\n      \"visibility\": {\n        \"vendor\": true,\n        \"customerDownload\": false,\n        \"authorityOnly\": false\n      }\n    }\n  ]\n}\n\nRules:\n- docId values must be unique\n- Do not include any pricing/bid/rate/BOQ/amount fields\n\nHere is the feature description from user: <<<USER_DESCRIPTION>>>\nNow generate the JSON.",
        'workflows' => "TITLE: Generate Scheme Workflows JSON for YOJAK (Workflows Section Only)\n\nYou are generating JSON for YOJAK Scheme Builder.\nOutput ONLY raw JSON (no markdown, no comments).\nThis JSON will be pasted into the \"Workflows\" section.\n\nSchema:\n{\n  \"workflows\": [\n    {\n      \"packId\": \"application_pack\",\n      \"workflow\": {\n        \"enabled\": true,\n        \"states\": [\"Draft\", \"Submitted\"],\n        \"transitions\": [\n          {\n            \"from\": \"Draft\",\n            \"to\": \"Submitted\",\n            \"roles\": [\"vendor_admin\"],\n            \"requiredFields\": [],\n            \"requiredDocs\": [],\n            \"approval\": null\n          }\n        ],\n        \"defaultState\": \"Draft\"\n      }\n    }\n  ]\n}\n\nRules:\n- packId must match existing packs\n- roles must exist\n- requiredFields must exist in fieldDictionary\n- requiredDocs must exist in the pack's documentIds\n- Do not include any pricing/bid/rate/BOQ/amount fields\n\nHere is the feature description from user: <<<USER_DESCRIPTION>>>\nNow generate the JSON.",
    ];
}

function scheme_load_prompt_templates(): array
{
    $path = scheme_prompt_templates_path();
    $defaults = scheme_default_prompt_templates();
    $stored = readJson($path);
    if (!$stored) {
        writeJsonAtomic($path, $defaults);
        return $defaults;
    }
    $merged = $stored + $defaults;
    if ($merged !== $stored) {
        writeJsonAtomic($path, $merged);
    }
    return $merged;
}

function scheme_section_payload_from_draft(string $section, array $draft): array
{
    return match ($section) {
        'overview' => [
            'name' => $draft['name'] ?? '',
            'description' => $draft['description'] ?? '',
            'caseLabel' => $draft['caseLabel'] ?? 'Beneficiary',
            'toggles' => $draft['toggles'] ?? [],
            'status' => $draft['status'] ?? 'draft',
        ],
        'roles' => ['roles' => $draft['roles'] ?? []],
        'modules' => ['modules' => $draft['modules'] ?? []],
        'fields' => ['fieldDictionary' => $draft['fieldDictionary'] ?? []],
        'packs' => ['packs' => $draft['packs'] ?? []],
        'documents' => ['documents' => $draft['documents'] ?? []],
        'workflows' => [
            'workflows' => array_values(array_map(function ($pack) {
                return [
                    'packId' => $pack['packId'] ?? '',
                    'workflow' => $pack['workflow'] ?? [
                        'enabled' => false,
                        'states' => [],
                        'transitions' => [],
                        'defaultState' => '',
                    ],
                ];
            }, $draft['packs'] ?? [])),
        ],
        default => [],
    };
}

function scheme_section_example_json(string $section): string
{
    $examples = [
        'overview' => [
            'name' => 'Scholarship Scheme',
            'description' => 'Support students with tuition assistance.',
            'caseLabel' => 'Beneficiary',
            'toggles' => [
                'customerPortalEnabled' => true,
                'autoCreateTasks' => false,
            ],
            'status' => 'draft',
        ],
        'roles' => [
            'roles' => [
                ['roleId' => 'vendor_admin', 'label' => 'Vendor Admin'],
            ],
        ],
        'modules' => [
            'modules' => [
                ['moduleId' => 'application', 'label' => 'Application'],
            ],
        ],
        'fields' => [
            'fieldDictionary' => [
                [
                    'key' => 'application.applicant_name',
                    'label' => 'Applicant Name',
                    'type' => 'text',
                    'required' => true,
                    'validation' => [
                        'minLen' => 2,
                        'maxLen' => 100,
                        'pattern' => null,
                        'min' => null,
                        'max' => null,
                        'dateMin' => null,
                        'dateMax' => null,
                        'options' => [],
                    ],
                    'visibility' => [
                        'view' => ['vendor_admin'],
                        'edit' => ['vendor_admin'],
                    ],
                    'moduleId' => 'application',
                ],
            ],
        ],
        'packs' => [
            'packs' => [
                [
                    'packId' => 'application_pack',
                    'label' => 'Application Pack',
                    'moduleId' => 'application',
                    'requiredFieldKeys' => ['application.applicant_name'],
                    'documentIds' => ['application_form'],
                    'workflow' => [
                        'enabled' => true,
                        'states' => ['Draft', 'Submitted'],
                        'transitions' => [],
                        'defaultState' => 'Draft',
                    ],
                ],
            ],
        ],
        'documents' => [
            'documents' => [
                [
                    'docId' => 'application_form',
                    'label' => 'Application Form',
                    'templateType' => 'simple_html',
                    'templateBody' => '<h1>Application</h1>',
                    'generation' => [
                        'auto' => false,
                        'allowManual' => true,
                        'allowRegen' => true,
                        'lockAfterGen' => false,
                    ],
                    'visibility' => [
                        'vendor' => true,
                        'customerDownload' => false,
                        'authorityOnly' => false,
                    ],
                ],
            ],
        ],
        'workflows' => [
            'workflows' => [
                [
                    'packId' => 'application_pack',
                    'workflow' => [
                        'enabled' => true,
                        'states' => ['Draft', 'Submitted'],
                        'transitions' => [
                            [
                                'from' => 'Draft',
                                'to' => 'Submitted',
                                'roles' => ['vendor_admin'],
                                'requiredFields' => [],
                                'requiredDocs' => [],
                                'approval' => null,
                            ],
                        ],
                        'defaultState' => 'Draft',
                    ],
                ],
            ],
        ],
    ];

    return json_encode($examples[$section] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function scheme_path_join(string $base, string $segment, bool $isIndex = false): string
{
    if ($base === '') {
        return $isIndex ? '[' . $segment . ']' : $segment;
    }
    return $isIndex ? $base . '[' . $segment . ']' : $base . '.' . $segment;
}

function scheme_forbidden_pricing_terms(): array
{
    return ['price', 'rate', 'boq', 'amount', 'bid'];
}

function scheme_string_has_forbidden_pricing(string $value): bool
{
    $lower = strtolower($value);
    if (str_contains($lower, 'billing status')) {
        return false;
    }
    foreach (scheme_forbidden_pricing_terms() as $term) {
        if ($term !== '' && str_contains($lower, $term)) {
            return true;
        }
    }
    return false;
}

function scheme_collect_pricing_errors(mixed $data, string $path = ''): array
{
    $errors = [];
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $keyPath = scheme_path_join($path, (string)$key, is_int($key));
            if (is_string($key) && scheme_string_has_forbidden_pricing($key)) {
                $errors[] = "Forbidden pricing/bid field detected in key '{$key}' at {$keyPath}. How to fix: remove pricing-related keys.";
            }
            if (is_string($value) && scheme_string_has_forbidden_pricing($value)) {
                $errors[] = "Forbidden pricing/bid field detected in value '{$value}' at {$keyPath}. How to fix: remove pricing-related labels or values.";
            }
            $errors = array_merge($errors, scheme_collect_pricing_errors($value, $keyPath));
        }
    }
    return $errors;
}

function scheme_validate_allowed_keys(array $data, array $allowed, string $path, array &$errors): void
{
    foreach ($data as $key => $_value) {
        if (!in_array($key, $allowed, true)) {
            $location = scheme_path_join($path, (string)$key);
            $errors[] = "Unknown key: {$location}. How to fix: remove it or move metadata under x_meta.";
        }
    }
}

function scheme_require_keys(array $data, array $required, string $path, array &$errors): void
{
    foreach ($required as $key) {
        if (!array_key_exists($key, $data)) {
            $location = scheme_path_join($path, $key);
            $errors[] = "Missing required key: {$location}. How to fix: add the missing key.";
        }
    }
}

function scheme_validate_section_json(string $section, array $payload, array $draft): array
{
    $errors = [];
    $errors = array_merge($errors, scheme_collect_pricing_errors($payload));
    $roles = array_map(fn($role) => $role['roleId'] ?? '', $draft['roles'] ?? []);
    $modules = array_map(fn($module) => $module['moduleId'] ?? '', $draft['modules'] ?? []);
    $fields = array_map(fn($field) => $field['key'] ?? '', $draft['fieldDictionary'] ?? []);
    $documents = array_map(fn($doc) => $doc['docId'] ?? '', $draft['documents'] ?? []);
    $packs = array_map(fn($pack) => $pack['packId'] ?? '', $draft['packs'] ?? []);

    switch ($section) {
        case 'overview':
            scheme_validate_allowed_keys($payload, ['name', 'description', 'caseLabel', 'toggles', 'status', 'x_meta'], '', $errors);
            scheme_require_keys($payload, ['name', 'description', 'caseLabel'], '', $errors);
            if (isset($payload['name']) && !is_string($payload['name'])) {
                $errors[] = 'Overview.name must be a string. How to fix: provide text value.';
            }
            if (isset($payload['description']) && !is_string($payload['description'])) {
                $errors[] = 'Overview.description must be a string. How to fix: provide text value.';
            }
            if (isset($payload['caseLabel']) && !is_string($payload['caseLabel'])) {
                $errors[] = 'Overview.caseLabel must be a string. How to fix: provide text value.';
            }
            if (isset($payload['toggles']) && !is_array($payload['toggles'])) {
                $errors[] = 'Overview.toggles must be an object. How to fix: provide JSON object with boolean flags.';
            }
            break;
        case 'roles':
            scheme_validate_allowed_keys($payload, ['roles', 'x_meta'], '', $errors);
            scheme_require_keys($payload, ['roles'], '', $errors);
            $rolesPayload = $payload['roles'] ?? null;
            if (!is_array($rolesPayload)) {
                $errors[] = 'roles must be an array. How to fix: provide roles as a JSON array.';
                break;
            }
            $seen = [];
            foreach ($rolesPayload as $index => $role) {
                $path = scheme_path_join('roles', (string)$index, true);
                if (!is_array($role)) {
                    $errors[] = "{$path} must be an object. How to fix: provide roleId and label.";
                    continue;
                }
                scheme_validate_allowed_keys($role, ['roleId', 'label', 'x_meta'], $path, $errors);
                scheme_require_keys($role, ['roleId', 'label'], $path, $errors);
                $roleId = $role['roleId'] ?? '';
                if ($roleId === '' || !is_string($roleId)) {
                    $errors[] = "{$path}.roleId must be a non-empty string. How to fix: add roleId.";
                }
                if ($roleId !== '') {
                    $lower = strtolower($roleId);
                    if (in_array($lower, $seen, true)) {
                        $errors[] = "Duplicate roleId: {$roleId}. How to fix: make roleId unique.";
                    }
                    $seen[] = $lower;
                }
                if (isset($role['label']) && !is_string($role['label'])) {
                    $errors[] = "{$path}.label must be a string. How to fix: provide a label.";
                }
            }
            break;
        case 'modules':
            scheme_validate_allowed_keys($payload, ['modules', 'x_meta'], '', $errors);
            scheme_require_keys($payload, ['modules'], '', $errors);
            $modulesPayload = $payload['modules'] ?? null;
            if (!is_array($modulesPayload)) {
                $errors[] = 'modules must be an array. How to fix: provide modules as a JSON array.';
                break;
            }
            $seen = [];
            foreach ($modulesPayload as $index => $module) {
                $path = scheme_path_join('modules', (string)$index, true);
                if (!is_array($module)) {
                    $errors[] = "{$path} must be an object. How to fix: provide moduleId and label.";
                    continue;
                }
                scheme_validate_allowed_keys($module, ['moduleId', 'label', 'x_meta'], $path, $errors);
                scheme_require_keys($module, ['moduleId', 'label'], $path, $errors);
                $moduleId = $module['moduleId'] ?? '';
                if ($moduleId === '' || !is_string($moduleId)) {
                    $errors[] = "{$path}.moduleId must be a non-empty string. How to fix: add moduleId.";
                }
                if ($moduleId !== '') {
                    $lower = strtolower($moduleId);
                    if (in_array($lower, $seen, true)) {
                        $errors[] = "Duplicate moduleId: {$moduleId}. How to fix: make moduleId unique.";
                    }
                    $seen[] = $lower;
                }
                if (isset($module['label']) && !is_string($module['label'])) {
                    $errors[] = "{$path}.label must be a string. How to fix: provide a label.";
                }
            }
            break;
        case 'fields':
            scheme_validate_allowed_keys($payload, ['fieldDictionary', 'x_meta'], '', $errors);
            scheme_require_keys($payload, ['fieldDictionary'], '', $errors);
            $dictionary = $payload['fieldDictionary'] ?? null;
            if (!is_array($dictionary)) {
                $errors[] = 'fieldDictionary must be an array. How to fix: provide fields as a JSON array.';
                break;
            }
            $seen = [];
            $allowedTypes = ['text', 'number', 'date', 'dropdown', 'file', 'textarea', 'yesno'];
            foreach ($dictionary as $index => $field) {
                $path = scheme_path_join('fieldDictionary', (string)$index, true);
                if (!is_array($field)) {
                    $errors[] = "{$path} must be an object. How to fix: provide field properties.";
                    continue;
                }
                scheme_validate_allowed_keys($field, ['key', 'label', 'type', 'required', 'validation', 'visibility', 'moduleId', 'unique', 'x_meta'], $path, $errors);
                scheme_require_keys($field, ['key', 'label', 'type', 'required', 'visibility'], $path, $errors);
                $key = $field['key'] ?? '';
                if ($key === '' || !is_string($key)) {
                    $errors[] = "{$path}.key must be a non-empty string. How to fix: add a key.";
                }
                if ($key !== '') {
                    $lower = strtolower($key);
                    if (in_array($lower, $seen, true)) {
                        $errors[] = "Duplicate field key: {$key}. How to fix: make field keys unique.";
                    }
                    $seen[] = $lower;
                }
                if (isset($field['label']) && !is_string($field['label'])) {
                    $errors[] = "{$path}.label must be a string. How to fix: provide a label.";
                }
                if (isset($field['type']) && (!is_string($field['type']) || !in_array($field['type'], $allowedTypes, true))) {
                    $errors[] = "{$path}.type must be one of: " . implode(', ', $allowedTypes) . '. How to fix: choose a valid type.';
                }
                if (isset($field['required']) && !is_bool($field['required'])) {
                    $errors[] = "{$path}.required must be true/false. How to fix: use boolean.";
                }
                if (isset($field['moduleId'])) {
                    $moduleId = $field['moduleId'];
                    if ($moduleId !== '' && !in_array($moduleId, $modules, true)) {
                        $errors[] = "Unknown moduleId '{$moduleId}' in {$path}.moduleId. How to fix: use existing moduleId.";
                    }
                }
                $visibility = $field['visibility'] ?? null;
                if (!is_array($visibility)) {
                    $errors[] = "{$path}.visibility must be an object. How to fix: include view/edit arrays.";
                } else {
                    scheme_require_keys($visibility, ['view', 'edit'], $path . '.visibility', $errors);
                    foreach (['view', 'edit'] as $visKey) {
                        $list = $visibility[$visKey] ?? [];
                        if (!is_array($list)) {
                            $errors[] = "{$path}.visibility.{$visKey} must be an array. How to fix: provide role ids.";
                            continue;
                        }
                        foreach ($list as $roleId) {
                            if (!in_array($roleId, $roles, true)) {
                                $errors[] = "Unknown roleId '{$roleId}' in {$path}.visibility.{$visKey}. How to fix: use existing role ids.";
                            }
                        }
                    }
                }
                $validation = $field['validation'] ?? null;
                if ($validation !== null) {
                    if (!is_array($validation)) {
                        $errors[] = "{$path}.validation must be an object. How to fix: provide validation rules or omit.";
                    } else {
                        scheme_validate_allowed_keys($validation, ['minLen', 'maxLen', 'pattern', 'min', 'max', 'dateMin', 'dateMax', 'options', 'x_meta'], $path . '.validation', $errors);
                        if (isset($validation['options']) && !is_array($validation['options'])) {
                            $errors[] = "{$path}.validation.options must be an array. How to fix: provide dropdown options array.";
                        }
                    }
                }
            }
            break;
        case 'packs':
            scheme_validate_allowed_keys($payload, ['packs', 'x_meta'], '', $errors);
            scheme_require_keys($payload, ['packs'], '', $errors);
            $packsPayload = $payload['packs'] ?? null;
            if (!is_array($packsPayload)) {
                $errors[] = 'packs must be an array. How to fix: provide packs as a JSON array.';
                break;
            }
            $seen = [];
            foreach ($packsPayload as $index => $pack) {
                $path = scheme_path_join('packs', (string)$index, true);
                if (!is_array($pack)) {
                    $errors[] = "{$path} must be an object. How to fix: provide pack properties.";
                    continue;
                }
                scheme_validate_allowed_keys($pack, ['packId', 'label', 'moduleId', 'requiredFieldKeys', 'documentIds', 'workflow', 'x_meta'], $path, $errors);
                scheme_require_keys($pack, ['packId', 'label', 'moduleId', 'requiredFieldKeys', 'workflow'], $path, $errors);
                $packId = $pack['packId'] ?? '';
                if ($packId === '' || !is_string($packId)) {
                    $errors[] = "{$path}.packId must be a non-empty string. How to fix: add packId.";
                }
                if ($packId !== '') {
                    $lower = strtolower($packId);
                    if (in_array($lower, $seen, true)) {
                        $errors[] = "Duplicate packId: {$packId}. How to fix: make packId unique.";
                    }
                    $seen[] = $lower;
                }
                $moduleId = $pack['moduleId'] ?? '';
                if ($moduleId !== '' && !in_array($moduleId, $modules, true)) {
                    $errors[] = "Unknown moduleId '{$moduleId}' in {$path}.moduleId. How to fix: use existing moduleId.";
                }
                $requiredFields = $pack['requiredFieldKeys'] ?? [];
                if (!is_array($requiredFields)) {
                    $errors[] = "{$path}.requiredFieldKeys must be an array. How to fix: provide field keys array.";
                } else {
                    foreach ($requiredFields as $fieldKey) {
                        if (!in_array($fieldKey, $fields, true)) {
                            $errors[] = "Unknown field key '{$fieldKey}' in {$path}.requiredFieldKeys. How to fix: use existing field keys.";
                        }
                    }
                }
                $docIds = $pack['documentIds'] ?? [];
                if (!is_array($docIds)) {
                    $errors[] = "{$path}.documentIds must be an array. How to fix: provide document ids array.";
                } else {
                    foreach ($docIds as $docId) {
                        if (!in_array($docId, $documents, true)) {
                            $errors[] = "Unknown documentId '{$docId}' in {$path}.documentIds. How to fix: use existing documents.";
                        }
                    }
                }
                $workflow = $pack['workflow'] ?? null;
                if (!is_array($workflow)) {
                    $errors[] = "{$path}.workflow must be an object. How to fix: provide workflow configuration.";
                } else {
                    scheme_validate_allowed_keys($workflow, ['enabled', 'states', 'transitions', 'defaultState', 'x_meta'], $path . '.workflow', $errors);
                    scheme_require_keys($workflow, ['enabled', 'states', 'transitions'], $path . '.workflow', $errors);
                    if (isset($workflow['enabled']) && !is_bool($workflow['enabled'])) {
                        $errors[] = "{$path}.workflow.enabled must be true/false. How to fix: use boolean.";
                    }
                    if (isset($workflow['states']) && !is_array($workflow['states'])) {
                        $errors[] = "{$path}.workflow.states must be an array. How to fix: provide state list.";
                    }
                    $transitions = $workflow['transitions'] ?? [];
                    if (!is_array($transitions)) {
                        $errors[] = "{$path}.workflow.transitions must be an array. How to fix: provide transitions array.";
                    } else {
                        foreach ($transitions as $tIndex => $transition) {
                            $tPath = scheme_path_join($path . '.workflow.transitions', (string)$tIndex, true);
                            if (!is_array($transition)) {
                                $errors[] = "{$tPath} must be an object. How to fix: provide transition properties.";
                                continue;
                            }
                            scheme_validate_allowed_keys($transition, ['from', 'to', 'roles', 'requiredFields', 'requiredDocs', 'approval', 'x_meta'], $tPath, $errors);
                            scheme_require_keys($transition, ['from', 'to', 'roles', 'requiredFields', 'requiredDocs'], $tPath, $errors);
                            foreach (['from', 'to'] as $key) {
                                if (isset($transition[$key]) && !is_string($transition[$key])) {
                                    $errors[] = "{$tPath}.{$key} must be a string. How to fix: provide state name.";
                                }
                            }
                            foreach (['roles' => $roles, 'requiredFields' => $fields, 'requiredDocs' => $docIds] as $listKey => $allowed) {
                                $items = $transition[$listKey] ?? [];
                                if (!is_array($items)) {
                                    $errors[] = "{$tPath}.{$listKey} must be an array. How to fix: provide list.";
                                    continue;
                                }
                                foreach ($items as $item) {
                                    if (!in_array($item, $allowed, true)) {
                                        $errors[] = "Unknown {$listKey} reference '{$item}' in {$tPath}.{$listKey}. How to fix: use existing values.";
                                    }
                                }
                            }
                        }
                    }
                }
            }
            break;
        case 'documents':
            scheme_validate_allowed_keys($payload, ['documents', 'x_meta'], '', $errors);
            scheme_require_keys($payload, ['documents'], '', $errors);
            $docsPayload = $payload['documents'] ?? null;
            if (!is_array($docsPayload)) {
                $errors[] = 'documents must be an array. How to fix: provide documents as a JSON array.';
                break;
            }
            $seen = [];
            foreach ($docsPayload as $index => $doc) {
                $path = scheme_path_join('documents', (string)$index, true);
                if (!is_array($doc)) {
                    $errors[] = "{$path} must be an object. How to fix: provide doc properties.";
                    continue;
                }
                scheme_validate_allowed_keys($doc, ['docId', 'label', 'templateType', 'templateBody', 'generation', 'visibility', 'x_meta'], $path, $errors);
                scheme_require_keys($doc, ['docId', 'label', 'templateBody', 'generation', 'visibility'], $path, $errors);
                $docId = $doc['docId'] ?? '';
                if ($docId === '' || !is_string($docId)) {
                    $errors[] = "{$path}.docId must be a non-empty string. How to fix: add docId.";
                }
                if ($docId !== '') {
                    $lower = strtolower($docId);
                    if (in_array($lower, $seen, true)) {
                        $errors[] = "Duplicate docId: {$docId}. How to fix: make docId unique.";
                    }
                    $seen[] = $lower;
                }
                if (isset($doc['label']) && !is_string($doc['label'])) {
                    $errors[] = "{$path}.label must be a string. How to fix: provide a label.";
                }
                if (isset($doc['templateBody']) && !is_string($doc['templateBody'])) {
                    $errors[] = "{$path}.templateBody must be a string. How to fix: provide HTML template body.";
                }
                $generation = $doc['generation'] ?? null;
                if (!is_array($generation)) {
                    $errors[] = "{$path}.generation must be an object. How to fix: include auto/allowManual/allowRegen/lockAfterGen.";
                } else {
                    scheme_validate_allowed_keys($generation, ['auto', 'allowManual', 'allowRegen', 'lockAfterGen', 'x_meta'], $path . '.generation', $errors);
                    scheme_require_keys($generation, ['auto', 'allowManual', 'allowRegen', 'lockAfterGen'], $path . '.generation', $errors);
                    foreach (['auto', 'allowManual', 'allowRegen', 'lockAfterGen'] as $key) {
                        if (isset($generation[$key]) && !is_bool($generation[$key])) {
                            $errors[] = "{$path}.generation.{$key} must be true/false. How to fix: use boolean.";
                        }
                    }
                }
                $visibility = $doc['visibility'] ?? null;
                if (!is_array($visibility)) {
                    $errors[] = "{$path}.visibility must be an object. How to fix: include vendor/customerDownload/authorityOnly.";
                } else {
                    scheme_validate_allowed_keys($visibility, ['vendor', 'customerDownload', 'authorityOnly', 'x_meta'], $path . '.visibility', $errors);
                    scheme_require_keys($visibility, ['vendor', 'customerDownload', 'authorityOnly'], $path . '.visibility', $errors);
                    foreach (['vendor', 'customerDownload', 'authorityOnly'] as $key) {
                        if (isset($visibility[$key]) && !is_bool($visibility[$key])) {
                            $errors[] = "{$path}.visibility.{$key} must be true/false. How to fix: use boolean.";
                        }
                    }
                }
            }
            break;
        case 'workflows':
            scheme_validate_allowed_keys($payload, ['workflows', 'x_meta'], '', $errors);
            scheme_require_keys($payload, ['workflows'], '', $errors);
            $workflowPayload = $payload['workflows'] ?? null;
            if (!is_array($workflowPayload)) {
                $errors[] = 'workflows must be an array. How to fix: provide workflows array.';
                break;
            }
            $seen = [];
            foreach ($workflowPayload as $index => $entry) {
                $path = scheme_path_join('workflows', (string)$index, true);
                if (!is_array($entry)) {
                    $errors[] = "{$path} must be an object. How to fix: include packId and workflow.";
                    continue;
                }
                scheme_validate_allowed_keys($entry, ['packId', 'workflow', 'x_meta'], $path, $errors);
                scheme_require_keys($entry, ['packId', 'workflow'], $path, $errors);
                $packId = $entry['packId'] ?? '';
                if ($packId === '' || !is_string($packId)) {
                    $errors[] = "{$path}.packId must be a non-empty string. How to fix: add packId.";
                }
                if ($packId !== '') {
                    if (!in_array($packId, $packs, true)) {
                        $errors[] = "Unknown packId '{$packId}' in {$path}.packId. How to fix: use existing packId.";
                    }
                    $lower = strtolower($packId);
                    if (in_array($lower, $seen, true)) {
                        $errors[] = "Duplicate packId entry: {$packId}. How to fix: keep one workflow per pack.";
                    }
                    $seen[] = $lower;
                }
                $workflow = $entry['workflow'] ?? null;
                if (!is_array($workflow)) {
                    $errors[] = "{$path}.workflow must be an object. How to fix: provide workflow configuration.";
                } else {
                    scheme_validate_allowed_keys($workflow, ['enabled', 'states', 'transitions', 'defaultState', 'x_meta'], $path . '.workflow', $errors);
                    scheme_require_keys($workflow, ['enabled', 'states', 'transitions'], $path . '.workflow', $errors);
                    if (isset($workflow['enabled']) && !is_bool($workflow['enabled'])) {
                        $errors[] = "{$path}.workflow.enabled must be true/false. How to fix: use boolean.";
                    }
                    $states = $workflow['states'] ?? [];
                    if (!is_array($states)) {
                        $errors[] = "{$path}.workflow.states must be an array. How to fix: provide states list.";
                    }
                    $transitions = $workflow['transitions'] ?? [];
                    if (!is_array($transitions)) {
                        $errors[] = "{$path}.workflow.transitions must be an array. How to fix: provide transitions array.";
                    } else {
                        $packDocs = [];
                        foreach ($draft['packs'] ?? [] as $pack) {
                            if (($pack['packId'] ?? '') === $packId) {
                                $packDocs = $pack['documentIds'] ?? [];
                                break;
                            }
                        }
                        foreach ($transitions as $tIndex => $transition) {
                            $tPath = scheme_path_join($path . '.workflow.transitions', (string)$tIndex, true);
                            if (!is_array($transition)) {
                                $errors[] = "{$tPath} must be an object. How to fix: provide transition properties.";
                                continue;
                            }
                            scheme_validate_allowed_keys($transition, ['from', 'to', 'roles', 'requiredFields', 'requiredDocs', 'approval', 'x_meta'], $tPath, $errors);
                            scheme_require_keys($transition, ['from', 'to', 'roles', 'requiredFields', 'requiredDocs'], $tPath, $errors);
                            foreach (['from', 'to'] as $key) {
                                if (isset($transition[$key]) && !is_string($transition[$key])) {
                                    $errors[] = "{$tPath}.{$key} must be a string. How to fix: provide state name.";
                                }
                            }
                            foreach (['roles' => $roles, 'requiredFields' => $fields, 'requiredDocs' => $packDocs] as $listKey => $allowed) {
                                $items = $transition[$listKey] ?? [];
                                if (!is_array($items)) {
                                    $errors[] = "{$tPath}.{$listKey} must be an array. How to fix: provide list.";
                                    continue;
                                }
                                foreach ($items as $item) {
                                    if (!in_array($item, $allowed, true)) {
                                        $errors[] = "Unknown {$listKey} reference '{$item}' in {$tPath}.{$listKey}. How to fix: use existing values.";
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $packIdSet = array_map('strtolower', $packs);
            $payloadIds = array_map('strtolower', array_filter(array_map(fn($entry) => $entry['packId'] ?? '', $workflowPayload)));
            $missing = array_diff($packIdSet, $payloadIds);
            if (!empty($packIdSet) && !empty($missing)) {
                $errors[] = 'Missing workflow entries for packs: ' . implode(', ', $missing) . '. How to fix: include a workflow object for each pack.';
            }
            break;
        default:
            $errors[] = 'Unknown section selected. How to fix: choose a valid section.';
            break;
    }

    return $errors;
}

function scheme_snapshot_draft(string $schemeCode, array $draft): string
{
    $timestamp = now_kolkata()->format('Ymd_His');
    $dir = scheme_snapshots_dir($schemeCode);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $path = $dir . '/draft_before_apply_' . $timestamp . '.json';
    writeJsonAtomic($path, $draft);
    return $path;
}

function scheme_apply_section_payload(string $section, array $payload, array $draft): array
{
    switch ($section) {
        case 'overview':
            foreach (['name', 'description', 'caseLabel', 'toggles', 'status'] as $key) {
                if (array_key_exists($key, $payload)) {
                    $draft[$key] = $payload[$key];
                }
            }
            break;
        case 'roles':
            $draft['roles'] = $payload['roles'] ?? [];
            break;
        case 'modules':
            $draft['modules'] = $payload['modules'] ?? [];
            break;
        case 'fields':
            $draft['fieldDictionary'] = $payload['fieldDictionary'] ?? [];
            break;
        case 'packs':
            $draft['packs'] = $payload['packs'] ?? [];
            break;
        case 'documents':
            $draft['documents'] = $payload['documents'] ?? [];
            break;
        case 'workflows':
            $workflows = $payload['workflows'] ?? [];
            foreach ($workflows as $entry) {
                $packId = $entry['packId'] ?? '';
                if ($packId === '') {
                    continue;
                }
                $draft = scheme_update_pack_workflow($draft, $packId, $entry['workflow'] ?? []);
            }
            break;
    }
    return $draft;
}

function section_to_tab(string $section): string
{
    return match ($section) {
        'overview' => 'overview',
        'roles', 'modules' => 'case_roles',
        'fields' => 'dictionary',
        'packs' => 'packs',
        'documents' => 'documents',
        'workflows' => 'workflows',
        default => 'overview',
    };
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
