<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_tender_archive_env($yojId);

    $errors = [];
    $titleInput = '';
    $yearInput = '';
    $departmentInput = '';
    $outcomeInput = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $titleInput = trim($_POST['title'] ?? '');
        $yearInput = trim($_POST['year'] ?? '');
        $departmentInput = trim($_POST['departmentName'] ?? '');
        $outcomeInput = normalize_archive_outcome(trim($_POST['outcome'] ?? ''));

        $year = normalize_archive_year($yearInput);
        if ($yearInput !== '' && $year === null) {
            $errors[] = 'Year must be between 2000 and current year.';
        }

        $files = $_FILES['documents'] ?? null;
        $maxTotal = 25 * 1024 * 1024;
        $totalSize = 0;
        $uploads = [];

        if (!$files || !isset($files['name']) || !is_array($files['name'])) {
            $errors[] = 'Please upload at least one PDF.';
        } else {
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }
                $totalSize += (int)$files['size'][$i];
                $tmp = $files['tmp_name'][$i];
                $originalName = basename((string)$files['name'][$i]);
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? finfo_file($finfo, $tmp) : '';
                if ($finfo) {
                    finfo_close($finfo);
                }
                if ($mime !== 'application/pdf') {
                    $errors[] = 'Only PDF files are allowed: ' . $originalName;
                    break;
                }
                $uploads[] = [
                    'tmp' => $tmp,
                    'name' => $originalName,
                    'size' => (int)$files['size'][$i],
                ];
            }
            if ($totalSize <= 0) {
                $errors[] = 'Please upload at least one PDF.';
            }
            if ($totalSize > $maxTotal) {
                $errors[] = 'Total upload size exceeds 25MB.';
            }
        }

        if (!$errors && $uploads) {
            $archId = generate_archtd_id($yojId);
            $uploadDir = tender_archive_upload_dir($yojId, $archId);
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $stored = [];
            foreach ($uploads as $file) {
                $targetName = uniqid('src_', true) . '.pdf';
                $destination = rtrim($uploadDir, '/') . '/' . $targetName;
                if (!move_uploaded_file($file['tmp'], $destination)) {
                    $errors[] = 'Failed to store ' . $file['name'];
                    break;
                }
                $stored[] = [
                    'name' => $file['name'],
                    'path' => str_replace(PUBLIC_PATH, '', $destination),
                    'sizeBytes' => $file['size'],
                    'uploadedAt' => now_kolkata()->format(DateTime::ATOM),
                ];
            }

            if (!$errors && $stored) {
                $now = now_kolkata()->format(DateTime::ATOM);
                $archive = [
                    'yojId' => $yojId,
                    'id' => $archId,
                    'title' => $titleInput !== '' ? $titleInput : ($stored[0]['name'] ?? 'Old Tender'),
                    'year' => $year,
                    'departmentName' => $departmentInput,
                    'outcome' => $outcomeInput,
                    'sourceFiles' => $stored,
                    'aiSummary' => tender_archive_ai_defaults(),
                    'createdAt' => $now,
                    'updatedAt' => $now,
                    'deletedAt' => null,
                ];

                save_tender_archive($archive);
                tender_archive_log([
                    'event' => 'archive_created',
                    'yojId' => $yojId,
                    'archId' => $archId,
                    'sourceFileCount' => count($stored),
                ]);

                set_flash('success', 'Archive created. You can now view details or run AI summary.');
                redirect('/contractor/tender_archive_view.php?id=' . urlencode($archId));
            }
        }
    }

    $titlePage = get_app_config()['appName'] . ' | Add Archive';
    $currentYear = (int)now_kolkata()->format('Y');

    render_layout($titlePage, function () use ($errors, $titleInput, $yearInput, $departmentInput, $outcomeInput, $currentYear) {
        ?>
        <div class="card">
            <h2 style="margin-top:0;"><?= sanitize('Add archived tender'); ?></h2>
            <p class="muted" style="margin:4px 0 12px;"><?= sanitize('Upload past tender PDFs, record metadata, and keep reusable learnings.'); ?></p>
            <?php if ($errors): ?>
                <div class="flashes">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/contractor/tender_archive_add.php" enctype="multipart/form-data" style="display:grid; gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="title"><?= sanitize('Tender title'); ?></label>
                    <input id="title" name="title" value="<?= sanitize($titleInput); ?>" placeholder="<?= sanitize('Required'); ?>" required>
                </div>
                <div class="field">
                    <label for="year"><?= sanitize('Year'); ?></label>
                    <input id="year" name="year" type="number" min="2000" max="<?= $currentYear; ?>" value="<?= sanitize($yearInput); ?>" placeholder="<?= sanitize('2000-' . $currentYear); ?>">
                </div>
                <div class="field">
                    <label for="departmentName"><?= sanitize('Department/authority name (optional)'); ?></label>
                    <input id="departmentName" name="departmentName" value="<?= sanitize($departmentInput); ?>">
                </div>
                <div class="field">
                    <label for="outcome"><?= sanitize('Outcome'); ?></label>
                    <select id="outcome" name="outcome">
                        <option value=""><?= sanitize('Select outcome'); ?></option>
                        <option value="participated" <?= $outcomeInput === 'participated' ? 'selected' : ''; ?>><?= sanitize('Participated'); ?></option>
                        <option value="won" <?= $outcomeInput === 'won' ? 'selected' : ''; ?>><?= sanitize('Won'); ?></option>
                        <option value="lost" <?= $outcomeInput === 'lost' ? 'selected' : ''; ?>><?= sanitize('Lost'); ?></option>
                    </select>
                </div>
                <div class="field">
                    <label for="documents"><?= sanitize('Upload PDFs'); ?></label>
                    <input id="documents" name="documents[]" type="file" accept=".pdf" multiple required>
                    <small class="muted"><?= sanitize('PDF only, up to 25MB total.'); ?></small>
                </div>
                <div class="buttons" style="margin-top:6px;">
                    <button class="btn" type="submit"><?= sanitize('Create'); ?></button>
                    <a class="btn secondary" href="/contractor/tender_archive.php"><?= sanitize('Back'); ?></a>
                </div>
            </form>
        </div>
        <?php
    });
});
