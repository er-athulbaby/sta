<?php

use App\Enums\Role;
use App\Filament\Portal\Resources\Invitations\Pages\ListInvitations;
use App\Filament\Portal\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Associations\Pages\ListAssociations;
use App\Mail\InvitationMail;
use App\Models\Association;
use App\Models\Invitation;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Mail;
use function Pest\Livewire\livewire;

beforeEach(fn () => Mail::fake());

it('lets STA invite the association admin from the list', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $assoc = Association::factory()->create(['email' => 'info@judo.bh']);

    livewire(ListAssociations::class)
        ->callAction(TestAction::make('invite')->table($assoc), data: ['email' => 'info@judo.bh'])
        ->assertNotified();

    expect(Invitation::sole())->role->toBe(Role::AssocAdmin)->email->toBe('info@judo.bh');
});

it('lets the association admin invite editors up to the cap', function () {
    $assoc = Association::factory()->create();
    actingInPortal(User::factory()->assocAdmin($assoc)->create());

    livewire(ListMembers::class)->callAction('inviteEditor', data: ['email' => 'e1@x.bh'])->assertNotified();
    livewire(ListMembers::class)->callAction('inviteEditor', data: ['email' => 'e2@x.bh'])->assertNotified();
    livewire(ListMembers::class)->callAction('inviteEditor', data: ['email' => 'e3@x.bh'])->assertNotified();

    expect(Invitation::where('role', Role::AssocEditor)->count())->toBe(2);
});

it('lets the association admin deactivate an editor', function () {
    $assoc = Association::factory()->create();
    actingInPortal(User::factory()->assocAdmin($assoc)->create());
    $editor = User::factory()->assocEditor($assoc)->create();

    livewire(ListMembers::class)->callAction(TestAction::make('deactivate')->table($editor));

    expect($editor->fresh()->is_active)->toBeFalse();
});

it('hides member management from editors', function () {
    $assoc = Association::factory()->create();
    $editor = User::factory()->assocEditor($assoc)->create();
    $this->actingAs($editor)->get("/portal/{$assoc->id}/members")->assertForbidden();
});

function twoTenants(): array
{
    $a = Association::factory()->create();
    $b = Association::factory()->create();
    $admin = User::factory()->assocAdmin($a)->create();
    $editorA = User::factory()->assocEditor($a)->create();
    $editorB = User::factory()->assocEditor($b)->create();
    $mk = fn (Association $x, string $email) => Invitation::create([
        'association_id' => $x->id, 'email' => $email, 'role' => Role::AssocEditor,
        'token_hash' => hash('sha256', $email), 'expires_at' => now()->addDays(7),
    ]);

    return [$a, $b, $admin, $editorA, $editorB, $mk($a, 'a@x.bh'), $mk($b, 'b@x.bh')];
}

it('scopes members to the current association', function () {
    [$a, $b, $admin, $editorA, $editorB] = twoTenants();
    actingInPortal($admin);

    livewire(ListMembers::class)
        ->assertCanSeeTableRecords([$editorA])
        ->assertCanNotSeeTableRecords([$editorB]);
});

it('scopes invitations to the current association', function () {
    [$a, $b, $admin, , , $invA, $invB] = twoTenants();
    actingInPortal($admin);

    livewire(ListInvitations::class)
        ->assertCanSeeTableRecords([$invA])
        ->assertCanNotSeeTableRecords([$invB]);
});

it('cannot deactivate another association\'s editor', function () {
    [$a, $b, $admin, , $editorB] = twoTenants();
    actingInPortal($admin);

    try {
        livewire(ListMembers::class)->callAction(TestAction::make('deactivate')->table($editorB));
    } catch (Throwable) {
    }

    expect($editorB->fresh()->is_active)->toBeTrue();
});

it('shows the cap message on the third editor invite', function () {
    $assoc = Association::factory()->create();
    actingInPortal(User::factory()->assocAdmin($assoc)->create());

    livewire(ListMembers::class)->callAction('inviteEditor', data: ['email' => 'e1@x.bh']);
    livewire(ListMembers::class)->callAction('inviteEditor', data: ['email' => 'e2@x.bh']);
    livewire(ListMembers::class)->callAction('inviteEditor', data: ['email' => 'e3@x.bh'])
        ->assertNotified('This association already has 2 editors (active or invited).');
});

it('resends an invitation as a new pending one and revokes the old', function () {
    [, , $admin, , , $invA] = twoTenants();
    actingInPortal($admin);

    livewire(ListInvitations::class)->callAction(TestAction::make('resend')->table($invA))->assertNotified();

    expect($invA->fresh()->status())->toBe('revoked')
        ->and(Invitation::where('email', 'a@x.bh')->get()->filter->isPending())->toHaveCount(1);
    Mail::assertQueued(InvitationMail::class);
});

it('revokes a pending invitation', function () {
    [, , $admin, , , $invA] = twoTenants();
    actingInPortal($admin);

    livewire(ListInvitations::class)->callAction(TestAction::make('revoke')->table($invA));

    expect($invA->fresh()->status())->toBe('revoked');
});

it('does not offer deactivate on the admin\'s own row', function () {
    $assoc = Association::factory()->create();
    $admin = User::factory()->assocAdmin($assoc)->create();
    actingInPortal($admin);

    livewire(ListMembers::class)->assertActionHidden(TestAction::make('deactivate')->table($admin));
});

it('re-checks the cap when activating an editor', function () {
    $assoc = Association::factory()->create();
    actingInPortal(User::factory()->assocAdmin($assoc)->create());
    User::factory()->assocEditor($assoc)->count(2)->create();
    $inactive = User::factory()->assocEditor($assoc)->inactive()->create();

    livewire(ListMembers::class)
        ->callAction(TestAction::make('activate')->table($inactive))
        ->assertNotified('This association already has 2 editors (active or invited).');

    expect($inactive->fresh()->is_active)->toBeFalse();
});

it('hides invitations from editors', function () {
    $assoc = Association::factory()->create();
    $editor = User::factory()->assocEditor($assoc)->create();
    $this->actingAs($editor)->get("/portal/{$assoc->id}/invitations")->assertForbidden();
});
