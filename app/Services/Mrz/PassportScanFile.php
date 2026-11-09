<?php

namespace App\Services\Mrz;

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** Turns a FileUpload state (new temporary upload or stored path) into a local file path the OCR can read. */
final class PassportScanFile
{
    private static function item(mixed $state): mixed
    {
        return is_array($state) ? (array_values($state)[0] ?? null) : $state;
    }

    public static function localPath(mixed $state): ?string
    {
        $item = self::item($state);
        if ($item instanceof TemporaryUploadedFile) {
            return $item->getRealPath() ?: null;
        }
        if (is_string($item) && $item !== '') {
            $disk = Storage::disk(config('filesystems.private'));
            if (! $disk->exists($item)) {
                return null;
            }
            $base = tempnam(sys_get_temp_dir(), 'pp_');
            $local = $base.'.'.pathinfo($item, PATHINFO_EXTENSION);
            rename($base, $local);
            file_put_contents($local, $disk->get($item)); // works for local and S3

            return $local;
        }

        return null;
    }

    /** Delete the temp copy localPath() made for a stored file (new uploads are left to Livewire). */
    public static function release(mixed $state, ?string $path): void
    {
        if ($path !== null && is_string(self::item($state))) {
            @unlink($path);
        }
    }
}
