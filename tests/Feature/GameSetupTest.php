<?php

use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\CreateGame;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\RelationManagers\PhasesRelationManager;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Game;
use App\Models\User;
use App\Notifications\PhaseOpened;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(fn () => actingInAdmin(User::factory()->superAdmin()->create()));

it('creates a game with associations, clothing items and reminder days', function () {
    $a = Association::factory()->create();
    $shirt = ClothingItem::factory()->create(['name' => 'T-Shirt']);

    livewire(CreateGame::class)->fillForm([
        'name' => 'GCC Youth Games 2027', 'start_date' => '2027-04-01', 'end_date' => '2027-04-10',
        'reminder_days' => ['7', '3', '0'], 'associations' => [$a->id], 'clothingItems' => [$shirt->id],
    ])->call('create')->assertHasNoFormErrors();

    $game = Game::sole();
    expect($game->reminder_days)->toBe([7, 3, 0])->and($game->associations->pluck('id')->all())->toBe([$a->id])
        ->and($game->clothingItems->pluck('name')->all())->toBe(['T-Shirt']);
});

it('rejects an end date before the start date', function () {
    livewire(CreateGame::class)->fillForm(['name' => 'X', 'start_date' => '2027-04-10', 'end_date' => '2027-04-01', 'reminder_days' => ['7']])
        ->call('create')->assertHasFormErrors(['end_date']);
});

it('opens an enabled phase with a deadline and emails invited associations', function () {
    Notification::fake();
    $game = Game::factory()->create();
    $a = Association::factory()->create();
    $game->associations()->attach($a);
    $admin = User::factory()->assocAdmin($a)->create();
    $phase = $game->phase(1);
    $phase->update(['enabled' => true, 'deadline' => now()->addWeek()]);

    livewire(PhasesRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class])
        ->callAction(TestAction::make('open')->table($phase));

    expect($phase->fresh()->status)->toBe(PhaseStatus::Open);
    Notification::assertSentTo($admin, PhaseOpened::class);
});

it('will not open a disabled phase or one without a deadline', function () {
    $game = Game::factory()->create();
    livewire(PhasesRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class])
        ->assertActionHidden(TestAction::make('open')->table($game->phase(2)));
});

it('closes an open phase', function () {
    $game = Game::factory()->create();
    $p = $game->phase(1);
    $p->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    livewire(PhasesRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class])
        ->callAction(TestAction::make('close')->table($p));
    expect($p->fresh()->status)->toBe(PhaseStatus::Closed);
});

it('is hidden from STA users without manage_games', function () {
    $sta = User::factory()->sta()->create();
    actingInAdmin($sta);
    $this->get(GameResource::getUrl('index'))->assertForbidden();
    $sta->givePermissionTo(StaPermission::ManageGames->value);
    $this->get(GameResource::getUrl('index'))->assertOk();
});
