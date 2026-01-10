<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

function assisted_format_formats(array $formats): string
{
    $lines = [];
    foreach ($formats as $format) {
        $name = trim((string)($format['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $notes = trim((string)($format['notes'] ?? ''));
        $lines[] = $name . '|' . $notes;
    }
    return implode("\n", $lines);
}

function assisted_format_checklist_lines(array $checklist): string
{
    $lines = [];
    foreach ($checklist as $item) {
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $category = trim((string)($item['category'] ?? 'Other'));
        $required = !empty($item['required']) ? 'yes' : 'no';
        $notes = trim((string)($item['notes'] ?? ''));
        $lines[] = implode(' | ', [$title, $category, $required, $notes]);
    }
    return implode("\n", $lines);
}

safe_page(function () {
    $actor = assisted_require_staff_access();
    $taskId = trim($_GET['taskId'] ?? '');
    $task = $taskId !== '' ? assisted_load_task($taskId) : null;
    if (!$task) {
        render_error_page('Assisted task not found.');
        return;
    }

    $isEmployee = ($actor['type'] ?? '') === 'employee';
    $assignedTo = $task['assignedTo']['userId'] ?? '';
    $canClaim = $isEmployee && $assignedTo === '';
    $isAssignedToActor = !$isEmployee || $assignedTo === '' || $assignedTo === ($actor['empId'] ?? '');
    if ($isEmployee && !$isAssignedToActor && !$canClaim) {
        render_error_page('You are not assigned to this task.');
        return;
    }

    $form = $task['form'] ?? assisted_task_form_defaults();

    $title = get_app_config()['appName'] . ' | Assisted Task ' . ($task['taskId'] ?? '');

    render_layout($title, function () use ($task, $form, $actor, $canClaim) {
        $pdfPath = $task['tender']['pdfPath'] ?? '';
        $status = $task['status'] ?? 'queued';
        $restricted = $form['lists']['restrictedAnnexures'] ?? [];
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Assisted Extraction Task</h2>
                    <p class="muted" style="margin:4px 0 0;">Task ID: <?= sanitize($task['taskId'] ?? ''); ?> • Status: <?= sanitize(ucwords(str_replace('_',' ', $status))); ?></p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <a class="btn secondary" href="/superadmin/assisted_queue.php">Back to queue</a>
                    <?php if ($canClaim): ?>
                        <form method="post" action="/superadmin/assisted_task_assign.php" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <input type="hidden" name="taskId" value="<?= sanitize($task['taskId'] ?? ''); ?>">
                            <input type="hidden" name="action" value="claim">
                            <button class="btn" type="submit">Claim Task</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px;">
                <div class="card" style="background:#0f1622;border:1px solid #30363d;">
                    <h4 style="margin:0 0 6px 0;">Contractor</h4>
                    <div><?= sanitize($task['contractor']['name'] ?? ''); ?></div>
                    <div class="muted" style="font-size:12px;"><?= sanitize($task['contractor']['yojId'] ?? ''); ?></div>
                    <div class="muted" style="font-size:12px;"><?= sanitize($task['contractor']['mobile'] ?? ''); ?></div>
                </div>
                <div class="card" style="background:#0f1622;border:1px solid #30363d;">
                    <h4 style="margin:0 0 6px 0;">Tender</h4>
                    <div><?= sanitize($task['tender']['title'] ?? ''); ?></div>
                    <div class="muted" style="font-size:12px;"><?= sanitize($task['tender']['offtdId'] ?? ''); ?></div>
                    <?php if ($pdfPath !== ''): ?>
                        <a class="pill" href="<?= sanitize($pdfPath); ?>" target="_blank" rel="noopener">Open PDF</a>
                    <?php endif; ?>
                </div>
                <div class="card" style="background:#0f1622;border:1px solid #30363d;">
                    <h4 style="margin:0 0 6px 0;">Assignment</h4>
                    <div><?= sanitize($task['assignedTo']['name'] ?? 'Unassigned'); ?></div>
                    <div class="muted" style="font-size:12px;">Updated: <?= sanitize($task['updatedAt'] ?? ''); ?></div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:12px;display:grid;gap:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <h3 style="margin:0;">Tender Extract Form</h3>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <form method="post" action="/superadmin/assisted_task_autofill_ai.php" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="taskId" value="<?= sanitize($task['taskId'] ?? ''); ?>">
                        <button class="btn secondary" type="submit">Auto-fill with AI</button>
                    </form>
                    <form method="post" action="/superadmin/assisted_task_deliver.php" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="taskId" value="<?= sanitize($task['taskId'] ?? ''); ?>">
                        <button class="btn" type="submit">Deliver to Contractor</button>
                    </form>
                </div>
            </div>

            <form method="post" action="/superadmin/assisted_task_save.php" style="display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="taskId" value="<?= sanitize($task['taskId'] ?? ''); ?>">

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                    <label class="field">
                        <span>Tender Title</span>
                        <input name="tender_title" value="<?= sanitize((string)($form['basics']['tenderTitle'] ?? '')); ?>">
                    </label>
                    <label class="field">
                        <span>Tender Number</span>
                        <input name="tender_number" value="<?= sanitize((string)($form['basics']['tenderNumber'] ?? '')); ?>">
                    </label>
                    <label class="field">
                        <span>Issuing Authority</span>
                        <input name="issuing_authority" value="<?= sanitize((string)($form['basics']['issuingAuthority'] ?? '')); ?>">
                    </label>
                    <label class="field">
                        <span>Department Name</span>
                        <input name="department_name" value="<?= sanitize((string)($form['basics']['departmentName'] ?? '')); ?>">
                    </label>
                    <label class="field">
                        <span>Location</span>
                        <input name="location" value="<?= sanitize((string)($form['basics']['location'] ?? '')); ?>">
                    </label>
                    <label class="field">
                        <span>Completion (months)</span>
                        <input type="number" min="0" name="completion_months" value="<?= sanitize((string)($form['basics']['completionMonths'] ?? '')); ?>">
                    </label>
                    <label class="field">
                        <span>Bid Validity (days)</span>
                        <input type="number" min="0" name="bid_validity_days" value="<?= sanitize((string)($form['basics']['bidValidityDays'] ?? '')); ?>">
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                    <label class="field">
                        <span>Submission Deadline</span>
                        <input name="submission_deadline" value="<?= sanitize((string)($form['dates']['submissionDeadline'] ?? '')); ?>" placeholder="2026-01-15T17:00">
                    </label>
                    <label class="field">
                        <span>Opening Date</span>
                        <input name="opening_date" value="<?= sanitize((string)($form['dates']['openingDate'] ?? '')); ?>" placeholder="2026-01-16T12:00">
                    </label>
                    <label class="field">
                        <span>Pre-bid Meeting</span>
                        <input name="prebid_date" value="<?= sanitize((string)($form['dates']['preBidDate'] ?? '')); ?>" placeholder="2026-01-10T11:00">
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                    <label class="field">
                        <span>Tender Fee</span>
                        <input name="tender_fee_text" value="<?= sanitize((string)($form['fees']['tenderFeeText'] ?? '')); ?>" placeholder="₹ 1,000 via DD">
                    </label>
                    <label class="field">
                        <span>EMD</span>
                        <input name="emd_text" value="<?= sanitize((string)($form['fees']['emdText'] ?? '')); ?>" placeholder="₹ 50,000 via BG">
                    </label>
                    <label class="field">
                        <span>Security Deposit (SD)</span>
                        <input name="sd_text" value="<?= sanitize((string)($form['fees']['sdText'] ?? '')); ?>">
                    </label>
                    <label class="field">
                        <span>Performance Guarantee (PG)</span>
                        <input name="pg_text" value="<?= sanitize((string)($form['fees']['pgText'] ?? '')); ?>">
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px;">
                    <label class="field">
                        <span>Eligibility Documents (one per line)</span>
                        <textarea name="eligibility_docs" rows="4" style="resize:vertical;"><?= sanitize(implode("\n", $form['lists']['eligibilityDocs'] ?? [])); ?></textarea>
                    </label>
                    <label class="field">
                        <span>Annexures (one per line)</span>
                        <textarea name="annexures" rows="4" style="resize:vertical;"><?= sanitize(implode("\n", $form['lists']['annexures'] ?? [])); ?></textarea>
                    </label>
                    <label class="field">
                        <span>Formats (name | notes per line)</span>
                        <textarea name="formats" rows="4" style="resize:vertical;"><?= sanitize(assisted_format_formats($form['lists']['formats'] ?? [])); ?></textarea>
                    </label>
                </div>

                <?php if (!empty($restricted)): ?>
                    <div style="border:1px solid #3a2a18;border-radius:10px;padding:10px;background:#1a1208;">
                        <strong>Restricted Annexures (Financial)</strong>
                        <ul style="margin:6px 0 0 16px;">
                            <?php foreach ($restricted as $item): ?>
                                <li style="color:#fcd34d;"><?= sanitize($item); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="muted" style="margin:6px 0 0;">Financial annexures are excluded from template generation.</p>
                    </div>
                <?php endif; ?>

                <label class="field">
                    <span>Checklist (Title | Category | Required yes/no | Notes)</span>
                    <textarea name="checklist_lines" rows="6" style="resize:vertical;"><?= sanitize(assisted_format_checklist_lines($form['checklist'] ?? [])); ?></textarea>
                </label>

                <label class="field">
                    <span>Notes (one per line)</span>
                    <textarea name="notes" rows="3" style="resize:vertical;"><?= sanitize(implode("\n", $form['notes'] ?? [])); ?></textarea>
                </label>

                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="btn" type="submit">Save Draft</button>
                    <span class="muted">Deliver once at least a deadline or 5 checklist items are captured.</span>
                </div>
            </form>
        </div>
        <?php
    });
});
