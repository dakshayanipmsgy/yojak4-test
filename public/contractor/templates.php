<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $globalTemplates = array_values(array_filter(
        list_template_library_records('global', null),
        fn($tpl) => ($tpl['status'] ?? 'active') === 'active'
    ));
    $myTemplates = array_values(array_filter(
        list_template_library_records('contractor', $yojId),
        fn($tpl) => ($tpl['status'] ?? 'active') === 'active'
    ));

    $title = get_app_config()['appName'] . ' | Templates Library';

    render_layout($title, function () use ($globalTemplates, $myTemplates) {
        $categories = template_library_categories();
        ?>
        <style>
            .tab-buttons { display:flex; gap:10px; flex-wrap:wrap; }
            .tab-buttons button { border:1px solid var(--border); background:var(--surface); color:var(--text); padding:8px 14px; border-radius:999px; cursor:pointer; }
            .tab-buttons button.active { background:var(--primary); color:#fff; border-color:transparent; }
            .tab-panel { display:none; margin-top:16px; }
            .tab-panel.active { display:block; }
            .template-grid { display:grid; gap:12px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
            .template-card { border:1px solid var(--border); border-radius:12px; padding:12px; background:var(--surface-2); display:grid; gap:8px; }
            .template-meta { display:flex; justify-content:space-between; gap:8px; }
            .template-actions { display:flex; gap:8px; flex-wrap:wrap; }
            .pill { font-size:12px; padding:2px 8px; border-radius:999px; border:1px solid var(--border); }
            .request-card { border:1px solid var(--border); border-radius:12px; padding:16px; background:var(--surface-2); display:grid; gap:12px; }
        </style>
        <div class="card" style="display:grid;gap:16px;">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Templates Library</h2>
                    <p class="muted" style="margin:4px 0 0;">Browse global templates or manage your own private templates. Use the guided editor with placeholder chips.</p>
                </div>
                <a class="btn" href="/contractor/template_new.php">Create New Template</a>
            </div>
            <div class="tab-buttons" role="tablist">
                <button type="button" class="active" data-tab="global">Global Templates</button>
                <button type="button" data-tab="my">My Templates</button>
                <button type="button" data-tab="request">Request a Template</button>
            </div>

            <div class="tab-panel active" data-panel="global">
                <?php if (!$globalTemplates): ?>
                    <div class="card" style="border:1px dashed var(--border);background:var(--surface-2);">
                        <p class="muted" style="margin:0;">No global templates published yet.</p>
                    </div>
                <?php else: ?>
                    <div class="template-grid">
                        <?php foreach ($globalTemplates as $tpl): ?>
                            <div class="template-card">
                                <div class="template-meta">
                                    <div>
                                        <h3 style="margin:0 0 6px 0;"><?= sanitize($tpl['title'] ?? 'Template'); ?></h3>
                                        <p class="muted" style="margin:0;"><?= sanitize($tpl['category'] ?? 'General'); ?> • <?= sanitize($tpl['templateId'] ?? ''); ?></p>
                                    </div>
                                    <span class="pill">Global</span>
                                </div>
                                <p class="muted" style="margin:0;white-space:pre-wrap;max-height:140px;overflow:hidden;">
                                    <?= sanitize(mb_substr($tpl['description'] ?? $tpl['body'] ?? '', 0, 220)); ?>
                                </p>
                                <div class="template-actions">
                                    <form method="post" action="/contractor/template_clone.php">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="templateId" value="<?= sanitize($tpl['templateId'] ?? ''); ?>">
                                        <button class="btn secondary" type="submit">Clone to My Templates</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-panel" data-panel="my">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                    <p class="muted" style="margin:0;">Your private templates are visible only to your account.</p>
                    <a class="btn secondary" href="/contractor/template_new.php">New Template</a>
                </div>
                <div class="template-grid" style="margin-top:12px;">
                    <?php if (!$myTemplates): ?>
                        <div class="card" style="border:1px dashed var(--border);background:var(--surface-2);">
                            <p class="muted" style="margin:0;">No templates yet. Create one or clone from global.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($myTemplates as $tpl): ?>
                        <div class="template-card">
                            <div class="template-meta">
                                <div>
                                    <h3 style="margin:0 0 6px 0;"><?= sanitize($tpl['title'] ?? 'Template'); ?></h3>
                                    <p class="muted" style="margin:0;"><?= sanitize($tpl['category'] ?? 'General'); ?> • <?= sanitize($tpl['templateId'] ?? ''); ?></p>
                                </div>
                                <span class="pill" style="border-color:#2563eb;color:#2563eb;">My</span>
                            </div>
                            <p class="muted" style="margin:0;white-space:pre-wrap;max-height:140px;overflow:hidden;">
                                <?= sanitize(mb_substr($tpl['description'] ?? $tpl['body'] ?? '', 0, 220)); ?>
                            </p>
                            <div class="template-actions">
                                <a class="btn secondary" href="/contractor/template_edit.php?templateId=<?= sanitize($tpl['templateId'] ?? ''); ?>">Edit</a>
                                <form method="post" action="/contractor/template_archive.php" onsubmit="return confirm('Archive this template?');">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                    <input type="hidden" name="templateId" value="<?= sanitize($tpl['templateId'] ?? ''); ?>">
                                    <button class="btn secondary" type="submit">Archive</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="tab-panel" data-panel="request">
                <div class="request-card">
                    <div>
                        <h3 style="margin:0 0 6px 0;">Request a Template from YOJAK Staff</h3>
                        <p class="muted" style="margin:0;">Upload the tender PDF or draft and explain what you need. Staff will create the template for you.</p>
                    </div>
                    <form method="post" action="/contractor/template_request_create.php" enctype="multipart/form-data" style="display:grid;gap:12px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="type" value="template">
                        <label style="display:grid;gap:6px;">
                            <span class="muted">Template Title</span>
                            <input class="input" type="text" name="title" placeholder="e.g., Technical Bid Format" required>
                        </label>
                        <label style="display:grid;gap:6px;">
                            <span class="muted">Notes</span>
                            <textarea class="input" name="notes" rows="4" placeholder="Mention any special instructions"></textarea>
                        </label>
                        <label style="display:grid;gap:6px;">
                            <span class="muted">Category</span>
                            <select class="input" name="category">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= sanitize($category); ?>"><?= sanitize($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label style="display:grid;gap:6px;">
                            <span class="muted">Upload Tender Files (PDF/DOC)</span>
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
