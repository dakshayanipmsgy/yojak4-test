<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $title = get_app_config()['appName'] . ' | Request Template Help';

    render_layout($title, function () use ($yojId) {
        ?>
        <div class="card" style="max-width:860px;margin:0 auto;display:grid;gap:16px;">
            <div>
                <h2 style="margin:0;">Request Template Help</h2>
                <p class="muted" style="margin:6px 0 0;">Upload tender PDF(s) and describe what you need. Staff will prepare templates or pack templates for you.</p>
            </div>
            <form method="post" action="/contractor/template_request_submit.php" enctype="multipart/form-data" style="display:grid;gap:14px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="yojId" value="<?= sanitize($yojId); ?>">
                <label style="display:grid;gap:6px;">
                    <span class="muted">Request Type</span>
                    <select class="input" name="type">
                        <option value="template">Template</option>
                        <option value="pack">Pack Template</option>
                        <option value="both">Both</option>
                    </select>
                </label>
                <label style="display:grid;gap:6px;">
                    <span class="muted">Notes</span>
                    <textarea class="input" name="notes" rows="5" placeholder="Describe the template/pack needed."></textarea>
                </label>
                <label style="display:grid;gap:6px;">
                    <span class="muted">Upload Tender PDFs (PDF only)</span>
                    <input class="input" type="file" name="uploads[]" accept="application/pdf" multiple>
                </label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="btn" type="submit">Submit Request</button>
                    <a class="btn secondary" href="/contractor/templates.php">Back to Templates</a>
                </div>
            </form>
        </div>
        <?php
    });
});
