<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $globalPacks = array_values(array_filter(
        list_pack_template_records('global', null),
        fn($pack) => ($pack['status'] ?? 'active') === 'active'
    ));
    $myPacks = array_values(array_filter(
        list_pack_template_records('contractor', $yojId),
        fn($pack) => ($pack['status'] ?? 'active') === 'active'
    ));

    $title = get_app_config()['appName'] . ' | Pack Templates';
    render_layout($title, function () use ($globalPacks, $myPacks) {
        ?>
        <style>
            .tab-buttons { display:flex; gap:10px; flex-wrap:wrap; }
            .tab-buttons button { border:1px solid var(--border); background:var(--surface); color:var(--text); padding:8px 14px; border-radius:999px; cursor:pointer; }
            .tab-buttons button.active { background:var(--primary); color:#fff; border-color:transparent; }
            .tab-panel { display:none; margin-top:16px; }
            .tab-panel.active { display:block; }
            .pack-grid { display:grid; gap:12px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
            .pack-card { border:1px solid var(--border); border-radius:12px; padding:12px; background:var(--surface-2); display:grid; gap:8px; }
            .pack-actions { display:flex; gap:8px; flex-wrap:wrap; }
            .pill { font-size:12px; padding:2px 8px; border-radius:999px; border:1px solid var(--border); }
        </style>
        <div class="card" style="display:grid;gap:16px;">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Pack Templates Library</h2>
                    <p class="muted" style="margin:4px 0 0;">Build reusable tender packs and clone global packs.</p>
                </div>
                <a class="btn" href="/contractor/pack_template_new.php">Create Pack Template</a>
            </div>
            <div class="tab-buttons" role="tablist">
                <button type="button" class="active" data-tab="global">Global Pack Templates</button>
                <button type="button" data-tab="my">My Pack Templates</button>
                <button type="button" data-tab="request">Request a Pack Template</button>
            </div>

            <div class="tab-panel active" data-panel="global">
                <?php if (!$globalPacks): ?>
                    <div class="card" style="border:1px dashed var(--border);background:var(--surface-2);">
                        <p class="muted" style="margin:0;">No global pack templates available.</p>
                    </div>
                <?php else: ?>
                    <div class="pack-grid">
                        <?php foreach ($globalPacks as $pack): ?>
                            <div class="pack-card">
                                <div style="display:flex;justify-content:space-between;gap:8px;">
                                    <div>
                                        <h3 style="margin:0 0 6px 0;"><?= sanitize($pack['title'] ?? 'Pack'); ?></h3>
                                        <p class="muted" style="margin:0;"><?= sanitize($pack['packTemplateId'] ?? ''); ?></p>
                                    </div>
                                    <span class="pill">Global</span>
                                </div>
                                <p class="muted" style="margin:0;max-height:140px;overflow:hidden;">
                                    <?= sanitize(mb_substr($pack['description'] ?? '', 0, 220)); ?>
                                </p>
                                <div class="pack-actions">
                                    <form method="post" action="/contractor/pack_template_clone.php">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="packTemplateId" value="<?= sanitize($pack['packTemplateId'] ?? ''); ?>">
                                        <button class="btn secondary" type="submit">Clone to My Packs</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-panel" data-panel="my">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                    <p class="muted" style="margin:0;">Private pack templates are visible only to your account.</p>
                    <a class="btn secondary" href="/contractor/pack_template_new.php">New Pack Template</a>
                </div>
                <div class="pack-grid" style="margin-top:12px;">
                    <?php if (!$myPacks): ?>
                        <div class="card" style="border:1px dashed var(--border);background:var(--surface-2);">
                            <p class="muted" style="margin:0;">No pack templates yet. Create one or clone a global pack.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($myPacks as $pack): ?>
                        <div class="pack-card">
                            <div style="display:flex;justify-content:space-between;gap:8px;">
                                <div>
                                    <h3 style="margin:0 0 6px 0;"><?= sanitize($pack['title'] ?? 'Pack'); ?></h3>
                                    <p class="muted" style="margin:0;"><?= sanitize($pack['packTemplateId'] ?? ''); ?></p>
                                </div>
                                <span class="pill" style="border-color:#2563eb;color:#2563eb;">My</span>
                            </div>
                            <p class="muted" style="margin:0;max-height:140px;overflow:hidden;">
                                <?= sanitize(mb_substr($pack['description'] ?? '', 0, 220)); ?>
                            </p>
                            <div class="pack-actions">
                                <a class="btn secondary" href="/contractor/pack_template_edit.php?packTemplateId=<?= sanitize($pack['packTemplateId'] ?? ''); ?>">Edit</a>
                                <form method="post" action="/contractor/pack_template_archive.php" onsubmit="return confirm('Archive this pack template?');">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                    <input type="hidden" name="packTemplateId" value="<?= sanitize($pack['packTemplateId'] ?? ''); ?>">
                                    <button class="btn secondary" type="submit">Archive</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="tab-panel" data-panel="request">
                <div class="card" style="border:1px solid var(--border);background:var(--surface-2);display:grid;gap:12px;">
                    <div>
                        <h3 style="margin:0 0 6px 0;">Request a Pack Template</h3>
                        <p class="muted" style="margin:0;">Share tender files and a checklist so staff can build a pack for you.</p>
                    </div>
                    <form method="post" action="/contractor/template_request_create.php" enctype="multipart/form-data" style="display:grid;gap:12px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="type" value="pack">
                        <label style="display:grid;gap:6px;">
                            <span class="muted">Pack Title</span>
                            <input class="input" type="text" name="title" placeholder="e.g., Standard Tender Pack" required>
                        </label>
                        <label style="display:grid;gap:6px;">
                            <span class="muted">Notes</span>
                            <textarea class="input" name="notes" rows="4" placeholder="Mention checklists or documents to include"></textarea>
                        </label>
                        <label style="display:grid;gap:6px;">
                            <span class="muted">Upload Tender Files</span>
                            <input class="input" type="file" name="attachments[]" multiple>
                        </label>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <button class="btn" type="submit">Submit Request</button>
                            <a class="btn secondary" href="/contractor/template_requests.php">View My Requests</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
            document.querySelectorAll('.tab-buttons button').forEach(button => {
                button.addEventListener('click', () => {
                    document.querySelectorAll('.tab-buttons button').forEach(btn => btn.classList.remove('active'));
                    document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
                    button.classList.add('active');
                    const target = button.getAttribute('data-tab');
                    const panel = document.querySelector(`.tab-panel[data-panel="${target}"]`);
                    if (panel) {
                        panel.classList.add('active');
                    }
                });
            });
        </script>
        <?php
    });
});
