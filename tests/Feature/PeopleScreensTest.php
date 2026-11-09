<?php

use App\Enums\AppliesTo;
use App\Enums\PersonType;
use App\Enums\StaPermission;
use App\Filament\Portal\Resources\People\Pages\CreatePerson;
use App\Filament\Portal\Resources\People\Pages\EditPerson;
use App\Filament\Portal\Resources\People\Pages\ListPeople;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake(config('filesystems.private'));
    $this->assoc = Association::factory()->create();
    $this->editor = actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    $this->shirt = ClothingItem::factory()->create(['name' => 'T-Shirt', 'sizes' => ['S', 'M', 'L'], 'applies_to' => AppliesTo::Both]);
});

function athleteData(array $overrides = []): array
{
    return array_merge([
        'type' => PersonType::Athlete->value, 'first_name' => 'Ali', 'last_name' => 'Hassan',
        'dob' => '2006-01-01', 'gender' => 'male', 'nationality' => 'Bahrain',
        'passport_no' => 'BH7654321', 'passport_expiry' => now()->addYears(2)->toDateString(),
    ], $overrides);
}

/** The creating hook pins association users' rows to their own association, so move the row afterwards. */
function foreignPerson(array $attrs = []): Person
{
    $person = Person::factory()->create($attrs);
    DB::table('people')->where('id', $person->id)->update(['association_id' => Association::factory()->create()->id]);

    return $person->refresh();
}

it('creates an athlete with photo and clothing size in the current association', function () {
    livewire(CreatePerson::class)
        ->fillForm(athleteData(['photo_path' => UploadedFile::fake()->image('ali.jpg'), "sizes.{$this->shirt->id}" => 'M']))
        ->call('create')
        ->assertHasNoFormErrors();

    $p = Person::sole();
    expect($p->association_id)->toBe($this->assoc->id)->and($p->sizesMap())->toBe([$this->shirt->id => 'M']);
    Storage::disk(config('filesystems.private'))->assertExists($p->photo_path);
});

it('requires function for team officials', function () {
    livewire(CreatePerson::class)->fillForm(athleteData(['type' => PersonType::Official->value]))->call('create')
        ->assertHasFormErrors(['function' => 'required']);
});

it('rejects a passport already registered by another association', function () {
    foreignPerson(['passport_no' => 'BH7654321']);
    livewire(CreatePerson::class)->fillForm(athleteData())->call('create')->assertHasFormErrors(['passport_no']);
});

it('never shows or opens another association\'s people', function () {
    $mine = Person::factory()->create(['association_id' => $this->assoc->id]);
    $theirs = foreignPerson();

    livewire(ListPeople::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs]);
    $this->get("/portal/{$this->assoc->id}/people/{$theirs->id}/edit")->assertNotFound();
});

it('lets STA with manage_people see all people, and blocks others', function () {
    Person::factory()->count(2)->create();
    $sta = User::factory()->sta()->create();
    $this->actingAs($sta)->get('/admin/people')->assertForbidden();

    $sta->givePermissionTo(StaPermission::ManagePeople->value);
    actingInAdmin($sta);
    livewire(App\Filament\Resources\People\Pages\ListPeople::class)->assertCountTableRecords(2);
});

it('offers no delete on the portal', function () {
    $mine = Person::factory()->create(['association_id' => $this->assoc->id]);

    livewire(EditPerson::class, ['record' => $mine->getRouteKey()])->assertActionDoesNotExist('delete');
    livewire(ListPeople::class)->assertTableActionDoesNotExist('delete', null, $mine);
});

it('shows admin row delete to super admins only', function () {
    $person = Person::factory()->create();
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ManagePeople->value);

    actingInAdmin($sta);
    livewire(App\Filament\Resources\People\Pages\ListPeople::class)->assertTableActionHidden('delete', $person);

    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(App\Filament\Resources\People\Pages\ListPeople::class)->assertTableActionVisible('delete', $person);
});

