<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_role('superadmin');
    ensure_template_pack_library_env();

    $templates = load_global_templates();
    usort($templates, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

    $requests = array_values(array_filter(load_requests_index(), fn($req) => ($req['type'] ?? '') === 'template'));
    usort($requests, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

    $title = get_app_config()['appName'] . ' | Global Templates';

    render_layout($title, function () use ($templates, $requests) {
        ?>
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">YOJAK Global Templates</h2>
                    <p class="muted" style="margin:4px 0 0;">Manage default templates available to all contractors.</p>
                </div>
                <a class="btn" href="/superadmin/template_edit.php">Create Template</a>
            </div>
        </div>

        <div style="display:grid; gap:12px; margin-top:12px;">
            <?php if (!$templates): ?>
                <div class="card"><p class="muted" style="margin:0;">No global templates yet.</p></div>
            <?php endif; ?>
            <?php foreach ($templates as $tpl): ?>
                <div class="card" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                    <div>
                        <h3 style="margin:0;"><?= sanitize($tpl['title'] ?? 'Template'); ?></h3>
                        <p class="muted" style="margin:4px 0 0;"><?= sanitize($tpl['id'] ?? ''); ?> • <?= sanitize($tpl['category'] ?? 'General'); ?></p>
                    </div>
                    <a class="btn secondary" href="/superadmin/template_edit.php?id=<?= sanitize($tpl['id'] ?? ''); ?>">Edit</a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card" style="margin-top:18px;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0;">Contractor Requests</h3>
                    <p class="muted" style="margin:4px 0 0;">Review and deliver template requests.</p>
                </div>
            </div>
        </div>

        <div style="display:grid; gap:12px; margin-top:12px;">
            <?php if (!$requests): ?>
                <div class="card"><p class="muted" style="margin:0;">No template requests.</p></div>
            <?php endif; ?>
            <?php foreach ($requests as $req): ?>
                <div class="card" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                    <div>
                        <h4 style="margin:0;"><?= sanitize($req['title'] ?? 'Template Request'); ?></h4>
                        <p class="muted" style="margin:4px 0 0;"><?= sanitize(($req['id'] ?? '') . ' • ' . ($req['status'] ?? 'new')); ?></p>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <a class="btn secondary" href="/superadmin/request_view.php?id=<?= sanitize($req['id'] ?? ''); ?>">View</a>
                        <a class="btn" href="/superadmin/template_edit.php?requestId=<?= sanitize($req['id'] ?? ''); ?>">Deliver</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    });
});
