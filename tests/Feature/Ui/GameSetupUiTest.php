<?php

use App\Enums\PhaseStatus;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\Pages\SetupLimits;
use App\Filament\Resources\Games\Pages\SetupPhases;
use App\Filament\Resources\Games\Pages\SetupShortList;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(fn () => actingInAdmin(User::factory()->superAdmin()->create()));

it('keeps the canvas header and four setup tabs on every screen', function () {
    $game = Game::factory()->draft()->create();

    foreach ([EditGame::class, SetupLimits::class, SetupShortList::class, SetupPhases::class] as $page) {
        livewire($page, ['record' => $game->getRouteKey()])
            ->assertSeeInOrder(['Game setup', 'Draft · not visible to associations', 'Publish needs 1+ invited association and 1+ sport', 'Save as draft', 'Publish',
                '1 · Details & associations', '2 · Sports & limits', '3 · Clothing & short list', '4 · Phases']);
    }
});

it('renders step 1 as game details and invited associations with their sports', function () {
    $game = Game::factory()->draft()->create();
    $a = Association::factory()->create(['name' => 'Bahrain Squash Federation']);
    Sport::factory()->create(['association_id' => $a->id, 'name' => 'Squash']);

    livewire(EditGame::class, ['record' => $game->getRouteKey()])
        ->assertSeeInOrder(['Game details', 'Name', 'Start date', 'End date', 'Game logo', 'Upload logo', 'PNG or JPG, printed on every PDF',
            'Invited associations & sports', 'Search associations', 'Select all', 'Bahrain Squash Federation', 'Squash', 'Showing 1 of 1 associations', 'Save & set limits'])
        ->assertSee('Phase 1 (Entry by Sport) uses the list on the right');
});

it('saves details, invitations and ticked sports from step 1', function () {
    $game = Game::factory()->draft()->create();
    $a = Association::factory()->create();
    $squash = Sport::factory()->create(['association_id' => $a->id]);
    Event::factory()->create(['sport_id' => $squash->id]);

    livewire(EditGame::class, ['record' => $game->getRouteKey()])
        ->set('name', 'Renamed Games')->call('toggleAssociation', $a->id)->call('toggleSport', $squash->id)->call('save')->assertHasNoErrors();

    expect($game->fresh()->name)->toBe('Renamed Games')->and($game->associations()->pluck('associations.id')->all())->toBe([$a->id])
        ->and($game->gameSports()->sole()->gameEvents()->count())->toBe(1);

    livewire(EditGame::class, ['record' => $game->getRouteKey()])->call('toggleSport', $squash->id)->call('save');
    expect($game->gameSports()->count())->toBe(0);
});

it('keeps sports once the game has submissions', function () {
    $gs = GameSport::factory()->create();
    $gs->game->associations()->attach($gs->sport->association_id);
    Submission::factory()->create(['game_id' => $gs->game_id]);

    livewire(EditGame::class, ['record' => $gs->game->getRouteKey()])->call('toggleSport', $gs->sport_id)->call('save');
    expect($gs->game->gameSports()->count())->toBe(1);
});

it('rejects an end date before the start date on step 1', function () {
    $game = Game::factory()->draft()->create();
    livewire(EditGame::class, ['record' => $game->getRouteKey()])->set('start_date', '2027-04-10')->set('end_date', '2027-04-01')->call('save')->assertHasErrors(['end_date']);
});

it('renders step 2 with the sport list, Phase 2 limits and events offered', function () {
    $gs = GameSport::factory()->create(['p2_max_men' => 4, 'p2_max_women' => 4, 'p2_max_total' => 8]);
    $ev = Event::factory()->create(['sport_id' => $gs->sport_id, 'name' => 'Male 60kg']);
    GameEvent::factory()->create(['game_id' => $gs->game_id, 'game_sport_id' => $gs->id, 'event_id' => $ev->id, 'p2_max_athletes' => 1]);

    livewire(SetupLimits::class, ['record' => $gs->game->getRouteKey()])
        ->assertSeeInOrder(['Sports in this game', $gs->sport->name, 'Editing', 'Phase 2 · Entry by Number', 'Phase 2 limits', 'Max male', 'Max female', 'Max total athletes',
            'Born on or after (optional)', 'Born on or before', 'Entry policy (shown to associations and printed on the Entry by Event PDF)',
            'Events offered', '1 of 1 events offered in this game', 'Offered', 'Event', 'Gender', 'Max athletes per association', 'Male 60kg',
            'Copy limits from another game', 'Save '.$gs->sport->name]);
});

it('saves Phase 2 limits, events offered and their maximums', function () {
    $gs = GameSport::factory()->create();
    $a = Event::factory()->create(['sport_id' => $gs->sport_id]);
    $b = Event::factory()->create(['sport_id' => $gs->sport_id]);
    GameEvent::factory()->create(['game_id' => $gs->game_id, 'game_sport_id' => $gs->id, 'event_id' => $a->id]);

    livewire(SetupLimits::class, ['record' => $gs->game->getRouteKey()])
        ->call('bump', 'men', 1)->call('bump', 'men', 1)->call('bump', 'total', 1)->set('policy', 'One athlete per event')
        ->call('toggleEvent', $b->id)->call('bumpEvent', $b->id, 1)->call('toggleEvent', $a->id)->call('saveSport');

    $gs->refresh();
    expect($gs->p2_max_men)->toBe(2)->and($gs->p2_max_total)->toBe(1)->and($gs->entry_policy)->toBe('One athlete per event')
        ->and($gs->gameEvents()->pluck('event_id')->all())->toBe([$b->id])->and($gs->gameEvents()->sole()->p2_max_athletes)->toBe(1);
});

