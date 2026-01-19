<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = require_staff_actor();
    $requestId = trim((string)($_GET['id'] ?? ''));
    if ($requestId === '') {
        render_error_page('Request not found.');
        return;
    }
    $request = load_template_request($requestId);
    if (!$request) {
        render_error_page('Request not found.');
        return;
    }

    $contractor = load_contractor($request['yojId'] ?? '') ?? [];
    $placeholders = template_placeholder_groups($contractor, $request['yojId'] ?? '', null);

    $defaultTemplate = [
        'templateId' => generate_template_id('contractor', $request['yojId'] ?? ''),
        'scope' => 'delivered',
        'owner' => ['yojId' => $request['yojId'] ?? ''],
        'title' => $request['title'] ?? 'Template',
        'category' => 'Other',
        'description' => $request['notes'] ?? '',
        'bodyHtml' => '',
        'placeholdersUsed' => [],
        'visibility' => ['contractorEditable' => true],
    ];

    $title = get_app_config()['appName'] . ' | Request ' . ($request['requestId'] ?? '');
    render_layout($title, function () use ($request, $contractor, $placeholders, $defaultTemplate) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Request: <?= sanitize($request['title'] ?? 'Template'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;">Status: <?= sanitize($request['status'] ?? 'pending'); ?> • Contractor: <?= sanitize($contractor['firmName'] ?? ($request['yojId'] ?? '')); ?></p>
                </div>
                <a class="btn secondary" href="/superadmin/template_requests.php">Back</a>
            </div>
            <div style="display:grid;gap:6px;">
                <div style="font-weight:600;">Notes</div>
                <div style="white-space:pre-wrap;"><?= sanitize($request['notes'] ?? ''); ?></div>
            </div>
            <?php if (!empty($request['files'])): ?>
                <div>
                    <div style="font-weight:600;">Uploaded files</div>
                    <ul>
                        <?php foreach ($request['files'] as $file): ?>
                            <li><?= sanitize($file['name'] ?? 'file.pdf'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" action="/superadmin/template_request_set_status.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="id" value="<?= sanitize($request['requestId'] ?? ''); ?>">
                <label>Status
                    <select name="status">
                        <?php foreach (['pending','in_progress','delivered','rejected'] as $status): ?>
                            <option value="<?= sanitize($status); ?>" <?= ($request['status'] ?? '') === $status ? 'selected' : ''; ?>><?= sanitize($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="btn secondary" type="submit">Update Status</button>
            </form>
        </div>

        <div class="card" style="margin-top:12px;display:grid;gap:12px;">
            <div>
                <h3 style="margin:0;">Deliver Template</h3>
                <p class="muted" style="margin:4px 0 0;">Use guided editor or advanced JSON.</p>
            </div>
            <form method="post" action="/superadmin/template_request_deliver.php" style="display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="requestId" value="<?= sanitize($request['requestId'] ?? ''); ?>">
                <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                    <label>Title
                        <input name="title" required minlength="3" maxlength="80" value="<?= sanitize($request['title'] ?? ''); ?>">
                    </label>
                    <label>Category
                        <select name="category">
                            <?php foreach (['Affidavit','Letter','Declaration','Form','Other'] as $category): ?>
                                <option value="<?= sanitize($category); ?>"><?= sanitize($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label>Description
                    <input name="description" maxlength="180" value="<?= sanitize($request['notes'] ?? ''); ?>">
                </label>
                <label>Delivery Scope
                    <select name="deliver_scope">
                        <option value="delivered">Deliver to contractor only</option>
                        <option value="global">Publish as global template</option>
                    </select>
                </label>
                <div class="template-editor" style="display:grid;gap:12px;grid-template-columns:minmax(0,2fr) minmax(240px,1fr);align-items:start;">
                    <div>
                        <label>Template Body
                            <textarea id="templateBody" name="bodyHtml" rows="16" required placeholder="Write the template body here."></textarea>
                        </label>
                        <p class="muted" style="margin:4px 0 0;">Unresolved placeholders print as blanks.</p>
                    </div>
                    <aside class="card" style="background:var(--surface-2);border:1px solid var(--border);display:grid;gap:10px;">
                        <div style="font-weight:600;">Available Fields</div>
                        <?php foreach ($placeholders as $group => $items): ?>
                            <div>
                                <div class="muted" style="font-size:12px;margin-bottom:6px;"><?= sanitize($group); ?></div>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <?php foreach ($items as $item): ?>
                                        <button type="button" class="tag insert-token" data-token="<?= sanitize($item['token']); ?>" title="<?= sanitize($item['key']); ?>">
                                            <?= sanitize($item['label']); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </aside>
                </div>
                <details>
                    <summary>Advanced JSON (staff only)</summary>
                    <p class="muted">Paste validated JSON. Forbidden pricing placeholders are blocked.</p>
                    <textarea name="advanced_json" rows="10" style="width:100%;"><?= sanitize(json_encode($defaultTemplate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea>
                    <label class="pill" style="display:inline-flex;gap:6px;align-items:center;margin-top:6px;">
                        <input type="checkbox" name="apply_json" value="1"> Apply Advanced JSON
                    </label>
                </details>
                <button class="btn" type="submit">Deliver Template</button>
            </form>
        </div>
        <script>
            (function () {
                const textarea = document.getElementById('templateBody');
                const buttons = document.querySelectorAll('.insert-token');
                buttons.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const token = btn.getAttribute('data-token');
                        if (!token || !textarea) return;
                        const start = textarea.selectionStart || 0;
                        const end = textarea.selectionEnd || 0;
                        const value = textarea.value || '';
                        textarea.value = value.substring(0, start) + token + value.substring(end);
                        const nextPos = start + token.length;
                        textarea.selectionStart = textarea.selectionEnd = nextPos;
                        textarea.focus();
                    });
                });
            })();
        </script>
        <style>
            @media (max-width: 900px) {
                .template-editor { grid-template-columns: 1fr !important; }
            }
            .insert-token { cursor: pointer; border: 1px solid transparent; }
            .insert-token:hover { border-color: var(--border); }
        </style>
        <?php
    });
});
