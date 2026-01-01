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
    unset($_SESSION['assisted_draft_input'][$reqId], $_SESSION['assisted_validation'][$reqId]);
    if ($draftInput === null) {
        $draftInput = json_encode($request['assistantDraft'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    $title = get_app_config()['appName'] . ' | Assisted Request ' . $reqId;
    render_layout($title, function () use ($request, $draftInput, $tender, $actor, $validation) {
        $status = $request['status'] ?? 'requested';
        $pdfRef = $request['tenderPdfRef'] ?? null;
        $requiredKeys = ASSISTED_REQUIRED_FIELDS;
        $sampleJson = json_encode([
            'submissionDeadline' => '2024-12-31T15:00:00+05:30',
            'openingDate' => '2025-01-02T11:00:00+05:30',
            'completionMonths' => 12,
            'bidValidityDays' => 90,
            'eligibilityDocs' => ['Certificate of incorporation', 'GST certificate'],
            'annexures' => ['Annexure A', 'Annexure B'],
            'formats' => [
                ['name' => 'Submission cover sheet', 'notes' => 'Fill in agency name only'],
                ['name' => 'Technical format', 'notes' => 'PDF'],
            ],
            'checklist' => [
                ['title' => 'Upload license copy', 'description' => 'Latest valid license', 'required' => true],
                ['title' => 'PAN card', 'description' => '', 'required' => true],
                ['title' => 'Experience certificates', 'description' => 'Last 3 years', 'required' => true],
            ],
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

        <div class="card" style="display:grid;gap:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0;">Assistant Draft</h3>
                    <p class="muted" style="margin:4px 0 0;">Paste structured JSON matching the assisted extraction schema. BOQ/bid rates are blocked; tender fee/EMD/security amounts and metadata like bidValidityDays are allowed.</p>
                </div>
                <div class="pill">Actor: <?= sanitize(assisted_actor_label($actor)); ?></div>
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
            <div class="muted" style="font-size:13px;">Sample payload covers all required keys and allows bidValidityDays without treating it as forbidden.</div>
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
