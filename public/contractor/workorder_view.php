<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

function wo_format_datetime(?string $value): string
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
    ensure_workorder_env($yojId);
    ensure_packs_env($yojId, 'workorder');

    $woId = trim($_GET['id'] ?? '');
    $workorder = $woId !== '' ? load_workorder($yojId, $woId) : null;
    if (!$workorder || ($workorder['yojId'] ?? '') !== $yojId) {
        render_error_page('Workorder not found.');
        return;
    }

    $linkedPackId = $workorder['linkedPackId'] ?? null;
    $existingPack = $linkedPackId ? load_pack($yojId, $linkedPackId, 'workorder') : find_pack_by_source($yojId, 'WORKORDER', $woId, 'workorder');

    $title = get_app_config()['appName'] . ' | ' . ($workorder['title'] ?? 'Workorder');

    render_layout($title, function () use ($workorder, $existingPack) {
        $ai = $workorder['ai'] ?? ['parsedOk' => false, 'errors' => [], 'rawText' => '', 'lastRunAt' => null];
        $obligations = $workorder['obligationsChecklist'] ?? [];
        $requiredDocs = $workorder['requiredDocs'] ?? [];
        $timeline = $workorder['timeline'] ?? [];
        ?>
        <div class="card" style="display:grid; gap:10px;">
            <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:center;">
                <div>
                    <h2 style="margin:0;"><?= sanitize($workorder['title'] ?? 'Workorder'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;">
                        <?= sanitize($workorder['woId']); ?>
                        <?php if (!empty($workorder['deptName'])): ?> • <?= sanitize($workorder['deptName']); ?><?php endif; ?>
                        <?php if (!empty($workorder['projectLocation'])): ?> • <?= sanitize($workorder['projectLocation']); ?><?php endif; ?>
                    </p>
                </div>
                <div class="buttons" style="gap:8px;">
                    <a class="btn secondary" href="/contractor/workorders.php"><?= sanitize('Back to list'); ?></a>
                </div>
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                <form method="post" action="/contractor/workorder_run_ai.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($workorder['woId']); ?>">
                    <button class="btn" type="submit"><?= sanitize('Run AI'); ?></button>
                </form>
                <?php if ($existingPack): ?>
                    <a class="btn secondary" href="/contractor/pack_view.php?packId=<?= sanitize($existingPack['packId']); ?>"><?= sanitize('Open Workorder Pack'); ?></a>
                <?php else: ?>
                    <form method="post" action="/contractor/workorder_create_pack.php">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="id" value="<?= sanitize($workorder['woId']); ?>">
                        <button class="btn secondary" type="submit"><?= sanitize('Create Workorder Pack'); ?></button>
                    </form>
                <?php endif; ?>
                <form method="post" action="/contractor/workorder_add_items_to_pack.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($workorder['woId']); ?>">
                    <button class="btn secondary" type="submit"><?= sanitize('Add suggested items to pack'); ?></button>
                </form>
                <form method="post" action="/contractor/workorder_add_reminders.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($workorder['woId']); ?>">
                    <button class="btn secondary" type="submit"><?= sanitize('Create reminders'); ?></button>
                </form>
                <form method="post" action="/contractor/workorder_upload_pdf.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($workorder['woId']); ?>">
                    <label class="btn secondary" style="display:inline-flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="file" name="workorder_pdf" accept=".pdf" required style="position:absolute; width:1px; height:1px; opacity:0;" onchange="this.form.submit();">
                        <?= sanitize('Upload PDF'); ?>
                    </label>
                </form>
            </div>
            <?php if (!empty($ai['errors'])): ?>
                <div class="flashes">
                    <div class="flash error">
                        <?= sanitize('AI parsing failed. You can edit manually or paste/review the AI text below.'); ?>
                        <ul style="margin:6px 0 0 16px;">
                            <?php foreach ($ai['errors'] as $err): ?>
                                <li><?= sanitize($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:12px; margin-top:12px;">
            <div class="card" style="display:grid; gap:12px;">
                <h3 style="margin:0;"><?= sanitize('Workorder details'); ?></h3>
                <form method="post" action="/contractor/workorder_update.php" style="display:grid; gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($workorder['woId']); ?>">
                    <input type="hidden" name="mode" value="save_details">
                    <div class="field">
                        <label><?= sanitize('Title'); ?></label>
                        <input name="title" value="<?= sanitize($workorder['title'] ?? ''); ?>" required>
                    </div>
                    <div class="field">
                        <label><?= sanitize('Department/Authority'); ?></label>
                        <input name="deptName" value="<?= sanitize($workorder['deptName'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label><?= sanitize('Project location'); ?></label>
                        <input name="projectLocation" value="<?= sanitize($workorder['projectLocation'] ?? ''); ?>">
                    </div>
                    <button class="btn" type="submit"><?= sanitize('Save details'); ?></button>
                </form>
                <div>
                    <h4 style="margin:0 0 6px 0;"><?= sanitize('Source PDFs'); ?></h4>
                    <ul style="margin:0 0 0 16px; padding:0; color:var(--muted);">
                        <?php foreach (($workorder['sourceFiles'] ?? []) as $file): ?>
                            <li style="margin-bottom:4px;">
                                <a href="<?= sanitize($file['path'] ?? '#'); ?>" target="_blank" rel="noopener"><?= sanitize($file['name'] ?? 'file'); ?></a>
                                <span class="muted">• <?= sanitize(format_bytes((int)($file['sizeBytes'] ?? 0))); ?></span>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($workorder['sourceFiles'])): ?>
                            <li class="muted"><?= sanitize('No source files yet.'); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <details style="border:1px solid #30363d; border-radius:10px; padding:10px; background:#0f1520;">
                    <summary style="cursor:pointer;"><?= sanitize('Paste/Review AI text'); ?></summary>
                    <p class="muted" style="margin-top:8px;"><?= sanitize('If parsing failed, copy the AI output here to manually adjust fields.'); ?></p>
                    <textarea readonly rows="6" style="width:100%; resize:vertical; background:#0d1117; color:#e6edf3; border:1px solid #30363d; border-radius:10px; padding:8px;"><?= sanitize($ai['rawText'] ?? ''); ?></textarea>
                </details>
            </div>
            <div class="card" style="display:grid; gap:10px;">
                <h3 style="margin:0;"><?= sanitize('Obligations checklist'); ?></h3>
                <form method="post" action="/contractor/workorder_update.php" style="display:grid; gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($workorder['woId']); ?>">
                    <input type="hidden" name="mode" value="save_obligations">
                    <?php foreach ($obligations as $item): ?>
                        <div style="border:1px solid #30363d; border-radius:10px; padding:10px; display:grid; gap:8px;">
                            <input type="hidden" name="obligations[<?= sanitize($item['itemId']); ?>][itemId]" value="<?= sanitize($item['itemId']); ?>">
                            <div class="field">
                                <label><?= sanitize('Title'); ?></label>
                                <input name="obligations[<?= sanitize($item['itemId']); ?>][title]" value="<?= sanitize($item['title'] ?? ''); ?>" required>
                            </div>
                            <div class="field">
                                <label><?= sanitize('Description'); ?></label>
                                <textarea name="obligations[<?= sanitize($item['itemId']); ?>][description]" rows="2" style="resize:vertical;"><?= sanitize($item['description'] ?? ''); ?></textarea>
                            </div>
                            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                <input type="datetime-local" name="obligations[<?= sanitize($item['itemId']); ?>][dueAt]" value="<?= sanitize(wo_format_datetime($item['dueAt'] ?? null)); ?>" class="pill">
                                <select name="obligations[<?= sanitize($item['itemId']); ?>][status]" class="pill">
                                    <?php foreach (['pending','in_progress','done'] as $status): ?>
                                        <option value="<?= sanitize($status); ?>" <?= ($item['status'] ?? '') === $status ? 'selected' : ''; ?>><?= sanitize(ucfirst(str_replace('_',' ',$status))); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="pill" style="display:inline-flex; gap:6px; align-items:center;">
                                    <input type="checkbox" name="obligations_remove[]" value="<?= sanitize($item['itemId']); ?>"> <?= sanitize('Remove'); ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div style="border:1px dashed #30363d; border-radius:10px; padding:10px;">
                        <h4 style="margin-top:0;"><?= sanitize('Add obligations'); ?></h4>
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <div class="field" style="margin-bottom:8px;">
                                <input name="new_obligations[<?= $i; ?>][title]" placeholder="<?= sanitize('Title'); ?>">
                                <textarea name="new_obligations[<?= $i; ?>][description]" rows="2" placeholder="<?= sanitize('Description'); ?>" style="resize:vertical; margin-top:6px;"></textarea>
                                <input type="datetime-local" name="new_obligations[<?= $i; ?>][dueAt]" class="pill" style="margin-top:6px;">
                            </div>
                        <?php endfor; ?>
                    </div>
                    <button class="btn" type="submit"><?= sanitize('Save obligations'); ?></button>
                </form>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:12px; margin-top:12px;">
            <div class="card" style="display:grid; gap:10px;">
                <h3 style="margin:0;"><?= sanitize('Required documents'); ?></h3>
                <form method="post" action="/contractor/workorder_update.php" style="display:grid; gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($workorder['woId']); ?>">
                    <input type="hidden" name="mode" value="save_docs">
                    <?php foreach ($requiredDocs as $idx => $doc): ?>
                        <div style="border:1px solid #30363d; border-radius:10px; padding:10px; display:grid; gap:6px;">
                            <div class="field">
                                <label><?= sanitize('Name'); ?></label>
                                <input name="requiredDocs[<?= $idx; ?>][name]" value="<?= sanitize($doc['name'] ?? ''); ?>" required>
                            </div>
                            <div class="field">
                                <label><?= sanitize('Notes'); ?></label>
                                <textarea name="requiredDocs[<?= $idx; ?>][notes]" rows="2" style="resize:vertical;"><?= sanitize($doc['notes'] ?? ''); ?></textarea>
                            </div>
                            <label class="pill" style="display:inline-flex; gap:6px; align-items:center;">
                                <input type="checkbox" name="requiredDocs_remove[]" value="<?= sanitize((string)$idx); ?>"> <?= sanitize('Remove'); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    <div style="border:1px dashed #30363d; border-radius:10px; padding:10px;">
                        <h4 style="margin-top:0;"><?= sanitize('Add documents'); ?></h4>
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <div class="field" style="margin-bottom:8px;">
                                <input name="new_requiredDocs[<?= $i; ?>][name]" placeholder="<?= sanitize('Document name'); ?>">
                                <textarea name="new_requiredDocs[<?= $i; ?>][notes]" rows="2" placeholder="<?= sanitize('Notes'); ?>" style="resize:vertical; margin-top:6px;"></textarea>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <button class="btn" type="submit"><?= sanitize('Save documents'); ?></button>
                </form>
            </div>
            <div class="card" style="display:grid; gap:10px;">
                <h3 style="margin:0;"><?= sanitize('Timeline'); ?></h3>
                <form method="post" action="/contractor/workorder_update.php" style="display:grid; gap:10px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?= sanitize($workorder['woId']); ?>">
                    <input type="hidden" name="mode" value="save_timeline">
                    <?php foreach ($timeline as $idx => $entry): ?>
                        <div style="border:1px solid #30363d; border-radius:10px; padding:10px; display:grid; gap:6px;">
                            <div class="field">
                                <label><?= sanitize('Milestone'); ?></label>
                                <input name="timeline[<?= $idx; ?>][milestone]" value="<?= sanitize($entry['milestone'] ?? ''); ?>" required>
                            </div>
                            <div class="field">
                                <label><?= sanitize('Due date/time'); ?></label>
                                <input type="datetime-local" name="timeline[<?= $idx; ?>][dueAt]" value="<?= sanitize(wo_format_datetime($entry['dueAt'] ?? null)); ?>">
                            </div>
                            <label class="pill" style="display:inline-flex; gap:6px; align-items:center;">
                                <input type="checkbox" name="timeline_remove[]" value="<?= sanitize((string)$idx); ?>"> <?= sanitize('Remove'); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    <div style="border:1px dashed #30363d; border-radius:10px; padding:10px;">
                        <h4 style="margin-top:0;"><?= sanitize('Add timeline entries'); ?></h4>
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <div class="field" style="margin-bottom:8px;">
                                <input name="new_timeline[<?= $i; ?>][milestone]" placeholder="<?= sanitize('Milestone'); ?>">
                                <input type="datetime-local" name="new_timeline[<?= $i; ?>][dueAt]" class="pill" style="margin-top:6px;">
                            </div>
                        <?php endfor; ?>
                    </div>
                    <button class="btn" type="submit"><?= sanitize('Save timeline'); ?></button>
                </form>
            </div>
        </div>
        <?php
    });
});
