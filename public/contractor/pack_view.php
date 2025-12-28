<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packId = trim($_GET['packId'] ?? '');
    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);

    $pack = $packId !== '' ? load_pack($yojId, $packId, $context) : null;
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }

    $progress = pack_progress_percent($pack);
    $stats = pack_stats($pack);
    $title = get_app_config()['appName'] . ' | ' . ($pack['title'] ?? 'Tender Pack');
    $signedToken = pack_signed_token($pack['packId'], $yojId);

    render_layout($title, function () use ($pack, $progress, $stats, $signedToken) {
        ?>
        <div class="card" style="display:grid; gap:10px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize($pack['title'] ?? 'Tender Pack'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;">
                        <?= sanitize($pack['packId']); ?> • <?= sanitize($pack['status'] ?? 'Pending'); ?>
                        <?php if (!empty($pack['sourceTender']['id'])): ?>
                            • <?= sanitize(($pack['sourceTender']['type'] ?? '') . ' ' . ($pack['sourceTender']['id'] ?? '')); ?>
                        <?php endif; ?>
                    </p>
                    <?php if (($pack['source'] ?? '') === 'dept'): ?>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
                            <span class="pill"><?= sanitize('Department Published'); ?></span>
                            <?php if (!empty($pack['prefillApplied'])): ?>
                                <span class="pill success"><?= sanitize('Prefill applied'); ?></span>
                            <?php else: ?>
                                <span class="pill"><?= sanitize('Prefill not applied'); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($pack['requirementSetApplied'])): ?>
                                <span class="pill success"><?= sanitize('Official checklist applied'); ?></span>
                            <?php elseif (!empty($pack['officialChecklistLocked'])): ?>
                                <span class="pill"><?= sanitize('Official checklist locked (link dept to unlock)'); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="buttons" style="gap:8px;">
                    <a class="btn secondary" href="/contractor/packs.php"><?= sanitize('Back to packs'); ?></a>
                    <a class="btn secondary" href="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>" target="_blank" rel="noopener"><?= sanitize('Print index'); ?></a>
                    <a class="btn" href="/contractor/pack_export_zip.php?packId=<?= sanitize($pack['packId']); ?>&token=<?= sanitize($signedToken); ?>"><?= sanitize('Export ZIP'); ?></a>
                </div>
            </div>
            <div style="display:grid; gap:8px; max-width:520px;">
                <div style="height:12px; background:#0d1117; border:1px solid #30363d; border-radius:999px; overflow:hidden;">
                    <div style="width:<?= $progress; ?>%; height:100%; background:linear-gradient(90deg, var(--primary), #2ea043);"></div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <span class="pill"><?= sanitize($stats['doneRequired'] . '/' . $stats['requiredItems'] . ' required complete'); ?></span>
                    <span class="pill"><?= sanitize($stats['generatedDocs'] . ' generated docs'); ?></span>
                </div>
            </div>
        </div>

        <form id="status-form" method="post" action="/contractor/pack_mark_status.php"></form>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:12px; margin-top:12px;">
            <div class="card" style="display:grid; gap:12px;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                    <h3 style="margin:0;"><?= sanitize('Pack items'); ?></h3>
                    <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-end;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>" form="status-form">
                        <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>" form="status-form">
                        <button class="btn secondary" type="submit" form="status-form"><?= sanitize('Save statuses'); ?></button>
                        <div style="font-size:12px; color:var(--muted); text-align:right;"><?= sanitize('Update multiple items together'); ?></div>
                    </div>
                </div>
                <?php if (!empty($pack['officialChecklistLocked'])): ?>
                    <div class="flash" style="background:#0f1625;border:1px solid #1f6feb;">
                        <?= sanitize('Official checklist available. Link to the department to auto-load it.'); ?>
                        <a class="btn secondary" style="margin-left:8px;" href="/contractor/departments.php"><?= sanitize('Link now'); ?></a>
                    </div>
                <?php endif; ?>
                <div style="display:grid; gap:10px;">
                    <?php foreach ($pack['items'] ?? [] as $item): ?>
                        <div style="border:1px solid #30363d; border-radius:12px; padding:10px; display:grid; gap:8px;">
                            <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                                <div>
                                    <strong><?= sanitize($item['title'] ?? ''); ?></strong>
                                    <div class="muted" style="margin-top:4px;"><?= sanitize($item['description'] ?? ''); ?></div>
                                    <div class="pill" style="margin-top:6px;"><?= !empty($item['required']) ? sanitize('Required') : sanitize('Optional'); ?></div>
                                </div>
                                <select name="statuses[<?= sanitize($item['itemId']); ?>]" class="pill" style="min-width:140px;" form="status-form">
                                    <?php foreach (['pending','uploaded','generated','done'] as $status): ?>
                                        <option value="<?= sanitize($status); ?>" <?= ($item['status'] ?? '') === $status ? 'selected' : ''; ?>><?= sanitize(ucfirst($status)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                <form method="post" action="/contractor/pack_upload_item.php" enctype="multipart/form-data" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                    <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                                    <input type="hidden" name="itemId" value="<?= sanitize($item['itemId']); ?>">
                                    <label class="btn secondary" style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                                        <input type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png" multiple required style="position:absolute; width:1px; height:1px; opacity:0;" onchange="this.form.submit();">
                                        <?= sanitize('Upload'); ?>
                                    </label>
                                    <div class="muted"><?= sanitize(count($item['fileRefs'] ?? []) . ' files'); ?></div>
                                </form>
                                <?php foreach (($item['fileRefs'] ?? []) as $file): ?>
                                    <a class="pill" href="<?= sanitize($file['path'] ?? '#'); ?>" target="_blank" rel="noopener"><?= sanitize($file['name'] ?? 'File'); ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card" style="display:grid; gap:12px;">
                <h3 style="margin:0;"><?= sanitize('Generate letters'); ?></h3>
                <p class="muted" style="margin:0;">Simple placeholders. Regenerate anytime.</p>
                <form method="post" action="/contractor/pack_generate_docs.php" style="display:grid; gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                    <label class="pill" style="display:inline-flex; gap:6px; align-items:center;">
                        <input type="checkbox" name="docs[]" value="cover" checked> <?= sanitize('Cover letter'); ?>
                    </label>
                    <label class="pill" style="display:inline-flex; gap:6px; align-items:center;">
                        <input type="checkbox" name="docs[]" value="undertaking"> <?= sanitize('Undertaking/Declaration'); ?>
                    </label>
                    <button class="btn" type="submit"><?= sanitize('Generate'); ?></button>
                </form>
                <div>
                    <h4 style="margin:0 0 8px 0;"><?= sanitize('Generated documents'); ?></h4>
                    <div style="display:grid; gap:6px;">
                        <?php foreach ($pack['generatedDocs'] ?? [] as $doc): ?>
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; border:1px solid #30363d; padding:8px 10px; border-radius:10px;">
                                <div>
                                    <strong><?= sanitize($doc['title'] ?? 'Document'); ?></strong>
                                    <div class="muted" style="margin-top:4px;"><?= sanitize($doc['generatedAt'] ?? ''); ?></div>
                                </div>
                                <a class="btn secondary" href="<?= sanitize($doc['path'] ?? '#'); ?>" target="_blank" rel="noopener"><?= sanitize('Open'); ?></a>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($pack['generatedDocs'])): ?>
                            <p class="muted" style="margin:0;"><?= sanitize('No generated documents yet.'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card" style="background:#111820; border-color:#1f6feb;">
                    <h4 style="margin:0 0 6px 0;"><?= sanitize('Export & print'); ?></h4>
                    <p class="muted" style="margin:0;">Export ZIP with uploaded files, generated docs, and a printable index.</p>
                    <div class="buttons" style="margin-top:8px; gap:8px;">
                        <a class="btn" href="/contractor/pack_export_zip.php?packId=<?= sanitize($pack['packId']); ?>&token=<?= sanitize($signedToken); ?>"><?= sanitize('Download ZIP'); ?></a>
                        <a class="btn secondary" href="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>" target="_blank" rel="noopener"><?= sanitize('Open print view'); ?></a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    });
});
