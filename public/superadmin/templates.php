<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('templates_manage');
    $templates = template_list('global');
    $requests = request_list('template');
    $title = get_app_config()['appName'] . ' | Global Templates';

    render_layout($title, function () use ($templates, $requests, $actor) {
        $isSuperadmin = ($actor['type'] ?? '') === 'superadmin';
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize('Global Templates'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Manage global templates available to all contractors.'); ?></p>
                </div>
                <a class="btn" href="/superadmin/template_edit.php"><?= sanitize('New Template'); ?></a>
            </div>
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
                <?php if (!$templates): ?>
                    <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                        <p class="muted" style="margin:0;"><?= sanitize('No global templates yet.'); ?></p>
                    </div>
                <?php endif; ?>
                <?php foreach ($templates as $tpl): ?>
                    <div style="border:1px solid var(--border);border-radius:12px;padding:12px;display:grid;gap:8px;background:var(--surface-2);">
                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                            <div>
                                <h3 style="margin:0 0 4px 0;"><?= sanitize($tpl['title'] ?? 'Template'); ?></h3>
                                <p class="muted" style="margin:0;"><?= sanitize($tpl['category'] ?? 'Other'); ?></p>
                            </div>
                            <?php if (!empty($tpl['published'])): ?>
                                <span class="pill" style="border-color:#2ea043;color:#8ce99a;">Published</span>
                            <?php else: ?>
                                <span class="pill" style="border-color:#f59f00;color:#f59f00;">Draft</span>
                            <?php endif; ?>
                        </div>
                        <p class="muted" style="margin:0;white-space:pre-wrap;max-height:140px;overflow:auto;"><?= sanitize(mb_substr($tpl['body'] ?? '', 0, 420)); ?></p>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a class="btn secondary" href="/superadmin/template_edit.php?id=<?= sanitize($tpl['id'] ?? ''); ?>"><?= sanitize('Edit'); ?></a>
                            <?php if ($isSuperadmin): ?>
                                <form method="post" action="/superadmin/template_publish_toggle.php">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                    <input type="hidden" name="id" value="<?= sanitize($tpl['id'] ?? ''); ?>">
                                    <button class="btn secondary" type="submit"><?= sanitize(!empty($tpl['published']) ? 'Unpublish' : 'Publish'); ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <p class="muted" style="margin:0;"><?= sanitize('Updated: ' . ($tpl['updatedAt'] ?? '')); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card" style="margin-top:16px;display:grid;gap:8px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0;"><?= sanitize('Template Requests Queue'); ?></h3>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Contractor submissions awaiting staff action.'); ?></p>
                </div>
                <a class="btn secondary" href="/superadmin/requests.php?type=templates"><?= sanitize('Open queue'); ?></a>
            </div>
            <?php if (!$requests): ?>
                <p class="muted"><?= sanitize('No pending template requests.'); ?></p>
            <?php else: ?>
                <div style="display:grid;gap:8px;">
                    <?php foreach (array_slice($requests, 0, 5) as $req): ?>
                        <div style="border:1px solid var(--border);border-radius:10px;padding:10px;display:grid;gap:4px;">
                            <strong><?= sanitize($req['title'] ?? 'Request'); ?></strong>
                            <span class="muted"><?= sanitize('Status: ' . request_status_label((string)($req['status'] ?? 'new'))); ?></span>
                            <span class="muted"><?= sanitize('Updated: ' . ($req['updatedAt'] ?? '')); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    });
});
