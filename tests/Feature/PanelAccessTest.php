<?php

use App\Models\Association;
use App\Models\User;

it('lets super admin and STA into admin only', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $this->actingAs($user)->get('/admin')->assertOk();
    $this->actingAs($user)->get('/portal')->assertForbidden();
})->with(['superAdmin', 'sta']);

it('lets association users into their own portal only', function (string $state) {
    $assoc = Association::factory()->create();
    $user = User::factory()->{$state}($assoc)->create();
    $this->actingAs($user)->get('/admin')->assertForbidden();
    $this->actingAs($user)->get("/portal/{$assoc->id}")->assertOk();

    $other = Association::factory()->create();
    expect($this->actingAs($user)->get("/portal/{$other->id}")->status())->toBeIn([403, 404]);
})->with(['assocAdmin', 'assocEditor']);

it('blocks inactive users from every panel', function () {
    $user = User::factory()->superAdmin()->inactive()->create();
    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('blocks inactive association users from their portal', function () {
    $assoc = Association::factory()->create();
    $user = User::factory()->assocEditor($assoc)->inactive()->create();
    $this->actingAs($user)->get("/portal/{$assoc->id}")->assertForbidden();
});

it('blocks users of a deactivated association from the portal', function (string $state) {
    $assoc = Association::factory()->create(['is_active' => false]);
    $user = User::factory()->{$state}($assoc)->create();
    $this->actingAs($user)->get("/portal/{$assoc->id}")->assertForbidden();
})->with(['assocAdmin', 'assocEditor']);
