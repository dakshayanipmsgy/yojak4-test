<?php
declare(strict_types=1);

function field_value_present($value): bool
{
    if ($value === null) {
        return false;
    }
    if (is_string($value)) {
        return trim($value) !== '';
    }
    return trim((string)$value) !== '';
}

function resolve_field_value(array $pack, array $contractor, string $key, bool $useOverrides = true): string
{
    $key = pack_normalize_placeholder_key($key);
    $registry = is_array($pack['fieldRegistry'] ?? null) ? $pack['fieldRegistry'] : [];
    if ($useOverrides && array_key_exists($key, $registry)) {
        $value = trim((string)$registry[$key]);
        if ($value !== '') {
            return $value;
        }
    }
    $derived = pack_tender_placeholder_values($pack);
    if (array_key_exists($key, $derived) && field_value_present($derived[$key])) {
        return trim((string)$derived[$key]);
    }
    $memory = pack_profile_memory_values((string)($contractor['yojId'] ?? ''));
    if (array_key_exists($key, $memory) && field_value_present($memory[$key])) {
        return trim((string)$memory[$key]);
    }
    $profile = pack_profile_placeholder_values($contractor);
    return trim((string)($profile[$key] ?? ''));
}
