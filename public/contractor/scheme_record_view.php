<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $schemeId = trim($_GET['schemeId'] ?? '');
    $entityKey = trim($_GET['entity'] ?? '');
    $recordId = trim($_GET['id'] ?? '');
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
        render_error_page('Scheme not compiled.');
        return;
    }

    $entity = null;
    foreach ($definition['entities'] ?? [] as $item) {
        if (($item['key'] ?? '') === $entityKey) {
            $entity = $item;
            break;
        }
    }
    if (!$entity) {
        render_error_page('Entity not found.');
        return;
    }

    $record = null;
    if ($recordId !== '') {
        $record = scheme_load_record($yojId, $schemeId, $entityKey, $recordId);
    }
    if (!$record) {
        $record = [
            'recordId' => '',
            'schemeId' => $schemeId,
            'entity' => $entityKey,
            'status' => $entity['statuses'][0] ?? 'draft',
            'data' => [],
            'tables' => ['items' => []],
        ];
    }

    $fields = $entity['fields'] ?? [];
    if (!is_array($fields)) {
        $fields = [];
    }

    $title = get_app_config()['appName'] . ' | Record';
    render_layout($title, function () use ($schemeId, $entityKey, $entity, $fields, $record) {
        $itemRows = $record['tables']['items'] ?? [];
        if (!is_array($itemRows)) {
            $itemRows = [];
        }
        $defaultRows = max(3, count($itemRows));
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div>
                <h2 style="margin:0;"><?= sanitize($entity['label'] ?? $entityKey); ?> Record</h2>
                <p class="muted" style="margin:6px 0 0;">Scheme: <?= sanitize($schemeId); ?></p>
            </div>
            <form method="post" action="/contractor/scheme_record_save.php" style="display:grid;gap:14px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="schemeId" value="<?= sanitize($schemeId); ?>">
                <input type="hidden" name="entity" value="<?= sanitize($entityKey); ?>">
                <input type="hidden" name="recordId" value="<?= sanitize($record['recordId'] ?? ''); ?>">

                <label style="display:grid;gap:6px;">
                    <span>Status</span>
                    <select class="input" name="status">
                        <?php foreach ($entity['statuses'] ?? ['draft'] as $status): ?>
                            <option value="<?= sanitize($status); ?>" <?= ($record['status'] ?? '') === $status ? 'selected' : ''; ?>><?= sanitize($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
                    <?php foreach ($fields as $field): ?>
                        <?php
                        if (str_contains((string)$field, 'items')) {
                            continue;
                        }
                        $value = scheme_get_path_value($record['data'] ?? [], (string)$field);
                        ?>
                        <label style="display:grid;gap:6px;">
                            <span><?= sanitize(scheme_label_from_key((string)$field)); ?></span>
                            <input class="input" name="field[<?= sanitize((string)$field); ?>]" value="<?= sanitize((string)($value ?? '')); ?>">
                        </label>
                    <?php endforeach; ?>
                </div>

                <div style="display:grid;gap:8px;">
                    <h3 style="margin:0;">Items Table</h3>
                    <div style="overflow:auto;">
                        <table style="width:100%;border-collapse:collapse;min-width:640px;">
                            <thead>
                            <tr style="border-bottom:1px solid var(--border);text-align:left;">
                                <th style="padding:8px;">Item Description</th>
                                <th style="padding:8px;">Qty</th>
                                <th style="padding:8px;">Unit</th>
                                <th style="padding:8px;">Rate</th>
                                <th style="padding:8px;">Amount</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php for ($i = 0; $i < $defaultRows; $i++): ?>
                                <?php $row = $itemRows[$i] ?? []; ?>
                                <tr style="border-bottom:1px solid var(--border);">
                                    <td style="padding:8px;"><input class="input" name="items[item_description][]" value="<?= sanitize((string)($row['item_description'] ?? '')); ?>"></td>
                                    <td style="padding:8px;"><input class="input" name="items[qty][]" value="<?= sanitize((string)($row['qty'] ?? '')); ?>"></td>
                                    <td style="padding:8px;"><input class="input" name="items[unit][]" value="<?= sanitize((string)($row['unit'] ?? '')); ?>"></td>
                                    <td style="padding:8px;"><input class="input" name="items[rate][]" value="<?= sanitize((string)($row['rate'] ?? '')); ?>"></td>
                                    <td style="padding:8px;"><input class="input" name="items[amount][]" value="<?= sanitize((string)($row['amount'] ?? '')); ?>"></td>
                                </tr>
                            <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button class="btn" type="submit">Save Record</button>
                    <?php if (!empty($record['recordId'])): ?>
                        <a class="btn secondary" href="/contractor/scheme_docs.php?schemeId=<?= urlencode($schemeId); ?>&recordId=<?= urlencode($record['recordId']); ?>&entity=<?= urlencode($entityKey); ?>">Generate Documents</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php
    });
});
