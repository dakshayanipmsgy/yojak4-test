<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $schemeId = trim($_GET['schemeId'] ?? '');
    $recordId = trim($_GET['recordId'] ?? '');
    $entityKey = trim($_GET['entity'] ?? '');
    if ($schemeId === '' || $recordId === '') {
        render_error_page('Scheme or record missing.');
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

    if ($entityKey === '') {
        foreach ($definition['entities'] ?? [] as $entity) {
            $candidate = scheme_load_record($yojId, $schemeId, $entity['key'] ?? '', $recordId);
            if ($candidate) {
                $entityKey = $entity['key'] ?? '';
                break;
            }
        }
    }

    $record = scheme_load_record($yojId, $schemeId, $entityKey, $recordId);
    if (!$record) {
        render_error_page('Record not found.');
        return;
    }

    $documents = array_values(array_filter($definition['documents'] ?? [], fn($doc) => ($doc['attachToEntity'] ?? '') === $entityKey));
    $portal = $definition['customerPortal'] ?? [];
    $portalEnabled = !empty($portal['enabled']);
    $portalToken = $record['portalToken'] ?? '';

    $title = get_app_config()['appName'] . ' | Scheme Documents';
    render_layout($title, function () use ($schemeId, $recordId, $entityKey, $documents, $portalEnabled, $portal, $portalToken) {
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div>
                <h2 style="margin:0;">Generated Documents</h2>
                <p class="muted" style="margin:6px 0 0;">Record: <?= sanitize($recordId); ?> • Scheme: <?= sanitize($schemeId); ?></p>
            </div>

            <?php if (!$documents): ?>
                <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                    <p class="muted" style="margin:0;">No documents configured for this entity.</p>
                </div>
            <?php endif; ?>

            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
                <?php foreach ($documents as $doc): ?>
                    <div style="border:1px solid var(--border);border-radius:12px;padding:12px;background:var(--surface-2);display:grid;gap:8px;">
                        <div>
                            <h3 style="margin:0 0 4px 0;"><?= sanitize($doc['label'] ?? $doc['docId'] ?? 'Document'); ?></h3>
                            <p class="muted" style="margin:0;">Doc ID: <?= sanitize($doc['docId'] ?? ''); ?></p>
                        </div>
                        <a class="btn secondary" href="/contractor/scheme_print.php?schemeId=<?= urlencode($schemeId); ?>&docId=<?= urlencode($doc['docId'] ?? ''); ?>&recordId=<?= urlencode($recordId); ?>&entity=<?= urlencode($entityKey); ?>&mode=print" target="_blank" rel="noopener">View & Print</a>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($portalEnabled): ?>
                <div style="border-top:1px solid var(--border);padding-top:12px;display:grid;gap:10px;">
                    <h3 style="margin:0;">Customer Portal</h3>
                    <p class="muted" style="margin:0;">Visible docs: <?= sanitize(implode(', ', $portal['visibleDocs'] ?? [])); ?></p>
                    <?php if ($portalToken): ?>
                        <div class="card" style="background:var(--surface-2);display:grid;gap:6px;">
                            <p class="muted" style="margin:0;">Active link:</p>
                            <a href="/site/customer.php?t=<?= urlencode($portalToken); ?>" target="_blank" rel="noopener"><?= sanitize('/site/customer.php?t=' . $portalToken); ?></a>
                            <form method="post" action="/contractor/scheme_portal_revoke.php" style="margin-top:8px;">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="schemeId" value="<?= sanitize($schemeId); ?>">
                                <input type="hidden" name="recordId" value="<?= sanitize($recordId); ?>">
                                <input type="hidden" name="entity" value="<?= sanitize($entityKey); ?>">
                                <input type="hidden" name="token" value="<?= sanitize($portalToken); ?>">
                                <button class="btn secondary" type="submit">Revoke Link</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <form method="post" action="/contractor/scheme_portal_publish.php" style="display:grid;gap:8px;max-width:360px;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <input type="hidden" name="schemeId" value="<?= sanitize($schemeId); ?>">
                            <input type="hidden" name="recordId" value="<?= sanitize($recordId); ?>">
                            <input type="hidden" name="entity" value="<?= sanitize($entityKey); ?>">
                            <button class="btn" type="submit">Publish Customer Portal Link</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    });
});
