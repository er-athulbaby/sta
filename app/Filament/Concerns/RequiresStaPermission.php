<?php

namespace App\Filament\Concerns;

use App\Enums\StaArea;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * A page or resource guarded by one row of the user-group grid: Access opens it; on a resource, create needs New,
 * edit needs Save and delete needs Delete (Filament asks getAuthorizationResponse for each).
 */
trait RequiresStaPermission
{
    abstract protected static function staArea(): StaArea;

    /** Livewire trait hook: re-check on every request, not only mount, so a revoked permission stops working at once. */
    public function hydrateRequiresStaPermission(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public static function canAccess(array $parameters = []): bool
    {
        return static::staArea()->can();
    }

    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        return static::staArea()->authorize($action);
    }
}
