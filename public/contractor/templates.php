<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_contractor_templates_env($yojId);
    ensure_template_pack_library_env();

    $tab = trim((string)($_GET['tab'] ?? 'default'));

    $globalTemplates = array_values(array_filter(load_global_templates(), fn($tpl) => !empty($tpl['published'])));
    usort($globalTemplates, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

    $myTemplates = array_values(array_filter(load_contractor_templates_full($yojId), fn($tpl) => empty($tpl['deletedAt'])));
    usort($myTemplates, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

    $requests = array_values(array_filter(load_requests_index(), fn($req) => ($req['yojId'] ?? '') === $yojId && ($req['type'] ?? '') === 'template'));
    usort($requests, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

    $title = get_app_config()['appName'] . ' | Templates';

    render_layout($title, function () use ($tab, $globalTemplates, $myTemplates, $requests) {
        $tabs = [
            'default' => 'YOJAK Templates (Default)',
            'mine' => 'My Templates',
            'requests' => 'Requests',
        ];
        ?>
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Templates</h2>
                    <p class="muted" style="margin:4px 0 0;">Draft reusable letters and annexures with auto-fill fields.</p>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <a class="btn" href="/contractor/template_new.php">Create Template</a>
                    <a class="btn secondary" href="/contractor/tenders.php">Back to Tenders</a>
                </div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:14px;">
                <?php foreach ($tabs as $key => $label): ?>
                    <a class="btn <?= $tab === $key ? '' : 'secondary'; ?>" href="/contractor/templates.php?tab=<?= sanitize($key); ?>"><?= sanitize($label); ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($tab === 'default'): ?>
            <div style="display:grid; gap:12px; margin-top:12px;">
                <?php if (!$globalTemplates): ?>
                    <div class="card"><p class="muted" style="margin:0;">No global templates yet.</p></div>
                <?php endif; ?>
                <?php foreach ($globalTemplates as $tpl): ?>
                    <div class="card" style="display:grid; gap:10px;">
                        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                            <div>
                                <h3 style="margin:0;"><?= sanitize($tpl['title'] ?? 'Template'); ?></h3>
                                <p class="muted" style="margin:4px 0 0;"><?= sanitize($tpl['category'] ?? 'General'); ?> • <?= sanitize($tpl['id'] ?? ''); ?></p>
                            </div>
                            <form method="post" action="/contractor/template_copy_from_global.php">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="tplId" value="<?= sanitize($tpl['id'] ?? ''); ?>">
                                <button class="btn" type="submit">Copy &amp; Customize</button>
                            </form>
                        </div>
                        <p class="muted" style="margin:0;"><?= sanitize($tpl['description'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($tab === 'requests'): ?>
            <div style="margin-top:12px; display:grid; gap:12px;">
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                        <div>
                            <h3 style="margin:0;">Request a Template</h3>
                            <p class="muted" style="margin:4px 0 0;">Upload the tender PDF and describe what you need. Our team will deliver it for you.</p>
                        </div>
                        <a class="btn" href="/contractor/request_new.php?type=template">New Request</a>
                    </div>
                </div>
                <?php if (!$requests): ?>
                    <div class="card"><p class="muted" style="margin:0;">No requests yet.</p></div>
                <?php endif; ?>
                <?php foreach ($requests as $req): ?>
                    <div class="card" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                        <div>
                            <h4 style="margin:0;"><?= sanitize($req['title'] ?? 'Template Request'); ?></h4>
                            <p class="muted" style="margin:4px 0 0;"><?= sanitize(($req['id'] ?? '') . ' • ' . ($req['status'] ?? 'new')); ?></p>
                        </div>
                        <a class="btn secondary" href="/contractor/request_view.php?id=<?= sanitize($req['id'] ?? ''); ?>">View</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="display:grid; gap:12px; margin-top:12px;">
                <?php if (!$myTemplates): ?>
                    <div class="card">
                        <p class="muted" style="margin:0;">No templates yet. Create a new one or copy from YOJAK defaults.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($myTemplates as $tpl): ?>
                    <div class="card" style="display:grid; gap:10px;">
                        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                            <div>
                                <h3 style="margin:0;"><?= sanitize($tpl['title'] ?? ($tpl['name'] ?? 'Template')); ?></h3>
                                <p class="muted" style="margin:4px 0 0;"><?= sanitize($tpl['category'] ?? 'General'); ?> • <?= sanitize($tpl['id'] ?? ($tpl['tplId'] ?? '')); ?></p>
                            </div>
                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <a class="btn secondary" href="/contractor/template_edit.php?id=<?= sanitize($tpl['id'] ?? ($tpl['tplId'] ?? '')); ?>">Edit</a>
                                <a class="btn secondary" href="/contractor/template_preview.php?tplId=<?= sanitize($tpl['tplId'] ?? ($tpl['id'] ?? '')); ?>" target="_blank" rel="noopener">Preview</a>
                            </div>
                        </div>
                        <p class="muted" style="margin:0;"><?= sanitize($tpl['description'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php
    });
});
