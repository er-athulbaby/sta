<?php

namespace Database\Seeders;

use App\Enums\StaArea;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** Every cell of the user-group grid ({area}.{action}); the groups themselves are created by the user-groups migration. */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (StaArea::allPermissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
