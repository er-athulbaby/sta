<?php

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
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
use App\Models\NumberEntry;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\DeadlineChanged;
use App\Notifications\PhaseOpened;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(fn () => actingInAdmin(User::factory()->superAdmin()->create()));

function phasesPage(Game $game)
{
    return livewire(SetupPhases::class, ['record' => $game->getRouteKey()]);
}

it('will not open a phase of a draft game', function () {
    $game = Game::factory()->draft()->create();
    $game->phase(2)->update(['enabled' => true, 'deadline' => now()->addWeek()]);

    phasesPage($game)->callAction('openPhase', arguments: ['phase' => 2]);

    expect($game->phase(2)->fresh()->status)->toBe(PhaseStatus::Draft);
});

it('emails the invited associations when a phase opens', function () {
    Notification::fake();
    $game = Game::factory()->create();
    $a = Association::factory()->create();
    $game->associations()->attach($a);
    $admin = User::factory()->assocAdmin($a)->create();
    $game->phase(1)->update(['enabled' => true, 'deadline' => now()->addWeek()]);

    phasesPage($game)->callAction('openPhase', arguments: ['phase' => 1]);

    expect($game->phase(1)->fresh()->status)->toBe(PhaseStatus::Open);
    Notification::assertSentTo($admin, PhaseOpened::class);
});

it('opens only one phase at a time, even a phase that is open but switched off', function () {
    $game = Game::factory()->create();
    $game->phase(1)->update(['enabled' => false, 'status' => PhaseStatus::Open]);
    $game->phase(2)->update(['enabled' => true, 'deadline' => now()->addWeek()]);

    phasesPage($game)->callAction('openPhase', arguments: ['phase' => 2]);

    expect($game->phase(2)->fresh()->status)->toBe(PhaseStatus::Draft);
});

it('extends a deadline with a note and tells the association', function () {
    Notification::fake();
    $game = Game::factory()->create();
    $a = Association::factory()->create();
    $game->associations()->attach($a);
    $admin = User::factory()->assocAdmin($a)->create();
    $game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $new = now()->addDays(10)->setTime(12, 0);

    phasesPage($game)->callAction('addExtension', ['type' => 'extend', 'association' => $a->id, 'phase' => 3,
        'deadline' => $new->format('Y-m-d H:i:s'), 'note' => 'Waiting for passports'])->assertHasNoErrors();

    $s = Submission::withoutGlobalScopes()->where(['game_id' => $game->id, 'association_id' => $a->id, 'phase' => 3])->sole();
    expect($s->deadline_override->timezone('Asia/Bahrain')->format('Y-m-d H:i'))->toBe($new->copy()->timezone('Asia/Bahrain')->format('Y-m-d H:i'))
        ->and($s->deadline_note)->toBe('Waiting for passports')->and($s->deadline_change)->toBe('extended');
    Notification::assertSentTo($admin, DeadlineChanged::class);
    phasesPage($game)->assertSee('Waiting for passports')->assertDontSee('Re-opened');
});

it('re-opens a submitted phase with a new deadline and shows the Re-opened variant', function () {
    Notification::fake();
    $game = Game::factory()->create();
    $a = Association::factory()->create();
    $game->associations()->attach($a);
    $admin = User::factory()->assocAdmin($a)->create();
    $game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $s = Submission::factory()->create(['game_id' => $game->id, 'association_id' => $a->id, 'phase' => 3, 'status' => SubmissionStatus::Submitted]);

    phasesPage($game)->callAction('addExtension', ['type' => 'reopen', 'association' => $a->id, 'phase' => 3,
        'deadline' => now()->addDays(5)->format('Y-m-d H:i:s'), 'note' => 'One official missing'])
        ->assertSee('Re-opened →')->assertSee('One official missing');

    expect($s->fresh()->status)->toBe(SubmissionStatus::Draft)->and($s->fresh()->deadline_change)->toBe('reopened');
    Notification::assertSentTo($admin, DeadlineChanged::class);
});

it('refuses to extend a submitted entry and stores no note', function () {
    $game = Game::factory()->create();
    $a = Association::factory()->create();
    $game->associations()->attach($a);
    $game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $s = Submission::factory()->create(['game_id' => $game->id, 'association_id' => $a->id, 'phase' => 3, 'status' => SubmissionStatus::Submitted]);

    phasesPage($game)->callAction('addExtension', ['type' => 'extend', 'association' => $a->id, 'phase' => 3,
        'deadline' => now()->addDays(5)->format('Y-m-d H:i:s'), 'note' => 'x']);

    expect($s->fresh()->deadline_note)->toBeNull()->and($s->fresh()->deadline_override)->toBeNull();
});

