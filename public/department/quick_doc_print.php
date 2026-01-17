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
            @media print{
                .ui-only,.no-print,header,nav,footer,.topbar,.actions,.btn,.controls,.toolbar,.sidebar,.panel,.sticky-header,[data-ui="true"]{display:none !important;}
                .card{box-shadow:none;border:none;background:#fff;padding:0;}
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
                </div>
            </div>
            <div style="margin-top:12px;border:1px solid var(--border);border-radius:12px;padding:12px;background:var(--surface);">
                <?= $doc['renderedHtml'] ?? ''; ?>
            </div>
        </div>
        <?php
    });
});
