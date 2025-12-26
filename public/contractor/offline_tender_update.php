<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/offline_tenders.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_offline_tender_env($yojId);

    $offtdId = trim($_POST['id'] ?? '');
    $mode = trim($_POST['mode'] ?? 'save');
    $tender = $offtdId !== '' ? load_offline_tender($yojId, $offtdId) : null;

    if (!$tender || ($tender['yojId'] ?? '') !== $yojId) {
        render_error_page('Tender not found.');
        return;
    }

    $errors = [];

    if ($mode === 'apply_ai') {
        $ai = $tender['ai'] ?? [];
        $candidateExtracted = $ai['candidateExtracted'] ?? null;
        if (empty($ai['parsedOk']) || !is_array($candidateExtracted)) {
            set_flash('error', 'No AI extraction is ready to apply. Please run AI again.');
            redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
            return;
        }
        $tender['extracted'] = $candidateExtracted;
        if (isset($ai['candidateChecklist']) && is_array($ai['candidateChecklist'])) {
            $tender['checklist'] = $ai['candidateChecklist'];
        }
        $tender['status'] = 'ai_extracted';
        $tender['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
        save_offline_tender($tender);
        set_flash('success', 'AI extracted fields applied. You can still edit them below.');
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        return;
    }

    if ($mode === 'upload') {
        $files = $_FILES['additional_documents'] ?? null;
        if (!$files || !isset($files['name']) || !is_array($files['name'])) {
            set_flash('error', 'Select PDF files to upload.');
            redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        }

        $maxTotal = 25 * 1024 * 1024;
        $total = 0;
        $uploads = [];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $total += (int)$files['size'][$i];
            $tmp = $files['tmp_name'][$i];
            $name = basename((string)$files['name'][$i]);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $tmp) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            if ($mime !== 'application/pdf') {
                $errors[] = 'Only PDF files allowed: ' . $name;
                break;
            }
            $uploads[] = ['tmp' => $tmp, 'name' => $name, 'size' => (int)$files['size'][$i]];
        }
        if ($total > $maxTotal) {
            $errors[] = 'Total upload size exceeds 25MB.';
        }
        if (!$uploads) {
            $errors[] = 'No files to upload.';
        }

        if (!$errors) {
            $uploadDir = offline_tender_upload_dir($yojId, $offtdId);
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            foreach ($uploads as $file) {
                $target = uniqid('src_', true) . '.pdf';
                $dest = rtrim($uploadDir, '/') . '/' . $target;
                if (!move_uploaded_file($file['tmp'], $dest)) {
                    $errors[] = 'Failed to store ' . $file['name'];
                    break;
                }
                $tender['sourceFiles'][] = [
                    'name' => $file['name'],
                    'path' => str_replace(PUBLIC_PATH, '', $dest),
                    'sizeBytes' => $file['size'],
                    'uploadedAt' => now_kolkata()->format(DateTime::ATOM),
                ];
            }
        }

        if ($errors) {
            set_flash('error', implode(' ', $errors));
        } else {
            $tender['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
            save_offline_tender($tender);
            set_flash('success', 'PDFs added to tender.');
        }

        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        return;
    }

    $checklist = [];
    $existingInput = $_POST['checklist'] ?? [];
    foreach ($existingInput as $itemId => $payload) {
        $titleText = trim((string)($payload['title'] ?? ''));
        if ($titleText === '') {
            continue;
        }
        $checklist[] = [
            'itemId' => $payload['itemId'] ?? $itemId,
            'title' => $titleText,
            'description' => trim((string)($payload['description'] ?? '')),
            'required' => !empty($payload['required']),
            'status' => in_array($payload['status'] ?? '', ['pending','uploaded','done'], true) ? $payload['status'] : 'pending',
            'source' => $payload['source'] ?? 'manual',
        ];
    }

    $removeIds = $_POST['checklist_remove'] ?? [];
    if (is_array($removeIds) && $removeIds) {
        $checklist = array_values(array_filter($checklist, function ($item) use ($removeIds) {
            return !in_array($item['itemId'] ?? '', $removeIds, true);
        }));
    }

    $newItems = $_POST['new_checklist'] ?? [];
    foreach ($newItems as $new) {
        $titleText = trim((string)($new['title'] ?? ''));
        if ($titleText === '') {
            continue;
        }
        $checklist[] = [
            'itemId' => 'CHK-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)),
            'title' => $titleText,
            'description' => trim((string)($new['description'] ?? '')),
            'required' => !empty($new['required']),
            'status' => 'pending',
            'source' => 'manual',
        ];
        if (count($checklist) >= 200) {
            break;
        }
    }

    if ($mode === 'save_checklist') {
        if (count($checklist) > 200) {
            $errors[] = 'Checklist limit reached (200 items max).';
        }

        if ($errors) {
            set_flash('error', implode(' ', $errors));
            redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
            return;
        }

        $tender['checklist'] = $checklist;
        $tender['status'] = 'editing';
        $tender['updatedAt'] = now_kolkata()->format(DateTime::ATOM);

        save_offline_tender($tender);
        set_flash('success', 'Checklist saved.');
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        return;
    }

    $title = trim($_POST['title'] ?? ($tender['title'] ?? ''));
    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    $publishDate = trim($_POST['publishDate'] ?? '');
    $submissionDeadline = trim($_POST['submissionDeadline'] ?? '');
    $openingDate = trim($_POST['openingDate'] ?? '');
    $feesInput = $_POST['fees'] ?? [];

    $extracted = offline_tender_defaults();
    $extracted['publishDate'] = $publishDate !== '' ? $publishDate : null;
    $extracted['submissionDeadline'] = $submissionDeadline !== '' ? $submissionDeadline : null;
    $extracted['openingDate'] = $openingDate !== '' ? $openingDate : null;
    $extracted['fees'] = [
        'tenderFee' => trim((string)($feesInput['tenderFee'] ?? '')),
        'emd' => trim((string)($feesInput['emd'] ?? '')),
        'other' => trim((string)($feesInput['other'] ?? '')),
    ];

    $completionMonths = trim($_POST['completionMonths'] ?? '');
    if ($completionMonths !== '' && !ctype_digit((string)$completionMonths)) {
        $errors[] = 'Completion months must be a number.';
    } else {
        $extracted['completionMonths'] = $completionMonths === '' ? null : (int)$completionMonths;
    }

    $bidValidityDays = trim($_POST['bidValidityDays'] ?? '');
    if ($bidValidityDays !== '' && !ctype_digit((string)$bidValidityDays)) {
        $errors[] = 'Bid validity days must be a number.';
    } else {
        $extracted['bidValidityDays'] = $bidValidityDays === '' ? null : (int)$bidValidityDays;
    }

    $extracted['eligibilityDocs'] = normalize_string_list($_POST['eligibilityDocs'] ?? []);
    $extracted['annexures'] = normalize_string_list($_POST['annexures'] ?? []);
    $extracted['formats'] = normalize_formats($_POST['formats'] ?? []);

    if (!$existingInput && !$newItems && empty($removeIds)) {
        $checklist = $tender['checklist'] ?? [];
    }

    if (count($checklist) > 200) {
        $errors[] = 'Checklist limit reached (200 items max).';
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
        return;
    }

    $tender['title'] = $title;
    $tender['extracted'] = $extracted;
    $tender['checklist'] = $checklist;
    $tender['status'] = 'editing';
    $tender['updatedAt'] = now_kolkata()->format(DateTime::ATOM);

    save_offline_tender($tender);

    set_flash('success', 'Tender updated.');
    redirect('/contractor/offline_tender_view.php?id=' . urlencode($offtdId));
});
