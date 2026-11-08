<?php

namespace App\Filament\Concerns;

use App\Enums\StaPermission;

trait RequiresStaPermission
{
    abstract protected static function requiredPermission(): StaPermission;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(static::requiredPermission()->value);
    }
}
