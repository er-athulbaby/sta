<?php

use App\Filament\Resources\Associations\Pages\CreateAssociation;
use App\Filament\Resources\Associations\Pages\EditAssociation;
use App\Models\Association;
use App\Models\Person;
use App\Models\User;

use function Pest\Livewire\livewire;

it('requires a unique upper-case association code', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    Association::factory()->create(['code' => 'BWF']);

    livewire(CreateAssociation::class)->fillForm(['name' => 'X', 'email' => 'x@x.bh', 'code' => 'BWF'])->call('create')
        ->assertHasFormErrors(['code']);
    livewire(CreateAssociation::class)->fillForm(['name' => 'X', 'email' => 'x@x.bh', 'code' => 'bad code!'])->call('create')
        ->assertHasFormErrors(['code']);
    livewire(CreateAssociation::class)->fillForm(['name' => 'Judo', 'email' => 'j@x.bh', 'code' => 'bjf'])->call('create')
        ->assertHasNoFormErrors();

    expect(Association::firstWhere('name', 'Judo')->code)->toBe('BJF');
});

it('trims and upper-cases the code at model level', function () {
    expect(Association::factory()->create(['code' => ' bjf '])->code)->toBe('BJF');
});

it('locks the code once the association has people', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $with = Association::factory()->create();
    Person::factory()->create(['association_id' => $with->id]);
    $without = Association::factory()->create();

    Livewire\Livewire::test(EditAssociation::class, ['record' => $with->getRouteKey()])
        ->assertFormFieldDisabled('code');
    Livewire\Livewire::test(EditAssociation::class, ['record' => $without->getRouteKey()])
        ->assertFormFieldEnabled('code');
});
