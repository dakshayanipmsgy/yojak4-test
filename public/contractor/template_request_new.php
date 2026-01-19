<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_contractor_library_env($yojId);

    $type = ($_GET['type'] ?? '') === 'pack' ? 'pack' : 'template';
    $title = get_app_config()['appName'] . ' | Request Template/Pack';

    render_layout($title, function () use ($type) {
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Request Template/Pack from YOJAK team</h2>
                    <p class="muted" style="margin:4px 0 0;">Upload tender documents and describe what you need.</p>
                </div>
                <a class="btn secondary" href="/contractor/template_requests.php">View Requests</a>
            </div>

            <form method="post" action="/contractor/template_request_create.php" enctype="multipart/form-data" style="display:grid;gap:14px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">

                <div style="display:grid;gap:8px;">
                    <label for="type"><strong>Request Type</strong></label>
                    <select class="input" id="type" name="type">
                        <option value="template" <?= $type === 'template' ? 'selected' : ''; ?>>Template</option>
                        <option value="pack" <?= $type === 'pack' ? 'selected' : ''; ?>>Pack</option>
                    </select>
                </div>

                <div style="display:grid;gap:8px;">
                    <label for="title"><strong>Title</strong></label>
                    <input class="input" id="title" name="title" required maxlength="120" placeholder="Create annexure formats for ...">
                </div>

                <div style="display:grid;gap:8px;">
                    <label for="notes"><strong>Notes</strong></label>
                    <textarea class="input" id="notes" name="notes" rows="4" maxlength="5000" placeholder="Explain what you need in simple words."></textarea>
                </div>

                <div style="display:grid;gap:8px;">
                    <label for="sourceTenderType"><strong>Source Tender Type</strong></label>
                    <select class="input" id="sourceTenderType" name="sourceTenderType">
                        <option value="offline">Offline Tender (manual)</option>
                        <option value="discovered">Discovered Tender</option>
                        <option value="uploaded_pdf" selected>Uploaded PDF</option>
                    </select>
                    <div class="muted">If you have an OFFTD ID, add it below.</div>
                </div>

                <div style="display:grid;gap:8px;">
                    <label for="sourceTenderId"><strong>Source Tender ID (optional)</strong></label>
                    <input class="input" id="sourceTenderId" name="sourceTenderId" maxlength="50" placeholder="OFFTD-XXXXX">
                </div>

                <div style="display:grid;gap:8px;">
                    <label for="attachment"><strong>Tender PDF (optional)</strong></label>
                    <input class="input" id="attachment" type="file" name="attachment" accept="application/pdf">
                    <div class="muted">PDF only. Max 15 MB.</div>
                </div>

                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button class="btn" type="submit">Submit Request</button>
                    <a class="btn secondary" href="/contractor/template_requests.php">Cancel</a>
                </div>
            </form>
        </div>
        <?php
    });
});
