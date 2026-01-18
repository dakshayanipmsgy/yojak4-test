<?php
declare(strict_types=1);

function ensure_schemes_environment(): void
{
    $directories = [
        DATA_PATH . '/schemes',
        DATA_PATH . '/scheme_requests',
        DATA_PATH . '/customer_tokens',
        DATA_PATH . '/logs',
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $logFiles = [
        DATA_PATH . '/logs/schemes.log',
        DATA_PATH . '/logs/scheme_import.log',
        DATA_PATH . '/logs/customer_portal.log',
    ];

    foreach ($logFiles as $logFile) {
        if (!file_exists($logFile)) {
            touch($logFile);
        }
    }
}

function scheme_log_jsonl(string $path, array $payload): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $payload['at'] = now_kolkata()->format(DateTime::ATOM);
    $line = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $handle = fopen($path, 'a');
    if ($handle) {
        flock($handle, LOCK_EX);
        fwrite($handle, $line . PHP_EOL);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function scheme_log_import(string $schemeId, string $event, array $errors = []): void
{
    scheme_log_jsonl(DATA_PATH . '/logs/scheme_import.log', [
        'schemeId' => $schemeId,
        'event' => $event,
        'errors' => $errors,
    ]);
}

function scheme_log_usage(string $yojId, string $schemeId, string $event, array $context = []): void
{
    scheme_log_jsonl(DATA_PATH . '/logs/schemes.log', array_merge([
        'yojId' => $yojId,
        'schemeId' => $schemeId,
        'event' => $event,
    ], $context));
}

function scheme_log_portal(array $context): void
{
    scheme_log_jsonl(DATA_PATH . '/logs/customer_portal.log', $context);
}

function scheme_root_path(): string
{
    return DATA_PATH . '/schemes';
}

function scheme_path(string $schemeId): string
{
    return scheme_root_path() . '/' . $schemeId;
}

function scheme_metadata_path(string $schemeId): string
{
    return scheme_path($schemeId) . '/scheme.json';
}

function scheme_definition_path(string $schemeId): string
{
    return scheme_compiled_definition_path($schemeId);
}

function scheme_compiled_definition_path(string $schemeId): string
{
    return scheme_path($schemeId) . '/compiled_definition.json';
}

function scheme_sections_path(string $schemeId): string
{
    return scheme_path($schemeId) . '/sections';
}

function scheme_sections_index_path(string $schemeId): string
{
    return scheme_sections_path($schemeId) . '/index.json';
}

function scheme_sections_index(string $schemeId): array
{
    $path = scheme_sections_index_path($schemeId);
    $data = readJson($path);
    return is_array($data) ? $data : [];
}

function scheme_sections_write_index(string $schemeId, array $entries): void
{
    writeJsonAtomic(scheme_sections_index_path($schemeId), array_values($entries));
}

function scheme_section_filename(string $order, string $sectionId, string $title): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title)));
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $sectionId)));
        $slug = trim($slug, '-');
    }
    if ($slug === '') {
        $slug = 'section';
    }
    return $order . '_' . $slug . '.json';
}

function scheme_sections_sort_key(string $order): string
{
    if (preg_match('/SEC-(\d+)/i', $order, $matches)) {
        return str_pad($matches[1], 6, '0', STR_PAD_LEFT);
    }
    return $order;
}

function scheme_sections_sorted(array $entries): array
{
    usort($entries, function ($a, $b) {
        return strcmp(scheme_sections_sort_key((string)($a['order'] ?? '')), scheme_sections_sort_key((string)($b['order'] ?? '')));
    });
    return $entries;
}

function scheme_section_path(string $schemeId, string $filename): string
{
    return scheme_sections_path($schemeId) . '/' . $filename;
}

function scheme_sections_next_order(array $entries): string
{
    $max = 0;
    foreach ($entries as $entry) {
        if (preg_match('/SEC-(\d+)/i', (string)($entry['order'] ?? ''), $matches)) {
            $max = max($max, (int)$matches[1]);
        }
    }
    return 'SEC-' . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
}

function scheme_sections_payloads(string $schemeId, ?string $excludeSectionId = null, bool $enabledOnly = true): array
{
    $payloads = [];
    $entries = scheme_sections_index($schemeId);
    foreach ($entries as $entry) {
        if ($excludeSectionId && ($entry['sectionId'] ?? '') === $excludeSectionId) {
            continue;
        }
        if ($enabledOnly && !($entry['enabled'] ?? true)) {
            continue;
        }
        $filename = $entry['file'] ?? '';
        if (!$filename) {
            continue;
        }
        $payload = readJson(scheme_section_path($schemeId, $filename));
        if ($payload) {
            $payloads[] = $payload;
        }
    }
    return $payloads;
}

function scheme_template_set_path(string $schemeId, string $templateSetId): string
{
    return scheme_path($schemeId) . '/template_sets/' . $templateSetId . '.json';
}

function scheme_template_sets(string $schemeId): array
{
    $dir = scheme_path($schemeId) . '/template_sets';
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.json') ?: [];
    $sets = [];
    foreach ($files as $file) {
        $payload = readJson($file);
        if ($payload) {
            $sets[] = $payload;
        }
    }
    return $sets;
}

