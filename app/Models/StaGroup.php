<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role;

/**
 * An STA user group: a spatie role (table roles, guard web) with a description. Its permissions are the ticked cells
 * of the grid ({area}.{action}, see StaArea); every STA user belongs to exactly one group.
 */
class StaGroup extends Role
{
    use LogsActivity;

    public const FULL_ACCESS = 'Full access';

    public const READ_ONLY = 'Read only';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'description'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /** Pivot changes are invisible to LogsActivity, so record the ticked cells explicitly. */
    public function logPermissions(string $description = 'group permissions updated'): void
    {
        activity()->performedOn($this)->causedBy(auth()->user())->event('updated')
            ->withProperties(['permissions' => $this->permissions()->pluck('name')->sort()->values()->all()])
            ->log($description);
    }
}
