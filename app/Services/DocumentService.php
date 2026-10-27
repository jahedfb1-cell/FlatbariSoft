<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores an uploaded document file on the public disk and returns its
 * stored path. A thin wrapper now, but keeps every document upload going
 * through one place — if storage moves to S3-compatible object storage
 * later, this is the only class that changes.
 */
class DocumentService
{
    public function store(UploadedFile $file, string $folder): string
    {
        return $file->store($folder, 'public');
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    public function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
