<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_assisted_v2_env();

    $requests = array_values(array_filter(assisted_v2_list_requests(), static function (array $req) use ($yojId) {
        return ($req['contractor']['yojId'] ?? '') === $yojId;
    }));

    $title = get_app_config()['appName'] . ' | Assisted Pack Requests';
    render_layout($title, function () use ($requests) {
        ?>
        <div class="card" style="display:grid;gap:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize('Assisted Pack Requests'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Track staff-delivered packs created from your tender PDFs.'); ?></p>
                </div>
                <a class="btn secondary" href="/contractor/offline_tenders.php"><?= sanitize('Back to offline tenders'); ?></a>
            </div>
        </div>
        <div class="card">
            <h3 style="margin-top:0;"><?= sanitize('Requests (' . count($requests) . ')'); ?></h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Request</th>
                            <th>Tender</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Pack</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$requests): ?>
                            <tr><td colspan="5" class="muted"><?= sanitize('No assisted pack requests yet.'); ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($requests as $req): ?>
                            <?php $packId = $req['result']['packId'] ?? ''; ?>
                            <tr>
                                <td><?= sanitize($req['reqId'] ?? ''); ?></td>
                                <td><?= sanitize($req['source']['offtdId'] ?? ''); ?></td>
                                <td><span class="pill"><?= sanitize(ucwords(str_replace('_',' ', $req['status'] ?? 'pending'))); ?></span></td>
                                <td><?= sanitize($req['createdAt'] ?? ''); ?></td>
                                <td>
                                    <?php if ($packId): ?>
                                        <a class="btn secondary" href="/contractor/pack_view.php?packId=<?= sanitize($packId); ?>"><?= sanitize('View Pack'); ?></a>
                                    <?php else: ?>
                                        <span class="muted"><?= sanitize('Pending'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    });
});
