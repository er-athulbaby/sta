<?php

use App\Enums\StaPermission;
use App\Filament\Portal\Auth\EditMyProfile;
use App\Filament\Portal\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Associations\Pages\ListAssociations;
use App\Models\Association;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

/** Final wave item 11: own Profile for every association user; admins manage their editors; STA can do the same. */
beforeEach(function () {
    Notification::fake();
    $this->assoc = Association::factory()->create();
    $this->admin = User::factory()->assocAdmin($this->assoc)->create(['name' => 'Mariam Admin', 'password' => Hash::make('old-password')]);
    $this->editor = User::factory()->assocEditor($this->assoc)->create(['name' => 'Ehab Editor', 'password' => Hash::make('old-password')]);
});

it('shows Profile in the portal user menu for admins and editors', function (string $who) {
    actingInPortal($this->{$who});
    $html = $this->get("/portal/{$this->assoc->id}")->assertOk()->getContent();
    expect($html)->toMatch('#href="http://localhost/portal/my-profile"[^>]*role="menuitem"(?:(?!</a>).)*Profile#s');
    $this->get('/portal/my-profile')->assertOk()->assertSee($this->{$who}->name);
})->with(['admin', 'editor']);

it('keeps the association profile route apart from the personal profile', function () {
    expect(route('filament.portal.auth.profile'))->toEndWith('/portal/my-profile')
        ->and(route('filament.portal.tenant.profile', ['tenant' => $this->assoc]))->toEndWith("/portal/{$this->assoc->id}/profile");
    actingInPortal($this->admin);
    $this->get("/portal/{$this->assoc->id}/profile")->assertOk()->assertSee('Association profile');
    $this->get('/portal/my-profile')->assertOk()->assertSee('Mariam Admin')->assertSee('Profile');
});

it('lets every association user edit their own name, email and password, with the current password', function (string $who) {
    $user = $this->{$who};
    actingInPortal($user);

    livewire(EditMyProfile::class)
        ->fillForm(['name' => 'New Name', 'email' => 'new@example.com'])->call('save')->assertHasFormErrors(['currentPassword'])
        ->fillForm(['name' => 'New Name', 'email' => 'new@example.com', 'password' => 'new-password-1', 'passwordConfirmation' => 'new-password-1', 'currentPassword' => 'old-password'])
        ->call('save')->assertHasNoFormErrors();

    $user->refresh();
    expect($user->name)->toBe('New Name')->and($user->email)->toBe('new@example.com')->and(Hash::check('new-password-1', $user->password))->toBeTrue();
})->with(['admin', 'editor']);

it('lets the association admin deactivate, reactivate and reset the password of its editors only', function () {
    actingInPortal($this->admin);

    livewire(ListMembers::class)
        ->assertActionHidden(TestAction::make('deactivate')->table($this->admin))
        ->assertActionHidden(TestAction::make('sendPasswordReset')->table($this->admin))
        ->callAction(TestAction::make('sendPasswordReset')->table($this->editor))
        ->assertNotified("Password reset link sent to {$this->editor->email}")
        ->callAction(TestAction::make('deactivate')->table($this->editor));
    expect($this->editor->fresh()->is_active)->toBeFalse();
    Notification::assertSentTo($this->editor, ResetPassword::class, fn ($n) => str_contains($n->url, '/portal/password-reset/reset'));

    livewire(ListMembers::class)->callAction(TestAction::make('activate')->table($this->editor));
    expect($this->editor->fresh()->is_active)->toBeTrue();
});

it('never lets an association admin act on another admin of its own association', function () {
    $second = User::factory()->assocAdmin($this->assoc)->create(['name' => 'Second Admin']);
    actingInPortal($this->admin);

    livewire(ListMembers::class)->assertCanSeeTableRecords([$second])
        ->assertActionHidden(TestAction::make('deactivate')->table($second))
        ->assertActionHidden(TestAction::make('sendPasswordReset')->table($second));
});

it('never lets an association admin act on another association\'s user or an editor use the Team page', function () {
    $foreign = User::factory()->assocEditor(Association::factory()->create())->create();
    actingInPortal($this->admin);

    livewire(ListMembers::class)->assertCanNotSeeTableRecords([$foreign]);
    expect(fn () => livewire(ListMembers::class)->callAction(TestAction::make('deactivate')->table($foreign)))->toThrow(Exception::class);
    expect($foreign->fresh()->is_active)->toBeTrue();

    actingInPortal($this->editor);
    $this->get(ListMembers::getUrl(tenant: $this->assoc))->assertForbidden();
});

it('lets STA with manage_associations deactivate and reset any association user', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ManageAssociations->value);
    actingInAdmin($sta);

    livewire(ListAssociations::class)
        ->callAction('resetUserPassword', arguments: ['id' => $this->admin->id])
        ->callAction('deactivateUser', arguments: ['id' => $this->admin->id]);

    expect($this->admin->fresh()->is_active)->toBeFalse();
    Notification::assertSentTo($this->admin, ResetPassword::class, fn ($n) => str_contains($n->url, '/portal/password-reset/reset'));
});

it('refuses a deactivated association user at the portal login', function () {
    $this->editor->update(['is_active' => false]);
    Filament::setCurrentPanel('portal');

    livewire(Login::class)->fillForm(['email' => $this->editor->email, 'password' => 'old-password'])->call('authenticate')
        ->assertHasFormErrors(['email']);
    $this->assertGuest();
});
