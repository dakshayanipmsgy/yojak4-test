<?php
declare(strict_types=1);

const PACKS_LOG = DATA_PATH . '/logs/packs.log';

function packs_root(string $yojId, string $context = 'tender'): string
{
    return contractors_approved_path($yojId) . ($context === 'workorder' ? '/packs_workorder' : '/packs');
}

function packs_upload_root(string $yojId, string $context = 'tender'): string
{
    return PUBLIC_PATH . '/uploads/contractors/' . $yojId . ($context === 'workorder' ? '/packs_workorder' : '/packs');
}

function packs_index_path(string $yojId, string $context = 'tender'): string
{
    return packs_root($yojId, $context) . '/index.json';
}

function detect_pack_context(string $packId): string
{
    return str_starts_with($packId, 'WOPK-') ? 'workorder' : 'tender';
}

function ensure_packs_env(string $yojId, string $context = 'tender'): void
{
    $directories = [
        packs_root($yojId, $context),
        packs_upload_root($yojId, $context),
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    if (!file_exists(packs_index_path($yojId, $context))) {
        writeJsonAtomic(packs_index_path($yojId, $context), []);
    }

    if (!file_exists(PACKS_LOG)) {
        touch(PACKS_LOG);
    }
}

function packs_index(string $yojId, string $context = 'tender'): array
{
    $index = readJson(packs_index_path($yojId, $context));
    return is_array($index) ? array_values($index) : [];
}

function save_packs_index(string $yojId, array $entries, string $context = 'tender'): void
{
    writeJsonAtomic(packs_index_path($yojId, $context), array_values($entries));
}

function pack_dir(string $yojId, string $packId, string $context = 'tender'): string
{
    return packs_root($yojId, $context) . '/' . $packId;
}

function pack_path(string $yojId, string $packId, string $context = 'tender'): string
{
    return pack_dir($yojId, $packId, $context) . '/pack.json';
}

function pack_upload_dir(string $yojId, string $packId, ?string $itemId = null, string $context = 'tender'): string
{
    $base = packs_upload_root($yojId, $context) . '/' . $packId . '/items';
    if ($itemId !== null) {
        $base .= '/' . $itemId;
    }
    return $base;
}

function pack_generated_dir(string $yojId, string $packId, string $context = 'tender'): string
{
    return packs_upload_root($yojId, $context) . '/' . $packId . '/generated';
}

function generate_pack_id(string $yojId, string $context = 'tender'): string
{
    ensure_packs_env($yojId, $context);
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $prefix = $context === 'workorder' ? 'WOPK-' : 'PACK-';
        $candidate = $prefix . $suffix;
    } while (file_exists(pack_path($yojId, $candidate, $context)));

    return $candidate;
}