it('edits a person keeping their own passport and syncs sizes', function () {
    $mine = Person::factory()->create(['association_id' => $this->assoc->id, 'passport_no' => 'BH1111111']);

    livewire(EditPerson::class, ['record' => $mine->getRouteKey()])
        ->fillForm(['first_name' => 'Changed', "sizes.{$this->shirt->id}" => 'L'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($mine->refresh()->first_name)->toBe('Changed')->and($mine->sizesMap())->toBe([$this->shirt->id => 'L']);
});

it('removes athlete-only sizes when editing a person to official', function () {
    $kit = ClothingItem::factory()->create(['sizes' => ['S', 'M'], 'applies_to' => AppliesTo::Athlete]);
    $mine = Person::factory()->create(['association_id' => $this->assoc->id]);
    $mine->syncSizes([$this->shirt->id => 'M', $kit->id => 'S']);

    livewire(EditPerson::class, ['record' => $mine->getRouteKey()])
        ->fillForm(['type' => PersonType::Official->value, 'function' => 'Coach'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($mine->refresh()->sizesMap())->toBe([$this->shirt->id => 'M']);
});

it('creates no person when a size is invalid', function () {
    // Filament's Select rule rejects it first; the DB transaction in SavesPersonSizes is the backstop for syncSizes failures.
    livewire(CreatePerson::class)->fillForm(athleteData(["sizes.{$this->shirt->id}" => 'XXL']))->call('create')
        ->assertHasFormErrors(["sizes.{$this->shirt->id}"]);

    expect(Person::count())->toBe(0);
});

it('refuses a tampered passport scan path pointing at another association\'s file', function () {
    Storage::disk(config('filesystems.private'))->put('scans/victim.pdf', 'secret');
    $victim = foreignPerson(['passport_scan_path' => 'scans/victim.pdf'])->passport_scan_path; // renamed on save
    $mine = Person::factory()->create(['association_id' => $this->assoc->id, 'passport_scan_path' => null]);

    livewire(EditPerson::class, ['record' => $mine->getRouteKey()])
        ->set('data.passport_scan_path', ['x' => $victim])
        ->call('save')
        ->assertHasFormErrors(['passport_scan_path']);

    expect($mine->refresh()->passport_scan_path)->toBeNull();
    Storage::disk(config('filesystems.private'))->assertExists($victim);
});

it('keeps a person\'s own stored files when saving', function () {
    $disk = Storage::disk(config('filesystems.private'));
    $disk->put('scans/mine.pdf', 'pdf');
    $disk->put('photos/mine.png', 'png');
    $mine = Person::factory()->create(['association_id' => $this->assoc->id, 'passport_scan_path' => 'scans/mine.pdf', 'photo_path' => 'photos/mine.png']);

    livewire(EditPerson::class, ['record' => $mine->getRouteKey()])
        ->fillForm(['first_name' => 'Changed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($mine->refresh()->passport_scan_path)->toBe("scans/{$mine->code}_passport.pdf")->and($mine->photo_path)->toBe("photos/{$mine->code}_photo.png");
});

it('rejects unsafe or oversized uploads', function (string $field, Closure $file) {
    livewire(CreatePerson::class)->fillForm(athleteData([$field => $file()]))->call('create')
        ->assertHasFormErrors([$field]);

    expect(Person::count())->toBe(0);
})->with([
    'svg photo' => ['photo_path', fn () => UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml')],
    'photo over 2 MB' => ['photo_path', fn () => UploadedFile::fake()->image('big.png')->size(3000)],
    'exe passport scan' => ['passport_scan_path', fn () => UploadedFile::fake()->create('x.exe', 5, 'application/x-msdownload')],
]);

it('can save twice on the edit page after an upload is renamed', function () {
    $mine = Person::factory()->create(['association_id' => $this->assoc->id, 'passport_scan_path' => null]);

    $page = livewire(EditPerson::class, ['record' => $mine->getRouteKey()])
        ->fillForm(['passport_scan_path' => UploadedFile::fake()->create('p.pdf', 5, 'application/pdf')])
        ->call('save')
        ->assertHasNoFormErrors();
    $stored = "scans/{$mine->refresh()->code}_passport.pdf";
    expect($mine->passport_scan_path)->toBe($stored);

    $page->call('save')->assertHasNoFormErrors();
    expect($mine->refresh()->passport_scan_path)->toBe($stored);
    Storage::disk(config('filesystems.private'))->assertExists($stored);
});
