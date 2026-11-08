<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // $this->call(PermissionSeeder::class); // restored in Task 3, Step 3

        $password = config('app.super_admin.password');

        if (blank($password)) {
            if (app()->isProduction()) {
                throw new \RuntimeException('SUPER_ADMIN_PASSWORD must be set');
            }
            $password = 'change-me-now';
        }

        User::query()->firstOrCreate(
            ['email' => config('app.super_admin.email')],
            ['name' => config('app.super_admin.name'), 'password' => $password, 'role' => Role::SuperAdmin],
        );
    }
}
