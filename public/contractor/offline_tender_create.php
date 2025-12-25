<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_offline_tender_env($yojId);

    $errors = [];
    $titleInput = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $titleInput = trim($_POST['title'] ?? '');
        $files = $_FILES['documents'] ?? null;

        if (!$files || !isset($files['name']) || !is_array($files['name'])) {
            $errors[] = 'Please upload at least one PDF.';
        }

        $maxTotal = 25 * 1024 * 1024;
        $totalSize = 0;
        $uploads = [];

        if (!$errors && $files) {
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
            $offtdId = generate_offtd_id($yojId);
            $uploadDir = offline_tender_upload_dir($yojId, $offtdId);
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
        }

        if (!$errors && $stored) {
            $title = $titleInput !== '' ? $titleInput : ($stored[0]['name'] ?? 'Offline Tender');
            $now = now_kolkata()->format(DateTime::ATOM);
            $tender = [
                'yojId' => $yojId,
                'id' => $offtdId,
                'title' => $title,
                'status' => 'draft',
                'createdAt' => $now,
                'updatedAt' => $now,
                'sourceFiles' => $stored,
                'ai' => [
                    'lastRunAt' => null,
                    'rawText' => '',
                    'parsedOk' => false,
                    'errors' => [],
                ],
                'extracted' => offline_tender_defaults(),
                'checklist' => [],
                'deletedAt' => null,
            ];

            save_offline_tender($tender);

            set_flash('success', 'Offline tender created. You can now run AI extraction or edit manually.');
            redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        }
    }

    $titlePage = get_app_config()['appName'] . ' | Create Offline Tender';

    render_layout($titlePage, function () use ($errors, $titleInput) {
        ?>
        <div class="card">
            <h2 style="margin-top:0;"><?= sanitize('Create Offline Tender'); ?></h2>
            <p class="muted" style="margin:4px 0 12px;"><?= sanitize('Upload tender PDFs (max 25MB total). AI will help extract deadlines and checklist items.'); ?></p>
            <?php if ($errors): ?>
                <div class="flashes">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/contractor/offline_tender_create.php" enctype="multipart/form-data" style="display:grid; gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="title"><?= sanitize('Tender title (optional)'); ?></label>
                    <input id="title" name="title" value="<?= sanitize($titleInput); ?>" placeholder="<?= sanitize('Will default to filename'); ?>">
                </div>
                <div class="field">
                    <label for="documents"><?= sanitize('Upload PDFs'); ?></label>
                    <input id="documents" name="documents[]" type="file" accept=".pdf" multiple required>
                    <small class="muted"><?= sanitize('PDF only, up to 25MB total.'); ?></small>
                </div>
                <div class="buttons" style="margin-top:6px;">
                    <button class="btn" type="submit"><?= sanitize('Create'); ?></button>
                    <a class="btn secondary" href="/contractor/offline_tenders.php"><?= sanitize('Back'); ?></a>
                </div>
            </form>
        </div>
        <?php
    });
});
