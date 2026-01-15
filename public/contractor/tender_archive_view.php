<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_tender_archive_env($yojId);

    $archId = trim($_GET['id'] ?? '');
    $archive = $archId !== '' ? load_tender_archive($yojId, $archId) : null;
    if (!$archive || ($archive['yojId'] ?? '') !== $yojId) {
        render_error_page('Archive not found.');
        return;
    }

    $aiSummary = array_merge(tender_archive_ai_defaults(), $archive['aiSummary'] ?? []);
    $title = get_app_config()['appName'] . ' | ' . ($archive['title'] ?? 'Archive');
    $currentYear = (int)now_kolkata()->format('Y');
    $checklist = $aiSummary['suggestedChecklist'] ?? [];
    ?>
    <?php render_layout($title, function () use ($archive, $aiSummary, $currentYear, $checklist) { ?>
        <div class="card" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
            <div>
                <h2 style="margin:0;"><?= sanitize($archive['title'] ?? 'Archived Tender'); ?></h2>
                <p class="muted" style="margin:4px 0 0;">
                    <?= sanitize($archive['id'] ?? ''); ?>
                    <?php if (!empty($archive['year'])): ?> • <?= sanitize('Year ' . $archive['year']); ?><?php endif; ?>
                    <?php if (!empty($archive['departmentName'])): ?> • <?= sanitize($archive['departmentName']); ?><?php endif; ?>
                </p>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <form method="post" action="/contractor/tender_archive_run_ai.php" onsubmit="return confirm('Run AI summary now?');">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($archive['id']); ?>">
                    <button class="btn secondary" type="submit"><?= sanitize('Run AI summary'); ?></button>
                </form>
                <a class="btn" href="/contractor/tender_archive.php"><?= sanitize('Back to list'); ?></a>
            </div>
        </div>

        <div style="display:grid; gap:12px; margin-top:12px;">
            <div class="card">
                <h3 style="margin-top:0;"><?= sanitize('Archive details'); ?></h3>
                <form method="post" action="/contractor/tender_archive_update.php" enctype="multipart/form-data" style="display:grid; gap:12px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($archive['id']); ?>">
                    <div class="field">
                        <label for="title"><?= sanitize('Title'); ?></label>
                        <input id="title" name="title" value="<?= sanitize($archive['title'] ?? ''); ?>" required>
                    </div>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:10px;">
                        <div class="field">
                            <label for="year"><?= sanitize('Year'); ?></label>
                            <input id="year" name="year" type="number" min="2000" max="<?= $currentYear; ?>" value="<?= sanitize((string)($archive['year'] ?? '')); ?>">
                        </div>
                        <div class="field">
                            <label for="departmentName"><?= sanitize('Department/authority'); ?></label>
                            <input id="departmentName" name="departmentName" value="<?= sanitize($archive['departmentName'] ?? ''); ?>">
                        </div>
                        <div class="field">
                            <label for="outcome"><?= sanitize('Outcome'); ?></label>
                            <select id="outcome" name="outcome">
                                <option value=""><?= sanitize('Select outcome'); ?></option>
                                <option value="participated" <?= ($archive['outcome'] ?? '') === 'participated' ? 'selected' : ''; ?>><?= sanitize('Participated'); ?></option>
                                <option value="won" <?= ($archive['outcome'] ?? '') === 'won' ? 'selected' : ''; ?>><?= sanitize('Won'); ?></option>
                                <option value="lost" <?= ($archive['outcome'] ?? '') === 'lost' ? 'selected' : ''; ?>><?= sanitize('Lost'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <label><?= sanitize('Source files'); ?></label>
                        <div style="display:flex; flex-wrap:wrap; gap:8px;">
                            <?php foreach (($archive['sourceFiles'] ?? []) as $file): ?>
                                <a class="pill" href="<?= sanitize($file['path'] ?? '#'); ?>" target="_blank" rel="noopener"><?= sanitize($file['name'] ?? 'PDF'); ?></a>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top:8px;">
                            <label class="muted"><?= sanitize('Add more PDFs (optional)'); ?></label>
                            <input name="documents[]" type="file" accept=".pdf" multiple>
                            <small class="muted"><?= sanitize('PDF only, up to 25MB per upload batch.'); ?></small>
                        </div>
                    </div>
                    <div class="field">
                        <label for="summaryText"><?= sanitize('Summary (AI or manual)'); ?></label>
                        <textarea id="summaryText" name="summaryText" rows="4" style="resize:vertical;"><?= sanitize($aiSummary['summaryText'] ?? ''); ?></textarea>
                    </div>
                    <div class="field">
                        <label for="keyLearnings"><?= sanitize('Key learnings (one per line)'); ?></label>
                        <textarea id="keyLearnings" name="keyLearnings" rows="4" style="resize:vertical;"><?= sanitize(implode("\n", $aiSummary['keyLearnings'] ?? [])); ?></textarea>
                    </div>
                    <div class="card" style="border-color:var(--border);">
                        <h4 style="margin-top:0;"><?= sanitize('Suggested checklist items'); ?></h4>
                        <?php if (!$checklist): ?>
                            <p class="muted" style="margin:0 0 8px;"><?= sanitize('Add checklist items manually or run AI to generate.'); ?></p>
                        <?php endif; ?>
                        <?php foreach ($checklist as $idx => $item): ?>
                            <div style="display:grid; gap:6px; border:1px solid var(--border); padding:10px; border-radius:10px; margin-bottom:8px;">
                                <input type="hidden" name="suggestedChecklist[<?= $idx; ?>][id]" value="<?= $idx; ?>">
                                <div class="field" style="margin-bottom:0;">
                                    <label><?= sanitize('Title'); ?></label>
                                    <input name="suggestedChecklist[<?= $idx; ?>][title]" value="<?= sanitize($item['title'] ?? ''); ?>" required>
                                </div>
                                <div class="field" style="margin-bottom:0;">
                                    <label><?= sanitize('Description'); ?></label>
                                    <textarea name="suggestedChecklist[<?= $idx; ?>][description]" rows="2" style="resize:vertical;"><?= sanitize($item['description'] ?? ''); ?></textarea>
                                </div>
                                <label style="display:flex; align-items:center; gap:6px;">
                                    <input type="checkbox" name="suggestedChecklist[<?= $idx; ?>][required]" value="1" <?= !empty($item['required']) ? 'checked' : ''; ?>> <?= sanitize('Required'); ?>
                                </label>
                                <label style="display:flex; align-items:center; gap:6px;">
                                    <input type="checkbox" name="removeChecklist[]" value="<?= $idx; ?>"> <?= sanitize('Remove item'); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        <h5 style="margin:6px 0 4px;"><?= sanitize('Add new checklist items'); ?></h5>
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <div style="display:grid; gap:6px; border:1px dashed var(--border); padding:10px; border-radius:10px; margin-bottom:8px;">
                                <input name="newChecklist[<?= $i; ?>][title]" placeholder="<?= sanitize('Title'); ?>">
                                <textarea name="newChecklist[<?= $i; ?>][description]" rows="2" placeholder="<?= sanitize('Description'); ?>" style="resize:vertical;"></textarea>
                                <label style="display:flex; align-items:center; gap:6px;">
                                    <input type="checkbox" name="newChecklist[<?= $i; ?>][required]" value="1"> <?= sanitize('Required'); ?>
                                </label>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <div class="buttons" style="margin-top:6px;">
                        <button class="btn" type="submit"><?= sanitize('Save changes'); ?></button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
                    <h3 style="margin:0;"><?= sanitize('AI summary'); ?></h3>
                    <span class="pill"><?= sanitize($aiSummary['parsedOk'] ? 'Parsed successfully' : 'Awaiting clean AI result'); ?></span>
                </div>
                <p class="muted" style="margin:6px 0 10px;"><?= sanitize('AI failures never block manual edits. You can refine the summary and checklist directly.'); ?></p>
                <p style="margin:0 0 8px; white-space:pre-wrap;"><?= sanitize($aiSummary['summaryText'] ?? ''); ?></p>
                <?php if (!empty($aiSummary['keyLearnings'])): ?>
                    <div style="margin-top:10px;">
                        <h4 style="margin:0 0 6px;"><?= sanitize('Key learnings'); ?></h4>
                        <ul style="margin:0 0 8px 18px; padding:0; color:var(--text);">
                            <?php foreach ($aiSummary['keyLearnings'] as $learning): ?>
                                <li><?= sanitize($learning); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (!empty($aiSummary['rawText'])): ?>
                    <details style="margin-top:8px;">
                        <summary class="muted"><?= sanitize('View raw AI response'); ?></summary>
                        <pre style="white-space:pre-wrap; background:var(--surface); padding:10px; border-radius:10px; border:1px solid var(--border);"><?= sanitize($aiSummary['rawText']); ?></pre>
                    </details>
                <?php endif; ?>
                <p class="muted" style="margin:8px 0 0; font-size:12px;"><?= sanitize('Last run: ' . ($aiSummary['lastRunAt'] ?? 'Never')); ?></p>
            </div>

            <div class="card">
                <h3 style="margin-top:0;"><?= sanitize('Save checklist as template'); ?></h3>
                <p class="muted" style="margin:4px 0 10px;"><?= sanitize('Save the suggested checklist into your templates library for reuse in future tenders.'); ?></p>
                <form method="post" action="/contractor/tender_archive_export_template.php" style="display:grid; gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($archive['id']); ?>">
                    <div class="field" style="margin-bottom:0;">
                        <label for="templateTitle"><?= sanitize('Template title'); ?></label>
                        <input id="templateTitle" name="templateTitle" value="<?= sanitize(($archive['title'] ?? 'Template') . ' Checklist'); ?>" required>
                    </div>
                    <button class="btn" type="submit"><?= sanitize('Save as template'); ?></button>
                </form>
            </div>
        </div>
    <?php }); ?>
    <?php
});
