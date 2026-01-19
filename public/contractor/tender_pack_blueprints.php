<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $global = array_filter(pack_blueprint_list('global'), static function (array $bp): bool {
        return !empty($bp['published']) && empty($bp['archived']);
    });
    $mine = array_filter(pack_blueprint_list('contractor', $yojId), static function (array $bp): bool {
        return empty($bp['archived']);
    });
    $requests = array_values(array_filter(request_list('pack'), static function (array $req) use ($yojId): bool {
        return ($req['from']['yojId'] ?? '') === $yojId;
    }));

    $title = get_app_config()['appName'] . ' | Tender Pack Blueprints';
    render_layout($title, function () use ($global, $mine, $requests) {
        ?>
        <div class="card" style="display:grid;gap:14px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize('Tender Pack Blueprints'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Reusable packs with checklist items, required fields, and templates.'); ?></p>
                </div>
                <a class="btn" href="/contractor/pack_blueprint_new.php"><?= sanitize('Create Blueprint'); ?></a>
            </div>
            <div class="tabs">
                <button class="tab active" data-tab="global"><?= sanitize('YOJAK Packs'); ?></button>
                <button class="tab" data-tab="mine"><?= sanitize('My Packs'); ?></button>
                <button class="tab" data-tab="request"><?= sanitize('Request a Pack'); ?></button>
            </div>

            <div class="tab-content active" id="tab-global">
                <p class="muted"><?= sanitize('Read-only pack blueprints provided by YOJAK.'); ?></p>
                <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
                    <?php if (!$global): ?>
                        <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                            <p class="muted" style="margin:0;"><?= sanitize('No global packs yet.'); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($global as $bp): ?>
                        <div style="border:1px solid var(--border);border-radius:12px;padding:12px;display:grid;gap:8px;background:var(--surface-2);">
                            <div>
                                <h3 style="margin:0 0 4px 0;"><?= sanitize($bp['title'] ?? 'Pack'); ?></h3>
                                <p class="muted" style="margin:0;"><?= sanitize($bp['description'] ?? ''); ?></p>
                            </div>
                            <span class="pill"><?= sanitize(count($bp['items']['checklist'] ?? []) . ' checklist items'); ?></span>
                            <form method="post" action="/contractor/pack_blueprint_use.php">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="id" value="<?= sanitize($bp['id'] ?? ''); ?>">
                                <button class="btn secondary" type="submit"><?= sanitize('Create Pack from Blueprint'); ?></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="tab-content" id="tab-mine">
                <p class="muted"><?= sanitize('Your private pack blueprints.'); ?></p>
                <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
                    <?php if (!$mine): ?>
                        <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                            <p class="muted" style="margin:0;"><?= sanitize('No pack blueprints yet.'); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($mine as $bp): ?>
                        <div style="border:1px solid var(--border);border-radius:12px;padding:12px;display:grid;gap:8px;background:var(--surface-2);">
                            <div>
                                <h3 style="margin:0 0 4px 0;"><?= sanitize($bp['title'] ?? 'Pack'); ?></h3>
                                <p class="muted" style="margin:0;"><?= sanitize($bp['description'] ?? ''); ?></p>
                            </div>
                            <span class="pill"><?= sanitize(count($bp['items']['checklist'] ?? []) . ' checklist items'); ?></span>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <a class="btn secondary" href="/contractor/pack_blueprint_edit.php?id=<?= sanitize($bp['id'] ?? ''); ?>"><?= sanitize('Edit'); ?></a>
                                <form method="post" action="/contractor/pack_blueprint_use.php">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                    <input type="hidden" name="id" value="<?= sanitize($bp['id'] ?? ''); ?>">
                                    <button class="btn secondary" type="submit"><?= sanitize('Create Pack'); ?></button>
                                </form>
                                <form method="post" action="/contractor/pack_blueprint_delete.php" onsubmit="return confirm('Archive this blueprint?');">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                    <input type="hidden" name="id" value="<?= sanitize($bp['id'] ?? ''); ?>">
                                    <button class="btn danger" type="submit"><?= sanitize('Archive'); ?></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="tab-content" id="tab-request">
                <div class="card" style="background:var(--surface-2);">
                    <h3 style="margin-top:0;"><?= sanitize('Request a Pack'); ?></h3>
                    <p class="muted"><?= sanitize('Upload the tender PDF and describe the pack blueprint you need.'); ?></p>
                    <form method="post" action="/contractor/template_requests_create.php" enctype="multipart/form-data" style="display:grid;gap:10px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="type" value="pack">
                        <label>
                            <?= sanitize('Pack title') ?>
                            <input type="text" name="title" required>
                        </label>
                        <label>
                            <?= sanitize('Notes for staff') ?>
                            <textarea name="notes" rows="4" required></textarea>
                        </label>
                        <label>
                            <?= sanitize('Tender PDF (optional)') ?>
                            <input type="file" name="attachment">
                        </label>
                        <button class="btn" type="submit"><?= sanitize('Submit request'); ?></button>
                    </form>
                </div>
                <h4 style="margin:14px 0 6px;"><?= sanitize('Your recent requests'); ?></h4>
                <div style="display:grid;gap:8px;">
                    <?php if (!$requests): ?>
                        <p class="muted" style="margin:0;"><?= sanitize('No requests yet.'); ?></p>
                    <?php endif; ?>
                    <?php foreach ($requests as $req): ?>
                        <div style="border:1px solid var(--border);border-radius:10px;padding:10px;display:grid;gap:4px;">
                            <strong><?= sanitize($req['title'] ?? 'Request'); ?></strong>
                            <span class="muted"><?= sanitize('Status: ' . request_status_label((string)($req['status'] ?? 'new'))); ?></span>
                            <span class="muted"><?= sanitize('Updated: ' . ($req['updatedAt'] ?? '')); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <style>
            .tabs{display:flex;gap:8px;flex-wrap:wrap;}
            .tab{border:1px solid var(--border);background:var(--surface-2);padding:6px 12px;border-radius:999px;cursor:pointer;color:var(--text);}
            .tab.active{border-color:#1f6feb;background:#0b1f3a;color:#fff;}
            .tab-content{display:none;}
            .tab-content.active{display:block;}
        </style>
        <script>
            document.querySelectorAll('.tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    tab.classList.add('active');
                    const target = document.getElementById('tab-' + tab.dataset.tab);
                    if (target) {
                        target.classList.add('active');
                    }
                });
            });
        </script>
        <?php
    });
});