it('warns when the total can never be reached', function () {
    $gs = GameSport::factory()->create(['p2_max_men' => 1, 'p2_max_women' => 1, 'p2_max_total' => 5]);
    livewire(SetupLimits::class, ['record' => $gs->game->getRouteKey()])->assertSee('Max total (5) is more than male + female (2), so the total can never be reached.');
});

it('renders step 3 with clothing items and Short List maximums', function () {
    $game = Game::factory()->create();
    $gs = GameSport::factory()->create(['game_id' => $game->id, 'p4_max_men' => 3]);
    ClothingItem::factory()->create(['name' => 'T-Shirt']);

    livewire(SetupShortList::class, ['record' => $game->getRouteKey()])
        ->assertSeeInOrder(['Phase 3', 'Clothing sizes to collect', 'Manage clothing items', 'T-Shirt', 'Phase 4', 'Short list maximums',
            'Per association. Going over is allowed with one justification', 'Sport', 'Max male', 'Max female', 'Max team officials', 'Long list today (M · W · officials)',
            $gs->sport->name, 'Within limits', 'Save & go to phases']);
});

it('saves clothing items and Short List maximums', function () {
    $game = Game::factory()->create();
    $gs = GameSport::factory()->create(['game_id' => $game->id]);
    $shirt = ClothingItem::factory()->create();

    livewire(SetupShortList::class, ['record' => $game->getRouteKey()])
        ->call('toggleItem', $shirt->id)->call('bump', $gs->id, 'men', 1)->call('bump', $gs->id, 'officials', 1)->call('save');

    expect($game->clothingItems()->pluck('clothing_items.id')->all())->toBe([$shirt->id])
        ->and($gs->fresh()->p4_max_men)->toBe(1)->and($gs->fresh()->p4_max_officials)->toBe(1);
});

it('renders step 4 as phase cards, reminders and extensions in the canvas order', function () {
    $game = Game::factory()->create();
    $game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);

    livewire(SetupPhases::class, ['record' => $game->getRouteKey()])
        ->assertSeeInOrder(['Turn on the phases this game needs', 'Entry by Sport', 'Entry by Number', 'Long List', 'Open', 'Close phase', 'Short List',
            'Automatic reminders', '7 days before', '3 days before', '1 day before', 'On the deadline day', 'Extensions for one association', 'Add extension',
            'Association', 'Phase', 'Deadline', 'Note'])
        ->assertSee('Associations pick athletes for each event')->assertSee('Turned off for this game');
});

it('turns phases on, sets dates, opens and closes them one at a time', function () {
    $game = Game::factory()->create();
    $page = fn () => livewire(SetupPhases::class, ['record' => $game->getRouteKey()]);

    $page()->call('toggle', 2)->call('setDate', 2, 'opens_at', '2027-01-01')->call('setDate', 2, 'deadline', '2027-01-10');
    $p = $game->phase(2)->fresh();
    expect($p->enabled)->toBeTrue()->and($p->deadline->timezone('Asia/Bahrain')->format('Y-m-d H:i'))->toBe('2027-01-10 23:59');

    $page()->call('setDate', 2, 'deadline', '2026-12-01');
    expect($game->phase(2)->fresh()->deadline->timezone('Asia/Bahrain')->format('Y-m-d'))->toBe('2027-01-10');

    $page()->callAction('openPhase', arguments: ['phase' => 2]);
    expect($game->phase(2)->fresh()->status)->toBe(PhaseStatus::Open);

    $game->phase(3)->update(['enabled' => true, 'deadline' => now()->addWeek()]);
    $page()->callAction('openPhase', arguments: ['phase' => 3]);
    expect($game->phase(3)->fresh()->status)->toBe(PhaseStatus::Draft);

    $page()->call('setDate', 2, 'deadline', '')->callAction('closePhase', arguments: ['phase' => 2]);
    expect($game->phase(2)->fresh()->status)->toBe(PhaseStatus::Closed)->and($game->phase(2)->fresh()->deadline)->not->toBeNull();
});

it('toggles reminder days and lists extensions', function () {
    $game = Game::factory()->create(['reminder_days' => [7, 0]]);
    $a = Association::factory()->create();
    $game->associations()->attach($a);
    $game->phase(3)->update(['enabled' => true, 'deadline' => now()->addWeek()]);
    Submission::factory()->create(['game_id' => $game->id, 'association_id' => $a->id, 'phase' => 3, 'deadline_override' => now()->addWeeks(2)]);

    livewire(SetupPhases::class, ['record' => $game->getRouteKey()])->call('toggleReminder', 3)->call('toggleReminder', 7)->assertSee($a->name);
    expect($game->fresh()->reminder_days)->toBe([3, 0]);
});

it('leaves game setup to users who can manage games', function () {
    actingInAdmin(User::factory()->create());
    $game = Game::factory()->create();
    foreach (['edit', 'setup-limits', 'setup-short-list', 'setup-phases'] as $page) {
        $this->get(GameResource::getUrl($page, ['record' => $game]))->assertForbidden();
    }
});
