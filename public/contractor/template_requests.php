<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $requestId = trim((string)($_GET['id'] ?? ''));

    $requests = load_template_requests_index();
    $contractorRequests = array_values(array_filter($requests, static fn($req) => ($req['yojId'] ?? '') === $yojId));

    $request = null;
    if ($requestId !== '') {
        $request = load_template_request($requestId);
        if (!$request || ($request['yojId'] ?? '') !== $yojId) {
            render_error_page('Request not found.');
            return;
        }
    }

    $title = get_app_config()['appName'] . ' | Template Requests';

    render_layout($title, function () use ($contractorRequests, $request) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Template Requests</h2>
                    <p class="muted" style="margin:4px 0 0;">Track your request status and delivered templates.</p>
                </div>
                <a class="btn secondary" href="/contractor/templates.php?tab=request">Back to Templates</a>
            </div>
            <?php if ($request): ?>
                <div class="card" style="background:var(--surface-2);border:1px solid var(--border);">
                    <h3 style="margin-top:0;"><?= sanitize($request['title'] ?? 'Template request'); ?></h3>
                    <p class="muted" style="margin:4px 0 0;">Status: <?= sanitize($request['status'] ?? 'pending'); ?></p>
                    <p style="white-space:pre-wrap;"><?= sanitize($request['notes'] ?? ''); ?></p>
                    <?php if (!empty($request['files'])): ?>
                        <div style="margin-top:10px;">
                            <div class="muted" style="font-size:12px;">Uploaded files</div>
                            <ul>
                                <?php foreach ($request['files'] as $file): ?>
                                    <li><?= sanitize($file['name'] ?? 'file.pdf'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div style="display:grid;gap:10px;">
                <?php if (!$contractorRequests): ?>
                    <p class="muted" style="margin:0;">No requests yet.</p>
                <?php endif; ?>
                <?php foreach ($contractorRequests as $req): ?>
                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;border:1px solid var(--border);border-radius:10px;padding:10px;background:var(--surface-2);">
                        <div>
                            <div style="font-weight:600;"><?= sanitize($req['title'] ?? 'Template request'); ?></div>
                            <div class="muted" style="font-size:12px;">Status: <?= sanitize($req['status'] ?? 'pending'); ?> • Updated: <?= sanitize($req['updatedAt'] ?? ''); ?></div>
                        </div>
                        <a class="btn secondary" href="/contractor/template_requests.php?id=<?= sanitize($req['requestId'] ?? ''); ?>">View</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
