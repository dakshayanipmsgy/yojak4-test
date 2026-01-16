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
    $financialManualTemplates = pack_financial_manual_templates($pack);
    $annexureTemplatesList = array_values(array_merge($annexureTemplates, $financialManualTemplates));
    $contractorTemplates = load_contractor_templates_full($yojId);
    $seededPack = pack_seed_field_registry($pack, $contractor, $annexureTemplates, $contractorTemplates);
    if (($seededPack['fieldRegistry'] ?? []) !== ($pack['fieldRegistry'] ?? [])) {
        $pack = $seededPack;
        save_pack($pack, $context);
    }
    $packFieldsInfo = pack_collect_pack_fields($pack, $contractor, $annexureTemplates, $contractorTemplates);
    $catalog = pack_field_meta_catalog($pack, $annexureTemplates, $contractorTemplates);
    $tableTemplates = array_values(array_filter($annexureTemplatesList, static function (array $tpl): bool {
        return !empty($tpl['tables']) && is_array($tpl['tables']);
    }));
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

    render_layout($title, function () use ($pack, $progress, $stats, $signedToken, $contractor, $suggestions, $attachments, $missingIds, $annexureTemplates, $annexureTemplatesList, $packFieldsInfo, $catalog, $tableTemplates) {
        ?>
        <details class="mobile-accordion" open data-mobile-open>
            <summary><?= sanitize('Pack Index'); ?></summary>
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
                        <a class="btn secondary" href="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=full&mode=print&autoprint=1" target="_blank" rel="noopener"><?= sanitize('Print pack (browser)'); ?></a>
                        <a class="btn" href="/contractor/pack_export_pdf.php?packId=<?= sanitize($pack['packId']); ?>&doc=full" target="_blank" rel="noopener"><?= sanitize('Download PDF (Submission-ready)'); ?></a>
                        <a class="btn" href="/contractor/pack_export_zip.php?packId=<?= sanitize($pack['packId']); ?>&token=<?= sanitize($signedToken); ?>"><?= sanitize('Export ZIP'); ?></a>
                    </div>
                </div>
                <div style="display:grid; gap:8px; max-width:520px;">
                    <div style="height:12px; background:var(--surface); border:1px solid var(--border); border-radius:999px; overflow:hidden;">
                        <div style="width:<?= $progress; ?>%; height:100%; background:linear-gradient(90deg, var(--primary), #2ea043);"></div>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <span class="pill"><?= sanitize($stats['doneRequired'] . '/' . $stats['requiredItems'] . ' required complete'); ?></span>
                        <span class="pill"><?= sanitize($stats['generatedDocs'] . ' generated docs'); ?></span>
                    </div>
                </div>
            </div>
        </details>

        <div class="card" style="margin-top:12px; display:grid; gap:12px;" id="field-registry">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0;"><?= sanitize('Field Registry'); ?></h3>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Fill every required detail once. Values flow into every annexure and template.'); ?></p>
                </div>
                <div class="pill"><?= sanitize(($packFieldsInfo['filled'] ?? 0) . ' / ' . ($packFieldsInfo['total'] ?? 0) . ' filled'); ?></div>
            </div>
            <?php if ($packFieldsInfo['errors']): ?>
                <div class="flash" style="background:#201012;border:1px solid #f85149;">
                    <?= sanitize('Some fields could not be detected from templates. You can still edit known fields below.'); ?>
                </div>
            <?php endif; ?>
            <?php if ($packFieldsInfo['fields']): ?>
                <?php
                $complianceFields = $packFieldsInfo['groups']['Compliance Table'] ?? [];
                $nonComplianceGroups = $packFieldsInfo['groups'];
                unset($nonComplianceGroups['Compliance Table']);
                unset($nonComplianceGroups['Financial Manual Entry']);
                ?>
                <?php if ($complianceFields): ?>
                    <div style="border:1px solid var(--border);border-radius:12px;padding:10px;display:grid;gap:10px;">
                        <strong><?= sanitize('Compliance Table'); ?></strong>
                        <div style="display:grid; gap:8px;">
                            <?php foreach ($complianceFields as $field): ?>
                                <?php if ($field['type'] !== 'choice') { continue; } ?>
                                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; border:1px solid #1f6feb33; padding:8px 10px; border-radius:10px;">
                                    <div>
                                        <div style="font-weight:600;"><?= sanitize($field['label']); ?></div>
                                        <div class="muted" style="font-size:12px;"><?= sanitize('Current: ' . ($field['value'] !== '' ? strtoupper($field['value']) : 'Not set')); ?></div>
                                    </div>
                                    <form method="post" action="/contractor/pack_field_toggle.php" style="display:flex; gap:6px; flex-wrap:wrap;">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                                        <input type="hidden" name="key" value="<?= sanitize($field['key']); ?>">
                                        <?php foreach (['yes' => 'Yes', 'no' => 'No', 'na' => 'N/A'] as $choiceValue => $choiceLabel): ?>
                                            <?php $active = strtolower($field['value']) === $choiceValue; ?>
                                            <button class="btn <?= $active ? '' : 'secondary'; ?>" type="submit" name="value" value="<?= sanitize($choiceValue); ?>"><?= sanitize($choiceLabel); ?></button>
                                        <?php endforeach; ?>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($tableTemplates): ?>
                    <form method="post" action="/contractor/pack_fields_save.php" style="display:grid; gap:12px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                        <?php foreach ($tableTemplates as $tpl): ?>
                            <?php foreach ((array)($tpl['tables'] ?? []) as $table): ?>
                                <?php
                                $columns = is_array($table['columns'] ?? null) ? $table['columns'] : [];
                                if (!$columns) { continue; }
                                $isFinancialManual = strtolower(trim((string)($tpl['templateKind'] ?? $tpl['type'] ?? ''))) === 'financial_manual';
                                $tableId = pack_normalize_placeholder_key((string)($table['tableId'] ?? $table['title'] ?? 'table'));
                                ?>
                                <div style="border:1px solid var(--border);border-radius:12px;padding:10px;display:grid;gap:8px;">
                                    <div>
                                        <strong><?= sanitize(($tpl['annexureCode'] ?? 'Annexure') . ' • ' . ($tpl['title'] ?? 'Table')); ?></strong>
                                        <div class="muted" style="font-size:12px;"><?= sanitize($table['title'] ?? 'Table'); ?></div>
                                        <?php if ($isFinancialManual): ?>
                                            <div class="muted" style="font-size:12px;"><?= sanitize('Rates are entered for printing only. They are not saved on the platform.'); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="overflow:auto;">
                                        <table style="min-width:640px;" <?= $isFinancialManual ? 'class="financial-manual-table"' : ''; ?>>
                                            <thead>
                                            <tr>
                                                <?php foreach ($columns as $column): ?>
                                                    <th><?= sanitize($column['label'] ?? $column['key'] ?? ''); ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ((array)($table['rows'] ?? []) as $row): ?>
                                                <?php if (!is_array($row)) { continue; } ?>
                                                <?php $rowId = pack_normalize_placeholder_key((string)($row['rowId'] ?? 'row')); ?>
                                                <?php $qtyValue = (string)($row['qty'] ?? ($row['cells']['qty'] ?? '')); ?>
                                                <tr>
                                                    <?php foreach ($columns as $column): ?>
                                                        <?php
                                                        $colKey = pack_normalize_placeholder_key((string)($column['key'] ?? ''));
                                                        if ($colKey === '') {
                                                            echo '<td></td>';
                                                            continue;
                                                        }
                                                        if ($isFinancialManual) {
                                                            $cellValue = (string)($row[$colKey] ?? ($row['cells'][$colKey] ?? ''));
                                                            if (in_array($colKey, ['item_description', 'qty', 'unit'], true)) {
                                                                echo '<td>' . sanitize($cellValue) . '</td>';
                                                                continue;
                                                            }
                                                            if ($colKey === 'rate') {
                                                                $rateKey = 'rate:' . $tableId . ':' . $rowId;
                                                                echo '<td><input class="financial-rate" type="number" step="0.01" inputmode="decimal" data-rate-key="' . sanitize($rateKey) . '" data-qty="' . sanitize(trim($qtyValue)) . '"></td>';
                                                                continue;
                                                            }
                                                            if ($colKey === 'amount') {
                                                                echo '<td><span class="financial-amount"></span></td>';
                                                                continue;
                                                            }
                                                        }
                                                        if (!empty($column['readOnly'])) {
                                                            $cell = (string)($row[$colKey] ?? ($row['cells'][$colKey] ?? ''));
                                                            echo '<td>' . sanitize($cell) . '</td>';
                                                            continue;
                                                        }
                                                        $fieldKey = pack_table_cell_field_key($row, $colKey);
                                                        if ($fieldKey === '') {
                                                            $cell = (string)($row[$colKey] ?? '');
                                                            echo '<td>' . sanitize($cell) . '</td>';
                                                            continue;
                                                        }
                                                        $meta = $catalog[$fieldKey] ?? [];
                                                        $type = $meta['type'] ?? ($column['type'] ?? 'text');
                                                        $choices = $meta['choices'] ?? ($column['choices'] ?? []);
                                                        $current = pack_resolve_field_value($fieldKey, $pack, $contractor, true);
                                                        $inputValue = $pack['fieldRegistry'][$fieldKey] ?? $current;
                                                        ?>
                                                        <td>
                                                            <?php if ($type === 'choice' && $choices): ?>
                                                                <select name="fields[<?= sanitize($fieldKey); ?>]">
                                                                    <option value=""><?= sanitize('Select'); ?></option>
                                                                    <?php foreach ($choices as $choice): ?>
                                                                        <option value="<?= sanitize($choice); ?>" <?= strtolower((string)$inputValue) === strtolower((string)$choice) ? 'selected' : ''; ?>>
                                                                            <?= sanitize(ucwords((string)$choice)); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            <?php else: ?>
                                                                <input type="<?= $type === 'number' ? 'number' : 'text'; ?>"
                                                                       name="fields[<?= sanitize($fieldKey); ?>]"
                                                                       value="<?= sanitize((string)$inputValue); ?>">
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <div class="buttons" style="gap:8px; flex-wrap:wrap;">
                            <button class="btn secondary" type="submit"><?= sanitize('Save Table Entries'); ?></button>
                            <span class="muted" style="font-size:12px;"><?= sanitize('Table entries are stored in the Field Registry.'); ?></span>
                        </div>
                    </form>
                <?php endif; ?>
                <form method="post" action="/contractor/pack_fields_save.php" style="display:grid; gap:12px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <input type="hidden" name="packId" value="<?= sanitize($pack['packId']); ?>">
                    <?php foreach ($nonComplianceGroups as $group => $fields): ?>
                        <div style="border:1px solid var(--border);border-radius:12px;padding:10px;display:grid;gap:8px;">
                            <strong><?= sanitize($group); ?></strong>
                            <div style="display:grid; gap:8px;">
                                <?php foreach ($fields as $field): ?>
                                    <?php if ($field['type'] === 'choice') { continue; } ?>
                                    <?php
                                    $inputValue = $field['override'] !== '' ? $field['override'] : ($field['value'] !== '' ? $field['value'] : $field['suggestion']);
                                    ?>
                                    <label class="field" style="margin:0;">
                                        <span class="muted" style="font-size:12px;"><?= sanitize($field['label']); ?></span>
                                        <?php if ($field['type'] === 'textarea'): ?>
                                            <textarea name="fields[<?= sanitize($field['key']); ?>]" rows="3" maxlength="<?= (int)$field['max']; ?>" <?= $field['readOnly'] ? 'disabled' : ''; ?>><?= sanitize($field['readOnly'] ? $field['value'] : $inputValue); ?></textarea>
                                        <?php else: ?>
                                            <input type="<?= $field['type'] === 'date' ? 'date' : 'text'; ?>"
                                                   name="fields[<?= sanitize($field['key']); ?>]"
                                                   value="<?= sanitize($field['readOnly'] ? $field['value'] : $inputValue); ?>"
                                                   maxlength="<?= (int)$field['max']; ?>"
                                                   <?= $field['readOnly'] ? 'disabled' : ''; ?>>
                                        <?php endif; ?>
                                        <?php if ($field['readOnly']): ?>
                                            <div class="muted" style="font-size:11px;"><?= sanitize('Prefilled from tender metadata.'); ?></div>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($pack['restrictedAnnexures'])): ?>
                        <div style="border:1px dashed #f85149;border-radius:12px;padding:10px;display:grid;gap:6px;">
                            <strong><?= sanitize('Financial Manual Entry'); ?></strong>
                            <div class="muted"><?= sanitize('Financial/Commercial/Price formats will be printed as manual-entry templates. YOJAK does not calculate or suggest rates.'); ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="buttons" style="gap:8px; flex-wrap:wrap;">
                        <button class="btn" type="submit"><?= sanitize('Save & Continue'); ?></button>
                        <button class="btn secondary" type="submit" name="after" value="print"><?= sanitize('Save & Print Full Pack'); ?></button>
                        <span class="muted" style="font-size:12px;"><?= sanitize('Saved values update the pack Field Registry.'); ?></span>
                    </div>
                </form>
            <?php else: ?>
                <div class="flash" style="background:var(--surface-2);border:1px solid #2ea043;">
                    <?= sanitize('Generate annexure formats to detect field requirements.'); ?>
                </div>
            <?php endif; ?>
        </div>

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
                            <div style="border:1px solid var(--border); border-radius:10px; padding:10px; display:grid; gap:6px;">
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
                    <div class="flash" style="background:var(--surface-2);border:1px solid #2ea043;">
                        <?= sanitize('All required items have vault attachments or are no longer pending.'); ?>
                    </div>
                <?php endif; ?>
            </div>
            <details class="mobile-accordion" id="checklist-toggle" open>
                <summary><?= sanitize('Checklist'); ?></summary>
                <div class="card" style="display:grid; gap:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
                        <h3 style="margin:0;"><?= sanitize('Checklist (quick toggle)'); ?></h3>
                        <span class="pill"><?= sanitize(count($pack['checklist'] ?? []) . ' items'); ?></span>
                    </div>
                    <?php if (!empty($pack['checklist'])): ?>
                        <div style="display:grid; gap:8px;">
                            <?php foreach ($pack['checklist'] as $item): ?>
                                <?php $itemId = $item['itemId'] ?? ($item['id'] ?? ''); ?>
                                <div style="border:1px solid var(--border); border-radius:10px; padding:10px; display:grid; gap:6px;">
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
            </details>

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
                    <div class="flash" style="background:var(--surface-2);border:1px solid #1f6feb;">
                        <?= sanitize('Official checklist available. Link to the department to auto-load it.'); ?>
                        <a class="btn secondary" style="margin-left:8px;" href="/contractor/departments.php"><?= sanitize('Link now'); ?></a>
                    </div>
                <?php endif; ?>
                <div style="display:grid; gap:10px;">
                    <?php foreach ($pack['items'] ?? [] as $item): ?>
                        <?php $itemId = $item['itemId'] ?? ($item['id'] ?? ''); ?>
                        <?php $itemSuggestions = $itemId !== '' ? ($suggestions[$itemId] ?? []) : []; ?>
                        <?php $attached = $itemId !== '' ? ($attachments[$itemId] ?? null) : null; ?>
                        <div style="border:1px solid var(--border); border-radius:12px; padding:10px; display:grid; gap:8px;">
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
            <details class="mobile-accordion" open>
                <summary><?= sanitize('Templates'); ?></summary>
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
                                <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; border:1px solid var(--border); padding:8px 10px; border-radius:10px; flex-wrap:wrap;">
                                    <div>
                                        <strong><?= sanitize($tpl['name'] ?? 'Template'); ?></strong>
                                        <div class="muted" style="margin-top:4px;"><?= sanitize($tpl['lastGeneratedAt'] ?? ''); ?></div>
                                    </div>
                                    <div class="buttons" style="gap:6px;">
                                        <?php if (!empty($tpl['tplId'])): ?>
                                            <a class="btn secondary" href="/contractor/template_preview_pack.php?packId=<?= sanitize($pack['packId']); ?>&tplId=<?= sanitize($tpl['tplId']); ?>&letterhead=1" data-print-base="/contractor/template_preview_pack.php?packId=<?= sanitize($pack['packId']); ?>&tplId=<?= sanitize($tpl['tplId']); ?>" target="_blank" rel="noopener"><?= sanitize('Preview'); ?></a>
                                            <a class="btn" href="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=templates&tplId=<?= sanitize($tpl['tplId']); ?>&mode=print&autoprint=1&letterhead=1" data-print-base="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=templates&tplId=<?= sanitize($tpl['tplId']); ?>&mode=print&autoprint=1" target="_blank" rel="noopener"><?= sanitize('Print (opens dialog)'); ?></a>
                                            <a class="btn" href="/contractor/pack_export_pdf.php?packId=<?= sanitize($pack['packId']); ?>&doc=templates&tplId=<?= sanitize($tpl['tplId']); ?>" target="_blank" rel="noopener"><?= sanitize('Download PDF'); ?></a>
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
                                <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; border:1px solid var(--border); padding:8px 10px; border-radius:10px;">
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
            </details>
            <details class="mobile-accordion" open>
                <summary><?= sanitize('Annexures & Formats'); ?></summary>
                <div class="card" style="display:grid; gap:12px;">
                    <div>
                        <h3 style="margin:0;"><?= sanitize('Annexures & Formats'); ?></h3>
                        <p class="muted" style="margin:0;">Generate printable annexure templates from NIB list. Financial annexures are manual-entry formats.</p>
                    </div>
                    <div class="flash" style="display:grid;gap:6px;background:var(--surface-2);border:1px solid #1f6feb;">
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
                        <span class="pill"><?= sanitize(count($annexureTemplatesList) . ' templates generated'); ?></span>
                        <?php if (!empty($pack['restrictedAnnexures'])): ?>
                            <span class="pill" style="border-color:#f85149;color:#ffb3b8;"><?= sanitize('Financial manual-entry annexures present'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="display:grid;gap:6px;">
                        <?php $list = $pack['annexureList'] ?? ($pack['annexures'] ?? []); ?>
                        <?php if ($list): ?>
                            <?php foreach (array_slice($list, 0, 10) as $annex): ?>
                                <?php $label = is_array($annex) ? ($annex['title'] ?? ($annex['name'] ?? 'Annexure')) : (string)$annex; ?>
                                <div style="border:1px solid var(--border);border-radius:10px;padding:8px 10px;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                                    <div>
                                        <strong><?= sanitize($label); ?></strong>
                                        <?php if (pack_is_restricted_annexure_label($label)): ?>
                                            <div class="muted" style="color:#ffb3b8;"><?= sanitize('Financial manual entry format included (no rate automation).'); ?></div>
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
                    <?php if ($annexureTemplatesList): ?>
                        <div>
                            <h4 style="margin:0 0 6px 0;"><?= sanitize('Generated templates'); ?></h4>
                            <div style="display:grid;gap:6px;">
                                <?php foreach ($annexureTemplatesList as $tpl): ?>
                                    <div style="border:1px solid var(--border);border-radius:10px;padding:8px 10px;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                                        <div>
                                            <strong><?= sanitize(($tpl['annexureCode'] ?? 'Annexure') . ' • ' . ($tpl['title'] ?? 'Template')); ?></strong>
                                            <div class="muted" style="margin-top:4px;"><?= sanitize($tpl['type'] ?? 'Annexure'); ?></div>
                                        </div>
                                        <div class="buttons" style="gap:6px;">
                                            <?php if (!empty($tpl['isManualFinancial'])): ?>
                                                <a class="btn secondary" href="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=annexures&annexId=<?= sanitize($tpl['annexId'] ?? ''); ?>&annexurePreview=1&mode=preview&letterhead=1" target="_blank" rel="noopener"><?= sanitize('Preview'); ?></a>
                                            <?php else: ?>
                                                <a class="btn secondary" href="/contractor/annexure_preview.php?packId=<?= sanitize($pack['packId']); ?>&annexId=<?= sanitize($tpl['annexId'] ?? ''); ?>&letterhead=1" data-print-base="/contractor/annexure_preview.php?packId=<?= sanitize($pack['packId']); ?>&annexId=<?= sanitize($tpl['annexId'] ?? ''); ?>" target="_blank" rel="noopener"><?= sanitize('Preview'); ?></a>
                                            <?php endif; ?>
                                            <a class="btn" href="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=annexures&annexId=<?= sanitize($tpl['annexId'] ?? ''); ?>&annexurePreview=1&mode=print&autoprint=1&letterhead=1" data-print-base="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=annexures&annexId=<?= sanitize($tpl['annexId'] ?? ''); ?>&annexurePreview=1&mode=print&autoprint=1" target="_blank" rel="noopener"><?= sanitize('Print (opens dialog)'); ?></a>
                                            <a class="btn" href="/contractor/pack_export_pdf.php?packId=<?= sanitize($pack['packId']); ?>&doc=annexures&annexId=<?= sanitize($tpl['annexId'] ?? ''); ?>" target="_blank" rel="noopener"><?= sanitize('Download PDF'); ?></a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($pack['restrictedAnnexures'])): ?>
                        <div class="flash" style="background:#201012;border:1px solid #f85149;">
                            <strong><?= sanitize('Financial/Price Annexures (Manual Entry)'); ?></strong>
                            <p class="muted" style="margin:6px 0 0;"><?= sanitize('YOJAK will not calculate or suggest rates. Manual entry formats are provided for print.'); ?></p>
                            <ul style="margin:6px 0 0 16px; padding:0;">
                                <?php foreach ($pack['restrictedAnnexures'] as $rest): ?>
                                    <li>
                                        <?= sanitize(is_array($rest) ? ($rest['title'] ?? ($rest['name'] ?? 'Restricted')) : (string)$rest); ?>
                                        <span class="muted"><?= sanitize(' — Financial manual entry format included.'); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
            <div class="card" style="display:grid; gap:12px;" id="print-center">
                <div>
                    <h3 style="margin:0;"><?= sanitize('Print Center'); ?></h3>
                    <p class="muted" style="margin:0;">Preview or print every section with letterhead on/off.</p>
                </div>
                <div class="flash" style="display:grid;gap:6px;background:var(--surface-2);border:1px solid #1f6feb;">
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
                <div class="muted" style="font-size:12px;"><?= sanitize('In print dialog, keep Scale = 100% for exact layout.'); ?></div>
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
                        <div style="border:1px solid var(--border);border-radius:12px;padding:10px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                            <div>
                                <strong><?= sanitize($docLink['title']); ?></strong>
                                <div class="muted" style="margin-top:4px;"><?= sanitize($docLink['desc']); ?></div>
                            </div>
                            <div class="buttons" style="gap:6px;">
                                <a class="btn secondary" href="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=<?= sanitize($docLink['id']); ?>&mode=preview&letterhead=1" data-print-base="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=<?= sanitize($docLink['id']); ?>&mode=preview" target="_blank" rel="noopener"><?= sanitize('Preview'); ?></a>
                                <a class="btn" href="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=<?= sanitize($docLink['id']); ?>&mode=print&autoprint=1&letterhead=1" data-print-base="/contractor/pack_print.php?packId=<?= sanitize($pack['packId']); ?>&doc=<?= sanitize($docLink['id']); ?>&mode=print&autoprint=1" target="_blank" rel="noopener"><?= sanitize('Print (opens dialog)'); ?></a>
                                <a class="btn" href="/contractor/pack_export_pdf.php?packId=<?= sanitize($pack['packId']); ?>&doc=<?= sanitize($docLink['id']); ?>" target="_blank" rel="noopener"><?= sanitize('Download PDF'); ?></a>
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
                        <div style="border:1px solid var(--border); border-radius:10px; padding:10px; display:grid; gap:6px;">
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
            (() => {
                const rateInputs = Array.from(document.querySelectorAll('.financial-rate'));
                if (!rateInputs.length) {
                    return;
                }
                const storageKey = 'yojak_pack_rates_<?= sanitize($pack['packId']); ?>';
                let stored = {};
                try {
                    stored = JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {};
                } catch (err) {
                    stored = {};
                }
                const updateAmount = (input) => {
                    const qty = parseFloat(input.dataset.qty || '');
                    const rate = parseFloat(input.value || '');
                    const amountCell = input.closest('tr')?.querySelector('.financial-amount');
                    if (!amountCell) {
                        return;
                    }
                    if (Number.isFinite(qty) && Number.isFinite(rate)) {
                        amountCell.textContent = (qty * rate).toFixed(2);
                    } else {
                        amountCell.textContent = '';
                    }
                };
                const persist = () => {
                    const next = {};
                    rateInputs.forEach((input) => {
                        const key = input.dataset.rateKey || '';
                        if (key && input.value !== '') {
                            next[key] = input.value;
                        }
                    });
                    try {
                        window.localStorage.setItem(storageKey, JSON.stringify(next));
                    } catch (err) {
                        // ignore storage errors
                    }
                };
                rateInputs.forEach((input) => {
                    const key = input.dataset.rateKey || '';
                    if (key && stored[key]) {
                        input.value = stored[key];
                    }
                    updateAmount(input);
                    input.addEventListener('input', () => {
                        updateAmount(input);
                        persist();
                    });
                });
            })();
        </script>
        <?php
    });
});
