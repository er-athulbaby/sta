<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('creates the super admin from config', function () {
    config(['app.super_admin' => ['name' => 'Boss', 'email' => 'boss@example.com', 'password' => 'secret-pass-1']]);

    (new DatabaseSeeder)->run();

    $user = User::where('email', 'boss@example.com')->firstOrFail();
    expect($user->role)->toBe(Role::SuperAdmin)->and($user->name)->toBe('Boss');
});

it('refuses to seed in production without a password', function () {
    config(['app.super_admin.password' => null]);
    $this->app['env'] = 'production';

    expect(fn () => (new DatabaseSeeder)->run())
        ->toThrow(RuntimeException::class, 'SUPER_ADMIN_PASSWORD must be set');
});
