<?php
declare(strict_types=1);

namespace Dompdf;

final class Options
{
    private array $settings = [];

    public function set(string $key, mixed $value): void
    {
        $this->settings[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }
}
