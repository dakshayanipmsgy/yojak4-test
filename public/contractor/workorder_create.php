<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_workorder_env($yojId);

    $errors = [];
    $titleInput = '';
    $deptInput = '';
    $locationInput = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $titleInput = trim($_POST['title'] ?? '');
        $deptInput = trim($_POST['deptName'] ?? '');
        $locationInput = trim($_POST['projectLocation'] ?? '');

        if ($titleInput === '') {
            $errors[] = 'Title is required.';
        }

        $upload = $_FILES['workorder_pdf'] ?? null;
        $sourceFiles = [];
        $sourceType = 'manual';

        if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $errors[] = 'Upload failed.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? finfo_file($finfo, $upload['tmp_name']) : '';
                if ($finfo) {
                    finfo_close($finfo);
                }
                if ($mime !== 'application/pdf') {
                    $errors[] = 'Only PDF files are allowed.';
                }
                if (($upload['size'] ?? 0) > 10 * 1024 * 1024) {
                    $errors[] = 'PDF too large (max 10MB).';
                }
            }
        }

        if (!$errors) {
            $woId = generate_workorder_id($yojId);
            $uploadDir = workorder_upload_dir($yojId, $woId);
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $target = uniqid('src_', true) . '.pdf';
                $dest = rtrim($uploadDir, '/') . '/' . $target;
                if (!move_uploaded_file($upload['tmp_name'], $dest)) {
                    $errors[] = 'Failed to store PDF.';
                } else {
                    $sourceType = 'uploaded_pdf';
                    $sourceFiles[] = [
                        'name' => basename((string)$upload['name']),
                        'path' => str_replace(PUBLIC_PATH, '', $dest),
                        'sizeBytes' => (int)($upload['size'] ?? 0),
                        'uploadedAt' => now_kolkata()->format(DateTime::ATOM),
                    ];
                }
            }
        }

        if (!$errors) {
            $now = now_kolkata()->format(DateTime::ATOM);
            $workorder = array_merge(workorder_defaults(), [
                'woId' => $woId,
                'yojId' => $yojId,
                'source' => $sourceType,
                'title' => $titleInput,
                'deptName' => $deptInput,
                'projectLocation' => $locationInput,
                'createdAt' => $now,
                'updatedAt' => $now,
                'sourceFiles' => $sourceFiles,
            ]);

            save_workorder($workorder);
            workorder_log([
                'event' => 'workorder_created',
                'yojId' => $yojId,
                'woId' => $woId,
                'source' => $sourceType,
            ]);

            set_flash('success', 'Workorder created. You can now upload PDFs or run AI extraction.');
            redirect('/contractor/workorder_view.php?id=' . urlencode($woId));
        }
    }

    $titlePage = get_app_config()['appName'] . ' | Create Workorder';

    render_layout($titlePage, function () use ($errors, $titleInput, $deptInput, $locationInput) {
        ?>
        <div class="card">
            <h2 style="margin-top:0;"><?= sanitize('Create Workorder'); ?></h2>
            <p class="muted" style="margin:4px 0 12px;"><?= sanitize('Add details and optionally upload a workorder PDF for AI extraction.'); ?></p>
            <?php if ($errors): ?>
                <div class="flashes">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/contractor/workorder_create.php" enctype="multipart/form-data" style="display:grid; gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="title"><?= sanitize('Title'); ?></label>
                    <input id="title" name="title" value="<?= sanitize($titleInput); ?>" required>
                </div>
                <div class="field">
                    <label for="deptName"><?= sanitize('Department/Authority'); ?></label>
                    <input id="deptName" name="deptName" value="<?= sanitize($deptInput); ?>">
                </div>
                <div class="field">
                    <label for="projectLocation"><?= sanitize('Project location'); ?></label>
                    <input id="projectLocation" name="projectLocation" value="<?= sanitize($locationInput); ?>">
                </div>
                <div class="field">
                    <label for="workorder_pdf"><?= sanitize('Upload workorder PDF (optional)'); ?></label>
                    <input id="workorder_pdf" name="workorder_pdf" type="file" accept=".pdf">
                    <small class="muted"><?= sanitize('PDF only, up to 10MB.'); ?></small>
                </div>
                <div class="buttons" style="margin-top:6px;">
                    <button class="btn" type="submit"><?= sanitize('Create'); ?></button>
                    <a class="btn secondary" href="/contractor/workorders.php"><?= sanitize('Back'); ?></a>
                </div>
            </form>
        </div>
        <?php
    });
});
