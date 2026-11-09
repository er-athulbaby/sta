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
        $this->call(PermissionSeeder::class);

        $password = config('app.super_admin.password');

        if (app()->isProduction() && (blank($password) || strtolower($password) === 'change-me-now')) {
            throw new \RuntimeException('SUPER_ADMIN_PASSWORD must be set to a non-default value');
        }

        if (blank($password)) {
            $password = 'change-me-now';
        }

        User::query()->firstOrCreate(
            ['email' => config('app.super_admin.email')],
            ['name' => config('app.super_admin.name'), 'password' => $password, 'role' => Role::SuperAdmin],
        );
    }
}
