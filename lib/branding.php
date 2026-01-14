<?php
declare(strict_types=1);

function branding_site_config_path(): string
{
    return DATA_PATH . '/site/branding.json';
}

function branding_logo_public_path(): string
{
    return '/uploads/branding/logo.png';
}

function get_branding(): array
{
    $defaults = [
        'logoPath' => null,
        'logoUpdatedAt' => null,
    ];

    $path = branding_site_config_path();
    if (!file_exists($path)) {
        return $defaults;
    }

    $data = readJson($path);
    if ($data === [] && filesize($path) > 0) {
        logEvent(DATA_PATH . '/logs/site.log', [
            'event' => 'BRANDING_READ_FAIL',
            'path' => $path,
        ]);
        return $defaults;
    }

    $branding = $defaults;
    if (isset($data['logoPath']) && is_string($data['logoPath']) && $data['logoPath'] !== '') {
        $branding['logoPath'] = $data['logoPath'];
    } elseif (isset($data['logoPublicPath']) && is_string($data['logoPublicPath']) && $data['logoPublicPath'] !== '') {
        $branding['logoPath'] = $data['logoPublicPath'];
    }
    if (isset($data['logoUpdatedAt']) && is_string($data['logoUpdatedAt']) && $data['logoUpdatedAt'] !== '') {
        $branding['logoUpdatedAt'] = $data['logoUpdatedAt'];
    } elseif (isset($data['logoUploadedAt']) && is_string($data['logoUploadedAt']) && $data['logoUploadedAt'] !== '') {
        $branding['logoUpdatedAt'] = $data['logoUploadedAt'];
    }

    if ($branding['logoPath']) {
        $absolute = rtrim(PUBLIC_PATH, '/') . $branding['logoPath'];
        if (!file_exists($absolute)) {
            logEvent(DATA_PATH . '/logs/site.log', [
                'event' => 'BRANDING_LOGO_MISSING',
                'path' => $branding['logoPath'],
            ]);
            $branding['logoPath'] = null;
        }
    }

    return $branding;
}

function render_logo_html(string $size = 'md'): string
{
    $branding = get_branding();
    $logoPath = $branding['logoPath'];
    $sizeClass = 'logo-' . preg_replace('/[^a-z]/', '', strtolower($size));

    if ($logoPath) {
        return '<div class="brand-logo-image ' . $sizeClass . '"><img src="' . sanitize($logoPath) . '" alt="YOJAK"></div>';
    }

    return '<div class="brand-logo ' . $sizeClass . '" aria-label="YOJAK">YJ</div>';
}
