<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $deptId = $user['deptId'] ?? '';
    ensure_department_env($deptId);
    require_department_permission($user, 'generate_docs');

    $query = trim((string)($_GET['q'] ?? ''));
    $category = trim((string)($_GET['category'] ?? ''));

    $templates = [];
    foreach (load_department_templates($deptId) as $tpl) {
        $tpl['scope'] = 'department';
        $templates[] = $tpl;
    }
    foreach (load_global_templates_cache($deptId) as $tpl) {
        $tpl['scope'] = 'global';
        $templates[] = $tpl;
    }

    if ($category !== '') {
        $templates = array_values(array_filter($templates, static function ($tpl) use ($category) {
            return strcasecmp((string)($tpl['category'] ?? ''), $category) === 0;
        }));
    }
    if ($query !== '') {
        $templates = array_values(array_filter($templates, static function ($tpl) use ($query) {
            $haystack = strtolower((string)($tpl['title'] ?? ''));
            return str_contains($haystack, strtolower($query));
        }));
    }

    usort($templates, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

    $categories = array_values(array_unique(array_filter(array_map(static function ($tpl) {
        return trim((string)($tpl['category'] ?? ''));
    }, $templates))));
    sort($categories);

    $history = create_docs_list_generated(department_generated_docs_path($deptId));

    $title = get_app_config()['appName'] . ' | Create Docs';
    render_layout($title, function () use ($templates, $history, $query, $category, $categories) {
        ?>
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Create Docs</h2>
                    <p class="muted" style="margin:4px 0 0;">Generate a standalone document from any saved template.</p>
                </div>
                <a class="btn secondary" href="/department/templates.php">Manage Templates</a>
            </div>
            <form method="get" style="margin-top:12px;display:grid;gap:12px;grid-template-columns:minmax(0,2fr) minmax(0,1fr);">
                <label class="field">
                    <span>Search templates</span>
                    <input name="q" value="<?= sanitize($query); ?>" placeholder="Search by title">
                </label>
                <label class="field">
                    <span>Category</span>
                    <select name="category">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= sanitize($cat); ?>" <?= $category === $cat ? 'selected' : ''; ?>><?= sanitize($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div style="grid-column:1 / -1;display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="btn" type="submit">Filter</button>
                    <a class="btn secondary" href="/department/create_docs.php">Reset</a>
                </div>
            </form>
        </div>

        <div style="display:grid;gap:12px;margin-top:12px;">
            <?php if (!$templates): ?>
                <div class="card"><p class="muted" style="margin:0;">No templates found for Create Docs.</p></div>
            <?php endif; ?>
            <?php foreach ($templates as $tpl): ?>
                <?php $templateId = create_docs_template_id($tpl); ?>
                <div class="card" style="display:grid;gap:10px;">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                        <div>
                            <h3 style="margin:0;"><?= sanitize(create_docs_template_title($tpl)); ?></h3>
                            <p class="muted" style="margin:4px 0 0;"><?= sanitize($tpl['category'] ?? 'General'); ?> • <?= sanitize($templateId); ?> • <?= sanitize(ucfirst($tpl['scope'] ?? 'global')); ?></p>
                        </div>
                        <a class="btn" href="/department/create_doc_new.php?templateId=<?= urlencode($templateId); ?>&scope=<?= urlencode((string)($tpl['scope'] ?? '')); ?>">Create Doc</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card" style="margin-top:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0;">Recent Generated Docs</h3>
                    <p class="muted" style="margin:4px 0 0;">Latest standalone documents you generated.</p>
                </div>
            </div>
            <table style="margin-top:12px;">
                <thead>
                    <tr>
                        <th>Doc ID</th>
                        <th>Title</th>
                        <th>Template</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$history): ?>
                        <tr><td colspan="5" class="muted">No documents generated yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $doc): ?>
                            <tr>
                                <td><?= sanitize($doc['docId'] ?? ''); ?></td>
                                <td><?= sanitize($doc['title'] ?? ''); ?></td>
                                <td><?= sanitize($doc['templateRef']['templateId'] ?? ''); ?></td>
                                <td><?= sanitize($doc['createdAt'] ?? ''); ?></td>
                                <td><a class="btn secondary" href="/department/create_doc_view.php?docId=<?= urlencode($doc['docId'] ?? ''); ?>">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
