<?php

use App\Enums\StaPermission;
use App\Filament\Portal\Pages\EditAssociationProfile;
use App\Filament\Resources\Associations\Pages\CreateAssociation;
use App\Models\Association;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Livewire\livewire;

it('lets STA with permission create an association with a private logo', function () {
    Storage::fake(config('filesystems.private'));
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ManageAssociations->value);
    actingInAdmin($sta);

    livewire(CreateAssociation::class)
        ->fillForm(['name' => 'Bahrain Athletics', 'email' => 'info@bah-athletics.bh', 'logo_path' => UploadedFile::fake()->image('logo.png')])
        ->call('create')
        ->assertHasNoFormErrors();

    $assoc = Association::firstWhere('name', 'Bahrain Athletics');
    Storage::disk(config('filesystems.private'))->assertExists($assoc->logo_path);
});

it('requires name and email', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(CreateAssociation::class)->fillForm(['name' => null, 'email' => null])->call('create')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'required']);
});

it('forbids STA without the permission', function () {
    $this->actingAs(User::factory()->sta()->create())->get('/admin/associations')->assertForbidden();
});

it('lets the association admin edit the profile but not the name', function () {
    $assoc = Association::factory()->create(['name' => 'Squash']);
    actingInPortal(User::factory()->assocAdmin($assoc)->create());

    livewire(EditAssociationProfile::class)
        ->fillForm(['phone' => '+973 1700 0000'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($assoc->fresh()->phone)->toBe('+973 1700 0000')->and($assoc->fresh()->name)->toBe('Squash');
});

it('blocks editors from the profile page', function () {
    $assoc = Association::factory()->create();
    $editor = User::factory()->assocEditor($assoc)->create();
    $this->actingAs($editor)->get("/portal/{$assoc->id}/profile")->assertNotFound();
});
