<?php

use App\Enums\AppliesTo;
use App\Enums\ExtraFieldType;
use App\Enums\PersonType;
use App\Enums\StaPermission;
use App\Filament\Resources\ExtraFields\ExtraFieldResource;
use App\Filament\Resources\ExtraFields\Pages\CreateExtraField;
use App\Filament\Resources\ExtraFields\Pages\EditExtraField;
use App\Models\ExtraField;
use App\Models\Person;
use App\Models\PersonFieldValue;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

it('generates a unique key from the label once', function () {
    $a = ExtraField::factory()->create(['label' => 'Shirt name']);
    $b = ExtraField::factory()->create(['label' => 'Shirt  Name!']);
    $c = ExtraField::factory()->create(['label' => '***']); // nothing sluggable
    $a->update(['label' => 'Name on shirt']);

    expect($a->fresh()->key)->toBe('shirt_name')->and($b->key)->toBe('shirt_name_2')->and($c->key)->toBe('field');
});

it('refuses a type change once values exist', function () {
    $f = ExtraField::factory()->create(['type' => ExtraFieldType::Text]);
    $f->update(['type' => ExtraFieldType::Number]); // no values yet: allowed
    PersonFieldValue::create(['person_id' => Person::factory()->create()->id, 'extra_field_id' => $f->id, 'value' => '5']);

    expect(fn () => $f->fresh()->update(['type' => ExtraFieldType::Date]))
        ->toThrow(ValidationException::class, 'The type cannot change once people have values for this field.')
        ->and($f->fresh()->type)->toBe(ExtraFieldType::Number);
});

it('normalises and validates values by type', function (ExtraFieldType $type, mixed $in, ?string $stored, bool $valid) {
    $f = ExtraField::factory()->make(['label' => 'X', 'type' => $type, 'options' => ['Red', 'Blue']]);
    $value = $f->normalise($in);

    expect($value)->toBe($stored)
        ->and(Validator::make(['v' => $value], ['v' => $f->rules()])->passes())->toBe($valid);
})->with([
    'text' => [ExtraFieldType::Text, '  Ali ', 'Ali', true],
    'text too long' => [ExtraFieldType::Text, str_repeat('x', 256), str_repeat('x', 256), false],
    'blank' => [ExtraFieldType::Text, '   ', null, true],
    'number' => [ExtraFieldType::Number, '12.5', '12.5', true],
    'not a number' => [ExtraFieldType::Number, 'abc', 'abc', false],
    'date' => [ExtraFieldType::Date, '2026-12-01', '2026-12-01', true],
    'Excel serial date' => [ExtraFieldType::Date, 46357, '2026-12-01', true],
    'date in another format' => [ExtraFieldType::Date, '01/12/2026', '01/12/2026', false],
    'choice' => [ExtraFieldType::Choice, 'Red', 'Red', true],
    'not an option' => [ExtraFieldType::Choice, 'Green', 'Green', false],
    'yes' => [ExtraFieldType::YesNo, 'Yes', '1', true],
    'false' => [ExtraFieldType::YesNo, false, '0', true],
    'maybe' => [ExtraFieldType::YesNo, 'maybe', 'maybe', false],
]);

it('lists required fields per type and sees a new one at once', function () {
    $athlete = ExtraField::factory()->create(['label' => 'Shirt name', 'required' => true, 'applies_to' => AppliesTo::Athlete]);
    ExtraField::factory()->create(['label' => 'Optional', 'required' => false]);
    ExtraField::factory()->create(['label' => 'Old', 'required' => true, 'is_active' => false]);

    expect(ExtraField::requiredFor(PersonType::Athlete)->pluck('id')->all())->toBe([$athlete->id])
        ->and(ExtraField::requiredFor(PersonType::Official))->toBeEmpty();

    $both = ExtraField::factory()->create(['label' => 'Diet', 'required' => true]);
    expect(ExtraField::requiredFor(PersonType::Official)->pluck('id')->all())->toBe([$both->id]);
});

it('lets only ManageMasterData users manage extra fields', function () {
    $sta = User::factory()->sta()->create();
    actingInAdmin($sta);
    $this->get(ExtraFieldResource::getUrl('index'))->assertForbidden();

    $sta->givePermissionTo(StaPermission::ManageMasterData->value);
    $this->get(ExtraFieldResource::getUrl('index'))->assertOk();
});

it('creates a choice field from the form, requiring options, and audits it', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(CreateExtraField::class)
        ->fillForm(['label' => 'Shirt size', 'type' => 'choice', 'applies_to' => 'both'])
        ->call('create')->assertHasFormErrors(['options' => 'required']);
    livewire(CreateExtraField::class)
        ->fillForm(['label' => 'Shirt size', 'type' => 'choice', 'applies_to' => 'both', 'options' => ['S', 'M'], 'sensitive' => true])
        ->call('create')->assertHasNoFormErrors();

    $f = ExtraField::sole();
    expect($f->key)->toBe('shirt_size')->and($f->options)->toBe(['S', 'M'])->and($f->sensitive)->toBeTrue()
        ->and(Activity::where('subject_type', ExtraField::class)->where('event', 'created')->exists())->toBeTrue();
});

it('disables the type field once values exist', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $f = ExtraField::factory()->create();
    livewire(EditExtraField::class, ['record' => $f->getRouteKey()])->assertFormFieldIsEnabled('type');

    PersonFieldValue::create(['person_id' => Person::factory()->create()->id, 'extra_field_id' => $f->id, 'value' => 'x']);
    livewire(EditExtraField::class, ['record' => $f->getRouteKey()])->assertFormFieldIsDisabled('type')
        ->fillForm(['label' => 'Renamed', 'is_active' => false])->call('save')->assertHasNoFormErrors();
    expect($f->fresh())->label->toBe('Renamed')->is_active->toBeFalse()->type->toBe(ExtraFieldType::Text);
});
