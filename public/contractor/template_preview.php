<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $tplId = trim($_GET['tplId'] ?? '');

    if ($tplId === '') {
        render_error_page('Template not found.');
        return;
    }

    $template = load_contractor_template($yojId, $tplId);
    if (!$template) {
        render_error_page('Template not found.');
        return;
    }

    $contractor = load_contractor($yojId) ?? [];
    $settings = load_contractor_print_settings($yojId);

    $tender = [
        'tender_title' => trim((string)($_GET['tender_title'] ?? '')),
        'tender_number' => trim((string)($_GET['tender_number'] ?? '')),
        'department_name' => trim((string)($_GET['department_name'] ?? '')),
        'submission_deadline' => trim((string)($_GET['submission_deadline'] ?? '')),
        'place' => trim((string)($_GET['place'] ?? '')),
    ];

    $contextMap = contractor_template_context($contractor, $tender);
    foreach ($contextMap as $key => $value) {
        $contextMap[$key] = (string)$value;
    }

    $body = contractor_fill_template_body($template['body'] ?? '', $contextMap);

    logEvent(DATA_PATH . '/logs/templates.log', [
        'event' => 'template_preview',
        'yojId' => $yojId,
        'tplId' => $tplId,
        'ip' => mask_ip($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    ]);

    $title = ($template['name'] ?? 'Template') . ' | Preview';
    $logoHtml = '';
    $headerText = '';
    if (!empty($settings['logoEnabled']) && !empty($settings['logoPublicPath'])) {
        $align = $settings['logoAlign'] ?? 'left';
        $logoHtml = '<div class="print-logo" style="text-align:' . htmlspecialchars($align, ENT_QUOTES, 'UTF-8') . ';">'
            . '<img src="' . htmlspecialchars($settings['logoPublicPath'], ENT_QUOTES, 'UTF-8') . '" alt="Logo"></div>';
    }
    if (!empty($settings['headerEnabled']) && trim((string)($settings['headerText'] ?? '')) !== '') {
        $headerText = '<div class="print-header-text">' . nl2br(htmlspecialchars((string)$settings['headerText'], ENT_QUOTES, 'UTF-8')) . '</div>';
    }
    $footerText = '';
    if (!empty($settings['footerEnabled']) && trim((string)($settings['footerText'] ?? '')) !== '') {
        $footerText = '<div class="print-footer-text">' . nl2br(htmlspecialchars((string)$settings['footerText'], ENT_QUOTES, 'UTF-8')) . '</div>';
    }

    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= sanitize($title); ?></title>
        <style>
            :root{color-scheme:light;}
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;margin:0;background:#f3f4f6;color:#111827;}
            .toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;padding:16px;background:#111827;color:#f9fafb;}
            .toolbar .btn{background:#2563eb;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:600;}
            .toolbar .meta{font-size:14px;color:#d1d5db;}
            .page{max-width:820px;margin:24px auto;padding:24px;background:#fff;box-shadow:0 10px 20px rgba(0,0,0,0.08);border-radius:12px;}
            .print-header{min-height:90px;border-bottom:1px solid #e5e7eb;padding-bottom:12px;display:flex;gap:12px;align-items:center;}
            .print-logo img{max-width:140px;max-height:80px;object-fit:contain;}
            .print-header-text{flex:1;white-space:pre-wrap;font-size:14px;color:#111827;}
            .content{padding:18px 0;white-space:pre-wrap;line-height:1.6;font-size:15px;}
            .print-footer{min-height:60px;border-top:1px solid #e5e7eb;padding-top:10px;white-space:pre-wrap;font-size:13px;color:#4b5563;}
            @media print{
                body{background:#fff;}
                .toolbar{display:none;}
                .page{box-shadow:none;border-radius:0;margin:0;padding:0 18mm;}
                .print-header{min-height:30mm;border-bottom:1px solid #cbd5f5;}
                .print-footer{min-height:20mm;border-top:1px solid #cbd5f5;}
            }
        </style>
    </head>
    <body>
        <div class="toolbar">
            <div>
                <div style="font-size:18px;font-weight:700;"><?= sanitize($template['name'] ?? 'Template'); ?></div>
                <div class="meta"><?= sanitize('Preview uses your saved profile + tender fields. Missing values print as blanks.'); ?></div>
            </div>
            <a class="btn" href="#" onclick="window.print();return false;">Print</a>
        </div>
        <div class="page">
            <div class="print-header">
                <?= $logoHtml; ?>
                <?= $headerText !== '' ? $headerText : '<div class="print-header-text" style="color:#9ca3af;">Header text disabled</div>'; ?>
            </div>
            <div class="content"><?= nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')); ?></div>
            <div class="print-footer">
                <?= $footerText !== '' ? $footerText : '<span style="color:#9ca3af;">Footer text disabled</span>'; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
});
