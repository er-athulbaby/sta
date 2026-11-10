<?php

use App\Enums\EventGender;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\LongListDetail;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Services\Reports\Builder\Engine;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;
use App\Services\Reports\Builder\ReportResult;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Sensitive plaintext values set through the models (encrypted casts included); none may reach a report. */
const SUBJECT_SECRETS = ['SECRETPP1', '990000001', 'SECRET-BLOOD', 'SECRET-ALLERGY', 'SECRET-CONDITION', 'SECRET-MEDICATION',
    'SECRET-POLICY', 'SECRET-TRAVEL-POLICY', 'SECRET-VACCINES', 'Vegan'];

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-10 10:00'));
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games', 'start_date' => '2026-12-01', 'end_date' => '2026-12-10']);
    $this->otherGame = Game::factory()->create(['name' => 'Gulf Games']);
    $this->fed = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $this->game->associations()->attach($this->fed);
    $shirt = ClothingItem::factory()->create(['name' => 'T-Shirt', 'sizes' => ['S', 'M', 'L']]);
    $shoes = ClothingItem::factory()->create(['name' => 'Shoes', 'sizes' => ['42', '43']]);
    $this->game->clothingItems()->attach($shirt);
    $judo = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->fed->id, 'name' => 'Judo'])->id]);
    $m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);

    $this->ali = Person::factory()->create(['association_id' => $this->fed->id, 'first_name' => 'Ali', 'last_name' => 'Hasan', 'dob' => '2005-12-05',
        'passport_no' => 'SECRETPP1', 'cpr_no' => '990000001', 'blood_group' => 'SECRET-BLOOD', 'allergies' => 'SECRET-ALLERGY',
        'chronic_conditions' => 'SECRET-CONDITION', 'medication' => 'SECRET-MEDICATION', 'insurance_policy_no' => 'SECRET-POLICY']);
    $omar = Person::factory()->create(['association_id' => $this->fed->id, 'first_name' => 'Omar', 'last_name' => 'Saleh', 'dob' => '2004-01-01']);
    $coach = Person::factory()->official()->create(['association_id' => $this->fed->id, 'first_name' => 'Coach', 'last_name' => 'Karim',
        'meal_requirement' => 'Vegan']);
    Person::factory()->create(['association_id' => $this->fed->id, 'first_name' => 'Not', 'last_name' => 'Entered']);
    $this->ali->syncSizes([$shirt->id => 'L', $shoes->id => '42']);
    $coach->syncSizes([$shirt->id => 'M']);

    $sub = fn (int $phase) => Submission::for($this->game, $this->fed, $phase);
    $athlete = fn (Submission $s, Person $p) => $s->entries()->create(['person_id' => $p->id, 'game_event_id' => $m60->id, 'game_sport_id' => $judo->id]);
    $sub(1)->answers()->create(['game_sport_id' => $judo->id, 'answer' => 'yes']);
    $p2 = $sub(2);
    $p2->numbers()->create(['game_event_id' => $m60->id, 'count' => 2]);
    $p2->sportTotals()->create(['game_sport_id' => $judo->id, 'male' => 2, 'female' => 0, 'gold' => 1, 'silver' => null, 'bronze' => 1]);
    $p3 = $sub(3);
    $athlete($p3, $this->ali);
    $athlete($p3, $omar);
    $p3->entries()->create(['person_id' => $coach->id, 'game_event_id' => null, 'game_sport_id' => $judo->id]);
    $p3->details()->create(['person_id' => $this->ali->id, 'insurance_policy_no' => 'SECRET-TRAVEL-POLICY', 'vaccinations' => 'SECRET-VACCINES']);
    $this->p4 = $sub(4);
    $athlete($this->p4, $this->ali);
    $athlete($this->p4, $omar);
    $p3->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BJF-G001-P3-R1', 'submitted_at' => '2026-11-05 09:00:00']);
    $this->p4->update(['justification' => 'Both can medal', 'deadline_override' => '2026-11-20 23:59:00']);
    $this->game->phase(4)->update(['deadline' => '2026-11-15 23:59:00']);
});

function runSubject(string $subject, ?int $gameId, array $changes = []): ReportResult
{
    $s = Registry::get($subject);

    return app(Engine::class)->run(ReportDefinition::fromArray([...ReportDefinition::fresh($s, $gameId), 'columns' => array_keys($s->fields()), ...$changes]));
}

/** @return list<list<mixed>> displayed values of the given columns, row by row */
function shown(ReportResult $r, string ...$keys): array
{
    $fields = collect($r->columns)->keyBy('key');

    return array_map(fn (array $row) => array_map(fn (string $k) => $fields[$k]->display($row['cells'][$k]), $keys), $r->rows);
}

it('offers the seven subjects', function () {
    expect(array_keys(Registry::all()))->toBe(['people', 'entries', 'submissions', 'phase1_answers', 'phase2_numbers', 'phase2_sport_totals', 'clothing_sizes']);
});

it('requires a game for game-based subjects only', function (string $subject, bool $required) {
    $errors = [];
    try {
        ReportDefinition::fromArray(ReportDefinition::fresh(Registry::get($subject)));
    } catch (ValidationException $e) {
        $errors = $e->errors();
    }

    expect($errors === ['game_id' => ['Choose a game.']])->toBe($required)
        ->and($required || $errors === [])->toBeTrue();
})->with([
    ['people', false], ['entries', true], ['submissions', true], ['phase1_answers', true], ['phase2_numbers', true],
    ['phase2_sport_totals', true], ['clothing_sizes', false],
]);

