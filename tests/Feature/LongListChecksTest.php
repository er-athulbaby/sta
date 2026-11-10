<?php

use App\Enums\EventGender;
use App\Enums\PersonGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\InterestAnswer;
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\Phases\LongListChecks;
use App\Services\Phases\SubmissionWorkflow;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    Bus::fake();
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create(['start_date' => '2027-03-01', 'end_date' => '2027-03-10']);
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id, 'dob_from' => '2004-01-01', 'dob_to' => '2009-12-31',
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $mk = fn (string $name, EventGender $g) => GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => $name, 'gender' => $g])->id]);
    $this->m60 = $mk('Men -60kg', EventGender::Men);
    $this->w48 = $mk('Women -48kg', EventGender::Women);
    $this->team = $mk('Mixed team', EventGender::Mixed);
    $this->sub = Submission::for($this->game, $this->assoc, 3);
    $this->man = Person::factory()->complete()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan',
        'cpr_front_path' => 'scans/c.jpg']);
    $this->checks = app(LongListChecks::class);
});

it('accepts a list that follows the rules', function () {
    expect($this->checks->errors($this->sub, [$this->m60->id => [(string) $this->man->id], $this->team->id => [$this->man->id]], []))->toBe([]);
});

it('rejects an athlete whose gender does not match the event', function () {
    expect($this->checks->errors($this->sub, [$this->w48->id => [$this->man->id]], []))
        ->toBe(["athletes.{$this->w48->id}" => 'Women -48kg: Ali Hasan (Male) cannot enter a Female event.']);
});

it('rejects people of another association, inactive people and the wrong type', function () {
    $other = Person::factory()->create();
    $inactive = Person::factory()->create(['association_id' => $this->assoc->id, 'is_active' => false, 'first_name' => 'Old', 'last_name' => 'Timer']);
    $official = Person::factory()->official()->create(['association_id' => $this->assoc->id, 'first_name' => 'Coach', 'last_name' => 'One']);
    $key = "athletes.{$this->m60->id}";

    expect($this->checks->errors($this->sub, [$this->m60->id => [$other->id]], []))->toBe([$key => "Men -60kg: Choose people from your association's registry."])
        ->and($this->checks->errors($this->sub, [$this->m60->id => [$inactive->id]], []))->toBe([$key => 'Men -60kg: Old Timer is inactive.'])
        ->and($this->checks->errors($this->sub, [$this->m60->id => [$official->id]], []))->toBe([$key => 'Men -60kg: Coach One is a team official, not an athlete.'])
        ->and($this->checks->errors($this->sub, [], [$this->judo->id => [$this->man->id]]))
        ->toBe(["officials.{$this->judo->id}" => 'Judo: Ali Hasan is an athlete, not a team official.']);
});

it('rejects events and sports hidden by a phase 1 "no"', function () {
    $this->game->phase(1)->update(['enabled' => true]);
    InterestAnswer::create(['submission_id' => Submission::for($this->game, $this->assoc, 1)->id, 'game_sport_id' => $this->judo->id, 'answer' => 'no']);

    expect($this->checks->errors($this->sub->fresh(), [$this->m60->id => [$this->man->id]], [$this->judo->id => [$this->man->id]]))->toBe([
        "athletes.{$this->m60->id}" => 'This event is not open to your association in this game.',
        "officials.{$this->judo->id}" => 'This sport is not open to your association in this game.',
    ]);
});

it('warns about expiring documents, missing scans, age range and missing sizes', function () {
    $shirt = ClothingItem::factory()->create(['name' => 'T-Shirt']);
    $this->game->clothingItems()->attach($shirt);
    $p = Person::factory()->create(['association_id' => $this->assoc->id, 'dob' => '2001-05-05',
        'passport_expiry' => '2027-03-05', 'cpr_expiry' => '2027-03-09']);

    expect($this->checks->warnings($this->game->fresh(), $p, [$this->judo]))->toBe([
        'Passport expires before the games end (5 Mar 2027).',
        'CPR expires before the games end (9 Mar 2027).',
        'Passport scan missing.',
        'CPR scan missing.',
        'Date of birth is outside the Judo age range.',
        'T-Shirt size missing.',
    ]);

    $this->man->syncSizes([$shirt->id => 'M']);
    expect($this->checks->warnings($this->game->fresh(), $this->man->fresh(), [$this->judo]))->toBe([]);
});

it('compares with phase 2 numbers only once phase 2 is submitted', function () {
    $p2 = Submission::for($this->game, $this->assoc, 2);
    NumberEntry::create(['submission_id' => $p2->id, 'game_event_id' => $this->m60->id, 'count' => 1]);
    expect($this->checks->phase2Numbers($this->sub))->toBeNull();

    $p2->update(['status' => SubmissionStatus::Submitted]);
    expect($this->checks->phase2Numbers($this->sub))->toBe([$this->m60->id => 1]);
});

it('builds the table rows with roles, age, completeness and warnings', function () {
    $row = $this->checks->rows($this->sub, [$this->m60->id => [(string) $this->man->id]], [])->sole();

    expect($row['person']->is($this->man))->toBeTrue()
        ->and($row['roles'])->toBe(['Judo · Men -60kg'])
        ->and($row['age'])->toBe(21)
        ->and($row['missing'])->toBe([])
        ->and($row['on_list'])->toBeFalse();
});

it('blocks submitting a long list with an incomplete person and names the missing fields', function () {
    $sara = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Sara', 'last_name' => 'Ali', 'gender' => PersonGender::Female]);
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $sara->id, 'game_event_id' => $this->w48->id, 'game_sport_id' => $this->judo->id]);

    expect(fn () => app(SubmissionWorkflow::class)->submit($this->sub, User::factory()->assocAdmin($this->assoc)->create()))
        ->toThrow(ValidationException::class, 'Sara Ali is incomplete: Full name, Photo, Passport issuing country, Passport issue date, Passport scan.');
    expect($this->sub->fresh()->status)->toBe(SubmissionStatus::Draft);
});

it('re-checks hard errors of the saved list on submit', function () {
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $this->man->id, 'game_event_id' => $this->w48->id, 'game_sport_id' => $this->judo->id]);

    expect(fn () => app(SubmissionWorkflow::class)->submit($this->sub, User::factory()->assocAdmin($this->assoc)->create()))
        ->toThrow(ValidationException::class, 'cannot enter a Female event');
});

it('submits a long list of complete people', function () {
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $this->man->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);

    expect(app(SubmissionWorkflow::class)->submit($this->sub, User::factory()->assocAdmin($this->assoc)->create())->status)
        ->toBe(SubmissionStatus::Submitted);
});

it('ignores saved entries in a sport later hidden by a phase 1 "no"', function () {
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $this->man->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    $this->game->phase(1)->update(['enabled' => true]);
    InterestAnswer::create(['submission_id' => Submission::for($this->game, $this->assoc, 1)->id, 'game_sport_id' => $this->judo->id, 'answer' => 'no']);

    expect($this->checks->selection($this->sub->fresh()))->toBe([[], []])
        ->and($this->checks->submitErrors($this->sub->fresh()))->toBe([]);
});

it('treats a person of another association as not in the registry', function () {
    $other = Person::factory()->complete()->create();

    expect($this->checks->errors($this->sub, [$this->m60->id => [$other->id]], []))
        ->toBe(["athletes.{$this->m60->id}" => "Men -60kg: Choose people from your association's registry."]);
});
