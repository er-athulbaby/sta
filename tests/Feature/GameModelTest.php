<?php

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('creates four disabled draft phases with a new game', function () {
    $game = Game::factory()->create();

    expect($game->phases()->orderBy('phase')->pluck('phase')->all())->toBe([1, 2, 3, 4])
        ->and($game->phase(2)->enabled)->toBeFalse()
        ->and($game->phase(2)->status)->toBe(PhaseStatus::Draft);
});

it('lists only the association\'s own sports in a game', function () {
    $bwf = Association::factory()->create(['code' => 'BWF']);
    $bjf = Association::factory()->create(['code' => 'BJF']);
    $wl = Sport::factory()->create(['association_id' => $bwf->id, 'name' => 'Weightlifting']);
    $judo = Sport::factory()->create(['association_id' => $bjf->id, 'name' => 'Judo']);
    $game = Game::factory()->create();
    $game->associations()->attach([$bwf->id, $bjf->id]);
    GameSport::factory()->create(['game_id' => $game->id, 'sport_id' => $wl->id]);
    GameSport::factory()->create(['game_id' => $game->id, 'sport_id' => $judo->id]);

    expect($game->sportsFor($bwf)->pluck('sport.name')->all())->toBe(['Weightlifting'])
        ->and($game->isInvited($bwf))->toBeTrue()
        ->and($game->isInvited(Association::factory()->create()))->toBeFalse();
});

it('gets or creates one draft submission per game, association and phase', function () {
    $game = Game::factory()->create();
    $assoc = Association::factory()->create();

    $a = Submission::for($game, $assoc, 2);
    $b = Submission::for($game, $assoc, 2);

    expect($a->id)->toBe($b->id)->and($a->status)->toBe(SubmissionStatus::Draft)
        ->and(Submission::count())->toBe(1);
});

it('uses the association override before the phase deadline', function () {
    $game = Game::factory()->create();
    $phase = $game->phase(2);
    $phase->update(['deadline' => '2026-12-01 23:59:00']);
    $sub = Submission::for($game, Association::factory()->create(), 2);

    expect($phase->effectiveDeadline($sub)->toDateString())->toBe('2026-12-01');
    $sub->update(['deadline_override' => '2026-12-05 23:59:00']);
    expect($phase->effectiveDeadline($sub->fresh())->toDateString())->toBe('2026-12-05');
});

it('builds game events from the game sport, explicit values winning', function () {
    $ge = GameEvent::factory()->create();
    expect($ge->game_id)->toBe($ge->gameSport->game_id)->and($ge->event->sport_id)->toBe($ge->gameSport->sport_id);

    $gs = GameSport::factory()->create();
    $other = GameSport::factory()->create();
    $event = Event::factory()->create(['sport_id' => $gs->sport_id]);
    $ge2 = GameEvent::factory()->create(['game_sport_id' => $gs->id, 'game_id' => $other->game_id, 'event_id' => $event->id]);
    expect($ge2->game_id)->toBe($other->game_id)->and($ge2->event_id)->toBe($event->id);
});

it('uses the Bahrain time zone', function () {
    expect(config('app.timezone'))->toBe('Asia/Bahrain');
});

it('refuses Submission::for across associations for association users only', function () {
    $game = Game::factory()->create();
    $a = Association::factory()->create();
    $b = Association::factory()->create();

    $this->actingAs(User::factory()->assocEditor($a)->create());
    expect(fn () => Submission::for($game, $b, 2))->toThrow(NotFoundHttpException::class);
    expect(Submission::withoutGlobalScopes()->count())->toBe(0)
        ->and(Submission::for($game, $a, 2)->association_id)->toBe($a->id);

    $this->actingAs(User::factory()->sta()->create());
    expect(Submission::for($game, $b, 2)->association_id)->toBe($b->id);
    auth()->logout();
    expect(Submission::for($game, $b, 3)->association_id)->toBe($b->id);
});

it('phase2SportsFor drops sports answered No when phase 1 is enabled', function () {
    $a = Association::factory()->create();
    $game = Game::factory()->create();
    $game->associations()->attach($a);
    $gs = GameSport::factory()->create(['game_id' => $game->id, 'sport_id' => Sport::factory()->create(['association_id' => $a->id])->id]);
    Submission::for($game, $a, 1)->answers()->create(['game_sport_id' => $gs->id, 'answer' => 'no']);

    expect($game->phase2SportsFor($a))->toHaveCount(1);
    $game->phase(1)->update(['enabled' => true]);
    expect($game->fresh()->phase2SportsFor($a))->toHaveCount(0);
});
