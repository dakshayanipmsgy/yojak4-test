<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_assisted_tasks_env();
    ensure_packs_env($yojId);

    $taskId = trim($_GET['taskId'] ?? '');
    $task = $taskId !== '' ? assisted_tasks_load_task($taskId) : null;
    if (!$task || ($task['yojId'] ?? '') !== $yojId) {
        render_error_page('Assisted extraction task not found.');
        return;
    }

    $title = get_app_config()['appName'] . ' | Assisted Extraction';
    render_layout($title, function () use ($task) {
        $form = $task['extractForm'] ?? assisted_tasks_default_form();
        $delivered = ($task['status'] ?? '') === 'delivered';
        $packId = $task['packId'] ?? '';
        ?>
        <div class="card" style="display:grid;gap:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Assisted Extraction (Delivered)</h2>
                    <p class="muted" style="margin:4px 0 0;">Read-only view of the tender extract prepared by YOJAK.</p>
                </div>
                <span class="pill" style="<?= $delivered ? 'border-color:#2ea043;color:#8ce99a;' : 'border-color:#f59f00;color:#fcd34d;'; ?>">
                    <?= sanitize('Status: ' . ucwords(str_replace('_', ' ', $task['status'] ?? 'requested'))); ?>
                </span>
            </div>
            <?php if (!$delivered): ?>
                <div class="card" style="border-color:#f59f00;background:#1f1a10;">
                    <strong><?= sanitize('In progress'); ?></strong>
                    <p class="muted" style="margin:6px 0 0;">Your request is being prepared. You can view this page again once delivered.</p>
                </div>
            <?php endif; ?>
            <?php if ($delivered && $packId !== ''): ?>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn" href="/contractor/pack_print.php?packId=<?= urlencode($packId); ?>&doc=checklist" target="_blank">Print Checklist</a>
                    <a class="btn" href="/contractor/pack_print.php?packId=<?= urlencode($packId); ?>&doc=annexures" target="_blank">Print Annexures</a>
                    <a class="btn" href="/contractor/pack_print.php?packId=<?= urlencode($packId); ?>&doc=full" target="_blank">Print Full Pack</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="card" style="margin-top:12px;display:grid;gap:10px;">
            <h3 style="margin:0;">Tender Basics</h3>
            <div class="grid-2" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                <div style="border:1px solid #30363d;border-radius:10px;padding:10px;background:#0f1622;"><div class="muted">Tender Title</div><strong><?= sanitize($form['tenderTitle'] ?? ''); ?></strong></div>
                <div style="border:1px solid #30363d;border-radius:10px;padding:10px;background:#0f1622;"><div class="muted">Tender Number</div><strong><?= sanitize($form['tenderNumber'] ?? ''); ?></strong></div>
                <div style="border:1px solid #30363d;border-radius:10px;padding:10px;background:#0f1622;"><div class="muted">Issuing Authority</div><strong><?= sanitize($form['issuingAuthority'] ?? ''); ?></strong></div>
                <div style="border:1px solid #30363d;border-radius:10px;padding:10px;background:#0f1622;"><div class="muted">Department</div><strong><?= sanitize($form['departmentName'] ?? ''); ?></strong></div>
                <div style="border:1px solid #30363d;border-radius:10px;padding:10px;background:#0f1622;"><div class="muted">Location</div><strong><?= sanitize($form['location'] ?? ''); ?></strong></div>
            </div>
        </div>

        <div class="card" style="margin-top:12px;display:grid;gap:10px;">
            <h3 style="margin:0;">Dates & Fees</h3>
            <div class="grid-2" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                <div style="border:1px solid #30363d;border-radius:10px;padding:10px;background:#0f1622;"><div class="muted">Submission Deadline</div><strong><?= sanitize($form['submissionDeadline'] ?? ''); ?></strong></div>
                <div style="border:1px solid #30363d;border-radius:10px;padding:10px;background:#0f1622;"><div class="muted">Opening Date</div><strong><?= sanitize($form['openingDate'] ?? ''); ?></strong></div>
                <div style="border:1px solid #30363d;border-radius:10px;padding:10px;background:#0f1622;"><div class="muted">Pre-Bid Date</div><strong><?= sanitize($form['preBidDate'] ?? ''); ?></strong></div>
                <div style="border:1px solid #30363d;border-radius:10px;padding:10px;background:#0f1622;"><div class="muted">Completion (months)</div><strong><?= sanitize((string)($form['completionMonths'] ?? '')); ?></strong></div>
                <div style="border:1px solid #30363d;border-radius:10px;padding:10px;background:#0f1622;"><div class="muted">Bid Validity (days)</div><strong><?= sanitize((string)($form['bidValidityDays'] ?? '')); ?></strong></div>
                <div style="border:1px solid #30363d;border-radius:10px;padding:10px;background:#0f1622;"><div class="muted">Tender Fee</div><strong><?= sanitize($form['fees']['tenderFeeText'] ?? ''); ?></strong></div>
                <div style="border:1px solid #30363d;border-radius:10px;padding:10px;background:#0f1622;"><div class="muted">EMD</div><strong><?= sanitize($form['fees']['emdText'] ?? ''); ?></strong></div>
                <div style="border:1px solid #30363d;border-radius:10px;padding:10px;background:#0f1622;"><div class="muted">SD</div><strong><?= sanitize($form['fees']['sdText'] ?? ''); ?></strong></div>
                <div style="border:1px solid #30363d;border-radius:10px;padding:10px;background:#0f1622;"><div class="muted">PG</div><strong><?= sanitize($form['fees']['pgText'] ?? ''); ?></strong></div>
            </div>
        </div>

        <div class="card" style="margin-top:12px;display:grid;gap:10px;">
            <h3 style="margin:0;">Eligibility Documents</h3>
            <?php if (!empty($form['eligibilityDocs'])): ?>
                <ul style="margin:0;padding-left:18px;display:grid;gap:4px;">
                    <?php foreach ($form['eligibilityDocs'] as $doc): ?>
                        <li><?= sanitize((string)$doc); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted" style="margin:0;">No eligibility documents listed.</p>
            <?php endif; ?>
        </div>

        <div class="card" style="margin-top:12px;display:grid;gap:10px;">
            <h3 style="margin:0;">Annexures & Formats</h3>
            <?php if (!empty($form['annexures'])): ?>
                <h4 style="margin:0;">Annexures</h4>
                <ul style="margin:0;padding-left:18px;display:grid;gap:4px;">
                    <?php foreach ($form['annexures'] as $annex): ?>
                        <li><?= sanitize(is_array($annex) ? ($annex['name'] ?? $annex['title'] ?? '') : (string)$annex); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!empty($form['formats'])): ?>
                <h4 style="margin:0;">Formats</h4>
                <ul style="margin:0;padding-left:18px;display:grid;gap:4px;">
                    <?php foreach ($form['formats'] as $fmt): ?>
                        <li>
                            <?= sanitize(is_array($fmt) ? ($fmt['name'] ?? '') : (string)$fmt); ?>
                            <?php if (!empty($fmt['notes'])): ?>
                                <div class="muted" style="font-size:12px;"><?= sanitize((string)$fmt['notes']); ?></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (empty($form['annexures']) && empty($form['formats'])): ?>
                <p class="muted" style="margin:0;">No annexures or formats listed.</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($form['restrictedAnnexures'])): ?>
            <div class="card" style="margin-top:12px;border-color:#3a2a18;background:#1a1208;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                    <h3 style="margin:0;">Restricted Annexures</h3>
                    <span class="pill" style="border-color:#f59f00;color:#fcd34d;">Restricted (pricing document)</span>
                </div>
                <p class="muted" style="margin:6px 0 0;">YOJAK does not generate pricing/BOQ/SOR templates.</p>
                <ul style="margin:6px 0 0;padding-left:18px;display:grid;gap:4px;">
                    <?php foreach ($form['restrictedAnnexures'] as $item): ?>
                        <li><?= sanitize((string)$item); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-top:12px;display:grid;gap:10px;">
            <h3 style="margin:0;">Checklist</h3>
            <?php if (!empty($form['checklist'])): ?>
                <div style="display:grid;gap:8px;">
                    <?php foreach ($form['checklist'] as $item): ?>
                        <div style="border:1px solid #30363d;border-radius:10px;padding:10px;background:#0f1622;">
                            <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                                <strong><?= sanitize($item['title'] ?? ''); ?></strong>
                                <span class="pill" style="<?= !empty($item['required']) ? 'border-color:#2ea043;color:#8ce99a;' : ''; ?>">
                                    <?= !empty($item['required']) ? sanitize('Required') : sanitize('Optional'); ?>
                                </span>
                            </div>
                            <div class="muted" style="margin-top:4px;">
                                <?= sanitize($item['category'] ?? 'Other'); ?>
                                <?php if (!empty($item['notes'])): ?>
                                    • <?= sanitize($item['notes']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="muted" style="margin:0;">No checklist items available.</p>
            <?php endif; ?>
        </div>
        <?php
    });
});
