<?php
declare(strict_types=1);

function render_print_layout(string $title, string $bodyHtml, array $options = []): string
{
    $styles = $options['styles'] ?? '';
    $scripts = $options['scripts'] ?? '';
    $bodyClass = trim((string)($options['bodyClass'] ?? ''));
    $bodyClassAttr = $bodyClass !== '' ? ' class="' . htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') . '"' : '';

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
        . $styles
        . '</head><body' . $bodyClassAttr . '>'
        . $bodyHtml
        . $scripts
        . '</body></html>';
}
