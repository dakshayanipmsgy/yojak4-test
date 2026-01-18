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

    $sectionId = trim($_GET['sectionId'] ?? '');
    $mode = trim($_GET['mode'] ?? '');
    $readOnly = ($mode === 'view');

    $entries = scheme_sections_index($schemeId);
    $currentEntry = null;
    foreach ($entries as $entry) {
        if (($entry['sectionId'] ?? '') === $sectionId) {
            $currentEntry = $entry;
            break;
        }
    }

    $jsonPayload = '';
    $errors = [];
    $warnings = [];
    $preview = null;

    if ($currentEntry && $currentEntry['file'] ?? '') {
        $payload = readJson(scheme_section_path($schemeId, $currentEntry['file']));
        if ($payload) {
            $jsonPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $jsonPayload = trim($_POST['section_json'] ?? '');
        if ($jsonPayload === '') {
            $errors[] = 'JSON payload is required.';
        } else {
            $decoded = json_decode($jsonPayload, true);
            if (!is_array($decoded)) {
                $errors[] = 'Invalid JSON payload.';
            } else {
                $availableKeys = scheme_collect_section_component_keys(
                    scheme_sections_payloads($schemeId, $sectionId !== '' ? $sectionId : null, true)
                );
                $normalized = [];
                $warnings = [];
                $errors = scheme_validate_section($decoded, $schemeId, $availableKeys, $normalized, $warnings);
                if (!$errors) {
                    $preview = $normalized;
                    $jsonPayload = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }
                scheme_log_import($schemeId, $errors ? 'SECTION_VALIDATE_FAIL' : 'SECTION_VALIDATE_OK', $errors);
            }
        }
    }

    $prompt = <<<'PROMPT'
You are creating YOJAK Scheme Engine SECTION JSON modules.

I will provide:
1) A description of the scheme workflow (steps from lead to invoice to handover etc.)
2) Sample PDFs / images showing how documents look (quotation, sales order, delivery challan, packing list, invoice, handover docs, DISCOM docs, etc.)

YOUR JOB:
- Output ONE section JSON at a time. Start with the Base section (core entities + primary workflow).
- Subsequent sections should focus on specific document packs or workflow patches (e.g., DISCOM docs, handover docs, compliance packs).
- Extract document templates and convert them into placeholder-driven templates.

RULES:
- Output ONLY strict JSON. No markdown, no commentary.
- Use placeholders ONLY like {{field:<key>}} and {{table:<tableId>}}.
- Any fields that should be filled by vendor/customer must become fieldCatalog keys.
- For tables (items lists), include columns: item_description, qty, unit, rate, amount.
- Amount may be computed as qty×rate.
- Use customerPortalPatch to add/remove visible docs.
- Use rulesPatch to add/remove notes.

SECTION OUTPUT FORMAT:
{
  "sectionVersion": 1,
  "sectionId": "SEC-BASE",
  "schemeId": "SCM-<SHORTID>",
  "title": "Base Workflow + Core Entities",
  "description": "Leads → Customer → Agreement → Deliveries → Invoice",
  "mode": "merge",
  "components": {
    "entities": [ ... ],
    "workflow": { "transitions": [ ... ], "milestones": [ ... ] },
    "fieldCatalog": [ ... ],
    "recordTemplates": { ... },
    "documents": [ ... ],
    "customerPortalPatch": { "visibleDocsAdd": [ ... ], "visibleDocsRemove": [ ... ] },
    "rulesPatch": { "notesAdd": [ ... ], "notesRemove": [ ... ] }
  }
}

