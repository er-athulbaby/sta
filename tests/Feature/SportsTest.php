<?php

use App\Enums\EventGender;
use App\Enums\StaPermission;
use App\Filament\Portal\Resources\Sports\Pages\ListSports;
use App\Filament\Portal\Resources\Sports\SportResource;
use App\Filament\Resources\Sports\Pages\CreateSport;
use App\Filament\Resources\Sports\Pages\EditSport;
use App\Filament\Resources\Sports\RelationManagers\EventsRelationManager;
use App\Models\Association;
use App\Models\Sport;
use App\Models\User;
use Filament\Actions\Testing\TestAction;

use function Pest\Livewire\livewire;

it('lets STA create a sport for an association', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ManageMasterData->value);
    actingInAdmin($sta);
    $assoc = Association::factory()->create();

    livewire(CreateSport::class)->fillForm(['association_id' => $assoc->id, 'name' => 'Badminton', 'is_active' => true])
        ->call('create')->assertHasNoFormErrors();

    expect(Sport::sole())->association_id->toBe($assoc->id);
});

it('adds events and rejects a duplicate name and gender in the same sport', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $sport = Sport::factory()->create();

    $rm = fn () => livewire(EventsRelationManager::class, ['ownerRecord' => $sport, 'pageClass' => EditSport::class]);
    $rm()->callAction(TestAction::make('create')->table(), data: ['name' => '100m', 'gender' => EventGender::Men->value, 'is_active' => true])
        ->assertHasNoFormErrors();
    $rm()->callAction(TestAction::make('create')->table(), data: ['name' => '100m', 'gender' => EventGender::Men->value, 'is_active' => true])
        ->assertHasFormErrors(['name']);

    expect($sport->events()->count())->toBe(1);
});

it('never offers deleting a sport (BR-16)', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $sport = Sport::factory()->create();

    livewire(EditSport::class, ['record' => $sport->getRouteKey()])->assertActionDoesNotExist('delete');
});

it('shows associations only their own sports, read only', function () {
    $mine = Sport::factory()->create();
    $theirs = Sport::factory()->create();
    actingInPortal(User::factory()->assocEditor($mine->association)->create());

    livewire(ListSports::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs]);
    expect(SportResource::canCreate())->toBeFalse();
});
