<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $templateId = trim((string)($_GET['templateId'] ?? ''));
    $scope = trim((string)($_GET['scope'] ?? ''));
    if ($templateId === '') {
        render_error_page('Template not selected.');
        return;
    }

    ensure_template_pack_library_env();
    ensure_contractor_templates_env($yojId);
    ensure_contractor_generated_docs_env($yojId);

    $template = create_docs_find_contractor_template($yojId, $templateId, $scope);
    if (!$template) {
        render_error_page('Template not found or access denied.');
        return;
    }

    $keys = create_docs_collect_template_keys($template);
    $fieldKeys = array_values(array_filter($keys, static fn($key) => !str_starts_with($key, 'table:')));
    $tableKeys = array_values(array_map(static fn($key) => substr($key, 6), array_filter($keys, static fn($key) => str_starts_with($key, 'table:'))));

    $contractor = load_contractor($yojId) ?? [];
    $memory = load_profile_memory($yojId);
    $values = create_docs_resolve_contractor_values($yojId, $contractor);

    $missing = array_values(array_filter($fieldKeys, static function ($key) use ($values) {
        return trim((string)($values[$key] ?? '')) === '';
    }));

    $registry = placeholder_registry([
        'contractor' => $contractor,
        'memory' => $memory,
    ]);
    $grouped = create_docs_group_missing_fields($missing, $registry);

    $title = get_app_config()['appName'] . ' | Create Doc';
    render_layout($title, function () use ($template, $templateId, $scope, $grouped, $missing, $tableKeys) {
        ?>
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Create Doc</h2>
                    <p class="muted" style="margin:4px 0 0;">Template: <?= sanitize(create_docs_template_title($template)); ?> (<?= sanitize(ucfirst($template['scope'] ?? $scope)); ?>)</p>
                </div>
                <a class="btn secondary" href="/contractor/create_docs.php">Back</a>
            </div>
        </div>

        <form method="post" action="/contractor/create_doc_generate.php" style="margin-top:12px;display:grid;gap:12px;">
            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
            <input type="hidden" name="templateId" value="<?= sanitize($templateId); ?>">
            <input type="hidden" name="scope" value="<?= sanitize($scope); ?>">

            <div class="card">
                <h3 style="margin-top:0;">Fill Missing Placeholders</h3>
                <p class="muted" style="margin:4px 0 12px;">Missing fields will print as blanks (__________).</p>
                <?php if (!$missing): ?>
                    <div class="pill">All placeholders already resolved.</div>
                <?php else: ?>
                    <div class="card" style="background:#f8fafc;border:1px solid var(--border);">
                        <strong><?= sanitize((string)count($missing)); ?> missing fields will print as blanks:</strong>
                        <ul style="margin:8px 0 0 18px;">
                            <?php foreach ($missing as $key): ?>
                                <li><?= sanitize($key); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php foreach ($grouped as $group => $fields): ?>
                        <div style="margin-top:16px;">
                            <h4 style="margin:0 0 8px 0;"><?= sanitize($group); ?></h4>
                            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                                <?php foreach ($fields as $key => $meta): ?>
                                    <?php $inputType = $meta['type'] ?? 'text'; ?>
                                    <label class="field">
                                        <span><?= sanitize($meta['label'] ?? $key); ?></span>
                                        <?php if ($inputType === 'textarea'): ?>
                                            <textarea name="fields[<?= sanitize($key); ?>]" rows="3" maxlength="<?= (int)($meta['max'] ?? 200); ?>"></textarea>
                                        <?php else: ?>
                                            <input type="<?= $inputType === 'date' ? 'date' : 'text'; ?>" name="fields[<?= sanitize($key); ?>]" maxlength="<?= (int)($meta['max'] ?? 200); ?>">
                                        <?php endif; ?>
                                        <label style="display:flex;align-items:center;gap:6px;margin-top:6px;">
                                            <input type="checkbox" name="save_future[<?= sanitize($key); ?>]" value="1">
                                            <span class="pill">Save this for future</span>
                                        </label>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($tableKeys): ?>
                <div class="card">
                    <h3 style="margin-top:0;">Tables</h3>
                    <p class="muted" style="margin-top:4px;">Add rows and fill optional rate. Amount auto-calculates if qty + rate are filled.</p>
                    <?php foreach ($tableKeys as $tableKey): ?>
                        <?php $columns = create_docs_table_columns($tableKey); ?>
                        <div style="margin-top:16px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                                <strong><?= sanitize($tableKey); ?></strong>
                                <button class="btn secondary add-row" type="button" data-table="<?= sanitize($tableKey); ?>">Add row</button>
                            </div>
                            <div style="overflow:auto;margin-top:8px;">
                                <table data-table="<?= sanitize($tableKey); ?>">
                                    <thead>
                                        <tr>
                                            <?php foreach ($columns as $column): ?>
                                                <th><?= sanitize($column['label']); ?></th>
                                            <?php endforeach; ?>
                                            <th>Remove</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <?php foreach ($columns as $column): ?>
                                                <td>
                                                    <input type="text" name="tables[<?= sanitize($tableKey); ?>][0][<?= sanitize($column['key']); ?>]" data-col="<?= sanitize($column['key']); ?>">
                                                </td>
                                            <?php endforeach; ?>
                                            <td><button class="btn secondary remove-row" type="button">Remove</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <h3 style="margin-top:0;">Preview & Print Settings</h3>
                <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                    <label class="field">
                        <span>Paper Size</span>
                        <select name="paper">
                            <option value="A4">A4</option>
                        </select>
                    </label>
                    <label class="field" style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="letterhead" value="1" checked>
                        <span class="pill">Letterhead spacing</span>
                    </label>
                    <label class="field" style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="headerFooterSpace" value="1" checked>
                        <span class="pill">Keep header/footer space</span>
                    </label>
                    <label class="field" style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="useSavedLetterhead" value="1" checked>
                        <span class="pill">Use saved logo/header/footer</span>
                    </label>
                </div>
                <div class="muted" style="margin-top:8px;">Preview will open after generation. Missing values will print as blanks.</div>
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button class="btn" type="submit">Generate Document</button>
                <a class="btn secondary" href="/contractor/create_docs.php">Cancel</a>
            </div>
        </form>

        <script>
            const tableContainers = document.querySelectorAll('table[data-table]');
            const updateAmount = (row) => {
                const qty = row.querySelector('[data-col="qty"]');
                const rate = row.querySelector('[data-col="rate"]');
                const amount = row.querySelector('[data-col="amount"]');
                if (!qty || !rate || !amount || amount.value.trim() !== '') return;
                const qtyVal = parseFloat(qty.value);
                const rateVal = parseFloat(rate.value);
                if (!Number.isNaN(qtyVal) && !Number.isNaN(rateVal)) {
                    amount.value = (qtyVal * rateVal).toFixed(2).replace(/\.00$/, '');
                }
            };

            tableContainers.forEach(table => {
                table.addEventListener('input', (event) => {
                    const row = event.target.closest('tr');
                    if (row) {
                        updateAmount(row);
                    }
                });
            });

            document.querySelectorAll('.add-row').forEach(btn => {
                btn.addEventListener('click', () => {
                    const key = btn.dataset.table;
                    const table = document.querySelector(`table[data-table="${key}"]`);
                    if (!table) return;
                    const tbody = table.querySelector('tbody');
                    const index = tbody.children.length;
                    const firstRow = tbody.querySelector('tr');
                    const clone = firstRow.cloneNode(true);
                    clone.querySelectorAll('input').forEach(input => {
                        input.value = '';
                        input.name = input.name.replace(/\[\d+\]/, `[${index}]`);
                    });
                    tbody.appendChild(clone);
                });
            });

            document.addEventListener('click', (event) => {
                if (!event.target.classList.contains('remove-row')) return;
                const row = event.target.closest('tr');
                const tbody = row ? row.parentElement : null;
                if (!row || !tbody) return;
                if (tbody.children.length === 1) {
                    row.querySelectorAll('input').forEach(input => input.value = '');
                    return;
                }
                row.remove();
            });
        </script>
        <?php
    });
});
