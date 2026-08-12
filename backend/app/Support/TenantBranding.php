<?php

namespace App\Support;

class TenantBranding
{
    /** @var array<string, string> asset key => path field in branding JSON */
    private const PATH_FIELDS = [
        'logo' => 'logo_path',
        'favicon' => 'favicon_path',
        'menu_header' => 'menu_header_path',
    ];

    public static function assetUrl(?string $path): ?string
    {
        return PublicUploadUrl::fromPath($path);
    }

    /**
     * Normalize US-21 alias fields before persisting to the database.
     *
     * @param  array<string, mixed>  $branding
     * @return array<string, mixed>
     */
    public static function normalizeInput(array $branding): array
    {
        if (isset($branding['secondary_color']) && ! isset($branding['accent_color'])) {
            $branding['accent_color'] = $branding['secondary_color'];
        }

        if (isset($branding['menu_cover_path']) && ! isset($branding['menu_header_path'])) {
            $branding['menu_header_path'] = $branding['menu_cover_path'];
        }

        unset($branding['secondary_color'], $branding['menu_cover_path'], $branding['menu_cover_url']);

        return $branding;
    }

    /**
     * Merge stored paths with public *_url fields for API responses.
     *
     * @param  array<string, mixed>|null  $branding
     * @return array<string, mixed>
     */
    public static function resolve(?array $branding): array
    {
        $branding = $branding ?? [];
        $resolved = $branding;

        foreach (self::PATH_FIELDS as $key => $pathKey) {
            $urlKey = $key === 'menu_header' ? 'menu_header_url' : $key.'_url';
            $path = $branding[$pathKey] ?? null;
            $legacyUrl = $branding[$urlKey] ?? null;
            $resolved[$urlKey] = self::assetUrl(is_string($path) ? $path : null) ?? (is_string($legacyUrl) ? $legacyUrl : null);
        }

        if (! empty($resolved['accent_color'])) {
            $resolved['secondary_color'] = $resolved['accent_color'];
        }

        if (! empty($resolved['menu_header_url'])) {
            $resolved['menu_cover_url'] = $resolved['menu_header_url'];
        }

        if (! empty($resolved['menu_header_path'])) {
            $resolved['menu_cover_path'] = $resolved['menu_header_path'];
        }

        return $resolved;
    }
}
