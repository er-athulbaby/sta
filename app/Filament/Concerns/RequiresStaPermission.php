<?php

namespace App\Filament\Concerns;

use App\Enums\StaPermission;

trait RequiresStaPermission
{
    abstract protected static function requiredPermission(): StaPermission;

    /** Livewire trait hook: re-check on every request, not only mount, so a revoked permission stops working at once. */
    public function hydrateRequiresStaPermission(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(static::requiredPermission()->value);
    }
}
