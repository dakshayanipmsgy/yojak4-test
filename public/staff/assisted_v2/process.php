<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $actor = require_active_employee();
    if (!employee_has_permission($actor, 'can_process_assisted')) {
        redirect('/staff/dashboard.php');
    }
    ensure_assisted_v2_env();

    $reqId = trim((string)($_GET['reqId'] ?? ''));
    $request = $reqId !== '' ? assisted_v2_load_request($reqId) : null;
    if (!$request) {
        render_error_page('Assisted request not found.');
        return;
    }

    if (($request['status'] ?? '') === 'pending') {
        assisted_v2_assign_request($request, $actor);
        assisted_v2_append_audit($request, assisted_v2_actor_label($actor), 'OPENED');
        assisted_v2_save_request($request);
        assisted_v2_log_event([
            'event' => 'request_opened',
            'reqId' => $reqId,
            'actor' => assisted_v2_actor_label($actor),
        ]);
    }

    $contractorId = $request['contractor']['yojId'] ?? '';
    $offtdId = $request['source']['offtdId'] ?? '';
    $tender = ($contractorId && $offtdId) ? load_offline_tender($contractorId, $offtdId) : null;
    $templatesIndex = assisted_v2_template_index();
    $promptText = assisted_v2_prompt_text();
    $draftPayload = $request['draftPayload'] ?? null;
    $draftSummary = $draftPayload ? assisted_v2_payload_summary($draftPayload) : null;
    $draftStats = $request['draftStats'] ?? [];
    $draftWarnings = $request['draftWarnings'] ?? [];
    $contractorProfile = $contractorId !== '' ? (load_contractor($contractorId) ?? []) : [];
    $previewBundle = $draftPayload ? assisted_v2_preview_bundle($draftPayload, $tender ?? [], $contractorProfile) : null;

    $title = get_app_config()['appName'] . ' | Assisted Pack v2';
    render_layout($title, function () use ($request, $tender, $templatesIndex, $promptText, $draftSummary, $draftPayload, $draftStats, $draftWarnings, $previewBundle) {
        $pdfPath = $request['source']['tenderPdfPath'] ?? '';
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize('Assisted Pack v2'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Request ' . ($request['reqId'] ?? '') . ' • ' . ($request['status'] ?? 'pending')); ?></p>
                </div>
                <a class="btn secondary" href="/staff/assisted_v2/queue.php"><?= sanitize('Back to queue'); ?></a>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:12px;margin-top:12px;">
            <div class="card" style="display:grid;gap:10px;">
                <h3 style="margin:0;"><?= sanitize('Tender PDF'); ?></h3>
                <?php if ($pdfPath): ?>
                    <a class="btn secondary" href="<?= sanitize($pdfPath); ?>" target="_blank" rel="noopener"><?= sanitize('Open PDF'); ?></a>
                <?php else: ?>
                    <p class="muted" style="margin:0;"><?= sanitize('No PDF path found.'); ?></p>
                <?php endif; ?>
                <div style="display:grid;gap:6px;">
                    <div><span class="muted"><?= sanitize('Contractor'); ?></span> <?= sanitize($request['contractor']['name'] ?? ''); ?></div>
                    <div><span class="muted"><?= sanitize('YOJ ID'); ?></span> <?= sanitize($request['contractor']['yojId'] ?? ''); ?></div>
                    <div><span class="muted"><?= sanitize('Mobile'); ?></span> <?= sanitize($request['contractor']['mobile'] ?? ''); ?></div>
                    <div><span class="muted"><?= sanitize('Tender'); ?></span> <?= sanitize($request['source']['tenderTitle'] ?? ($request['source']['offtdId'] ?? '')); ?></div>
                </div>
                <?php if ($tender && !empty($tender['sourceFiles'])): ?>
                    <div class="muted" style="font-size:12px;"><?= sanitize('PDFs available: ' . count($tender['sourceFiles'])); ?></div>
                <?php endif; ?>
            </div>
            <div class="card" style="display:grid;gap:12px;">
                <div>
                    <h3 style="margin:0;"><?= sanitize('A) Apply Department Template Pack'); ?></h3>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Use an existing template pack without AI.'); ?></p>
                </div>
                <form method="post" action="/staff/assisted_v2/apply_template.php" style="display:grid;gap:8px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="reqId" value="<?= sanitize($request['reqId'] ?? ''); ?>">
                    <label class="field">
                        <span><?= sanitize('Template Pack'); ?></span>
                        <select name="templateId" required>
                            <option value=""><?= sanitize('Select template'); ?></option>
                            <?php foreach (($templatesIndex['templates'] ?? []) as $tpl): ?>
                                <option value="<?= sanitize($tpl['templateId'] ?? ''); ?>"><?= sanitize($tpl['name'] ?? 'Template'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="btn" type="submit"><?= sanitize('Apply Template to Generate Pack'); ?></button>
                </form>
            </div>
            <div class="card" style="display:grid;gap:12px;">
                <div>
                    <h3 style="margin:0;"><?= sanitize('B) External AI Extraction (Paste JSON)'); ?></h3>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Upload PDF to ChatGPT/Gemini, then paste JSON here.'); ?></p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <button class="btn secondary" type="button" id="copy-prompt"><?= sanitize('Copy Prompt'); ?></button>
                    <span class="pill"><?= sanitize('Strict JSON only'); ?></span>
                </div>
                <textarea id="prompt-text" style="position:absolute;left:-9999px;top:-9999px;"><?= sanitize($promptText); ?></textarea>
                <form method="post" action="/staff/assisted_v2/paste_validate.php" style="display:grid;gap:8px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="reqId" value="<?= sanitize($request['reqId'] ?? ''); ?>">
                    <label class="field">
                        <span><?= sanitize('Paste JSON payload'); ?></span>
                        <textarea name="payload" rows="10" required style="resize:vertical;"></textarea>
                    </label>
                    <button class="btn" type="submit"><?= sanitize('Validate & Preview'); ?></button>
                </form>
                <?php if ($draftSummary): ?>
                    <?php if (!empty($draftStats['tableKeysGenerated']) || !empty($draftStats['placeholdersFixed'])): ?>
                        <div class="flash" style="background:#0f172a;border:1px solid #38bdf8;">
                            <strong><?= sanitize('Auto-repairs applied'); ?></strong>
                            <ul style="margin:6px 0 0 16px;">
                                <li><?= sanitize('Table placeholders fixed: ' . (int)($draftStats['placeholdersFixed'] ?? 0)); ?></li>
                                <li><?= sanitize('Table field keys generated: ' . (int)($draftStats['tableKeysGenerated'] ?? 0)); ?></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <div class="flash" style="background:#0f1625;border:1px solid #1f6feb;">
                        <strong><?= sanitize('Preview Summary'); ?></strong>
                        <ul style="margin:6px 0 0 16px;">
                            <li><?= sanitize('Checklist items: ' . $draftSummary['checklistCount']); ?></li>
                            <li><?= sanitize('Annexure templates: ' . $draftSummary['annexureTemplatesCount']); ?></li>
                            <li><?= sanitize('Annexures listed: ' . $draftSummary['annexureCount']); ?></li>
                            <li><?= sanitize('Formats listed: ' . $draftSummary['formatCount']); ?></li>
                            <li><?= sanitize('Restricted annexures: ' . $draftSummary['restrictedCount']); ?></li>
                        </ul>
                        <?php if (!empty($draftPayload['restrictedAnnexures'])): ?>
                            <div style="margin-top:10px;display:grid;gap:6px;">
                                <strong><?= sanitize('Restricted annexures'); ?></strong>
                                <div style="display:grid;gap:6px;">
                                    <?php foreach ($draftPayload['restrictedAnnexures'] as $rest): ?>
                                        <?php $label = is_array($rest) ? ($rest['title'] ?? ($rest['name'] ?? 'Restricted')) : (string)$rest; ?>
                                        <div style="border:1px solid #30363d;border-radius:10px;padding:8px 10px;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                                            <span><?= sanitize($label); ?></span>
                                            <span class="pill" style="border-color:#30363d;color:#9da7b3;"><?= sanitize('Not supported (no rates)'); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($draftWarnings)): ?>
                        <div class="flash" style="background:#111827;border:1px solid #334155;">
                            <strong><?= sanitize('Normalization notes'); ?></strong>
                            <ul style="margin:6px 0 0 16px;">
                                <?php foreach ($draftWarnings as $warning): ?>
                                    <li><?= sanitize((string)$warning); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <?php if ($previewBundle): ?>
                        <div class="flash" style="background:#0b111a;border:1px solid #1f6feb33;">
                            <strong><?= sanitize('Resolved field preview'); ?></strong>
                            <div style="display:grid;gap:6px;margin-top:6px;max-height:220px;overflow:auto;">
                                <?php foreach ($previewBundle['fields'] as $field): ?>
                                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;border-bottom:1px dashed #30363d;padding-bottom:4px;">
                                        <span class="muted"><?= sanitize($field['label'] . ' (' . $field['key'] . ')'); ?></span>
                                        <span><?= sanitize($field['value'] !== '' ? $field['value'] : '____'); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <details class="flash" style="background:#0b111a;border:1px dashed #1f6feb33;">
                            <summary style="cursor:pointer;"><?= sanitize('Profile mapping diagnostics'); ?></summary>
                            <div style="display:grid;gap:6px;margin-top:8px;max-height:200px;overflow:auto;">
                                <?php if (!empty($previewBundle['mappingDiagnostics'])): ?>
                                    <ul style="margin:0 0 0 16px;">
                                        <?php foreach ($previewBundle['mappingDiagnostics'] as $entry): ?>
                                            <li class="muted"><?= sanitize((string)$entry); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span class="muted"><?= sanitize('No profile mapping matches detected.'); ?></span>
                                <?php endif; ?>
                            </div>
                        </details>
                        <?php if (!empty($previewBundle['tables'])): ?>
                            <div class="flash" style="background:#0b111a;border:1px solid #1f6feb33;">
                                <strong><?= sanitize('Table structure preview'); ?></strong>
                                <div style="display:grid;gap:10px;margin-top:8px;">
                                    <?php foreach ($previewBundle['tables'] as $table): ?>
                                        <div style="border:1px solid #30363d;border-radius:10px;padding:8px;">
                                            <div style="font-weight:600;"><?= sanitize($table['templateTitle'] ?? 'Annexure'); ?></div>
                                            <div class="muted" style="font-size:12px;"><?= sanitize(($table['templateKind'] ?? '') . ' • ' . ($table['tableTitle'] ?? 'Table')); ?></div>
                                            <div style="overflow:auto;margin-top:6px;">
                                                <table style="min-width:520px;">
                                                    <thead><tr>
                                                        <?php foreach ($table['columns'] as $col): ?>
                                                            <th><?= sanitize($col['label'] ?? $col['key'] ?? ''); ?></th>
                                                        <?php endforeach; ?>
                                                    </tr></thead>
                                                    <tbody>
                                                        <?php foreach ($table['rows'] as $row): ?>
                                                            <?php if (!is_array($row)) { continue; } ?>
                                                            <tr>
                                                                <?php foreach ($table['columns'] as $col): ?>
                                                                    <?php $colKey = pack_normalize_placeholder_key((string)($col['key'] ?? '')); ?>
                                                                    <td><?= sanitize((string)($row[$colKey] ?? '')); ?></td>
                                                                <?php endforeach; ?>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <form method="post" action="/staff/assisted_v2/deliver.php" style="display:grid;gap:8px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="reqId" value="<?= sanitize($request['reqId'] ?? ''); ?>">
                        <label class="pill" style="display:inline-flex;gap:6px;align-items:center;">
                            <input type="checkbox" name="save_template" value="1" checked> <?= sanitize('Save as Department Template Pack after delivery'); ?>
                        </label>
                        <button class="btn" type="submit"><?= sanitize('Deliver Pack to Contractor'); ?></button>
                    </form>
                <?php else: ?>
                    <p class="muted" style="margin:0;"><?= sanitize('Validate JSON to preview counts before delivery.'); ?></p>
                <?php endif; ?>
            </div>
            <div class="card" style="display:grid;gap:12px;">
                <div>
                    <h3 style="margin:0;"><?= sanitize('Reject Request'); ?></h3>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Provide a clear reason so contractors can re-request.'); ?></p>
                </div>
                <form method="post" action="/staff/assisted_v2/reject.php" style="display:grid;gap:8px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="reqId" value="<?= sanitize($request['reqId'] ?? ''); ?>">
                    <label class="field">
                        <span><?= sanitize('Reject reason'); ?></span>
                        <textarea name="reason" rows="4" required style="resize:vertical;"></textarea>
                    </label>
                    <button class="btn secondary" type="submit"><?= sanitize('Reject Request'); ?></button>
                </form>
            </div>
        </div>
        <script>
            (() => {
                const copyBtn = document.getElementById('copy-prompt');
                const prompt = document.getElementById('prompt-text');
                if (copyBtn && prompt) {
                    copyBtn.addEventListener('click', async () => {
                        try {
                            await navigator.clipboard.writeText(prompt.value);
                            copyBtn.textContent = 'Prompt Copied';
                            setTimeout(() => copyBtn.textContent = 'Copy Prompt', 2000);
                        } catch (err) {
                            alert('Copy failed. Select the prompt text manually.');
                        }
                    });
                }
            })();
        </script>
        <?php
    });
});
