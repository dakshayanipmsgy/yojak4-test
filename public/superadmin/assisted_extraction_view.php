<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = assisted_staff_actor();
    $reqId = trim($_GET['reqId'] ?? '');
    if ($reqId === '') {
        render_error_page('Request id missing.');
        return;
    }

    $request = assisted_load_request($reqId);
    if (!$request) {
        render_error_page('Request not found.');
        return;
    }

    $yojId = $request['yojId'] ?? '';
    $offtdId = $request['offtdId'] ?? '';
    $tender = null;
    if ($yojId && $offtdId) {
        ensure_offline_tender_env($yojId);
        $tender = load_offline_tender($yojId, $offtdId);
    }

    $draftInput = $_SESSION['assisted_draft_input'][$reqId] ?? null;
    $validation = $_SESSION['assisted_validation'][$reqId] ?? null;
    $nonBlockingFindings = $validation['nonBlockingFindings'] ?? [];
    unset($_SESSION['assisted_draft_input'][$reqId], $_SESSION['assisted_validation'][$reqId]);
    if ($draftInput === null) {
        $draftInput = json_encode($request['assistantDraft'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    $title = get_app_config()['appName'] . ' | Assisted Request ' . $reqId;
    render_layout($title, function () use ($request, $draftInput, $tender, $actor, $validation, $nonBlockingFindings) {
        $status = $request['status'] ?? 'requested';
        $pdfRef = $request['tenderPdfRef'] ?? null;
        $requiredKeys = ASSISTED_REQUIRED_FIELDS;
        $sampleJson = json_encode([
            'tender' => [
                'documentType' => 'NIB',
                'tenderTitle' => 'Construction of Community Hall at Ranchi',
                'submissionDeadline' => '2025-01-15T15:00:00+05:30',
                'openingDate' => '2025-01-16T15:30:00+05:30',
                'completionMonths' => 6,
                'validityDays' => 120,
            ],
            'lists' => [
                'eligibilityDocs' => ['GST Registration', 'PAN Card', 'Character Certificate'],
                'annexures' => ['Annexure-I', 'Annexure-II'],
                'formats' => [['name' => 'Affidavit', 'notes' => 'Non-judicial stamp paper']],
                'restricted' => ['Financial Bid Format (Part-B)'],
            ],
            'checklist' => [
                ['title' => 'Tender Fee', 'description' => 'Rs. 5000 via DD', 'required' => true, 'category' => 'Fees'],
                ['title' => 'EMD', 'description' => 'Rs. 50000', 'required' => true, 'category' => 'Fees'],
            ],
            'templates' => [
                [
                    'code' => 'Annexure-I',
                    'name' => 'General Undertaking',
                    'type' => 'declaration',
                    'body' => "I, {{contractor_firm_name}}, hereby declare...",
                    'placeholders' => ['{{contractor_firm_name}}']
                ]
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        ?>
        <div class="card" style="display:grid;gap:10px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Request <?= sanitize($request['reqId'] ?? ''); ?></h2>
                    <p class="muted" style="margin:4px 0 0;">
                        Contractor <?= sanitize($request['yojId'] ?? ''); ?> • Tender <?= sanitize($request['offtdId'] ?? ''); ?>
                    </p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
                        <span class="pill"><?= sanitize(ucwords(str_replace('_',' ', $status))); ?></span>
                        <?php if (!empty($request['assignedTo'])): ?>
                            <span class="pill">Assigned: <?= sanitize($request['assignedTo']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($request['deliveredAt'])): ?>
                            <span class="pill success">Delivered: <?= sanitize($request['deliveredAt']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <a class="btn secondary" href="/superadmin/assisted_extraction_queue.php">Back to queue</a>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px;">
                <div style="border:1px solid #30363d;border-radius:12px;padding:10px;">
                    <h4 style="margin:0 0 6px 0;">Tender PDF</h4>
                    <?php if ($pdfRef && !empty($pdfRef['storedPath'])): ?>
                        <a class="btn secondary" href="<?= sanitize($pdfRef['storedPath']); ?>" target="_blank" rel="noopener"><?= sanitize($pdfRef['fileName'] ?? 'Open PDF'); ?></a>
                        <p class="muted" style="margin:6px 0 0;"><?= sanitize(($pdfRef['size'] ?? 0) . ' bytes • ' . ($pdfRef['mime'] ?? '')); ?></p>
                    <?php else: ?>
                        <p class="muted" style="margin:0;">No PDF reference captured.</p>
                    <?php endif; ?>
                </div>
                <div style="border:1px solid #30363d;border-radius:12px;padding:10px;">
                    <h4 style="margin:0 0 6px 0;">Tender snapshot</h4>
                    <?php if ($tender): ?>
                        <p style="margin:0;"><strong><?= sanitize($tender['title'] ?? ''); ?></strong></p>
                        <p class="muted" style="margin:4px 0 0;"><?= sanitize($tender['location'] ?? ''); ?></p>
                        <p class="muted" style="margin:4px 0 0;">Submission: <?= sanitize($tender['extracted']['submissionDeadline'] ?? ''); ?></p>
                    <?php else: ?>
                        <p class="muted" style="margin:0;">Tender not available (might be archived).</p>
                    <?php endif; ?>
                </div>
                <div style="border:1px solid #30363d;border-radius:12px;padding:10px;">
                    <h4 style="margin:0 0 6px 0;">Notes from contractor</h4>
                    <p style="margin:0;white-space:pre-wrap;"><?= sanitize($request['notesFromContractor'] ?? ''); ?></p>
                </div>
            </div>
        </div>

        <?php if ($tender): ?>
            <?php
            // Generate the prompt for the staff member
            [$systemPrompt, $userPrompt] = offline_tender_ai_prompt($tender, false);
            $fullPrompt = "System: " . $systemPrompt . "\n\nUser: " . $userPrompt;
            ?>
            <div class="card" style="display:grid;gap:10px;">
                <h3 style="margin:0;">External AI Prompt</h3>
                <p class="muted" style="margin:0;">Copy this detailed prompt and paste it into ChatGPT (o1/4o) or Gemini Advanced. It contains the schema instructions and the raw text from the source PDF.</p>
                <textarea id="prompt-area" rows="6" readonly style="resize:vertical;background:#0d1117;color:#8b949e;border:1px solid #30363d;border-radius:10px;padding:8px;font-family:monospace;font-size:12px;"><?= sanitize($fullPrompt); ?></textarea>
                <button type="button" class="btn secondary" onclick="navigator.clipboard.writeText(document.getElementById('prompt-area').value).then(()=>alert('Prompt copied!'));">Copy Prompt to Clipboard</button>
            </div>
        <?php endif; ?>

            <div class="card" style="display:grid;gap:10px;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                    <div>
                        <h3 style="margin:0;">Assistant Draft</h3>
                        <p class="muted" style="margin:4px 0 0;">Paste the JSON response from the external AI here. The system will validate and structure it.</p>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <button type="button" class="btn secondary" onclick="const prev=document.getElementById('contractor-preview');if(prev){prev.open=true;prev.scrollIntoView({behavior:'smooth'});}"><?= sanitize('Preview: what contractor will see'); ?></button>
                        <div class="pill">Actor: <?= sanitize(assisted_actor_label($actor)); ?></div>
                    </div>
                </div>
                <div style="display:grid;gap:8px;padding:12px;border:1px dashed #30363d;border-radius:12px;background:#0f1622;">
                    <strong>Required keys</strong>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        <?php foreach ($requiredKeys as $key): ?>
                        <span class="pill"><?= sanitize($key); ?></span>
                    <?php endforeach; ?>
                </div>
                <p class="muted" style="margin:4px 0 0;">Values can be empty/null, but keys must exist. Formats can be an array of strings or objects with name/notes. Checklist is optional but recommended.</p>
            </div>

            <?php if ($validation && (!empty($validation['errors']) || !empty($validation['missingKeys']) || !empty($validation['forbiddenFindings']) || !empty($validation['jsonError']))): ?>
                <div class="card error-card" style="background:#1b1114;">
                    <h4 style="margin:0 0 8px 0;">Validation issues</h4>
                    <?php if (!empty($validation['jsonError'])): ?>
                        <p class="muted" style="margin:4px 0;">JSON error: <?= sanitize((string)$validation['jsonError']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($validation['errors'])): ?>
                        <ul style="margin:6px 0 0 16px;padding:0;color:#f77676;">
                            <?php foreach ($validation['errors'] as $err): ?>
                                <li><?= sanitize($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if (!empty($validation['missingKeys'])): ?>
                        <p class="muted" style="margin:6px 0 0;">Missing keys: <?= sanitize(implode(', ', $validation['missingKeys'])); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($validation['forbiddenFindings'])): ?>
                        <div class="muted" style="margin-top:6px;">
                            <strong>Pricing/rate content detected.</strong>
                            <p style="margin:4px 0 0;">Tender fee/EMD/security amounts are allowed; BOQ/quoted rates and item prices are not.</p>
                            <ul style="margin:6px 0 0 16px;padding:0;">
                                <?php foreach ($validation['forbiddenFindings'] as $finding): ?>
                                    <li><?= sanitize(($finding['path'] ?? 'field') . ' • ' . ($finding['reasonCode'] ?? 'reason') . ' • ' . ($finding['snippet'] ?? '')); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($validation && !empty($nonBlockingFindings)): ?>
                <div class="card" style="background:#181204;border:1px solid #3a2d16;border-radius:12px;">
                    <h4 style="margin:0 0 8px 0;color:#fcd34d;">Review needed (currency context)</h4>
                    <p class="muted" style="margin:4px 0 8px;">Currency found but context unclear. These are warnings only; ensure they are not bid pricing.</p>
                    <ul style="margin:6px 0 0 16px;padding:0;color:#fcd34d;">
                        <?php foreach ($nonBlockingFindings as $finding): ?>
                            <li><?= sanitize(($finding['path'] ?? 'field') . ' • ' . ($finding['reasonCode'] ?? 'reason') . ' • ' . ($finding['snippet'] ?? '')); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="/superadmin/assisted_extraction_update.php" style="display:grid;gap:10px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="reqId" value="<?= sanitize($request['reqId'] ?? ''); ?>">
                <textarea name="assistantDraft" rows="18" style="width:100%;resize:vertical;background:#0d1117;color:#e6edf3;border:1px solid #30363d;border-radius:10px;padding:10px;"><?= sanitize($draftInput); ?></textarea>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <button class="btn secondary" type="submit" name="action" value="save">Save Draft</button>
                    <button class="btn" type="submit" name="action" value="deliver">Deliver to contractor</button>
                    <button type="button" class="btn secondary" id="assisted-sample-btn">Paste sample JSON</button>
                    <span class="muted">Statuses: save = in progress; deliver = delivered + notify contractor.</span>
                </div>
            </form>
            <div class="muted" style="font-size:13px;">Use the 'Copy Prompt' button above to get the correct schema instructions for the AI.</div>
            <?php $previewDraft = is_array($request['assistantDraft'] ?? null) ? $request['assistantDraft'] : []; ?>
            <?php $previewChecklist = $previewDraft['checklist'] ?? []; ?>
            <?php $previewLists = $previewDraft['lists'] ?? []; ?>
            <details id="contractor-preview" style="border:1px solid #30363d;border-radius:12px;padding:12px;background:#0f1520;margin-top:6px;">
                <summary style="cursor:pointer;font-weight:600;"><?= sanitize('Preview: what contractor will see'); ?></summary>
                <div style="display:grid;gap:8px;margin-top:8px;">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <span class="pill"><?= sanitize(count($previewChecklist) . ' checklist items'); ?></span>
                        <span class="pill"><?= sanitize(count($previewLists['annexures'] ?? []) . ' annexures'); ?></span>
                        <span class="pill"><?= sanitize(count($previewLists['restricted'] ?? []) . ' restricted'); ?></span>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px;">
                        <div>
                            <strong><?= sanitize('Key dates'); ?></strong>
                            <p class="muted" style="margin:4px 0 0;"><?= sanitize('Submission: ' . ($previewDraft['tender']['submissionDeadline'] ?? '')); ?></p>
                            <p class="muted" style="margin:4px 0 0;"><?= sanitize('Opening: ' . ($previewDraft['tender']['openingDate'] ?? '')); ?></p>
                        </div>
                        <div>
                            <strong><?= sanitize('Fees (text allowed)'); ?></strong>
                            <?php $feesPreview = $previewDraft['fees'] ?? []; ?>
                            <p class="muted" style="margin:4px 0 0;"><?= sanitize('Tender fee: ' . ($feesPreview['tenderFeeText'] ?? '')); ?></p>
                            <p class="muted" style="margin:4px 0 0;"><?= sanitize('EMD: ' . ($feesPreview['emdText'] ?? '')); ?></p>
                        </div>
                    </div>
                    <?php if ($previewChecklist): ?>
                        <div>
                            <strong><?= sanitize('Sample checklist'); ?></strong>
                            <ul style="margin:6px 0 0 16px;padding:0;">
                                <?php foreach (array_slice($previewChecklist, 0, 5) as $item): ?>
                                    <li><?= sanitize(($item['title'] ?? '') . (!empty($item['required']) ? ' (Required)' : ' (Optional)')); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if (count($previewChecklist) > 5): ?>
                                <p class="muted" style="margin:4px 0 0;"><?= sanitize('+' . (count($previewChecklist) - 5) . ' more'); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($previewLists['annexures'])): ?>
                        <div>
                            <strong><?= sanitize('Annexures to generate'); ?></strong>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
                                <?php foreach (array_slice($previewLists['annexures'], 0, 6) as $ann): ?>
                                    <span class="pill"><?= sanitize(is_array($ann) ? ($ann['title'] ?? ($ann['name'] ?? 'Annexure')) : (string)$ann); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($previewLists['restricted'])): ?>
                        <div class="flash" style="background:#211015;border:1px solid #3a2a18;">
                            <strong><?= sanitize('Restricted annexures detected'); ?></strong>
                            <p class="muted" style="margin:6px 0 0;"><?= sanitize('These will be listed but never generated for rate entry.'); ?></p>
                            <ul style="margin:6px 0 0 16px;padding:0;color:#fcd34d;">
                                <?php foreach ($previewLists['restricted'] as $rest): ?>
                                    <li><?= sanitize(is_array($rest) ? ($rest['name'] ?? ($rest['title'] ?? 'Restricted')) : (string)$rest); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
            <div>
                <h4 style="margin:0 0 6px 0;">Audit Trail</h4>
                <div style="display:grid;gap:6px;">
                    <?php foreach (($request['audit'] ?? []) as $audit): ?>
                        <div class="pill" style="display:flex;gap:8px;flex-wrap:wrap;">
                            <span><?= sanitize($audit['at'] ?? ''); ?></span>
                            <span><?= sanitize($audit['by'] ?? ''); ?></span>
                            <span><?= sanitize($audit['action'] ?? ''); ?></span>
                            <?php if (!empty($audit['note'])): ?>
                                <span class="muted"><?= sanitize($audit['note']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($request['audit'])): ?>
                        <p class="muted" style="margin:0;">No audit entries yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script>
            (function () {
                const btn = document.getElementById('assisted-sample-btn');
                if (!btn) return;
                btn.addEventListener('click', function () {
                    const sample = <?= json_encode($sampleJson); ?>;
                    const textarea = document.querySelector('textarea[name="assistantDraft"]');
                    if (textarea) {
                        textarea.value = sample;
                        textarea.focus();
                    }
                });
            })();
        </script>
        <?php
    });
});
