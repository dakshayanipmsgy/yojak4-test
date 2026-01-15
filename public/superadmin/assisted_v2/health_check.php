<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    require_role('superadmin');
    ensure_assisted_v2_env();

    $standard = assisted_v2_field_key_standard();
    $standardMeta = assisted_v2_field_meta_from_catalog($standard['canonical'] ?? []);
    $canonicalKeys = array_keys($standardMeta);

    $collect_recent_packs = static function (int $limit = 50): array {
        $entries = [];
        foreach (contractors_index() as $contractor) {
            $yojId = (string)($contractor['yojId'] ?? '');
            if ($yojId === '') {
                continue;
            }
            foreach (packs_index($yojId, 'tender') as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $entries[] = [
                    'yojId' => $yojId,
                    'packId' => (string)($entry['packId'] ?? ''),
                    'updatedAt' => (string)($entry['updatedAt'] ?? $entry['createdAt'] ?? ''),
                    'createdAt' => (string)($entry['createdAt'] ?? ''),
                ];
            }
        }
        usort($entries, static function (array $a, array $b): int {
            return strcmp($b['updatedAt'], $a['updatedAt']);
        });
        return array_slice($entries, 0, $limit);
    };

    $repair_pack = static function (array $pack) use ($standardMeta): bool {
        $context = detect_pack_context((string)($pack['packId'] ?? ''));
        $fieldMeta = is_array($pack['fieldMeta'] ?? null) ? $pack['fieldMeta'] : [];
        $newFieldMeta = [];
        foreach ($fieldMeta as $key => $meta) {
            $normalizedKey = assisted_v2_normalize_reference_key((string)$key, []);
            $newFieldMeta[$normalizedKey] = $meta;
        }
        foreach ($standardMeta as $key => $meta) {
            if (!isset($newFieldMeta[$key])) {
                $newFieldMeta[$key] = $meta;
            }
        }
        $pack['fieldMeta'] = $newFieldMeta;

        $registry = is_array($pack['fieldRegistry'] ?? null) ? $pack['fieldRegistry'] : [];
        $newRegistry = [];
        foreach ($registry as $key => $value) {
            $normalizedKey = assisted_v2_normalize_reference_key((string)$key, []);
            if (!array_key_exists($normalizedKey, $newRegistry) || trim((string)$newRegistry[$normalizedKey]) === '') {
                $newRegistry[$normalizedKey] = $value;
            }
        }
        $pack['fieldRegistry'] = $newRegistry;

        $annexDir = pack_annexures_dir((string)($pack['yojId'] ?? ''), (string)($pack['packId'] ?? ''), $context);
        foreach (glob($annexDir . '/*.json') ?: [] as $file) {
            $template = readJson($file);
            if (!$template) {
                continue;
            }
            $stats = [];
            $template['body'] = assisted_v2_normalize_field_placeholders(
                assisted_v2_canonicalize_table_placeholders((string)($template['body'] ?? ''), $stats),
                [],
                $stats
            );
            $template['renderTemplate'] = assisted_v2_normalize_field_placeholders(
                assisted_v2_canonicalize_table_placeholders((string)($template['renderTemplate'] ?? ($template['body'] ?? '')), $stats),
                [],
                $stats
            );
            $required = [];
            foreach ((array)($template['requiredFieldKeys'] ?? []) as $key) {
                $normalizedKey = assisted_v2_normalize_reference_key((string)$key, []);
                if ($normalizedKey !== '') {
                    $required[] = $normalizedKey;
                }
            }
            $template['requiredFieldKeys'] = array_values(array_unique($required));
            foreach ((array)($template['tables'] ?? []) as $tIndex => $table) {
                if (!is_array($table)) {
                    continue;
                }
                foreach ((array)($table['rows'] ?? []) as $rIndex => $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $fieldKeys = is_array($row['fieldKeys'] ?? null) ? $row['fieldKeys'] : [];
                    foreach ($fieldKeys as $colKey => $fieldKey) {
                        if (stripos((string)$fieldKey, 'table.') === 0) {
                            continue;
                        }
                        $fieldKeys[$colKey] = assisted_v2_normalize_reference_key((string)$fieldKey, []);
                    }
                    if ($fieldKeys) {
                        $template['tables'][$tIndex]['rows'][$rIndex]['fieldKeys'] = $fieldKeys;
                    }
                }
            }
            writeJsonAtomic($file, $template);
        }

        save_pack($pack, $context);
        return true;
    };

    $repairs = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $action = trim((string)($_POST['action'] ?? ''));
        if ($action === 'repair') {
            $packId = trim((string)($_POST['packId'] ?? ''));
            $yojId = trim((string)($_POST['yojId'] ?? ''));
            if ($packId !== '' && $yojId !== '') {
                $context = detect_pack_context($packId);
                $pack = load_pack($yojId, $packId, $context);
                if ($pack && ($pack['source'] ?? '') === 'assisted_v2') {
                    $repairs[] = $repair_pack($pack) ? $packId : null;
                }
            }
        }
    }

    $report = [];
    foreach ($collect_recent_packs() as $entry) {
        $pack = load_pack($entry['yojId'], $entry['packId'], detect_pack_context($entry['packId']));
        if (!$pack || ($pack['source'] ?? '') !== 'assisted_v2') {
            continue;
        }
        $fieldMeta = is_array($pack['fieldMeta'] ?? null) ? $pack['fieldMeta'] : [];
        $missingCanonical = array_values(array_diff($canonicalKeys, array_keys($fieldMeta)));
        $aliasPlaceholders = [];
        $annexDir = pack_annexures_dir((string)($pack['yojId'] ?? ''), (string)($pack['packId'] ?? ''), detect_pack_context($entry['packId']));
        foreach (glob($annexDir . '/*.json') ?: [] as $file) {
            $template = readJson($file);
            if (!$template) {
                continue;
            }
            $placeholders = pack_extract_placeholders_from_template($template);
            foreach ($placeholders as $placeholder) {
                $normalized = assisted_v2_normalize_reference_key($placeholder, []);
                if ($normalized !== $placeholder) {
                    $aliasPlaceholders[] = $placeholder . ' → ' . $normalized;
                }
            }
        }
        $report[] = [
            'yojId' => $pack['yojId'] ?? '',
            'packId' => $pack['packId'] ?? '',
            'title' => $pack['title'] ?? '',
            'missingCanonical' => $missingCanonical,
            'aliasPlaceholders' => array_values(array_unique($aliasPlaceholders)),
            'updatedAt' => $pack['updatedAt'] ?? '',
        ];
    }

    $title = get_app_config()['appName'] . ' | Assisted v2 Health Check';
    render_layout($title, function () use ($report, $repairs) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div>
                <h2 style="margin:0;"><?= sanitize('Assisted Pack v2 Health Check'); ?></h2>
                <p class="muted" style="margin:4px 0 0;"><?= sanitize('Scan recent packs for missing canonical keys and placeholder aliases.'); ?></p>
            </div>
            <?php if ($repairs): ?>
                <div class="flash" style="background:var(--surface-2);border:1px solid #1f6feb33;">
                    <?= sanitize('Repairs completed for pack(s): ' . implode(', ', array_filter($repairs))); ?>
                </div>
            <?php endif; ?>
            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Pack</th>
                            <th>Missing Canonical Keys</th>
                            <th>Alias Placeholders</th>
                            <th>Updated</th>
                            <th>Repair</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$report): ?>
                            <tr><td colspan="5" class="muted"><?= sanitize('No assisted v2 packs found in recent scan.'); ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($report as $row): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;"><?= sanitize($row['packId'] ?? ''); ?></div>
                                    <div class="muted" style="font-size:12px;"><?= sanitize(($row['yojId'] ?? '') . ' • ' . ($row['title'] ?? '')); ?></div>
                                </td>
                                <td class="muted">
                                    <?= sanitize($row['missingCanonical'] ? implode(', ', $row['missingCanonical']) : 'None'); ?>
                                </td>
                                <td class="muted">
                                    <?= sanitize($row['aliasPlaceholders'] ? implode(', ', $row['aliasPlaceholders']) : 'None'); ?>
                                </td>
                                <td class="muted"><?= sanitize($row['updatedAt'] ?? ''); ?></td>
                                <td>
                                    <form method="post" action="/superadmin/assisted_v2/health_check.php" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="repair">
                                        <input type="hidden" name="yojId" value="<?= sanitize($row['yojId'] ?? ''); ?>">
                                        <input type="hidden" name="packId" value="<?= sanitize($row['packId'] ?? ''); ?>">
                                        <button class="btn secondary" type="submit"><?= sanitize('Repair'); ?></button>
                                    </form>
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
