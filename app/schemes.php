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
    return scheme_path($schemeId) . '/definition.json';
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
    $path = scheme_definition_path($schemeId);
    if (!file_exists($path)) {
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

    $normalizedCatalog = [];
    $catalogKeys = [];
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

    $normalized['fieldCatalog'] = $normalizedCatalog;

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
