<?php

use App\Enums\PersonType;
use App\Enums\StaPermission;
use App\Exports\PeopleTemplateExport;
use App\Filament\Portal\Resources\People\Pages\CreatePerson;
use App\Filament\Portal\Resources\People\Pages\EditPerson;
use App\Filament\Portal\Resources\People\Pages\ListPeople;
use App\Filament\Resources\People\Pages\CreatePerson as AdminCreatePerson;
use App\Filament\Resources\Relationships\Pages\CreateRelationship;
use App\Filament\Resources\Relationships\Pages\EditRelationship;
use App\Filament\Resources\Relationships\Pages\ListRelationships;
use App\Filament\Resources\Relationships\RelationshipResource;
use App\Models\Association;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use App\Services\PeopleImporter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake(config('filesystems.private'));
    $this->assoc = Association::factory()->create();
});

function crossCheckAthlete(array $overrides = []): array
{
    return array_merge([
        'type' => PersonType::Athlete->value, 'first_name' => 'Ali', 'last_name' => 'Hassan',
        'dob' => '2006-01-01', 'gender' => 'male', 'nationality' => 'Bahrain',
        'passport_no' => 'BH7654321', 'passport_expiry' => now()->addYears(2)->toDateString(),
    ], $overrides);
}

// Item 6: cross-check tick

it('refuses to create a person until the cross-check is ticked, then stores who ticked it and when', function () {
    $editor = actingInPortal(User::factory()->assocEditor($this->assoc)->create(['name' => 'Mona Editor']));
    Carbon::setTestNow('2026-11-22 10:00');

    livewire(CreatePerson::class)->assertSee(Person::CROSS_CHECK_LABEL)
        ->fillForm(crossCheckAthlete())->call('create')
        ->assertHasFormErrors(['cross_checked' => 'accepted'])->assertSee(Person::CROSS_CHECK_MESSAGE);
    expect(Person::count())->toBe(0);

    livewire(CreatePerson::class)->fillForm([...crossCheckAthlete(), 'cross_checked' => true])->call('create')->assertHasNoFormErrors();

    $p = Person::sole();
    expect($p->checked_by)->toBe($editor->id)->and($p->checked_at->toDateTimeString())->toBe('2026-11-22 10:00:00')
        ->and($p->crossCheckNote())->toBe('Cross-checked by Mona Editor on 22 Nov 2026');
});

it('never pre-ticks the cross-check on edit, needs it on every save and shows who checked last in the header', function () {
    $first = User::factory()->assocAdmin($this->assoc)->create(['name' => 'Ahmed First']);
    $editor = actingInPortal(User::factory()->assocEditor($this->assoc)->create(['name' => 'Mona Editor']));
    $p = Person::factory()->create(['association_id' => $this->assoc->id, 'checked_by' => $first->id, 'checked_at' => '2026-11-01 09:00']);

    $page = livewire(EditPerson::class, ['record' => $p->getRouteKey()])
        ->assertFormSet(['cross_checked' => null])->assertSee('Cross-checked by Ahmed First on 1 Nov 2026');

    $page->fillForm(['first_name' => 'Changed'])->call('save')->assertHasFormErrors(['cross_checked' => 'accepted']);
    expect($p->fresh()->first_name)->not->toBe('Changed');

    Carbon::setTestNow('2026-11-22 11:30');
    $page->set('data.cross_checked', true)->call('save')->assertHasNoFormErrors()
        ->assertFormSet(['cross_checked' => false])->assertSee('Cross-checked by Mona Editor on 22 Nov 2026');
    expect($p->fresh())->first_name->toBe('Changed')->checked_by->toBe($editor->id);

    // "Save as draft" again without a new tick is refused.
    $page->call('save')->assertHasFormErrors(['cross_checked' => 'accepted']);
});

it('stamps the STA user who cross-checks in the admin form', function () {
    $sta = actingInAdmin(User::factory()->sta()->create());
    $sta->givePermissionTo(StaPermission::ManagePeople->value);

    livewire(AdminCreatePerson::class)->fillForm([...crossCheckAthlete(), 'association_id' => $this->assoc->id])->call('create')
        ->assertHasFormErrors(['cross_checked' => 'accepted']);
    livewire(AdminCreatePerson::class)->fillForm([...crossCheckAthlete(), 'association_id' => $this->assoc->id, 'cross_checked' => true])->call('create')
        ->assertHasNoFormErrors();

    expect(Person::sole()->checked_by)->toBe($sta->id);
});

it('needs the cross-check in the registry aside and resets it after saving', function () {
    $editor = actingInPortal(User::factory()->assocEditor($this->assoc)->create(['name' => 'Mona Editor']));
    $p = Person::factory()->create(['association_id' => $this->assoc->id]);

    $page = livewire(ListPeople::class)->call('selectPerson', $p->id)->assertSee(Person::CROSS_CHECK_LABEL)
        ->set('panel.passport_no', 'NEW55555')->call('savePanel')->assertHasErrors(['panel.cross_checked' => 'accepted']);
    expect($p->fresh()->checked_at)->toBeNull();

    $page->set('panel.cross_checked', true)->call('savePanel')->assertHasNoErrors()
        ->assertSet('panel.cross_checked', null)->assertSee('Cross-checked by Mona Editor on '.now()->format('j M Y'));
    expect($p->fresh())->passport_no->toBe('NEW55555')->checked_by->toBe($editor->id);
});

