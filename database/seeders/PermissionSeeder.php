<?php

namespace Database\Seeders;

use App\Enums\StaPermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (StaPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