it('narrows people to those on a list of the game', function () {
    $names = fn (?int $game) => array_column(shown(runSubject('people', $game), 'full_name'), 0);

    expect($names($this->game->id))->toBe(['Ali Hasan', 'Coach Karim', 'Omar Saleh'])
        ->and($names(null))->toHaveCount(4)
        ->and($names($this->otherGame->id))->toBe([]);
});

it('reports entries with list, event or Official, and age at game start', function () {
    $r = runSubject('entries', $this->game->id, ['filters' => [['field' => 'phase', 'op' => 'in', 'value' => ['3']]]]);

    expect(shown($r, 'game', 'phase', 'status', 'sport', 'event', 'event_gender', 'full_name', 'age'))->toBe([
        ['Asian Indoor Games', 'Long List', 'Submitted', 'Judo', 'Men -60kg', 'Male', 'Ali Hasan', 20],
        ['Asian Indoor Games', 'Long List', 'Submitted', 'Judo', 'Official', null, 'Coach Karim', 21],
        ['Asian Indoor Games', 'Long List', 'Submitted', 'Judo', 'Men -60kg', 'Male', 'Omar Saleh', 22],
    ])->and(runSubject('entries', $this->game->id)->rowCount)->toBe(5)
        ->and(runSubject('entries', $this->otherGame->id)->rowCount)->toBe(0);
});

it('reports submissions with the effective deadline', function () {
    $run = fn () => shown(runSubject('submissions', $this->game->id, ['sort' => ['field' => 'phase', 'dir' => 'asc']]),
        'phase', 'status', 'reference', 'submitted_at', 'deadline', 'justification');

    expect($run())->toBe([
        ['Phase 1 – Entry by Sport', 'Draft', null, null, null, null],
        ['Phase 2 – Entry by Number', 'Draft', null, null, null, null],
        ['Phase 3 – Long List', 'Submitted', 'BJF-G001-P3-R1', '2026-11-05', null, null],
        ['Phase 4 – Short List', 'Draft', null, null, '2026-11-20', 'Both can medal'],
    ])->and(Registry::get('submissions')->fields())->not->toHaveKey('over_limit');

    $this->p4->update(['deadline_override' => null]);
    expect($run()[3][4])->toBe('2026-11-15');
});

it('reports phase 1 answers, phase 2 numbers and phase 2 sport totals', function () {
    expect(shown(runSubject('phase1_answers', $this->game->id), 'game', 'association', 'sport', 'answer', 'status'))
        ->toBe([['Asian Indoor Games', 'Bahrain Judo Federation', 'Judo', 'Yes', 'Draft']])
        ->and(shown(runSubject('phase2_numbers', $this->game->id), 'sport', 'event', 'gender', 'count'))
        ->toBe([['Judo', 'Men -60kg', 'Male', 2]])
        ->and(shown(runSubject('phase2_sport_totals', $this->game->id),
            'game', 'association', 'sport', 'status', 'male', 'female', 'gold', 'silver', 'bronze'))
        ->toBe([['Asian Indoor Games', 'Bahrain Judo Federation', 'Judo', 'Draft', 2, 0, 1, null, 1]])
        ->and(runSubject('phase2_sport_totals', $this->otherGame->id)->rowCount)->toBe(0);
});

it('reports clothing sizes, narrowed by game to listed people and the game items', function () {
    expect(shown(runSubject('clothing_sizes', $this->game->id), 'full_name', 'type', 'item', 'size'))->toBe([
        ['Ali Hasan', 'Athlete', 'T-Shirt', 'L'],
        ['Coach Karim', 'Team official', 'T-Shirt', 'M'],
    ])->and(runSubject('clothing_sizes', null)->rowCount)->toBe(3);
});

it('never exposes passport, CPR, medical, insurance or vaccination data', function () {
    foreach (Registry::all() as $subject) {
        foreach (array_keys($subject->fields()) as $key) {
            expect($key)->not->toMatch('/passport_no|cpr_no|blood|allerg|condition|medic|insurance|vaccin|injur|meal|clearance|wada/');
        }
    }

    // The fixture really holds every secret (read back through the encrypted casts), so the scan below is not vacuous.
    $ali = Person::find($this->ali->id);
    $detail = LongListDetail::firstOrFail();
    $coach = Person::where('first_name', 'Coach')->firstOrFail();
    expect([$ali->passport_no, $ali->cpr_no, $ali->blood_group, $ali->allergies, $ali->chronic_conditions, $ali->medication,
        $ali->insurance_policy_no, $detail->insurance_policy_no, $detail->vaccinations, $coach->meal_requirement])->toBe(SUBJECT_SECRETS);

    $scanned = 0;
    foreach (array_keys(Registry::all()) as $subject) {
        foreach ([$this->game->id, null] as $gameId) {
            if ($gameId === null && Registry::get($subject)->gameBased()) {
                continue;
            }
            foreach (runSubject($subject, $gameId)->rows as $row) {
                foreach (array_filter($row['cells'], fn ($c) => $c !== null) as $cell) {
                    $scanned++;
                    foreach (SUBJECT_SECRETS as $secret) {
                        expect(str_contains((string) $cell, $secret))->toBeFalse("{$subject} exposed a sensitive value");
                    }
                }
            }
        }
    }
    expect($scanned)->toBeGreaterThan(100);
});
