<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_contractor_templates_env($yojId);
    ensure_templates_env($yojId);
    ensure_global_templates_seeded();
    migrate_legacy_templates_to_new($yojId);

    $tab = trim((string)($_GET['tab'] ?? 'default'));
    $globalIndex = load_template_index('global');
    $contractorIndex = load_template_index('contractor', $yojId);

    $globalTemplates = [];
    foreach ($globalIndex as $entry) {
        $record = load_template_record_by_scope('global', $entry['templateId'] ?? '');
        if ($record) {
            $globalTemplates[] = $record;
        }
    }

    $contractorTemplates = [];
    foreach ($contractorIndex as $entry) {
        $record = load_template_record_by_scope('contractor', $entry['templateId'] ?? '', $yojId);
        if ($record) {
            $contractorTemplates[] = $record;
        }
    }

    $requestsIndex = load_template_requests_index();
    $contractorRequests = array_values(array_filter($requestsIndex, static fn($req) => ($req['yojId'] ?? '') === $yojId));

    $title = get_app_config()['appName'] . ' | Templates & Packs';

    render_layout($title, function () use ($tab, $globalTemplates, $contractorTemplates, $contractorRequests) {
        $tabs = [
            'default' => 'Default Templates (YOJAK)',
            'mine' => 'My Templates',
            'request' => 'Request a Template',
        ];
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div>
                <h2 style="margin:0;">Templates</h2>
                <p class="muted" style="margin:4px 0 0;">Create tender-ready letters with guided placeholders. Default templates are read-only.</p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php foreach ($tabs as $key => $label): ?>
                    <a class="btn <?= $tab === $key ? '' : 'secondary'; ?>" href="/contractor/templates.php?tab=<?= sanitize($key); ?>"><?= sanitize($label); ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($tab === 'default'): ?>
            <div class="card" style="margin-top:12px;display:grid;gap:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                    <div>
                        <h3 style="margin:0;">YOJAK Default Templates</h3>
                        <p class="muted" style="margin:4px 0 0;">Common tender letters maintained by staff. Preview or use them in packs.</p>
                    </div>
                </div>
                <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
                    <?php if (!$globalTemplates): ?>
                        <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                            <p class="muted" style="margin:0;">No default templates yet.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($globalTemplates as $tpl): ?>
                        <div style="border:1px solid var(--border);border-radius:12px;padding:12px;display:grid;gap:8px;background:var(--surface-2);">
                            <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                                <div>
                                    <h4 style="margin:0 0 4px 0;"><?= sanitize($tpl['title'] ?? 'Template'); ?></h4>
                                    <p class="muted" style="margin:0;"><?= sanitize(ucfirst($tpl['category'] ?? 'Other')); ?></p>
                                </div>
                                <span class="pill" style="border-color:#2ea043;color:#8ce99a;">Default</span>
                            </div>
                            <p class="muted" style="margin:0;white-space:pre-wrap;max-height:160px;overflow:auto;"><?= sanitize(mb_substr($tpl['bodyHtml'] ?? '', 0, 500)); ?></p>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <a class="btn secondary" href="/contractor/template_preview.php?tplId=<?= sanitize($tpl['templateId'] ?? ''); ?>" target="_blank" rel="noopener">Preview & Print</a>
                            </div>
                            <p class="muted" style="margin:0;">Updated: <?= sanitize($tpl['updatedAt'] ?? ''); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif ($tab === 'mine'): ?>
            <div class="card" style="margin-top:12px;display:grid;gap:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                    <div>
                        <h3 style="margin:0;">My Templates</h3>
                        <p class="muted" style="margin:4px 0 0;">Create, edit, and delete your own templates.</p>
                    </div>
                    <a class="btn" href="/contractor/template_new.php">Create Template</a>
                </div>
                <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
                    <?php if (!$contractorTemplates): ?>
                        <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                            <p class="muted" style="margin:0;">No custom templates yet.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($contractorTemplates as $tpl): ?>
                        <div style="border:1px solid var(--border);border-radius:12px;padding:12px;display:grid;gap:8px;background:var(--surface-2);">
                            <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                                <div>
                                    <h4 style="margin:0 0 4px 0;"><?= sanitize($tpl['title'] ?? 'Template'); ?></h4>
                                    <p class="muted" style="margin:0;"><?= sanitize(ucfirst($tpl['category'] ?? 'Other')); ?></p>
                                </div>
                            </div>
                            <p class="muted" style="margin:0;white-space:pre-wrap;max-height:160px;overflow:auto;"><?= sanitize(mb_substr($tpl['bodyHtml'] ?? '', 0, 500)); ?></p>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <a class="btn secondary" href="/contractor/template_preview.php?tplId=<?= sanitize($tpl['templateId'] ?? ''); ?>" target="_blank" rel="noopener">Preview & Print</a>
                                <a class="btn" href="/contractor/template_edit.php?id=<?= sanitize($tpl['templateId'] ?? ''); ?>">Edit</a>
                                <form method="post" action="/contractor/template_delete.php" onsubmit="return confirm('Delete this template?');">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                    <input type="hidden" name="id" value="<?= sanitize($tpl['templateId'] ?? ''); ?>">
                                    <button class="btn secondary" type="submit">Delete</button>
                                </form>
                            </div>
                            <p class="muted" style="margin:0;">Updated: <?= sanitize($tpl['updatedAt'] ?? ''); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card" style="margin-top:12px;display:grid;gap:12px;">
                <div>
                    <h3 style="margin:0;">Request a Template</h3>
                    <p class="muted" style="margin:4px 0 0;">Share the tender format and a note. Staff will deliver a ready-to-use template.</p>
                </div>
                <form method="post" action="/contractor/template_request_create.php" enctype="multipart/form-data" style="display:grid;gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <label>Title needed
                        <input name="title" required minlength="3" maxlength="80" placeholder="e.g., Technical bid format">
                    </label>
                    <label>Notes
                        <textarea name="notes" rows="4" placeholder="Explain the required sections or formatting..."></textarea>
                    </label>
                    <label>Upload tender/sample PDF
                        <input type="file" name="sample" accept=".pdf" required>
                    </label>
                    <label class="pill" style="display:inline-flex;gap:6px;align-items:center;">
                        <input type="checkbox" name="make_global" value="1"> Suggest making this a global template
                    </label>
                    <button class="btn" type="submit">Submit Request</button>
                </form>
            </div>
            <div class="card" style="margin-top:12px;display:grid;gap:12px;">
                <div>
                    <h3 style="margin:0;">My Requests</h3>
                    <p class="muted" style="margin:4px 0 0;">Track your template requests and delivery status.</p>
                </div>
                <div style="display:grid;gap:10px;">
                    <?php if (!$contractorRequests): ?>
                        <p class="muted" style="margin:0;">No requests yet.</p>
                    <?php endif; ?>
                    <?php foreach ($contractorRequests as $request): ?>
                        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;border:1px solid var(--border);border-radius:10px;padding:10px;background:var(--surface-2);">
                            <div>
                                <div style="font-weight:600;"><?= sanitize($request['title'] ?? 'Template request'); ?></div>
                                <div class="muted" style="font-size:13px;">Status: <?= sanitize($request['status'] ?? 'pending'); ?> • Updated: <?= sanitize($request['updatedAt'] ?? ''); ?></div>
                            </div>
                            <a class="btn secondary" href="/contractor/template_requests.php?id=<?= sanitize($request['requestId'] ?? ''); ?>">View</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php
    });
});