function scheme_list_all(): array
{
    $root = scheme_root_path();
    if (!is_dir($root)) {
        return [];
    }
    $entries = [];
    foreach (scandir($root) as $dir) {
        if ($dir === '.' || $dir === '..') {
            continue;
        }
        $metaPath = scheme_metadata_path($dir);
        if (file_exists($metaPath)) {
            $entries[] = readJson($metaPath);
        }
    }
    return $entries;
}

function scheme_list_published(): array
{
    return array_values(array_filter(scheme_list_all(), fn($entry) => ($entry['status'] ?? '') === 'published'));
}

function scheme_load_metadata(string $schemeId): ?array
{
    $path = scheme_metadata_path($schemeId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function scheme_load_definition(string $schemeId): ?array
{
    $path = scheme_compiled_definition_path($schemeId);
    if (!file_exists($path)) {
        scheme_log_import($schemeId, 'COMPILE_MISSING', ['Compiled definition missing.']);
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function scheme_create_shell(string $schemeId, string $name, string $shortDescription, string $category, array $createdBy): array
{
    $now = now_kolkata()->format(DateTime::ATOM);
    $payload = [
        'schemeId' => $schemeId,
        'name' => $name,
        'shortDescription' => $shortDescription,
        'category' => $category,
        'version' => 1,
        'status' => 'draft',
        'createdAt' => $now,
        'updatedAt' => $now,
        'createdBy' => $createdBy,
    ];

    writeJsonAtomic(scheme_metadata_path($schemeId), $payload);

    return $payload;
}

function scheme_update_metadata(string $schemeId, array $updates): void
{
    $metadata = scheme_load_metadata($schemeId) ?? [];
    $metadata = array_merge($metadata, $updates);
    $metadata['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(scheme_metadata_path($schemeId), $metadata);
}

function scheme_access_path(string $yojId, string $schemeId): string
{
    return DATA_PATH . '/contractors/approved/' . $yojId . '/schemes/' . $schemeId . '/access.json';
}

function scheme_access_record(string $yojId, string $schemeId): ?array
{
    $path = scheme_access_path($yojId, $schemeId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function scheme_has_access(string $yojId, string $schemeId): bool
{
    $access = scheme_access_record($yojId, $schemeId);
    return !empty($access) && ($access['enabled'] ?? false);
}

function scheme_request_path(string $requestId): string
{
    return DATA_PATH . '/scheme_requests/' . $requestId . '.json';
}

function scheme_generate_request_id(): string
{
    $stamp = now_kolkata()->format('YmdHis');
    $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    return 'REQ-' . $stamp . '-' . $rand;
}

function scheme_request_access(string $schemeId, string $yojId): array
{
    $requestId = scheme_generate_request_id();
    $payload = [
        'requestId' => $requestId,
        'schemeId' => $schemeId,
        'yojId' => $yojId,
        'status' => 'pending',
        'requestedAt' => now_kolkata()->format(DateTime::ATOM),
        'decidedAt' => null,
        'decidedBy' => null,
        'notes' => '',
    ];
    writeJsonAtomic(scheme_request_path($requestId), $payload);
    return $payload;
}

function scheme_requests_all(): array
{
    $dir = DATA_PATH . '/scheme_requests';
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.json') ?: [];
    $records = [];
    foreach ($files as $file) {
        $data = readJson($file);
        if ($data) {
            $records[] = $data;
        }
    }
    return $records;
}

function scheme_pending_requests(): array
{
    return array_values(array_filter(scheme_requests_all(), fn($req) => ($req['status'] ?? '') === 'pending'));
}

function scheme_update_request(string $requestId, array $updates): ?array
{
    $path = scheme_request_path($requestId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    if (!$data) {
        return null;
    }
    $data = array_merge($data, $updates);
    writeJsonAtomic($path, $data);
    return $data;
}

function scheme_record_root(string $yojId, string $schemeId, string $entity): string
{
    return DATA_PATH . '/contractors/approved/' . $yojId . '/schemes/' . $schemeId . '/records/' . $entity;
}

function scheme_record_path(string $yojId, string $schemeId, string $entity, string $recordId): string
{
    return scheme_record_root($yojId, $schemeId, $entity) . '/' . $recordId . '.json';
}

function scheme_load_records(string $yojId, string $schemeId, string $entity): array
{
    $dir = scheme_record_root($yojId, $schemeId, $entity);
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.json') ?: [];
    $records = [];
    foreach ($files as $file) {
        $data = readJson($file);
        if ($data) {
            $records[] = $data;
        }
    }
    usort($records, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));
    return $records;
}

function scheme_load_record(string $yojId, string $schemeId, string $entity, string $recordId): ?array
{
    $path = scheme_record_path($yojId, $schemeId, $entity, $recordId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function scheme_save_record(string $yojId, string $schemeId, string $entity, array $record): void
{
    $dir = scheme_record_root($yojId, $schemeId, $entity);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    writeJsonAtomic(scheme_record_path($yojId, $schemeId, $entity, $record['recordId']), $record);
}

function scheme_generate_record_id(string $prefix): string
{
    $stamp = now_kolkata()->format('ymdHis');
    $rand = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    return $prefix . '-' . $stamp . '-' . $rand;
}

function scheme_customer_token_path(string $token): string
{
    return DATA_PATH . '/customer_tokens/' . $token . '.json';
}

function scheme_generate_customer_token(): string
{
    return bin2hex(random_bytes(16));
}

function scheme_store_customer_token(array $payload): array
{
    $token = $payload['token'] ?? scheme_generate_customer_token();
    $payload['token'] = $token;
    writeJsonAtomic(scheme_customer_token_path($token), $payload);
    return $payload;
}

function scheme_load_customer_token(string $token): ?array
{
    $path = scheme_customer_token_path($token);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function scheme_revoke_customer_token(string $token, string $actor): bool
{
    $data = scheme_load_customer_token($token);
    if (!$data) {
        return false;
    }
    $data['revokedAt'] = now_kolkata()->format(DateTime::ATOM);
    $data['revokedBy'] = $actor;
    $data['revoked'] = true;
    writeJsonAtomic(scheme_customer_token_path($token), $data);
    return true;
}

function scheme_get_path_value(array $data, string $path)
{
    $segments = explode('.', $path);
    $current = $data;
    foreach ($segments as $segment) {
        if (!is_array($current) || !array_key_exists($segment, $current)) {
            return null;
        }
        $current = $current[$segment];
    }
    return $current;
}

function scheme_set_path_value(array &$data, string $path, $value): void
{
    $segments = explode('.', $path);
    $current =& $data;
    foreach ($segments as $segment) {
        if (!isset($current[$segment]) || !is_array($current[$segment])) {
            $current[$segment] = [];
        }
        $current =& $current[$segment];
    }
    $current = $value;
}

function scheme_label_from_key(string $key): string
{
    $label = str_replace(['_', '.'], ' ', $key);
    $label = preg_replace('/\s+/', ' ', $label);
    return ucwords(trim($label));
}

function scheme_normalize_column_key(string $label): string
{
    $key = strtolower(trim($label));
    $key = preg_replace('/[^a-z0-9]+/', '_', $key);
    $key = trim($key, '_');
    return $key !== '' ? $key : 'col';
}

function scheme_normalize_field_catalog_entries($fieldCatalog, array &$catalogKeys): array
{
    $catalogKeys = [];
    $normalizedCatalog = [];
    if (!is_array($fieldCatalog)) {
        return [];
    }
    foreach ($fieldCatalog as $entry) {
        if (is_string($entry)) {
            $key = trim($entry);
            if ($key === '') {
                continue;
            }
            $entry = ['key' => $key, 'label' => scheme_label_from_key($key)];
        }
        if (!is_array($entry)) {
            continue;
        }
        $key = trim((string)($entry['key'] ?? ''));
        if ($key === '') {
            continue;
        }
        $entry['label'] = $entry['label'] ?? scheme_label_from_key($key);
        $normalizedCatalog[] = $entry;
        $catalogKeys[$key] = true;
    }

    return $normalizedCatalog;
}

function scheme_validate_definition(array $payload, array &$normalized, array &$warnings): array
{
    $errors = [];
    $warnings = [];
    $normalized = $payload;

    $required = ['engineVersion', 'schemeId', 'entities', 'workflow', 'fieldCatalog', 'documents', 'recordTemplates', 'customerPortal', 'rules'];
    foreach ($required as $key) {
        if (!array_key_exists($key, $payload)) {
            $errors[] = "Missing required key: {$key}";
        }
    }

    if ($errors) {
        return $errors;
    }

    if (!is_array($payload['entities'])) {
        $errors[] = 'Entities must be an array.';
    }

    if (!is_array($payload['documents'])) {
        $errors[] = 'Documents must be an array.';
    }

    $fieldCatalog = $payload['fieldCatalog'];
    if (!is_array($fieldCatalog)) {
        $errors[] = 'Field catalog must be an array.';
        $fieldCatalog = [];
    }

    $catalogKeys = [];
    $normalized['fieldCatalog'] = scheme_normalize_field_catalog_entries($fieldCatalog, $catalogKeys);

    $entityKeys = [];
    foreach ($payload['entities'] as $entity) {
        if (!is_array($entity)) {
            $errors[] = 'Entity definition must be object.';
            continue;
        }
        $key = $entity['key'] ?? null;
        if (!$key || !is_string($key)) {
            $errors[] = 'Entity key is required.';
            continue;
        }
        if (isset($entityKeys[$key])) {
            $errors[] = "Duplicate entity key: {$key}";
        }
        $entityKeys[$key] = true;
    }

    $docIds = [];
    $referencedFields = [];
    foreach ($payload['documents'] as $index => $doc) {
        if (!is_array($doc)) {
            $errors[] = 'Document entry must be object.';
            continue;
        }
        $docId = $doc['docId'] ?? null;
        if (!$docId || !is_string($docId)) {
            $errors[] = 'Document docId is required.';
            continue;
        }
        if (isset($docIds[$docId])) {
            $errors[] = "Duplicate document id: {$docId}";
        }
        $docIds[$docId] = true;

        $body = (string)($doc['body'] ?? '');
        $placeholders = scheme_extract_placeholders($body);
        foreach ($placeholders['invalid'] as $invalid) {
            $errors[] = "Invalid placeholder syntax in {$docId}: {{$invalid}}";
        }
        foreach ($placeholders['fields'] as $fieldKey) {
            $referencedFields[$fieldKey] = true;
        }

        $tables = $doc['tables'] ?? [];
        if ($tables && !is_array($tables)) {
            $errors[] = "Tables for document {$docId} must be an array.";
            $tables = [];
        }
        foreach ($tables as $tIndex => $table) {
            if (!is_array($table)) {
                $errors[] = "Table definition in {$docId} must be object.";
                continue;
            }
            $tableId = $table['tableId'] ?? '';
            if (!$tableId) {
                $errors[] = "TableId missing in {$docId}.";
                continue;
            }
            $columns = $table['columns'] ?? [];
            if (!is_array($columns)) {
                $errors[] = "Columns for table {$tableId} must be an array.";
                continue;
            }
            foreach ($columns as $cIndex => $column) {
                if (!is_array($column)) {
                    $errors[] = "Column definition in {$tableId} must be object.";
                    continue;
                }
                if (empty($column['key'])) {
                    $label = $column['label'] ?? 'Column';
                    $column['key'] = scheme_normalize_column_key((string)$label);
                    $tables[$tIndex]['columns'][$cIndex]['key'] = $column['key'];
                }
            }
        }
        $normalized['documents'][$index]['tables'] = $tables;
    }

    $missingFields = [];
    foreach (array_keys($referencedFields) as $fieldKey) {
        if (!isset($catalogKeys[$fieldKey])) {
            $missingFields[] = $fieldKey;
            $normalized['fieldCatalog'][] = [
                'key' => $fieldKey,
                'label' => scheme_label_from_key($fieldKey),
                'source' => 'auto',
            ];
            $catalogKeys[$fieldKey] = true;
        }
    }

    if ($missingFields) {
        $warnings[] = 'Auto-added missing field keys: ' . implode(', ', $missingFields);
    }

    return $errors;
}

function scheme_collect_section_component_keys(array $sections): array
{
    $entities = [];
    $fields = [];
    $tables = [];
    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }
        $components = $section['components'] ?? [];
        if (!is_array($components)) {
            continue;
        }
        $entityList = $components['entities'] ?? [];
        if (is_array($entityList)) {
            foreach ($entityList as $entity) {
                if (is_array($entity) && !empty($entity['key'])) {
                    $entities[(string)$entity['key']] = true;
                }
            }
        }
        $fieldCatalog = $components['fieldCatalog'] ?? [];
        if (is_array($fieldCatalog)) {
            foreach ($fieldCatalog as $entry) {
                if (is_string($entry)) {
                    $key = trim($entry);
                    if ($key !== '') {
                        $fields[$key] = true;
                    }
                } elseif (is_array($entry) && !empty($entry['key'])) {
                    $fields[(string)$entry['key']] = true;
                }
            }
        }
        $recordTemplates = $components['recordTemplates'] ?? [];
        if (is_array($recordTemplates)) {
            $isAssoc = array_keys($recordTemplates) !== range(0, count($recordTemplates) - 1);
            if ($isAssoc) {
                foreach ($recordTemplates as $tableId => $template) {
                    if ($tableId !== '') {
                        $tables[(string)$tableId] = true;
                    }
                }
            } else {
                foreach ($recordTemplates as $template) {
                    if (is_array($template) && !empty($template['tableId'])) {
                        $tables[(string)$template['tableId']] = true;
                    }
                }
            }
        }
    }
    return [
        'entities' => $entities,
        'fields' => $fields,
        'tables' => $tables,
    ];
}

function scheme_normalize_document_tables(array $tables, array &$errors, string $docId): array
{
    foreach ($tables as $tIndex => $table) {
        if (!is_array($table)) {
            $errors[] = "Table definition in {$docId} must be object.";
            continue;
        }
        $tableId = $table['tableId'] ?? '';
        if (!$tableId) {
            $errors[] = "TableId missing in {$docId}.";
            continue;
        }
        $columns = $table['columns'] ?? [];
        if (!is_array($columns)) {
            $errors[] = "Columns for table {$tableId} must be an array.";
            continue;
        }
        foreach ($columns as $cIndex => $column) {
            if (!is_array($column)) {
                $errors[] = "Column definition in {$tableId} must be object.";
                continue;
            }
            if (empty($column['key'])) {
                $label = $column['label'] ?? 'Column';
                $column['key'] = scheme_normalize_column_key((string)$label);
                $tables[$tIndex]['columns'][$cIndex]['key'] = $column['key'];
            }
        }
    }
    return $tables;
}

function scheme_validate_section(array $payload, string $schemeId, array $availableKeys, array &$normalized, array &$warnings): array
{
    $errors = [];
    $warnings = [];
    $normalized = $payload;

    $required = ['sectionVersion', 'sectionId', 'schemeId', 'title', 'description', 'mode', 'components'];
    foreach ($required as $key) {
        if (!array_key_exists($key, $payload)) {
            $errors[] = "Missing required key: {$key}";
        }
    }
    if ($errors) {
        return $errors;
    }
    if (($payload['schemeId'] ?? '') !== $schemeId) {
        $errors[] = 'Scheme ID mismatch in payload.';
    }
    if (!is_array($payload['components'])) {
        $errors[] = 'Components must be an object.';
        return $errors;
    }

    $components = $payload['components'];
    $entities = $components['entities'] ?? [];
    if ($entities && !is_array($entities)) {
        $errors[] = 'Entities must be an array.';
        $entities = [];
    }
    $entityKeys = $availableKeys['entities'] ?? [];
    foreach ($entities as $entity) {
        if (!is_array($entity)) {
            $errors[] = 'Entity definition must be object.';
            continue;
        }
        $key = $entity['key'] ?? null;
        if (!$key || !is_string($key)) {
            $errors[] = 'Entity key is required.';
            continue;
        }
        $entityKeys[$key] = true;
    }

    $fieldCatalog = $components['fieldCatalog'] ?? [];
    if ($fieldCatalog && !is_array($fieldCatalog)) {
        $errors[] = 'Field catalog must be an array.';
        $fieldCatalog = [];
    }
    $catalogKeys = [];
    $normalizedCatalog = scheme_normalize_field_catalog_entries($fieldCatalog, $catalogKeys);
    $normalized['components']['fieldCatalog'] = $normalizedCatalog;

    $recordTemplates = $components['recordTemplates'] ?? [];
    if ($recordTemplates && !is_array($recordTemplates)) {
        $errors[] = 'Record templates must be an object.';
        $recordTemplates = [];
    }
    $recordTemplateKeys = $availableKeys['tables'] ?? [];
    if (is_array($recordTemplates)) {
        $isAssoc = array_keys($recordTemplates) !== range(0, count($recordTemplates) - 1);
        if ($isAssoc) {
            foreach ($recordTemplates as $tableId => $template) {
                if ($tableId !== '') {
                    $recordTemplateKeys[(string)$tableId] = true;
                }
            }
        } else {
            foreach ($recordTemplates as $template) {
                if (is_array($template) && !empty($template['tableId'])) {
                    $recordTemplateKeys[(string)$template['tableId']] = true;
                }
            }
        }
    }

    $documents = $components['documents'] ?? [];
    if ($documents && !is_array($documents)) {
        $errors[] = 'Documents must be an array.';
        $documents = [];
    }
    $docIds = [];
    foreach ($documents as $index => $doc) {
        if (!is_array($doc)) {
            $errors[] = 'Document entry must be object.';
            continue;
        }
        $docId = $doc['docId'] ?? null;
        if (!$docId || !is_string($docId)) {
            $errors[] = 'Document docId is required.';
            continue;
        }
        if (isset($docIds[$docId])) {
            $errors[] = "Duplicate document id: {$docId}";
        }
        $docIds[$docId] = true;

        $attachTo = (string)($doc['attachToEntity'] ?? '');
        if ($attachTo !== '' && !isset($entityKeys[$attachTo])) {
            $errors[] = "{$docId} requires entity '{$attachTo}' which is not defined in enabled sections. Import Base section first.";
        }

        $body = (string)($doc['body'] ?? '');
        $placeholders = scheme_extract_placeholders($body);
        foreach ($placeholders['invalid'] as $invalid) {
            $errors[] = "Invalid placeholder syntax in {$docId}: {{$invalid}}";
        }
        foreach ($placeholders['fields'] as $fieldKey) {
            if (!isset($catalogKeys[$fieldKey]) && !isset(($availableKeys['fields'] ?? [])[$fieldKey])) {
                $errors[] = "{$docId} requires field '{$fieldKey}' which is not defined in enabled sections.";
            }
        }
        foreach ($placeholders['tables'] as $tableId) {
            if (!isset($recordTemplateKeys[$tableId])) {
                $errors[] = "{$docId} requires table '{$tableId}' which is not defined in enabled sections.";
            }
        }

        $tables = $doc['tables'] ?? [];
        if ($tables && !is_array($tables)) {
            $errors[] = "Tables for document {$docId} must be an array.";
            $tables = [];
        }
        $tables = scheme_normalize_document_tables($tables, $errors, $docId);
        $normalized['components']['documents'][$index]['tables'] = $tables;
    }

    $workflow = $components['workflow'] ?? null;
    if ($workflow !== null && !is_array($workflow)) {
        $errors[] = 'Workflow must be an object.';
    }

    $portalPatch = $components['customerPortalPatch'] ?? null;
    if ($portalPatch !== null && !is_array($portalPatch)) {
        $errors[] = 'Customer portal patch must be an object.';
    }

    $rulesPatch = $components['rulesPatch'] ?? null;
    if ($rulesPatch !== null && !is_array($rulesPatch)) {
        $errors[] = 'Rules patch must be an object.';
    }

    return $errors;
}

function scheme_merge_unique_list(array $left, array $right): array
{
    $merged = array_values(array_unique(array_merge($left, $right)));
    return $merged;
}

function scheme_merge_entity(array $existing, array $incoming, string $strategy): array
{
    if ($strategy === 'replace') {
        return $incoming;
    }
    $merged = array_merge($existing, $incoming);
    $merged['statuses'] = scheme_merge_unique_list(
        is_array($existing['statuses'] ?? null) ? $existing['statuses'] : [],
        is_array($incoming['statuses'] ?? null) ? $incoming['statuses'] : []
    );
    $merged['fields'] = scheme_merge_unique_list(
        is_array($existing['fields'] ?? null) ? $existing['fields'] : [],
        is_array($incoming['fields'] ?? null) ? $incoming['fields'] : []
    );
    return $merged;
}

function scheme_merge_record_template(array $existing, array $incoming): array
{
    $merged = array_merge($existing, $incoming);
    $columns = [];
    $existingCols = $existing['columns'] ?? [];
    $incomingCols = $incoming['columns'] ?? [];
    if (is_array($existingCols)) {
        foreach ($existingCols as $col) {
            if (is_array($col) && !empty($col['key'])) {
                $columns[$col['key']] = $col;
            }
        }
    }
    if (is_array($incomingCols)) {
        foreach ($incomingCols as $col) {
            if (is_array($col) && !empty($col['key'])) {
                $columns[$col['key']] = $col;
            }
        }
    }
    if ($columns) {
        $merged['columns'] = array_values($columns);
    }
    return $merged;
}

function scheme_merge_document(array $existing, array $incoming): array
{
    $merged = array_merge($existing, $incoming);
    if (!is_array($incoming['tables'] ?? null)) {
        return $merged;
    }
    $tables = [];
    foreach (($existing['tables'] ?? []) as $table) {
        if (is_array($table) && !empty($table['tableId'])) {
            $tables[$table['tableId']] = $table;
        }
    }
    foreach ($incoming['tables'] as $table) {
        if (is_array($table) && !empty($table['tableId'])) {
            $tables[$table['tableId']] = $table;
        }
    }
    $merged['tables'] = array_values($tables);
    return $merged;
}

function scheme_compile_definition(string $schemeId, array &$errors, array &$warnings): ?array
{
    $errors = [];
    $warnings = [];
    $sections = scheme_sections_sorted(scheme_sections_index($schemeId));
    $enabledSections = array_values(array_filter($sections, fn($entry) => ($entry['enabled'] ?? true)));
    if (!$enabledSections) {
        $errors[] = 'No enabled sections found.';
        return null;
    }

    $compiled = [
        'engineVersion' => 1,
        'schemeId' => $schemeId,
        'entities' => [],
        'workflow' => [
            'transitions' => [],
            'milestones' => [],
        ],
        'fieldCatalog' => [],
        'documents' => [],
        'recordTemplates' => [],
        'customerPortal' => null,
        'rules' => [
            'notes' => [],
        ],
    ];

    $entityMap = [];
    $entityOrder = [];
    $documentMap = [];
    $documentOrder = [];
    $fieldMap = [];
    $fieldOrder = [];
    $recordTemplateMap = [];
    $recordTemplateOrder = [];
    $transitionMap = [];
    $milestoneMap = [];
    $portal = null;
    $rules = [
        'notes' => [],
    ];

    foreach ($enabledSections as $sectionMeta) {
        $filename = $sectionMeta['file'] ?? '';
        if (!$filename) {
            $errors[] = 'Section file missing in metadata.';
            continue;
        }
        $section = readJson(scheme_section_path($schemeId, $filename));
        if (!$section) {
            $errors[] = "Section file {$filename} could not be read.";
            continue;
        }
        $components = $section['components'] ?? [];
        if (!is_array($components)) {
            continue;
        }
        $sectionMode = (string)($section['mode'] ?? '');

        $entities = $components['entities'] ?? [];
        if (is_array($entities)) {
            foreach ($entities as $entity) {
                if (!is_array($entity) || empty($entity['key'])) {
                    continue;
                }
                $key = (string)$entity['key'];
                $strategy = (string)($entity['mergeStrategy'] ?? 'merge');
                if ($sectionMode === 'override') {
                    $strategy = 'replace';
                }
                if (!isset($entityMap[$key])) {
                    $entityMap[$key] = $entity;
                    $entityOrder[] = $key;
                } else {
                    $entityMap[$key] = scheme_merge_entity($entityMap[$key], $entity, $strategy === 'replace' ? 'replace' : 'merge');
                }
            }
        }

        $fieldCatalog = $components['fieldCatalog'] ?? [];
        $catalogKeys = [];
        $normalizedCatalog = scheme_normalize_field_catalog_entries($fieldCatalog, $catalogKeys);
        foreach ($normalizedCatalog as $entry) {
            $key = (string)$entry['key'];
            if (!isset($fieldMap[$key])) {
                $fieldOrder[] = $key;
            }
            $fieldMap[$key] = $entry;
        }

        $recordTemplates = $components['recordTemplates'] ?? [];
        if (is_array($recordTemplates)) {
            $isAssoc = array_keys($recordTemplates) !== range(0, count($recordTemplates) - 1);
            if ($isAssoc) {
                foreach ($recordTemplates as $tableId => $template) {
                    if (!is_array($template)) {
                        continue;
                    }
                    $mergeStrategy = (string)($template['mergeStrategy'] ?? '');
                    if (!isset($recordTemplateMap[$tableId])) {
                        $recordTemplateOrder[] = (string)$tableId;
                        $recordTemplateMap[$tableId] = $template;
                    } else {
                        $recordTemplateMap[$tableId] = $mergeStrategy === 'merge'
                            ? scheme_merge_record_template($recordTemplateMap[$tableId], $template)
                            : $template;
                    }
                }
            } else {
                foreach ($recordTemplates as $template) {
                    if (!is_array($template) || empty($template['tableId'])) {
                        continue;
                    }
                    $tableId = (string)$template['tableId'];
                    $mergeStrategy = (string)($template['mergeStrategy'] ?? '');
                    if (!isset($recordTemplateMap[$tableId])) {
                        $recordTemplateOrder[] = $tableId;
                        $recordTemplateMap[$tableId] = $template;
                    } else {
                        $recordTemplateMap[$tableId] = $mergeStrategy === 'merge'
                            ? scheme_merge_record_template($recordTemplateMap[$tableId], $template)
                            : $template;
                    }
                }
            }
        }

        $documents = $components['documents'] ?? [];
        if (is_array($documents)) {
            foreach ($documents as $doc) {
                if (!is_array($doc) || empty($doc['docId'])) {
                    continue;
                }
                $docId = (string)$doc['docId'];
                $mergeStrategy = (string)($doc['mergeStrategy'] ?? '');
                if (!isset($documentMap[$docId])) {
                    $documentOrder[] = $docId;
                    $documentMap[$docId] = $doc;
                } else {
                    $documentMap[$docId] = $mergeStrategy === 'merge'
                        ? scheme_merge_document($documentMap[$docId], $doc)
                        : $doc;
                }
            }
        }

        $workflow = $components['workflow'] ?? [];
        if (is_array($workflow)) {
            $transitions = $workflow['transitions'] ?? [];
            if (is_array($transitions)) {
                foreach ($transitions as $transition) {
                    if (!is_array($transition)) {
                        continue;
                    }
                    $from = (string)($transition['from'] ?? '');
                    $action = (string)($transition['action'] ?? '');
                    $to = (string)($transition['to'] ?? '');
                    $key = $from . '|' . $action . '|' . $to;
                    if ($key !== '||') {
                        $transitionMap[$key] = $transition;
                    }
                }
            }
            $milestones = $workflow['milestones'] ?? [];
            if (is_array($milestones)) {
                foreach ($milestones as $milestone) {
                    if (!is_array($milestone) || empty($milestone['key'])) {
                        continue;
                    }
                    $milestoneKey = (string)$milestone['key'];
                    $milestoneMap[$milestoneKey] = $milestone;
                }
            }
            foreach ($workflow as $wfKey => $wfValue) {
                if (in_array($wfKey, ['transitions', 'milestones'], true)) {
                    continue;
                }
                $compiled['workflow'][$wfKey] = $wfValue;
            }
        }

        if (isset($components['customerPortal']) && is_array($components['customerPortal'])) {
            $portal = $components['customerPortal'];
        }

        $portalPatch = $components['customerPortalPatch'] ?? null;
        if ($portalPatch && is_array($portalPatch)) {
            if (!$portal) {
                $portal = [
                    'enabled' => false,
                    'visibleDocs' => [],
                    'accessMode' => 'token',
                    'tokenTTLdays' => 365,
                ];
            }
            $add = $portalPatch['visibleDocsAdd'] ?? [];
            if (is_array($add)) {
                $portal['visibleDocs'] = scheme_merge_unique_list($portal['visibleDocs'] ?? [], $add);
            }
            $remove = $portalPatch['visibleDocsRemove'] ?? [];
            if (is_array($remove)) {
                $portal['visibleDocs'] = array_values(array_diff($portal['visibleDocs'] ?? [], $remove));
            }
            if (isset($portalPatch['tokenTTLdays'])) {
                $portal['tokenTTLdays'] = (int)$portalPatch['tokenTTLdays'];
            }
        }

        if (isset($components['rules']) && is_array($components['rules'])) {
            $rules = $components['rules'];
        }
        $rulesPatch = $components['rulesPatch'] ?? null;
        if ($rulesPatch && is_array($rulesPatch)) {
            $add = $rulesPatch['notesAdd'] ?? [];
            if (is_array($add)) {
                $rules['notes'] = scheme_merge_unique_list($rules['notes'] ?? [], $add);
            }
            $remove = $rulesPatch['notesRemove'] ?? [];
            if (is_array($remove)) {
                $rules['notes'] = array_values(array_diff($rules['notes'] ?? [], $remove));
            }
        }
    }

    $compiled['entities'] = array_map(fn($key) => $entityMap[$key], $entityOrder);
    $compiled['fieldCatalog'] = array_map(fn($key) => $fieldMap[$key], $fieldOrder);
    $compiled['recordTemplates'] = [];
    foreach ($recordTemplateOrder as $tableId) {
        $compiled['recordTemplates'][$tableId] = $recordTemplateMap[$tableId];
    }
    $compiled['documents'] = array_map(fn($key) => $documentMap[$key], $documentOrder);
    $compiled['workflow']['transitions'] = array_values($transitionMap);
    $compiled['workflow']['milestones'] = array_values($milestoneMap);
    $compiled['customerPortal'] = $portal ?? [
        'enabled' => false,
        'visibleDocs' => [],
        'accessMode' => 'token',
        'tokenTTLdays' => 365,
    ];
    $compiled['rules'] = $rules;

    return $compiled;
}

function scheme_extract_placeholders(string $body): array
{
    $matches = [];
    preg_match_all('/{{\s*([^}]+)\s*}}/', $body, $matches);
    $fields = [];
    $tables = [];
    $invalid = [];
    foreach ($matches[1] as $token) {
        if (preg_match('/^(field|table):([a-zA-Z0-9_.\-]+)$/', trim($token), $parts)) {
            if ($parts[1] === 'field') {
                $fields[] = $parts[2];
            } else {
                $tables[] = $parts[2];
            }
        } else {
            $invalid[] = $token;
        }
    }
    return [
        'fields' => $fields,
        'tables' => $tables,
        'invalid' => $invalid,
    ];
}

function scheme_field_value(array $record, array $contractor, string $key): string
{
    $value = null;
    if (str_starts_with($key, 'vendor.') || str_starts_with($key, 'contractor.')) {
        $mapping = [
            'vendor.firm.name' => 'firmName',
            'vendor.firm.type' => 'firmType',
            'vendor.firm.address' => 'address',
            'vendor.firm.address_line1' => 'addressLine1',
            'vendor.firm.address_line2' => 'addressLine2',
            'vendor.firm.state' => 'state',
            'vendor.firm.district' => 'district',
            'vendor.firm.pincode' => 'pincode',
            'vendor.contact.mobile' => 'mobile',
            'vendor.contact.email' => 'email',
            'vendor.gst' => 'gstNumber',
            'vendor.pan' => 'panNumber',
            'vendor.bank.account' => 'bankAccount',
            'vendor.bank.ifsc' => 'ifsc',
            'vendor.bank.name' => 'bankName',
            'vendor.authorized_signatory' => 'authorizedSignatoryName',
        ];
        $keyMap = $mapping[$key] ?? null;
        if ($keyMap) {
            $value = $contractor[$keyMap] ?? null;
        }
    }

    if ($value === null) {
        $value = scheme_get_path_value($record['data'] ?? [], $key);
    }

    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_SLASHES);
    }
    return trim((string)($value ?? ''));
}

function scheme_render_table(array $table, array $record): string
{
    $tableId = (string)($table['tableId'] ?? '');
    $rows = $record['tables'][$tableId] ?? [];
    if (!$rows && $tableId !== 'items') {
        $rows = $record['tables']['items'] ?? [];
    }
    if (!is_array($rows)) {
        $rows = [];
    }

    $columns = $table['columns'] ?? [];
    if (!is_array($columns)) {
        $columns = [];
    }

    ob_start();
    ?>
    <table class="doc-table">
        <thead>
        <tr>
            <?php foreach ($columns as $column): ?>
                <th><?= sanitize((string)($column['label'] ?? $column['key'] ?? '')); ?></th>
            <?php endforeach; ?>
        </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr>
                <td colspan="<?= max(1, count($columns)); ?>" class="muted">No rows</td>
            </tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <?php foreach ($columns as $column): ?>
                    <td><?= sanitize((string)($row[$column['key']] ?? '')); ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return (string)ob_get_clean();
}

function scheme_render_document_html(array $definition, array $doc, array $record, array $contractor): string
{
    $body = (string)($doc['body'] ?? '');
    $tables = is_array($doc['tables'] ?? null) ? $doc['tables'] : [];

    $body = preg_replace_callback('/{{\s*field:([a-zA-Z0-9_.\-]+)\s*}}/', function ($matches) use ($record, $contractor) {
        return sanitize(scheme_field_value($record, $contractor, $matches[1]));
    }, $body);

    $body = preg_replace_callback('/{{\s*table:([a-zA-Z0-9_.\-]+)\s*}}/', function ($matches) use ($tables, $record) {
        foreach ($tables as $table) {
            if (($table['tableId'] ?? '') === $matches[1]) {
                return scheme_render_table($table, $record);
            }
        }
        return '';
    }, $body);

    return $body;
}

function require_scheme_builder(): array
{
    $user = current_user();
    if (!$user) {
        redirect('/auth/login.php');
    }
    if (($user['type'] ?? '') === 'superadmin') {
        return $user;
    }
    if (($user['type'] ?? '') === 'employee' && in_array('scheme_builder', $user['permissions'] ?? [], true)) {
        return $user;
    }
    render_error_page('Unauthorized');
    exit;
}

function require_scheme_approver(): array
{
    $user = current_user();
    if (!$user) {
        redirect('/auth/login.php');
    }
    if (($user['type'] ?? '') !== 'superadmin') {
        render_error_page('Unauthorized');
        exit;
    }
    return $user;
}
