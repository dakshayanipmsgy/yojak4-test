<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/contractor/profile.php');
    }

    require_csrf();
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $key = pack_normalize_placeholder_key(trim((string)($_POST['key'] ?? '')));
    if ($key === '') {
        set_flash('error', 'Invalid saved field key.');
        redirect('/contractor/profile.php#profile-memory');
        return;
    }

    $memory = load_profile_memory($yojId);
    $fields = is_array($memory['fields'] ?? null) ? $memory['fields'] : [];
    if (!isset($fields[$key]) || !is_array($fields[$key])) {
        set_flash('error', 'Saved field not found.');
        redirect('/contractor/profile.php#profile-memory');
        return;
    }

    $existing = $fields[$key];
    $type = (string)($existing['type'] ?? 'text');
    $value = profile_memory_limit_value((string)($_POST['value'] ?? ''), profile_memory_max_length($type));
    if ($value === '') {
        set_flash('error', 'Value cannot be empty.');
        redirect('/contractor/profile.php#profile-memory');
        return;
    }
    if (!profile_memory_is_eligible_key($key, $value)) {
        set_flash('error', 'This field cannot be saved to profile memory.');
        redirect('/contractor/profile.php#profile-memory');
        return;
    }

    $now = now_kolkata()->format(DateTime::ATOM);
    $fields[$key] = [
        'label' => (string)($existing['label'] ?? profile_memory_label_from_key($key)),
        'value' => $value,
        'type' => $type === '' ? 'text' : $type,
        'updatedAt' => $now,
        'source' => 'profile',
    ];
    $memory['fields'] = $fields;
    $memory['lastUpdatedAt'] = $now;
    save_profile_memory($yojId, $memory);

    logEvent(DATA_PATH . '/logs/profile_memory.log', [
        'at' => $now,
        'yojId' => $yojId,
        'event' => 'MEMORY_UPSERT',
        'key' => $key,
    ]);

    set_flash('success', 'Saved field updated.');
    redirect('/contractor/profile.php#profile-memory');
});
