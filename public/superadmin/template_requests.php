<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $actor = require_superadmin_or_permission('templates_manage');
    $requestId = trim((string)($_GET['requestId'] ?? ''));
    $statusFilter = trim((string)($_GET['status'] ?? ''));
    $requests = load_template_request_index();
    usort($requests, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

    if ($statusFilter !== '') {
        $requests = array_filter($requests, fn($req) => ($req['status'] ?? '') === $statusFilter);
    }

    $detail = $requestId !== '' ? load_template_request($requestId) : null;
    $globalTemplates = load_global_templates_full();
    $globalPackTemplates = load_global_pack_templates_full();

    $title = get_app_config()['appName'] . ' | Template Requests';

    render_layout($title, function () use ($requests, $detail, $actor, $globalTemplates, $globalPackTemplates, $statusFilter) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Template Requests</h2>
                    <p class="muted" style="margin:4px 0 0;">Review contractor requests and deliver templates or pack templates.</p>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php
                $filters = ['' => 'All', 'pending' => 'Pending', 'in_progress' => 'In Progress', 'delivered' => 'Delivered', 'rejected' => 'Rejected'];
                foreach ($filters as $key => $label) {
                    $active = $statusFilter === $key;
                    $href = '/superadmin/template_requests.php' . ($key !== '' ? '?status=' . urlencode($key) : '');
                    $style = $active ? 'border-color:var(--primary);color:#fff;background:#1f6feb22;' : '';
                    echo '<a class="pill" style="' . $style . '" href="' . sanitize($href) . '">' . sanitize($label) . '</a>';
                }
                ?>
            </div>
            <div style="display:grid;gap:10px;">
                <?php if (!$requests): ?>
                    <div class="card">
                        <p class="muted" style="margin:0;">No requests.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($requests as $req): ?>
                    <a class="card" style="display:flex;justify-content:space-between;gap:12px;align-items:center;" href="/superadmin/template_requests.php?requestId=<?= sanitize($req['requestId']); ?>">
                        <div>
                            <strong><?= sanitize($req['requestId'] ?? 'REQ'); ?></strong>
                            <div class="muted"><?= sanitize($req['yojId'] ?? ''); ?> • <?= sanitize($req['type'] ?? 'template'); ?></div>
                        </div>
                        <span class="pill"><?= sanitize($req['status'] ?? 'pending'); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($detail): ?>
            <div class="card" style="margin-top:16px;display:grid;gap:12px;">
                <div>
                    <h3 style="margin:0;">Request: <?= sanitize($detail['requestId'] ?? ''); ?></h3>
                    <p class="muted" style="margin:4px 0 0;">Contractor: <?= sanitize($detail['yojId'] ?? ''); ?> • Type: <?= sanitize($detail['type'] ?? ''); ?></p>
                </div>
                <div class="card" style="background:var(--surface-2);">
                    <p class="muted" style="margin:0;">Notes</p>
                    <p style="margin:6px 0 0;white-space:pre-wrap;"><?= sanitize($detail['notes'] ?? ''); ?></p>
                </div>
                <div>
                    <p class="muted" style="margin:0;">Uploads</p>
                    <ul style="margin:6px 0 0;padding-left:18px;">
                        <?php
                        $uploadsDir = template_request_upload_dir($detail['requestId']);
                        $files = is_dir($uploadsDir) ? array_values(array_filter(scandir($uploadsDir), fn($f) => !in_array($f, ['.', '..'], true))) : [];
                        if (!$files) {
                            echo '<li class="muted">No files uploaded.</li>';
                        } else {
                            foreach ($files as $file) {
                                echo '<li>' . sanitize($file) . '</li>';
                            }
                        }
                        ?>
                    </ul>
                </div>
                <form method="post" action="/superadmin/template_request_deliver.php" style="display:grid;gap:12px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="requestId" value="<?= sanitize($detail['requestId']); ?>">
                    <label style="display:grid;gap:6px;">
                        <span class="muted">Delivery Scope</span>
                        <select class="input" name="scope">
                            <option value="contractor">Contractor Only</option>
                            <option value="global">Publish Global (superadmin only)</option>
                        </select>
                    </label>
                    <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
                        <label style="display:grid;gap:6px;">
                            <span class="muted">Templates to Deliver</span>
                            <select class="input" name="templateIds[]" multiple size="6">
                                <?php foreach ($globalTemplates as $tpl): ?>
                                    <option value="<?= sanitize($tpl['templateId']); ?>"><?= sanitize($tpl['title'] ?? 'Template'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label style="display:grid;gap:6px;">
                            <span class="muted">Pack Templates to Deliver</span>
                            <select class="input" name="packTemplateIds[]" multiple size="6">
                                <?php foreach ($globalPackTemplates as $tpl): ?>
                                    <option value="<?= sanitize($tpl['packTemplateId']); ?>"><?= sanitize($tpl['title'] ?? 'Pack Template'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <button class="btn" type="submit">Deliver to Contractor</button>
                </form>
            </div>
        <?php endif; ?>
        <?php
    });
});
