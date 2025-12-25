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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $title = trim($_POST['title'] ?? '');
        $workorderRef = trim($_POST['workorderRef'] ?? '');
        $amountText = trim($_POST['amountText'] ?? '');

        if ($title === '') {
            set_flash('error', 'Title is required.');
            redirect('/contractor/bill_create.php');
        }
        if (mb_strlen($amountText) > 30) {
            set_flash('error', 'Amount text must be 30 characters or less.');
            redirect('/contractor/bill_create.php');
        }

        $bill = create_contractor_bill($contractor['yojId'], $title, $workorderRef, $amountText);
        set_flash('success', 'Bill created.');
        redirect('/contractor/bill_view.php?id=' . urlencode($bill['billId']));
    }

    $title = get_app_config()['appName'] . ' | Create Bill';

    render_layout($title, function () {
        ?>
        <div class="card">
            <h2 style="margin-top:0;"><?= sanitize('Create Bill'); ?></h2>
            <p class="muted" style="margin:4px 0 12px;"><?= sanitize('Submit your invoice details and manage its lifecycle.'); ?></p>
            <form method="post" action="/contractor/bill_create.php" style="display:grid; gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label><?= sanitize('Title'); ?></label>
                    <input name="title" required maxlength="120" placeholder="<?= sanitize('e.g. Stage 1 Invoice for WO-123'); ?>">
                </div>
                <div class="field">
                    <label><?= sanitize('Workorder Reference'); ?> <span class="muted">(optional)</span></label>
                    <input name="workorderRef" maxlength="80" placeholder="<?= sanitize('WO number or internal ref'); ?>">
                </div>
                <div class="field">
                    <label><?= sanitize('Amount Text'); ?> <span class="muted">(optional, max 30 chars)</span></label>
                    <input name="amountText" maxlength="30" placeholder="<?= sanitize('e.g. ₹3,40,000'); ?>">
                </div>
                <div class="field">
                    <label><?= sanitize('Notes'); ?></label>
                    <p class="muted" style="margin:0;">We only store the provided invoice amount text. No bid values or rates are captured.</p>
                </div>
                <div class="buttons">
                    <button class="btn" type="submit"><?= sanitize('Create'); ?></button>
                    <a class="btn secondary" href="/contractor/bills.php"><?= sanitize('Back to list'); ?></a>
                </div>
            </form>
        </div>
        <?php
    });
});
