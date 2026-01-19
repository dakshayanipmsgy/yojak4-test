<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_packs_env($yojId);
    ensure_contractor_pack_templates_env($yojId);
    ensure_global_pack_templates_env();

    $packs = packs_index($yojId);
    usort($packs, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));
    $globalPackTemplates = array_filter(load_global_pack_templates_full(), static fn($tpl) => ($tpl['status'] ?? 'active') === 'active');
    $myPackTemplates = array_filter(load_contractor_pack_templates_full($yojId), static fn($tpl) => ($tpl['status'] ?? 'active') === 'active');

    $title = get_app_config()['appName'] . ' | Tender Packs';

    render_layout($title, function () use ($packs, $globalPackTemplates, $myPackTemplates) {
        ?>
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize('Tender Packs'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('One-click packs for offline tenders. Upload items, generate letters, export ZIP.'); ?></p>
                </div>
                <a class="btn secondary" href="/contractor/offline_tenders.php"><?= sanitize('Back to OFFTD'); ?></a>
            </div>
        </div>
        <div class="card" style="margin-top:12px;display:grid;gap:12px;">
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;">
                <div>
                    <h3 style="margin:0;">Pack Templates</h3>
                    <p class="muted" style="margin:4px 0 0;">Use templates to speed up repeated tender submissions.</p>
                </div>
                <a class="btn" href="/contractor/pack_template_edit.php">Create My Pack Template</a>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a class="pill" href="#default-pack-templates">Default Pack Templates</a>
                <a class="pill" href="#my-pack-templates">My Pack Templates</a>
            </div>
        </div>
        <div id="default-pack-templates" class="card" style="margin-top:12px;display:grid;gap:10px;">
            <div>
                <h4 style="margin:0;">Default Pack Templates</h4>
                <p class="muted" style="margin:4px 0 0;">Global pack templates maintained by YOJAK staff.</p>
            </div>
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
                <?php if (!$globalPackTemplates): ?>
                    <div class="card">
                        <p class="muted" style="margin:0;">No default pack templates yet.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($globalPackTemplates as $tpl): ?>
                    <div class="card" style="display:grid;gap:8px;background:var(--surface-2);">
                        <div>
                            <h4 style="margin:0;"><?= sanitize($tpl['title'] ?? 'Pack Template'); ?></h4>
                            <p class="muted" style="margin:4px 0 0;"><?= sanitize(mb_substr($tpl['description'] ?? '', 0, 140)); ?></p>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <form method="post" action="/contractor/pack_template_use.php">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="packTemplateId" value="<?= sanitize($tpl['packTemplateId']); ?>">
                                <button class="btn secondary" type="submit">Use Template to Create Pack</button>
                            </form>
                        </div>
                        <p class="muted" style="margin:0;">Updated: <?= sanitize($tpl['updatedAt'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div id="my-pack-templates" class="card" style="margin-top:12px;display:grid;gap:10px;">
            <div>
                <h4 style="margin:0;">My Pack Templates</h4>
                <p class="muted" style="margin:4px 0 0;">Private pack templates created by you.</p>
            </div>
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
                <?php if (!$myPackTemplates): ?>
                    <div class="card">
                        <p class="muted" style="margin:0;">No pack templates yet.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($myPackTemplates as $tpl): ?>
                    <div class="card" style="display:grid;gap:8px;background:var(--surface-2);">
                        <div>
                            <h4 style="margin:0;"><?= sanitize($tpl['title'] ?? 'Pack Template'); ?></h4>
                            <p class="muted" style="margin:4px 0 0;"><?= sanitize(mb_substr($tpl['description'] ?? '', 0, 140)); ?></p>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <form method="post" action="/contractor/pack_template_use.php">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="packTemplateId" value="<?= sanitize($tpl['packTemplateId']); ?>">
                                <button class="btn secondary" type="submit">Use Template to Create Pack</button>
                            </form>
                            <a class="btn secondary" href="/contractor/pack_template_edit.php?packTemplateId=<?= sanitize($tpl['packTemplateId']); ?>">Edit</a>
                        </div>
                        <p class="muted" style="margin:0;">Updated: <?= sanitize($tpl['updatedAt'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="display:grid; gap:12px; margin-top:12px;">
            <?php if (!$packs): ?>
                <div class="card">
                    <p class="muted" style="margin:0;"><?= sanitize('No tender packs yet. Create from an offline tender.'); ?></p>
                </div>
            <?php endif; ?>
            <?php foreach ($packs as $pack): ?>
                <div class="card" style="display:grid; gap:8px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                        <div>
                            <h3 style="margin:0;"><?= sanitize($pack['title'] ?? 'Tender Pack'); ?></h3>
                            <p class="muted" style="margin:4px 0 0;">
                                <?= sanitize($pack['packId'] ?? ''); ?> • <?= sanitize($pack['status'] ?? 'Pending'); ?>
                                <?php if (!empty($pack['sourceTender']['id'])): ?>
                                    • <?= sanitize($pack['sourceTender']['type'] ?? ''); ?> <?= sanitize($pack['sourceTender']['id']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <a class="btn secondary" href="/contractor/pack_view.php?packId=<?= sanitize($pack['packId']); ?>"><?= sanitize('Open'); ?></a>
                        </div>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                        <div style="flex:1; min-width:220px;">
                            <?php
                            $required = (int)($pack['requiredItems'] ?? 0);
                            $done = (int)($pack['doneRequired'] ?? 0);
                            $progress = $required > 0 ? (int)round(($done / max(1, $required)) * 100) : 0;
                            ?>
                            <div style="position:relative; background:var(--surface); border:1px solid var(--border); border-radius:12px; height:12px; overflow:hidden;">
                                <div style="width:<?= $progress; ?>%; background:var(--primary); height:100%;"></div>
                            </div>
                        </div>
                        <div class="pill"><?= sanitize(($pack['doneRequired'] ?? 0) . '/' . ($pack['requiredItems'] ?? 0) . ' required'); ?></div>
                        <div class="pill"><?= sanitize(($pack['generatedDocs'] ?? 0) . ' generated'); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    });
});
