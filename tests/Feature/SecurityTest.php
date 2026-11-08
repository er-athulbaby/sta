<?php

use App\Models\User;

it('gives admin users a profile page where MFA can be set up', function () {
    $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/profile')->assertOk();
});

it('does not force MFA on login', function () {
    $this->actingAs(User::factory()->sta()->create())->get('/admin')->assertOk();
});

it('hides the MFA secret from serialisation', function () {
    $user = User::factory()->sta()->create();
    $user->forceFill(['app_authentication_secret' => 'SECRET'])->save();
    expect($user->fresh()->toArray())->not->toHaveKey('app_authentication_secret');
});
