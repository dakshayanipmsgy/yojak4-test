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

    $deptTemplates = load_department_templates($deptId);
    $globalTemplates = load_global_templates_cache($deptId);

    $generated = [];
    $genDir = department_generated_docs_path($deptId);
    $files = is_dir($genDir) ? scandir($genDir) : [];
    foreach ($files as $file) {
        if (!str_ends_with((string)$file, '.json')) {
            continue;
        }
        $doc = readJson($genDir . '/' . $file);
        if ($doc) {
            $generated[] = $doc;
        }
    }
    usort($generated, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
    $generated = array_slice($generated, 0, 10);

    $title = get_app_config()['appName'] . ' | Quick Doc Studio';
    render_layout($title, function () use ($deptTemplates, $globalTemplates, $generated, $user) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Quick Doc Studio'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Fast generation with preview and print options.'); ?></p>
                </div>
                <div class="pill"><?= sanitize('Signed in: ' . ($user['username'] ?? '')); ?></div>
            </div>
            <form method="post" action="/department/quick_doc_generate.php" style="margin-top:12px;display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="templateId"><?= sanitize('Template'); ?></label>
                    <select id="templateId" name="templateId" required>
                        <option value=""><?= sanitize('Select template'); ?></option>
                        <?php foreach ($deptTemplates as $tpl): ?>
                            <option value="<?= sanitize($tpl['templateId'] ?? ''); ?>"><?= sanitize('[Dept] ' . ($tpl['title'] ?? '')); ?></option>
                        <?php endforeach; ?>
                        <?php foreach ($globalTemplates as $tpl): ?>
                            <option value="<?= sanitize($tpl['templateId'] ?? ''); ?>"><?= sanitize('[Global] ' . ($tpl['title'] ?? '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="docTitle"><?= sanitize('Document Title'); ?></label>
                    <input id="docTitle" name="docTitle" required>
                </div>
                <div class="field">
                    <label for="tenderId"><?= sanitize('Tender ID (optional)'); ?></label>
                    <input id="tenderId" name="tenderId" placeholder="YTD-XXXXX">
                </div>
                <div class="field">
                    <label for="workorderId"><?= sanitize('Workorder ID (optional)'); ?></label>
                    <input id="workorderId" name="workorderId" placeholder="DWO-XXXXX">
                </div>
                <div class="field" style="display:flex;gap:12px;flex-wrap:wrap;">
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" name="showHeader" value="1" checked>
                        <span class="pill"><?= sanitize('Include header'); ?></span>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" name="showFooter" value="1" checked>
                        <span class="pill"><?= sanitize('Include footer'); ?></span>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" name="includeLogo" value="1" checked>
                        <span class="pill"><?= sanitize('Include logo'); ?></span>
                    </label>
                </div>
                <button class="btn" type="submit"><?= sanitize('Generate & Preview'); ?></button>
            </form>

            <h3 style="margin-top:16px;"><?= sanitize('Recent Generated'); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('Doc ID'); ?></th>
                        <th><?= sanitize('Title'); ?></th>
                        <th><?= sanitize('Template'); ?></th>
                        <th><?= sanitize('Created'); ?></th>
                        <th><?= sanitize('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$generated): ?>
                        <tr><td colspan="5" class="muted"><?= sanitize('No documents yet.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($generated as $doc): ?>
                            <tr>
                                <td><?= sanitize($doc['docId'] ?? ''); ?></td>
                                <td><?= sanitize($doc['docTitle'] ?? ''); ?></td>
                                <td><?= sanitize($doc['templateId'] ?? ''); ?></td>
                                <td><?= sanitize($doc['createdAt'] ?? ''); ?></td>
                                <td><a class="btn secondary" href="/department/quick_doc_print.php?docId=<?= urlencode($doc['docId'] ?? ''); ?>"><?= sanitize('Open'); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
