<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $user = require_role('contractor');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        render_error_page('Invalid request.');
        return;
    }
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $yojId = $user['yojId'];
    $packTemplateId = trim((string)($_POST['packTemplateId'] ?? ''));
    if ($packTemplateId === '') {
        set_flash('error', 'Pack template not found.');
        redirect('/contractor/packs.php');
    }

    $template = load_contractor_pack_template($yojId, $packTemplateId);
    if (!$template) {
        $template = load_global_pack_template($packTemplateId);
    }
    if (!$template) {
        set_flash('error', 'Pack template not found.');
        redirect('/contractor/packs.php');
    }

    $items = [];
    foreach (($template['items'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $type = $item['type'] ?? 'checklist';
        $required = (bool)($item['required'] ?? true);
        if ($type === 'templateRef') {
            $templateId = trim((string)($item['templateId'] ?? ''));
            if ($templateId === '') {
                continue;
            }
            $templateTitle = $templateId;
            $candidate = load_contractor_template($yojId, $templateId) ?? load_global_template($templateId);
            if ($candidate) {
                $templateTitle = $candidate['title'] ?? $templateId;
            }
            $items[] = [
                'itemId' => generate_pack_item_id(),
                'title' => 'Generate: ' . $templateTitle,
                'description' => 'Template document',
                'required' => $required,
                'status' => 'pending',
                'category' => 'Forms',
            ];
        } elseif ($type === 'upload') {
            $label = trim((string)($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $items[] = [
                'itemId' => generate_pack_item_id(),
                'title' => $label,
                'description' => 'Upload required document.',
                'required' => $required,
                'status' => 'pending',
                'category' => pack_infer_category($label),
            ];
        } else {
            $label = trim((string)($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $items[] = [
                'itemId' => generate_pack_item_id(),
                'title' => $label,
                'description' => '',
                'required' => $required,
                'status' => 'pending',
                'category' => pack_infer_category($label),
            ];
        }
    }

    $packId = generate_pack_id($yojId, 'tender');
    $now = now_kolkata()->format(DateTime::ATOM);
    $pack = [
        'packId' => $packId,
        'yojId' => $yojId,
        'title' => $template['title'] ?? 'Tender Pack',
        'tenderTitle' => $template['title'] ?? 'Tender Pack',
        'items' => $items,
        'checklist' => [],
        'createdAt' => $now,
        'updatedAt' => $now,
        'source' => 'template',
        'sourcePackTemplateId' => $packTemplateId,
    ];

    save_pack($pack, 'tender');
    logEvent(DATA_PATH . '/logs/packs.log', [
        'event' => 'pack_created_from_template',
        'yojId' => $yojId,
        'packId' => $packId,
        'packTemplateId' => $packTemplateId,
    ]);

    set_flash('success', 'Pack created from template.');
    redirect('/contractor/pack_view.php?packId=' . urlencode($packId));
});
