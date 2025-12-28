<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

function format_date_value(?string $value): string
{
    if (!$value) {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($value, new DateTimeZone('Asia/Kolkata'));
        return $dt->format('Y-m-d');
    } catch (Throwable $e) {
        return '';
    }
}

function format_datetime_value(?string $value): string
{
    if (!$value) {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($value, new DateTimeZone('Asia/Kolkata'));
        return $dt->format('Y-m-d\TH:i');
    } catch (Throwable $e) {
        return '';
    }
}

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_offline_tender_env($yojId);
    ensure_packs_env($yojId);
    ensure_assisted_extraction_env();

    $offtdId = trim($_GET['id'] ?? '');
    $tender = $offtdId !== '' ? load_offline_tender($yojId, $offtdId) : null;
    $existingPack = $offtdId !== '' ? find_pack_by_source($yojId, 'OFFTD', $offtdId) : null;
    $assistedRequest = $offtdId !== '' ? assisted_active_request_for_tender($yojId, $offtdId) : null;

    if (!$tender || ($tender['yojId'] ?? '') !== $yojId) {
        render_error_page('Tender not found.');
        return;
    }

    $title = get_app_config()['appName'] . ' | ' . ($tender['title'] ?? 'Offline Tender');

    render_layout($title, function () use ($tender, $existingPack, $assistedRequest) {
        $extracted = $tender['extracted'] ?? offline_tender_defaults();
        $checklist = $tender['checklist'] ?? [];
        $aiDefaults = [
            'parsedOk' => false,
            'providerOk' => false,
            'errors' => [],
            'rawText' => '',
            'lastRunAt' => null,
            'httpStatus' => null,
            'parseStage' => 'fallback_manual',
            'provider' => '',
            'requestId' => null,
            'responseId' => null,
            'finishReason' => null,
            'promptBlockReason' => null,
            'safetyRatingsSummary' => '',
            'retryCount' => 0,
            'fallbackUsed' => false,
            'rawEnvelope' => null,
            'runMode' => 'strict',
        ];
        $ai = array_merge($aiDefaults, $tender['ai'] ?? []);
        $ai['rawEnvelope'] = is_array($ai['rawEnvelope'] ?? null) ? $ai['rawEnvelope'] : [];
        $source = $tender['source'] ?? [];
        $aiHttpBadge = $ai['httpStatus'] ? 'HTTP ' . $ai['httpStatus'] : 'HTTP ?';
        $aiParseBadge = $ai['parsedOk'] ? 'Parsed' : ($ai['providerOk'] ? 'Needs Review' : 'Provider Error');
        $aiBlocked = !empty($ai['promptBlockReason']);
        $aiEmpty = !empty($ai['providerOk']) && trim((string)($ai['rawText'] ?? '')) === '';
        $aiEmptyContentError = false;
        foreach ((array)($ai['errors'] ?? []) as $err) {
            if (stripos((string)$err, 'empty content') !== false) {
                $aiEmptyContentError = true;
                break;
            }
        }
        $aiEmptyEvents = (int)($ai['emptyContentEvents'] ?? 0);
        $aiEmptyAnomaly = $aiEmptyContentError && ($ai['provider'] ?? '') === 'gemini';
        $aiEmptyRepeated = $aiEmptyAnomaly && $aiEmptyEvents >= 2;
        ?>
        <div class="card" style="display:grid; gap:10px;">
            <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:center;">
                <div>
                    <h2 style="margin:0;"><?= sanitize($tender['title'] ?? 'Offline Tender'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;">
                        <?= sanitize($tender['id']); ?> • <?= sanitize(ucfirst($tender['status'] ?? 'draft')); ?>
                        <?php if (!empty($tender['deletedAt'])): ?>
                            • <span style="color:#f77676;"><?= sanitize('Archived'); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="buttons" style="gap:8px;">
                    <a class="btn secondary" href="/contractor/offline_tenders.php"><?= sanitize('Back to list'); ?></a>
                </div>
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                <form method="post" action="/contractor/offline_tender_run_ai.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($tender['id']); ?>">
                    <input type="hidden" name="run_mode" value="strict">
                    <button class="btn" type="submit"><?= sanitize('Re-run AI (JSON strict)'); ?></button>
                </form>
                <form method="post" action="/contractor/offline_tender_run_ai.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($tender['id']); ?>">
                    <input type="hidden" name="run_mode" value="lenient">
                    <button class="btn secondary" type="submit"><?= sanitize('Re-run AI (lenient)'); ?></button>
                </form>
                <?php if (!empty($ai['parsedOk']) && is_array($ai['candidateExtracted'] ?? null)): ?>
                    <form method="post" action="/contractor/offline_tender_update.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="id" value="<?= sanitize($tender['id']); ?>">
                        <input type="hidden" name="mode" value="apply_ai">
                        <button class="btn" type="submit"><?= sanitize('Apply extracted fields'); ?></button>
                    </form>
                <?php endif; ?>
                <?php if ($existingPack): ?>
                    <a class="btn secondary" href="/contractor/pack_view.php?packId=<?= sanitize($existingPack['packId']); ?>"><?= sanitize('Open Tender Pack'); ?></a>
                <?php else: ?>
                    <form method="post" action="/contractor/pack_create.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="offtdId" value="<?= sanitize($tender['id']); ?>">
                        <label class="pill" style="display:inline-flex;gap:6px;align-items:center;">
                            <input type="checkbox" name="include_defaults" value="1" checked> <?= sanitize('Add default tender letters'); ?>
                        </label>
                        <button class="btn" type="submit"><?= sanitize('Create Tender Pack'); ?></button>
                    </form>
                <?php endif; ?>
                <form method="post" action="/contractor/offline_tender_add_reminders.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($tender['id']); ?>">
                    <button class="btn secondary" type="submit"><?= sanitize('Create reminders'); ?></button>
                </form>
                <form method="post" action="/contractor/offline_tender_update.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($tender['id']); ?>">
                    <input type="hidden" name="mode" value="upload">
                    <label class="btn secondary" style="display:inline-flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="file" name="additional_documents[]" accept=".pdf" multiple required style="position:absolute; width:1px; height:1px; opacity:0;" onchange="this.form.submit();">
                        <?= sanitize('Upload more PDFs'); ?>
                    </label>
                </form>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <?php if (($source['type'] ?? '') === 'tender_discovery' && !empty($source['discId'])): ?>
                    <span class="pill">Source: <?= sanitize($source['discId']); ?></span>
                    <a class="pill" href="/contractor/discovered_tender_view.php?id=<?= sanitize(urlencode($source['discId'])); ?>">Discovered tender</a>
                    <?php if (!empty($source['originalUrl'])): ?>
                        <a class="pill" href="<?= sanitize($source['originalUrl']); ?>" target="_blank" rel="noopener"><?= sanitize('View notice'); ?></a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($tender['location'])): ?>
                    <span class="pill"><?= sanitize($tender['location']); ?></span>
                <?php endif; ?>
                <span class="pill"><?= sanitize($aiHttpBadge); ?></span>
                <span class="pill" style="<?= $ai['parsedOk'] ? 'background:#183d2f;color:#9ef0c0;' : ($ai['providerOk'] ? 'background:#3d2f18;color:#f2c265;' : 'background:#3d1818;color:#f77676;'); ?>">
                    <?= sanitize($aiParseBadge); ?>
                </span>
                <span class="pill muted">Stage: <?= sanitize($ai['parseStage'] ?? 'fallback_manual'); ?></span>
                <?php if (!empty($ai['lastRunAt'])): ?>
                    <span class="pill muted">Last AI: <?= sanitize($ai['lastRunAt']); ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($ai['lastRunAt']) && (!empty($ai['errors']) || empty($ai['parsedOk']))): ?>
                <div class="flashes">
                    <div class="flash error">
                        <?php if ($aiBlocked): ?>
                            <?= sanitize('AI response blocked by safety filters (' . ($ai['promptBlockReason'] ?? 'blocked') . '). Please rephrase and try again.'); ?>
                        <?php elseif ($aiEmpty): ?>
                            <?= sanitize('Gemini returned an empty final response. Streaming fallback, retry, and fallback model attempts were triggered automatically. Consider switching models in AI Studio if the issue persists.'); ?>
                        <?php elseif (!empty($ai['providerOk'])): ?>
                            <?= sanitize('AI responded, but the output was not in the expected format. You can edit manually, or re-run AI. The raw AI text is shown below.'); ?>
                        <?php else: ?>
                            <?= sanitize('The AI provider reported a problem. Please review the debug info below and retry.'); ?>
                        <?php endif; ?>
                        <?php if (!empty($ai['errors'])): ?>
                            <ul style="margin:6px 0 0 16px;">
                                <?php foreach ($ai['errors'] as $err): ?>
                                    <li><?= sanitize($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($aiEmptyAnomaly): ?>
            <div class="card" style="margin-top:12px;display:grid;gap:10px;background:#0f1826;border:1px solid #27344a;">
                <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;">
                    <div>
                        <h3 style="margin:0;"><?= sanitize('AI empty response detected'); ?></h3>
                        <p class="muted" style="margin:4px 0 0;"><?= sanitize('AI provider returned empty final output. Please ask admin to switch extraction model or try again later.'); ?></p>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <?php if ($aiEmptyRepeated): ?>
                            <span class="pill" style="background:#402222;color:#f6c7c7;"><?= sanitize('Repeated anomaly'); ?></span>
                        <?php endif; ?>
                        <span class="pill" style="background:#1f6feb;color:#e6edf3;"><?= sanitize('Gemini monitor'); ?></span>
                    </div>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <span class="pill muted"><?= sanitize('Empty responses logged: ' . max(1, $aiEmptyEvents)); ?></span>
                    <?php if (!empty($ai['lastRunAt'])): ?>
                        <span class="pill muted"><?= sanitize('Last AI run: ' . $ai['lastRunAt']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($ai['runMode'])): ?>
                        <span class="pill muted"><?= sanitize('Mode: ' . ucfirst($ai['runMode'])); ?></span>
                    <?php endif; ?>
                </div>
                <div class="muted" style="line-height:1.5;">
                    <?= sanitize('Recommended fix: switch Offline Tender Extraction from gemini-3-pro-preview to a Flash fallback in AI Studio with structured outputs ON. This avoids blank final responses and keeps retries safer.'); ?>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px;align-items:center;">
                    <ul class="muted" style="margin:0;padding-left:18px;line-height:1.6;">
                        <li><?= sanitize('Share this notice with your admin or superadmin.'); ?></li>
                        <li><?= sanitize('Admins can open AI Studio > Offline Tender Extraction to switch to the Flash fallback model.'); ?></li>
                        <li><?= sanitize('Re-run AI after the switch or wait a few minutes before retrying.'); ?></li>
                    </ul>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;">
                        <a class="btn secondary" href="/superadmin/ai_studio.php#offline-extraction"><?= sanitize('Contact admin / open AI Studio'); ?></a>
                        <form method="post" action="/contractor/offline_tender_run_ai.php" class="ai-rerun-form" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <input type="hidden" name="id" value="<?= sanitize($tender['id']); ?>">
                            <input type="hidden" name="run_mode" value="<?= sanitize($ai['runMode'] ?? 'strict'); ?>">
                            <button class="btn" type="submit"><?= sanitize('Retry AI now'); ?></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $assistedStatus = $assistedRequest['status'] ?? 'none';
        $assistedDelivered = $assistedStatus === 'delivered';
        $assistedDraft = $assistedRequest['assistantDraft'] ?? [];
        ?>
        <div class="card" style="margin-top:12px;display:grid;gap:10px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0;"><?= sanitize('Assisted Extraction'); ?></h3>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Request human-in-the-loop checklist prep when AI cannot parse your NIT.'); ?></p>
                </div>
                <span class="pill" style="<?= $assistedDelivered ? 'border-color:#2ea043;color:#8ce99a;' : 'border-color:#f59f00;color:#fcd34d;'; ?>">
                    <?= sanitize('Status: ' . ($assistedStatus === 'none' ? 'Not requested' : ucwords(str_replace('_',' ', $assistedStatus)))); ?>
                </span>
            </div>
            <?php if (!$assistedRequest): ?>
                <form method="post" action="/contractor/offline_tender_request_help.php" style="display:grid;gap:8px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($tender['id']); ?>">
                    <label class="field">
                        <span><?= sanitize('Notes for Yojak team (optional, max 500 chars)'); ?></span>
                        <textarea name="notes" rows="3" maxlength="500" style="resize:vertical;"></textarea>
                    </label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <button class="btn" type="submit"><?= sanitize('Request Assisted Extraction'); ?></button>
                        <span class="muted"><?= sanitize('Limit: 3 requests/week per contractor, 1 active per tender.'); ?></span>
                    </div>
                </form>
            <?php else: ?>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <span class="pill"><?= sanitize('Created: ' . ($assistedRequest['createdAt'] ?? '')); ?></span>
                    <?php if (!empty($assistedRequest['assignedTo'])): ?>
                        <span class="pill"><?= sanitize('Assigned: ' . $assistedRequest['assignedTo']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($assistedRequest['deliveredAt'])): ?>
                        <span class="pill success"><?= sanitize('Delivered: ' . $assistedRequest['deliveredAt']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($assistedDelivered && is_array($assistedDraft)): ?>
                    <div style="border:1px solid #30363d;border-radius:12px;padding:10px;display:grid;gap:8px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                            <h4 style="margin:0;"><?= sanitize('Delivered checklist'); ?></h4>
                            <form method="post" action="/contractor/offline_tender_apply_assisted.php" style="margin:0;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="id" value="<?= sanitize($tender['id']); ?>">
                                <input type="hidden" name="reqId" value="<?= sanitize($assistedRequest['reqId'] ?? ''); ?>">
                                <button class="btn" type="submit"><?= sanitize('Apply to tender'); ?></button>
                            </form>
                        </div>
                        <?php if (!empty($assistedDraft['checklist']) && is_array($assistedDraft['checklist'])): ?>
                            <div style="display:grid;gap:8px;">
                                <?php foreach ($assistedDraft['checklist'] as $item): ?>
                                    <div style="border:1px solid #30363d;border-radius:10px;padding:10px;">
                                        <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                                            <strong><?= sanitize($item['title'] ?? ''); ?></strong>
                                            <span class="pill" style="<?= !empty($item['required']) ? 'border-color:#2ea043;color:#8ce99a;' : ''; ?>">
                                                <?= !empty($item['required']) ? sanitize('Required') : sanitize('Optional'); ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($item['description'])): ?>
                                            <p class="muted" style="margin:6px 0 0;"><?= sanitize($item['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="muted" style="margin:0;"><?= sanitize('No checklist entries found in the delivered draft.'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="muted" style="margin:0;"><?= sanitize('Team is working on your request. You will see the checklist here once delivered.'); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:12px; margin-top:12px;">
            <div class="card" style="display:grid; gap:12px;">
                <h3 style="margin:0;"><?= sanitize('Tender details'); ?></h3>
                <form method="post" action="/contractor/offline_tender_update.php" style="display:grid; gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($tender['id']); ?>">
                    <input type="hidden" name="mode" value="save_details">
                    <div class="field">
                        <label><?= sanitize('Title'); ?></label>
                        <input name="title" value="<?= sanitize($tender['title'] ?? ''); ?>" required>
                    </div>
                    <div class="field">
                        <label><?= sanitize('Publish date'); ?></label>
                        <input type="date" name="publishDate" value="<?= sanitize(format_date_value($extracted['publishDate'] ?? null)); ?>">
                    </div>
                    <div class="field">
                        <label><?= sanitize('Submission deadline'); ?></label>
                        <input type="datetime-local" name="submissionDeadline" value="<?= sanitize(format_datetime_value($extracted['submissionDeadline'] ?? null)); ?>">
                    </div>
                    <div class="field">
                        <label><?= sanitize('Opening date'); ?></label>
                        <input type="datetime-local" name="openingDate" value="<?= sanitize(format_datetime_value($extracted['openingDate'] ?? null)); ?>">
                    </div>
                    <div class="field">
                        <label><?= sanitize('Fees'); ?></label>
                        <input name="fees[tenderFee]" placeholder="<?= sanitize('Tender fee'); ?>" value="<?= sanitize($extracted['fees']['tenderFee'] ?? ''); ?>">
                        <input name="fees[emd]" placeholder="<?= sanitize('EMD'); ?>" value="<?= sanitize($extracted['fees']['emd'] ?? ''); ?>">
                        <input name="fees[other]" placeholder="<?= sanitize('Other'); ?>" value="<?= sanitize($extracted['fees']['other'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label><?= sanitize('Completion months'); ?></label>
                        <input type="number" min="0" name="completionMonths" value="<?= sanitize((string)($extracted['completionMonths'] ?? '')); ?>">
                    </div>
                    <div class="field">
                        <label><?= sanitize('Bid validity days'); ?></label>
                        <input type="number" min="0" name="bidValidityDays" value="<?= sanitize((string)($extracted['bidValidityDays'] ?? '')); ?>">
                    </div>
                    <div class="field">
                        <label><?= sanitize('Eligibility documents (one per line)'); ?></label>
                        <textarea name="eligibilityDocs" rows="4" style="resize:vertical;"><?= sanitize(implode("\n", $extracted['eligibilityDocs'] ?? [])); ?></textarea>
                    </div>
                    <div class="field">
                        <label><?= sanitize('Annexures (one per line)'); ?></label>
                        <textarea name="annexures" rows="3" style="resize:vertical;"><?= sanitize(implode("\n", $extracted['annexures'] ?? [])); ?></textarea>
                    </div>
                    <div class="field">
                        <label><?= sanitize('Formats (name|notes per line)'); ?></label>
                        <textarea name="formats" rows="3" style="resize:vertical;"><?= sanitize(implode("\n", array_map(function ($f) {
                            return ($f['name'] ?? '') . '|' . ($f['notes'] ?? '');
                        }, $extracted['formats'] ?? []))); ?></textarea>
                    </div>
                    <button class="btn" type="submit"><?= sanitize('Save edits'); ?></button>
                </form>
                <div>
                    <h4 style="margin:0 0 6px 0;"><?= sanitize('Source PDFs'); ?></h4>
                    <ul style="margin:0 0 0 16px; padding:0; color:var(--muted);">
                        <?php foreach (($tender['sourceFiles'] ?? []) as $file): ?>
                            <li style="margin-bottom:4px;">
                                <a href="<?= sanitize($file['path'] ?? '#'); ?>" target="_blank" rel="noopener"><?= sanitize($file['name'] ?? 'file'); ?></a>
                                <span class="muted">• <?= sanitize(format_bytes((int)($file['sizeBytes'] ?? 0))); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <details style="border:1px solid #30363d; border-radius:10px; padding:10px; background:#0f1520;">
                    <summary style="cursor:pointer;"><?= sanitize('Diagnostics'); ?></summary>
                    <div style="margin-top:10px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;align-items:start;">
                        <div style="display:grid;gap:6px;">
                            <div class="pill"><?= sanitize('Provider: ' . ($ai['provider'] ?: 'not run')); ?></div>
                            <div class="pill"><?= sanitize('Model: ' . (($ai['rawEnvelope']['model'] ?? '') ?: 'unknown')); ?></div>
                            <div class="pill"><?= sanitize('HTTP: ' . ($ai['httpStatus'] ?? 'n/a')); ?></div>
                            <div class="pill"><?= sanitize('Finish reason: ' . (($ai['finishReason'] ?? 'n/a'))); ?></div>
                            <div class="pill"><?= sanitize('Block reason: ' . (($ai['promptBlockReason'] ?? 'none'))); ?></div>
                            <div class="pill"><?= sanitize('Retry count: ' . ((int)($ai['retryCount'] ?? 0))); ?></div>
                            <div class="pill"><?= sanitize('Fallback used: ' . (!empty($ai['fallbackUsed']) ? 'yes' : 'no')); ?></div>
                            <div class="pill muted"><?= sanitize('Parsed: ' . ($ai['parsedOk'] ? 'yes' : 'no')); ?></div>
                            <div class="pill muted"><?= sanitize('Parse stage: ' . ($ai['parseStage'] ?? 'fallback_manual')); ?></div>
                            <?php if (!empty($ai['requestId'])): ?>
                                <div class="pill muted"><?= sanitize('Request ID: ' . $ai['requestId']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($ai['responseId'])): ?>
                                <div class="pill muted"><?= sanitize('Response ID: ' . $ai['responseId']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h4 style="margin:0 0 6px 0;"><?= sanitize('Errors / notes'); ?></h4>
                            <?php if (!empty($ai['errors'])): ?>
                                <ul style="padding-left:16px;margin:0;color:#f77676;">
                                    <?php foreach ($ai['errors'] as $err): ?>
                                        <li><?= sanitize($err); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="muted" style="margin:0;"><?= sanitize('No errors captured.'); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($ai['safetyRatingsSummary'])): ?>
                                <p class="muted" style="margin:6px 0 0 0;"><?= sanitize('Safety: ' . $ai['safetyRatingsSummary']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="margin-top:10px;">
                        <h4 style="margin:0 0 6px 0;"><?= sanitize('Raw AI text'); ?></h4>
                        <textarea readonly rows="6" style="width:100%; resize:vertical; background:#0d1117; color:#e6edf3; border:1px solid #30363d; border-radius:10px; padding:8px;"><?= sanitize($ai['rawText'] ?? ''); ?></textarea>
                    </div>
                </details>
            </div>
            <div class="card" style="display:grid; gap:10px;">
                <h3 style="margin:0;"><?= sanitize('Checklist'); ?></h3>
                <form method="post" action="/contractor/offline_tender_update.php" style="display:grid; gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($tender['id']); ?>">
                    <input type="hidden" name="mode" value="save_checklist">
                    <?php foreach ($checklist as $item): ?>
                        <div style="border:1px solid #30363d; border-radius:10px; padding:10px;">
                            <input type="hidden" name="checklist[<?= sanitize($item['itemId']); ?>][itemId]" value="<?= sanitize($item['itemId']); ?>">
                            <div class="field">
                                <label><?= sanitize('Title'); ?></label>
                                <input name="checklist[<?= sanitize($item['itemId']); ?>][title]" value="<?= sanitize($item['title'] ?? ''); ?>" required>
                            </div>
                            <div class="field">
                                <label><?= sanitize('Description'); ?></label>
                                <textarea name="checklist[<?= sanitize($item['itemId']); ?>][description]" rows="2" style="resize:vertical;"><?= sanitize($item['description'] ?? ''); ?></textarea>
                            </div>
                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <label class="pill" style="display:inline-flex; gap:6px; align-items:center;">
                                    <input type="checkbox" name="checklist[<?= sanitize($item['itemId']); ?>][required]" value="1" <?= !empty($item['required']) ? 'checked' : ''; ?>> <?= sanitize('Required'); ?>
                                </label>
                                <label class="pill" style="display:inline-flex; gap:6px; align-items:center;">
                                    <input type="checkbox" name="checklist_remove[]" value="<?= sanitize($item['itemId']); ?>"> <?= sanitize('Remove'); ?>
                                </label>
                                <select name="checklist[<?= sanitize($item['itemId']); ?>][status]" class="pill">
                                    <?php foreach (['pending','uploaded','done'] as $status): ?>
                                        <option value="<?= sanitize($status); ?>" <?= ($item['status'] ?? '') === $status ? 'selected' : ''; ?>><?= sanitize(ucfirst($status)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div style="border:1px dashed #30363d; border-radius:10px; padding:10px;">
                        <h4 style="margin-top:0;"><?= sanitize('Add checklist items'); ?></h4>
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <div class="field" style="margin-bottom:8px;">
                                <input name="new_checklist[<?= $i; ?>][title]" placeholder="<?= sanitize('Title'); ?>">
                                <textarea name="new_checklist[<?= $i; ?>][description]" rows="2" placeholder="<?= sanitize('Description'); ?>" style="resize:vertical; margin-top:6px;"></textarea>
                                <label class="pill" style="display:inline-flex; gap:6px; align-items:center; margin-top:6px;">
                                    <input type="checkbox" name="new_checklist[<?= $i; ?>][required]" value="1" checked> <?= sanitize('Required'); ?>
                                </label>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <button class="btn" type="submit"><?= sanitize('Save checklist'); ?></button>
                </form>
            </div>
        </div>
        <?php
    });
});
