<?php

namespace Helper\Traits;

use Illuminate\Support\Facades\Storage;

trait FileTrait {

    /**
     * Get human-readable file size for a private file (e.g. '1.23 MB').
     *
     * In fields:
     *   Field::make('contract_size', fn($d) => $d->getSecureFileSize($d->contract_path, 'local')),
     */
    public function getSecureFileSize(?string $filename, string $disk = 'local'): ?string {
        if (!$filename || filter_var($filename, FILTER_VALIDATE_URL)) {
            return null;
        }

        if (!Storage::disk($disk)->exists($filename)) {
            return null;
        }

        $bytes = Storage::disk($disk)->size($filename);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
