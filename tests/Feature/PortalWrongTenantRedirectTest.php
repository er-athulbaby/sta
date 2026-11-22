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

it('does not redirect STA users, who have no portal access', function () {
    $other = Association::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())->get('/portal/'.$other->id)->assertForbidden();
});
