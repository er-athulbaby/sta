<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\CreateGame;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\RelationManagers\GameSportsRelationManager;
use App\Filament\Resources\Games\RelationManagers\PhasesRelationManager;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Sport;
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

it('adds a sport of an invited association with all its active events', function () {
    $game = Game::factory()->create();
    $a = Association::factory()->create();
    $game->associations()->attach($a);
    $judo = Sport::factory()->create(['association_id' => $a->id, 'name' => 'Judo']);
    Event::factory()->create(['sport_id' => $judo->id, 'name' => 'Men -60kg', 'gender' => EventGender::Men]);
    Event::factory()->create(['sport_id' => $judo->id, 'name' => 'Old', 'is_active' => false]);

    livewire(GameSportsRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class])
        ->callAction(TestAction::make('create')->table(), ['sport_id' => $judo->id, 'p2_max_men' => 3, 'p2_max_total' => 5,
            'dob_from' => '2002-01-01', 'dob_to' => '2010-12-31', 'entry_policy' => 'Max 1 athlete per weight category'])
        ->assertHasNoFormErrors();

    $gs = GameSport::sole();
    expect($gs->p2_max_men)->toBe(3)->and($gs->gameEvents->pluck('event.name')->all())->toBe(['Men -60kg']);
});

it('only offers sports of invited associations, once', function () {
    $game = Game::factory()->create();
    $invited = Association::factory()->create();
    $game->associations()->attach($invited);
    $ok = Sport::factory()->create(['association_id' => $invited->id]);
    GameSport::factory()->create(['game_id' => $game->id, 'sport_id' => $ok->id]);
    $other = Sport::factory()->create();

    livewire(GameSportsRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class])
        ->callAction(TestAction::make('create')->table(), ['sport_id' => $other->id])
        ->assertHasFormErrors(['sport_id']);

    livewire(GameSportsRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class])
        ->callAction(TestAction::make('create')->table(), ['sport_id' => $ok->id])
        ->assertHasFormErrors(['sport_id']);
});

it('sets event maximums and rejects dob_to before dob_from', function () {
    $gs = GameSport::factory()->create();
    $ge = GameEvent::factory()->create(['game_id' => $gs->game_id, 'game_sport_id' => $gs->id]);

    livewire(GameSportsRelationManager::class, ['ownerRecord' => $gs->game, 'pageClass' => EditGame::class])
        ->callAction(TestAction::make('edit')->table($gs), ['dob_from' => '2010-01-01', 'dob_to' => '2005-01-01'])
        ->assertHasFormErrors(['dob_to']);

    livewire(GameSportsRelationManager::class, ['ownerRecord' => $gs->game, 'pageClass' => EditGame::class])
        ->callAction(TestAction::make('edit')->table($gs), ['gameEvents' => ["record-{$ge->id}" => ['p2_max_athletes' => 2]]])->assertHasNoFormErrors()->assertNotified();
    expect($ge->fresh()->p2_max_athletes)->toBe(2);
});

it('syncs events added to the sport later without duplicating', function () {
    $gs = GameSport::factory()->create();
    GameEvent::factory()->create(['game_id' => $gs->game_id, 'game_sport_id' => $gs->id]);
    Event::factory()->create(['sport_id' => $gs->sport_id]);

    livewire(GameSportsRelationManager::class, ['ownerRecord' => $gs->game, 'pageClass' => EditGame::class])
        ->callAction(TestAction::make('syncEvents')->table());

    expect($gs->gameEvents()->count())->toBe(2);
});
