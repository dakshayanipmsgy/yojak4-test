<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/packs.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packId = trim($_POST['packId'] ?? '');
    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);

    $pack = $packId !== '' ? load_pack($yojId, $packId, $context) : null;
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    $contractor = load_contractor($yojId);
    if (!$contractor) {
        render_error_page('Contractor profile not found.');
        return;
    }

    $payloads = pack_template_payloads($pack, $contractor);
    if (!$payloads) {
        $seeded = seed_default_contractor_templates($yojId);
        if ($seeded) {
            $payloads = pack_template_payloads($pack, $contractor);
        }
    }
    if (!$payloads) {
        set_flash('error', 'No templates available to generate.');
        redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
        return;
    }

    $generatedDir = pack_generated_dir($yojId, $packId, $context) . '/templates';
    if (!is_dir($generatedDir)) {
        mkdir($generatedDir, 0775, true);
    }

    $now = now_kolkata()->format(DateTime::ATOM);
    $generatedTemplates = [];
    foreach ($payloads as $tpl) {
        $tplId = $tpl['tplId'] ?: 'TPL-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $filename = $tplId . '.html';
        $path = $generatedDir . '/' . $filename;
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'
            . htmlspecialchars($tpl['name'] ?? 'Template')
            . '</title><style>body{font-family:Arial,sans-serif;background:var(--surface);color:var(--text);padding:28px;}h1{margin-top:0;}pre{white-space:pre-wrap;font-family:inherit;line-height:1.6;}</style></head><body>'
            . '<h1>' . htmlspecialchars($tpl['name'] ?? 'Template') . '</h1>'
            . '<pre>' . htmlspecialchars($tpl['body'] ?? '') . '</pre>'
            . '</body></html>';
        file_put_contents($path, $html);
        $generatedTemplates[] = [
            'tplId' => $tplId,
            'name' => $tpl['name'] ?? 'Template',
            'storedPath' => str_replace(PUBLIC_PATH, '', $path),
            'lastGeneratedAt' => $now,
        ];
    }

    $pack['generatedTemplates'] = $generatedTemplates;
    $pack['updatedAt'] = $now;
    save_pack($pack, $context);

    pack_log([
        'event' => 'templates_generated',
        'yojId' => $yojId,
        'packId' => $packId,
    ]);

    set_flash('success', 'Templates refreshed using the latest contractor profile.');
    redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
});
