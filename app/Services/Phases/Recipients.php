<?php

namespace App\Services\Phases;

use App\Enums\Role;
use App\Enums\StaAction;
use App\Enums\StaArea;
use App\Models\Association;
use App\Models\User;
use Illuminate\Support\Collection;

final class Recipients
{
    /** @return Collection<int, User> */
    public static function association(Association $a, ?Role $role = null): Collection
    {
        return User::query()->where('association_id', $a->id)->where('is_active', true)
            ->when($role, fn ($q) => $q->where('role', $role))->get();
    }

    /** @return Collection<int, User> active Super Admins and STA users with this cell of the user-group grid */
    public static function sta(StaArea $area = StaArea::Submissions, StaAction $action = StaAction::Access): Collection
    {
        // withoutGlobalScopes: in the portal, Filament's tenant scope (Members resource) would hide every STA user.
        return User::withoutGlobalScopes()->where('is_active', true)->whereIn('role', [Role::SuperAdmin, Role::Sta])->get()
            ->filter(fn (User $u) => $u->isSuperAdmin() || $area->can($action, $u))->values();
    }
}
