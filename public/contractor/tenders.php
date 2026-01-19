<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    try {
        $user = require_role('contractor');

        $cards = [
            [
                'title' => 'Offline Tenders',
                'what' => 'Turn a tender PDF/NIB/NIT into a structured pack with checklists and annexure templates.',
                'when' => 'Use this when you already have a tender document and need a submission-ready pack.',
                'href' => '/contractor/offline_tenders.php',
            ],
            [
                'title' => 'Discovered Tenders',
                'what' => 'Browse tenders discovered by YOJAK from trusted sources and open details instantly.',
                'when' => 'Use this when you want to find new tenders and start offline prep quickly.',
                'href' => '/contractor/discovered_tenders.php',
            ],
            [
                'title' => 'Tender Packs',
                'what' => 'Your submission-ready folder with checklist, annexures, templates, and attachments.',
                'when' => 'Use this when preparing final documents, printing, or exporting a ZIP for upload.',
                'href' => '/contractor/packs.php',
            ],
            [
                'title' => 'Templates',
                'what' => 'Reusable letters, affidavits, declarations, and formats used across tender packs.',
                'when' => 'Use this to generate standardized documents with auto-filled fields.',
                'href' => '/contractor/templates.php',
            ],
            [
                'title' => 'Tender Archive',
                'what' => 'Store old tenders, outcomes, and notes to reuse checklists and templates later.',
                'when' => 'Use this to learn from past bids and build reusable references.',
                'href' => '/contractor/tender_archive.php',
            ],
        ];

        $title = get_app_config()['appName'] . ' | Tenders Hub';

        render_layout($title, function () use ($cards, $user) {
            ?>
        <style>
            .tenders-hero {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
            }
            .tenders-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 16px;
                margin-top: 16px;
            }
            .tenders-card {
                display: grid;
                gap: 10px;
                padding: 18px;
                border-radius: 16px;
                border: 1px solid var(--border);
                background: #fff;
                box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
            }
            .tenders-card h3 {
                margin: 0;
                font-size: 18px;
            }
            .tenders-meta {
                font-size: 13px;
                color: var(--muted);
                margin: 0;
                line-height: 1.5;
            }
            .tenders-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                align-items: center;
            }
            .guide-link {
                font-size: 13px;
                color: var(--primary);
                font-weight: 600;
            }
        </style>

        <div class="card" style="margin-bottom:16px;">
            <div class="tenders-hero">
                <div>
                    <h2 style="margin:0;">Tenders Hub</h2>
                    <p class="muted" style="margin:6px 0 0;">
                        Choose the right workflow for every tender step. Welcome, <?= sanitize($user['displayName'] ?? $user['username']); ?>.
                    </p>
                </div>
                <a class="btn secondary" href="/contractor/guide.php?id=GUIDE-TENDERS-HUB">Guide</a>
            </div>
        </div>

        <div class="tenders-grid">
            <?php foreach ($cards as $card): ?>
                <div class="tenders-card">
                    <h3><?= sanitize($card['title']); ?></h3>
                    <p class="tenders-meta"><strong>What this is:</strong> <?= sanitize($card['what']); ?></p>
                    <p class="tenders-meta"><strong>When to use it:</strong> <?= sanitize($card['when']); ?></p>
                    <div class="tenders-actions">
                        <a class="btn" href="<?= sanitize($card['href']); ?>">Open</a>
                        <a class="guide-link" href="/contractor/guide.php?id=GUIDE-TENDERS-HUB">Guide</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
            <?php
        });
    } catch (Throwable $e) {
        logEvent(DATA_PATH . '/logs/site.log', [
            'event' => 'contractor_tenders_hub_error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user' => current_user(),
        ]);
        throw $e;
    }
});
