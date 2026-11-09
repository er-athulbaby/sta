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

    /** @return Collection<int, User> */
    public static function sta(): Collection
    {
        return User::query()->where('is_active', true)->whereIn('role', [Role::SuperAdmin, Role::Sta])->get()
            ->filter(fn (User $u) => $u->isSuperAdmin() || $u->can(StaPermission::ViewSubmissions->value))->values();
    }
}
