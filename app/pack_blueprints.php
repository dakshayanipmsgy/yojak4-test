<?php
declare(strict_types=1);

function contractor_pack_blueprints_dir(string $yojId): string
{
    return contractors_approved_path($yojId) . '/packs_blueprints';
}

function contractor_pack_blueprints_index_path(string $yojId): string
{
    return contractor_pack_blueprints_dir($yojId) . '/index.json';
}

function contractor_pack_blueprint_path(string $yojId, string $packId): string
{
    return contractor_pack_blueprints_dir($yojId) . '/' . $packId . '.json';
}

function ensure_contractor_pack_blueprints_env(string $yojId): void
{
    $dir = contractor_pack_blueprints_dir($yojId);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!file_exists(contractor_pack_blueprints_index_path($yojId))) {
        writeJsonAtomic(contractor_pack_blueprints_index_path($yojId), []);
    }
}

function load_contractor_pack_blueprint(string $yojId, string $packId): ?array
{
    ensure_contractor_pack_blueprints_env($yojId);
    $path = contractor_pack_blueprint_path($yojId, $packId);
    if (!file_exists($path)) {
        return null;
    }
    $data = readJson($path);
    return $data ?: null;
}

function load_contractor_pack_blueprints(string $yojId): array
{
    ensure_contractor_pack_blueprints_env($yojId);
    $blueprints = [];
    $index = readJson(contractor_pack_blueprints_index_path($yojId));
    $index = is_array($index) ? $index : [];
    foreach ($index as $entry) {
        $pack = load_contractor_pack_blueprint($yojId, $entry['id'] ?? '');
        if ($pack) {
            $blueprints[] = $pack;
        }
    }
    return $blueprints;
}

function save_contractor_pack_blueprint(string $yojId, array $pack): void
{
    if (empty($pack['id'])) {
        throw new InvalidArgumentException('Missing pack id.');
    }
    ensure_contractor_pack_blueprints_env($yojId);
    $now = now_kolkata()->format(DateTime::ATOM);
    $pack['updatedAt'] = $now;
    $pack['createdAt'] = $pack['createdAt'] ?? $now;
    $pack['scope'] = $pack['scope'] ?? 'contractor';
    $pack['owner'] = $pack['owner'] ?? ['yojId' => $yojId];
    $pack['published'] = $pack['published'] ?? true;
    writeJsonAtomic(contractor_pack_blueprint_path($yojId, $pack['id']), $pack);

    $index = readJson(contractor_pack_blueprints_index_path($yojId));
    $index = is_array($index) ? $index : [];
    $found = false;
    foreach ($index as &$entry) {
        if (($entry['id'] ?? '') === $pack['id']) {
            $entry['title'] = $pack['title'] ?? 'Pack Blueprint';
            $entry['updatedAt'] = $pack['updatedAt'];
            $found = true;
            break;
        }
    }
    unset($entry);
    if (!$found) {
        $index[] = [
            'id' => $pack['id'],
            'title' => $pack['title'] ?? 'Pack Blueprint',
            'updatedAt' => $pack['updatedAt'],
        ];
    }
    writeJsonAtomic(contractor_pack_blueprints_index_path($yojId), array_values($index));
}

function pack_blueprint_items_from_post(array $post): array
{
    $types = $post['item_type'] ?? [];
    $titles = $post['item_title'] ?? [];
    $templateIds = $post['item_template'] ?? [];
    $requiredFlags = $post['item_required'] ?? [];

    $items = [];
    foreach ($types as $idx => $type) {
        $type = trim((string)$type);
        if ($type === '') {
            continue;
        }
        $required = !empty($requiredFlags[$idx]);
        $title = trim((string)($titles[$idx] ?? ''));
        $templateId = trim((string)($templateIds[$idx] ?? ''));
        if ($type === 'template_ref' && $templateId === '') {
            continue;
        }
        if ($type !== 'template_ref' && $title === '') {
            continue;
        }
        $item = [
            'type' => $type,
            'required' => $required,
        ];
        if ($type === 'template_ref') {
            $item['templateId'] = $templateId;
        } else {
            $item['title'] = $title;
        }
        $items[] = $item;
    }
    return $items;
}
