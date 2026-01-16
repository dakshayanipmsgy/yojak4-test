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
    $mode = trim((string)($_GET['mode'] ?? 'preview'));
    if (!in_array($mode, ['preview', 'print'], true)) {
        $mode = 'preview';
    }
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        logEvent(PACK_PRINT_LOG, [
            'event' => 'PACK_PRINT_ERROR',
            'at' => now_kolkata()->format(DateTime::ATOM),
            'yojId' => $yojId,
            'packId' => $packId,
            'doc' => $doc,
            'mode' => $mode,
            'autoprint' => 0,
        ]);
        if ($mode === 'print') {
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Pack not found</title>'
                . '<link rel="stylesheet" href="/assets/css/print.css"></head>'
                . '<body class="print-mode"><div class="print-area" style="padding:24px;">'
                . '<h1 style="margin:0 0 8px 0;">Pack not found</h1>'
                . '<p style="margin:0;">The requested tender pack could not be located.</p>'
                . '</div></body></html>';
            return;
        }
        render_error_page('Pack not found.');
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $pageSize = trim((string)($_POST['pageSize'] ?? 'A4'));
        $orientation = trim((string)($_POST['orientation'] ?? 'portrait'));
        $letterheadMode = trim((string)($_POST['letterheadMode'] ?? 'use_saved_letterhead'));
        $includeSnippets = ($_POST['includeSnippets'] ?? '1') !== '0';
        $density = trim((string)($_POST['density'] ?? 'normal'));
        if (!in_array($pageSize, ['A4', 'Letter', 'Legal'], true)) {
            $pageSize = 'A4';
        }
        if (!in_array($orientation, ['portrait', 'landscape'], true)) {
            $orientation = 'portrait';
        }
        if (!in_array($letterheadMode, ['blank_space', 'use_saved_letterhead'], true)) {
            $letterheadMode = 'use_saved_letterhead';
        }
        if (!in_array($density, ['normal', 'compact', 'dense'], true)) {
            $density = 'normal';
        }

        $pack['printPrefs'] = [
            'pageSize' => $pageSize,
            'orientation' => $orientation,
            'letterheadMode' => $letterheadMode,
            'includeSnippets' => $includeSnippets,
            'density' => $density,
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

    $autoPrint = $mode === 'print' && (($_GET['autoprint'] ?? '') === '1');
    $printPrefs = array_merge(default_pack_print_prefs(), $pack['printPrefs'] ?? []);
    $pageSize = trim((string)($_GET['pageSize'] ?? $printPrefs['pageSize']));
    $orientation = trim((string)($_GET['orientation'] ?? $printPrefs['orientation']));
    $letterheadMode = trim((string)($_GET['letterheadMode'] ?? $printPrefs['letterheadMode']));
    $density = trim((string)($_GET['density'] ?? $printPrefs['density']));
    if (!in_array($pageSize, ['A4', 'Letter', 'Legal'], true)) {
        $pageSize = 'A4';
    }
    if (!in_array($orientation, ['portrait', 'landscape'], true)) {
        $orientation = 'portrait';
    }
    if (!in_array($letterheadMode, ['blank_space', 'use_saved_letterhead'], true)) {
        $letterheadMode = 'use_saved_letterhead';
    }
    if (!in_array($density, ['normal', 'compact', 'dense'], true)) {
        $density = 'normal';
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
        'density' => $density,
    ];
    $annexureTemplates = load_pack_annexures($yojId, $packId, $context);
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
    logEvent(DATA_PATH . '/logs/print_fix.log', [
        'event' => 'PRINT_RENDER',
        'at' => now_kolkata()->format(DateTime::ATOM),
        'yojId' => $yojId,
        'packId' => $packId,
        'doc' => $doc,
        'mode' => $mode,
        'density' => $density,
    ]);
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
});