it('imports rows as not cross-checked', function () {
    $dir = storage_path('app/testing');
    is_dir($dir) || mkdir($dir, 0777, true);
    $path = $dir.'/cross-check-import-'.uniqid().'.xlsx';
    (new PeopleTemplateExport([[
        'type' => 'athlete', 'full_name' => 'Sara Ali', 'first_name' => 'Sara', 'second_name' => '', 'third_name' => '', 'last_name' => 'Ali',
        'date_of_birth' => '2007-04-02', 'gender' => 'female', 'nationality' => 'Bahrain', 'height_cm' => 165, 'weight_kg' => 55,
        'passport_no' => 'BH1111111', 'passport_issuing_country' => 'Bahrain', 'passport_issue_date' => '2023-01-01', 'passport_expiry' => '2029-01-01',
        'cpr_no' => '070402111', 'cpr_expiry' => '2028-01-01', 'function' => '', 'mobile' => '', 'email' => '',
    ]]))->writeTo($path);

    expect(app(PeopleImporter::class)->import($path, $this->assoc)->ok())->toBeTrue()
        ->and(Person::sole())->checked_by->toBeNull()->checked_at->toBeNull();
    @unlink($path);
});

// Item 9: Relationships master data

it('seeds the relationship list in order', function () {
    expect(array_keys(Relationship::options()))->toBe(['Father', 'Mother', 'Brother', 'Sister', 'Spouse', 'Son', 'Daughter', 'Uncle', 'Aunt',
        'Cousin', 'Guardian', 'Friend', 'Coach', 'Other']);
});

it('lets only manage_master_data users manage relationships, and never deletes them', function () {
    $sta = actingInAdmin(User::factory()->sta()->create());
    $this->get(RelationshipResource::getUrl('index'))->assertForbidden();

    $sta->givePermissionTo(StaPermission::ManageMasterData->value);
    $this->get(RelationshipResource::getUrl('index'))->assertOk()->assertSee('Relationships');
    livewire(ListRelationships::class)->loadTable()->assertCanSeeTableRecords(Relationship::orderBy('sort_order')->take(5)->get(), inOrder: true);

    livewire(CreateRelationship::class)->fillForm(['name' => 'Father', 'sort_order' => 1])->call('create')->assertHasFormErrors(['name' => 'unique']);
    livewire(CreateRelationship::class)->fillForm(['name' => 'Grandparent', 'sort_order' => 0, 'is_active' => true])->call('create')->assertHasNoFormErrors();
    expect(array_key_first(Relationship::options()))->toBe('Grandparent');

    livewire(EditRelationship::class, ['record' => Relationship::where('name', 'Coach')->value('id')])->assertActionDoesNotExist('delete')
        ->fillForm(['is_active' => false])->call('save')->assertHasNoFormErrors();
    expect(Relationship::options())->not->toHaveKey('Coach');
});

it('offers the active relationships as dropdowns for emergency contact and guardian, storing the name', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    Relationship::where('name', 'Friend')->update(['is_active' => false]);

    $page = livewire(CreatePerson::class);
    foreach (['emergency_relationship', 'guardian_relationship'] as $field) {
        $page->assertFormFieldExists($field, fn ($select) => array_keys($select->getOptions()) === array_keys(Relationship::options())
            && ! array_key_exists('Friend', $select->getOptions()) && array_key_exists('Guardian', $select->getOptions()));
    }

    $page->fillForm([...crossCheckAthlete(), 'emergency_relationship' => 'Friend', 'cross_checked' => true])->call('create')
        ->assertHasFormErrors(['emergency_relationship' => 'in']);
    $page->fillForm([...crossCheckAthlete(), 'emergency_relationship' => 'Mother', 'guardian_relationship' => 'Uncle', 'cross_checked' => true])->call('create')
        ->assertHasNoFormErrors();

    expect(Person::sole())->emergency_relationship->toBe('Mother')->guardian_relationship->toBe('Uncle');
});

it('keeps an existing free-text relationship that is not on the list', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    $p = Person::factory()->create(['association_id' => $this->assoc->id, 'emergency_relationship' => 'Parent', 'guardian_relationship' => 'Legal guardian']);

    livewire(EditPerson::class, ['record' => $p->getRouteKey()])
        ->assertFormSet(['emergency_relationship' => 'Parent', 'guardian_relationship' => 'Legal guardian'])
        ->assertFormFieldExists('emergency_relationship', fn ($select) => array_key_exists('Parent', $select->getOptions()))
        ->set('data.cross_checked', true)->call('save')->assertHasNoFormErrors();

    expect($p->fresh())->emergency_relationship->toBe('Parent')->guardian_relationship->toBe('Legal guardian');
});