Now wait for my workflow text and sample PDFs and then output ONE section JSON.
PROMPT;

    $skeleton = <<<'JSON'
{
  "sectionVersion": 1,
  "sectionId": "SEC-BASE",
  "schemeId": "SCM-XXXXX",
  "title": "Base Workflow + Core Entities",
  "description": "Leads → Customer → Agreement → Deliveries → Invoice",
  "mode": "merge",
  "components": {
    "entities": [],
    "workflow": { "transitions": [], "milestones": [] },
    "fieldCatalog": [],
    "recordTemplates": {},
    "documents": [],
    "customerPortalPatch": { "visibleDocsAdd": [], "visibleDocsRemove": [] },
    "rulesPatch": { "notesAdd": [], "notesRemove": [] }
  }
}
JSON;

    $title = get_app_config()['appName'] . ' | Scheme Section Import';
    render_layout($title, function () use ($scheme, $schemeId, $sectionId, $jsonPayload, $errors, $warnings, $preview, $prompt, $skeleton, $readOnly, $currentEntry) {
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div>
                <h2 style="margin:0;">Import Scheme Section JSON</h2>
                <p class="muted" style="margin:6px 0 0;">Scheme: <?= sanitize($scheme['name'] ?? ''); ?> (<?= sanitize($schemeId); ?>)</p>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a class="btn secondary" href="/superadmin/scheme_sections.php?schemeId=<?= urlencode($schemeId); ?>">Back to Sections</a>
            </div>

            <div style="display:grid;gap:10px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                    <h3 style="margin:0;">External AI Prompt</h3>
                    <button class="btn secondary" type="button" data-copy-target="prompt-text">Copy Prompt</button>
                </div>
                <textarea class="input" id="prompt-text" rows="12" readonly style="font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;white-space:pre;"><?= sanitize($prompt); ?></textarea>
            </div>

            <div style="display:grid;gap:10px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                    <h3 style="margin:0;">Section JSON Skeleton</h3>
                    <button class="btn secondary" type="button" data-copy-target="skeleton-text">Copy Skeleton</button>
                </div>
                <textarea class="input" id="skeleton-text" rows="10" readonly style="font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;white-space:pre;"><?= sanitize($skeleton); ?></textarea>
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
                    <span>Section JSON</span>
                    <textarea class="input" name="section_json" rows="18" placeholder="Paste section JSON here" style="font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;white-space:pre;" <?= $readOnly ? 'readonly' : 'required'; ?>><?= sanitize($jsonPayload); ?></textarea>
                </label>
                <?php if (!$readOnly): ?>
                    <button class="btn" type="submit">Validate</button>
                <?php endif; ?>
            </form>

            <?php if ($preview && !$readOnly): ?>
                <div style="border-top:1px solid var(--border);padding-top:16px;display:grid;gap:10px;">
                    <h3 style="margin:0;">Validation Preview</h3>
                    <div class="grid" style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                        <div class="card" style="background:var(--surface-2);">
                            <p class="muted" style="margin:0 0 4px 0;">Entities</p>
                            <strong><?= sanitize((string)count($preview['components']['entities'] ?? [])); ?></strong>
                        </div>
                        <div class="card" style="background:var(--surface-2);">
                            <p class="muted" style="margin:0 0 4px 0;">Documents</p>
                            <strong><?= sanitize((string)count($preview['components']['documents'] ?? [])); ?></strong>
                        </div>
                        <div class="card" style="background:var(--surface-2);">
                            <p class="muted" style="margin:0 0 4px 0;">Field Catalog</p>
                            <strong><?= sanitize((string)count($preview['components']['fieldCatalog'] ?? [])); ?></strong>
                        </div>
                    </div>
                    <form method="post" action="/superadmin/scheme_section_import_save.php" style="display:grid;gap:10px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="schemeId" value="<?= sanitize($schemeId); ?>">
                        <input type="hidden" name="section_json" value="<?= sanitize($jsonPayload); ?>">
                        <?php if ($currentEntry): ?>
                            <input type="hidden" name="existing_section_id" value="<?= sanitize((string)$currentEntry['sectionId']); ?>">
                            <label style="display:grid;gap:6px;">
                                <span>Save Mode</span>
                                <select class="input" name="save_mode">
                                    <option value="update">Update existing section</option>
                                    <option value="new">Save as new section</option>
                                </select>
                            </label>
                        <?php endif; ?>
                        <button class="btn" type="submit">Save Section</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <script>
            (function () {
                const buttons = document.querySelectorAll('[data-copy-target]');
                const copy = (text) => {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        return navigator.clipboard.writeText(text);
                    }
                    const temp = document.createElement('textarea');
                    temp.value = text;
                    document.body.appendChild(temp);
                    temp.select();
                    document.execCommand('copy');
                    document.body.removeChild(temp);
                    return Promise.resolve();
                };
                buttons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const targetId = button.getAttribute('data-copy-target');
                        const target = document.getElementById(targetId);
                        if (!target) {
                            return;
                        }
                        copy(target.value || target.textContent || '').then(() => {
                            button.textContent = 'Copied';
                            setTimeout(() => {
                                button.textContent = targetId === 'prompt-text' ? 'Copy Prompt' : 'Copy Skeleton';
                            }, 1200);
                        });
                    });
                });
            })();
        </script>
        <?php
    });
});
