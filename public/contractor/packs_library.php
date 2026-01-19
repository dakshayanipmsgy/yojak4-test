<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_contractor_library_env($yojId);

    $globalPacks = pack_library_load_global();
    $myPacks = pack_library_load_contractor($yojId);
    $tab = ($_GET['tab'] ?? 'global') === 'my' ? 'my' : 'global';
    $query = trim((string)($_GET['q'] ?? ''));
    $title = get_app_config()['appName'] . ' | Packs Library';

    $filterPacks = function (array $packs) use ($query): array {
        if ($query === '') {
            return $packs;
        }
        $needle = mb_strtolower($query);
        return array_values(array_filter($packs, function (array $pack) use ($needle): bool {
            $haystack = mb_strtolower(($pack['title'] ?? '') . ' ' . ($pack['description'] ?? ''));
            return str_contains($haystack, $needle);
        }));
    };

    $globalPacks = $filterPacks($globalPacks);
    $myPacks = $filterPacks($myPacks);

    $summaryForPack = function (array $pack): string {
        $counts = ['checklist_item' => 0, 'vault_doc_tag' => 0, 'template_ref' => 0, 'upload_slot' => 0];
        foreach (($pack['items'] ?? []) as $item) {
            $type = $item['type'] ?? '';
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }
        return sprintf(
            '%d checklist • %d vault tags • %d templates • %d uploads',
            $counts['checklist_item'],
            $counts['vault_doc_tag'],
            $counts['template_ref'],
            $counts['upload_slot']
        );
    };

    render_layout($title, function () use ($tab, $query, $globalPacks, $myPacks, $summaryForPack) {
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Packs Library</h2>
                    <p class="muted" style="margin:4px 0 0;">Reusable checklists, required documents, templates, and upload slots.</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn" href="/contractor/pack_new.php">Create Pack</a>
                    <a class="btn secondary" href="/contractor/template_request_new.php?type=pack">Request Template/Pack from YOJAK team</a>
                </div>
            </div>

            <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;">
                <input type="hidden" name="tab" value="<?= sanitize($tab); ?>">
                <input class="input" type="search" name="q" value="<?= sanitize($query); ?>" placeholder="Search packs by title or description" style="min-width:240px;flex:1;">
                <button class="btn secondary" type="submit">Search</button>
            </form>

            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a class="btn <?= $tab === 'global' ? '' : 'secondary'; ?>" href="/contractor/packs_library.php?tab=global<?= $query !== '' ? '&q=' . urlencode($query) : ''; ?>">YOJAK Packs</a>
                <a class="btn <?= $tab === 'my' ? '' : 'secondary'; ?>" href="/contractor/packs_library.php?tab=my<?= $query !== '' ? '&q=' . urlencode($query) : ''; ?>">My Packs</a>
            </div>

            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
                <?php $currentList = $tab === 'global' ? $globalPacks : $myPacks; ?>
                <?php if (!$currentList): ?>
                    <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                        <p class="muted" style="margin:0;">No packs found yet.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($currentList as $pack): ?>
                    <div style="border:1px solid var(--border);border-radius:12px;padding:12px;display:grid;gap:10px;background:var(--surface-2);">
                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                            <div>
                                <h3 style="margin:0 0 4px 0;"><?= sanitize($pack['title'] ?? 'Pack'); ?></h3>
                                <p class="muted" style="margin:0;"><?= sanitize($pack['scope'] ?? 'global'); ?></p>
                            </div>
                            <?php if (($pack['scope'] ?? '') === 'global'): ?>
                                <span class="pill">YOJAK</span>
                            <?php endif; ?>
                        </div>
                        <p class="muted" style="margin:0;white-space:pre-wrap;max-height:140px;overflow:auto;"><?= sanitize(mb_substr($pack['description'] ?? '', 0, 220)); ?></p>
                        <div class="muted"><?= sanitize($summaryForPack($pack)); ?></div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a class="btn secondary" href="/contractor/pack_edit.php?id=<?= sanitize($pack['id'] ?? ''); ?>&scope=<?= sanitize($pack['scope'] ?? 'global'); ?>" style="color:var(--text);">Preview</a>
                            <?php if (($pack['scope'] ?? '') === 'contractor'): ?>
                                <a class="btn" href="/contractor/pack_edit.php?id=<?= sanitize($pack['id'] ?? ''); ?>&scope=contractor">Edit</a>
                            <?php endif; ?>
                        </div>
                        <p class="muted" style="margin:0;">Updated: <?= sanitize($pack['updatedAt'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
