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
    require_department_permission($user, 'manage_tenders');

    $errors = [];
    $requirementSets = load_requirement_sets($deptId);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $title = trim($_POST['title'] ?? '');
        $publish = trim($_POST['publish'] ?? '');
        $submission = trim($_POST['submission'] ?? '');
        $opening = trim($_POST['opening'] ?? '');
        $completionMonths = trim($_POST['completionMonths'] ?? '');
        $paymentSteps = array_filter(array_map('trim', preg_split('/\r?\n/', (string)($_POST['paymentSteps'] ?? '')) ?: []));
        $emdText = trim($_POST['emdText'] ?? '');
        $sdPercent = trim($_POST['sdPercent'] ?? '');
        $pgPercent = trim($_POST['pgPercent'] ?? '');
        $reqId = trim($_POST['requirementSetId'] ?? '');
        $publishedToContractors = isset($_POST['publishedToContractors']) && $_POST['publishedToContractors'] === 'on';
        $titlePublic = trim($_POST['titlePublic'] ?? '');
        $summaryPublic = trim($_POST['summaryPublic'] ?? '');

        if ($title === '') {
            $errors[] = 'Title required.';
        }

        $validReq = null;
        foreach ($requirementSets as $set) {
            if (($set['setId'] ?? '') === $reqId) {
                $validReq = $set['setId'];
                break;
            }
        }

        if (!$errors) {
            $contractorSummary = [
                'titlePublic' => $titlePublic !== '' ? $titlePublic : null,
                'summaryPublic' => $summaryPublic,
                'attachmentsPublic' => [],
            ];

            $tender = [
                'id' => '', // auto fill
                'title' => $title,
                'tenderNumberFormat' => [
                    'prefix' => trim($_POST['prefix'] ?? 'YTD-'),
                    'sequence' => max(1, (int)($_POST['sequence'] ?? 1)),
                    'postfix' => trim($_POST['postfix'] ?? ''),
                ],
                'dates' => [
                    'publish' => $publish,
                    'submission' => $submission,
                    'opening' => $opening,
                ],
                'completionMonths' => $completionMonths !== '' ? (int)$completionMonths : null,
                'paymentSteps' => array_values($paymentSteps),
                'emdText' => $emdText,
                'sdPercent' => $sdPercent,
                'pgPercent' => $pgPercent,
                'requirementSetId' => $validReq,
                'contractorVisibleSummary' => $contractorSummary,
                'createdAt' => now_kolkata()->format(DateTime::ATOM),
                'updatedAt' => now_kolkata()->format(DateTime::ATOM),
                'status' => 'draft',
                'publishedToContractors' => $publishedToContractors,
                'publishedAt' => $publishedToContractors ? now_kolkata()->format(DateTime::ATOM) : null,
            ];
            save_department_tender($deptId, $tender);
            if ($publishedToContractors) {
                $attachments = save_public_attachments($deptId, $tender['id'], $_FILES['publicAttachments'] ?? [], []);
                $tender['contractorVisibleSummary']['attachmentsPublic'] = $attachments;
                save_department_tender($deptId, $tender);
                write_public_tender_snapshot(load_department($deptId) ?? ['deptId' => $deptId], $tender, $requirementSets, $attachments);
                logEvent(DATA_PATH . '/logs/tenders_publication.log', [
                    'event' => 'tender_published',
                    'deptId' => $deptId,
                    'ytdId' => $tender['id'],
                    'actor' => $user['username'] ?? '',
                ]);
            }
            append_department_audit($deptId, [
                'by' => $user['username'] ?? '',
                'action' => 'tender_created',
                'meta' => ['title' => $title],
            ]);
            set_flash('success', 'Tender saved.');
            redirect('/department/tenders.php');
        }
    }

    $title = get_app_config()['appName'] . ' | Create Tender';
    render_layout($title, function () use ($errors, $requirementSets) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Create Tender'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('YTD auto generated with department isolation.'); ?></p>
                </div>
                <a class="btn secondary" href="/department/tenders.php"><?= sanitize('Back'); ?></a>
            </div>
            <?php if ($errors): ?>
                <div class="flashes" style="margin-top:12px;">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" style="margin-top:12px;display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="title"><?= sanitize('Title'); ?></label>
                    <input id="title" name="title" required>
                </div>
                <div class="field">
                    <label><?= sanitize('Tender Number Format'); ?></label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <input name="prefix" placeholder="Prefix" style="flex:1;min-width:120px;">
                        <input name="sequence" type="number" min="1" value="1" style="flex:1;min-width:120px;">
                        <input name="postfix" placeholder="Postfix" style="flex:1;min-width:120px;">
                    </div>
                </div>
                <div class="field">
                    <label><?= sanitize('Dates'); ?></label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px;">
                        <input type="date" name="publish" placeholder="Publish date">
                        <input type="datetime-local" name="submission" placeholder="Submission deadline">
                        <input type="datetime-local" name="opening" placeholder="Opening date">
                    </div>
                </div>
                <div class="field">
                    <label for="completionMonths"><?= sanitize('Completion Months'); ?></label>
                    <input id="completionMonths" name="completionMonths" type="number" min="0">
                </div>
                <div class="field">
                    <label for="paymentSteps"><?= sanitize('Payment Steps (one per line)'); ?></label>
                    <textarea id="paymentSteps" name="paymentSteps" rows="3" style="width:100%;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:10px;padding:10px;"></textarea>
                </div>
                <div class="field">
                    <label for="emdText"><?= sanitize('EMD Text'); ?></label>
                    <input id="emdText" name="emdText">
                </div>
                <div class="field" style="display:flex;gap:8px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:150px;">
                        <label for="sdPercent"><?= sanitize('SD %'); ?></label>
                        <input id="sdPercent" name="sdPercent">
                    </div>
                    <div style="flex:1;min-width:150px;">
                        <label for="pgPercent"><?= sanitize('PG %'); ?></label>
                        <input id="pgPercent" name="pgPercent">
                    </div>
                </div>
                <div class="card" style="background:var(--surface-2);border:1px solid var(--primary);display:grid;gap:10px;">
                    <h3 style="margin:0;"><?= sanitize('Contractor Visibility'); ?></h3>
                    <div class="field" style="display:flex;align-items:center;gap:10px;">
                        <input type="checkbox" id="publishedToContractors" name="publishedToContractors" style="width:auto;">
                        <label for="publishedToContractors" style="margin:0;"><?= sanitize('Publish to contractors (visible even without linking)'); ?></label>
                    </div>
                    <div class="field">
                        <label for="titlePublic"><?= sanitize('Public Title (optional override)'); ?></label>
                        <input id="titlePublic" name="titlePublic" placeholder="If empty, use tender title">
                    </div>
                    <div class="field">
                        <label for="summaryPublic"><?= sanitize('Public Summary (contractor safe)'); ?></label>
                        <textarea id="summaryPublic" name="summaryPublic" rows="3" style="width:100%;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:10px;padding:10px;"></textarea>
                    </div>
                    <div class="field">
                        <label for="requirementSetId"><?= sanitize('Official Requirement Set'); ?></label>
                        <select id="requirementSetId" name="requirementSetId">
                            <option value=""><?= sanitize('None'); ?></option>
                            <?php foreach ($requirementSets as $set): ?>
                                <option value="<?= sanitize($set['setId'] ?? ''); ?>"><?= sanitize(($set['name'] ?? $set['title'] ?? '') ?: ($set['setId'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="muted" style="margin:4px 0 0;"><?= sanitize('Linked contractors can auto-apply this checklist.'); ?></p>
                    </div>
                    <div class="field">
                        <label for="publicAttachments"><?= sanitize('Public Attachments (PDF/JPG/PNG)'); ?></label>
                        <input id="publicAttachments" type="file" name="publicAttachments[]" multiple accept=".pdf,.jpg,.jpeg,.png">
                        <p class="muted" style="margin:4px 0 0;"><?= sanitize('Uploaded files are shared with contractors via a safe download link.'); ?></p>
                    </div>
                </div>
                <button class="btn" type="submit"><?= sanitize('Save Tender'); ?></button>
            </form>
        </div>
        <?php
    });
});
