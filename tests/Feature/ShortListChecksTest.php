<?php

use App\Enums\EventGender;
use App\Enums\PersonGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\Phases\ShortListChecks;
use App\Services\Phases\SubmissionWorkflow;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id, 'p4_max_men' => 1, 'p4_max_officials' => 0,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $event = fn (string $name, EventGender $g) => GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => $name, 'gender' => $g])->id]);
    $this->m60 = $event('Men -60kg', EventGender::Men);
    $this->w52 = $event('Women -52kg', EventGender::Women);
    $this->mixed = $event('Mixed team', EventGender::Mixed);
    $this->ali = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->omar = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Omar', 'last_name' => 'Saleh']);
    $this->sara = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Sara', 'last_name' => 'Ali', 'gender' => PersonGender::Female]);
    $this->coach = Person::factory()->official()->create(['association_id' => $this->assoc->id, 'first_name' => 'Coach', 'last_name' => 'Karim']);
    $this->p3 = Submission::for($this->game, $this->assoc, 3);
    foreach ([[$this->ali, $this->m60], [$this->omar, $this->m60], [$this->sara, $this->w52]] as [$p, $ge]) {
        $this->p3->entries()->create(['person_id' => $p->id, 'game_event_id' => $ge->id, 'game_sport_id' => $this->judo->id]);
    }
    $this->p3->entries()->create(['person_id' => $this->coach->id, 'game_event_id' => null, 'game_sport_id' => $this->judo->id]);
    $this->p3->update(['status' => SubmissionStatus::Submitted]);
});

it('does not copy anything just from Submission::for()', function () {
    expect(Submission::for($this->game, $this->assoc, 4)->entries()->count())->toBe(0);
});

it('preloads the submitted Long List into a new Phase 4 submission, once', function () {
    $p4 = Submission::for($this->game, $this->assoc, 4);

    expect(app(ShortListChecks::class)->preload($p4))->toBeTrue()
        ->and($p4->entries()->count())->toBe(4)
        ->and($p4->entries()->whereNull('game_event_id')->sole()->person_id)->toBe($this->coach->id);

    $p4->entries()->where('person_id', $this->omar->id)->delete();
    expect(app(ShortListChecks::class)->preload($p4->fresh()))->toBeFalse()
        ->and($p4->entries()->count())->toBe(3)
        ->and($this->p3->entries()->count())->toBe(4);
});

it('does not preload once the Phase 4 submission has been saved', function () {
    $p4 = Submission::for($this->game, $this->assoc, 4);
    expect($p4->claimVersion(0))->toBeTrue()
        ->and(app(ShortListChecks::class)->preload($p4->fresh()))->toBeFalse()
        ->and($p4->entries()->count())->toBe(0);
});

it('preloads nothing while the Long List is not submitted, and can later', function () {
    $this->p3->update(['status' => SubmissionStatus::Draft]);
    $p4 = Submission::for($this->game, $this->assoc, 4);

    expect(app(ShortListChecks::class)->preload($p4))->toBeFalse()
        ->and($p4->entries()->count())->toBe(0)
        ->and(app(ShortListChecks::class)->submitErrors($p4))->toBe([ShortListChecks::LONG_LIST_FIRST])
        ->and(app(ShortListChecks::class)->allowed($p4))->toBe([[], []]);

    $this->p3->update(['status' => SubmissionStatus::Submitted]);
    expect(app(ShortListChecks::class)->preload($p4->fresh()))->toBeTrue()->and($p4->entries()->count())->toBe(4);
});

it('allows only people in their own Long List slot', function () {
    $p4 = Submission::for($this->game, $this->assoc, 4);
    app(ShortListChecks::class)->preload($p4);
    $checks = app(ShortListChecks::class);

    expect($checks->errors($p4, [$this->m60->id => [(string) $this->ali->id]], [$this->judo->id => [(string) $this->coach->id]]))->toBe([])
        // Sara is on the Long List, but in Women -52kg, not Men -60kg.
        ->and($checks->errors($p4, [$this->m60->id => [(string) $this->ali->id, (string) $this->sara->id]], []))
        ->toBe(["athletes.{$this->m60->id}" => ShortListChecks::NOT_ON_LONG_LIST])
        ->and($checks->errors($p4, [$this->mixed->id => [(string) $this->ali->id]], [$this->judo->id => [(string) $this->ali->id]]))
        ->toBe(["athletes.{$this->mixed->id}" => ShortListChecks::NOT_ON_LONG_LIST, "officials.{$this->judo->id}" => ShortListChecks::NOT_ON_LONG_LIST]);
});

