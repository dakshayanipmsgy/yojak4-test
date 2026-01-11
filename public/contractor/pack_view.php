<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $packId = trim($_GET['packId'] ?? '');
    $context = detect_pack_context($packId);
    ensure_packs_env($yojId, $context);

    $pack = $packId !== '' ? load_pack($yojId, $packId, $context) : null;
    if (!$pack || ($pack['yojId'] ?? '') !== $yojId) {
        render_error_page('Pack not found.');
        return;
    }
    $contractor = load_contractor($yojId) ?? [];
    $vaultFiles = contractor_vault_index($yojId);
    $attachments = pack_attachment_map($pack, $vaultFiles);
    $missingIds = pack_missing_checklist_item_ids($pack, $attachments);
    $suggestions = pack_vault_suggestions($pack, $vaultFiles, $attachments);
    $annexureTemplates = load_pack_annexures($yojId, $packId, $context);
    $packFieldsInfo = pack_collect_pack_fields($pack, $contractor, $annexureTemplates);
    $missingFieldGroups = [];
    foreach ($packFieldsInfo['groups'] as $group => $fields) {
        foreach ($fields as $field) {
            if (!$field['missing']) {
                continue;
            }
            $missingFieldGroups[$group][] = $field;
        }
    }
    if ($packFieldsInfo['errors']) {
        pack_log([
            'at' => now_kolkata()->format(DateTime::ATOM),
            'event' => 'PACK_FIELDS_PARSE_ERROR',
            'yojId' => $yojId,
            'packId' => $packId,
            'errors' => $packFieldsInfo['errors'],
        ]);
    }

    $progress = pack_progress_percent($pack);
    $stats = pack_stats($pack);
    $title = get_app_config()['appName'] . ' | ' . ($pack['title'] ?? 'Tender Pack');
    $signedToken = pack_signed_token($pack['packId'], $yojId);

    render_layout($title, function () use ($pack, $progress, $stats, $signedToken, $contractor, $suggestions, $attachments, $missingIds, $annexureTemplates, $packFieldsInfo, $missingFieldGroups) {
        ?>
        <div class="card" style="display:grid; gap:10px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize($pack['tenderTitle'] ?? $pack['title'] ?? 'Tender Pack'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;">
                        <?= sanitize($pack['packId']); ?> • <?= sanitize($pack['status'] ?? 'Pending'); ?>
                        <?php if (!empty($pack['sourceTender']['id'])): ?>
                            • <?= sanitize(($pack['sourceTender']['type'] ?? '') . ' ' . ($pack['sourceTender']['id'] ?? '')); ?>
                        <?php endif; ?>
                    </p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
                        <?php if (!empty($pack['defaultTemplatesApplied'])): ?>
                            <span class="pill success"><?= sanitize('Default tender letters added'); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (($pack['source'] ?? '') === 'dept'): ?>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
                            <span class="pill"><?= sanitize('Department Published'); ?></span>
                            <?php if (!empty($pack['prefillApplied'])): ?>
                                <span class="pill success"><?= sanitize('Prefill applied'); ?></span>
                            <?php else: ?>
                                <span class="pill"><?= sanitize('Prefill not applied'); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($pack['requirementSetApplied'])): ?>
                                <span class="pill success"><?= sanitize('Official checklist applied'); ?></span>
                            <?php elseif (!empty($pack['officialChecklistLocked'])): ?>
                                <span class="pill"><?= sanitize('Official checklist locked (link dept to unlock)'); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="buttons" style="gap:8px;">
                    <a class="btn secondary" href="/contractor/packs.php"><?= sanitize('Back to packs'); ?></a>
                    <a class="btn secondary" href="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=full" target="_blank" rel="noopener"><?= sanitize('Print pack'); ?></a>
                    <a class="btn" href="/contractor/pack_export_zip.php?packId=<?= sanitize($pack['packId']); ?>&token=<?= sanitize($signedToken); ?>"><?= sanitize('Export ZIP'); ?></a>
                </div>
            </div>
            <div style="display:grid; gap:8px; max-width:520px;">
                <div style="height:12px; background:#0d1117; border:1px solid #30363d; border-radius:999px; overflow:hidden;">
                    <div style="width:<?= $progress; ?>%; height:100%; background:linear-gradient(90deg, var(--primary), #2ea043);"></div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <span class="pill"><?= sanitize($stats['doneRequired'] . '/' . $stats['requiredItems'] . ' required complete'); ?></span>
                    <span class="pill"><?= sanitize($stats['generatedDocs'] . ' generated docs'); ?></span>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:12px; display:grid; gap:12px;" id="fill-missing">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0;"><?= sanitize('Fill Missing Details'); ?></h3>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('All missing placeholders across annexures are listed here. Fill once to update every template.'); ?></p>
                </div>
                <button class="btn secondary" type="button" onclick="const dlg=document.getElementById('edit-pack-fields-dialog'); if (dlg){dlg.showModal();}"><?= sanitize('Edit Pack Fields'); ?></button>
            </div>
            <?php if ($packFieldsInfo['errors']): ?>
                <div class="flash" style="background:#201012;border:1px solid #f85149;">
                    <?= sanitize('Some fields could not be detected from templates. You can still edit known fields below.'); ?>
                </div>
            <?php endif; ?>
            <?php if ($missingFieldGroups): ?>
                <form method="post" action="/contractor/pack_fields_save.php" style="display:grid; gap:12px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                    <?php foreach ($missingFieldGroups as $group => $fields): ?>
                        <div style="border:1px solid #30363d;border-radius:12px;padding:10px;display:grid;gap:8px;">
                            <strong><?= sanitize($group); ?></strong>
                            <div style="display:grid; gap:8px;">
                                <?php foreach ($fields as $field): ?>
                                    <label class="field" style="margin:0;">
                                        <span class="muted" style="font-size:12px;"><?= sanitize($field['label']); ?></span>
                                        <?php if ($field['type'] === 'textarea'): ?>
                                            <textarea name="fields[<?= sanitize($field['key']); ?>]" rows="3" maxlength="<?= (int)$field['max']; ?>"><?= sanitize($field['suggestion']); ?></textarea>
                                        <?php else: ?>
                                            <input type="<?= $field['type'] === 'date' ? 'date' : 'text'; ?>"
                                                   name="fields[<?= sanitize($field['key']); ?>]"
                                                   value="<?= sanitize($field['suggestion']); ?>"
                                                   maxlength="<?= (int)$field['max']; ?>">
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="buttons" style="gap:8px;">
                        <button class="btn" type="submit"><?= sanitize('Save missing details'); ?></button>
                        <span class="muted" style="font-size:12px;"><?= sanitize('Saved values update pack field overrides.'); ?></span>
                    </div>
                </form>
            <?php else: ?>
                <div class="flash" style="background:#0f1625;border:1px solid #2ea043;">
                    <?= sanitize($packFieldsInfo['fields'] ? 'All fields complete ✅' : 'Generate annexure formats to detect missing fields.'); ?>
                </div>
            <?php endif; ?>
        </div>

        <dialog id="edit-pack-fields-dialog" style="max-width:720px;width:90%;background:#0f1520;border:1px solid #30363d;border-radius:16px;color:var(--text);">
            <form method="post" action="/contractor/pack_fields_save.php" style="display:grid; gap:12px;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                    <h3 style="margin:0;"><?= sanitize('Edit Pack Fields'); ?></h3>
                    <button class="btn secondary" type="button" onclick="this.closest('dialog').close()"><?= sanitize('Close'); ?></button>
                </div>
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                <?php if ($packFieldsInfo['groups']): ?>
                    <?php foreach ($packFieldsInfo['groups'] as $group => $fields): ?>
                        <div style="border:1px solid #30363d;border-radius:12px;padding:10px;display:grid;gap:8px;">
                            <strong><?= sanitize($group); ?></strong>
                            <div style="display:grid; gap:8px;">
                                <?php foreach ($fields as $field): ?>
                                    <label class="field" style="margin:0;">
                                        <span class="muted" style="font-size:12px;"><?= sanitize($field['label']); ?></span>
                                        <?php if ($field['type'] === 'textarea'): ?>
                                            <textarea name="fields[<?= sanitize($field['key']); ?>]" rows="3" maxlength="<?= (int)$field['max']; ?>"><?= sanitize($field['override']); ?></textarea>
                                        <?php else: ?>
                                            <input type="<?= $field['type'] === 'date' ? 'date' : 'text'; ?>"
                                                   name="fields[<?= sanitize($field['key']); ?>]"
                                                   value="<?= sanitize($field['override']); ?>"
                                                   maxlength="<?= (int)$field['max']; ?>">
                                        <?php endif; ?>
                                        <?php if ($field['value'] !== ''): ?>
                                            <div class="muted" style="font-size:11px;"><?= sanitize('Current value: ' . $field['value']); ?></div>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="buttons" style="gap:8px;">
                        <button class="btn" type="submit"><?= sanitize('Save pack fields'); ?></button>
                    </div>
                <?php else: ?>
                    <p class="muted" style="margin:0;"><?= sanitize('Generate annexure formats to edit pack fields.'); ?></p>
                <?php endif; ?>
            </form>
        </dialog>

        <form id="status-form" method="post" action="/contractor/pack_mark_status.php"></form>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:12px; margin-top:12px;">
            <div class="card" style="display:grid; gap:12px;" id="missing-docs">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
                    <h3 style="margin:0;"><?= sanitize('Missing documents'); ?></h3>
                    <span class="pill" style="<?= $missingIds ? 'border-color:#f85149;color:#ffb3b8;' : ''; ?>"><?= sanitize(count($missingIds) . ' pending'); ?></span>
                </div>
                <p class="muted" style="margin:0;"><?= sanitize('Required checklist items still pending without a vault attachment.'); ?></p>
                <?php if ($missingIds): ?>
                    <div style="display:grid; gap:8px;">
                        <?php foreach ($pack['items'] ?? [] as $item): ?>
                            <?php $itemId = $item['itemId'] ?? ($item['id'] ?? ''); ?>
                            <?php if (!in_array($itemId, $missingIds, true)) { continue; } ?>
                            <div style="border:1px solid #30363d; border-radius:10px; padding:10px; display:grid; gap:6px;">
                                <div style="display:flex; justify-content:space-between; gap:8px; flex-wrap:wrap;">
                                    <div>
                                        <strong><?= sanitize($item['title'] ?? ''); ?></strong>
                                        <div class="muted" style="margin-top:4px;"><?= sanitize($item['description'] ?? ''); ?></div>
                                    </div>
                                    <span class="pill"><?= sanitize(ucfirst($item['status'] ?? 'pending')); ?></span>
                                </div>
                                <?php $itemSuggestions = $suggestions[$itemId] ?? []; ?>
                                <?php if ($itemSuggestions): ?>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                        <?php foreach ($itemSuggestions as $suggested): ?>
                                            <form method="post" action="/contractor/pack_attach_from_vault.php" style="display:flex; gap:6px; align-items:center;">
                                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                                <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                                                <input type="hidden" name="itemId" value="<?= sanitize($itemId); ?>">
                                                <input type="hidden" name="vaultDocId" value="<?= sanitize($suggested['suggestedVaultDocId'] ?? ''); ?>">
                                                <button class="btn secondary" type="submit"><?= sanitize('Attach ' . ($suggested['fileTitle'] ?? 'Vault doc')); ?></button>
                                                <span class="pill"><?= sanitize($suggested['confidenceLabel'] ?? 'Medium'); ?></span>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="muted" style="font-size:12px;"><?= sanitize($itemSuggestions[0]['reason'] ?? 'Suggested match'); ?></div>
                                <?php else: ?>
                                    <div class="muted" style="font-size:12px;"><?= sanitize('No vault suggestion yet. Update tags in Vault to improve matches.'); ?></div>
                                    <a class="btn secondary" href="/contractor/vault.php"><?= sanitize('Open Vault'); ?></a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="flash" style="background:#0f1625;border:1px solid #2ea043;">
                        <?= sanitize('All required items have vault attachments or are no longer pending.'); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card" style="display:grid; gap:12px;" id="checklist-toggle">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
                    <h3 style="margin:0;"><?= sanitize('Checklist (quick toggle)'); ?></h3>
                    <span class="pill"><?= sanitize(count($pack['checklist'] ?? []) . ' items'); ?></span>
                </div>
                <?php if (!empty($pack['checklist'])): ?>
                    <div style="display:grid; gap:8px;">
                        <?php foreach ($pack['checklist'] as $item): ?>
                            <?php $itemId = $item['itemId'] ?? ($item['id'] ?? ''); ?>
                            <div style="border:1px solid #30363d; border-radius:10px; padding:10px; display:grid; gap:6px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
                                    <div>
                                        <strong><?= sanitize($item['title'] ?? ''); ?></strong>
                                        <?php if (!empty($item['description'])): ?>
                                            <div class="muted" style="margin-top:4px;"><?= sanitize($item['description']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="pill" style="<?= ($item['status'] ?? '') === 'done' ? 'border-color:#2ea043;color:#8ce99a;' : ''; ?>"><?= sanitize(ucfirst($item['status'] ?? 'pending')); ?></span>
                                </div>
                                <form method="post" action="/contractor/pack_checklist_update.php" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                    <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                                    <input type="hidden" name="itemId" value="<?= sanitize($itemId); ?>">
                                    <button class="btn secondary" type="submit" name="status" value="pending"><?= sanitize('Mark Pending'); ?></button>
                                    <button class="btn" type="submit" name="status" value="done"><?= sanitize('Mark Done'); ?></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted" style="margin:0;"><?= sanitize('No checklist items available yet.'); ?></p>
                <?php endif; ?>
            </div>

            <div class="card" style="display:grid; gap:12px;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                    <h3 style="margin:0;"><?= sanitize('Pack items'); ?></h3>
                    <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-end;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>" form="status-form">
                        <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>" form="status-form">
                        <button class="btn secondary" type="submit" form="status-form"><?= sanitize('Save statuses'); ?></button>
                        <div style="font-size:12px; color:var(--muted); text-align:right;"><?= sanitize('Update multiple items together'); ?></div>
                    </div>
                </div>
                <?php if (!empty($pack['officialChecklistLocked'])): ?>
                    <div class="flash" style="background:#0f1625;border:1px solid #1f6feb;">
                        <?= sanitize('Official checklist available. Link to the department to auto-load it.'); ?>
                        <a class="btn secondary" style="margin-left:8px;" href="/contractor/departments.php"><?= sanitize('Link now'); ?></a>
                    </div>
                <?php endif; ?>
                <div style="display:grid; gap:10px;">
                    <?php foreach ($pack['items'] ?? [] as $item): ?>
                        <?php $itemId = $item['itemId'] ?? ($item['id'] ?? ''); ?>
                        <?php $itemSuggestions = $itemId !== '' ? ($suggestions[$itemId] ?? []) : []; ?>
                        <?php $attached = $itemId !== '' ? ($attachments[$itemId] ?? null) : null; ?>
                        <div style="border:1px solid #30363d; border-radius:12px; padding:10px; display:grid; gap:8px;">
                            <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                                <div>
                                    <strong><?= sanitize($item['title'] ?? ''); ?></strong>
                                    <div class="muted" style="margin-top:4px;"><?= sanitize($item['description'] ?? ''); ?></div>
                                    <div class="pill" style="margin-top:6px;"><?= !empty($item['required']) ? sanitize('Required') : sanitize('Optional'); ?></div>
                                    <?php if (!empty($attachments[$item['itemId']] ?? null)): ?>
                                        <div class="pill" style="margin-top:6px; border-color:#1f6feb; color:#9cc4ff;">
                                            <?= sanitize('Attached: ' . ($attachments[$item['itemId']]['title'] ?? 'Vault doc') . ' (' . ($attachments[$item['itemId']]['fileId'] ?? '') . ')'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <select name="statuses[<?= sanitize($itemId); ?>]" class="pill" style="min-width:140px;" form="status-form">
                                    <?php foreach (['pending','uploaded','generated','done'] as $status): ?>
                                        <option value="<?= sanitize($status); ?>" <?= ($item['status'] ?? '') === $status ? 'selected' : ''; ?>><?= sanitize(ucfirst($status)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                <form method="post" action="/contractor/pack_upload_item.php" enctype="multipart/form-data" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                    <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                                    <input type="hidden" name="itemId" value="<?= sanitize($itemId); ?>">
                                    <label class="btn secondary" style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                                        <input type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png" multiple required style="position:absolute; width:1px; height:1px; opacity:0;" onchange="this.form.submit();">
                                        <?= sanitize('Upload'); ?>
                                    </label>
                                    <div class="muted"><?= sanitize(count($item['fileRefs'] ?? []) . ' files'); ?></div>
                                </form>
                                <?php foreach (($item['fileRefs'] ?? []) as $file): ?>
                                    <a class="pill" href="<?= sanitize($file['path'] ?? '#'); ?>" target="_blank" rel="noopener"><?= sanitize($file['name'] ?? 'File'); ?></a>
                                <?php endforeach; ?>
                            </div>
                            <div style="display:grid; gap:6px;">
                                <?php if ($attached): ?>
                                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                        <span class="pill" style="border-color:#1f6feb; color:#9cc4ff;"><?= sanitize('Vault linked: ' . ($attached['title'] ?? 'Vault doc')); ?></span>
                                        <form method="post" action="/contractor/pack_detach_vault.php">
                                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                            <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                                            <input type="hidden" name="itemId" value="<?= sanitize($itemId); ?>">
                                            <input type="hidden" name="vaultDocId" value="<?= sanitize($attached['fileId'] ?? ''); ?>">
                                            <button class="btn secondary" type="submit"><?= sanitize('Detach'); ?></button>
                                        </form>
                                    </div>
                                <?php elseif ($itemSuggestions): ?>
                                    <div class="muted" style="font-size:12px;"><?= sanitize('Attach from vault suggestions'); ?></div>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                        <?php foreach ($itemSuggestions as $suggested): ?>
                                            <form method="post" action="/contractor/pack_attach_from_vault.php" style="display:flex; gap:6px; align-items:center;">
                                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                                <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                                                <input type="hidden" name="itemId" value="<?= sanitize($itemId); ?>">
                                                <input type="hidden" name="vaultDocId" value="<?= sanitize($suggested['suggestedVaultDocId'] ?? ''); ?>">
                                                <button class="btn secondary" type="submit"><?= sanitize('Attach ' . ($suggested['fileTitle'] ?? 'Vault doc')); ?></button>
                                                <span class="pill"><?= sanitize($suggested['confidenceLabel'] ?? 'Medium'); ?></span>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="muted" style="font-size:12px;"><?= sanitize('No vault suggestions yet. Tag vault files to improve matches.'); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card" style="display:grid; gap:12px;">
                <div>
                    <h3 style="margin:0;"><?= sanitize('Templates & letters'); ?></h3>
                    <p class="muted" style="margin:0;">Auto-filled using your contractor profile. Regenerate to refresh details.</p>
                </div>
                <form method="post" action="/contractor/pack_generate_templates.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                    <button class="btn" type="submit"><?= sanitize('Generate / Refresh templates'); ?></button>
                    <div class="muted" style="font-size:12px;"><?= sanitize('Refresh pulls the latest profile values (GST, PAN, signatory).'); ?></div>
                </form>
                <div>
                    <h4 style="margin:0 0 8px 0;"><?= sanitize('Auto-filled templates'); ?></h4>
                    <div style="display:grid; gap:8px;">
                        <?php foreach ($pack['generatedTemplates'] ?? [] as $tpl): ?>
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; border:1px solid #30363d; padding:8px 10px; border-radius:10px; flex-wrap:wrap;">
                                <div>
                                    <strong><?= sanitize($tpl['name'] ?? 'Template'); ?></strong>
                                    <div class="muted" style="margin-top:4px;"><?= sanitize($tpl['lastGeneratedAt'] ?? ''); ?></div>
                                </div>
                                <div class="buttons" style="gap:6px;">
                                    <?php if (!empty($tpl['tplId'])): ?>
                                        <a class="btn secondary" href="/contractor/template_preview_pack.php?packId=<?= sanitize($pack['packId']); ?>&tplId=<?= sanitize($tpl['tplId']); ?>&letterhead=1" data-print-base="/contractor/template_preview_pack.php?packId=<?= sanitize($pack['packId']); ?>&tplId=<?= sanitize($tpl['tplId']); ?>" target="_blank" rel="noopener"><?= sanitize('Preview'); ?></a>
                                        <a class="btn" href="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=templates&tplId=<?= sanitize($tpl['tplId']); ?>&letterhead=1" data-print-base="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=templates&tplId=<?= sanitize($tpl['tplId']); ?>" target="_blank" rel="noopener"><?= sanitize('Print'); ?></a>
                                    <?php endif; ?>
                                    <?php if (!empty($tpl['storedPath'])): ?>
                                        <a class="btn secondary" href="<?= sanitize($tpl['storedPath']); ?>" target="_blank" rel="noopener"><?= sanitize('Open'); ?></a>
                                    <?php else: ?>
                                        <span class="pill"><?= sanitize('Printable only'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($pack['generatedTemplates'])): ?>
                            <p class="muted" style="margin:0;"><?= sanitize('No templates generated yet. Use "Generate / Refresh" to create them.'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <h4 style="margin:0 0 8px 0;"><?= sanitize('Other generated documents'); ?></h4>
                    <div style="display:grid; gap:6px;">
                        <?php foreach ($pack['generatedDocs'] ?? [] as $doc): ?>
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; border:1px solid #30363d; padding:8px 10px; border-radius:10px;">
                                <div>
                                    <strong><?= sanitize($doc['title'] ?? 'Document'); ?></strong>
                                    <div class="muted" style="margin-top:4px;"><?= sanitize($doc['generatedAt'] ?? ''); ?></div>
                                </div>
                                <a class="btn secondary" href="<?= sanitize($doc['path'] ?? '#'); ?>" target="_blank" rel="noopener"><?= sanitize('Open'); ?></a>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($pack['generatedDocs'])): ?>
                            <p class="muted" style="margin:0;"><?= sanitize('No other generated documents yet.'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card" style="display:grid; gap:12px;">
                <div>
                    <h3 style="margin:0;"><?= sanitize('Annexures & Formats'); ?></h3>
                    <p class="muted" style="margin:0;">Generate printable annexure templates from NIB list. Financial annexures stay restricted.</p>
                </div>
                <div class="flash" style="display:grid;gap:6px;background:#0f1625;border:1px solid #1f6feb;">
                    <strong><?= sanitize('Steps'); ?></strong>
                    <ol style="margin:0 0 0 18px; padding:0; color:var(--text); line-height:1.5;">
                        <li><?= sanitize('Review checklist'); ?></li>
                        <li><?= sanitize('Generate annexure formats'); ?></li>
                        <li><?= sanitize('Fill missing profile fields if blanks appear'); ?></li>
                        <li><?= sanitize('Print checklist + annexures or full pack'); ?></li>
                        <li><?= sanitize('Export ZIP if needed'); ?></li>
                    </ol>
                </div>
                <div class="buttons" style="gap:8px; flex-wrap:wrap;">
                    <form method="post" action="/contractor/pack_generate_annexures.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                        <button class="btn" type="submit"><?= sanitize('Generate Annexure Formats'); ?></button>
                        <div class="muted" style="font-size:12px;"><?= sanitize('Auto-prefills firm, GST, PAN, tender details.'); ?></div>
                    </form>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <span class="pill"><?= sanitize(count($pack['annexureList'] ?? []) . ' annexures detected'); ?></span>
                    <span class="pill"><?= sanitize(count($annexureTemplates) . ' templates generated'); ?></span>
                    <?php if (!empty($pack['restrictedAnnexures'])): ?>
                        <span class="pill" style="border-color:#f85149;color:#ffb3b8;"><?= sanitize('Restricted financial annexures present'); ?></span>
                    <?php endif; ?>
                </div>
                <div style="display:grid;gap:6px;">
                    <?php $list = $pack['annexureList'] ?? ($pack['annexures'] ?? []); ?>
                    <?php if ($list): ?>
                        <?php foreach (array_slice($list, 0, 10) as $annex): ?>
                            <?php $label = is_array($annex) ? ($annex['title'] ?? ($annex['name'] ?? 'Annexure')) : (string)$annex; ?>
                            <div style="border:1px solid #30363d;border-radius:10px;padding:8px 10px;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                                <div>
                                    <strong><?= sanitize($label); ?></strong>
                                    <?php if (pack_is_restricted_annexure_label($label)): ?>
                                        <div class="muted" style="color:#ffb3b8;"><?= sanitize('Restricted: not supported in YOJAK (no rate docs)'); ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="pill"><?= sanitize('Annexure'); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($list) > 10): ?>
                            <div class="muted"><?= sanitize('+' . (count($list) - 10) . ' more annexures'); ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="muted" style="margin:0;"><?= sanitize('No annexures detected yet.'); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($annexureTemplates): ?>
                    <div>
                        <h4 style="margin:0 0 6px 0;"><?= sanitize('Generated templates'); ?></h4>
                        <div style="display:grid;gap:6px;">
                            <?php foreach ($annexureTemplates as $tpl): ?>
                                <div style="border:1px solid #30363d;border-radius:10px;padding:8px 10px;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                                    <div>
                                        <strong><?= sanitize(($tpl['annexureCode'] ?? 'Annexure') . ' • ' . ($tpl['title'] ?? 'Template')); ?></strong>
                                        <div class="muted" style="margin-top:4px;"><?= sanitize($tpl['type'] ?? 'Annexure'); ?></div>
                                    </div>
                                    <div class="buttons" style="gap:6px;">
                                        <a class="btn secondary" href="/contractor/annexure_preview.php?packId=<?= sanitize($pack['packId']); ?>&annexId=<?= sanitize($tpl['annexId'] ?? ''); ?>&letterhead=1" data-print-base="/contractor/annexure_preview.php?packId=<?= sanitize($pack['packId']); ?>&annexId=<?= sanitize($tpl['annexId'] ?? ''); ?>" target="_blank" rel="noopener"><?= sanitize('Preview'); ?></a>
                                        <a class="btn" href="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=annexures&annexId=<?= sanitize($tpl['annexId'] ?? ''); ?>&annexurePreview=1&letterhead=1" data-print-base="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=annexures&annexId=<?= sanitize($tpl['annexId'] ?? ''); ?>&annexurePreview=1" target="_blank" rel="noopener"><?= sanitize('Print'); ?></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($pack['restrictedAnnexures'])): ?>
                    <div class="flash" style="background:#201012;border:1px solid #f85149;">
                        <strong><?= sanitize('Restricted (Financial/Price references)'); ?></strong>
                        <p class="muted" style="margin:6px 0 0;"><?= sanitize('YOJAK will not ask for rates. These items are shown only for awareness.'); ?></p>
                        <ul style="margin:6px 0 0 16px; padding:0;">
                            <?php foreach ($pack['restrictedAnnexures'] as $rest): ?>
                                <li>
                                    <?= sanitize(is_array($rest) ? ($rest['title'] ?? ($rest['name'] ?? 'Restricted')) : (string)$rest); ?>
                                    <span class="muted"><?= sanitize(' — Restricted: not supported in YOJAK (no rate docs)'); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card" style="display:grid; gap:12px;" id="print-center">
                <div>
                    <h3 style="margin:0;"><?= sanitize('Print Center'); ?></h3>
                    <p class="muted" style="margin:0;">Preview or print every section with letterhead on/off.</p>
                </div>
                <div class="flash" style="display:grid;gap:6px;background:#0f1625;border:1px solid #1f6feb;">
                    <strong><?= sanitize('Stepper'); ?></strong>
                    <ol style="margin:0 0 0 18px; padding:0; color:var(--text); line-height:1.5;">
                        <li><?= sanitize('Review checklist'); ?></li>
                        <li><?= sanitize('Generate annexures/templates'); ?></li>
                        <li><?= sanitize('Print or export the full pack'); ?></li>
                    </ol>
                </div>
                <label class="field" style="margin:0;">
                    <span class="muted" style="font-size:12px;"><?= sanitize('Letterhead on print'); ?></span>
                    <select id="letterhead-select">
                        <option value="1"><?= sanitize('ON — use saved logo/header/footer'); ?></option>
                        <option value="0"><?= sanitize('OFF — reserve blank space'); ?></option>
                    </select>
                </label>
                <div class="muted" style="font-size:12px;"><?= sanitize('Header (30mm) and footer (20mm) space are always reserved for printing.'); ?></div>
                <div style="display:grid; gap:8px;">
                    <?php
                    $docLinks = [
                        ['id' => 'index', 'title' => 'Pack Index', 'desc' => 'Tender + contractor summary with key dates.'],
                        ['id' => 'checklist', 'title' => 'Checklist', 'desc' => 'Checklist status table and notes.'],
                        ['id' => 'annexures', 'title' => 'Annexures & Formats', 'desc' => 'Annexure list with generated templates.'],
                        ['id' => 'templates', 'title' => 'Templates', 'desc' => 'Letters/undertakings from your profile.'],
                        ['id' => 'full', 'title' => 'Full Pack', 'desc' => 'All sections in one print-ready set.'],
                    ];
                    ?>
                    <?php foreach ($docLinks as $docLink): ?>
                        <div style="border:1px solid #30363d;border-radius:12px;padding:10px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                            <div>
                                <strong><?= sanitize($docLink['title']); ?></strong>
                                <div class="muted" style="margin-top:4px;"><?= sanitize($docLink['desc']); ?></div>
                            </div>
                            <div class="buttons" style="gap:6px;">
                                <a class="btn secondary" href="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=<?= sanitize($docLink['id']); ?>&letterhead=1" data-print-base="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=<?= sanitize($docLink['id']); ?>" target="_blank" rel="noopener"><?= sanitize('Preview'); ?></a>
                                <a class="btn" href="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=<?= sanitize($docLink['id']); ?>&letterhead=1" data-print-base="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=<?= sanitize($docLink['id']); ?>" target="_blank" rel="noopener"><?= sanitize('Print'); ?></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="buttons" style="gap:8px;">
                    <a class="btn" href="/contractor/pack_export_zip.php?packId=<?= sanitize($pack['packId']); ?>&token=<?= sanitize($signedToken); ?>"><?= sanitize('Export ZIP'); ?></a>
                    <a class="btn secondary" href="/contractor/print_settings.php"><?= sanitize('Print header/footer settings'); ?></a>
                </div>
            </div>
            <div class="card" style="display:grid; gap:12px;">
                <div>
                    <h3 style="margin:0;"><?= sanitize('Suggested attachments'); ?></h3>
                    <p class="muted" style="margin:0;">Rule-based vault matches (GST/PAN/ITR/etc.). Attachments are linked, not copied.</p>
                </div>
                <div style="display:grid; gap:10px;">
                    <?php $displayed = 0; ?>
                    <?php foreach ($pack['checklist'] ?? [] as $item): ?>
                        <?php if ($displayed >= 25) { break; } ?>
                        <?php $displayed++; $itemId = $item['itemId'] ?? ($item['id'] ?? ''); ?>
                        <?php $attached = $itemId !== '' ? ($attachments[$itemId] ?? null) : null; ?>
                        <?php $itemSuggestions = $itemId !== '' ? ($suggestions[$itemId] ?? []) : []; ?>
                        <div style="border:1px solid #30363d; border-radius:10px; padding:10px; display:grid; gap:6px;">
                            <div style="display:flex; justify-content:space-between; gap:8px; align-items:center; flex-wrap:wrap;">
                                <div>
                                    <strong><?= sanitize($item['title'] ?? ''); ?></strong>
                                    <div class="muted"><?= sanitize($item['category'] ?? ''); ?></div>
                                </div>
                                <span class="pill"><?= sanitize(ucfirst($item['status'] ?? 'pending')); ?></span>
                            </div>
                            <?php if ($attached): ?>
                                <div class="pill" style="border-color:#2ea043; color:#8ce99a;"><?= sanitize('Attached: ' . ($attached['title'] ?? 'Vault doc') . ' (' . ($attached['fileId'] ?? '') . ')'); ?></div>
                            <?php elseif ($itemSuggestions): ?>
                                <div style="display:grid; gap:6px;">
                                    <?php foreach ($itemSuggestions as $suggested): ?>
                                        <form method="post" action="/contractor/pack_attach_from_vault.php" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                            <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                                            <input type="hidden" name="itemId" value="<?= sanitize($itemId); ?>">
                                            <input type="hidden" name="vaultDocId" value="<?= sanitize($suggested['suggestedVaultDocId'] ?? ''); ?>">
                                            <div class="muted" style="flex:1; min-width:200px;"><?= sanitize('Suggested: ' . ($suggested['fileTitle'] ?? '') . ' (' . ($suggested['suggestedVaultDocId'] ?? '') . ')'); ?></div>
                                            <span class="pill"><?= sanitize($suggested['confidenceLabel'] ?? 'Medium'); ?></span>
                                            <button class="btn secondary" type="submit"><?= sanitize('Attach from Vault'); ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="muted"><?= sanitize('No suggestion yet. Tag vault files to improve matches.'); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (($pack['checklist'] ?? []) === []): ?>
                        <p class="muted" style="margin:0;"><?= sanitize('No checklist items available.'); ?></p>
                    <?php endif; ?>
                </div>
                <a class="btn secondary" href="/contractor/vault.php"><?= sanitize('Open Vault'); ?></a>
            </div>
        </div>
        <script>
            (() => {
                const select = document.getElementById('letterhead-select');
                const updateLinks = () => {
                    if (!select) {
                        return;
                    }
                    const value = encodeURIComponent(select.value || '1');
                    document.querySelectorAll('[data-print-base]').forEach((link) => {
                        const base = link.getAttribute('data-print-base');
                        if (!base) {
                            return;
                        }
                        const joiner = base.includes('?') ? '&' : '?';
                        link.setAttribute('href', `${base}${joiner}letterhead=${value}`);
                    });
                };
                if (select) {
                    select.addEventListener('change', updateLinks);
                    updateLinks();
                }
            })();
        </script>
        <?php
    });
});
