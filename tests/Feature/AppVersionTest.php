<?php

use App\Models\Association;
use App\Models\User;

it('reads the build from the checked-out git commit', function () {
    expect(config('app.build'))->toBe(trim((string) shell_exec('git rev-parse --short=7 HEAD')));
});

it('shows the app version under the sidebar user block in both panels', function () {
    config(['app.version' => '1.0', 'app.build' => 'abc1234']);
    $this->actingAs(User::factory()->superAdmin()->create())->get('/admin')->assertOk()->assertSee('Version 1.0 · abc1234');

    $assoc = Association::factory()->create();
    $this->actingAs(User::factory()->assocEditor($assoc)->create())->get("/portal/{$assoc->id}")->assertOk()->assertSee('Version 1.0 · abc1234');
});
