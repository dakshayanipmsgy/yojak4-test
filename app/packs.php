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

function default_pack_print_prefs(): array
{
    return [
        'pageSize' => 'A4',
        'orientation' => 'portrait',
        'letterheadMode' => 'use_saved_letterhead',
        'includeSnippets' => true,
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
    if (!isset($pack['fieldOverrides']) || !is_array($pack['fieldOverrides'])) {
        $pack['fieldOverrides'] = [];
    }
    if (!isset($pack['fieldRegistry']) || !is_array($pack['fieldRegistry'])) {
        $pack['fieldRegistry'] = [];
    }
    if (!isset($pack['fieldMeta']) || !is_array($pack['fieldMeta'])) {
        $pack['fieldMeta'] = [];
    }
    $pack['fieldMeta'] = array_merge(pack_default_field_meta(), $pack['fieldMeta']);
    $normalizedRegistry = [];
    foreach ($pack['fieldRegistry'] as $key => $value) {
        $normalized = pack_normalize_placeholder_key((string)$key);
        if ($normalized === '') {
            continue;
        }
        $normalizedRegistry[$normalized] = trim((string)$value);
    }
    foreach ($pack['fieldOverrides'] as $key => $value) {
        $normalized = pack_normalize_placeholder_key((string)$key);
        if ($normalized !== '' && !array_key_exists($normalized, $normalizedRegistry)) {
            $normalizedRegistry[$normalized] = trim((string)$value);
        }
    }
    $pack['fieldRegistry'] = $normalizedRegistry;
    if (!isset($pack['printPrefs']) || !is_array($pack['printPrefs'])) {
        $pack['printPrefs'] = default_pack_print_prefs();
    } else {
        $pack['printPrefs'] = array_merge(default_pack_print_prefs(), $pack['printPrefs']);
    }
    if (!isset($pack['audit']) || !is_array($pack['audit'])) {
        $pack['audit'] = [];
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
    $catalog = pack_field_meta_catalog($pack, [], $templates);
    $contextMap = contractor_template_context($contractor, pack_tender_context($pack));
    $contextMap = array_merge($contextMap, pack_placeholder_value_map($pack, $contractor, $catalog));
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
    $catalog = pack_field_meta_catalog($pack, [], $defaults);
    $contextMap = array_merge($contextMap, pack_placeholder_value_map($pack, $contractor, $catalog));
    foreach ($contextMap as $key => $value) {
        if (trim((string)$value) === '') {
            $contextMap[$key] = '__________';
        }
    }
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
            . '</title><style>body{font-family:Arial,sans-serif;background:var(--surface);color:var(--text);padding:24px;}h1{margin-top:0;color:#fff;}p,pre{line-height:1.6;white-space:pre-wrap;}</style></head><body>'
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
            'body' => "Name of Bidder/Firm: {{contractor_firm_name}}\nType of Firm: {{contractor_firm_type}}\nAddress: {{contractor_address}}\nGST: {{contractor_gst}} | PAN: {{contractor_pan}}\nAuthorized Signatory: {{authorized_signatory}} ({{designation}})\nOffice Phone: {{contact.office_phone}} | Residence Phone: {{contact.residence_phone}}\nEmail: {{contact.email}} | Mobile: {{contact.mobile}} | Fax: {{contact.fax}}",
            'placeholders' => ['{{contractor_firm_name}}','{{contractor_firm_type}}','{{contractor_address}}','{{contractor_gst}}','{{contractor_pan}}','{{authorized_signatory}}','{{designation}}','{{contact.office_phone}}','{{contact.residence_phone}}','{{contact.email}}','{{contact.mobile}}','{{contact.fax}}'],
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
            'body' => "This is to certify that {{contractor_firm_name}} has achieved the following turnovers (audited):\n{{turnover_details}}\n\nChartered Accountant Signature & Seal\nDate: {{date}}\nPlace: {{place}}",
            'placeholders' => ['{{contractor_firm_name}}','{{turnover_details}}','{{date}}','{{place}}'],
            'createdAt' => $now,
        ],
        'net_worth_certificate' => [
            'type' => 'net_worth_certificate',
            'title' => 'Net Worth Certificate',
            'body' => "Certified that the Net Worth of {{contractor_firm_name}} as on {{net_worth_as_on}} is ₹ {{net_worth_amount}} (in words: {{net_worth_in_words}}).\n\nChartered Accountant Signature & Seal\nDate: {{date}}\nPlace: {{place}}",
            'placeholders' => ['{{contractor_firm_name}}','{{net_worth_as_on}}','{{net_worth_amount}}','{{net_worth_in_words}}','{{date}}','{{place}}'],
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
            'renderTemplate' => $matched['body'] ?? '',
            'placeholders' => $matched['placeholders'] ?? [],
            'requiredFields' => $matched['requiredFields'] ?? [],
            'tables' => $matched['tables'] ?? [],
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

function pack_default_field_meta(): array
{
    return [
        'firm.name' => ['label' => 'Firm name', 'group' => 'Contractor Contact', 'max' => 160, 'type' => 'text'],
        'firm.type' => ['label' => 'Firm type', 'group' => 'Contractor Contact', 'max' => 80, 'type' => 'text'],
        'firm.address' => ['label' => 'Firm address', 'group' => 'Contractor Contact', 'max' => 400, 'type' => 'textarea'],
        'firm.city' => ['label' => 'City', 'group' => 'Contractor Contact', 'max' => 120, 'type' => 'text'],
        'firm.state' => ['label' => 'State', 'group' => 'Contractor Contact', 'max' => 120, 'type' => 'text'],
        'firm.pincode' => ['label' => 'Pincode', 'group' => 'Contractor Contact', 'max' => 20, 'type' => 'text'],
        'tax.gst' => ['label' => 'GST number', 'group' => 'Contractor Contact', 'max' => 80, 'type' => 'text'],
        'tax.pan' => ['label' => 'PAN number', 'group' => 'Contractor Contact', 'max' => 80, 'type' => 'text'],
        'contact.office_phone' => ['label' => 'Office phone', 'group' => 'Contractor Contact', 'max' => 30, 'type' => 'text'],
        'contact.residence_phone' => ['label' => 'Residence phone', 'group' => 'Contractor Contact', 'max' => 30, 'type' => 'text'],
        'contact.mobile' => ['label' => 'Mobile', 'group' => 'Contractor Contact', 'max' => 30, 'type' => 'text'],
        'contact.fax' => ['label' => 'Fax', 'group' => 'Contractor Contact', 'max' => 30, 'type' => 'text'],
        'contact.email' => ['label' => 'Email', 'group' => 'Contractor Contact', 'max' => 120, 'type' => 'text'],
        'bank.account_no' => ['label' => 'Bank account number', 'group' => 'Bank Details', 'max' => 60, 'type' => 'text'],
        'bank.ifsc' => ['label' => 'IFSC', 'group' => 'Bank Details', 'max' => 20, 'type' => 'text'],
        'bank.bank_name' => ['label' => 'Bank name', 'group' => 'Bank Details', 'max' => 120, 'type' => 'text'],
        'bank.branch' => ['label' => 'Bank branch', 'group' => 'Bank Details', 'max' => 120, 'type' => 'text'],
        'bank.account_holder' => ['label' => 'Account holder', 'group' => 'Bank Details', 'max' => 160, 'type' => 'text'],
        'signatory.name' => ['label' => 'Authorized signatory', 'group' => 'Signatory', 'max' => 160, 'type' => 'text'],
        'signatory.designation' => ['label' => 'Signatory designation', 'group' => 'Signatory', 'max' => 120, 'type' => 'text'],
        'place' => ['label' => 'Place', 'group' => 'Signatory', 'max' => 120, 'type' => 'text'],
        'date' => ['label' => 'Date', 'group' => 'Signatory', 'max' => 40, 'type' => 'date'],
        'tender_title' => ['label' => 'Tender title', 'group' => 'Tender Meta', 'max' => 200, 'type' => 'text', 'readOnly' => true],
        'tender_number' => ['label' => 'Tender number', 'group' => 'Tender Meta', 'max' => 120, 'type' => 'text', 'readOnly' => true],
        'department_name' => ['label' => 'Department name', 'group' => 'Tender Meta', 'max' => 200, 'type' => 'text', 'readOnly' => true],
        'submission_deadline' => ['label' => 'Submission deadline', 'group' => 'Tender Meta', 'max' => 120, 'type' => 'text', 'readOnly' => true],
        'emd_text' => ['label' => 'EMD details', 'group' => 'Tender Meta', 'max' => 240, 'type' => 'textarea'],
        'fee_text' => ['label' => 'Tender fee details', 'group' => 'Tender Meta', 'max' => 240, 'type' => 'textarea'],
        'sd_text' => ['label' => 'Security deposit details', 'group' => 'Tender Meta', 'max' => 240, 'type' => 'textarea'],
        'pg_text' => ['label' => 'Performance guarantee details', 'group' => 'Tender Meta', 'max' => 240, 'type' => 'textarea'],
        'officer_name' => ['label' => 'Officer name', 'group' => 'Tender Meta', 'max' => 160, 'type' => 'text', 'readOnly' => true],
        'office_address' => ['label' => 'Office address', 'group' => 'Tender Meta', 'max' => 400, 'type' => 'textarea', 'readOnly' => true],
        'warranty_years' => ['label' => 'Warranty (years)', 'group' => 'Other', 'max' => 40, 'type' => 'text'],
        'installation_timeline_days' => ['label' => 'Installation timeline (days)', 'group' => 'Other', 'max' => 40, 'type' => 'text'],
        'local_content_percent' => ['label' => 'Local content (%)', 'group' => 'Other', 'max' => 40, 'type' => 'text'],
        'company.core_expertise' => ['label' => 'Core expertise', 'group' => 'Other', 'max' => 240, 'type' => 'textarea'],
        'company.year_established' => ['label' => 'Year of establishment', 'group' => 'Other', 'max' => 40, 'type' => 'text'],
        'company.key_licenses' => ['label' => 'Key licenses', 'group' => 'Other', 'max' => 240, 'type' => 'textarea'],
        'experience_summary_table' => ['label' => 'Experience summary table', 'group' => 'Other', 'max' => 1500, 'type' => 'textarea'],
        'manpower_list_table' => ['label' => 'Manpower list', 'group' => 'Other', 'max' => 1500, 'type' => 'textarea'],
        'equipment_list_table' => ['label' => 'Equipment list', 'group' => 'Other', 'max' => 1500, 'type' => 'textarea'],
        'turnover_details' => ['label' => 'Turnover details', 'group' => 'Other', 'max' => 800, 'type' => 'textarea'],
        'net_worth_as_on' => ['label' => 'Net worth as on', 'group' => 'Other', 'max' => 40, 'type' => 'text'],
        'net_worth_amount' => ['label' => 'Net worth amount', 'group' => 'Other', 'max' => 80, 'type' => 'text'],
        'net_worth_in_words' => ['label' => 'Net worth (in words)', 'group' => 'Other', 'max' => 120, 'type' => 'text'],
        'compliance.SolarArrayCapacity' => ['label' => 'Solar Array Capacity meets tender spec', 'group' => 'Compliance Table', 'max' => 10, 'type' => 'choice', 'choices' => ['yes', 'no', 'na']],
        'compliance.SolarModules' => ['label' => 'Solar Modules comply', 'group' => 'Compliance Table', 'max' => 10, 'type' => 'choice', 'choices' => ['yes', 'no', 'na']],
    ];
}

function pack_editable_field_catalog(): array
{
    return pack_default_field_meta();
}

function pack_field_aliases(): array
{
    return [
        'contractor_firm_name' => 'firm.name',
        'contractor_firm_type' => 'firm.type',
        'contractor_address' => 'firm.address',
        'contractor_gst' => 'tax.gst',
        'contractor_pan' => 'tax.pan',
        'company_name' => 'firm.name',
        'dealer_name' => 'firm.name',
        'contractor_name' => 'firm.name',
        'firm_name' => 'firm.name',
        'company_address' => 'firm.address',
        'pan_no' => 'tax.pan',
        'pan_number' => 'tax.pan',
        'gst_no' => 'tax.gst',
        'gstin' => 'tax.gst',
        'authorized_signatory' => 'signatory.name',
        'designation' => 'signatory.designation',
        'contractor_email' => 'contact.email',
        'email' => 'contact.email',
        'contractor_mobile' => 'contact.mobile',
        'mobile_no' => 'contact.mobile',
        'phone' => 'contact.mobile',
        'mobile' => 'contact.mobile',
        'office_phone' => 'contact.office_phone',
        'residence_phone' => 'contact.residence_phone',
        'fax' => 'contact.fax',
        'bank_name' => 'bank.bank_name',
        'bank_branch' => 'bank.branch',
        'bank_account' => 'bank.account_no',
        'account_no' => 'bank.account_no',
        'ifsc' => 'bank.ifsc',
    ];
}

function pack_table_cell_field_key(array $row, string $columnKey): string
{
    $fieldKey = '';
    if (isset($row['fieldKeys']) && is_array($row['fieldKeys'])) {
        $fieldKey = (string)($row['fieldKeys'][$columnKey] ?? '');
    }
    if ($fieldKey === '' && isset($row[$columnKey . 'FieldKey'])) {
        $fieldKey = (string)$row[$columnKey . 'FieldKey'];
    }
    if ($fieldKey === '' && $columnKey === 'value' && isset($row['valueFieldKey'])) {
        $fieldKey = (string)$row['valueFieldKey'];
    }
    return pack_normalize_placeholder_key($fieldKey);
}

function pack_table_field_keys(array $table): array
{
    $keys = [];
    $columns = is_array($table['columns'] ?? null) ? $table['columns'] : [];
    foreach ((array)($table['rows'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $colKey = pack_normalize_placeholder_key((string)($column['key'] ?? ''));
            if ($colKey === '') {
                continue;
            }
            $fieldKey = pack_table_cell_field_key($row, $colKey);
            if ($fieldKey !== '') {
                $keys[] = $fieldKey;
            }
        }
    }
    return array_values(array_unique($keys));
}

function pack_template_group_label(array $template, array $table = []): string
{
    $templateKind = strtolower(trim((string)($template['templateKind'] ?? $template['type'] ?? '')));
    if ($templateKind === 'financial_manual') {
        return 'Financial Manual Entry';
    }
    if ($templateKind === 'compliance') {
        return 'Compliance Table';
    }
    $title = trim((string)($table['title'] ?? ''));
    return $title !== '' ? $title : 'Other';
}

function pack_field_meta_catalog(array $pack, array $annexureTemplates = [], array $contractorTemplates = []): array
{
    $meta = pack_default_field_meta();
    if (isset($pack['fieldMeta']) && is_array($pack['fieldMeta'])) {
        foreach ($pack['fieldMeta'] as $key => $entry) {
            $normalized = pack_normalize_placeholder_key((string)$key);
            if ($normalized === '') {
                continue;
            }
            $meta[$normalized] = $entry;
        }
    }

    $register = static function (array $spec) use (&$meta): void {
        $key = pack_normalize_placeholder_key((string)($spec['key'] ?? ''));
        if ($key === '') {
            return;
        }
        if (!isset($meta[$key])) {
            $meta[$key] = [
                'label' => $spec['label'] ?? $key,
                'group' => $spec['group'] ?? 'Other',
                'max' => (int)($spec['maxLen'] ?? 200),
                'type' => $spec['type'] ?? 'text',
            ];
        }
        if (!empty($spec['choices']) && empty($meta[$key]['choices'])) {
            $meta[$key]['choices'] = array_values(array_unique(array_map('strval', $spec['choices'])));
        }
    };

    foreach ($annexureTemplates as $tpl) {
        foreach ((array)($tpl['requiredFieldKeys'] ?? []) as $key) {
            if (is_string($key)) {
                $register(['key' => $key]);
            }
        }
        foreach ((array)($tpl['requiredFields'] ?? []) as $spec) {
            if (is_array($spec)) {
                $register($spec);
            }
        }
        foreach ((array)($tpl['tables'] ?? []) as $table) {
            $group = pack_template_group_label($tpl, $table);
            $columns = is_array($table['columns'] ?? null) ? $table['columns'] : [];
            foreach ((array)($table['rows'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach ($columns as $column) {
                    if (!is_array($column)) {
                        continue;
                    }
                    $colKey = pack_normalize_placeholder_key((string)($column['key'] ?? ''));
                    if ($colKey === '' || !empty($column['readOnly'])) {
                        continue;
                    }
                    $fieldKey = pack_table_cell_field_key($row, $colKey);
                    if ($fieldKey === '') {
                        continue;
                    }
                    $labelPrefix = trim((string)($table['title'] ?? $tpl['title'] ?? 'Table'));
                    $labelSuffix = trim((string)($column['label'] ?? $colKey));
                    $register([
                        'key' => $fieldKey,
                        'label' => trim($labelPrefix . ' - ' . $labelSuffix),
                        'group' => $group,
                        'type' => $column['type'] ?? 'text',
                        'choices' => $column['choices'] ?? [],
                    ]);
                }
            }
        }
    }

    foreach ($contractorTemplates as $tpl) {
        if (!is_array($tpl)) {
            continue;
        }
        $placeholders = pack_extract_placeholders_from_template($tpl);
        foreach ($placeholders as $key) {
            if (!isset($meta[$key])) {
                $meta[$key] = [
                    'label' => ucwords(str_replace(['.', '_'], ' ', $key)),
                    'group' => 'Other',
                    'max' => 200,
                    'type' => 'text',
                ];
            }
        }
    }

    return $meta;
}

function pack_normalize_placeholder_key(string $raw): string
{
    $key = trim($raw);
    $key = preg_replace('/^{+\s*/', '', $key);
    $key = preg_replace('/\s*}+$/', '', $key);
    $key = strtolower(trim($key));
    $aliases = pack_field_aliases();
    return $aliases[$key] ?? $key;
}

function pack_tender_placeholder_values(array $pack): array
{
    $fees = is_array($pack['fees'] ?? null) ? $pack['fees'] : [];
    return [
        'tender_title' => $pack['tenderTitle'] ?? ($pack['title'] ?? 'Tender'),
        'tender_number' => $pack['tenderNumber'] ?? '',
        'department_name' => $pack['departmentName'] ?? ($pack['deptName'] ?? ($pack['sourceTender']['deptName'] ?? '')),
        'submission_deadline' => $pack['submissionDeadline'] ?? ($pack['dates']['submission'] ?? ''),
        'emd_text' => $fees['emdText'] ?? '',
        'fee_text' => $fees['tenderFeeText'] ?? '',
        'sd_text' => $fees['sdText'] ?? '',
        'pg_text' => $fees['pgText'] ?? '',
        'officer_name' => $pack['officerName'] ?? '',
        'office_address' => $pack['officeAddress'] ?? '',
        'warranty_years' => $pack['warrantyYears'] ?? '',
        'installation_timeline_days' => $pack['installationTimelineDays'] ?? '',
        'local_content_percent' => $pack['localContentPercent'] ?? '',
        'annexure_title' => '',
        'annexure_code' => '',
    ];
}

function pack_profile_placeholder_values(array $contractor): array
{
    $keys = array_keys(assisted_v2_canonical_key_set());

    $values = [
        'date' => '',
        'company.core_expertise' => $contractor['coreExpertise'] ?? '',
        'company.year_established' => $contractor['yearEstablished'] ?? '',
        'company.key_licenses' => $contractor['keyLicenses'] ?? '',
        'experience_summary_table' => '',
        'manpower_list_table' => '',
        'equipment_list_table' => '',
        'turnover_details' => '',
        'net_worth_as_on' => '',
        'net_worth_amount' => '',
        'net_worth_in_words' => '',
    ];

    foreach ($keys as $key) {
        $values[$key] = get_profile_field_value($contractor, $key);
    }

    return $values;
}

function pack_profile_memory_values(string $yojId): array
{
    if ($yojId === '') {
        return [];
    }
    static $cache = [];
    if (isset($cache[$yojId])) {
        return $cache[$yojId];
    }
    $memory = load_profile_memory($yojId);
    $values = [];
    foreach (($memory['fields'] ?? []) as $key => $entry) {
        $normalized = pack_normalize_placeholder_key((string)$key);
        if ($normalized === '') {
            continue;
        }
        $value = trim((string)($entry['value'] ?? ''));
        if ($value !== '') {
            $values[$normalized] = $value;
        }
    }
    $cache[$yojId] = $values;
    return $values;
}

function pack_resolve_field_value(string $key, array $pack, array $contractor, bool $useOverrides = true): string
{
    return resolve_field_value($pack, $contractor, $key, $useOverrides);
}

function pack_placeholder_value_map(array $pack, array $contractor, ?array $catalog = null): array
{
    $prefill = static function ($value, int $minLength = 20): string {
        $trim = trim((string)$value);
        return $trim === '' ? str_repeat('_', $minLength) : $trim;
    };

    $map = [];
    $catalog = $catalog ?? pack_field_meta_catalog($pack);
    foreach (array_keys($catalog) as $key) {
        $value = pack_resolve_field_value($key, $pack, $contractor, true);
        $map['{{' . $key . '}}'] = $prefill($value, 6);
        $map['{{field:' . $key . '}}'] = $prefill($value, 6);
    }

    $aliases = pack_field_aliases();
    foreach ($aliases as $alias => $canonical) {
        $value = pack_resolve_field_value($canonical, $pack, $contractor, true);
        $map['{{' . $alias . '}}'] = $prefill($value, 6);
        $map['{{field:' . $alias . '}}'] = $prefill($value, 6);
    }

    $map['{{annexure_title}}'] = '';
    $map['{{annexure_code}}'] = '';

    return $map;
}

function pack_seed_field_registry(array $pack, array $contractor, array $annexureTemplates = [], array $contractorTemplates = []): array
{
    $registry = is_array($pack['fieldRegistry'] ?? null) ? $pack['fieldRegistry'] : [];
    $catalog = pack_field_meta_catalog($pack, $annexureTemplates, $contractorTemplates);
    $profile = pack_profile_placeholder_values($contractor);
    $memory = pack_profile_memory_values((string)($contractor['yojId'] ?? ''));

    foreach (array_keys($catalog) as $key) {
        $normalized = pack_normalize_placeholder_key($key);
        if ($normalized === '') {
            continue;
        }
        if (array_key_exists($normalized, $registry)) {
            continue;
        }
        $registry[$normalized] = trim((string)($memory[$normalized] ?? ($profile[$normalized] ?? '')));
    }

    $pack['fieldRegistry'] = $registry;
    return $pack;
}

function pack_placeholder_suggestion(string $key, array $pack, array $contractor): string
{
    $key = pack_normalize_placeholder_key($key);
    if ($key === 'date') {
        return now_kolkata()->format('Y-m-d');
    }
    if ($key === 'place') {
        $place = trim((string)($contractor['placeDefault'] ?? ''));
        if ($place === '') {
            $place = trim((string)($contractor['district'] ?? ''));
        }
        return $place;
    }
    return '';
}

function pack_extract_placeholders_from_template(array $template, array &$errors = []): array
{
    $keys = [];
    $body = $template['renderTemplate'] ?? ($template['bodyTemplate'] ?? ($template['body'] ?? ''));
    if ($body !== '' && !is_string($body)) {
        $errors[] = 'invalid_body_template';
    } elseif (is_string($body)) {
        $matches = [];
        $matched = preg_match_all('/{{\s*(?:field:)?([a-z0-9_.]+)\s*}}/i', $body, $matches);
        if ($matched === false) {
            $errors[] = 'placeholder_parse_failed';
        } elseif (!empty($matches[1])) {
            foreach ($matches[1] as $raw) {
                $key = pack_normalize_placeholder_key($raw);
                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        }
    }
    if (array_key_exists('placeholders', $template)) {
        if (!is_array($template['placeholders'])) {
            $errors[] = 'invalid_placeholders_list';
        } else {
            foreach ($template['placeholders'] as $placeholder) {
                $key = pack_normalize_placeholder_key((string)$placeholder);
                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        }
    }

    return array_values(array_unique($keys));
}

function pack_collect_pack_fields(array $pack, array $contractor, array $annexureTemplates, array $contractorTemplates = []): array
{
    $errors = [];
    $catalog = pack_field_meta_catalog($pack, $annexureTemplates, $contractorTemplates);
    $required = [];

    foreach ($annexureTemplates as $tpl) {
        if (!is_array($tpl)) {
            $errors[] = 'invalid_template';
            continue;
        }
        foreach ((array)($tpl['requiredFieldKeys'] ?? []) as $key) {
            $normalized = pack_normalize_placeholder_key((string)$key);
            if ($normalized !== '') {
                $required[$normalized] = true;
            }
        }
        foreach ((array)($tpl['requiredFields'] ?? []) as $spec) {
            if (is_array($spec)) {
                $key = pack_normalize_placeholder_key((string)($spec['key'] ?? ''));
                if ($key !== '') {
                    $required[$key] = true;
                }
            }
        }
        foreach ((array)($tpl['tables'] ?? []) as $table) {
            foreach (pack_table_field_keys((array)$table) as $key) {
                $required[$key] = true;
            }
        }
        $found = pack_extract_placeholders_from_template($tpl, $errors);
        foreach ($found as $key) {
            $required[$key] = true;
        }
    }

    foreach ($contractorTemplates as $tpl) {
        if (!is_array($tpl)) {
            continue;
        }
        $found = pack_extract_placeholders_from_template($tpl, $errors);
        foreach ($found as $key) {
            $required[$key] = true;
        }
    }

    if (!$required) {
        foreach (array_keys($catalog) as $key) {
            $required[$key] = true;
        }
    }

    $fields = [];
    foreach (array_keys($required) as $key) {
        if (!isset($catalog[$key])) {
            continue;
        }
        $value = pack_resolve_field_value($key, $pack, $contractor, true);
        $override = trim((string)($pack['fieldRegistry'][$key] ?? ''));
        $fields[] = [
            'key' => $key,
            'label' => $catalog[$key]['label'],
            'group' => $catalog[$key]['group'],
            'type' => $catalog[$key]['type'],
            'max' => $catalog[$key]['max'] ?? 200,
            'choices' => $catalog[$key]['choices'] ?? [],
            'readOnly' => !empty($catalog[$key]['readOnly']),
            'value' => $value,
            'override' => $override,
            'missing' => trim((string)$value) === '',
            'suggestion' => pack_placeholder_suggestion($key, $pack, $contractor),
        ];
    }

    $groups = [];
    foreach ($fields as $field) {
        $groups[$field['group']][] = $field;
    }
    $order = ['Contractor Contact', 'Bank Details', 'Signatory', 'Tender Meta', 'Compliance Table', 'Financial Manual Entry', 'Other'];
    uksort($groups, static function ($a, $b) use ($order) {
        $posA = array_search($a, $order, true);
        $posB = array_search($b, $order, true);
        $posA = $posA === false ? 999 : $posA;
        $posB = $posB === false ? 999 : $posB;
        if ($posA === $posB) {
            return strcmp($a, $b);
        }
        return $posA <=> $posB;
    });

    $filled = 0;
    foreach ($fields as $field) {
        if (!$field['missing']) {
            $filled++;
        }
    }

    return [
        'fields' => $fields,
        'groups' => $groups,
        'errors' => array_values(array_unique($errors)),
        'total' => count($fields),
        'filled' => $filled,
    ];
}

function pack_annexure_placeholder_context(array $pack, array $contractor, array $annexureTemplates = []): array
{
    $catalog = pack_field_meta_catalog($pack, $annexureTemplates);
    return pack_placeholder_value_map($pack, $contractor, $catalog);
}

function pack_field_blank_text(array $meta, string $key): string
{
    $max = (int)($meta[$key]['max'] ?? 12);
    $min = 6;
    $length = max($min, min(24, $max > 0 ? $max : $min));
    return str_repeat('_', $length);
}

function pack_render_field_value_html(string $key, array $pack, array $contractor, array $catalog, string $packId, bool $printMode = false): string
{
    $key = pack_normalize_placeholder_key($key);
    $value = pack_resolve_field_value($key, $pack, $contractor, true);
    $meta = $catalog[$key] ?? ['type' => 'text'];
    $type = strtolower((string)($meta['type'] ?? 'text'));
    if ($type === 'choice') {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return '<span class="choice-empty">☐ Yes ☐ No ☐ N/A</span>';
        }
        return '<span class="field-value">' . htmlspecialchars(ucwords($normalized), ENT_QUOTES, 'UTF-8') . '</span>';
    }
    if (trim($value) === '') {
        $blank = pack_field_blank_text($catalog, $key);
        if ($printMode) {
            return '<span class="field-blank">' . $blank . '</span>';
        }
        $href = '/contractor/pack_view.php?packId=' . urlencode($packId) . '#field-registry';
        return '<a class="field-blank" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" title="Fill ' . htmlspecialchars($meta['label'] ?? $key, ENT_QUOTES, 'UTF-8') . '">' . $blank . '</a>';
    }
    return '<span class="field-value">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</span>';
}

function pack_template_table_placeholders(array $template): array
{
    $body = (string)($template['renderTemplate'] ?? ($template['bodyTemplate'] ?? $template['body'] ?? ''));
    if (trim($body) === '') {
        return [];
    }
    $matches = [];
    $found = preg_match_all('/{{\s*(?:field:)?table:\s*([a-z0-9_.-]+)\s*}}/i', $body, $matches);
    if ($found === false || empty($matches[1])) {
        return [];
    }
    $ids = array_map(static function (string $raw): string {
        return pack_normalize_placeholder_key($raw);
    }, $matches[1]);
    return array_values(array_filter(array_unique($ids)));
}

function pack_render_template_table_html(array $table, array $pack, array $contractor, array $catalog, string $templateKind = '', bool $printMode = false): string
{
    $title = trim((string)($table['title'] ?? ''));
    $columns = is_array($table['columns'] ?? null) ? $table['columns'] : [];
    if (!$columns) {
        return '';
    }
    $templateKind = strtolower(trim($templateKind));
    $html = '<div class="template-table">';
    if ($title !== '') {
        $html .= '<div class="table-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    $tableClass = $templateKind === 'financial_manual' ? 'annexure-table financial-manual-table' : 'annexure-table';
    $html .= '<table class="' . $tableClass . '"><thead><tr>';
    foreach ($columns as $column) {
        $label = $column['label'] ?? $column['key'] ?? '';
        $html .= '<th>' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ((array)($table['rows'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $qtyValue = (string)($row['qty'] ?? ($row['cells']['qty'] ?? ''));
        $html .= '<tr>';
        foreach ($columns as $column) {
            $colKey = pack_normalize_placeholder_key((string)($column['key'] ?? ''));
            $cell = '';
            if ($colKey === '') {
                $html .= '<td></td>';
                continue;
            }
            if ($templateKind === 'financial_manual') {
                $rawValue = (string)($row[$colKey] ?? ($row['cells'][$colKey] ?? ''));
                if (in_array($colKey, ['item_description', 'qty', 'unit'], true)) {
                    $cell = htmlspecialchars(trim($rawValue), ENT_QUOTES, 'UTF-8');
                    if ($cell === '' && $colKey !== 'qty') {
                        $cell = '<span class="field-blank">' . pack_field_blank_text($catalog, $colKey) . '</span>';
                    }
                } elseif ($colKey === 'rate') {
                    $rateKey = pack_table_cell_field_key($row, $colKey);
                    $cell = '<input class="financial-rate" type="number" step="0.01" inputmode="decimal"'
                        . ' data-rate-key="' . htmlspecialchars($rateKey, ENT_QUOTES, 'UTF-8') . '"'
                        . ' data-qty="' . htmlspecialchars(trim($qtyValue), ENT_QUOTES, 'UTF-8') . '">';
                } elseif ($colKey === 'amount') {
                    $cell = '<span class="financial-amount"></span>';
                } else {
                    $cell = htmlspecialchars(trim($rawValue), ENT_QUOTES, 'UTF-8');
                }
            } elseif (!empty($column['readOnly'])) {
                $cell = (string)($row[$colKey] ?? ($row['cells'][$colKey] ?? ''));
                $cell = htmlspecialchars(trim($cell), ENT_QUOTES, 'UTF-8');
            } else {
                $fieldKey = pack_table_cell_field_key($row, $colKey);
                if ($fieldKey !== '') {
                    $cell = pack_render_field_value_html($fieldKey, $pack, $contractor, $catalog, (string)($pack['packId'] ?? ''), $printMode);
                } elseif (isset($row[$colKey])) {
                    $cell = htmlspecialchars(trim((string)$row[$colKey]), ENT_QUOTES, 'UTF-8');
                }
            }
            if ($cell === '') {
                $cell = '<span class="field-blank">' . pack_field_blank_text($catalog, $colKey) . '</span>';
            }
            $html .= '<td>' . $cell . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table></div>';
    return $html;
}

function pack_render_annexure_body_html(array $template, array $pack, array $contractor, array $catalog, bool $printMode = false): string
{
    $body = (string)($template['renderTemplate'] ?? ($template['bodyTemplate'] ?? ''));
    $body = str_replace(
        ['{{annexure_title}}', '{{annexure_code}}'],
        [(string)($template['title'] ?? ''), (string)($template['annexureCode'] ?? '')],
        $body
    );
    $body = preg_replace_callback('/{{\s*(?!field:)([a-z0-9_.]+)\s*}}/i', static function (array $match) use ($catalog): string {
        $key = pack_normalize_placeholder_key($match[1] ?? '');
        if ($key !== '' && isset($catalog[$key])) {
            return '{{field:' . $key . '}}';
        }
        return $match[0];
    }, $body) ?? $body;
    if (!str_contains($body, '{{field:signatory.name}}') && stripos($body, 'authorized signatory') === false) {
        $body .= "\n\nAuthorized Signatory\n{{field:signatory.name}}\n{{field:signatory.designation}}\n{{field:firm.name}}\nPlace: {{field:place}}\nDate: {{field:date}}";
    }
    $tableMap = [];
    foreach ((array)($template['tables'] ?? []) as $table) {
        if (!is_array($table)) {
            continue;
        }
        $tableId = pack_normalize_placeholder_key((string)($table['tableId'] ?? $table['title'] ?? ''));
        if ($tableId === '') {
            continue;
        }
        $tableMap[$tableId] = pack_render_template_table_html($table, $pack, $contractor, $catalog, (string)($template['templateKind'] ?? $template['type'] ?? ''), $printMode);
    }
    $parts = preg_split('/{{\s*([^}]+)\s*}}/i', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return htmlspecialchars($body, ENT_QUOTES, 'UTF-8');
    }
    $packId = (string)($pack['packId'] ?? '');
    $html = '';
    foreach ($parts as $index => $chunk) {
        if ($index % 2 === 0) {
            $html .= htmlspecialchars($chunk, ENT_QUOTES, 'UTF-8');
            continue;
        }
        $raw = trim((string)$chunk);
        if (stripos($raw, 'field:') === 0) {
            $key = pack_normalize_placeholder_key(substr($raw, 6));
            if (stripos($key, 'table:') === 0) {
                $tableId = pack_normalize_placeholder_key(substr($key, 6));
                $html .= $tableMap[$tableId] ?? '<span class="field-blank">' . pack_field_blank_text($catalog, $tableId) . '</span>';
                continue;
            }
            $html .= pack_render_field_value_html($key, $pack, $contractor, $catalog, $packId, $printMode);
            continue;
        }
        if (stripos($raw, 'table:') === 0) {
            $tableId = pack_normalize_placeholder_key(substr($raw, 6));
            $html .= $tableMap[$tableId] ?? '<span class="field-blank">' . pack_field_blank_text($catalog, $tableId) . '</span>';
            continue;
        }
        $html .= '<span class="field-blank">' . pack_field_blank_text($catalog, $raw) . '</span>';
    }
    return $html;
}

function pack_render_template_tables_html(array $template, array $pack, array $contractor, array $catalog, bool $printMode = false): string
{
    $tables = $template['tables'] ?? [];
    if (!is_array($tables) || !$tables) {
        return '';
    }
    $skipTableIds = pack_template_table_placeholders($template);
    $html = '';
    foreach ($tables as $table) {
        if (!is_array($table)) {
            continue;
        }
        $tableId = pack_normalize_placeholder_key((string)($table['tableId'] ?? $table['title'] ?? ''));
        if ($tableId !== '' && in_array($tableId, $skipTableIds, true)) {
            continue;
        }
        $tableHtml = pack_render_template_table_html($table, $pack, $contractor, $catalog, (string)($template['templateKind'] ?? $template['type'] ?? ''), $printMode);
        if ($tableHtml === '') {
            continue;
        }
        $html .= $tableHtml;
    }
    return $html;
}

function pack_financial_manual_templates(array $pack): array
{
    $restricted = $pack['restrictedAnnexures'] ?? [];
    if (!$restricted) {
        return [];
    }

    $templates = [];
    $seen = [];
    foreach ($restricted as $entry) {
        $label = is_array($entry) ? ($entry['title'] ?? ($entry['name'] ?? 'Financial Bid')) : (string)$entry;
        $label = trim($label);
        if ($label === '' || isset($seen[$label])) {
            continue;
        }
        $seen[$label] = true;
        $code = 'Financial-Manual';
        if (preg_match('/annexure\s*-?\s*([0-9a-z]+)/i', $label, $matches)) {
            $code = 'Annexure-' . strtoupper($matches[1]) . '-Manual';
        }
        $body = "Financial/Commercial Bid (Manual Entry)\n"
            . "YOJAK does not calculate or suggest rates. Fill this table manually.\n";
        $rows = [];
        for ($i = 1; $i <= 5; $i++) {
            $rows[] = [
                'rowId' => 'r' . $i,
                'fieldKeys' => [
                    'item_description' => 'financial.row' . $i . '.item_description',
                    'qty' => 'financial.row' . $i . '.qty',
                    'unit' => 'financial.row' . $i . '.unit',
                    'rate' => 'financial.row' . $i . '.rate',
                    'amount' => 'financial.row' . $i . '.amount',
                    'remarks' => 'financial.row' . $i . '.remarks',
                ],
            ];
        }

        $templates[] = [
            'annexId' => 'FIN-' . strtoupper(substr(hash('sha256', $label), 0, 8)),
            'annexureCode' => $code,
            'title' => $label . ' (Manual Entry)',
            'type' => 'financial_manual',
            'templateKind' => 'financial_manual',
            'bodyTemplate' => $body,
            'renderTemplate' => $body,
            'placeholders' => [],
            'requiredFields' => [],
            'requiredFieldKeys' => [],
            'tables' => [
                [
                    'tableId' => 'financial_manual',
                    'title' => 'Manual Entry Table',
                    'columns' => [
                        ['key' => 'item_description', 'label' => 'Item Description', 'type' => 'text'],
                        ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
                        ['key' => 'unit', 'label' => 'Unit', 'type' => 'text'],
                        ['key' => 'rate', 'label' => 'Rate (manual)', 'type' => 'number'],
                        ['key' => 'amount', 'label' => 'Amount (manual)', 'type' => 'number'],
                        ['key' => 'remarks', 'label' => 'Remarks', 'type' => 'text'],
                    ],
                    'rows' => $rows,
                ],
            ],
            'isManualFinancial' => true,
        ];
    }

    return $templates;
}

function pack_financial_manual_field_keys(array $annexureTemplates): array
{
    $blocked = [];
    foreach ($annexureTemplates as $tpl) {
        if (!is_array($tpl)) {
            continue;
        }
        $kind = strtolower(trim((string)($tpl['templateKind'] ?? $tpl['type'] ?? '')));
        if ($kind !== 'financial_manual') {
            continue;
        }
        foreach ((array)($tpl['tables'] ?? []) as $table) {
            if (!is_array($table)) {
                continue;
            }
            $columns = is_array($table['columns'] ?? null) ? $table['columns'] : [];
            foreach ((array)($table['rows'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach ($columns as $column) {
                    if (!is_array($column)) {
                        continue;
                    }
                    $colKey = pack_normalize_placeholder_key((string)($column['key'] ?? ''));
                    if (!in_array($colKey, ['rate', 'amount'], true)) {
                        continue;
                    }
                    $fieldKey = pack_table_cell_field_key($row, $colKey);
                    if ($fieldKey !== '') {
                        $blocked[] = $fieldKey;
                    }
                }
            }
        }
    }
    return array_values(array_unique($blocked));
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
    $financialManualTemplates = pack_financial_manual_templates($pack);
    if ($financialManualTemplates) {
        $annexureTemplates = array_values(array_merge($annexureTemplates, $financialManualTemplates));
    }
    $options = array_merge([
        'includeSnippets' => true,
        'includeRestricted' => true,
        'pendingOnly' => false,
        'useLetterhead' => true,
        'letterheadMode' => 'use_saved_letterhead',
        'pageSize' => 'A4',
        'orientation' => 'portrait',
        'annexureId' => null,
        'templateId' => null,
        'annexurePreview' => false,
        'mode' => 'preview',
        'autoprint' => false,
    ], $options);
    $mode = $options['mode'] === 'print' ? 'print' : 'preview';
    $autoPrint = !empty($options['autoprint']);
    $singleAnnexureId = is_string($options['annexureId']) ? trim($options['annexureId']) : '';
    if ($singleAnnexureId !== '') {
        $annexureTemplates = array_values(array_filter($annexureTemplates, static function ($tpl) use ($singleAnnexureId) {
            return (($tpl['annexId'] ?? '') === $singleAnnexureId) || (($tpl['annexureCode'] ?? '') === $singleAnnexureId);
        }));
    }

    $pageSizes = ['A4', 'Letter', 'Legal'];
    $pageSize = in_array($options['pageSize'], $pageSizes, true) ? $options['pageSize'] : 'A4';
    $orientation = in_array($options['orientation'], ['portrait', 'landscape'], true) ? $options['orientation'] : 'portrait';
    $letterheadMode = in_array($options['letterheadMode'], ['blank_space', 'use_saved_letterhead'], true)
        ? $options['letterheadMode']
        : 'use_saved_letterhead';
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
        $color = $colors[$status] ?? 'var(--muted)';
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
        $html = '<div class="section">';
        if ($showCatalog) {
            if (!$annexures && !$formats) {
                $html .= '<p class="muted">No annexures listed.</p>';
            } else {
                if ($annexures) {
                    $html .= '<ol>';
                    foreach ($annexures as $annex) {
                        $label = is_array($annex) ? ($annex['name'] ?? $annex['title'] ?? 'Annexure') : (string)$annex;
                        $notes = is_array($annex) ? ($annex['notes'] ?? '') : '';
                        $restrictedLabel = pack_is_restricted_annexure_label($label);
                        $html .= '<li><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong>';
                        if ($restrictedLabel) {
                            $html .= '<div class="muted" style="color:#f85149;">Financial/price annexure — manual entry template included.</div>';
                        }
                        if ($notes !== '') {
                            $html .= '<div class="muted">' . htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') . '</div>';
                        }
                        $html .= '</li>';
                    }
                    $html .= '</ol>';
                }
                if ($formats) {
                    $html .= '<ul>';
                    foreach ($formats as $fmt) {
                        $label = is_array($fmt) ? ($fmt['name'] ?? $fmt['title'] ?? 'Format') : (string)$fmt;
                        $html .= '<li>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</li>';
                    }
                    $html .= '</ul>';
                }
            }
        }

        $catalog = pack_field_meta_catalog($pack, $annexureTemplates);
        if ($annexureTemplates) {
            $html .= '<div class="subsection">';
            foreach ($annexureTemplates as $idx => $tpl) {
                $bodyHtml = pack_render_annexure_body_html($tpl, $pack, $contractor, $catalog, true);
                $tablesHtml = pack_render_template_tables_html($tpl, $pack, $contractor, $catalog, true);
                $html .= '<div class="template-block' . ($idx > 0 ? ' page-break' : '') . '">';
                $html .= '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">';
                $html .= '<div><div class="muted">' . htmlspecialchars($tpl['annexureCode'] ?? 'Annexure', ENT_QUOTES, 'UTF-8') . '</div><h3 style="margin:4px 0 6px 0;">' . htmlspecialchars($tpl['title'] ?? 'Annexure', ENT_QUOTES, 'UTF-8') . '</h3></div>';
                $html .= '<span class="pill">' . htmlspecialchars(ucwords(str_replace('_', ' ', $tpl['type'] ?? 'other')), ENT_QUOTES, 'UTF-8') . '</span>';
                $html .= '</div>';
                $html .= '<div class="template-body">' . $bodyHtml . '</div>';
                $html .= $tablesHtml;
                $html .= '</div>';
            }
            $html .= '</div>';
        } else {
            $html .= '<p class="muted">No annexure formats generated yet.</p>';
        }

        if ($showCatalog && $options['includeRestricted'] && $restricted) {
            $html .= '<div class="warning"><strong>Financial/Price Annexures</strong><p>Manual entry formats are included. YOJAK does not calculate or suggest rates.</p><ul>';
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

    $render_index = static function () use ($pack, $contractor, $prefill): string {
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

    $styles = "<style>
    @page{size:{$pageSize} {$orientation};margin:30mm 18mm 20mm;}
    body{font-family:'Segoe UI',Arial,sans-serif;background:var(--surface);color:var(--text);margin:0;padding:24px;}
    .page{max-width:960px;margin:0 auto;background:var(--surface-2);border:1px solid var(--border);border-radius:14px;padding:20px;}
    .printable{display:block;}
    .ui-only{display:block;}
    h1,h2,h3,h4{margin:0 0 8px;}
    .muted{color:var(--muted);}
    table{width:100%;border-collapse:collapse;margin-top:8px;}
    th,td{padding:8px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top;}
    th{color:var(--muted);text-transform:uppercase;font-size:12px;letter-spacing:0.04em;}
    .status{padding:6px 10px;border-radius:20px;font-size:12px;display:inline-block;}
    .section{margin-top:16px;}
    .subsection{margin-top:10px;}
    .warning{border:1px solid var(--danger);padding:10px;border-radius:10px;background:#fef2f2;color:#b91c1c;}
    .template-block{background:var(--surface-2);border:1px solid #1f6feb33;border-radius:12px;padding:14px;margin-top:12px;}
    .template-body{white-space:pre-wrap;line-height:1.6;font-family:inherit;margin-top:8px;}
    .annexure-table{table-layout:fixed;word-break:break-word;}
    .annexure-table th,.annexure-table td{overflow-wrap:anywhere;}
    .financial-manual-table input{width:100%;background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:6px;color:var(--text);}
    .financial-amount{font-weight:600;}
    .template-table{margin-top:12px;}
    .table-title{font-weight:600;margin-bottom:6px;}
    .field-blank{color:var(--text);text-decoration:none;border-bottom:1px solid var(--muted);padding:0 2px;}
    .choice-empty{letter-spacing:0.04em;}
    .card-sm{background:var(--surface-2);border:1px solid var(--border);border-radius:10px;padding:12px;}
    .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;}
    .large{font-size:18px;font-weight:700;}
    .grid-2{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin-top:12px;}
    .plain{list-style:none;padding:0;margin:0;}
    .plain li{margin:4px 0;}
    .pill{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--border);font-size:12px;background:var(--surface-2);}
    .page-break{page-break-before:always;}
    .print-footer{margin-top:20px;font-size:12px;color:var(--muted);text-align:center;min-height:20mm;}
    footer.page-footer{margin-top:6px;font-size:12px;color:var(--muted);text-align:center;min-height:8mm;}
    footer.page-footer .page-number::after{content:'1';}
    .print-header{min-height:24mm;margin-bottom:10px;display:flex;gap:12px;align-items:center;border-bottom:1px solid var(--border);padding-bottom:8px;}
    .print-header .logo{max-width:35mm;max-height:20mm;object-fit:contain;}
    .print-header .blank{height:20mm;}
    .print-meta{margin-bottom:12px;}
    .print-meta-line{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:11px;color:#9CA3AF;}
    .print-meta-line .meta-right{text-align:right;flex:1 1 auto;}
    .print-tender-no{margin-top:4px;font-size:13px;color:#000;}
    .print-tender-title{margin-top:4px;font-size:15px;font-weight:600;color:#000;}
    .print-settings{background:var(--surface-2);border:1px solid var(--border);border-radius:12px;padding:12px;margin-bottom:16px;display:grid;gap:10px;}
    .print-settings .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;}
    .print-settings label{display:grid;gap:6px;font-size:12px;color:var(--muted);}
    .print-settings select,.print-settings input{background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:8px;color:var(--text);}
    .print-settings button{background:var(--primary);border:none;color:var(--primary-contrast);padding:10px 14px;border-radius:8px;font-weight:600;cursor:pointer;}
    .print-settings .hint{font-size:12px;color:var(--muted);}
    .print-actions{display:none;margin-bottom:12px;padding:12px;border:1px solid #d0d7de;border-radius:10px;background:#fff;color:#000;gap:8px;flex-wrap:wrap;align-items:center;justify-content:space-between;}
    .print-actions .btn{background:var(--primary);color:var(--primary-contrast);border:none;padding:10px 14px;border-radius:8px;font-weight:600;cursor:pointer;}
    .print-actions .hint{font-size:12px;color:#444;}
    body.print-mode{background:#fff !important;color:#000 !important;}
    body.print-mode .page,body.print-mode .print-page{background:#fff;border:0;border-radius:0;box-shadow:none;padding:0;}
    body.print-mode h1,body.print-mode h2,body.print-mode h3,body.print-mode h4,body.print-mode strong{color:#000;}
    body.print-mode .muted{color:#444;}
    body.print-mode a{color:#000;text-decoration:none;}
    body.print-mode .print-settings{display:none;}
    body.print-mode .print-actions{display:flex;}
    body.print-mode .card-sm,body.print-mode .template-block,body.print-mode .warning,body.print-mode .pill{background:transparent;border:0;box-shadow:none;color:#000;}
    body.print-mode th,body.print-mode td{border:1px solid #ddd;color:#000;}
    body.print-mode th{background:#f7f7f7;}
    body.print-mode .financial-manual-table input{background:#fff;color:#000;border:1px solid #000;}
    body.print-mode hr{border-top:1px solid #000 !important;}
    @media print{
        body{background:#fff !important;color:#000 !important;padding:0;}
        .page,.print-page{box-shadow:none;border:0;padding:0;background:#fff;}
        a{color:#000;text-decoration:none;}
        .ui-only,.no-print,header,nav,footer:not(.page-footer),.topbar,.actions,.btn,.controls,.toolbar,.sidebar,.panel,.sticky-header,[data-ui=\"true\"]{display:none !important;}
        .print-settings,.print-actions{display:none;}
        .card,.panel,.box,.paper,.shadow,.rounded,.bordered,.container,.section,.card-sm,.template-block,.warning,.pill{background:transparent;border:0 !important;box-shadow:none !important;border-radius:0 !important;}
        th,td{border:1px solid #ddd;color:#000;}
        th{background:#f7f7f7;}
        .financial-manual-table input{background:#fff;color:#000;border:1px solid #000;}
        hr{border-top:1px solid #000 !important;}
        footer.page-footer .page-number::after{content: counter(page);}
    }
    </style>";

    $printSettings = load_contractor_print_settings($pack['yojId']);
    $useLetterhead = $letterheadMode === 'use_saved_letterhead';
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
    $offtdId = $pack['sourceTender']['id'] ?? ($pack['offtdId'] ?? '');
    $packIdLabel = trim((string)($pack['packId'] ?? ''));
    $packIdDisplay = $packIdLabel !== '' ? htmlspecialchars($packIdLabel, ENT_QUOTES, 'UTF-8') : '—';
    $metaRightParts = ['Pack ID: ' . $packIdDisplay];
    if ($offtdId !== '') {
        $metaRightParts[] = 'OFFTD ID: ' . htmlspecialchars($offtdId, ENT_QUOTES, 'UTF-8');
    }
    $tenderNumber = trim((string)($pack['tenderNumber'] ?? ''));
    $tenderTitle = $pack['tenderTitle'] ?? ($pack['title'] ?? 'Tender Pack');
    $header = '<div class="print-header" aria-label="Print header">' . $logoHtml . $headerText . '</div>'
        . '<div class="print-meta">'
        . '<div class="print-meta-line"><div class="meta-left">YOJAK pack</div><div class="meta-right">' . implode(' | ', $metaRightParts) . '</div></div>'
        . ($tenderNumber !== '' ? '<div class="print-tender-no">Tender No: ' . htmlspecialchars($tenderNumber, ENT_QUOTES, 'UTF-8') . '</div>' : '')
        . '<div class="print-tender-title">' . htmlspecialchars($tenderTitle, ENT_QUOTES, 'UTF-8') . '</div>'
        . '</div>';

    $footerText = '';
    if (!empty($printSettings['footerEnabled']) && trim((string)$printSettings['footerText']) !== '') {
        $footerText = '<div style="white-space:pre-wrap;">' . nl2br(htmlspecialchars($printSettings['footerText'], ENT_QUOTES, 'UTF-8')) . '</div>';
    } else {
        $footerText = '<div style="min-height:20mm;"></div>';
    }
    $footer = '<div class="print-footer">' . $footerText . '</div><footer class="page-footer"><span class="page-number"></span></footer>';

    $settingsPanel = '';
    if ($mode === 'preview') {
        $settingsPanel = '<form class="print-settings ui-only" method="post" action="/contractor/pack_print.php" data-ui="true">'
        . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="packId" value="' . htmlspecialchars($pack['packId'] ?? '', ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="doc" value="' . htmlspecialchars($docType, ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="annexId" value="' . htmlspecialchars((string)($options['annexureId'] ?? ''), ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="tplId" value="' . htmlspecialchars((string)($options['templateId'] ?? ''), ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="annexurePreview" value="' . htmlspecialchars(!empty($options['annexurePreview']) ? '1' : '0', ENT_QUOTES, 'UTF-8') . '">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">'
        . '<div><strong>Print settings</strong><div class="hint">In print dialog, keep Scale = 100% for exact sizing.</div><div class="hint no-print">To remove the URL at the bottom: In the print dialog, turn OFF “Headers and footers”.</div></div>'
        . '<button type="submit">Save settings</button>'
        . '</div>'
        . '<div class="grid">'
        . '<label>Page size'
        . '<select name="pageSize">'
        . '<option value="A4"' . ($pageSize === 'A4' ? ' selected' : '') . '>A4</option>'
        . '<option value="Letter"' . ($pageSize === 'Letter' ? ' selected' : '') . '>Letter</option>'
        . '<option value="Legal"' . ($pageSize === 'Legal' ? ' selected' : '') . '>Legal</option>'
        . '</select></label>'
        . '<label>Orientation'
        . '<select name="orientation">'
        . '<option value="portrait"' . ($orientation === 'portrait' ? ' selected' : '') . '>Portrait</option>'
        . '<option value="landscape"' . ($orientation === 'landscape' ? ' selected' : '') . '>Landscape</option>'
        . '</select></label>'
        . '<label>Letterhead mode'
        . '<select name="letterheadMode">'
        . '<option value="blank_space"' . ($letterheadMode === 'blank_space' ? ' selected' : '') . '>Leave blank header/footer space</option>'
        . '<option value="use_saved_letterhead"' . ($letterheadMode === 'use_saved_letterhead' ? ' selected' : '') . '>Use saved letterhead</option>'
        . '</select></label>'
        . '<label>Checklist snippets'
        . '<select name="includeSnippets">'
        . '<option value="1"' . (!empty($options['includeSnippets']) ? ' selected' : '') . '>Include source snippets</option>'
        . '<option value="0"' . (empty($options['includeSnippets']) ? ' selected' : '') . '>Hide snippets</option>'
        . '</select></label>'
        . '</div>'
        . '</form>';
    }

    $printActions = '';

    $rateScript = "<script>
    (() => {
        const rateInputs = Array.from(document.querySelectorAll('.financial-rate'));
        if (!rateInputs.length) {
            return;
        }
        const updateAmount = (input) => {
            const qty = parseFloat(input.dataset.qty || '');
            const rate = parseFloat(input.value || '');
            const amountCell = input.closest('tr')?.querySelector('.financial-amount');
            if (!amountCell) {
                return;
            }
            if (Number.isFinite(qty) && Number.isFinite(rate)) {
                amountCell.textContent = (qty * rate).toFixed(2);
            } else {
                amountCell.textContent = '';
            }
        };
        rateInputs.forEach((input) => {
            updateAmount(input);
            input.addEventListener('input', () => updateAmount(input));
        });
    })();
    </script>";

    $autoPrintScript = '';
    if ($mode === 'print') {
        $autoPrintScript = "<script>
        (() => {
            const printNow = () => window.print();
            if (" . ($autoPrint ? 'true' : 'false') . ") {
                window.addEventListener('load', () => setTimeout(printNow, 300));
            }
        })();
        </script>";
    }

    $bodyClass = $mode === 'print' ? ' class="print-mode"' : '';
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Pack '
        . htmlspecialchars($pack['packId'] ?? 'Pack', ENT_QUOTES, 'UTF-8') . '</title>'
        . $styles . '</head><body' . $bodyClass . '><div class="page print-page">'
        . '<div class="ui-only" data-ui="true">' . $printActions . $settingsPanel . '</div>'
        . '<div class="printable">' . $header
        . implode('<hr class="muted" style="border:none;border-top:1px solid var(--border);margin:16px 0;">', $sections) . $footer . '</div></div>'
        . $rateScript . $autoPrintScript . '</body></html>';

    return $html;
}

function pack_index_html(array $pack, ?array $contractor = null, array $options = [], array $vaultFiles = [], array $annexureTemplates = []): string
{
    if ($contractor === null && !empty($pack['yojId'])) {
        $contractor = load_contractor($pack['yojId']);
    }
    return pack_print_html($pack, $contractor ?? [], 'index', $options, $vaultFiles, $annexureTemplates);
}
