<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = require_staff_actor();
    ensure_global_templates_seeded();
    $globalIndex = load_template_index('global');
    $templates = [];
    foreach ($globalIndex as $entry) {
        $record = load_template_record_by_scope('global', $entry['templateId'] ?? '');
        if ($record) {
            $templates[] = $record;
        }
    }

    $title = get_app_config()['appName'] . ' | Global Templates';
    render_layout($title, function () use ($templates, $actor) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Global Templates</h2>
                    <p class="muted" style="margin:4px 0 0;">Manage default templates shared across contractors.</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn" href="/superadmin/template_new.php">Create Global Template</a>
                    <a class="btn secondary" href="/superadmin/template_requests.php">Template Requests</a>
                    <?php if (($actor['type'] ?? '') === 'superadmin'): ?>
                        <a class="btn secondary" href="/superadmin/dashboard.php">Back</a>
                    <?php else: ?>
                        <a class="btn secondary" href="/staff/dashboard.php">Back</a>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
                <?php if (!$templates): ?>
                    <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                        <p class="muted" style="margin:0;">No global templates yet.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($templates as $tpl): ?>
                    <div style="border:1px solid var(--border);border-radius:12px;padding:12px;display:grid;gap:8px;background:var(--surface-2);">
                        <div>
                            <h4 style="margin:0 0 4px 0;"><?= sanitize($tpl['title'] ?? 'Template'); ?></h4>
                            <p class="muted" style="margin:0;"><?= sanitize(ucfirst($tpl['category'] ?? 'Other')); ?></p>
                        </div>
                        <p class="muted" style="margin:0;white-space:pre-wrap;max-height:160px;overflow:auto;"><?= sanitize(mb_substr($tpl['bodyHtml'] ?? '', 0, 500)); ?></p>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a class="btn" href="/superadmin/template_edit.php?id=<?= sanitize($tpl['templateId'] ?? ''); ?>">Edit</a>
                        </div>
                        <p class="muted" style="margin:0;">Updated: <?= sanitize($tpl['updatedAt'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
