<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/tender_archive.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_tender_archive_env($yojId);

    $archId = trim($_POST['id'] ?? '');
    $archive = $archId !== '' ? load_tender_archive($yojId, $archId) : null;
    if (!$archive || ($archive['yojId'] ?? '') !== $yojId) {
        render_error_page('Archive not found.');
        return;
    }

    $errors = [];
    $title = trim($_POST['title'] ?? '');
    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    $yearInput = trim($_POST['year'] ?? '');
    $year = normalize_archive_year($yearInput);
    if ($yearInput !== '' && $year === null) {
        $errors[] = 'Year must be between 2000 and current year.';
    }

    $department = trim($_POST['departmentName'] ?? '');
    $outcome = normalize_archive_outcome(trim($_POST['outcome'] ?? ''));

    $summaryText = trim($_POST['summaryText'] ?? '');
    $keyLearningsRaw = trim($_POST['keyLearnings'] ?? '');
    $keyLearnings = $keyLearningsRaw === '' ? [] : normalize_archive_learnings($keyLearningsRaw);

    $existingChecklist = $_POST['suggestedChecklist'] ?? [];
    $removeIds = $_POST['removeChecklist'] ?? [];
    $newChecklistInput = $_POST['newChecklist'] ?? [];

    $checklistItems = [];
    foreach ($existingChecklist as $idx => $item) {
        if (in_array((string)$idx, array_map('strval', $removeIds), true)) {
            continue;
        }
        $checklistItems[] = [
            'title' => trim((string)($item['title'] ?? '')),
            'description' => trim((string)($item['description'] ?? '')),
            'required' => !empty($item['required']),
        ];
    }
    foreach ($newChecklistInput as $item) {
        $titleItem = trim((string)($item['title'] ?? ''));
        if ($titleItem === '') {
            continue;
        }
        $checklistItems[] = [
            'title' => $titleItem,
            'description' => trim((string)($item['description'] ?? '')),
            'required' => !empty($item['required']),
        ];
        if (count($checklistItems) >= 200) {
            break;
        }
    }
    $checklistItems = normalize_archive_checklist($checklistItems);

    $files = $_FILES['documents'] ?? null;
    $newFiles = [];
    if ($files && isset($files['name']) && is_array($files['name'])) {
        $maxTotal = 25 * 1024 * 1024;
        $totalSize = 0;
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
            $newFiles[] = [
                'tmp' => $tmp,
                'name' => $originalName,
                'size' => (int)$files['size'][$i],
            ];
        }
        if ($totalSize > $maxTotal) {
            $errors[] = 'New upload batch exceeds 25MB.';
        }
    }

    if (!$errors) {
        $uploadDir = tender_archive_upload_dir($yojId, $archId);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        foreach ($newFiles as $file) {
            $targetName = uniqid('src_', true) . '.pdf';
            $destination = rtrim($uploadDir, '/') . '/' . $targetName;
            if (!move_uploaded_file($file['tmp'], $destination)) {
                $errors[] = 'Failed to store ' . $file['name'];
                break;
            }
            $archive['sourceFiles'][] = [
                'name' => $file['name'],
                'path' => str_replace(PUBLIC_PATH, '', $destination),
                'sizeBytes' => $file['size'],
                'uploadedAt' => now_kolkata()->format(DateTime::ATOM),
            ];
        }
    }

    if ($errors) {
        foreach ($errors as $error) {
            set_flash('error', $error);
        }
        redirect('/contractor/tender_archive_view.php?id=' . urlencode($archId));
        return;
    }

    $archive['title'] = $title;
    $archive['year'] = $year;
    $archive['departmentName'] = $department;
    $archive['outcome'] = $outcome;

    $archive['aiSummary'] = array_merge(tender_archive_ai_defaults(), $archive['aiSummary'] ?? []);
    $archive['aiSummary']['summaryText'] = $summaryText;
    $archive['aiSummary']['keyLearnings'] = $keyLearnings;
    $archive['aiSummary']['suggestedChecklist'] = $checklistItems;
    $archive['updatedAt'] = now_kolkata()->format(DateTime::ATOM);

    save_tender_archive($archive);
    tender_archive_log([
        'event' => 'archive_updated',
        'yojId' => $yojId,
        'archId' => $archId,
        'fileCount' => count($archive['sourceFiles'] ?? []),
    ]);

    set_flash('success', 'Archive updated successfully.');
    redirect('/contractor/tender_archive_view.php?id=' . urlencode($archId));
});
