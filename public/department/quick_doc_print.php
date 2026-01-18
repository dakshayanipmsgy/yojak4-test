<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $deptId = $user['deptId'] ?? '';
    ensure_department_env($deptId);
    require_department_permission($user, 'generate_docs');

    $docId = trim($_GET['docId'] ?? '');
    if ($docId === '') {
        render_error_page('Document not found.');
        return;
    }

    $path = department_generated_doc_path($deptId, $docId);
    if (!file_exists($path)) {
        render_error_page('Document not found.');
        return;
    }

    $doc = readJson($path);
    if (!$doc) {
        render_error_page('Document invalid.');
        return;
    }

    $title = get_app_config()['appName'] . ' | ' . ($doc['docTitle'] ?? 'Doc');
    render_layout($title, function () use ($doc) {
        ?>
        <style>
            .print-hint{font-size:12px;color:var(--muted);}
            .print-footer-bar{display:none;}
            @media print{
                @page{margin-bottom:18mm;}
                .ui-only,.no-print,header,nav,footer,.topbar,.actions,.btn,.controls,.toolbar,.sidebar,.panel,.sticky-header,[data-ui="true"]{display:none !important;}
                .card{box-shadow:none;border:none;background:#fff;padding:0;}
                .printable{padding-bottom:20mm;}
                .print-footer-bar{position:fixed;bottom:0;left:0;right:0;display:flex;justify-content:space-between;align-items:flex-end;font-size:10px;color:#9CA3AF;padding:0 18mm 6mm;}
                .print-footer-bar .page-number::before{content:"Page " counter(page);}
            }
        </style>
        <div class="card printable">
            <div class="ui-only" data-ui="true" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize($doc['docTitle'] ?? 'Document'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Template: ' . ($doc['templateId'] ?? '')); ?></p>
                </div>
                <div class="buttons ui-only no-print" data-ui="true">
                    <button class="btn secondary no-print" type="button" onclick="window.print();"><?= sanitize('Print'); ?></button>
                    <a class="btn secondary no-print" href="/department/quick_doc.php"><?= sanitize('Back'); ?></a>
                    <div class="print-hint no-print"><?= sanitize('For clean PDF: In print dialog, turn OFF “Headers and footers” to remove URL/date/time.'); ?></div>
                </div>
            </div>
            <div style="margin-top:12px;border:1px solid var(--border);border-radius:12px;padding:12px;background:var(--surface);">
                <?= $doc['renderedHtml'] ?? ''; ?>
            </div>
        </div>
        <div class="print-footer-bar" aria-hidden="true">
            <div>yojak.co.in</div>
            <div class="page-number"></div>
        </div>
        <?php
    });
});
