<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');

    $date = $_GET['date'] ?? (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
    $levelFilter = $_GET['level'] ?? '';
    $pathFilter = $_GET['path'] ?? '';
    $userTypeFilter = $_GET['userType'] ?? '';

    $logPath = DATA_PATH . '/logs/runtime_errors/' . $date . '.jsonl';
    $entries = [];
    if (file_exists($logPath)) {
        $handle = fopen($logPath, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $decoded = json_decode(trim($line), true);
                if (is_array($decoded)) {
                    $entries[] = $decoded;
                }
            }
            fclose($handle);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $reference = $_POST['reference'] ?? '';
        $target = null;
        foreach ($entries as $entry) {
            if (($entry['reference'] ?? '') === $reference) {
                $target = $entry;
                break;
            }
        }
        if ($target) {
            $ticketId = support_create_ticket_from_error($target);
            set_flash('success', 'Created ticket ' . $ticketId);
            redirect('/superadmin/support_ticket.php?ticketId=' . urlencode($ticketId));
        }
        set_flash('error', 'Error reference not found');
        redirect('/superadmin/error_log.php?date=' . urlencode($date));
    }

    $filtered = array_values(array_filter($entries, function ($entry) use ($levelFilter, $pathFilter, $userTypeFilter) {
        if ($levelFilter && ($entry['level'] ?? '') !== $levelFilter) {
            return false;
        }
        if ($userTypeFilter && ($entry['user']['userType'] ?? '') !== $userTypeFilter) {
            return false;
        }
        if ($pathFilter && stripos((string)($entry['request']['path'] ?? ''), $pathFilter) === false) {
            return false;
        }
        return true;
    }));

    $title = get_app_config()['appName'] . ' | Error Log';
    render_layout($title, function () use ($date, $levelFilter, $pathFilter, $userTypeFilter, $filtered) {
        ?>
        <div class="card">
            <h2 style="margin-bottom:6px;">Runtime Errors</h2>
            <p class="muted" style="margin-top:0;">Captured PHP warnings, fatals, and exceptions.</p>
            <form method="get" style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));align-items:end;">
                <div class="field">
                    <label>Date</label>
                    <input type="date" name="date" value="<?= sanitize($date); ?>">
                </div>
                <div class="field">
                    <label>Level</label>
                    <select name="level">
                        <option value="">All</option>
                        <?php foreach (['warning','notice','error','fatal','exception'] as $level): ?>
                            <option value="<?= sanitize($level); ?>" <?= $levelFilter === $level ? 'selected' : ''; ?>><?= sanitize(ucfirst($level)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Path contains</label>
                    <input type="text" name="path" value="<?= sanitize($pathFilter); ?>" placeholder="/contractor/">
                </div>
                <div class="field">
                    <label>User Type</label>
                    <select name="userType">
                        <option value="">All</option>
                        <?php foreach (['contractor','department','superadmin'] as $u): ?>
                            <option value="<?= sanitize($u); ?>" <?= $userTypeFilter === $u ? 'selected' : ''; ?>><?= sanitize(ucfirst($u)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn">Filter</button>
                </div>
            </form>
        </div>
        <div class="card">
            <h3 style="margin-top:0;">Entries (<?= count($filtered); ?>)</h3>
            <?php if (!$filtered): ?>
                <p class="muted">No entries for this filter.</p>
            <?php endif; ?>
            <?php foreach ($filtered as $entry): ?>
                <div class="card" style="background:var(--surface-2);margin-bottom:10px;border:1px solid var(--border);">
                    <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;align-items:center;">
                        <div>
                            <strong><?= sanitize($entry['reference'] ?? ''); ?></strong>
                            <span class="pill"><?= sanitize(ucfirst($entry['level'] ?? '')); ?></span>
                            <span class="pill"><?= sanitize($entry['at'] ?? ''); ?></span>
                        </div>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <input type="hidden" name="reference" value="<?= sanitize($entry['reference'] ?? ''); ?>">
                            <button type="submit" class="btn secondary">Create Support Ticket</button>
                        </form>
                    </div>
                    <p style="margin:6px 0;white-space:pre-wrap;">Message: <?= sanitize($entry['message'] ?? ''); ?></p>
                    <p class="muted" style="margin:0;">File: <?= sanitize($entry['file'] ?? ''); ?> : <?= sanitize((string)($entry['line'] ?? '')); ?></p>
                    <p class="muted" style="margin:0;">Path: <?= sanitize($entry['request']['path'] ?? ''); ?> | Method: <?= sanitize($entry['request']['method'] ?? ''); ?></p>
                    <p class="muted" style="margin:0;">User: <?= sanitize($entry['user']['userType'] ?? ''); ?> | IP: <?= sanitize($entry['ipMasked'] ?? ''); ?></p>
                    <?php if (!empty($entry['traceSnippet'])): ?>
                        <details style="margin-top:6px;">
                            <summary>Trace</summary>
                            <pre style="white-space:pre-wrap;"><?= sanitize($entry['traceSnippet']); ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    });
});
