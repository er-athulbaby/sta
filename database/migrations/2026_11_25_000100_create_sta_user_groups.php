<?php

use App\Enums\Role;
use App\Enums\StaArea;
use App\Enums\StaPermission;
use App\Models\StaGroup;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * User groups: the 11 on/off STA rights become a grid of {area}.{action} permissions held by groups (spatie roles).
 * Nobody loses access: every distinct combination of old rights among STA users becomes a "Migrated group N" with the
 * same access (StaPermission::grants()), and its users are moved into it. "Full access" and "Read only" are added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', fn (Blueprint $table) => $table->string('description', 500)->nullable()->after('guard_name'));
        $this->forgetCache();

        foreach (StaArea::allPermissions() as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $this->group(StaGroup::FULL_ACCESS, 'Every page and action.', StaArea::allPermissions());
        $this->group(StaGroup::READ_ONLY, 'Can open and view the main pages; cannot add, change, delete or export anything.',
            collect(StaArea::cases())->reject(fn (StaArea $a) => $a->isSpecial() || in_array($a, [StaArea::AuditLog, StaArea::Users], true))
                ->map(fn (StaArea $a) => $a->permission())->values()->all());

        $old = array_map(fn (StaPermission $p) => $p->value, StaPermission::cases());
        $users = User::withoutGlobalScopes()->where('role', Role::Sta)->orderBy('id')->get();
        $combos = $users->groupBy(fn (User $u) => DB::table('model_has_permissions')->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_type', $u->getMorphClass())->where('model_id', $u->id)->whereIn('permissions.name', $old)
            ->orderBy('permissions.name')->pluck('permissions.name')->implode(','));
        $n = 0;
        foreach ($combos as $key => $members) {
            $rights = array_values(array_filter(explode(',', $key)));
            $group = $this->group('Migrated group '.++$n,
                $rights ? 'Same access as before user groups: '.implode('; ', array_map(fn ($r) => StaPermission::from($r)->label(), $rights)).'.'
                    : 'Had no extra rights before user groups (Dashboard only).',
                StaPermission::grantsOf($rights));
            foreach ($members as $user) {
                $user->syncRoles([$group]);
            }
        }

        // The old rights are replaced by the groups.
        Permission::whereIn('name', $old)->get()->each->delete();
        $this->forgetCache();
    }

    public function down(): void
    {
        $this->forgetCache();
        foreach (StaPermission::cases() as $p) {
            Permission::findOrCreate($p->value, 'web');
        }
        // Best effort: an old right comes back when the user's group still has every cell it stood for.
        foreach (User::withoutGlobalScopes()->where('role', Role::Sta)->with('roles.permissions')->get() as $user) {
            $has = $user->roles->flatMap->permissions->pluck('name')->all();
            $user->givePermissionTo(collect(StaPermission::cases())->filter(fn (StaPermission $p) => array_diff($p->grants(), $has) === [])->map->value->all());
            $user->syncRoles([]);
        }
        StaGroup::query()->get()->each->delete();
        Permission::whereIn('name', StaArea::allPermissions())->get()->each->delete();
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn('description'));
        $this->forgetCache();
    }

    /** @param  list<string>  $permissions */
    private function group(string $name, string $description, array $permissions): StaGroup
    {
        $group = StaGroup::findOrCreate($name, 'web');
        $group->forceFill(['description' => $description])->save();
        $group->syncPermissions($permissions);

        return $group;
    }

    private function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
