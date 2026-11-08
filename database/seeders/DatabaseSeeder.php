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

        User::query()->firstOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'admin@example.com')],
            ['name' => env('SUPER_ADMIN_NAME', 'Super Admin'), 'password' => env('SUPER_ADMIN_PASSWORD', 'change-me-now'), 'role' => Role::SuperAdmin],
        );
    }
}
