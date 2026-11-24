<?php

use App\Models\Association;
use App\Models\User;

it('sends an association user who opens another association\'s portal link to their own home', function () {
    $mine = Association::factory()->create();
    $other = Association::factory()->create();
    $user = User::factory()->assocAdmin($mine)->create();

    $this->actingAs($user)->get('/portal/'.$other->id)->assertRedirect('/portal/'.$mine->id);
    $this->actingAs($user)->get('/portal/99999')->assertRedirect('/portal/'.$mine->id);
    $this->actingAs($user)->get('/portal/'.$mine->id)->assertOk();
});

it('sends STA users who open the portal to the admin side', function () {
    $other = Association::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())->get('/portal/'.$other->id)->assertRedirect('/admin');
    $this->actingAs(User::factory()->superAdmin()->create())->get('/portal')->assertRedirect('/admin');
});

it('sends association users who open the admin side to their own association', function () {
    $mine = Association::factory()->create();

    $this->actingAs(User::factory()->assocEditor($mine)->create())->get('/admin')->assertRedirect('/portal/'.$mine->id);
});

it('shows a deactivated page and signs out a deactivated user or a user of a deactivated association', function () {
    $this->actingAs(User::factory()->superAdmin()->inactive()->create())->get('/admin')
        ->assertForbidden()->assertSee('Your account is deactivated')->assertSee('An administrator has deactivated your account.');
    $this->assertGuest();

    $closed = Association::factory()->create(['is_active' => false]);
    $this->actingAs(User::factory()->assocAdmin($closed)->create())->get('/portal/'.$closed->id)
        ->assertForbidden()->assertSee('Your association has been deactivated');
    $this->assertGuest();
});

it('keeps a plain 403 for an STA user without the permission for an admin page', function () {
    $this->actingAs(User::factory()->sta()->create())->get('/admin/sports')->assertForbidden();
    $this->assertAuthenticated();
});
