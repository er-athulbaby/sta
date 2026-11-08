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
    livewire(\App\Filament\Resources\People\Pages\ListPeople::class)->assertCountTableRecords(2);
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
    livewire(\App\Filament\Resources\People\Pages\ListPeople::class)->assertTableActionHidden('delete', $person);

    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(\App\Filament\Resources\People\Pages\ListPeople::class)->assertTableActionVisible('delete', $person);
});

it('edits a person keeping their own passport and syncs sizes', function () {
    $mine = Person::factory()->create(['association_id' => $this->assoc->id, 'passport_no' => 'BH1111111']);

    livewire(EditPerson::class, ['record' => $mine->getRouteKey()])
        ->fillForm(['first_name' => 'Changed', "sizes.{$this->shirt->id}" => 'L'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($mine->refresh()->first_name)->toBe('Changed')->and($mine->sizesMap())->toBe([$this->shirt->id => 'L']);
});
