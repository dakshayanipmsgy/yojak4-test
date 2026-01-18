<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $schemeId = trim($_GET['schemeId'] ?? '');
    if ($schemeId === '') {
        render_error_page('Scheme ID missing.');
        return;
    }
    if (!scheme_has_access($yojId, $schemeId)) {
        render_error_page('Scheme access not enabled.');
        return;
    }

    $scheme = scheme_load_metadata($schemeId);
    $definition = scheme_load_definition($schemeId);
    if (!$scheme || !$definition) {
        render_error_page('Scheme not available.');
        return;
    }

    $entities = $definition['entities'] ?? [];
    $stats = [];
    foreach ($entities as $entity) {
        $key = $entity['key'] ?? '';
        if ($key === '') {
            continue;
        }
        $records = scheme_load_records($yojId, $schemeId, $key);
        $counts = ['total' => count($records)];
        foreach ($records as $record) {
            $status = $record['status'] ?? 'unknown';
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        $stats[$key] = $counts;
    }

    $startEntity = $definition['workflow']['startEntity'] ?? ($entities[0]['key'] ?? '');

    $title = get_app_config()['appName'] . ' | Scheme Dashboard';
    render_layout($title, function () use ($scheme, $schemeId, $entities, $stats, $startEntity) {
        ?>
        <div class="card" style="display:grid;gap:18px;">
            <div>
                <h2 style="margin:0;"><?= sanitize($scheme['name'] ?? 'Scheme'); ?></h2>
                <p class="muted" style="margin:6px 0 0;"><?= sanitize($scheme['shortDescription'] ?? ''); ?></p>
            </div>

            <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                <?php foreach ($entities as $entity): ?>
                    <?php
                    $key = $entity['key'] ?? '';
                    $label = $entity['label'] ?? $key;
                    $counts = $stats[$key] ?? ['total' => 0];
                    ?>
                    <div class="card" style="background:var(--surface-2);">
                        <p class="muted" style="margin:0 0 6px 0;"><?= sanitize($label); ?></p>
                        <strong style="font-size:22px;"><?= sanitize((string)($counts['total'] ?? 0)); ?></strong>
                        <div class="muted" style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;">
                            <?php foreach ($counts as $status => $count): ?>
                                <?php if ($status === 'total') continue; ?>
                                <span><?= sanitize($status); ?>: <?= sanitize((string)$count); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php if ($startEntity): ?>
                    <a class="btn" href="/contractor/scheme_record_view.php?schemeId=<?= urlencode($schemeId); ?>&entity=<?= urlencode($startEntity); ?>">Add <?= sanitize($startEntity); ?></a>
                <?php endif; ?>
                <?php foreach ($entities as $entity): ?>
                    <?php $key = $entity['key'] ?? ''; ?>
                    <?php if ($key === '') continue; ?>
                    <a class="btn secondary" href="/contractor/scheme_records.php?schemeId=<?= urlencode($schemeId); ?>&entity=<?= urlencode($key); ?>">View <?= sanitize($entity['label'] ?? $key); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
