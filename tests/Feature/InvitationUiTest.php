<?php

use App\Enums\Role;
use App\Filament\Portal\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Associations\Pages\ListAssociations;
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
