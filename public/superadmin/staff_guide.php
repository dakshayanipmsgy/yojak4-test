<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    ensure_staff_guides_env();

    $entries = array_filter(staff_guide_index_entries(), function ($entry) {
        if (!empty($entry['archived'])) {
            return false;
        }
        return !empty($entry['published']);
    });

    $sections = [];
    foreach ($entries as $entry) {
        $section = staff_guide_load_section((string)($entry['id'] ?? ''));
        if (!$section) {
            continue;
        }
        if (!empty($section['archivedAt'])) {
            continue;
        }
        if (empty($section['published'])) {
            continue;
        }
        $sections[] = $section;
    }

    $selectedId = staff_guide_sanitize_id((string)($_GET['id'] ?? ''));
    $selected = null;
    foreach ($sections as $section) {
        if (($section['id'] ?? '') === $selectedId) {
            $selected = $section;
            break;
        }
    }
    if (!$selected && $sections) {
        $selected = $sections[0];
        $selectedId = $selected['id'] ?? null;
    }

    $title = get_app_config()['appName'] . ' | Staff Guide';

    render_layout($title, function () use ($sections, $selected, $selectedId, $user) {
        ?>
        <style>
            .guide-layout {
                display: grid;
                grid-template-columns: minmax(240px, 320px) minmax(0, 1fr);
                gap: 18px;
            }
            .guide-sidebar { display: grid; gap: 12px; }
            .guide-list { display: grid; gap: 8px; }
            .guide-list a {
                display: block;
                padding: 10px 12px;
                border-radius: 10px;
                border: 1px solid var(--border);
                background: #fff;
                color: #0f172a;
                font-weight: 600;
            }
            .guide-list a.active {
                border-color: var(--primary);
                box-shadow: 0 10px 20px rgba(37, 99, 235, 0.12);
                background: #f8fbff;
            }
            .guide-search input,
            .guide-search select {
                width: 100%;
                padding: 10px 12px;
                border-radius: 10px;
                border: 1px solid var(--border);
                background: #fff;
                font-size: 14px;
            }
            .guide-block {
                padding: 16px;
                border-radius: 12px;
                border: 1px solid var(--border);
                background: #fff;
                margin-bottom: 14px;
            }
            .guide-block h3 { margin: 0 0 10px 0; }
            .guide-block ul,
            .guide-block ol {
                margin: 0 0 0 18px;
                padding: 0;
                display: grid;
                gap: 8px;
            }
            .guide-meta {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
            }
            .guide-callout {
                background: #fff7ed;
                border-color: #fed7aa;
            }
            .guide-do-dont {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 12px;
            }
            .guide-do-dont .card {
                box-shadow: none;
                border: 1px solid var(--border);
            }
            .guide-faq details {
                border-bottom: 1px solid var(--border);
                padding: 10px 0;
            }
            .guide-faq summary {
                cursor: pointer;
                font-weight: 600;
                list-style: none;
            }
            .guide-faq summary::-webkit-details-marker { display: none; }
            .guide-faq details:last-child {
                border-bottom: none;
            }
            .guide-empty {
                text-align: center;
                padding: 24px;
            }
            .guide-mobile-only { display: none; }
            @media (max-width: 900px) {
                .guide-layout { grid-template-columns: 1fr; }
                .guide-sidebar { order: 2; }
                .guide-desktop-only { display: none; }
                .guide-mobile-only { display: block; }
            }
        </style>

        <div class="card" style="margin-bottom:16px;">
            <div class="guide-meta">
                <div>
                    <h2 style="margin:0;">Staff Guide</h2>
                    <p class="muted" style="margin:6px 0 0;">Operational references for admin workflows. Hello, <?= sanitize($user['username'] ?? ''); ?>.</p>
                </div>
                <div class="guide-search" style="min-width:220px;">
                    <label class="muted" style="font-size:12px;">Search the guide</label>
                    <input type="search" id="guide-search" placeholder="Type keywords...">
                </div>
                <div class="guide-search guide-mobile-only">
                    <label class="muted" style="font-size:12px;">Jump to a section</label>
                    <select id="guide-select">
                        <?php foreach ($sections as $section): ?>
                            <option value="<?= sanitize($section['id'] ?? ''); ?>" <?= ($section['id'] ?? '') === $selectedId ? 'selected' : ''; ?>>
                                <?= sanitize($section['title'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <a class="btn secondary" href="/superadmin/staff_guide_manage/index.php">Manage Sections</a>
                </div>
            </div>
        </div>

        <div class="guide-layout">
            <aside class="guide-sidebar guide-desktop-only">
                <div class="guide-list" id="guide-list">
                    <?php foreach ($sections as $section): ?>
                        <a href="/superadmin/staff_guide.php?id=<?= sanitize($section['id'] ?? ''); ?>"
                           class="<?= ($section['id'] ?? '') === $selectedId ? 'active' : ''; ?>"
                           data-search="<?= sanitize(strtolower(($section['title'] ?? '') . ' ' . ($section['summary'] ?? ''))); ?>">
                            <?= sanitize($section['title'] ?? ''); ?>
                            <div class="muted" style="font-size:12px; margin-top:4px;">
                                <?= sanitize($section['summary'] ?? ''); ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>

            <section>
                <?php if (!$selected): ?>
                    <div class="card guide-empty">
                        <p class="muted">No published staff guide sections yet.</p>
                    </div>
                <?php else: ?>
                    <div class="card" style="margin-bottom:16px;">
                        <h2 style="margin:0;"><?= sanitize($selected['title'] ?? ''); ?></h2>
                        <p class="muted" style="margin-top:6px;"><?= sanitize($selected['summary'] ?? ''); ?></p>
                    </div>

                    <div id="guide-content">
                        <?php foreach (($selected['contentBlocks'] ?? []) as $block): ?>
                            <?php
                            $type = strtolower((string)($block['type'] ?? ''));
                            $title = (string)($block['title'] ?? '');
                            $searchParts = [];
                            if ($title !== '') {
                                $searchParts[] = $title;
                            }
                            if (!empty($block['text'])) {
                                $searchParts[] = $block['text'];
                            }
                            if (!empty($block['items']) && is_array($block['items'])) {
                                foreach ($block['items'] as $item) {
                                    if (is_array($item)) {
                                        $searchParts[] = (string)($item['q'] ?? '');
                                        $searchParts[] = (string)($item['a'] ?? '');
                                    } else {
                                        $searchParts[] = (string)$item;
                                    }
                                }
                            }
                            if (!empty($block['do']) && is_array($block['do'])) {
                                $searchParts = array_merge($searchParts, $block['do']);
                            }
                            if (!empty($block['dont']) && is_array($block['dont'])) {
                                $searchParts = array_merge($searchParts, $block['dont']);
                            }
                            $searchValue = strtolower(trim(implode(' ', $searchParts)));
                            ?>
                            <div class="guide-block <?= $type === 'warning' ? 'guide-callout' : ''; ?>" data-search="<?= sanitize($searchValue); ?>">
                                <?php if ($title !== ''): ?>
                                    <h3><?= sanitize($title); ?></h3>
                                <?php elseif ($type === 'intro'): ?>
                                    <h3><?= sanitize('Intro'); ?></h3>
                                <?php endif; ?>

                                <?php if (in_array($type, ['intro', 'warning'], true)): ?>
                                    <p style="margin:0; line-height:1.6;">
                                        <?= guide_render_text((string)($block['text'] ?? '')); ?>
                                    </p>
                                <?php elseif ($type === 'steps'): ?>
                                    <ol>
                                        <?php foreach (($block['items'] ?? []) as $item): ?>
                                            <li><?= guide_render_text((string)$item); ?></li>
                                        <?php endforeach; ?>
                                    </ol>
                                <?php elseif ($type === 'tips'): ?>
                                    <ul>
                                        <?php foreach (($block['items'] ?? []) as $item): ?>
                                            <li><?= guide_render_text((string)$item); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php elseif ($type === 'do_dont'): ?>
                                    <div class="guide-do-dont">
                                        <div class="card" style="border-color:#bbf7d0; background:#f0fdf4;">
                                            <strong>What to do</strong>
                                            <ul>
                                                <?php foreach (($block['do'] ?? []) as $item): ?>
                                                    <li><?= guide_render_text((string)$item); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <div class="card" style="border-color:#fecaca; background:#fff1f2;">
                                            <strong>What to avoid</strong>
                                            <ul>
                                                <?php foreach (($block['dont'] ?? []) as $item): ?>
                                                    <li><?= guide_render_text((string)$item); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                <?php elseif ($type === 'faq'): ?>
                                    <div class="guide-faq">
                                        <?php foreach (($block['items'] ?? []) as $item): ?>
                                            <details>
                                                <summary><?= sanitize((string)($item['q'] ?? '')); ?></summary>
                                                <p class="muted" style="margin:8px 0 0; line-height:1.6;">
                                                    <?= guide_render_text((string)($item['a'] ?? '')); ?>
                                                </p>
                                            </details>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <script>
            (() => {
                const searchInput = document.getElementById('guide-search');
                const list = document.getElementById('guide-list');
                const blocks = document.querySelectorAll('[data-search]');
                const select = document.getElementById('guide-select');

                if (select) {
                    select.addEventListener('change', () => {
                        const id = select.value;
                        if (id) {
                            window.location.href = `/superadmin/staff_guide.php?id=${encodeURIComponent(id)}`;
                        }
                    });
                }

                if (searchInput && list) {
                    searchInput.addEventListener('input', () => {
                        const query = searchInput.value.toLowerCase().trim();
                        list.querySelectorAll('a').forEach((link) => {
                            const match = link.dataset.search || '';
                            link.style.display = query === '' || match.includes(query) ? 'block' : 'none';
                        });
                        blocks.forEach((block) => {
                            const match = block.dataset.search || '';
                            block.style.display = query === '' || match.includes(query) ? 'block' : 'none';
                        });
                    });
                }
            })();
        </script>
        <?php
    });
});
