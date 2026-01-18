<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $schemeId = trim($_GET['schemeId'] ?? '');
    $entityKey = trim($_GET['entity'] ?? '');
    if ($schemeId === '' || $entityKey === '') {
        render_error_page('Scheme or entity missing.');
        return;
    }
    if (!scheme_has_access($yojId, $schemeId)) {
        render_error_page('Scheme access not enabled.');
        return;
    }

    $definition = scheme_load_definition($schemeId);
    if (!$definition) {
        render_error_page('Scheme not available.');
        return;
    }

    $entityLabel = $entityKey;
    foreach ($definition['entities'] ?? [] as $entity) {
        if (($entity['key'] ?? '') === $entityKey) {
            $entityLabel = $entity['label'] ?? $entityKey;
            break;
        }
    }

    $records = scheme_load_records($yojId, $schemeId, $entityKey);

    $title = get_app_config()['appName'] . ' | Scheme Records';
    render_layout($title, function () use ($schemeId, $entityKey, $entityLabel, $records) {
        ?>
        <div class="card" style="display:grid;gap:14px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize($entityLabel); ?> Records</h2>
                    <p class="muted" style="margin:6px 0 0;">Scheme: <?= sanitize($schemeId); ?></p>
                </div>
                <a class="btn" href="/contractor/scheme_record_view.php?schemeId=<?= urlencode($schemeId); ?>&entity=<?= urlencode($entityKey); ?>">Add Record</a>
            </div>

            <?php if (!$records): ?>
                <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                    <p class="muted" style="margin:0;">No records found yet.</p>
                </div>
            <?php else: ?>
                <div style="overflow:auto;">
                    <table style="width:100%;border-collapse:collapse;min-width:520px;">
                        <thead>
                        <tr style="text-align:left;border-bottom:1px solid var(--border);">
                            <th style="padding:8px;">Record ID</th>
                            <th style="padding:8px;">Status</th>
                            <th style="padding:8px;">Updated</th>
                            <th style="padding:8px;">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($records as $record): ?>
                            <tr style="border-bottom:1px solid var(--border);">
                                <td style="padding:8px;"><?= sanitize($record['recordId'] ?? ''); ?></td>
                                <td style="padding:8px;"><span class="pill"><?= sanitize($record['status'] ?? ''); ?></span></td>
                                <td style="padding:8px;" class="muted"><?= sanitize($record['updatedAt'] ?? ''); ?></td>
                                <td style="padding:8px;">
                                    <a class="btn secondary" href="/contractor/scheme_record_view.php?schemeId=<?= urlencode($schemeId); ?>&entity=<?= urlencode($entityKey); ?>&id=<?= urlencode($record['recordId'] ?? ''); ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    });
});
