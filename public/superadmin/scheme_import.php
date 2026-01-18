<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_scheme_builder();
    $schemeId = trim($_GET['schemeId'] ?? '');
    if ($schemeId === '') {
        render_error_page('Scheme ID missing.');
        return;
    }

    $scheme = scheme_load_metadata($schemeId);
    if (!$scheme) {
        render_error_page('Scheme not found.');
        return;
    }

    $jsonPayload = '';
    $errors = [];
    $warnings = [];
    $preview = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $jsonPayload = trim($_POST['definition_json'] ?? '');
        if ($jsonPayload === '') {
            $errors[] = 'JSON payload is required.';
        } else {
            $decoded = json_decode($jsonPayload, true);
            if (!is_array($decoded)) {
                $errors[] = 'Invalid JSON payload.';
            } else {
                $normalized = [];
                $warnings = [];
                $errors = scheme_validate_definition($decoded, $normalized, $warnings);
                if (!$errors && (($normalized['schemeId'] ?? '') !== $schemeId)) {
                    $errors[] = 'Scheme ID mismatch in payload.';
                }
                if (!$errors) {
                    $preview = $normalized;
                    $jsonPayload = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }
                scheme_log_import($schemeId, $errors ? 'VALIDATE_FAIL' : 'VALIDATE_OK', $errors);
            }
        }
    }

    $prompt = <<<'PROMPT'
You are creating a YOJAK Scheme Engine definition.

I will provide:
1) A description of the scheme workflow (steps from lead to invoice to handover etc.)
2) Sample PDFs / images showing how documents look (quotation, sales order, delivery challan, packing list, invoice, handover docs, DISCOM docs, etc.)

YOUR JOB:
- Understand the workflow steps and required records.
- Extract document templates and convert them into placeholder-driven templates.
- Output ONLY strict JSON that matches the YOJAK scheme engine format below.

RULES:
- Output ONLY JSON. No markdown, no commentary.
- Use placeholders ONLY like {{field:<key>}} and {{table:<tableId>}}.
- Any fields that should be filled by vendor/customer must become fieldCatalog keys.
- For tables (items lists), include columns: item_description, qty, unit, rate, amount.
- Amount may be computed as qty×rate.
- Include customer portal config showing which docs customer can download.
- Keep names and labels simple and professional.

OUTPUT JSON FORMAT:
{
  "engineVersion": 1,
  "schemeId": "SCM-<SHORTID>",
  "entities": [ ... ],
  "workflow": { ... },
  "fieldCatalog": [ ... ],
  "documents": [ ... ],
  "recordTemplates": { ... },
  "customerPortal": { "enabled": true, "visibleDocs": [ ... ], "accessMode":"token", "tokenTTLdays": 365 },
  "rules": { "notes": [ ... ] }
}

Now wait for my workflow text and sample PDFs and then output the JSON.
PROMPT;

    $title = get_app_config()['appName'] . ' | Scheme Import';
    render_layout($title, function () use ($scheme, $schemeId, $jsonPayload, $errors, $warnings, $preview, $prompt) {
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div>
                <h2 style="margin:0;">Import Scheme JSON</h2>
                <p class="muted" style="margin:6px 0 0;">Scheme: <?= sanitize($scheme['name'] ?? ''); ?> (<?= sanitize($schemeId); ?>)</p>
            </div>

            <div style="display:grid;gap:10px;">
                <h3 style="margin:0;">External AI Prompt (Copy)</h3>
                <textarea class="input" rows="12" readonly style="font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;white-space:pre;"><?= sanitize($prompt); ?></textarea>
            </div>

            <?php if ($errors): ?>
                <div class="pill" style="border-color:#f08c00;color:#f08c00;">
                    <?= sanitize(implode(' ', $errors)); ?>
                </div>
            <?php endif; ?>
            <?php if ($warnings): ?>
                <div class="pill" style="border-color:#3b82f6;color:#3b82f6;">
                    <?= sanitize(implode(' ', $warnings)); ?>
                </div>
            <?php endif; ?>

            <form method="post" style="display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <label style="display:grid;gap:6px;">
                    <span>Definition JSON</span>
                    <textarea class="input" name="definition_json" rows="18" placeholder="Paste JSON here" style="font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;white-space:pre;" required><?= sanitize($jsonPayload); ?></textarea>
                </label>
                <button class="btn" type="submit">Validate</button>
            </form>

            <?php if ($preview): ?>
                <div style="border-top:1px solid var(--border);padding-top:16px;display:grid;gap:10px;">
                    <h3 style="margin:0;">Validation Preview</h3>
                    <div class="grid" style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                        <div class="card" style="background:var(--surface-2);">
                            <p class="muted" style="margin:0 0 4px 0;">Entities</p>
                            <strong><?= sanitize((string)count($preview['entities'] ?? [])); ?></strong>
                        </div>
                        <div class="card" style="background:var(--surface-2);">
                            <p class="muted" style="margin:0 0 4px 0;">Documents</p>
                            <strong><?= sanitize((string)count($preview['documents'] ?? [])); ?></strong>
                        </div>
                        <div class="card" style="background:var(--surface-2);">
                            <p class="muted" style="margin:0 0 4px 0;">Field Catalog</p>
                            <strong><?= sanitize((string)count($preview['fieldCatalog'] ?? [])); ?></strong>
                        </div>
                    </div>
                    <form method="post" action="/superadmin/scheme_import_save.php" style="display:grid;gap:10px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeId" value="<?= sanitize($schemeId); ?>">
                        <input type="hidden" name="definition_json" value="<?= sanitize($jsonPayload); ?>">
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="publish_now" value="1">
                            <span>Publish scheme immediately</span>
                        </label>
                        <button class="btn" type="submit">Import & Save</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    });
});
