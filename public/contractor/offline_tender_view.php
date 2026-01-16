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
    ensure_assisted_v2_env();

    $offtdId = trim($_GET['id'] ?? '');
    $tender = $offtdId !== '' ? load_offline_tender($yojId, $offtdId) : null;
    $existingPack = $offtdId !== '' ? find_pack_by_source($yojId, 'OFFTD', $offtdId) : null;
    $assistedRequest = $offtdId !== '' ? assisted_v2_latest_request_for_tender($yojId, $offtdId) : null;

    if (!$tender || ($tender['yojId'] ?? '') !== $yojId) {
        render_error_page('Tender not found.');
        return;
    }

    $title = get_app_config()['appName'] . ' | ' . ($tender['title'] ?? 'Offline Tender');

    render_layout($title, function () use ($tender, $existingPack, $assistedRequest) {
        $extracted = $tender['extracted'] ?? offline_tender_defaults();
        $checklist = $tender['checklist'] ?? [];
        $packContext = $existingPack ? detect_pack_context($existingPack['packId']) : 'tender';
        $annexureTemplates = $existingPack ? load_pack_annexures($tender['yojId'], $existingPack['packId'], $packContext) : [];
        $printSettings = load_contractor_print_settings($tender['yojId']);
        $hasPdf = !empty($tender['sourceFiles']);
        $source = $tender['source'] ?? [];
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
            </div>
        </div>

        <?php
        $assistedStatus = $assistedRequest['status'] ?? 'none';
        $assistedDelivered = $assistedStatus === 'delivered';
        $assistedRejected = $assistedStatus === 'rejected';
        $canRequestAssisted = !$assistedRequest || $assistedRejected || $assistedDelivered;
        $packIdFromRequest = $assistedRequest['result']['packId'] ?? null;
        $packId = $packIdFromRequest ?: ($existingPack['packId'] ?? null);
        ?>
        <div class="card" style="margin-top:12px;display:grid;gap:10px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0;"><?= sanitize('Assisted Pack'); ?></h3>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Request a staff-generated pack from your tender PDF. Contractors never run AI.'); ?></p>
                </div>
                <span class="pill" style="<?= $assistedDelivered ? 'border-color:#2ea043;color:#8ce99a;' : 'border-color:#f59f00;color:#fcd34d;'; ?>">
                    <?= sanitize('Status: ' . ($assistedStatus === 'none' ? 'Not requested' : ucwords(str_replace('_',' ', $assistedStatus)))); ?>
                </span>
            </div>
            <?php if ($assistedRequest): ?>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <span class="pill"><?= sanitize('Created: ' . ($assistedRequest['createdAt'] ?? '')); ?></span>
                    <?php if (!empty($assistedRequest['staff']['assignedTo'])): ?>
                        <span class="pill"><?= sanitize('Assigned: ' . ($assistedRequest['staff']['assignedTo'] ?? '')); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($assistedRequest['staff']['processedAt'])): ?>
                        <span class="pill"><?= sanitize('Updated: ' . ($assistedRequest['staff']['processedAt'] ?? '')); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($assistedRejected): ?>
                    <div class="flash" style="background:#201012;border:1px solid #f85149;">
                        <?= sanitize('Rejected: ' . ($assistedRequest['reject']['reason'] ?? 'No reason provided.')); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <?php if ($canRequestAssisted): ?>
                    <form method="post" action="/contractor/assisted_v2/request_create.php" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="offtdId" value="<?= sanitize($tender['id']); ?>">
                        <button class="btn" type="submit" <?= $hasPdf ? '' : 'disabled'; ?>><?= sanitize('Request Assisted Pack'); ?></button>
                    </form>
                <?php endif; ?>
                <?php if (!$hasPdf): ?>
                    <span class="muted"><?= sanitize('Upload at least one tender PDF to enable assisted pack requests.'); ?></span>
                <?php endif; ?>
                <?php if ($packId): ?>
                    <a class="btn secondary" href="/contractor/pack_view.php?packId=<?= sanitize($packId); ?>"><?= sanitize('View Pack'); ?></a>
                    <a class="btn" href="/contractor/pack_print_full_v3.php?packId=<?= sanitize($packId); ?>&density=normal&autoprint=1" target="_blank" rel="noopener"><?= sanitize('Print Full Pack (opens dialog)'); ?></a>
                <?php endif; ?>
                <a class="btn secondary" href="/contractor/assisted_v2/requests.php"><?= sanitize('View all requests'); ?></a>
            </div>
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
            </div>
            <div class="card" style="display:grid; gap:10px;">
                <h3 style="margin:0;"><?= sanitize('Checklist'); ?></h3>
                <form method="post" action="/contractor/offline_tender_update.php" style="display:grid; gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($tender['id']); ?>">
                    <input type="hidden" name="mode" value="save_checklist">
                    <?php foreach ($checklist as $item): ?>
                        <div style="border:1px solid var(--border); border-radius:10px; padding:10px;">
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
                    <div style="border:1px dashed var(--border); border-radius:10px; padding:10px;">
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
