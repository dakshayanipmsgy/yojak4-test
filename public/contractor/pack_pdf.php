<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once BASE_PATH . '/lib/vendor/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packId = trim((string)($_GET['packId'] ?? ''));
    $doc = trim((string)($_GET['doc'] ?? 'index'));
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
        'mode' => 'print',
        'autoprint' => false,
        'includeBranding' => $includeBranding,
        'pdfMode' => true,
    ];

    $annexureTemplates = load_pack_annexures($yojId, $packId, $context);
    $html = pack_print_html($pack, $contractor, $doc, $options, $vaultFiles, $annexureTemplates);

    $dompdfOptions = new Options();
    $dompdfOptions->set('isRemoteEnabled', true);
    $dompdfOptions->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($dompdfOptions);
    $dompdf->setPaper($pageSize, $orientation);
    $dompdf->loadHtml($html);
    $dompdf->render();

    $now = now_kolkata()->format('Ymd');
    $filename = 'Pack_' . $packId . '_' . $doc . '_' . $now . '.pdf';
    $pdf = $dompdf->output();

    logEvent(PACK_PRINT_LOG, [
        'event' => 'PACK_PRINT_PDF',
        'at' => now_kolkata()->format(DateTime::ATOM),
        'yojId' => $yojId,
        'packId' => $packId,
        'doc' => $doc,
    ]);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
});
