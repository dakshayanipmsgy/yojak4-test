<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = assisted_tasks_require_staff();
    ensure_assisted_tasks_env();

    $taskId = trim($_GET['taskId'] ?? '');
    $task = $taskId !== '' ? assisted_tasks_load_task($taskId) : null;
    if (!$task) {
        render_error_page('Assisted task not found.');
        return;
    }

    if (($task['status'] ?? '') === 'requested') {
        $task['status'] = 'in_progress';
        assisted_tasks_append_history($task, assisted_tasks_actor_label($actor), 'opened');
    }
    if (empty($task['assignedTo'])) {
        $task['assignedTo'] = assisted_tasks_actor_label($actor);
        assisted_tasks_append_history($task, assisted_tasks_actor_label($actor), 'assigned');
    }
    assisted_tasks_save_task($task);

    $title = get_app_config()['appName'] . ' | Assisted Task';
    render_layout($title, function () use ($task, $actor) {
        $form = $task['extractForm'] ?? assisted_tasks_default_form();
        $eligibilityLines = implode("\n", $form['eligibilityDocs'] ?? []);
        $annexureLines = implode("\n", array_map(fn($a) => is_array($a) ? ($a['name'] ?? $a['title'] ?? '') : (string)$a, $form['annexures'] ?? []));
        $formatLines = implode("\n", array_map(function ($f) {
            if (is_array($f)) {
                $name = $f['name'] ?? $f['title'] ?? '';
                $notes = $f['notes'] ?? '';
                return trim($notes) !== '' ? $name . ' | ' . $notes : $name;
            }
            return (string)$f;
        }, $form['formats'] ?? []));
        $notesLines = implode("\n", $form['notes'] ?? []);
        $restricted = $form['restrictedAnnexures'] ?? [];
        $aiAssist = $task['aiAssist'] ?? [];
        $pdf = $task['tenderPdf'] ?? [];
        $pdfUrl = $pdf['publicPath'] ?? '';
        $pdfLabel = $pdf['originalName'] ?? 'Tender PDF';
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Assisted Task: <?= sanitize($task['taskId']); ?></h2>
                    <p class="muted" style="margin:4px 0 0;">Task-first extraction editor for offline tenders.</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <span class="pill"><?= sanitize('Status: ' . ucwords(str_replace('_',' ', $task['status'] ?? ''))); ?></span>
                    <span class="pill"><?= sanitize('Assigned: ' . ($task['assignedTo'] ?? 'Unassigned')); ?></span>
                </div>
            </div>

            <?php if (($aiAssist['status'] ?? '') === 'failed'): ?>
                <div class="card" style="border-color:#f85149;background:#201012;">
                    <strong><?= sanitize('AI auto-fill failed'); ?></strong>
                    <p class="muted" style="margin:6px 0 0;"><?= sanitize($aiAssist['error'] ?? 'AI response could not be parsed. You can continue manually.'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px;margin-top:12px;">
            <div class="card" style="display:grid;gap:8px;">
                <h3 style="margin:0;">Tender PDF</h3>
                <?php if ($pdfUrl !== ''): ?>
                    <a class="btn" href="<?= sanitize($pdfUrl); ?>" target="_blank"><?= sanitize('Open ' . $pdfLabel); ?></a>
                <?php else: ?>
                    <p class="muted" style="margin:0;">No PDF path available in the task record.</p>
                <?php endif; ?>
                <div class="muted" style="font-size:12px;">Use the PDF to cross-check extracted fields.</div>
            </div>
            <div class="card" style="display:grid;gap:8px;">
                <h3 style="margin:0;">Quick Actions</h3>
                <form method="post" action="/superadmin/assisted_task_autofill_ai.php" style="margin:0;display:grid;gap:8px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="taskId" value="<?= sanitize($task['taskId']); ?>">
                    <button class="btn" type="submit">Auto-fill with AI</button>
                </form>
                <div class="muted" style="font-size:12px;">Save draft before auto-fill to avoid losing manual edits.</div>
            </div>
        </div>

        <form method="post" action="/superadmin/assisted_task_save.php" style="margin-top:12px;display:grid;gap:12px;">
            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
            <input type="hidden" name="taskId" value="<?= sanitize($task['taskId']); ?>">

            <div class="card" style="display:grid;gap:10px;">
                <h3 style="margin:0;">Basics</h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                    <label class="field"><span>Tender Title</span><input name="tenderTitle" value="<?= sanitize($form['tenderTitle'] ?? ''); ?>"></label>
                    <label class="field"><span>Tender Number</span><input name="tenderNumber" value="<?= sanitize($form['tenderNumber'] ?? ''); ?>"></label>
                    <label class="field"><span>Issuing Authority</span><input name="issuingAuthority" value="<?= sanitize($form['issuingAuthority'] ?? ''); ?>"></label>
                    <label class="field"><span>Department Name</span><input name="departmentName" value="<?= sanitize($form['departmentName'] ?? ''); ?>"></label>
                    <label class="field"><span>Location</span><input name="location" value="<?= sanitize($form['location'] ?? ''); ?>"></label>
                </div>
            </div>

            <div class="card" style="display:grid;gap:10px;">
                <h3 style="margin:0;">Dates</h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                    <label class="field"><span>Submission Deadline</span><input name="submissionDeadline" value="<?= sanitize($form['submissionDeadline'] ?? ''); ?>"></label>
                    <label class="field"><span>Opening Date</span><input name="openingDate" value="<?= sanitize($form['openingDate'] ?? ''); ?>"></label>
                    <label class="field"><span>Pre-Bid Date</span><input name="preBidDate" value="<?= sanitize($form['preBidDate'] ?? ''); ?>"></label>
                    <label class="field"><span>Completion (months)</span><input name="completionMonths" value="<?= sanitize((string)($form['completionMonths'] ?? '')); ?>"></label>
                    <label class="field"><span>Bid Validity (days)</span><input name="bidValidityDays" value="<?= sanitize((string)($form['bidValidityDays'] ?? '')); ?>"></label>
                </div>
            </div>

            <div class="card" style="display:grid;gap:10px;">
                <h3 style="margin:0;">Fees</h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                    <label class="field"><span>Tender Fee</span><input name="tenderFeeText" value="<?= sanitize($form['fees']['tenderFeeText'] ?? ''); ?>"></label>
                    <label class="field"><span>EMD</span><input name="emdText" value="<?= sanitize($form['fees']['emdText'] ?? ''); ?>"></label>
                    <label class="field"><span>SD</span><input name="sdText" value="<?= sanitize($form['fees']['sdText'] ?? ''); ?>"></label>
                    <label class="field"><span>PG</span><input name="pgText" value="<?= sanitize($form['fees']['pgText'] ?? ''); ?>"></label>
                </div>
            </div>

            <div class="card" style="display:grid;gap:10px;">
                <h3 style="margin:0;">Eligibility Docs</h3>
                <textarea name="eligibilityDocs" rows="4" style="resize:vertical;" placeholder="One doc per line"><?= sanitize($eligibilityLines); ?></textarea>
            </div>

            <div class="card" style="display:grid;gap:10px;">
                <h3 style="margin:0;">Annexures</h3>
                <textarea name="annexures" rows="4" style="resize:vertical;" placeholder="One annexure per line"><?= sanitize($annexureLines); ?></textarea>
            </div>

            <div class="card" style="display:grid;gap:10px;">
                <h3 style="margin:0;">Formats</h3>
                <textarea name="formats" rows="4" style="resize:vertical;" placeholder="Format name | notes (one per line)"><?= sanitize($formatLines); ?></textarea>
            </div>

            <div class="card" style="display:grid;gap:10px;">
                <h3 style="margin:0;">Checklist</h3>
                <div style="overflow-x:auto;">
                    <table id="checklist-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Required</th>
                                <th>Notes</th>
                                <th>Snippet</th>
                                <th>Remove</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($form['checklist'] ?? [] as $idx => $item): ?>
                                <tr>
                                    <td>
                                        <input type="hidden" name="checklist[<?= $idx; ?>][id]" value="<?= sanitize($item['id'] ?? ''); ?>">
                                        <input name="checklist[<?= $idx; ?>][title]" value="<?= sanitize($item['title'] ?? ''); ?>" style="min-width:200px;">
                                    </td>
                                    <td>
                                        <select name="checklist[<?= $idx; ?>][category]">
                                            <?php foreach (['Eligibility','Fees','Forms','Technical','Submission','Declarations','Other'] as $cat): ?>
                                                <option value="<?= sanitize($cat); ?>" <?= ($item['category'] ?? '') === $cat ? 'selected' : ''; ?>><?= sanitize($cat); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="checkbox" name="checklist[<?= $idx; ?>][required]" value="1" <?= !empty($item['required']) ? 'checked' : ''; ?>></td>
                                    <td><input name="checklist[<?= $idx; ?>][notes]" value="<?= sanitize($item['notes'] ?? ''); ?>" style="min-width:160px;"></td>
                                    <td><input name="checklist[<?= $idx; ?>][snippet]" value="<?= sanitize($item['snippet'] ?? ''); ?>" style="min-width:160px;"></td>
                                    <td><button type="button" class="btn secondary btn-remove">Remove</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn secondary" id="add-checklist">Add checklist item</button>
            </div>

            <?php if ($restricted): ?>
                <div class="card" style="border-color:#3a2a18;background:#1a1208;">
                    <h3 style="margin:0;">Restricted Annexures (read-only)</h3>
                    <p class="muted" style="margin:6px 0 0;">Pricing documents are listed here and excluded from template generation.</p>
                    <ul style="margin:6px 0 0;padding-left:18px;display:grid;gap:4px;">
                        <?php foreach ($restricted as $rest): ?>
                            <li><?= sanitize((string)$rest); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card" style="display:grid;gap:10px;">
                <h3 style="margin:0;">Notes</h3>
                <textarea name="notes" rows="4" style="resize:vertical;" placeholder="One note per line"><?= sanitize($notesLines); ?></textarea>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <button class="btn" type="submit" name="action" value="save">Save Draft</button>
                <button class="btn secondary" type="submit" name="action" value="needs_info">Mark Needs Contractor Info</button>
                <button class="btn" type="submit" formaction="/superadmin/assisted_task_deliver.php">Deliver to Contractor</button>
            </div>
        </form>

        <script>
            (function () {
                const tableBody = document.querySelector('#checklist-table tbody');
                const addBtn = document.getElementById('add-checklist');
                let index = tableBody ? tableBody.children.length : 0;
                if (!tableBody || !addBtn) return;
                const rowTemplate = () => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>
                            <input type="hidden" name="checklist[${index}][id]" value="">
                            <input name="checklist[${index}][title]" value="" style="min-width:200px;">
                        </td>
                        <td>
                            <select name="checklist[${index}][category]">
                                <option value="Eligibility">Eligibility</option>
                                <option value="Fees">Fees</option>
                                <option value="Forms">Forms</option>
                                <option value="Technical">Technical</option>
                                <option value="Submission">Submission</option>
                                <option value="Declarations">Declarations</option>
                                <option value="Other" selected>Other</option>
                            </select>
                        </td>
                        <td><input type="checkbox" name="checklist[${index}][required]" value="1" checked></td>
                        <td><input name="checklist[${index}][notes]" value="" style="min-width:160px;"></td>
                        <td><input name="checklist[${index}][snippet]" value="" style="min-width:160px;"></td>
                        <td><button type="button" class="btn secondary btn-remove">Remove</button></td>
                    `;
                    index += 1;
                    return row;
                };
                addBtn.addEventListener('click', () => {
                    tableBody.appendChild(rowTemplate());
                });
                tableBody.addEventListener('click', (event) => {
                    const target = event.target;
                    if (target && target.classList.contains('btn-remove')) {
                        const row = target.closest('tr');
                        if (row) row.remove();
                    }
                });
            })();
        </script>
        <?php
    });
});
