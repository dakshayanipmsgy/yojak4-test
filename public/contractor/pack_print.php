<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packId = trim((string)($_REQUEST['packId'] ?? ''));
    $doc = trim((string)($_REQUEST['doc'] ?? 'index'));
    $allowedDocs = ['index', 'checklist', 'annexures', 'templates', 'full'];
    if (!in_array($doc, $allowedDocs, true)) {
        $doc = 'index';
    }
    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);

    $pack = $packId !== '' ? load_pack($yojId, $packId, $context) : null;
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $pageSize = trim((string)($_POST['pageSize'] ?? 'A4'));
        $orientation = trim((string)($_POST['orientation'] ?? 'portrait'));
        $letterheadMode = trim((string)($_POST['letterheadMode'] ?? 'use_saved_letterhead'));
        $includeSnippets = ($_POST['includeSnippets'] ?? '1') !== '0';
        $includeBranding = ($_POST['includeBranding'] ?? '1') !== '0';
        if (!in_array($pageSize, ['A4', 'Letter', 'Legal'], true)) {
            $pageSize = 'A4';
        }
        if (!in_array($orientation, ['portrait', 'landscape'], true)) {
            $orientation = 'portrait';
        }
        if (!in_array($letterheadMode, ['blank_space', 'use_saved_letterhead'], true)) {
            $letterheadMode = 'use_saved_letterhead';
        }

        $pack['printPrefs'] = [
            'pageSize' => $pageSize,
            'orientation' => $orientation,
            'letterheadMode' => $letterheadMode,
            'includeSnippets' => $includeSnippets,
            'includeBranding' => $includeBranding,
        ];
        $pack['audit'][] = [
            'at' => now_kolkata()->format(DateTime::ATOM),
            'event' => 'PRINT_PREFS_UPDATED',
            'yojId' => $yojId,
        ];
        save_pack($pack, $context);

        $redirectParams = [
            'packId' => $packId,
            'doc' => $doc,
        ];
        $annexId = trim((string)($_POST['annexId'] ?? ''));
        $tplId = trim((string)($_POST['tplId'] ?? ''));
        if ($annexId !== '') {
            $redirectParams['annexId'] = $annexId;
        }
        if ($tplId !== '') {
            $redirectParams['tplId'] = $tplId;
        }
        if (!empty($_POST['annexurePreview'])) {
            $redirectParams['annexurePreview'] = '1';
        }
        redirect('/contractor/pack_print.php?' . http_build_query($redirectParams));
        return;
    }

    $mode = trim((string)($_GET['mode'] ?? 'preview'));
    if (!in_array($mode, ['preview', 'print'], true)) {
        $mode = 'preview';
    }
    $autoPrint = $mode === 'print' && (($_GET['autoprint'] ?? '') === '1');
    $printPrefs = array_merge(default_pack_print_prefs(), $pack['printPrefs'] ?? []);
    $pageSize = trim((string)($_GET['pageSize'] ?? $printPrefs['pageSize']));
    $orientation = trim((string)($_GET['orientation'] ?? $printPrefs['orientation']));
    $letterheadMode = trim((string)($_GET['letterheadMode'] ?? $printPrefs['letterheadMode']));
    $includeBranding = ($_GET['branding'] ?? ($printPrefs['includeBranding'] ? '1' : '0')) !== '0';
    if (!in_array($pageSize, ['A4', 'Letter', 'Legal'], true)) {
        $pageSize = 'A4';
    }
    if (!in_array($orientation, ['portrait', 'landscape'], true)) {
        $orientation = 'portrait';
    }
    if (!in_array($letterheadMode, ['blank_space', 'use_saved_letterhead'], true)) {
        $letterheadMode = 'use_saved_letterhead';
    }
    if (isset($_GET['letterhead'])) {
        $letterheadMode = ($_GET['letterhead'] ?? '1') === '0' ? 'blank_space' : 'use_saved_letterhead';
    }

    $contractor = load_contractor($yojId) ?? [];
    $vaultFiles = contractor_vault_index($yojId);
    $options = [
        'includeSnippets' => ($_GET['snippets'] ?? ($printPrefs['includeSnippets'] ? '1' : '0')) !== '0',
        'includeRestricted' => ($_GET['restricted'] ?? '1') !== '0',
        'pendingOnly' => ($_GET['pendingOnly'] ?? '') === '1',
        'useLetterhead' => $letterheadMode === 'use_saved_letterhead',
        'letterheadMode' => $letterheadMode,
        'pageSize' => $pageSize,
        'orientation' => $orientation,
        'annexureId' => trim((string)($_GET['annexId'] ?? '')) ?: null,
        'templateId' => trim((string)($_GET['tplId'] ?? '')) ?: null,
        'annexurePreview' => ($_GET['annexurePreview'] ?? '') === '1',
        'mode' => $mode,
        'autoprint' => $autoPrint,
        'includeBranding' => $includeBranding,
    ];
    $annexureTemplates = load_pack_annexures($yojId, $packId, $context);
    $manifest = build_print_manifest($pack, $contractor, $doc, $options, $annexureTemplates);
    logEvent(PACK_PRINT_LOG, [
        'event' => 'PACK_PRINT_RENDER',
        'at' => now_kolkata()->format(DateTime::ATOM),
        'yojId' => $yojId,
        'packId' => $packId,
        'mode' => $mode,
        'doc' => $doc,
        'sectionsCount' => count($manifest),
    ]);
    $html = pack_print_html($pack, $contractor, $doc, $options, $vaultFiles, $annexureTemplates);
    logEvent(PACK_PRINT_LOG, [
        'event' => 'PACK_PRINT',
        'at' => now_kolkata()->format(DateTime::ATOM),
        'yojId' => $yojId,
        'packId' => $packId,
        'doc' => $doc,
        'mode' => $mode,
        'autoprint' => $autoPrint ? 1 : 0,
        'letterhead' => $letterheadMode,
        'pageSize' => $pageSize,
        'orientation' => $orientation,
        'annexureId' => $options['annexureId'],
    ]);
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
});
