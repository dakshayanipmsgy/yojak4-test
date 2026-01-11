<?php
declare(strict_types=1);

const PACKS_LOG = DATA_PATH . '/logs/packs.log';
const PACK_PRINT_LOG = DATA_PATH . '/logs/print.log';

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

function pack_annexures_dir(string $yojId, string $packId, string $context = 'tender'): string
{
    return pack_dir($yojId, $packId, $context) . '/annexures';
}

function pack_annexure_index_path(string $yojId, string $packId, string $context = 'tender'): string
{
    return pack_annexures_dir($yojId, $packId, $context) . '/index.json';
}

function pack_annexure_path(string $yojId, string $packId, string $annexId, string $context = 'tender'): string
{
    return pack_annexures_dir($yojId, $packId, $context) . '/' . $annexId . '.json';
}

function ensure_pack_annexure_env(string $yojId, string $packId, string $context = 'tender'): void
{
    $dir = pack_annexures_dir($yojId, $packId, $context);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $indexPath = pack_annexure_index_path($yojId, $packId, $context);
    if (!file_exists($indexPath)) {
        writeJsonAtomic($indexPath, []);
    }
}

function load_pack_annexure_index(string $yojId, string $packId, string $context = 'tender'): array
{
    ensure_pack_annexure_env($yojId, $packId, $context);
    $index = readJson(pack_annexure_index_path($yojId, $packId, $context));
    return is_array($index) ? array_values($index) : [];
}

function save_pack_annexure_index(string $yojId, string $packId, array $records, string $context = 'tender'): void
{
    ensure_pack_annexure_env($yojId, $packId, $context);
    writeJsonAtomic(pack_annexure_index_path($yojId, $packId, $context), array_values($records));
}

function load_pack_annexures(string $yojId, string $packId, string $context = 'tender'): array
{
    $annexures = [];
    foreach (load_pack_annexure_index($yojId, $packId, $context) as $entry) {
        $annexId = $entry['annexId'] ?? '';
        if ($annexId === '') {
            continue;
        }
        $path = pack_annexure_path($yojId, $packId, $annexId, $context);
        $data = readJson($path);
        if (!$data) {
            continue;
        }
        $annexures[] = array_merge($entry, $data);
    }
    return $annexures;
}

function contractor_print_settings_path(string $yojId): string
{
    return contractors_approved_path($yojId) . '/print_settings.json';
}

function default_print_settings(): array
{
    return [
        'headerEnabled' => false,
        'headerText' => '',
        'footerEnabled' => false,
        'footerText' => '',
        'logoEnabled' => false,
        'logoPublicPath' => null,
        'logoAlign' => 'left',
        'updatedAt' => now_kolkata()->format(DateTime::ATOM),
    ];
}

function load_contractor_print_settings(string $yojId): array
{
    $path = contractor_print_settings_path($yojId);
    if (!file_exists($path)) {
        return default_print_settings();
    }
    $data = readJson($path);
    $defaults = default_print_settings();
    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $data)) {
            $data[$key] = $value;
        }
    }
    if (!array_key_exists('logoPublicPath', $data) && array_key_exists('logoPathPublic', $data)) {
        $data['logoPublicPath'] = $data['logoPathPublic'];
    }
    if (!in_array($data['logoAlign'] ?? 'left', ['left', 'center', 'right'], true)) {
        $data['logoAlign'] = 'left';
    }
    return $data;
}

function save_contractor_print_settings(string $yojId, array $settings): void
{
    $defaults = default_print_settings();
    $merged = array_merge($defaults, $settings);
    $merged['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
    writeJsonAtomic(contractor_print_settings_path($yojId), $merged);
}

function pack_infer_category(string $title): string
{
    $title = strtolower($title);
    $categories = [
        'eligibility' => ['license', 'gst', 'pan', 'experience', 'turnover', 'registration'],
        'forms' => ['form', 'affidavit', 'undertaking', 'declaration'],
        'technical' => ['technical', 'specification', 'methodology'],
        'submission' => ['submission', 'upload', 'cover'],
        'declarations' => ['declaration', 'self', 'undertaking'],
    ];

    foreach ($categories as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (str_contains($title, $keyword)) {
                return ucfirst($category);
            }
        }
    }

    return 'Other';
}

function pack_sync_checklist(array $pack): array
{
    $items = is_array($pack['items'] ?? null) ? $pack['items'] : [];
    $existing = is_array($pack['checklist'] ?? null) ? $pack['checklist'] : [];

    $itemMap = [];
    foreach ($items as $item) {
        $id = $item['itemId'] ?? generate_pack_item_id();
        $itemMap[$id] = $item;
    }

    $final = [];
    $seen = [];
    foreach ($existing as $entry) {
        $id = $entry['itemId'] ?? $entry['id'] ?? generate_pack_item_id();
        $title = trim((string)($entry['title'] ?? $entry['name'] ?? ''));
        if ($title === '') {
            continue;
        }
        $itemStatus = $itemMap[$id]['status'] ?? null;
        $final[] = [
            'id' => $id,
            'itemId' => $id,
            'title' => $title,
            'description' => trim((string)($entry['description'] ?? '')),
            'required' => (bool)($entry['required'] ?? true),
            'status' => $itemStatus ?: (in_array($entry['status'] ?? '', ['pending', 'uploaded', 'generated', 'done'], true) ? $entry['status'] : 'pending'),
            'notes' => trim((string)($entry['notes'] ?? '')),
            'sourceSnippet' => trim((string)($entry['sourceSnippet'] ?? '')),
            'category' => $entry['category'] ?? pack_infer_category($title),
        ];
        $seen[$id] = true;
    }

    foreach ($items as $item) {
        $id = $item['itemId'] ?? generate_pack_item_id();
        if (isset($seen[$id])) {
            continue;
        }
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $final[] = [
            'id' => $id,
            'itemId' => $id,
            'title' => $title,
            'description' => trim((string)($item['description'] ?? '')),
            'required' => (bool)($item['required'] ?? true),
            'status' => in_array($item['status'] ?? '', ['pending', 'uploaded', 'generated', 'done'], true) ? $item['status'] : 'pending',
            'notes' => trim((string)($item['notes'] ?? '')),
            'sourceSnippet' => trim((string)($item['sourceSnippet'] ?? '')),
            'category' => $item['category'] ?? pack_infer_category($title),
        ];
    }

    return array_values($final);
}

