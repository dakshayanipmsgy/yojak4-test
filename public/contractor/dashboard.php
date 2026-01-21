<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $title = get_app_config()['appName'] . ' | Contractor Dashboard';

    $contractor = load_contractor($yojId) ?: [];
    $now = now_kolkata();
    $vaultFiles = contractor_vault_index($yojId);
    $contractorTemplates = load_contractor_templates_full($yojId);

    $packsInProgress = 0;
    $missingFieldsTotal = 0;
    $checklistPendingTotal = 0;
    $topMissingPack = null;

    foreach (['tender', 'workorder'] as $context) {
        foreach (packs_index($yojId, $context) as $entry) {
            $packId = $entry['packId'] ?? '';
            if ($packId === '') {
                continue;
            }
            $pack = load_pack($yojId, $packId, $context);
            if (!$pack) {
                continue;
            }
            $status = resolve_pack_status($pack);
            if ($status === 'Completed') {
                continue;
            }
            $packsInProgress++;

            $annexures = load_pack_annexures($yojId, $packId, $context);
            $fieldSummary = pack_collect_pack_fields($pack, $contractor, $annexures, $contractorTemplates);
            $missingFields = 0;
            foreach ($fieldSummary['fields'] as $field) {
                if (!empty($field['missing']) && ($field['group'] ?? '') !== 'Financial Manual Entry') {
                    $missingFields++;
                }
            }
            $missingFieldsTotal += $missingFields;

            if ($topMissingPack === null || $missingFields > ($topMissingPack['missing'] ?? -1)) {
                $topMissingPack = [
                    'packId' => $packId,
                    'missing' => $missingFields,
                ];
            }

            $stats = pack_stats($pack);
            $checklistPendingTotal += $stats['pendingRequired'] ?? 0;
        }
    }

    ensure_reminders_env($yojId);
    $remindersDue7 = 0;
    $reminderCutoff = $now->modify('+7 days');
    foreach (reminder_index_entries($yojId) as $entry) {
        $entry = normalize_reminder_entry($entry);
        if (($entry['status'] ?? 'open') === 'done') {
            continue;
        }
        $dueAtRaw = $entry['dueAt'] ?? $entry['dueDate'] ?? '';
        if ($dueAtRaw === '' || $dueAtRaw === null) {
            continue;
        }
        try {
            $dueAt = new DateTimeImmutable((string)$dueAtRaw, new DateTimeZone('Asia/Kolkata'));
        } catch (Exception $e) {
            continue;
        }
        if ($dueAt >= $now && $dueAt <= $reminderCutoff) {
            $remindersDue7++;
        }
    }

    $requiredVaultDocs = [
        [
            'label' => 'GST',
            'keywords' => ['gst', 'gstin'],
        ],
        [
            'label' => 'PAN',
            'keywords' => ['pan'],
        ],
        [
            'label' => 'ITR (Latest)',
            'keywords' => ['itr', 'income tax'],
        ],
        [
            'label' => 'Bank Proof',
            'keywords' => ['bank', 'cancelled cheque', 'cheque', 'statement', 'ifsc'],
        ],
        [
            'label' => 'Registration',
            'keywords' => ['registration', 'incorporation', 'msme', 'udyam'],
        ],
        [
            'label' => 'Experience',
            'keywords' => ['experience', 'completion certificate', 'work completion'],
        ],
    ];

    $vaultMissingCount = 0;
    foreach ($requiredVaultDocs as $doc) {
        $found = false;
        foreach ($vaultFiles as $file) {
            if (!empty($file['deletedAt'])) {
                continue;
            }
            $docType = strtolower(pack_vault_doc_type($file));
            $title = strtolower((string)($file['title'] ?? ''));
            $tags = array_map('strtolower', $file['tags'] ?? []);
            foreach ($doc['keywords'] as $keyword) {
                if ($keyword !== '' && (str_contains($docType, $keyword) || str_contains($title, $keyword))) {
                    $found = true;
                    break 2;
                }
                foreach ($tags as $tag) {
                    if ($tag !== '' && str_contains($tag, str_replace(' ', '', $keyword))) {
                        $found = true;
                        break 3;
                    }
                }
            }
        }
        if (!$found) {
            $vaultMissingCount++;
        }
    }

    $offlineTenders = offline_tenders_index($yojId);
    $offlineTotal = 0;
    $offlinePending = 0;
    foreach ($offlineTenders as $entry) {
        if (!empty($entry['deletedAt'])) {
            continue;
        }
        $offlineTotal++;
        $status = strtolower((string)($entry['status'] ?? 'draft'));
        if ($status === '' || in_array($status, ['draft', 'pending', 'extraction_pending', 'needs_review', 'needs_input'], true)) {
            $offlinePending++;
        }
    }

    $assistedCounts = ['pending' => 0, 'delivered' => 0, 'needs_input' => 0];
    if (function_exists('assisted_v2_list_requests')) {
        foreach (assisted_v2_list_requests() as $request) {
            if (($request['contractor']['yojId'] ?? '') !== $yojId) {
                continue;
            }
            $status = strtolower(str_replace(' ', '_', (string)($request['status'] ?? 'pending')));
            if (in_array($status, ['delivered', 'completed', 'done'], true)) {
                $assistedCounts['delivered']++;
            } elseif (in_array($status, ['needs_input', 'needs_review'], true)) {
                $assistedCounts['needs_input']++;
            } else {
                $assistedCounts['pending']++;
            }
        }
    }

    $billCounts = ['draft' => 0, 'submitted' => 0, 'pendingApproval' => 0];
    foreach (contractor_bills_index($yojId) as $bill) {
        $status = strtolower((string)($bill['status'] ?? 'draft'));
        if ($status === 'draft') {
            $billCounts['draft']++;
        } elseif ($status === 'submitted') {
            $billCounts['submitted']++;
            $billCounts['pendingApproval']++;
        }
    }

    $pendingDeptLinks = 0;
    foreach (load_contractor_links($yojId) as $link) {
        $status = strtolower((string)($link['status'] ?? 'pending'));
        if (in_array($status, ['pending', 'requested', 'awaiting'], true)) {
            $pendingDeptLinks++;
        }
    }

    logEvent(DATA_PATH . '/logs/contractor_dashboard.log', [
        'at' => $now->format(DateTime::ATOM),
        'yojId' => $yojId,
        'event' => 'DASH_COUNTS',
        'packsInProgress' => $packsInProgress,
        'missingFields' => $missingFieldsTotal,
        'remindersDue7' => $remindersDue7,
    ]);

    render_layout($title, function () use ($user, $packsInProgress, $missingFieldsTotal, $checklistPendingTotal, $topMissingPack, $remindersDue7, $vaultMissingCount, $offlineTotal, $offlinePending, $assistedCounts, $billCounts, $pendingDeptLinks) {
        ?>
        <style>
            .dashboard-grid {
                display: grid;
                gap: 16px;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            }
            .dash-card {
                display: grid;
                gap: 8px;
                min-height: 168px;
                padding: 16px;
            }
            .dash-number {
                font-size: 32px;
                font-weight: 700;
                letter-spacing: -0.02em;
            }
            .dash-link {
                color: var(--accent);
                font-weight: 600;
                text-decoration: none;
            }
            .dash-link:hover {
                text-decoration: underline;
            }
            .suggestion-cta {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 16px;
                flex-wrap: wrap;
            }
            .suggestion-cta p {
                margin: 0;
                color: var(--muted);
            }
            .suggestion-cta h3 {
                margin: 0 0 6px 0;
                color: #0f172a;
            }
        </style>

        <div class="card" style="display:grid; gap:12px;">
            <h2 style="margin:0;"><?= sanitize('Welcome, ' . ($user['displayName'] ?? $user['username'])); ?></h2>
            <p class="muted" style="margin:0;"><?= sanitize('Quick actions to keep your packs, reminders, and documents on track.'); ?></p>
            <div class="buttons">
                <a class="btn" href="/contractor/offline_tender_create.php"><?= sanitize('Create Offline Tender'); ?></a>
                <a class="btn secondary" href="/contractor/pack_create.php"><?= sanitize('Create Pack'); ?></a>
                <a class="btn secondary" href="/contractor/vault.php#vault-upload"><?= sanitize('Upload to Vault'); ?></a>
                <a class="btn secondary" href="/contractor/profile.php"><?= sanitize('My Profile'); ?></a>
                <a class="btn secondary" href="/contractor/guide.php"><?= sanitize('Guide'); ?></a>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="suggestion-cta">
                <div>
                    <h3><?= sanitize('Help us improve YOJAK'); ?></h3>
                    <p><?= sanitize('We’re building YOJAK to fulfill all your needs. Please share what features you want, or what is confusing, so we can improve.'); ?></p>
                </div>
                <a class="btn" href="/suggestions/new.php?page=/contractor/dashboard.php"><?= sanitize('Share a Suggestion'); ?></a>
            </div>
        </div>

        <div class="dashboard-grid" style="margin-top:16px;">
            <div class="card dash-card" style="border:1px solid #1f6feb;">
                <div class="dash-number">🧾</div>
                <strong><?= sanitize('Create Docs'); ?></strong>
                <div class="muted"><?= sanitize('Generate standalone documents from saved templates.'); ?></div>
                <a class="dash-link" href="/contractor/create_docs.php"><?= sanitize('Open'); ?></a>
            </div>

            <div class="card dash-card">
                <div class="dash-number"><?= sanitize((string)$packsInProgress); ?></div>
                <strong><?= sanitize('Packs In Progress'); ?></strong>
                <div class="muted"><?= sanitize('Open packs that still need completion.'); ?></div>
                <a class="dash-link" href="/contractor/packs.php?filter=in_progress"><?= sanitize('Open'); ?></a>
            </div>

            <div class="card dash-card">
                <div class="dash-number"><?= sanitize((string)$missingFieldsTotal); ?></div>
                <strong><?= sanitize('Missing Fields to Fill'); ?></strong>
                <div class="muted">
                    <?= sanitize('Required fields missing across active packs.'); ?>
                    <?php if ($topMissingPack && ($topMissingPack['missing'] ?? 0) > 0): ?>
                        <span><?= sanitize(' Top 1 pack: ' . $topMissingPack['packId'] . ' (' . $topMissingPack['missing'] . ' missing)'); ?></span>
                    <?php endif; ?>
                </div>
                <a class="dash-link" href="/contractor/packs.php?filter=missing_fields"><?= sanitize('Open'); ?></a>
            </div>

            <div class="card dash-card">
                <div class="dash-number"><?= sanitize((string)$checklistPendingTotal); ?></div>
                <strong><?= sanitize('Checklist Items Pending'); ?></strong>
                <div class="muted"><?= sanitize('Required checklist items not marked done.'); ?></div>
                <a class="dash-link" href="/contractor/packs.php?filter=checklist_pending"><?= sanitize('Open'); ?></a>
            </div>

            <div class="card dash-card">
                <div class="dash-number"><?= sanitize((string)$remindersDue7); ?></div>
                <strong><?= sanitize('Reminders Due Soon'); ?></strong>
                <div class="muted"><?= sanitize('Due in the next 7 days.'); ?></div>
                <a class="dash-link" href="/contractor/reminders.php?filter=due_7_days"><?= sanitize('Open'); ?></a>
            </div>

            <div class="card dash-card">
                <div class="dash-number"><?= sanitize((string)$vaultMissingCount); ?></div>
                <strong><?= sanitize('Vault Documents Missing'); ?></strong>
                <div class="muted"><?= sanitize('Baseline compliance docs to upload.'); ?></div>
                <a class="dash-link" href="/contractor/vault.php?filter=missing"><?= sanitize('Open'); ?></a>
            </div>

            <div class="card dash-card">
                <div class="dash-number"><?= sanitize((string)$offlineTotal); ?></div>
                <strong><?= sanitize('Offline Tenders'); ?></strong>
                <div class="muted"><?= sanitize($offlinePending . ' pending extraction/manual work.'); ?></div>
                <a class="dash-link" href="/contractor/offline_tenders.php"><?= sanitize('Open'); ?></a>
            </div>

            <div class="card dash-card">
                <div class="dash-number"><?= sanitize((string)($assistedCounts['pending'] + $assistedCounts['delivered'] + $assistedCounts['needs_input'])); ?></div>
                <strong><?= sanitize('Assisted Pack v2 Requests'); ?></strong>
                <div class="muted">
                    <?= sanitize('Pending ' . $assistedCounts['pending'] . ' • Delivered ' . $assistedCounts['delivered'] . ' • Needs input ' . $assistedCounts['needs_input']); ?>
                </div>
                <a class="dash-link" href="/contractor/assisted_v2/requests.php"><?= sanitize('Open'); ?></a>
            </div>

            <div class="card dash-card">
                <div class="dash-number"><?= sanitize((string)($billCounts['draft'] + $billCounts['submitted'])); ?></div>
                <strong><?= sanitize('Bills / Payments Tracker'); ?></strong>
                <div class="muted">
                    <?= sanitize('Draft ' . $billCounts['draft'] . ' • Submitted ' . $billCounts['submitted'] . ' • Pending approval ' . $billCounts['pendingApproval']); ?>
                </div>
                <a class="dash-link" href="/contractor/bills.php"><?= sanitize('Open'); ?></a>
            </div>

            <div class="card dash-card">
                <div class="dash-number"><?= sanitize((string)$pendingDeptLinks); ?></div>
                <strong><?= sanitize('Department Links'); ?></strong>
                <div class="muted"><?= sanitize('Pending approvals from departments.'); ?></div>
                <a class="dash-link" href="/contractor/departments.php?filter=pending"><?= sanitize('Open'); ?></a>
            </div>

            <div class="card dash-card">
                <div class="dash-number">📘</div>
                <strong><?= sanitize('Contractor Guide'); ?></strong>
                <div class="muted"><?= sanitize('Step-by-step workflows for offline tenders and schemes.'); ?></div>
                <a class="dash-link" href="/contractor/guide.php"><?= sanitize('Open'); ?></a>
            </div>
        </div>
        <?php
    });
});
