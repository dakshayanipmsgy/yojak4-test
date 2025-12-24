<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $contractor = load_contractor($user['yojId']);
    if (!$contractor) {
        render_error_page('Contractor not found.');
        return;
    }

    $title = get_app_config()['appName'] . ' | Upload';
    $errors = [];
    $titleInput = '';
    $category = 'Other';
    $tagsInput = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $titleInput = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Other');
        $tagsInput = trim($_POST['tags'] ?? '');

        if ($titleInput === '') {
            $errors[] = 'Title is required.';
        }
        $allowedCategories = ['GST','PAN','ITR','Affidavit','Experience','BalanceSheet','Other'];
        if (!in_array($category, $allowedCategories, true)) {
            $errors[] = 'Invalid category selected.';
        }

        $tags = [];
        if ($tagsInput !== '') {
            foreach (explode(',', $tagsInput) as $tag) {
                $t = trim($tag);
                if ($t === '') {
                    continue;
                }
                if (strlen($t) < 2 || strlen($t) > 20) {
                    $errors[] = 'Tags must be between 2 and 20 characters.';
                    break;
                }
                $tags[] = $t;
            }
            $tags = array_values(array_unique($tags));
            if (count($tags) > 10) {
                $errors[] = 'Maximum 10 tags allowed.';
            }
        }

        if (!isset($_FILES['document']) || ($_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Please select a file to upload.';
        }

        if (!$errors && isset($_FILES['document'])) {
            $file = $_FILES['document'];
            $maxSize = 10 * 1024 * 1024;
            if (($file['size'] ?? 0) > $maxSize) {
                $errors[] = 'File too large. Max 10MB allowed.';
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            $allowed = allowed_vault_mimes();
            if (!isset($allowed[$mime])) {
                $errors[] = 'Unsupported file type.';
            } else {
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($extension !== $allowed[$mime]) {
                    $errors[] = 'File extension does not match the detected file type.';
                }
            }
        }

        if (!$errors) {
            $fileId = generate_vault_file_id();
            $ext = allowed_vault_mimes()[$mime];
            ensure_contractor_upload_dir($contractor['yojId']);
            $destination = contractor_upload_dir($contractor['yojId']) . '/' . $fileId . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $errors[] = 'Failed to store the uploaded file.';
            } else {
                $storedPath = str_replace(PUBLIC_PATH, '', $destination);
                $record = [
                    'fileId' => $fileId,
                    'title' => $titleInput,
                    'category' => $category,
                    'tags' => $tags,
                    'storedPath' => $storedPath,
                    'mime' => $mime,
                    'sizeBytes' => (int)$file['size'],
                    'uploadedAt' => now_kolkata()->format(DateTime::ATOM),
                    'deletedAt' => null,
                ];

                $index = contractor_vault_index($contractor['yojId']);
                $index[] = $record;
                save_contractor_vault_index($contractor['yojId'], $index);

                $fileDir = ensure_vault_file_dir($contractor['yojId'], $fileId);
                $meta = $record;
                $meta['notes'] = '';
                $meta['source'] = 'uploaded';
                writeJsonAtomic($fileDir . '/meta.json', $meta);

                logEvent(DATA_PATH . '/logs/uploads.log', [
                    'event' => 'contractor_upload',
                    'fileId' => $fileId,
                    'yojId' => $contractor['yojId'],
                    'mime' => $mime,
                    'sizeBytes' => (int)$file['size'],
                ]);

                set_flash('success', 'File uploaded to vault.');
                redirect('/contractor/vault.php');
            }
        }
    }

    render_layout($title, function () use ($errors, $titleInput, $category, $tagsInput) {
        ?>
        <div class="card">
            <h2><?= sanitize('Upload Document'); ?></h2>
            <p class="muted"><?= sanitize('PDF, JPG, or PNG up to 10MB.'); ?></p>
            <?php if ($errors): ?>
                <div class="flashes">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/contractor/vault_upload.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="title"><?= sanitize('Title'); ?></label>
                    <input id="title" name="title" value="<?= sanitize($titleInput); ?>" required>
                </div>
                <div class="field">
                    <label for="category"><?= sanitize('Category'); ?></label>
                    <select id="category" name="category">
                        <?php foreach (['GST','PAN','ITR','Affidavit','Experience','BalanceSheet','Other'] as $cat): ?>
                            <option value="<?= sanitize($cat); ?>" <?= $cat === $category ? 'selected' : ''; ?>><?= sanitize($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="tags"><?= sanitize('Tags (comma separated)'); ?></label>
                    <input id="tags" name="tags" value="<?= sanitize($tagsInput); ?>" placeholder="license, fy2023">
                </div>
                <div class="field">
                    <label for="document"><?= sanitize('Choose file'); ?></label>
                    <input id="document" name="document" type="file" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
                <div class="buttons">
                    <button class="btn" type="submit"><?= sanitize('Upload'); ?></button>
                    <a class="btn secondary" href="/contractor/vault.php"><?= sanitize('Back'); ?></a>
                </div>
            </form>
        </div>
        <?php
    });
});