function pack_apply_schema_defaults(array $pack): array
{
    $pack['packId'] = $pack['packId'] ?? ($pack['id'] ?? '');
    $pack['yojId'] = $pack['yojId'] ?? '';
    $pack['source'] = $pack['source'] ?? ($pack['sourceTender']['source'] ?? 'offline');
    $pack['tenderTitle'] = $pack['tenderTitle'] ?? ($pack['title'] ?? 'Tender Pack');
    $pack['tenderNumber'] = $pack['tenderNumber'] ?? ($pack['sourceTender']['id'] ?? '');
    if (!isset($pack['dates']) || !is_array($pack['dates'])) {
        $pack['dates'] = [];
    }
    foreach (['submission', 'opening', 'prebid'] as $dateKey) {
        if (!array_key_exists($dateKey, $pack['dates'])) {
            $pack['dates'][$dateKey] = '';
        }
    }
    $pack['departmentName'] = $pack['departmentName']
        ?? ($pack['deptName'] ?? ($pack['sourceTender']['departmentName'] ?? ($pack['sourceTender']['deptName'] ?? '')));
    $pack['deptName'] = $pack['deptName'] ?? $pack['departmentName'];
    $pack['submissionDeadline'] = $pack['submissionDeadline']
        ?? ($pack['dates']['submission'] ?? ($pack['sourceTender']['submissionDeadline'] ?? ($pack['sourceTender']['extracted']['submissionDeadline'] ?? '')));
    $pack['openingDate'] = $pack['openingDate']
        ?? ($pack['dates']['opening'] ?? ($pack['sourceTender']['openingDate'] ?? ($pack['sourceTender']['extracted']['openingDate'] ?? '')));
    $pack['completionMonths'] = $pack['completionMonths']
        ?? ($pack['sourceTender']['completionMonths'] ?? ($pack['sourceTender']['extracted']['completionMonths'] ?? null));
    $pack['bidValidityDays'] = $pack['bidValidityDays']
        ?? ($pack['sourceTender']['bidValidityDays'] ?? ($pack['sourceTender']['extracted']['bidValidityDays'] ?? null));
    if ($pack['dates']['submission'] === '' && !empty($pack['submissionDeadline'])) {
        $pack['dates']['submission'] = $pack['submissionDeadline'];
    }
    if ($pack['dates']['opening'] === '' && !empty($pack['openingDate'])) {
        $pack['dates']['opening'] = $pack['openingDate'];
    }

    $pack['checklist'] = pack_sync_checklist($pack);
    if (!isset($pack['items']) || !is_array($pack['items'])) {
        $pack['items'] = pack_items_from_checklist($pack['checklist']);
    } else {
        $statusMap = [];
        foreach ($pack['checklist'] as $entry) {
            $statusMap[$entry['itemId']] = $entry['status'] ?? 'pending';
        }
        foreach ($pack['items'] as &$item) {
            $id = $item['itemId'] ?? null;
            if ($id !== null && isset($statusMap[$id])) {
                $item['status'] = $statusMap[$id];
            }
        }
        unset($item);
    }
    if (!isset($pack['annexures']) || !is_array($pack['annexures'])) {
        $pack['annexures'] = [];
    }
    if (!isset($pack['annexureList']) || !is_array($pack['annexureList'])) {
        $pack['annexureList'] = [];
    }
    if (!isset($pack['formats']) || !is_array($pack['formats'])) {
        $pack['formats'] = [];
    }
    if (!isset($pack['restrictedAnnexures']) || !is_array($pack['restrictedAnnexures'])) {
        $pack['restrictedAnnexures'] = [];
    }
    if (!isset($pack['generatedAnnexures']) || !is_array($pack['generatedAnnexures'])) {
        $pack['generatedAnnexures'] = [];
    }
    if (!isset($pack['generatedTemplates']) || !is_array($pack['generatedTemplates'])) {
        $pack['generatedTemplates'] = [];
    }
    if (!isset($pack['vaultMappings']) || !is_array($pack['vaultMappings'])) {
        $pack['vaultMappings'] = [];
    }
    if (!isset($pack['attachmentsPlan']) || !is_array($pack['attachmentsPlan'])) {
        $pack['attachmentsPlan'] = [];
    }
    if (!isset($pack['missingChecklistItemIds']) || !is_array($pack['missingChecklistItemIds'])) {
        $pack['missingChecklistItemIds'] = [];
    }

    return $pack;
}

function pack_tender_context(array $pack): array
{
    $dates = $pack['dates'] ?? [];
    return [
        'title' => $pack['tenderTitle'] ?? ($pack['title'] ?? 'Tender'),
        'tenderTitle' => $pack['tenderTitle'] ?? ($pack['title'] ?? 'Tender'),
        'id' => $pack['tenderNumber'] ?? ($pack['sourceTender']['id'] ?? ''),
        'tenderNumber' => $pack['tenderNumber'] ?? ($pack['sourceTender']['id'] ?? ''),
        'departmentName' => $pack['departmentName'] ?? ($pack['deptName'] ?? ''),
        'location' => $pack['departmentName'] ?? ($pack['deptName'] ?? ''),
        'extracted' => [
            'submissionDeadline' => $dates['submission'] ?? '',
            'openingDate' => $dates['opening'] ?? '',
        ],
    ];
}

function pack_template_payloads(array $pack, array $contractor): array
{
    $templates = load_contractor_templates_full($pack['yojId']);
    $contextMap = contractor_template_context($contractor, pack_tender_context($pack));
    foreach ($contextMap as $key => $value) {
        if (trim((string)$value) === '') {
            $contextMap[$key] = '__________';
        }
    }
    $generatedMap = [];
    foreach ($pack['generatedTemplates'] ?? [] as $gen) {
        $tplId = $gen['tplId'] ?? '';
        if ($tplId !== '') {
            $generatedMap[$tplId] = $gen;
        }
    }

    $payloads = [];
    foreach ($templates as $tpl) {
        if (count($payloads) >= 50) {
            break;
        }
        $category = $tpl['category'] ?? 'tender';
        if (!in_array($category, ['tender', 'workorder', 'general'], true)) {
            continue;
        }
        $tplId = $tpl['tplId'] ?? '';
        $payloads[] = [
            'tplId' => $tplId,
            'name' => $tpl['name'] ?? 'Template',
            'body' => contractor_fill_template_body($tpl['body'] ?? '', $contextMap),
            'rawBody' => $tpl['body'] ?? '',
            'lastGeneratedAt' => $generatedMap[$tplId]['lastGeneratedAt'] ?? null,
            'storedPath' => $generatedMap[$tplId]['storedPath'] ?? null,
        ];
    }
    return $payloads;
}

function pack_vault_doc_type(array $file): string
{
    $docType = trim((string)($file['docType'] ?? $file['category'] ?? ''));
    return $docType !== '' ? $docType : 'Other';
}

