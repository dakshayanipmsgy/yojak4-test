<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_contractor_templates_env($yojId);
    ensure_global_templates_seeded();

    $globalTemplates = array_filter(load_global_templates_full(), static fn($tpl) => ($tpl['status'] ?? 'active') === 'active');
    $myTemplates = array_filter(load_contractor_templates_full($yojId), static fn($tpl) => ($tpl['status'] ?? 'active') === 'active');
    usort($globalTemplates, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));
    usort($myTemplates, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));
    $title = get_app_config()['appName'] . ' | Tender Templates';

    render_layout($title, function () use ($globalTemplates, $myTemplates) {
        ?>
        <div class="card" style="display:grid;gap:14px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Templates Library</h2>
                    <p class="muted" style="margin:4px 0 0;">YOJAK default templates are shared across contractors. Your private templates stay in your account.</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn secondary" href="/contractor/template_request.php">Request Template Help</a>
                    <a class="btn" href="/contractor/template_edit.php">Create My Template</a>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a class="pill" href="#yojak-templates">YOJAK Templates (Default)</a>
                <a class="pill" href="#my-templates">My Templates</a>
            </div>
        </div>

        <div id="yojak-templates" class="card" style="margin-top:14px;display:grid;gap:10px;">
            <div>
                <h3 style="margin:0;">YOJAK Templates (Default)</h3>
                <p class="muted" style="margin:4px 0 0;">Use or duplicate these templates into your private library.</p>
            </div>
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
                <?php if (!$globalTemplates): ?>
                    <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                        <p class="muted" style="margin:0;">No global templates yet.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($globalTemplates as $tpl): ?>
                    <div style="border:1px solid var(--border);border-radius:12px;padding:12px;display:grid;gap:8px;background:var(--surface-2);">
                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                            <div>
                                <h3 style="margin:0 0 4px 0;"><?= sanitize($tpl['title'] ?? 'Template'); ?></h3>
                                <p class="muted" style="margin:0;"><?= sanitize(ucfirst($tpl['category'] ?? 'tender')); ?></p>
                            </div>
                            <span class="pill" style="border-color:#2ea043;color:#8ce99a;">Default</span>
                        </div>
                        <p class="muted" style="margin:0;white-space:pre-wrap;max-height:140px;overflow:auto;"><?= sanitize(mb_substr($tpl['description'] ?? $tpl['body'] ?? '', 0, 240)); ?></p>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a class="btn secondary" href="/contractor/template_preview.php?templateId=<?= sanitize($tpl['templateId']); ?>&scope=global" target="_blank" rel="noopener" style="color:var(--text);">Use / Generate</a>
                            <form method="post" action="/contractor/template_duplicate.php">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="templateId" value="<?= sanitize($tpl['templateId']); ?>">
                                <button class="btn secondary" type="submit">Duplicate</button>
                            </form>
                        </div>
                        <p class="muted" style="margin:0;">Updated: <?= sanitize($tpl['updatedAt'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="my-templates" class="card" style="margin-top:14px;display:grid;gap:10px;">
            <div>
                <h3 style="margin:0;">My Templates</h3>
                <p class="muted" style="margin:4px 0 0;">Create and manage private templates using the guidance editor. No JSON is shown here.</p>
            </div>
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
                <?php if (!$myTemplates): ?>
                    <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                        <p class="muted" style="margin:0;">No templates yet. Create your first one.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($myTemplates as $tpl): ?>
                    <div style="border:1px solid var(--border);border-radius:12px;padding:12px;display:grid;gap:8px;background:var(--surface-2);">
                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                            <div>
                                <h3 style="margin:0 0 4px 0;"><?= sanitize($tpl['title'] ?? 'Template'); ?></h3>
                                <p class="muted" style="margin:0;"><?= sanitize(ucfirst($tpl['category'] ?? 'tender')); ?></p>
                            </div>
                        </div>
                        <p class="muted" style="margin:0;white-space:pre-wrap;max-height:140px;overflow:auto;"><?= sanitize(mb_substr($tpl['description'] ?? $tpl['body'] ?? '', 0, 240)); ?></p>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a class="btn secondary" href="/contractor/template_preview.php?templateId=<?= sanitize($tpl['templateId']); ?>&scope=contractor" target="_blank" rel="noopener" style="color:var(--text);">Use / Generate</a>
                            <a class="btn secondary" href="/contractor/template_edit.php?templateId=<?= sanitize($tpl['templateId']); ?>" style="color:var(--text);">Edit</a>
                        </div>
                        <p class="muted" style="margin:0;">Updated: <?= sanitize($tpl['updatedAt'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
