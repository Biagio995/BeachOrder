<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PublicUploadUrl
{
    /**
     * Build a browser-usable URL for a public upload path.
     *
     * Local disk returns a same-origin relative path (`/storage/...`) so Vite/nginx
     * proxies and DevTunnel setups work without hard-coding APP_URL / localhost.
     */
    public static function fromPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $disk = config('filesystems.uploads_disk', 'public');

        if ($disk === 's3') {
            return Storage::disk('s3')->url($path);
        }

        return '/storage/'.ltrim($path, '/');
    }
}
