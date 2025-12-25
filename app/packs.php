<?php
declare(strict_types=1);

const PACKS_LOG = DATA_PATH . '/logs/packs.log';

function packs_root(string $yojId): string
{
    return contractors_approved_path($yojId) . '/packs';
}

function packs_upload_root(string $yojId): string
{
    return PUBLIC_PATH . '/uploads/contractors/' . $yojId . '/packs';
}

function packs_index_path(string $yojId): string
{
    return packs_root($yojId) . '/index.json';
}

function ensure_packs_env(string $yojId): void
{
    $directories = [
        packs_root($yojId),
        packs_upload_root($yojId),
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    if (!file_exists(packs_index_path($yojId))) {
        writeJsonAtomic(packs_index_path($yojId), []);
    }

    if (!file_exists(PACKS_LOG)) {
        touch(PACKS_LOG);
    }
}

function packs_index(string $yojId): array
{
    $index = readJson(packs_index_path($yojId));
    return is_array($index) ? array_values($index) : [];
}

function save_packs_index(string $yojId, array $entries): void
{
    writeJsonAtomic(packs_index_path($yojId), array_values($entries));
}

function pack_dir(string $yojId, string $packId): string
{
    return packs_root($yojId) . '/' . $packId;
}

function pack_path(string $yojId, string $packId): string
{
    return pack_dir($yojId, $packId) . '/pack.json';
}

function pack_upload_dir(string $yojId, string $packId, ?string $itemId = null): string
{
    $base = packs_upload_root($yojId) . '/' . $packId . '/items';
    if ($itemId !== null) {
        $base .= '/' . $itemId;
    }
    return $base;
}

function pack_generated_dir(string $yojId, string $packId): string
{
    return packs_upload_root($yojId) . '/' . $packId . '/generated';
}

function generate_pack_id(string $yojId): string
{
    ensure_packs_env($yojId);
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $candidate = 'PACK-' . $suffix;
    } while (file_exists(pack_path($yojId, $candidate)));

    return $candidate;
}

function load_pack(string $yojId, string $packId): ?array
{
    $path = pack_path($yojId, $packId);
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

function save_pack(array $pack): void
{
    if (empty($pack['packId']) || empty($pack['yojId'])) {
        throw new InvalidArgumentException('Pack id or contractor id missing');
    }

    ensure_packs_env($pack['yojId']);

    $pack['status'] = resolve_pack_status($pack);
    $pack['updatedAt'] = $pack['updatedAt'] ?? now_kolkata()->format(DateTime::ATOM);

    $path = pack_path($pack['yojId'], $pack['packId']);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    writeJsonAtomic($path, $pack);

    $index = packs_index($pack['yojId']);
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

    save_packs_index($pack['yojId'], $index);
}

function find_pack_by_source(string $yojId, string $type, string $sourceId): ?array
{
    foreach (packs_index($yojId) as $entry) {
        $source = $entry['sourceTender'] ?? [];
        if (($source['type'] ?? '') === $type && ($source['id'] ?? '') === $sourceId) {
            return load_pack($yojId, $entry['packId']);
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
