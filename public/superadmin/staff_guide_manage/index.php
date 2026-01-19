<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('staff_guide_editor');
    ensure_staff_guides_env();

    $indexEntries = staff_guide_index_entries();
    $sections = [];
    foreach ($indexEntries as $entry) {
        $section = staff_guide_load_section((string)($entry['id'] ?? ''));
        if (!$section) {
            continue;
        }
        $sections[] = [
            'index' => $entry,
            'section' => $section,
        ];
    }

    $title = get_app_config()['appName'] . ' | Staff Guide Manager';

    render_layout($title, function () use ($sections, $indexEntries, $actor) {
        ?>
        <style>
            .guide-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
            }
            .guide-table {
                display: grid;
                gap: 12px;
                margin-top: 16px;
            }
            .guide-row {
                display: grid;
                gap: 12px;
            }
            .guide-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            .pill {
                display: inline-flex;
                align-items: center;
                padding: 4px 10px;
                border-radius: 999px;
                background: #eef2ff;
                color: #3730a3;
                font-size: 12px;
                font-weight: 700;
            }
            .muted { color: var(--muted); }
        </style>

        <div class="card">
            <div class="guide-header">
                <div>
                    <h2 style="margin:0;">Staff Guide Manager</h2>
                    <p class="muted" style="margin:6px 0 0;">Create and publish staff guide sections with operational checklists.</p>
                </div>
                <a class="btn" href="/superadmin/staff_guide_manage/new.php">New Section</a>
            </div>
        </div>

        <div class="guide-table">
            <?php if (!$sections): ?>
                <div class="card">
                    <p class="muted" style="margin:0;">No staff guide sections yet. Create the first section.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($sections as $idx => $row): ?>
                <?php
                $entry = $row['index'];
                $section = $row['section'];
                $isPublished = !empty($entry['published']);
                ?>
                <div class="card guide-row">
                    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                        <div>
                            <h3 style="margin:0;"><?= sanitize($section['title'] ?? 'Untitled'); ?></h3>
                            <p class="muted" style="margin:6px 0 0;">
                                <?= sanitize($section['summary'] ?? ''); ?>
                            </p>
                            <div style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">
                                <span class="pill"><?= sanitize($isPublished ? 'Published' : 'Draft'); ?></span>
                                <span class="pill" style="background:#ecfdf3; color:#166534;">Order <?= sanitize((string)($entry['order'] ?? '')); ?></span>
                            </div>
                        </div>
                        <div class="guide-actions">
                            <a class="btn secondary" href="/superadmin/staff_guide_manage/edit.php?id=<?= sanitize($section['id'] ?? ''); ?>">Edit</a>
                            <form method="post" action="/superadmin/staff_guide_manage/publish_toggle.php">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="id" value="<?= sanitize($section['id'] ?? ''); ?>">
                                <button class="btn secondary" type="submit"><?= sanitize($isPublished ? 'Unpublish' : 'Publish'); ?></button>
                            </form>
                        </div>
                    </div>
                    <div class="guide-actions">
                        <form method="post" action="/superadmin/staff_guide_manage/reorder.php">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <input type="hidden" name="id" value="<?= sanitize($section['id'] ?? ''); ?>">
                            <input type="hidden" name="direction" value="up">
                            <button class="btn secondary" type="submit" <?= $idx === 0 ? 'disabled' : ''; ?>>↑ Move Up</button>
                        </form>
                        <form method="post" action="/superadmin/staff_guide_manage/reorder.php">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <input type="hidden" name="id" value="<?= sanitize($section['id'] ?? ''); ?>">
                            <input type="hidden" name="direction" value="down">
                            <button class="btn secondary" type="submit" <?= $idx === count($indexEntries) - 1 ? 'disabled' : ''; ?>>↓ Move Down</button>
                        </form>
                    </div>
                    <div class="muted" style="font-size:12px;">
                        Updated: <?= sanitize($section['updatedAt'] ?? ''); ?> • Audience: <?= sanitize($section['audience'] ?? 'staff'); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    });
});
