<?php

use App\Enums\AppliesTo;
use App\Enums\ExtraFieldType;
use App\Enums\StaPermission;
use App\Filament\Portal\Resources\People\Pages\CreatePerson;
use App\Filament\Resources\People\Pages\EditPerson as AdminEditPerson;
use App\Models\Association;
use App\Models\ExtraField;
use App\Models\Person;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->assoc = Association::factory()->create(['code' => 'BWF']);
    $this->shirt = ExtraField::factory()->create(['label' => 'Shirt name', 'applies_to' => AppliesTo::Athlete, 'required' => true]);
    $this->weight = ExtraField::factory()->create(['label' => 'Weight class', 'type' => ExtraFieldType::Number]);
    $this->blood = ExtraField::factory()->create(['label' => 'Donor type', 'type' => ExtraFieldType::Choice, 'options' => ['A', 'B'], 'sensitive' => true]);
    $this->onDuty = ExtraField::factory()->create(['label' => 'On duty', 'type' => ExtraFieldType::YesNo, 'applies_to' => AppliesTo::Official]);
    $this->old = ExtraField::factory()->create(['label' => 'Old field', 'required' => true, 'is_active' => false]);
});

function extraAthlete(array $o = []): array
{
    return array_merge(['type' => 'athlete', 'first_name' => 'Noor', 'last_name' => 'Alsayed', 'dob' => '2006-05-27', 'gender' => 'female',
        'nationality' => 'Bahrain', 'passport_no' => 'BH7777771', 'passport_expiry' => now()->addYears(3)->toDateString()], $o);
}

it('shows the active fields of the person type in the portal and saves their values', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());

    livewire(CreatePerson::class)
        ->assertFormFieldExists("extra.{$this->shirt->id}")->assertFormFieldExists("extra.{$this->blood->id}")
        ->assertFormFieldDoesNotExist("extra.{$this->onDuty->id}")->assertFormFieldDoesNotExist("extra.{$this->old->id}")
        ->fillForm(extraAthlete(['extra' => [$this->shirt->id => 'NOOR', $this->weight->id => '57', $this->blood->id => 'A']]))
        ->call('create')->assertHasNoFormErrors();

    expect(Person::sole()->extraValues())->toBe([$this->shirt->id => 'NOOR', $this->weight->id => '57', $this->blood->id => 'A']);
});

it('validates values by type in the form', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());

    livewire(CreatePerson::class)->fillForm(extraAthlete(['extra' => [$this->weight->id => 'heavy']]))
        ->call('create')->assertHasFormErrors(["extra.{$this->weight->id}" => 'numeric']);
    expect(Person::count())->toBe(0);
});

it('validates, skips hidden fields and audits without sensitive values when syncing', function () {
    $p = Person::factory()->create(['association_id' => $this->assoc->id]);
    $p->syncExtraValues([$this->shirt->id => ' ALI ', $this->blood->id => 'B', $this->onDuty->id => 'yes']); // onDuty: officials only

    expect($p->extraValues())->toBe([$this->shirt->id => 'ALI', $this->blood->id => 'B']);
    $log = Activity::where('subject_type', Person::class)->where('description', 'extra fields updated')->sole();
    expect($log->properties['extra_fields'])->toBe(['shirt_name' => ['old' => null, 'new' => 'ALI']])
        ->and($log->properties['sensitive_changed'])->toBeTrue()
        ->and(json_encode($log->properties))->not->toContain('"B"');

    expect(fn () => $p->syncExtraValues([$this->weight->id => 'heavy']))->toThrow(ValidationException::class, 'The Weight class field must be a number.');

    $sta = User::factory()->sta()->create();
    $p->syncExtraValues([$this->blood->id => 'A', $this->shirt->id => ''], $sta); // STA without view_medical
    expect($p->extraValues())->toBe([$this->blood->id => 'B']);
});

it('hides sensitive fields from STA without view_medical and keeps their values on save', function () {
    $p = Person::factory()->create(['association_id' => $this->assoc->id]);
    $p->syncExtraValues([$this->blood->id => 'B', $this->shirt->id => 'ALI']);
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ManagePeople->value);
    actingInAdmin($sta);

    $page = livewire(AdminEditPerson::class, ['record' => $p->getRouteKey()])
        ->assertFormFieldDoesNotExist("extra.{$this->blood->id}")->assertFormFieldExists("extra.{$this->shirt->id}");
    expect($page->get("data.extra.{$this->blood->id}"))->toBeNull();
    $page->fillForm(['extra' => [$this->shirt->id => 'ALI H']])->call('save')->assertHasNoFormErrors();
    expect($p->fresh()->extraValues())->toBe([$this->blood->id => 'B', $this->shirt->id => 'ALI H']);

    $sta->givePermissionTo(StaPermission::ViewMedical->value);
    actingInAdmin($sta->fresh());
    livewire(AdminEditPerson::class, ['record' => $p->getRouteKey()])->assertFormFieldExists("extra.{$this->blood->id}")
        ->assertSchemaStateSet(["extra.{$this->blood->id}" => 'B']);
});

it('counts active required fields of the person type for completeness', function () {
    $athlete = Person::factory()->complete()->create(['association_id' => $this->assoc->id]);
    $official = Person::factory()->official()->complete()->create(['association_id' => $this->assoc->id]);

    expect($athlete->missingFields())->toBe(['Shirt name'])->and($official->isComplete())->toBeTrue();

    $athlete->syncExtraValues([$this->shirt->id => 'ALI']);
    expect($athlete->isComplete())->toBeTrue();
});