it('loads only the saved entries that are still on the Long List, with every Long List slot present', function () {
    $p4 = Submission::for($this->game, $this->assoc, 4);
    app(ShortListChecks::class)->preload($p4);
    $this->p3->entries()->where('person_id', $this->omar->id)->delete();
    $p4->entries()->where('person_id', $this->sara->id)->delete();

    expect(app(ShortListChecks::class)->selection($p4))->toBe([
        [$this->m60->id => [(string) $this->ali->id], $this->w52->id => []],
        [$this->judo->id => [(string) $this->coach->id]],
    ]);
});

it('counts distinct athletes by gender and officials per sport against the Phase 4 limits', function () {
    $p4 = Submission::for($this->game, $this->assoc, 4);
    app(ShortListChecks::class)->preload($p4);
    $selection = [
        [$this->m60->id => [(string) $this->ali->id], $this->mixed->id => [(string) $this->ali->id, (string) $this->omar->id, (string) $this->sara->id],
            $this->w52->id => [(string) $this->sara->id]],
        [$this->judo->id => [(string) $this->coach->id]],
    ];

    $over = app(ShortListChecks::class)->overLimits($p4, $selection);
    expect($over)->toBe([
        ['sport' => 'Judo', 'kind' => 'men', 'count' => 2, 'max' => 1],
        ['sport' => 'Judo', 'kind' => 'officials', 'count' => 1, 'max' => 0],
    ])->and(ShortListChecks::describe($over[0]))->toBe('Judo: 2 men (limit 1)')
        ->and(ShortListChecks::describe($over[1]))->toBe('Judo: 1 team officials (limit 0)');

    $this->judo->update(['p4_max_men' => 5, 'p4_max_officials' => null]);
    expect(app(ShortListChecks::class)->overLimits($p4, $selection))->toBe([]);
});

it('requires a justification to submit and to approve when over a limit', function () {
    Bus::fake();
    Notification::fake();
    $this->game->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $p4 = Submission::for($this->game, $this->assoc, 4); // preloaded: 2 men > 1, 1 official > 0
    app(ShortListChecks::class)->preload($p4);
    $editor = User::factory()->assocEditor($this->assoc)->create();
    $admin = User::factory()->assocAdmin($this->assoc)->create();
    $flow = app(SubmissionWorkflow::class);

    expect(fn () => $flow->submit($p4->fresh(), $editor))->toThrow(ValidationException::class, ShortListChecks::JUSTIFICATION_REQUIRED);

    $p4->update(['justification' => 'Both can medal']);
    expect($flow->submit($p4->fresh(), $editor)->status)->toBe(SubmissionStatus::AwaitingAssocAdmin);

    $p4->update(['justification' => null]);
    expect(fn () => $flow->approve($p4->fresh(), $admin))->toThrow(ValidationException::class, ShortListChecks::JUSTIFICATION_REQUIRED);

    $p4->update(['justification' => 'Both can medal']);
    expect($flow->approve($p4->fresh(), $admin)->status)->toBe(SubmissionStatus::Submitted);
});

it('blocks submitting a Short List while the Long List is not submitted', function () {
    $this->game->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $p4 = Submission::for($this->game, $this->assoc, 4);
    app(ShortListChecks::class)->preload($p4);
    $this->p3->update(['status' => SubmissionStatus::Draft]);

    expect(fn () => app(SubmissionWorkflow::class)->submit($p4->fresh(), User::factory()->assocAdmin($this->assoc)->create()))
        ->toThrow(ValidationException::class, ShortListChecks::LONG_LIST_FIRST);
});
