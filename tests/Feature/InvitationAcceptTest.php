<?php

use App\Enums\Role;
use App\Models\Association;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->assoc = Association::factory()->create(['name' => 'Bahrain Squash']);
    $this->url = app(InvitationService::class)->invite($this->assoc, 'boss@squash.bh', Role::AssocAdmin, null);
});

it('shows the accept form', function () {
    $this->get($this->url)->assertOk()->assertSee('Bahrain Squash')->assertSee('boss@squash.bh');
});

it('creates the account, logs in and redirects to the portal', function () {
    $this->post($this->url, ['name' => 'Ali', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1'])
        ->assertRedirect("/portal/{$this->assoc->id}");

    $this->assertAuthenticatedAs(User::firstWhere('email', 'boss@squash.bh'));
});

it('validates the password', function () {
    $this->post($this->url, ['name' => 'Ali', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');
});

it('shows an invalid page for unknown tokens', function () {
    $this->get('/invitations/not-a-real-token')->assertStatus(410)->assertSee('invalid or has expired');
});

it('shows the cap error when the association is already full of editors', function () {
    $assoc = Association::factory()->create();
    User::factory()->assocEditor($assoc)->count(2)->create();
    Invitation::create([
        'association_id' => $assoc->id,
        'email' => 'late@squash.bh',
        'role' => Role::AssocEditor,
        'token_hash' => hash('sha256', 'tok-123'),
        'expires_at' => now()->addDays(7),
    ]);

    $this->from('/invitations/tok-123')
        ->post('/invitations/tok-123', ['name' => 'Late', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1'])
        ->assertSessionHasErrors('email');

    $this->get('/invitations/tok-123')->assertOk()->assertSee('already has 2 editors');
});