function pack_vault_suggestions(array $pack, array $vaultFiles, array $attachments = []): array
{
    $suggestions = [];
    $vaultMap = [];
    foreach ($vaultFiles as $file) {
        if (!empty($file['deletedAt'])) {
            continue;
        }
        $file['docType'] = pack_vault_doc_type($file);
        $file['tags'] = array_values(array_filter(array_map('strval', $file['tags'] ?? [])));
        $vaultMap[$file['fileId'] ?? ''] = $file;
    }

    foreach ($pack['checklist'] ?? [] as $item) {
        $itemId = $item['itemId'] ?? $item['id'] ?? null;
        $title = strtolower(trim((string)($item['title'] ?? '')));
        if ($itemId === null || $title === '') {
            continue;
        }
        if (!empty($attachments[$itemId])) {
            continue;
        }

        $ruleMap = [
            'GST' => ['gst'],
            'PAN' => ['pan'],
            'ITR' => ['itr', 'income tax'],
            'BalanceSheet' => ['balance sheet', 'balance-sheet'],
            'Affidavit' => ['affidavit', 'undertaking'],
            'ExperienceCert' => ['experience', 'work experience', 'completion certificate'],
        ];
        $targetDocTypes = [];
        foreach ($ruleMap as $docType => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($title, $keyword)) {
                    $targetDocTypes[] = $docType;
                    break;
                }
            }
        }
        $targetDocTypes = array_values(array_unique($targetDocTypes));

        $matches = [];
        foreach ($vaultMap as $file) {
            $score = 0.0;
            $reasons = [];
            $fileDocType = strtoupper(pack_vault_doc_type($file));
            $fileTitle = strtolower((string)($file['title'] ?? ''));
            $fileTags = array_map('strtolower', $file['tags'] ?? []);

            foreach ($targetDocTypes as $docType) {
                if ($fileDocType === strtoupper($docType)
                    || ($docType === 'Affidavit' && in_array($fileDocType, ['AFFIDAVIT', 'UNDERTAKING'], true))) {
                    $score += 3.0;
                    $reasons[] = 'Doc type match: ' . $docType;
                    break;
                }
            }

            foreach ($ruleMap as $docType => $keywords) {
                foreach ($keywords as $keyword) {
                    if ($fileTitle !== '' && str_contains($fileTitle, $keyword)) {
                        $score += 1.5;
                        $reasons[] = 'Title match: ' . strtoupper($keyword);
                        break 2;
                    }
                    foreach ($fileTags as $tag) {
                        if ($tag !== '' && str_contains($tag, str_replace(' ', '', $keyword))) {
                            $score += 2.0;
                            $reasons[] = 'Tag match: ' . strtoupper($keyword);
                            break 3;
                        }
                    }
                }
            }

            if ($score <= 0) {
                continue;
            }
            $matches[] = [
                'checklistItemId' => $itemId,
                'suggestedVaultDocId' => $file['fileId'] ?? '',
                'fileTitle' => $file['title'] ?? '',
                'docType' => $file['docType'] ?? '',
                'confidenceScore' => $score,
                'confidenceLabel' => $score >= 4 ? 'High' : 'Medium',
                'reason' => $reasons ? implode(' • ', array_unique($reasons)) : 'Rule-based match',
            ];
        }

        if ($matches) {
            usort($matches, static function (array $a, array $b): int {
                return $b['confidenceScore'] <=> $a['confidenceScore'];
            });
            $suggestions[$itemId] = array_slice($matches, 0, 3);
        }
    }

    return $suggestions;
}

function pack_attachment_map(array $pack, array $vaultFiles = []): array
{
    $vaultMap = [];
    foreach ($vaultFiles as $file) {
        $vaultMap[$file['fileId'] ?? ''] = $file;
    }

    $attachments = [];
    foreach ($pack['attachmentsPlan'] ?? [] as $entry) {
        $itemId = $entry['checklistItemId'] ?? '';
        $docId = $entry['vaultDocId'] ?? '';
        if ($itemId === '' || $docId === '') {
            continue;
        }
        $title = $entry['fileName'] ?? ($vaultMap[$docId]['title'] ?? 'Vault document');
        $attachments[$itemId] = [
            'fileId' => $docId,
            'title' => $title,
            'attachedAt' => $entry['attachedAt'] ?? null,
            'reason' => 'Attached from vault',
        ];
    }

    if (!$attachments && !empty($pack['vaultMappings'])) {
        foreach ($pack['vaultMappings'] ?? [] as $map) {
            $itemId = $map['checklistItemId'] ?? '';
            $docId = $map['suggestedVaultDocId'] ?? '';
            if ($itemId === '' || $docId === '') {
                continue;
            }
            $attachments[$itemId] = [
                'fileId' => $docId,
                'title' => $map['fileTitle'] ?? ($vaultMap[$docId]['title'] ?? 'Vault document'),
                'reason' => $map['reason'] ?? 'Previously mapped',
                'confidence' => $map['confidence'] ?? null,
            ];
        }
    }

    return $attachments;
}

function pack_missing_checklist_item_ids(array $pack, array $attachments = []): array
{
    $missing = [];
    foreach ($pack['items'] ?? [] as $item) {
        $itemId = $item['itemId'] ?? ($item['id'] ?? '');
        if ($itemId === '') {
            continue;
        }
        if (empty($item['required'])) {
            continue;
        }
        if (($item['status'] ?? 'pending') !== 'pending') {
            continue;
        }
        if (!empty($attachments[$itemId])) {
            continue;
        }
        $missing[] = $itemId;
    }
    return array_values(array_unique($missing));
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
    return $data ? pack_apply_schema_defaults($data) : null;
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
            'category' => $item['category'] ?? pack_infer_category($title),
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
            'category' => $item['category'] ?? pack_infer_category($title),
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

function contractor_prefill_value(array $contractor, string $key, int $minLength = 8): string
{
    $value = trim((string)($contractor[$key] ?? ''));
    return $value === '' ? str_repeat('_', $minLength) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function pack_annexure_template_library(): array
{
    $now = now_kolkata()->format(DateTime::ATOM);
    return [
        'cover_letter_fee' => [
            'type' => 'cover_letter',
            'title' => 'Covering Letter for Tender Fee Submission',
            'body' => "To,\n{{department_name}}\nSubject: Submission of tender documents for {{tender_title}} ({{tender_number}})\n\nRespected Sir/Madam,\n\nWe, {{contractor_firm_name}}, are submitting our bid for the above-mentioned tender. Tender fee and supporting documents are enclosed.\n\nThank you,\n{{authorized_signatory}}\n{{designation}}\nDate: {{date}}\nPlace: {{place}}",
            'placeholders' => ['{{contractor_firm_name}}','{{department_name}}','{{tender_title}}','{{tender_number}}','{{authorized_signatory}}','{{designation}}','{{date}}','{{place}}'],
            'createdAt' => $now,
        ],
        'cover_letter_emd' => [
            'type' => 'cover_letter',
            'title' => 'Covering Letter for EMD Submission',
            'body' => "To,\n{{department_name}}\nSubject: Submission of EMD for {{tender_title}} ({{tender_number}})\n\nDear Sir/Madam,\n\nPlease find enclosed the Earnest Money Deposit for the above tender. All documents are authentic to the best of our knowledge.\n\nSincerely,\n{{authorized_signatory}}\n{{designation}}\nDate: {{date}}\nPlace: {{place}}",
            'placeholders' => ['{{department_name}}','{{tender_title}}','{{tender_number}}','{{authorized_signatory}}','{{designation}}','{{date}}','{{place}}'],
            'createdAt' => $now,
        ],
        'information_sheet' => [
            'type' => 'info_sheet',
            'title' => 'Bidder Information Sheet',
            'body' => "Name of Bidder/Firm: {{contractor_firm_name}}\nType of Firm: {{contractor_firm_type}}\nAddress: {{contractor_address}}\nGST: {{contractor_gst}} | PAN: {{contractor_pan}}\nAuthorized Signatory: {{authorized_signatory}} ({{designation}})\nEmail: {{contractor_email}} | Mobile: {{contractor_mobile}}",
            'placeholders' => ['{{contractor_firm_name}}','{{contractor_firm_type}}','{{contractor_address}}','{{contractor_gst}}','{{contractor_pan}}','{{authorized_signatory}}','{{designation}}','{{contractor_email}}','{{contractor_mobile}}'],
            'createdAt' => $now,
        ],
        'declaration_general' => [
            'type' => 'declaration',
            'title' => 'Declaration by Bidder',
            'body' => "We hereby declare that the information submitted for {{tender_title}} is true and correct. We accept all tender terms and conditions.\n\nAuthorized Signatory\n{{authorized_signatory}}\n{{designation}}\nDate: {{date}}\nPlace: {{place}}",
            'placeholders' => ['{{tender_title}}','{{authorized_signatory}}','{{designation}}','{{date}}','{{place}}'],
            'createdAt' => $now,
        ],
        'power_of_attorney' => [
            'type' => 'poa',
            'title' => 'Power of Attorney',
            'body' => "We, {{contractor_firm_name}}, authorize {{authorized_signatory}} ({{designation}}) to act on our behalf for all matters related to {{tender_title}} ({{tender_number}}).\n\nSignature\nDate: {{date}}\nPlace: {{place}}",
            'placeholders' => ['{{contractor_firm_name}}','{{authorized_signatory}}','{{designation}}','{{tender_title}}','{{tender_number}}','{{date}}','{{place}}'],
            'createdAt' => $now,
        ],
        'turnover_certificate' => [
            'type' => 'turnover_certificate',
            'title' => 'Annual Turnover Certificate',
            'body' => "This is to certify that {{contractor_firm_name}} has achieved the following turnovers (audited):\nFY ____ : ₹ __________\nFY ____ : ₹ __________\nFY ____ : ₹ __________\n\nChartered Accountant Signature & Seal\nDate: {{date}}\nPlace: {{place}}",
            'placeholders' => ['{{contractor_firm_name}}','{{date}}','{{place}}'],
            'createdAt' => $now,
        ],
        'net_worth_certificate' => [
            'type' => 'net_worth_certificate',
            'title' => 'Net Worth Certificate',
            'body' => "Certified that the Net Worth of {{contractor_firm_name}} as on ____ is ₹ __________ (in words: __________).\n\nChartered Accountant Signature & Seal\nDate: {{date}}\nPlace: {{place}}",
            'placeholders' => ['{{contractor_firm_name}}','{{date}}','{{place}}'],
            'createdAt' => $now,
        ],
        'msme_undertaking' => [
            'type' => 'declaration',
            'title' => 'MSME Undertaking (Jharkhand Preference)',
            'body' => "We, {{contractor_firm_name}}, claim MSME preference as per Jharkhand procurement policy, subject to submission of valid certificates.\n\nAuthorized Signatory\n{{authorized_signatory}}\n{{designation}}\nDate: {{date}}\nPlace: {{place}}",
            'placeholders' => ['{{contractor_firm_name}}','{{authorized_signatory}}','{{designation}}','{{date}}','{{place}}'],
            'createdAt' => $now,
        ],
    ];
}

function pack_match_annexure_template(string $label): ?array
{
    $labelLower = mb_strtolower($label);
    $library = pack_annexure_template_library();
    $map = [
        'cover_letter_fee' => ['covering letter', 'cover letter', 'tender fee', 'bid fee'],
        'cover_letter_emd' => ['emd', 'earnest money'],
        'information_sheet' => ['information sheet', 'bidder information', 'particulars of bidder'],
        'declaration_general' => ['declaration', 'undertaking'],
        'power_of_attorney' => ['power of attorney', 'poa', 'authorization'],
        'turnover_certificate' => ['turnover', 'annual turnover'],
        'net_worth_certificate' => ['net worth'],
        'msme_undertaking' => ['msme', 'micro', 'small', 'medium'],
    ];
    foreach ($map as $key => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($labelLower, $needle) && isset($library[$key])) {
                return $library[$key];
            }
        }
    }
    return null;
}

