<?php

use App\Enums\PhaseStatus;
use App\Filament\Portal\Pages\Games;
use App\Filament\Portal\Pages\Phase2Entry;
use App\Filament\Resources\Games\Pages\CreateGame;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\Pages\GameDashboard;
use App\Filament\Resources\Games\Pages\ListGames;
use App\Filament\Resources\Games\RelationManagers\PhasesRelationManager;
use App\Models\Association;
use App\Models\Game;
use App\Models\GameSport;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\DeadlineChanged;
use App\Notifications\PhaseReminder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->draft()->create(['name' => 'GCC Youth Games 2027']);
});

it('creates new games as drafts and keeps factory games published', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(CreateGame::class)->fillForm(['name' => 'New Games', 'start_date' => '2027-04-01', 'end_date' => '2027-04-10', 'reminder_days' => ['7']])
        ->call('create')->assertHasNoFormErrors();

    expect(Game::where('name', 'New Games')->sole()->isPublished())->toBeFalse()
        ->and(Game::factory()->create()->isPublished())->toBeTrue();
});

it('needs an invited association and a sport to publish', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(EditGame::class, ['record' => $this->game->getRouteKey()])->assertSee('Draft')->callAction('publish')
        ->assertNotified('Invite at least one association and add at least one sport before publishing.');
    expect($this->game->fresh()->isPublished())->toBeFalse();

    $this->game->associations()->attach($this->assoc);
    GameSport::factory()->create(['game_id' => $this->game->id, 'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id])->id]);
    livewire(EditGame::class, ['record' => $this->game->getRouteKey()])->callAction('publish')->assertNotified('Game published')
        ->assertActionHidden('publish')->assertSee('Published');
    expect($this->game->fresh()->isPublished())->toBeTrue();
});

it('shows a Draft or Published badge in the games list', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    Game::factory()->create(['name' => 'Live Games']);
    livewire(ListGames::class)->assertSee('Draft')->assertSee('Published');
});

it('will not open a phase of a draft game', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $phase = $this->game->phase(2);
    $phase->update(['enabled' => true, 'deadline' => now()->addWeek()]);
    livewire(PhasesRelationManager::class, ['ownerRecord' => $this->game, 'pageClass' => EditGame::class])
        ->assertActionHidden(TestAction::make('open')->table($phase));
});

it('hides a draft game from the association', function () {
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());

    livewire(Games::class)->assertDontSee('GCC Youth Games 2027');
    $this->get(Phase2Entry::getUrl(['game' => $this->game->id], tenant: $this->assoc))->assertNotFound();
});

it('sends no reminders for a draft game', function () {
    Notification::fake();
    $this->game->associations()->attach($this->assoc);
    User::factory()->assocAdmin($this->assoc)->create();
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->subDay()]);

    $this->artisan('phases:send-reminders')->assertSuccessful();
    Notification::assertNotSentTo(User::where('association_id', $this->assoc->id)->get(), PhaseReminder::class);
});

it('hides Remind pending for a draft game', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])->assertActionHidden('remindPending');
});

it('sends no deadline change notice for a draft game', function () {
    Notification::fake();
    $this->game->associations()->attach($this->assoc);
    $admin = User::factory()->assocAdmin($this->assoc)->create();
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    actingInAdmin(User::factory()->superAdmin()->create());
    $page = livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()]);

    $page->call('extend', $this->assoc->id, 2, now()->addDays(10)->format('Y-m-d H:i'));
    expect(Submission::withoutGlobalScopes()->where('association_id', $this->assoc->id)->sole()->deadline_override)->not->toBeNull();

    Submission::withoutGlobalScopes()->where('association_id', $this->assoc->id)->update(['status' => 'submitted']);
    $page->call('reopen', $this->assoc->id, 2, now()->addDays(12)->format('Y-m-d H:i'));
    Notification::assertNotSentTo($admin, DeadlineChanged::class);
});
