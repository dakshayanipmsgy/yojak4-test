<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../lib/tcpdf/tcpdf.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packId = trim((string)($_GET['packId'] ?? ''));
    $doc = trim((string)($_GET['doc'] ?? 'full'));
    $allowedDocs = ['index', 'checklist', 'annexures', 'templates', 'full'];
    if (!in_array($doc, $allowedDocs, true)) {
        $doc = 'full';
    }

    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);
    $pack = $packId !== '' ? load_pack($yojId, $packId, $context) : null;
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    $logBase = [
        'event' => 'PACK_PDF',
        'at' => now_kolkata()->format(DateTime::ATOM),
        'yojId' => $yojId,
        'packId' => $packId,
        'doc' => $doc,
        'mode' => 'pdf',
    ];

    try {
        $contractor = load_contractor($yojId) ?? [];
        $vaultFiles = contractor_vault_index($yojId);
        $printPrefs = array_merge(default_pack_print_prefs(), $pack['printPrefs'] ?? []);
        $options = [
            'includeSnippets' => $printPrefs['includeSnippets'] ?? true,
            'includeRestricted' => true,
            'pendingOnly' => false,
            'useLetterhead' => false,
            'letterheadMode' => 'blank_space',
            'pageSize' => $printPrefs['pageSize'] ?? 'A4',
            'orientation' => $printPrefs['orientation'] ?? 'portrait',
            'annexureId' => trim((string)($_GET['annexId'] ?? '')) ?: null,
            'templateId' => trim((string)($_GET['tplId'] ?? '')) ?: null,
            'annexurePreview' => false,
            'mode' => 'print',
            'autoprint' => false,
        ];
        $annexureTemplates = load_pack_annexures($yojId, $packId, $context);
        $data = pack_collect_print_sections($pack, $contractor, $doc, $options, $vaultFiles, $annexureTemplates);

        $pack = $data['pack'];
        $contractor = $data['contractor'];
        $sections = $data['sections'];
        $tocEntries = $data['tocEntries'];
        $printedAt = $data['printedAt'];

        $orientation = ($options['orientation'] ?? 'portrait') === 'landscape' ? 'L' : 'P';
        $pageSize = $options['pageSize'] ?? 'A4';
        $pdf = new TCPDF($orientation, 'pt', $pageSize, true, 'UTF-8');
        $pdf->SetCreator('YOJAK');
        $pdf->SetAuthor('YOJAK');
        $pdf->SetTitle('Tender Pack ' . ($pack['packId'] ?? 'Pack'));
        $pdf->SetSubject('Tender Pack Export');
        $pdf->SetMargins(36, 54, 36);
        $pdf->SetAutoPageBreak(true, 54);
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $headerText = trim(($pack['tenderTitle'] ?? $pack['title'] ?? 'Tender Pack') . ' • Pack ID: ' . ($pack['packId'] ?? '') . ' • Contractor: ' . ($contractor['firmName'] ?? ($contractor['name'] ?? '')));
        $pdf->setHeaderText($headerText);
        $pdf->setFooterText('Page {page} of {total}');
        $pdf->SetFont('helvetica', '', 10);

        $tocPage = 0;
        if ($doc === 'full') {
            $pdf->AddPage();
            $tocPage = $pdf->getPage();
        }

        $sectionPageMap = [];
        foreach ($sections as $section) {
            if (!empty($section['isToc'])) {
                continue;
            }
            $pdf->AddPage();
            $pageNumber = $pdf->getPage();
            if (!empty($section['includeInToc'])) {
                $sectionPageMap[$section['title']] = $pageNumber;
            }
            $sectionTitle = $section['title'] ?? '';
            $sectionHtml = '<h2>' . htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8') . '</h2>' . $section['html'];
            $pdf->writeHTML($sectionHtml);
        }

        if ($doc === 'full' && $tocPage > 0) {
            $pdf->clearPage($tocPage);
            $pdf->setPage($tocPage);
            $tocRows = '';
            foreach ($tocEntries as $entry) {
                $title = $entry['title'] ?? '';
                $page = $sectionPageMap[$title] ?? '';
                $tocRows .= '<tr><td>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string)$page, ENT_QUOTES, 'UTF-8') . '</td></tr>';
            }
            $tocHtml = '<h1>Index / Table of Contents</h1>'
                . '<p><strong>Tender:</strong> ' . htmlspecialchars($pack['tenderTitle'] ?? ($pack['title'] ?? 'Tender Pack'), ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><strong>Pack ID:</strong> ' . htmlspecialchars($pack['packId'] ?? '', ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><strong>Tender No:</strong> ' . htmlspecialchars($pack['tenderNumber'] ?? '', ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><strong>Generated:</strong> ' . htmlspecialchars($printedAt, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<table><tr><th>Section</th><th>Start Page</th></tr>' . $tocRows . '</table>';
            $pdf->writeHTML($tocHtml);
        }

        $filename = 'YOJAK_' . sanitize_filename($packId) . '_' . sanitize_filename($pack['tenderNumber'] ?? 'OFFTD') . '.pdf';
        logEvent(PACK_PRINT_LOG, $logBase + ['status' => 'ok', 'message' => 'PDF generated']);
        $pdf->Output($filename, 'I');
    } catch (Throwable $e) {
        logEvent(PACK_PRINT_LOG, $logBase + ['status' => 'fail', 'message' => $e->getMessage()]);
        render_error_page('Unable to generate the PDF right now.');
    }
});

function sanitize_filename(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', $value) ?? '';
    return trim($value, '_') ?: 'OFFTD';
}
