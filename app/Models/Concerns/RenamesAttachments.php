<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/** After save, move each changed attachment to {dir}/{prefix}_{kind}.{ext}; the previous file is deleted. */
trait RenamesAttachments
{
    /** @var array<string, ?string> paths before this save, captured in `saving` (getOriginal() is already synced in `saved`) */
    public array $attachmentOldPaths = [];

    /** @return array<string, string> column => kind */
    abstract protected function attachmentColumns(): array;

    abstract protected function attachmentPrefix(): string;

    public static function bootRenamesAttachments(): void
    {
        static::saving(function (self $model) {
            foreach (array_keys($model->attachmentColumns()) as $column) {
                $model->attachmentOldPaths[$column] = $model->getOriginal($column);
            }
        });

        static::saved(function (self $model) {
            $disk = Storage::disk(config('filesystems.private'));
            $updates = [];
            foreach ($model->attachmentColumns() as $column => $kind) {
                $path = $model->{$column};
                $old = $model->attachmentOldPaths[$column] ?? null;
                if (! $model->wasChanged($column) && ! $model->wasRecentlyCreated) {
                    continue;
                }
                if (blank($path)) {
                    if ($old) {
                        $disk->delete($old);
                    }

                    continue;
                }
                if (! $disk->exists($path)) {
                    continue; // e.g. factory paths with no file behind them
                }
                $target = trim(dirname($path), '.\/').'/'.$model->attachmentPrefix().'_'.$kind.'.'.strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if ($old && $old !== $path && $old !== $target) {
                    $disk->delete($old);
                }
                if ($path !== $target) {
                    $disk->delete($target);
                    $disk->move($path, $target);
                    $updates[$column] = $target;
                }
            }
            if ($updates) {
                $model->forceFill($updates)->saveQuietly();
            }
        });
    }
}
