<?php

namespace App\Services\Phases;

use App\Enums\Role;
use App\Enums\StaPermission;
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

    /** @return Collection<int, User> active Super Admins and STA users holding $permission */
    public static function sta(StaPermission $permission = StaPermission::ViewSubmissions): Collection
    {
        // withoutGlobalScopes: in the portal, Filament's tenant scope (Members resource) would hide every STA user.
        return User::withoutGlobalScopes()->where('is_active', true)->whereIn('role', [Role::SuperAdmin, Role::Sta])->get()
            ->filter(fn (User $u) => $u->isSuperAdmin() || $u->can($permission->value))->values();
    }
}
