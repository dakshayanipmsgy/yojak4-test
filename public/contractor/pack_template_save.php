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
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $itemsJson = (string)($_POST['items_json'] ?? '[]');

    if ($title === '' || strlen($title) < 3) {
        set_flash('error', 'Title must be at least 3 characters.');
        redirect('/contractor/pack_template_edit.php' . ($packTemplateId !== '' ? '?packTemplateId=' . urlencode($packTemplateId) : ''));
    }

    $items = [];
    $decoded = json_decode($itemsJson, true);
    if (is_array($decoded)) {
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = $item['type'] ?? 'checklist';
            if ($type === 'templateRef') {
                $templateId = trim((string)($item['templateId'] ?? ''));
                if ($templateId !== '') {
                    $items[] = [
                        'type' => 'templateRef',
                        'templateId' => $templateId,
                        'required' => (bool)($item['required'] ?? true),
                    ];
                }
            } elseif ($type === 'upload') {
                $label = trim((string)($item['label'] ?? ''));
                if ($label !== '') {
                    $items[] = [
                        'type' => 'upload',
                        'label' => $label,
                        'required' => (bool)($item['required'] ?? true),
                        'vaultTagHint' => trim((string)($item['vaultTagHint'] ?? '')),
                    ];
                }
            } else {
                $label = trim((string)($item['label'] ?? ''));
                if ($label !== '') {
                    $items[] = [
                        'type' => 'checklist',
                        'label' => $label,
                        'required' => (bool)($item['required'] ?? true),
                    ];
                }
            }
        }
    }

    $existing = null;
    if ($packTemplateId !== '') {
        $existing = load_contractor_pack_template($yojId, $packTemplateId);
    }

    $payload = [
        'packTemplateId' => $packTemplateId !== '' ? $packTemplateId : generate_pack_template_id(),
        'scope' => 'contractor',
        'owner' => ['yojId' => $yojId],
        'title' => $title,
        'description' => $description,
        'items' => $items,
        'rules' => ['autoCreateOnNewTenderPack' => false],
        'createdAt' => $existing['createdAt'] ?? null,
        'status' => $existing['status'] ?? 'active',
    ];

    save_contractor_pack_template($yojId, $payload);

    logEvent(DATA_PATH . '/logs/packs.log', [
        'event' => $existing ? 'pack_template_updated' : 'pack_template_created',
        'yojId' => $yojId,
        'packTemplateId' => $payload['packTemplateId'],
    ]);

    set_flash('success', 'Pack template saved.');
    redirect('/contractor/packs.php#my-pack-templates');
});
