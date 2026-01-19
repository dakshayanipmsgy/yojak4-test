<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $type = trim((string)($_GET['type'] ?? 'template'));
    $type = in_array($type, ['template', 'pack'], true) ? $type : 'template';

    $title = get_app_config()['appName'] . ' | New Request';

    render_layout($title, function () use ($type) {
        ?>
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Request a <?= sanitize($type === 'pack' ? 'Pack Blueprint' : 'Template'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;">Upload the tender PDF and explain what you need. Our team will deliver it for you.</p>
                </div>
                <a class="btn secondary" href="/contractor/<?= $type === 'pack' ? 'packs_library.php?tab=requests' : 'templates.php?tab=requests'; ?>">Back to Requests</a>
            </div>
        </div>

        <form method="post" action="/contractor/request_create.php" enctype="multipart/form-data" style="margin-top:12px;">
            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
            <input type="hidden" name="type" value="<?= sanitize($type); ?>">
            <div class="card" style="display:grid; gap:12px;">
                <label class="field">
                    <span>Title</span>
                    <input type="text" name="title" required placeholder="e.g., Technical bid format for APS Ranchi">
                </label>
                <label class="field">
                    <span>Notes / Instructions</span>
                    <textarea name="notes" rows="5" placeholder="Explain what needs to be included, any annexures, department details, etc."></textarea>
                </label>
                <div style="display:grid; gap:10px; grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                    <label class="field">
                        <span>OFFTD / Tender ID (optional)</span>
                        <input type="text" name="tender_id" placeholder="OFFTD-XXXX">
                    </label>
                    <label class="field">
                        <span>Tender Title (optional)</span>
                        <input type="text" name="tender_title" placeholder="Tender name">
                    </label>
                </div>
                <label class="field">
                    <span>Upload Tender PDF</span>
                    <input type="file" name="tender_pdf" accept="application/pdf" required>
                </label>
            </div>
            <div style="display:flex; gap:10px; margin-top:12px;">
                <button class="btn" type="submit">Submit Request</button>
                <a class="btn secondary" href="/contractor/<?= $type === 'pack' ? 'packs_library.php?tab=requests' : 'templates.php?tab=requests'; ?>">Cancel</a>
            </div>
        </form>
        <?php
    });
});
