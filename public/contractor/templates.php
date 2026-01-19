<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_contractor_library_env($yojId);

    $globalTemplates = template_library_load_global();
    $myTemplates = template_library_load_contractor($yojId);
    $tab = ($_GET['tab'] ?? 'global') === 'my' ? 'my' : 'global';
    $query = trim((string)($_GET['q'] ?? ''));
    $title = get_app_config()['appName'] . ' | Templates Library';

    $filterTemplates = function (array $templates) use ($query): array {
        if ($query === '') {
            return $templates;
        }
        $needle = mb_strtolower($query);
        return array_values(array_filter($templates, function (array $tpl) use ($needle): bool {
            $haystack = mb_strtolower(($tpl['title'] ?? '') . ' ' . ($tpl['description'] ?? '') . ' ' . ($tpl['category'] ?? ''));
            return str_contains($haystack, $needle);
        }));
    };

    $globalTemplates = $filterTemplates($globalTemplates);
    $myTemplates = $filterTemplates($myTemplates);

    render_layout($title, function () use ($tab, $query, $globalTemplates, $myTemplates) {
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Templates Library</h2>
                    <p class="muted" style="margin:4px 0 0;">Use YOJAK defaults or build your own guided templates. No JSON editing required.</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn" href="/contractor/template_new.php">Create Template</a>
                    <a class="btn secondary" href="/contractor/template_request_new.php?type=template">Request Template/Pack from YOJAK team</a>
                </div>
            </div>

            <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;">
                <input type="hidden" name="tab" value="<?= sanitize($tab); ?>">
                <input class="input" type="search" name="q" value="<?= sanitize($query); ?>" placeholder="Search templates by title, category, description" style="min-width:240px;flex:1;">
                <button class="btn secondary" type="submit">Search</button>
            </form>

            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a class="btn <?= $tab === 'global' ? '' : 'secondary'; ?>" href="/contractor/templates.php?tab=global<?= $query !== '' ? '&q=' . urlencode($query) : ''; ?>">YOJAK Templates</a>
                <a class="btn <?= $tab === 'my' ? '' : 'secondary'; ?>" href="/contractor/templates.php?tab=my<?= $query !== '' ? '&q=' . urlencode($query) : ''; ?>">My Templates</a>
            </div>

            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
                <?php $currentList = $tab === 'global' ? $globalTemplates : $myTemplates; ?>
                <?php if (!$currentList): ?>
                    <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                        <p class="muted" style="margin:0;">No templates found yet.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($currentList as $tpl): ?>
                    <div style="border:1px solid var(--border);border-radius:12px;padding:12px;display:grid;gap:10px;background:var(--surface-2);">
                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                            <div>
                                <h3 style="margin:0 0 4px 0;"><?= sanitize($tpl['title'] ?? 'Template'); ?></h3>
                                <p class="muted" style="margin:0;"><?= sanitize(ucfirst($tpl['category'] ?? 'general')); ?> • <?= sanitize($tpl['scope'] ?? 'global'); ?></p>
                            </div>
                            <?php if (($tpl['scope'] ?? '') === 'global'): ?>
                                <span class="pill">YOJAK</span>
                            <?php endif; ?>
                        </div>
                        <p class="muted" style="margin:0;white-space:pre-wrap;max-height:140px;overflow:auto;"><?= sanitize(mb_substr($tpl['description'] ?? '', 0, 220)); ?></p>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a class="btn secondary" href="/contractor/pack_new.php?templateId=<?= sanitize($tpl['id'] ?? ''); ?>" style="color:var(--text);">Use</a>
                            <a class="btn secondary" href="/contractor/template_edit.php?id=<?= sanitize($tpl['id'] ?? ''); ?>&scope=<?= sanitize($tpl['scope'] ?? 'global'); ?>" style="color:var(--text);">Preview</a>
                            <?php if (($tpl['scope'] ?? '') === 'contractor'): ?>
                                <a class="btn" href="/contractor/template_edit.php?id=<?= sanitize($tpl['id'] ?? ''); ?>&scope=contractor">Edit</a>
                            <?php endif; ?>
                        </div>
                        <p class="muted" style="margin:0;">Updated: <?= sanitize($tpl['updatedAt'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
