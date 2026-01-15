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
    if (!isset($fields[$key])) {
        set_flash('error', 'Saved field not found.');
        redirect('/contractor/profile.php#profile-memory');
        return;
    }

    unset($fields[$key]);
    $now = now_kolkata()->format(DateTime::ATOM);
    $memory['fields'] = $fields;
    $memory['lastUpdatedAt'] = $now;
    save_profile_memory($yojId, $memory);

    logEvent(DATA_PATH . '/logs/profile_memory.log', [
        'at' => $now,
        'yojId' => $yojId,
        'event' => 'MEMORY_DELETE',
        'key' => $key,
    ]);

    set_flash('success', 'Saved field removed.');
    redirect('/contractor/profile.php#profile-memory');
});