it('keeps an offered event that already has entries', function () {
    $gs = GameSport::factory()->create();
    $ev = Event::factory()->create(['sport_id' => $gs->sport_id]);
    $ge = GameEvent::factory()->create(['game_id' => $gs->game_id, 'game_sport_id' => $gs->id, 'event_id' => $ev->id]);
    $sub = Submission::factory()->create(['game_id' => $gs->game_id, 'phase' => 2]);
    NumberEntry::create(['submission_id' => $sub->id, 'game_event_id' => $ge->id, 'count' => 1]);

    livewire(SetupLimits::class, ['record' => $gs->game->getRouteKey()])->call('toggleEvent', $ev->id)->call('save');

    expect($ge->fresh())->not->toBeNull();
});

it('copies limits from another game into the form without saving', function () {
    $sport = Sport::factory()->create();
    $old = GameSport::factory()->create(['sport_id' => $sport->id, 'p2_max_men' => 4, 'p2_max_women' => 3, 'p2_max_total' => 7, 'entry_policy' => 'Old policy']);
    $gs = GameSport::factory()->create(['sport_id' => $sport->id]);

    livewire(SetupLimits::class, ['record' => $gs->game->getRouteKey()])->callAction('copyLimits', ['game_id' => $old->game_id])
        ->assertSet('men', 4)->assertSet('women', 3)->assertSet('total', 7)->assertSet('policy', 'Old policy');

    expect($gs->fresh()->p2_max_men)->toBeNull();
});

it('publishes from a setup screen', function () {
    $game = Game::factory()->draft()->create();
    $a = Association::factory()->create();
    $game->associations()->attach($a);
    GameSport::factory()->create(['game_id' => $game->id, 'sport_id' => Sport::factory()->create(['association_id' => $a->id])->id]);

    phasesPage($game)->callAction('publish')->assertNotified('Game published');

    expect($game->fresh()->isPublished())->toBeTrue();
});

it('ignores association and sport ids that are not active', function () {
    $game = Game::factory()->draft()->create();
    $off = Association::factory()->create(['is_active' => false]);
    $offSport = Sport::factory()->create(['is_active' => false]);

    livewire(EditGame::class, ['record' => $game->getRouteKey()])
        ->call('toggleAssociation', $off->id)->call('toggleSport', $offSport->id)->assertSet('invited', [])->assertSet('picked', [])
        ->set('invited', [$off->id, 999999])->call('save')->assertHasNoErrors();

    expect($game->associations()->count())->toBe(0);
});

it('never attaches another sport\'s event and rejects a negative maximum', function () {
    $gs = GameSport::factory()->create();
    $mine = Event::factory()->create(['sport_id' => $gs->sport_id]);
    $foreign = Event::factory()->create();

    $page = livewire(SetupLimits::class, ['record' => $gs->game->getRouteKey()])
        ->set("events.{$foreign->id}", ['name' => 'X', 'gender' => 'Male', 'kind' => 'men', 'offered' => true, 'max' => 1])->call('save');
    expect($gs->gameEvents()->count())->toBe(0);

    $page->set("events.{$mine->id}.max", -1)->call('save')->assertHasErrors(['events.'.$mine->id.'.max']);
});

it('keeps reminder days between 0 and 60 and at least one', function () {
    $game = Game::factory()->create(['reminder_days' => [3]]);

    phasesPage($game)->call('toggleReminder', 3)->call('toggleReminder', 99)->call('toggleReminder', -1);
    expect($game->fresh()->reminder_days)->toBe([3]);

    phasesPage($game)->call('toggleReminder', 7);
    expect($game->fresh()->reminder_days)->toBe([7, 3]);
});

it('answers an unparsable date with a message', function () {
    $game = Game::factory()->create();
    $game->phase(2)->update(['enabled' => true]);

    phasesPage($game)->call('setDate', 2, 'deadline', 'not a date')->assertNotified('Give a valid date.');

    expect($game->phase(2)->fresh()->deadline)->toBeNull();
});

it('refuses inactive clothing items', function () {
    $game = Game::factory()->create();
    $off = ClothingItem::factory()->create(['is_active' => false]);

    livewire(SetupShortList::class, ['record' => $game->getRouteKey()])->call('toggleItem', $off->id)->assertSet('items', [])
        ->set('items', [$off->id])->call('save');

    expect($game->clothingItems()->count())->toBe(0);
});

it('uses the canvas spacing class and clothing wording', function () {
    $game = Game::factory()->create();
    ClothingItem::factory()->create(['applies_to' => 'athlete']);

    livewire(SetupShortList::class, ['record' => $game->getRouteKey()])->assertSee('Athletes only');
    $this->get(GameResource::getUrl('setup-phases', ['record' => $game]))->assertSee('sta-canvas-page', false);
});
