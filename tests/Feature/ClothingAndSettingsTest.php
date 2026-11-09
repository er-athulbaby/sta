<?php

use App\Enums\AppliesTo;
use App\Enums\PersonType;
use App\Enums\StaPermission;
use App\Filament\Pages\Settings;
use App\Filament\Resources\ClothingItems\Pages\CreateClothingItem;
use App\Filament\Resources\ClothingItems\Pages\EditClothingItem;
use App\Models\ClothingItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

it('creates a clothing item with an ordered size list', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(CreateClothingItem::class)
        ->fillForm(['name' => 'T-Shirt', 'sizes' => ['S', 'M', 'L', 'XL'], 'applies_to' => AppliesTo::Both->value, 'is_active' => true])
        ->call('create')->assertHasNoFormErrors();

    expect(ClothingItem::sole()->sizes)->toBe(['S', 'M', 'L', 'XL']);
});

it('filters items by person type', function () {
    ClothingItem::factory()->create(['name' => 'Suit', 'applies_to' => AppliesTo::Official]);
    ClothingItem::factory()->create(['name' => 'Spikes', 'applies_to' => AppliesTo::Athlete]);
    ClothingItem::factory()->create(['name' => 'T-Shirt', 'applies_to' => AppliesTo::Both]);
    ClothingItem::factory()->create(['name' => 'Old', 'applies_to' => AppliesTo::Both, 'is_active' => false]);

    expect(ClothingItem::active()->forType(PersonType::Official)->pluck('name')->sort()->values()->all())->toBe(['Suit', 'T-Shirt']);
});

it('never offers deleting a clothing item (BR-16)', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $item = ClothingItem::factory()->create();

    livewire(EditClothingItem::class, ['record' => $item->getKey()])->assertActionDoesNotExist('delete');
});

it('saves the declaration text in settings', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(Settings::class)->fillForm(['pdf_declaration' => 'I confirm all information is accurate.'])->call('save')->assertHasNoFormErrors();

    expect(Setting::get(Setting::DECLARATION))->toBe('I confirm all information is accurate.');
});

it('gates settings and clothing items behind manage_master_data', function () {
    $sta = User::factory()->sta()->create();

    $this->actingAs($sta)->get('/admin/settings')->assertForbidden();
    $this->actingAs($sta)->get('/admin/clothing-items')->assertForbidden();

    $sta->givePermissionTo(StaPermission::ManageMasterData->value);
    $sta = $sta->fresh();

    $this->actingAs($sta)->get('/admin/settings')->assertOk();
    $this->actingAs($sta)->get('/admin/clothing-items')->assertOk();
});

it('stores and clears the NOC logo', function () {
    Storage::fake(config('filesystems.private'));
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(Settings::class)
        ->fillForm(['noc_logo_path' => UploadedFile::fake()->image('noc.png'), 'pdf_declaration' => 'Declaration'])
        ->call('save')->assertHasNoFormErrors();

    $path = Setting::get(Setting::NOC_LOGO);
    expect($path)->not->toBeEmpty();
    Storage::disk(config('filesystems.private'))->assertExists($path);

    livewire(Settings::class)
        ->fillForm(['noc_logo_path' => [], 'pdf_declaration' => 'Declaration'])
        ->call('save')->assertHasNoFormErrors();

    expect(Setting::get(Setting::NOC_LOGO))->toBeNull();
});

it('keeps the stored NOC logo on save and refuses a tampered logo path', function () {
    Storage::fake(config('filesystems.private'));
    Storage::disk(config('filesystems.private'))->put('settings/noc.png', 'png');
    Storage::disk(config('filesystems.private'))->put('scans/victim.pdf', 'secret');
    Setting::put(Setting::NOC_LOGO, 'settings/noc.png');
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(Settings::class)->fillForm(['pdf_declaration' => 'Changed'])->call('save')->assertHasNoFormErrors();
    expect(Setting::get(Setting::NOC_LOGO))->toBe('settings/noc.png');

    livewire(Settings::class)->set('data.noc_logo_path', ['x' => 'scans/victim.pdf'])->call('save')
        ->assertHasFormErrors(['noc_logo_path']);
    expect(Setting::get(Setting::NOC_LOGO))->toBe('settings/noc.png');
});