function load_pack(string $yojId, string $packId, string $context = 'tender'): ?array
{
    $path = pack_path($yojId, $packId, $context);
    if (!file_exists($path) && $context === 'tender') {
        $altContext = detect_pack_context($packId);
        $path = pack_path($yojId, $packId, $altContext);
        $context = $altContext;
    }
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function generate_pack_item_id(): string
{
    return 'PIT-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
}

function pack_log(array $context): void
{
    logEvent(PACKS_LOG, $context);
}

function pack_items_from_checklist(array $checklist): array
{
    $items = [];
    foreach ($checklist as $item) {
        if (count($items) >= 300) {
            break;
        }
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $items[] = [
            'itemId' => $item['itemId'] ?? generate_pack_item_id(),
            'title' => $title,
            'description' => trim((string)($item['description'] ?? '')),
            'required' => (bool)($item['required'] ?? true),
            'status' => in_array($item['status'] ?? '', ['pending', 'uploaded', 'generated', 'done'], true) ? $item['status'] : 'pending',
            'fileRefs' => [],
        ];
    }

    if (!$items) {
        $items[] = [
            'itemId' => generate_pack_item_id(),
            'title' => 'Signed cover letter',
            'description' => 'Upload scanned copy of signed covering letter.',
            'required' => true,
            'status' => 'pending',
            'fileRefs' => [],
        ];
        $items[] = [
            'itemId' => generate_pack_item_id(),
            'title' => 'Undertaking on company letterhead',
            'description' => 'Self-declaration/undertaking to accompany the pack.',
            'required' => true,
            'status' => 'pending',
            'fileRefs' => [],
        ];
    }

    return $items;
}

function pack_items_from_requirement_set(array $set): array
{
    $items = [];
    foreach ($set['items'] ?? [] as $item) {
        if (count($items) >= 300) {
            break;
        }
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $items[] = [
            'itemId' => generate_pack_item_id(),
            'title' => $title,
            'description' => trim((string)($item['description'] ?? '')),
            'required' => (bool)($item['required'] ?? true),
            'status' => 'pending',
            'fileRefs' => [],
        ];
    }
    if (!$items) {
        return pack_items_from_checklist([]);
    }
    return $items;
}

function pack_apply_default_templates(array $pack, array $tender, array $contractor, string $context = 'tender'): array
{
    $templates = load_contractor_templates_full($pack['yojId']);
    $defaults = array_filter($templates, fn($tpl) => ($tpl['category'] ?? 'tender') === 'tender');
    if (!$defaults) {
        return $pack;
    }

    $existingTemplateDocs = [];
    foreach ($pack['generatedDocs'] ?? [] as $doc) {
        if (!empty($doc['templateId'])) {
            $existingTemplateDocs[$doc['templateId']] = true;
        }
    }

    $generatedDir = pack_generated_dir($pack['yojId'], $pack['packId'], $context);
    $defaultsDir = $generatedDir . '/default_letters';
    if (!is_dir($defaultsDir)) {
        mkdir($defaultsDir, 0775, true);
    }

    $contextMap = contractor_template_context($contractor, $tender);
    $now = now_kolkata()->format(DateTime::ATOM);
    $docs = $pack['generatedDocs'] ?? [];

    foreach ($defaults as $tpl) {
        $tplId = $tpl['tplId'] ?? '';
        if ($tplId !== '' && isset($existingTemplateDocs[$tplId])) {
            continue;
        }
        $docId = 'DOC-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        $filename = $docId . '.html';
        $path = $defaultsDir . '/' . $filename;
        $filled = contractor_fill_template_body($tpl['body'] ?? '', $contextMap);
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'
            . htmlspecialchars($tpl['name'] ?? 'Template')
            . '</title><style>body{font-family:Arial,sans-serif;background:#0d1117;color:#e6edf3;padding:24px;}h1{margin-top:0;color:#fff;}p,pre{line-height:1.6;white-space:pre-wrap;}</style></head><body>'
            . '<h1>' . htmlspecialchars($tpl['name'] ?? 'Template') . '</h1>'
            . '<pre>' . htmlspecialchars($filled) . '</pre>'
            . '</body></html>';
        file_put_contents($path, $html);
        $docs[] = [
            'docId' => $docId,
            'title' => $tpl['name'] ?? 'Tender letter',
            'path' => str_replace(PUBLIC_PATH, '', $path),
            'generatedAt' => $now,
            'templateId' => $tplId,
        ];
    }

    $pack['generatedDocs'] = $docs;
    $pack['defaultTemplatesApplied'] = true;
    return $pack;
}

function pack_stats(array $pack): array
{
    $items = $pack['items'] ?? [];
    $required = array_filter($items, fn($i) => !empty($i['required']));
    $doneRequired = array_filter($required, fn($i) => ($i['status'] ?? '') === 'done');
    $uploadedRequired = array_filter($required, fn($i) => in_array($i['status'] ?? '', ['uploaded', 'generated', 'done'], true));
    $pendingRequired = array_filter($required, fn($i) => ($i['status'] ?? '') === 'pending');

    return [
        'totalItems' => count($items),
        'requiredItems' => count($required),
        'doneRequired' => count($doneRequired),
        'uploadedRequired' => count($uploadedRequired),
        'pendingRequired' => count($pendingRequired),
        'generatedDocs' => count($pack['generatedDocs'] ?? []),
    ];
}

function resolve_pack_status(array $pack): string
{
    $stats = pack_stats($pack);
    if ($stats['requiredItems'] > 0 && $stats['doneRequired'] >= $stats['requiredItems']) {
        return 'Completed';
    }
    if ($stats['generatedDocs'] > 0) {
        return 'Generated';
    }
    if ($stats['requiredItems'] > 0 && $stats['pendingRequired'] === 0) {
        return 'Uploaded';
    }
    return 'Pending';
}

function pack_progress_percent(array $pack): int
{
    $stats = pack_stats($pack);
    if ($stats['requiredItems'] === 0) {
        return 0;
    }
    return (int)round(($stats['doneRequired'] / max(1, $stats['requiredItems'])) * 100);
}

function pack_summary(array $pack): array
{
    $stats = pack_stats($pack);
    return [
        'packId' => $pack['packId'],
        'title' => $pack['title'] ?? 'Tender Pack',
        'sourceTender' => $pack['sourceTender'] ?? null,
        'status' => resolve_pack_status($pack),
        'createdAt' => $pack['createdAt'] ?? null,
        'updatedAt' => $pack['updatedAt'] ?? null,
        'requiredItems' => $stats['requiredItems'],
        'doneRequired' => $stats['doneRequired'],
        'generatedDocs' => $stats['generatedDocs'],
    ];
}

function save_pack(array $pack, string $context = 'tender'): void
{
    if (empty($pack['packId']) || empty($pack['yojId'])) {
        throw new InvalidArgumentException('Pack id or contractor id missing');
    }

    ensure_packs_env($pack['yojId'], $context);

    $pack['status'] = resolve_pack_status($pack);
    $pack['updatedAt'] = $pack['updatedAt'] ?? now_kolkata()->format(DateTime::ATOM);

    $path = pack_path($pack['yojId'], $pack['packId'], $context);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    writeJsonAtomic($path, $pack);

    $index = packs_index($pack['yojId'], $context);
    $summary = pack_summary($pack);
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['packId'] ?? '') === $pack['packId']) {
            $entry = $summary;
            $found = true;
            break;
        }
    }
    unset($entry);

    if (!$found) {
        $index[] = $summary;
    }

    save_packs_index($pack['yojId'], $index, $context);
}

