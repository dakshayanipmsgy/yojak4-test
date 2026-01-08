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
    $suggestions = pack_vault_suggestions($pack, $vaultFiles);
    $mappings = [];
    foreach ($pack['vaultMappings'] ?? [] as $map) {
        if (!empty($map['checklistItemId'])) {
            $mappings[$map['checklistItemId']] = $map;
        }
    }
    $annexureTemplates = load_pack_annexures($yojId, $packId, $context);

    $progress = pack_progress_percent($pack);
    $stats = pack_stats($pack);
    $title = get_app_config()['appName'] . ' | ' . ($pack['title'] ?? 'Tender Pack');
    $signedToken = pack_signed_token($pack['packId'], $yojId);

    render_layout($title, function () use ($pack, $progress, $stats, $signedToken, $contractor, $suggestions, $mappings, $annexureTemplates) {
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

        <form id="status-form" method="post" action="/contractor/pack_mark_status.php"></form>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:12px; margin-top:12px;">
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
                                <form method="post" action="/contractor/pack_checklist_toggle.php" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
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
                        <div style="border:1px solid #30363d; border-radius:12px; padding:10px; display:grid; gap:8px;">
                            <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                                <div>
                                    <strong><?= sanitize($item['title'] ?? ''); ?></strong>
                                    <div class="muted" style="margin-top:4px;"><?= sanitize($item['description'] ?? ''); ?></div>
                                    <div class="pill" style="margin-top:6px;"><?= !empty($item['required']) ? sanitize('Required') : sanitize('Optional'); ?></div>
                                    <?php if (!empty($mappings[$item['itemId']] ?? null)): ?>
                                        <div class="pill" style="margin-top:6px; border-color:#1f6feb; color:#9cc4ff;">
                                            <?= sanitize('Mapped: ' . (($mappings[$item['itemId']]['fileTitle'] ?? 'Vault doc')) . ' (' . ($mappings[$item['itemId']]['suggestedVaultDocId'] ?? '') . ')'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <select name="statuses[<?= sanitize($item['itemId']); ?>]" class="pill" style="min-width:140px;" form="status-form">
                                    <?php foreach (['pending','uploaded','generated','done'] as $status): ?>
                                        <option value="<?= sanitize($status); ?>" <?= ($item['status'] ?? '') === $status ? 'selected' : ''; ?>><?= sanitize(ucfirst($status)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                <form method="post" action="/contractor/pack_upload_item.php" enctype="multipart/form-data" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                    <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                                    <input type="hidden" name="itemId" value="<?= sanitize($item['itemId']); ?>">
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
                                        <div class="muted" style="color:#ffb3b8;"><?= sanitize('Not supported in YOJAK'); ?></div>
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
                    <p class="muted" style="margin:0;">Based on your vault tags. Attachments are linked, not copied.</p>
                </div>
                <div style="display:grid; gap:10px;">
                    <?php $displayed = 0; ?>
                    <?php foreach ($pack['checklist'] ?? [] as $item): ?>
                        <?php if ($displayed >= 25) { break; } ?>
                        <?php $displayed++; $itemId = $item['itemId'] ?? ($item['id'] ?? ''); $map = $itemId !== '' && isset($mappings[$itemId]) ? $mappings[$itemId] : null; $suggested = $itemId !== '' && isset($suggestions[$itemId]) ? $suggestions[$itemId] : null; ?>
                        <div style="border:1px solid #30363d; border-radius:10px; padding:10px; display:grid; gap:6px;">
                            <div style="display:flex; justify-content:space-between; gap:8px; align-items:center; flex-wrap:wrap;">
                                <div>
                                    <strong><?= sanitize($item['title'] ?? ''); ?></strong>
                                    <div class="muted"><?= sanitize($item['category'] ?? ''); ?></div>
                                </div>
                                <span class="pill"><?= sanitize(ucfirst($item['status'] ?? 'pending')); ?></span>
                            </div>
                            <?php if ($map): ?>
                                <div class="pill" style="border-color:#2ea043; color:#8ce99a;"><?= sanitize('Mapped: ' . ($map['fileTitle'] ?? 'Vault doc') . ' (' . ($map['suggestedVaultDocId'] ?? '') . ')'); ?></div>
                            <?php elseif ($suggested): ?>
                                <form method="post" action="/contractor/pack_map_vault.php" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                    <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                                    <input type="hidden" name="itemId" value="<?= sanitize($itemId); ?>">
                                    <input type="hidden" name="fileId" value="<?= sanitize($suggested['suggestedVaultDocId'] ?? ''); ?>">
                                    <input type="hidden" name="reason" value="<?= sanitize($suggested['reason'] ?? 'Suggested match'); ?>">
                                    <input type="hidden" name="confidence" value="<?= sanitize((string)($suggested['confidence'] ?? 0.6)); ?>">
                                    <div class="muted" style="flex:1; min-width:200px;"><?= sanitize('Suggested: ' . ($suggested['fileTitle'] ?? '') . ' (' . ($suggested['suggestedVaultDocId'] ?? '') . ')'); ?></div>
                                    <button class="btn secondary" type="submit"><?= sanitize('Attach from Vault'); ?></button>
                                </form>
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