function pack_annexure_generate_id(string $yojId, string $packId, string $context = 'tender'): string
{
    ensure_pack_annexure_env($yojId, $packId, $context);
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $candidate = 'ANNX-' . $suffix;
    } while (file_exists(pack_annexure_path($yojId, $packId, $candidate, $context)));

    return $candidate;
}

function pack_is_restricted_annexure_label(string $label): bool
{
    $lower = mb_strtolower($label);
    return assisted_v2_is_restricted_financial_label($lower) || str_contains($lower, 'financial bid') || str_contains($lower, 'price bid');
}

function pack_generate_annexures(array $pack, array $contractor, string $context = 'tender'): array
{
    $yojId = $pack['yojId'];
    $packId = $pack['packId'];
    ensure_pack_annexure_env($yojId, $packId, $context);

    $existingIndex = load_pack_annexure_index($yojId, $packId, $context);
    $existingCodes = [];
    foreach ($existingIndex as $entry) {
        $existingCodes[$entry['annexureCode'] ?? ''] = true;
    }

    $templates = [];
    $restricted = $pack['restrictedAnnexures'] ?? [];
    $sourceList = $pack['annexureList'] ?? [];
    if (!$sourceList && !empty($pack['annexures'])) {
        $sourceList = $pack['annexures'];
    }
    foreach ($sourceList as $raw) {
        $label = is_array($raw) ? ($raw['title'] ?? ($raw['name'] ?? 'Annexure')) : (string)$raw;
        if ($label === '') {
            continue;
        }
        if (pack_is_restricted_annexure_label($label)) {
            if (!in_array($label, $restricted, true)) {
                $restricted[] = $label;
            }
            continue;
        }
        $matched = pack_match_annexure_template($label) ?? [
            'type' => 'other',
            'title' => $label,
            'body' => "This annexure format is not auto-generated. Please prepare manually and attach.\n\nTitle: {{annexure_code}} — {{annexure_title}}\nContractor: {{contractor_firm_name}}",
            'placeholders' => ['{{annexure_code}}','{{annexure_title}}','{{contractor_firm_name}}'],
            'createdAt' => now_kolkata()->format(DateTime::ATOM),
        ];
        $annexureCode = 'Annexure-' . (count($templates) + count($existingIndex) + 1);
        if (!empty($raw['code'])) {
            $annexureCode = trim((string)$raw['code']);
        } elseif (preg_match('/annexure\s*-?\s*([0-9a-z]+)/i', $label, $matches)) {
            $annexureCode = 'Annexure-' . strtoupper($matches[1]);
        }
        if (isset($existingCodes[$annexureCode])) {
            continue;
        }
        $annexId = pack_annexure_generate_id($yojId, $packId, $context);
        $templates[] = [
            'annexId' => $annexId,
            'annexureCode' => $annexureCode,
            'title' => $matched['title'] ?? $label,
            'type' => $matched['type'] ?? 'other',
            'bodyTemplate' => $matched['body'] ?? '',
            'placeholders' => $matched['placeholders'] ?? [],
            'createdAt' => $matched['createdAt'] ?? now_kolkata()->format(DateTime::ATOM),
        ];
    }

    $index = $existingIndex;
    foreach ($templates as $tpl) {
        $index[] = [
            'annexId' => $tpl['annexId'],
            'annexureCode' => $tpl['annexureCode'],
            'title' => $tpl['title'],
            'type' => $tpl['type'],
            'createdAt' => $tpl['createdAt'],
        ];
        writeJsonAtomic(pack_annexure_path($yojId, $packId, $tpl['annexId'], $context), $tpl);
    }
    save_pack_annexure_index($yojId, $packId, $index, $context);

    $pack['annexureList'] = array_values($sourceList);
    $pack['restrictedAnnexures'] = array_values(array_unique($restricted));
    $pack['generatedAnnexures'] = array_values(array_unique(array_merge($pack['generatedAnnexures'] ?? [], array_map(fn($tpl) => $tpl['annexId'], $templates))));
    return $pack;
}

