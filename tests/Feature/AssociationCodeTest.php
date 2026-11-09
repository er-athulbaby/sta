<?php

use App\Filament\Resources\Associations\Pages\CreateAssociation;
use App\Models\Association;
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