function find_pack_by_source(string $yojId, string $type, string $sourceId, string $context = 'tender'): ?array
{
    foreach (packs_index($yojId, $context) as $entry) {
        $source = $entry['sourceTender'] ?? [];
        if (($source['type'] ?? '') === $type && ($source['id'] ?? '') === $sourceId) {
            return load_pack($yojId, $entry['packId'], $context);
        }
    }
    return null;
}

function pack_item_by_id(array $pack, string $itemId): ?array
{
    foreach ($pack['items'] ?? [] as $item) {
        if (($item['itemId'] ?? '') === $itemId) {
            return $item;
        }
    }
    return null;
}

function safe_pack_filename(string $original, string $fallbackExt): string
{
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($original));
    if ($name === '' || $name === '.' || $name === '..') {
        $name = 'document_' . strtolower(bin2hex(random_bytes(4))) . '.' . $fallbackExt;
    }
    return $name;
}

function is_path_within(string $path, string $base): bool
{
    $realPath = realpath($path);
    $realBase = realpath($base);
    if ($realPath === false || $realBase === false) {
        return false;
    }
    return str_starts_with($realPath, $realBase);
}

function pack_signed_token(string $packId, string $yojId): string
{
    $secret = $_SESSION['csrf_token'] ?? '';
    return hash_hmac('sha256', $packId . '|' . $yojId, $secret);
}

function verify_pack_token(string $packId, string $yojId, string $token): bool
{
    if ($token === '') {
        return false;
    }
    $expected = pack_signed_token($packId, $yojId);
    return hash_equals($expected, $token);
}

function pack_index_html(array $pack): string
{
    $stats = pack_stats($pack);
    $lines = [];
    $lines[] = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Pack ' . htmlspecialchars($pack['packId']) . '</title>';
    $lines[] = '<style>body{font-family:Arial,sans-serif;background:#0d1117;color:#e6edf3;padding:20px;}h1,h2{margin:0 0 8px;}table{width:100%;border-collapse:collapse;}th,td{padding:8px;border-bottom:1px solid #30363d;text-align:left;}th{color:#8b949e;text-transform:uppercase;font-size:12px;letter-spacing:0.04em;} .muted{color:#8b949e;}</style></head><body>';
    $lines[] = '<h1>Pack ' . htmlspecialchars($pack['packId']) . '</h1>';
    $lines[] = '<p class="muted">Status: ' . htmlspecialchars($pack['status'] ?? 'Pending') . ' • Created: ' . htmlspecialchars($pack['createdAt'] ?? '') . '</p>';
    $lines[] = '<p>Required items done: ' . $stats['doneRequired'] . ' / ' . $stats['requiredItems'] . '</p>';
    $lines[] = '<h2>Items</h2><table><thead><tr><th>Title</th><th>Required</th><th>Status</th><th>Files</th></tr></thead><tbody>';
    foreach ($pack['items'] ?? [] as $item) {
        $lines[] = '<tr><td>' . htmlspecialchars($item['title'] ?? '') . '</td><td>' . (!empty($item['required']) ? 'Yes' : 'Optional') . '</td><td>' . htmlspecialchars(ucfirst($item['status'] ?? 'pending')) . '</td><td>' . count($item['fileRefs'] ?? []) . '</td></tr>';
    }
    $lines[] = '</tbody></table>';
    $lines[] = '<h2>Generated docs</h2><ul>';
    foreach ($pack['generatedDocs'] ?? [] as $doc) {
        $lines[] = '<li>' . htmlspecialchars($doc['title'] ?? ($doc['docId'] ?? 'Doc')) . ' • ' . htmlspecialchars($doc['generatedAt'] ?? '') . '</li>';
    }
    if (empty($pack['generatedDocs'])) {
        $lines[] = '<li class="muted">None yet.</li>';
    }
    $lines[] = '</ul></body></html>';
    return implode('', $lines);
}