function pack_upsert_offline_tender(array $tender, array $normalized, array $contractor): ?array
{
    $yojId = $tender['yojId'] ?? '';
    $offtdId = $tender['id'] ?? '';
    if ($yojId === '' || $offtdId === '') {
        return null;
    }
    $context = 'tender';
    ensure_packs_env($yojId, $context);
    $existing = find_pack_by_source($yojId, 'OFFTD', $offtdId, $context);
    $now = now_kolkata()->format(DateTime::ATOM);

    if ($existing) {
        $pack = $existing;
    } else {
        $packId = generate_pack_id($yojId, $context);
        $pack = [
            'packId' => $packId,
            'yojId' => $yojId,
            'title' => $tender['title'] ?? 'Tender Pack',
            'sourceTender' => [
                'type' => 'OFFTD',
                'id' => $offtdId,
                'source' => 'offline_assisted',
            ],
            'source' => 'offline_assisted',
            'createdAt' => $now,
            'status' => 'Pending',
            'items' => [],
            'generatedDocs' => [],
            'defaultTemplatesApplied' => false,
        ];
    }

    $pack['updatedAt'] = $now;
    $pack['title'] = $tender['title'] ?? ($pack['title'] ?? 'Tender Pack');
    $pack['tenderTitle'] = $pack['title'];
    $pack['tenderNumber'] = $pack['tenderNumber'] ?? ($tender['tenderNumber'] ?? '');
    $pack['sourceTender']['id'] = $offtdId;
    $pack['sourceTender']['type'] = 'OFFTD';
    $pack['dates']['submission'] = $tender['extracted']['submissionDeadline'] ?? ($pack['dates']['submission'] ?? '');
    $pack['dates']['opening'] = $tender['extracted']['openingDate'] ?? ($pack['dates']['opening'] ?? '');
    $pack['checklist'] = $tender['checklist'] ?? [];
    $pack['items'] = pack_items_from_checklist($pack['checklist']);
    $pack['annexureList'] = $tender['extracted']['annexures'] ?? [];
    $pack['annexures'] = $pack['annexureList'];
    $pack['formats'] = $tender['extracted']['formats'] ?? [];
    $pack['restrictedAnnexures'] = array_values(array_unique(array_merge(
        $pack['restrictedAnnexures'] ?? [],
        $tender['extracted']['restrictedAnnexures'] ?? [],
        $normalized['lists']['restricted'] ?? []
    )));

    if (empty($existing) && empty($pack['defaultTemplatesApplied'])) {
        $pack = pack_apply_default_templates($pack, $tender, $contractor, $context);
    }

    $pack = pack_apply_schema_defaults($pack);
    $pack = pack_generate_annexures($pack, $contractor, $context);
    save_pack($pack, $context);

    pack_log([
        'event' => 'offline_assisted_sync',
        'yojId' => $yojId,
        'packId' => $pack['packId'],
        'offtdId' => $offtdId,
        'annexures' => count($pack['annexureList'] ?? []),
        'templatesGenerated' => count($pack['generatedAnnexures'] ?? []),
        'restrictedCount' => count($pack['restrictedAnnexures'] ?? []),
    ]);

    return $pack;
}

function pack_annexure_placeholder_context(array $pack, array $contractor): array
{
    $prefill = static function ($value, int $minLength = 8): string {
        $trim = trim((string)$value);
        return $trim === '' ? str_repeat('_', $minLength) : $trim;
    };

    $placeDefault = $contractor['placeDefault'] ?? '';
    if ($placeDefault === '') {
        $placeDefault = $contractor['district'] ?? '';
    }

    return [
        '{{contractor_firm_name}}' => $prefill($contractor['firmName'] ?? ($contractor['name'] ?? '')),
        '{{contractor_firm_type}}' => $prefill($contractor['firmType'] ?? '', 5),
        '{{contractor_address}}' => $prefill(contractor_profile_address($contractor)),
        '{{contractor_gst}}' => $prefill($contractor['gstNumber'] ?? '', 5),
        '{{contractor_pan}}' => $prefill($contractor['panNumber'] ?? '', 5),
        '{{authorized_signatory}}' => $prefill($contractor['authorizedSignatoryName'] ?? ($contractor['name'] ?? ''), 5),
        '{{designation}}' => $prefill($contractor['authorizedSignatoryDesignation'] ?? '', 5),
        '{{tender_title}}' => $prefill($pack['tenderTitle'] ?? ($pack['title'] ?? 'Tender'), 6),
        '{{tender_number}}' => $prefill($pack['tenderNumber'] ?? '', 6),
        '{{department_name}}' => $prefill($pack['deptName'] ?? ($pack['sourceTender']['deptName'] ?? ''), 6),
        '{{place}}' => $prefill($placeDefault, 6),
        '{{date}}' => now_kolkata()->format('d M Y'),
        '{{contractor_email}}' => $prefill($contractor['email'] ?? '', 6),
        '{{contractor_mobile}}' => $prefill($contractor['mobile'] ?? '', 6),
        '{{submission_deadline}}' => $prefill($pack['submissionDeadline'] ?? ($pack['dates']['submission'] ?? ''), 6),
        '{{emd_text}}' => $prefill($pack['fees']['emdText'] ?? '', 6),
        '{{fee_text}}' => $prefill($pack['fees']['tenderFeeText'] ?? '', 6),
        '{{sd_text}}' => $prefill($pack['fees']['sdText'] ?? '', 6),
        '{{pg_text}}' => $prefill($pack['fees']['pgText'] ?? '', 6),
        '{{annexure_title}}' => '',
        '{{annexure_code}}' => '',
    ];
}

