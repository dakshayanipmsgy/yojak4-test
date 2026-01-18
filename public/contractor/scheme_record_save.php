<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    require_csrf();
    $yojId = $user['yojId'];

    $schemeId = trim($_POST['schemeId'] ?? '');
    $entityKey = trim($_POST['entity'] ?? '');
    $recordId = trim($_POST['recordId'] ?? '');
    $status = trim($_POST['status'] ?? 'draft');
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

    $data = [];
    foreach (($_POST['field'] ?? []) as $key => $value) {
        $key = trim((string)$key);
        if ($key === '') {
            continue;
        }
        scheme_set_path_value($data, $key, trim((string)$value));
    }

    $items = [];
    $itemData = $_POST['items'] ?? [];
    $columns = ['item_description', 'qty', 'unit', 'rate', 'amount'];
    $rows = 0;
    foreach ($columns as $column) {
        $count = is_array($itemData[$column] ?? null) ? count($itemData[$column]) : 0;
        $rows = max($rows, $count);
    }
    for ($i = 0; $i < $rows; $i++) {
        $row = [];
        foreach ($columns as $column) {
            $row[$column] = trim((string)($itemData[$column][$i] ?? ''));
        }
        if (implode('', $row) !== '') {
            $items[] = $row;
        }
    }

    $now = now_kolkata()->format(DateTime::ATOM);
    $record = null;
    if ($recordId !== '') {
        $record = scheme_load_record($yojId, $schemeId, $entityKey, $recordId);
    }
    if (!$record) {
        $recordId = scheme_generate_record_id((string)($entity['idPrefix'] ?? strtoupper($entityKey)));
        $record = [
            'recordId' => $recordId,
            'schemeId' => $schemeId,
            'entity' => $entityKey,
            'createdAt' => $now,
            'createdBy' => $user['username'] ?? $yojId,
        ];
    }

    $record['status'] = $status;
    $record['data'] = $data;
    $record['tables'] = ['items' => $items];
    $record['updatedAt'] = $now;

    scheme_save_record($yojId, $schemeId, $entityKey, $record);
    scheme_log_usage($yojId, $schemeId, 'RECORD_CREATE', [
        'recordId' => $record['recordId'],
        'entity' => $entityKey,
    ]);

    redirect('/contractor/scheme_record_view.php?schemeId=' . urlencode($schemeId) . '&entity=' . urlencode($entityKey) . '&id=' . urlencode($record['recordId']));
});
