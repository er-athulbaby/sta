<?php

use App\Enums\Role;
use App\Filament\Portal\Auth\EditMyProfile;
use App\Filament\Portal\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\StaUsers\Pages\CreateStaUser;
use App\Filament\Resources\StaUsers\Pages\EditStaUser;
use App\Filament\Shared\EditUserProfile;
use App\Models\Association;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake(config('filesystems.private'));
    $this->disk = Storage::disk(config('filesystems.private'));
    $this->assoc = Association::factory()->create();
});

it('saves photo and mobile on the own profile of an STA user and an association editor', function (string $who) {
    $user = $who === 'sta'
        ? actingInAdmin(User::factory()->superAdmin()->create(['password' => Hash::make('old-password')]))
        : actingInPortal(User::factory()->assocEditor($this->assoc)->create(['password' => Hash::make('old-password')]));

    livewire($who === 'sta' ? EditUserProfile::class : EditMyProfile::class)
        ->fillForm(['photo_path' => UploadedFile::fake()->image('me.jpg'), 'phone_cc' => '+966', 'phone' => '3300 0020'])
        ->call('save')->assertHasNoFormErrors();

    $user->refresh();
    expect($user->photo_path)->toStartWith('user-photos/')->and($this->disk->exists($user->photo_path))->toBeTrue()
        ->and($user->mobile())->toBe('+966 3300 0020');
})->with(['sta', 'editor']);

it('rejects a malformed mobile on the profile', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(EditUserProfile::class)->fillForm(['phone' => 'abc'])->call('save')->assertHasFormErrors(['phone']);
});

it('deletes the old photo file when the photo is replaced or removed', function () {
    $user = User::factory()->superAdmin()->create(['photo_path' => 'user-photos/old.jpg']);
    $this->disk->put('user-photos/old.jpg', 'x');
    $this->disk->put('user-photos/new.jpg', 'x');

    $user->update(['photo_path' => 'user-photos/new.jpg']);
    expect($this->disk->exists('user-photos/old.jpg'))->toBeFalse()->and($this->disk->exists('user-photos/new.jpg'))->toBeTrue();

    $user->update(['photo_path' => null]);
    expect($this->disk->exists('user-photos/new.jpg'))->toBeFalse();
});

it('gives no avatar URL without a photo (initials) and a signed link with one', function () {
    $user = User::factory()->superAdmin()->create();
    expect($user->getFilamentAvatarUrl())->toBeNull()
        ->and(filament()->getUserAvatarUrl($user))->toStartWith('data:image/svg+xml');

    $user->update(['photo_path' => 'user-photos/me.jpg']);
    expect($user->getFilamentAvatarUrl())->toContain('user-photos/me.jpg')->toContain('expiration='); // the faked disk leaves out the signature
});

it('shows the photo in the sidebar user block', function () {
    $user = actingInAdmin(User::factory()->superAdmin()->create(['photo_path' => 'user-photos/me.jpg']));
    $this->get('/admin')->assertOk()->assertSee('user-photos/me.jpg', false);
});

it('saves mobile and photo on the STA user form', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(CreateStaUser::class)
        ->fillForm(['name' => 'Fatima', 'email' => 'fatima@sta.bh', 'password' => 'a-long-password-1', 'role' => Role::SuperAdmin->value,
            'phone_cc' => '+973', 'phone' => '3900 1122', 'photo_path' => UploadedFile::fake()->image('f.png')])
        ->call('create')->assertHasNoFormErrors();

    $user = User::firstWhere('email', 'fatima@sta.bh');
    expect($user->mobile())->toBe('+973 3900 1122')->and($user->photo_path)->toStartWith('user-photos/');

    livewire(EditStaUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['phone' => ''])->call('save')->assertHasNoFormErrors();
    expect($user->fresh()->mobile())->toBeNull();
});

it('shows mobiles on the portal Team list and lets the admin edit an editor mobile', function () {
    $admin = actingInPortal(User::factory()->assocAdmin($this->assoc)->create());
    $editor = User::factory()->assocEditor($this->assoc)->create(['phone' => '3300 0020']);

    livewire(ListMembers::class)->assertSee('+973 3300 0020')
        ->assertActionHidden(TestAction::make('editMobile')->table($admin))
        ->callAction(TestAction::make('editMobile')->table($editor), ['phone_cc' => '+971', 'phone' => '5011 2233'])
        ->assertHasNoFormErrors();
    expect($editor->fresh()->mobile())->toBe('+971 5011 2233');
});

it('stores the mobile given when accepting an invitation', function () {
    Mail::fake();
    $url = app(InvitationService::class)->invite($this->assoc, 'new@club.bh', Role::AssocEditor, null);
    expect(Invitation::count())->toBe(1);

    $this->post($url, ['name' => 'Noor', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1',
        'phone_cc' => '+973', 'phone' => '3600 4455'])->assertRedirect();
    expect(User::firstWhere('email', 'new@club.bh')->mobile())->toBe('+973 3600 4455');
});