function pack_fill_annexure_body(array $template, array $context): string
{
    $body = (string)($template['bodyTemplate'] ?? '');
    if (!str_contains($body, '{{authorized_signatory}}') && stripos($body, 'authorized signatory') === false) {
        $body .= "\n\nAuthorized Signatory\n{{authorized_signatory}}\n{{designation}}\n{{contractor_firm_name}}\nPlace: {{place}}\nDate: {{date}}";
    }
    foreach ($context as $key => $value) {
        $body = str_replace($key, $value, $body);
    }
    return $body;
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

    $pack = pack_apply_schema_defaults($pack);
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

function pack_print_html(array $pack, array $contractor, string $docType = 'index', array $options = [], array $vaultFiles = [], array $annexureTemplates = []): string
{
    $pack = pack_apply_schema_defaults($pack);
    $contractor = normalize_contractor_profile($contractor);
    $allowedDocs = ['index', 'checklist', 'annexures', 'templates', 'full'];
    if (!in_array($docType, $allowedDocs, true)) {
        $docType = 'index';
    }
    if (!$annexureTemplates && !empty($pack['packId']) && !empty($pack['yojId'])) {
        $annexureTemplates = load_pack_annexures($pack['yojId'], $pack['packId'], detect_pack_context($pack['packId']));
    }
    $options = array_merge([
        'includeSnippets' => true,
        'includeRestricted' => true,
        'pendingOnly' => false,
        'useLetterhead' => true,
        'annexureId' => null,
        'templateId' => null,
        'annexurePreview' => false,
    ], $options);
    $singleAnnexureId = is_string($options['annexureId']) ? trim($options['annexureId']) : '';
    if ($singleAnnexureId !== '') {
        $annexureTemplates = array_values(array_filter($annexureTemplates, static function ($tpl) use ($singleAnnexureId) {
            return (($tpl['annexId'] ?? '') === $singleAnnexureId) || (($tpl['annexureCode'] ?? '') === $singleAnnexureId);
        }));
    }

    $printedAt = now_kolkata()->format('d M Y, h:i A');
    $prefill = static function ($value, int $minLength = 8): string {
        $trim = trim((string)$value);
        return $trim === '' ? str_repeat('_', $minLength) : htmlspecialchars($trim, ENT_QUOTES, 'UTF-8');
    };

    $attachments = pack_attachment_map($pack, $vaultFiles);

    $checklist = $pack['checklist'] ?? [];
    if ($options['pendingOnly']) {
        $checklist = array_values(array_filter($checklist, fn($item) => ($item['status'] ?? 'pending') === 'pending'));
    }

    $render_badge = static function (string $status): string {
        $status = strtolower($status);
        $colors = [
            'pending' => '#f0ad4e',
            'uploaded' => '#2ea043',
            'generated' => '#58a6ff',
            'done' => '#2ea043',
        ];
        $label = ucfirst($status ?: 'Pending');
        $color = $colors[$status] ?? '#8b949e';
        return '<span class="status" style="background:' . $color . '1a;color:' . $color . ';border:1px solid ' . $color . '33;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    };

    $render_checklist = static function () use ($checklist, $attachments, $render_badge, $options): string {
        if (!$checklist) {
            return '<div class="section"><h2>Checklist</h2><p class="muted">No data available.</p></div>';
        }
        $grouped = [];
        foreach ($checklist as $item) {
            $group = $item['category'] ?? 'Other';
            $grouped[$group][] = $item;
        }
        $order = ['Eligibility', 'Forms', 'Technical', 'Submission', 'Declarations', 'Other'];
        uksort($grouped, static function ($a, $b) use ($order) {
            $posA = array_search($a, $order, true);
            $posB = array_search($b, $order, true);
            $posA = $posA === false ? 999 : $posA;
            $posB = $posB === false ? 999 : $posB;
            if ($posA === $posB) {
                return strcmp($a, $b);
            }
            return $posA <=> $posB;
        });
        $html = '<div class="section"><h2>Checklist</h2>';
        foreach ($grouped as $group => $items) {
            $html .= '<div class="subsection"><h3>' . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . '</h3>';
            $html .= '<table><thead><tr><th>Item</th><th>Required</th><th>Status</th><th>Notes</th><th>Attachment</th></tr></thead><tbody>';
            foreach ($items as $item) {
                $itemId = $item['itemId'] ?? ($item['id'] ?? '');
                $attach = $attachments[$itemId] ?? null;
                $notes = trim((string)($item['notes'] ?? ''));
                if ($options['includeSnippets'] && trim((string)($item['sourceSnippet'] ?? '')) !== '') {
                    $notes .= ($notes !== '' ? ' | ' : '') . trim((string)$item['sourceSnippet']);
                }
                $html .= '<tr>';
                $html .= '<td><strong>' . htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8') . '</strong><div class="muted">' . htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8') . '</div></td>';
                $html .= '<td>' . (!empty($item['required']) ? 'Required' : 'Optional') . '</td>';
                $html .= '<td>' . $render_badge($item['status'] ?? 'pending') . '</td>';
                $html .= '<td>' . ($notes !== '' ? htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') : '<span class="muted">-</span>') . '</td>';
                if ($attach) {
                    $title = htmlspecialchars($attach['title'] ?? 'Vault document', ENT_QUOTES, 'UTF-8');
                    $html .= '<td><div class="pill">' . $title . ' (' . htmlspecialchars($attach['fileId'] ?? '', ENT_QUOTES, 'UTF-8') . ')</div><div class="muted" style="font-size:12px;">' . htmlspecialchars($attach['reason'] ?? '', ENT_QUOTES, 'UTF-8') . '</div></td>';
                } else {
                    $html .= '<td><span class="muted">Not mapped</span></td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table></div>';
        }
        $html .= '</div>';
        return $html;
    };

    $render_annexures = static function () use ($pack, $options, $annexureTemplates, $contractor): string {
        $annexures = $pack['annexures'] ?? [];
        $formats = $pack['formats'] ?? [];
        $restricted = $pack['restrictedAnnexures'] ?? [];
        $showCatalog = empty($options['annexurePreview']);
        $html = '<div class="section"><h2>Annexures & Formats</h2>';
        if ($showCatalog) {
            if (!$annexures && !$formats) {
                $html .= '<p class="muted">No annexures listed.</p>';
            } else {
                if ($annexures) {
                    $html .= '<h3>Annexures</h3><ol>';
                    foreach ($annexures as $annex) {
                        $label = is_array($annex) ? ($annex['name'] ?? $annex['title'] ?? 'Annexure') : (string)$annex;
                        $notes = is_array($annex) ? ($annex['notes'] ?? '') : '';
                        $restrictedLabel = pack_is_restricted_annexure_label($label);
                        $html .= '<li><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong>';
                        if ($restrictedLabel) {
                            $html .= '<div class="muted" style="color:#f85149;">Not supported in YOJAK (financial/price annexure).</div>';
                        }
                        if ($notes !== '') {
                            $html .= '<div class="muted">' . htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') . '</div>';
                        }
                        $html .= '</li>';
                    }
                    $html .= '</ol>';
                }
                if ($formats) {
                    $html .= '<h3>Formats</h3><ul>';
                    foreach ($formats as $fmt) {
                        $label = is_array($fmt) ? ($fmt['name'] ?? $fmt['title'] ?? 'Format') : (string)$fmt;
                        $html .= '<li>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</li>';
                    }
                    $html .= '</ul>';
                }
            }
        }

        $annexureContext = pack_annexure_placeholder_context($pack, $contractor);
        if ($annexureTemplates) {
            $html .= '<div class="subsection"><h3>Generated Annexure Templates</h3>';
            foreach ($annexureTemplates as $idx => $tpl) {
                $context = $annexureContext;
                $context['{{annexure_title}}'] = $tpl['title'] ?? '';
                $context['{{annexure_code}}'] = $tpl['annexureCode'] ?? '';
                $body = pack_fill_annexure_body($tpl, $context);
                $html .= '<div class="template-block' . ($idx > 0 ? ' page-break' : '') . '">';
                $html .= '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">';
                $html .= '<div><div class="muted">' . htmlspecialchars($tpl['annexureCode'] ?? 'Annexure', ENT_QUOTES, 'UTF-8') . '</div><h3 style="margin:4px 0 6px 0;">' . htmlspecialchars($tpl['title'] ?? 'Annexure', ENT_QUOTES, 'UTF-8') . '</h3></div>';
                $html .= '<span class="pill">' . htmlspecialchars(ucwords(str_replace('_', ' ', $tpl['type'] ?? 'other')), ENT_QUOTES, 'UTF-8') . '</span>';
                $html .= '</div>';
                $html .= '<pre>' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</pre>';
                $html .= '</div>';
            }
            $html .= '</div>';
        } else {
            $html .= '<p class="muted">No annexure formats generated yet.</p>';
        }

        if ($showCatalog && $options['includeRestricted'] && $restricted) {
            $html .= '<div class="warning"><strong>Restricted Annexures</strong><p>Financial/Price annexures referenced — Not supported in YOJAK.</p><ul>';
            foreach ($restricted as $rest) {
                $html .= '<li>' . htmlspecialchars(is_array($rest) ? ($rest['name'] ?? $rest['title'] ?? 'Restricted') : (string)$rest, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $html .= '</ul></div>';
        }
        $html .= '</div>';
        return $html;
    };

    $render_templates = static function () use ($pack, $contractor, $options): string {
        $templates = pack_template_payloads($pack, $contractor);
        $templateId = is_string($options['templateId'] ?? null) ? trim((string)$options['templateId']) : '';
        if ($templateId !== '') {
            $templates = array_values(array_filter($templates, static function (array $tpl) use ($templateId) {
                return ($tpl['tplId'] ?? '') === $templateId;
            }));
        }
        $html = '<div class="section"><h2>Templates</h2>';
        if (!$templates) {
            return $html . '<p class="muted">No templates available.</p></div>';
        }
        foreach ($templates as $idx => $tpl) {
            $html .= '<div class="template-block' . ($idx > 0 ? ' page-break' : '') . '">';
            $html .= '<h3>' . htmlspecialchars($tpl['name'] ?? 'Template', ENT_QUOTES, 'UTF-8') . '</h3>';
            if (!empty($tpl['lastGeneratedAt'])) {
                $html .= '<p class="muted" style="margin-top:-6px;">Updated: ' . htmlspecialchars($tpl['lastGeneratedAt'], ENT_QUOTES, 'UTF-8') . '</p>';
            }
            $html .= '<pre>' . htmlspecialchars($tpl['body'] ?? '', ENT_QUOTES, 'UTF-8') . '</pre>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    };

    $render_attachments_plan = static function () use ($checklist, $attachments): string {
        if (!$checklist) {
            return '';
        }
        $html = '<div class="section"><h2>Attachments Plan</h2><table><thead><tr><th>Checklist Item</th><th>Vault Document</th></tr></thead><tbody>';
        foreach ($checklist as $item) {
            $itemId = $item['itemId'] ?? ($item['id'] ?? '');
            $attach = $attachments[$itemId] ?? null;
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            if ($attach) {
                $html .= '<td><strong>' . htmlspecialchars($attach['title'] ?? 'Vault document', ENT_QUOTES, 'UTF-8') . '</strong><div class="muted">' . htmlspecialchars($attach['fileId'] ?? '', ENT_QUOTES, 'UTF-8') . '</div></td>';
            } else {
                $html .= '<td><span class="muted">Not mapped</span></td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
        return $html;
    };

    $render_index = static function () use ($pack, $contractor, $prefill, $printedAt): string {
        $stats = pack_stats($pack);
        $annexureList = $pack['annexureList'] ?? ($pack['annexures'] ?? []);
        $templateList = array_map(static function (array $tpl): string {
            return (string)($tpl['name'] ?? 'Template');
        }, $pack['generatedTemplates'] ?? []);
        $restricted = $pack['restrictedAnnexures'] ?? [];
        $html = '<div class="section"><h2>Pack Index</h2>';
        $html .= '<div class="cards"><div class="card-sm"><div class="muted">Tender</div><div class="large">' . htmlspecialchars($pack['tenderTitle'] ?? $pack['title'] ?? 'Tender Pack', ENT_QUOTES, 'UTF-8') . '</div><div class="muted">No: ' . htmlspecialchars($pack['tenderNumber'] ?? '', ENT_QUOTES, 'UTF-8') . '</div><div class="muted">' . htmlspecialchars($pack['departmentName'] ?? ($pack['deptName'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div></div>';
        $html .= '<div class="card-sm"><div class="muted">Contractor</div><div class="large">' . htmlspecialchars($contractor['firmName'] ?? ($contractor['name'] ?? 'Contractor'), ENT_QUOTES, 'UTF-8') . '</div><div class="muted">YOJ ID: ' . htmlspecialchars($pack['yojId'] ?? '', ENT_QUOTES, 'UTF-8') . '</div></div>';
        $html .= '<div class="card-sm"><div class="muted">Progress</div><div class="large">' . $stats['doneRequired'] . ' / ' . $stats['requiredItems'] . '</div><div class="muted">Generated docs: ' . $stats['generatedDocs'] . '</div></div></div>';
        $html .= '<div class="grid-2"><div><h4>Contractor Summary</h4><ul class="plain">';
        $html .= '<li>Address: ' . $prefill(contractor_profile_address($contractor)) . '</li>';
        $html .= '<li>PAN: ' . $prefill($contractor['panNumber'] ?? '') . ' • GST: ' . $prefill($contractor['gstNumber'] ?? '') . '</li>';
        $html .= '<li>Signatory: ' . $prefill($contractor['authorizedSignatoryName'] ?? '') . ' (' . $prefill($contractor['authorizedSignatoryDesignation'] ?? '', 5) . ')</li>';
        $html .= '<li>Contact: ' . $prefill($contractor['mobile'] ?? '', 6) . ' • ' . $prefill($contractor['email'] ?? '', 6) . '</li>';
        $html .= '</ul></div>';
        $html .= '<div><h4>Key Dates</h4><ul class="plain">';
        $html .= '<li>Submission: ' . $prefill($pack['submissionDeadline'] ?? ($pack['dates']['submission'] ?? '')) . '</li>';
        $html .= '<li>Opening: ' . $prefill($pack['openingDate'] ?? ($pack['dates']['opening'] ?? '')) . '</li>';
        $html .= '<li>Completion: ' . $prefill((string)($pack['completionMonths'] ?? ''), 2) . ' months</li>';
        $html .= '<li>Bid validity: ' . $prefill((string)($pack['bidValidityDays'] ?? ''), 2) . ' days</li>';
        $html .= '<li>Generated: ' . htmlspecialchars($printedAt, ENT_QUOTES, 'UTF-8') . '</li>';
        $html .= '</ul><h4>Checklist Summary</h4><ul class="plain"><li>Done: ' . $stats['doneRequired'] . '</li><li>Pending: ' . $stats['pendingRequired'] . '</li></ul>';
        $html .= '<h4>Included Annexures</h4><ul class="plain">';
        if ($annexureList) {
            foreach ($annexureList as $annex) {
                $label = is_array($annex) ? ($annex['title'] ?? ($annex['name'] ?? 'Annexure')) : (string)$annex;
                $html .= '<li>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</li>';
            }
        } else {
            $html .= '<li><span class="muted">None listed</span></li>';
        }
        $html .= '</ul><h4>Included Templates</h4><ul class="plain">';
        if ($templateList) {
            foreach ($templateList as $tplName) {
                $html .= '<li>' . htmlspecialchars($tplName, ENT_QUOTES, 'UTF-8') . '</li>';
            }
        } else {
            $html .= '<li><span class="muted">None generated</span></li>';
        }
        $html .= '</ul>';
        if ($restricted) {
            $html .= '<h4>Restricted (Not supported in YOJAK)</h4><ul class="plain">';
            foreach ($restricted as $rest) {
                $html .= '<li>' . htmlspecialchars(is_array($rest) ? ($rest['name'] ?? $rest['title'] ?? 'Restricted') : (string)$rest, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '<h4>Contents</h4><ul class="plain"><li>Index</li><li>Checklist</li><li>Annexures & Formats</li><li>Templates</li></ul></div></div>';
        $html .= '</div>';
        return $html;
    };

    $sections = [];
    if (in_array($docType, ['index', 'full'], true)) {
        $sections[] = $render_index();
    }
    if ($docType === 'index') {
        $sections[] = $render_attachments_plan();
    }
    if (in_array($docType, ['checklist', 'full'], true)) {
        $sections[] = $render_checklist();
    }
    if (in_array($docType, ['annexures', 'full'], true)) {
        $sections[] = $render_annexures();
    }
    if (in_array($docType, ['templates', 'full'], true)) {
        $sections[] = $render_templates();
    }
    if ($docType === 'full') {
        $sections[] = $render_attachments_plan();
    }

    $styles = '<style>
    @page{size:A4;margin:18mm;}
    body{font-family:\'Segoe UI\',Arial,sans-serif;background:#0d1117;color:#e6edf3;margin:0;padding:24px;}
    .page{max-width:960px;margin:0 auto;background:#0f1520;border:1px solid #30363d;border-radius:14px;padding:20px;}
    h1,h2,h3,h4{margin:0 0 8px;}
    .muted{color:#8b949e;}
    table{width:100%;border-collapse:collapse;margin-top:8px;}
    th,td{padding:8px;border-bottom:1px solid #30363d;text-align:left;vertical-align:top;}
    th{color:#8b949e;text-transform:uppercase;font-size:12px;letter-spacing:0.04em;}
    .status{padding:6px 10px;border-radius:20px;font-size:12px;display:inline-block;}
    .section{margin-top:16px;}
    .subsection{margin-top:10px;}
    .warning{border:1px solid #f85149;padding:10px;border-radius:10px;background:#211015;}
    .template-block{background:#0b111a;border:1px solid #1f6feb33;border-radius:12px;padding:14px;margin-top:12px;}
    pre{white-space:pre-wrap;line-height:1.6;font-family:inherit;}
    .card-sm{background:#0b111a;border:1px solid #30363d;border-radius:10px;padding:12px;}
    .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;}
    .large{font-size:18px;font-weight:700;}
    .grid-2{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin-top:12px;}
    .plain{list-style:none;padding:0;margin:0;}
    .plain li{margin:4px 0;}
    .pill{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid #30363d;font-size:12px;background:#111820;}
    .page-break{page-break-before:always;}
    footer{margin-top:20px;font-size:12px;color:#8b949e;text-align:center;min-height:20mm;}
    footer .page-number::after{content:"1";}
    .print-header{min-height:30mm;margin-bottom:12px;display:flex;gap:12px;align-items:center;border-bottom:1px solid #30363d;padding-bottom:10px;}
    .print-header .logo{max-width:35mm;max-height:20mm;object-fit:contain;}
    .print-header .blank{height:20mm;}
    @media print{
        body{background:#fff;color:#000;}
        .page{box-shadow:none;border:1px solid #ddd;}
        a{color:#000;}
        footer .page-number::after{content: counter(page);}
    }
    </style>';

    $printSettings = load_contractor_print_settings($pack['yojId']);
    $useLetterhead = (bool)($options['useLetterhead'] ?? true);
    if (!$useLetterhead) {
        $printSettings['headerEnabled'] = false;
        $printSettings['footerEnabled'] = false;
        $printSettings['logoEnabled'] = false;
    }
    $logoHtml = '';
    if (!empty($printSettings['logoEnabled']) && !empty($printSettings['logoPublicPath'])) {
        $align = $printSettings['logoAlign'] ?? 'left';
        $logoHtml = '<div style="flex:0 0 auto;text-align:' . htmlspecialchars($align, ENT_QUOTES, 'UTF-8') . ';"><img class="logo" src="' . htmlspecialchars($printSettings['logoPublicPath'], ENT_QUOTES, 'UTF-8') . '" alt="Logo"></div>';
    }
    $headerText = '';
    if (!empty($printSettings['headerEnabled']) && trim((string)$printSettings['headerText']) !== '') {
        $headerText = '<div style="flex:1;white-space:pre-wrap;">' . nl2br(htmlspecialchars($printSettings['headerText'], ENT_QUOTES, 'UTF-8')) . '</div>';
    } else {
        $headerText = '<div class="blank" style="flex:1;"></div>';
    }
    $headerNote = $useLetterhead ? 'Using saved letterhead' : 'Letterhead space reserved (pre-printed)';
    $header = '<div class="print-header" aria-label="Print header">' . $logoHtml . $headerText . '</div>'
        . '<div class="header" style="margin-bottom:12px;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">'
        . '<div><div class="muted" style="font-size:12px;">YOJAK Tender Pack</div><h1 style="margin:2px 0 4px 0;">' . htmlspecialchars($pack['tenderTitle'] ?? ($pack['title'] ?? 'Tender Pack'), ENT_QUOTES, 'UTF-8') . '</h1>'
        . '<div class="muted">Pack ID: ' . htmlspecialchars($pack['packId'] ?? '', ENT_QUOTES, 'UTF-8') . ' • Tender No: ' . htmlspecialchars($pack['tenderNumber'] ?? '', ENT_QUOTES, 'UTF-8') . '</div></div>'
        . '<div style="text-align:right;"><div class="muted">Contractor</div><strong>' . htmlspecialchars($contractor['firmName'] ?? ($contractor['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong><div class="muted">Printed on ' . htmlspecialchars($printedAt, ENT_QUOTES, 'UTF-8') . '</div><div class="muted" style="font-size:12px;">' . htmlspecialchars($headerNote, ENT_QUOTES, 'UTF-8') . '</div></div>'
        . '</div>';

    $footerText = '';
    if (!empty($printSettings['footerEnabled']) && trim((string)$printSettings['footerText']) !== '') {
        $footerText = '<div style="white-space:pre-wrap;">' . nl2br(htmlspecialchars($printSettings['footerText'], ENT_QUOTES, 'UTF-8')) . '</div>';
    } else {
        $footerText = '<div style="min-height:20mm;"></div>';
    }
    $footer = '<footer>' . $footerText . '<div>Printed via YOJAK • Page <span class="page-number"></span></div></footer>';

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Pack '
        . htmlspecialchars($pack['packId'] ?? 'Pack', ENT_QUOTES, 'UTF-8') . '</title>'
        . $styles . '</head><body><div class="page">' . $header . implode('<hr class="muted" style="border:none;border-top:1px solid #30363d;margin:16px 0;">', $sections) . $footer . '</div></body></html>';

    return $html;
}

function pack_index_html(array $pack, ?array $contractor = null, array $options = [], array $vaultFiles = [], array $annexureTemplates = []): string
{
    if ($contractor === null && !empty($pack['yojId'])) {
        $contractor = load_contractor($pack['yojId']);
    }
    return pack_print_html($pack, $contractor ?? [], 'index', $options, $vaultFiles, $annexureTemplates);
}
