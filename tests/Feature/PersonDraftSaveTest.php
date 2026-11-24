<?php

use App\Enums\PersonType;
use App\Filament\Portal\Resources\People\Pages\CreatePerson;
use App\Filament\Portal\Resources\People\Pages\EditPerson;
use App\Models\Association;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake(config('filesystems.private'));
    $this->assoc = Association::factory()->create();
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
});

it('saves a draft with only the names, leaves it Incomplete and without a cross-check stamp', function () {
    livewire(CreatePerson::class)->fillForm(['type' => PersonType::Athlete->value, 'first_name' => 'Ali', 'last_name' => 'Hassan'])
        ->call('saveDraft')->assertHasNoFormErrors();

    $p = Person::sole();
    expect($p->dob)->toBeNull()->and($p->passport_no)->toBeNull()->and($p->checked_at)->toBeNull()->and($p->age())->toBeNull()
        ->and($p->isComplete())->toBeFalse()
        ->and($p->missingFields())->toContain('Date of birth', 'Gender', 'Nationality', 'Passport number', 'Passport expiry')
        ->and($p->missingFields())->not->toContain('CPR number');
});

it('still needs the names on a draft, and everything on the final Save', function () {
    livewire(CreatePerson::class)->fillForm(['type' => PersonType::Athlete->value, 'first_name' => '', 'last_name' => ''])
        ->call('saveDraft')->assertHasFormErrors(['first_name' => 'required', 'last_name' => 'required']);

    livewire(CreatePerson::class)->fillForm(['type' => PersonType::Athlete->value, 'first_name' => 'Ali', 'last_name' => 'Hassan'])
        ->call('create')->assertHasFormErrors(['dob' => 'required', 'gender' => 'required', 'nationality' => 'required',
            'passport_no' => 'required', 'passport_expiry' => 'required', 'cross_checked' => 'accepted']);
    expect(Person::count())->toBe(0);
});

it('lets two drafts without a passport number exist side by side', function () {
    foreach (['Ali', 'Sara'] as $name) {
        livewire(CreatePerson::class)->fillForm(['type' => PersonType::Athlete->value, 'first_name' => $name, 'last_name' => 'Test'])
            ->call('saveDraft')->assertHasNoFormErrors();
    }
    expect(Person::count())->toBe(2);
});

it('keeps an existing cross-check stamp on a draft save of a saved person', function () {
    $p = Person::factory()->create(['association_id' => $this->assoc->id, 'checked_at' => '2026-11-01 09:00']);

    livewire(EditPerson::class, ['record' => $p->getRouteKey()])->fillForm(['first_name' => 'Changed', 'dob' => null])
        ->call('saveDraft')->assertHasNoFormErrors();

    expect($p->fresh())->first_name->toBe('Changed')->dob->toBeNull()->checked_at->not->toBeNull();
});
