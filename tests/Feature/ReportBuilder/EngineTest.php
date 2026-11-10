<?php

use App\Enums\PersonGender;
use App\Models\Association;
use App\Models\Person;
use App\Services\Reports\Builder\Engine;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;
use App\Services\Reports\Builder\ReportResult;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-10 10:00'));
    $judo = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $karate = Association::factory()->create(['code' => 'BKF', 'name' => 'Bahrain Karate Federation']);
    Person::factory()->create(['association_id' => $judo->id, 'first_name' => 'Ali', 'last_name' => 'Hasan', 'dob' => '2005-12-05',
        'passport_expiry' => '2027-01-15', 'photo_path' => 'photos/a.jpg']);
    Person::factory()->create(['association_id' => $judo->id, 'first_name' => 'Omar', 'last_name' => 'Saleh', 'full_name' => 'Omar Saleh Al-Bahraini',
        'dob' => '2004-01-01', 'passport_expiry' => '2028-06-30']);
    Person::factory()->official()->create(['association_id' => $judo->id, 'first_name' => 'Coach', 'last_name' => 'Karim', 'dob' => '1980-05-20',
        'passport_expiry' => '2027-03-01']);
    Person::factory()->complete()->create(['association_id' => $karate->id, 'first_name' => 'Mariam', 'last_name' => 'Ahmed', 'full_name' => 'Mariam Ahmed',
        'gender' => PersonGender::Female, 'dob' => '2005-03-10', 'passport_expiry' => '2027-01-20']);
});

function runPeople(array $changes = [], ?int $limit = Engine::SCREEN_LIMIT): ReportResult
{
    return app(Engine::class)->run(ReportDefinition::fromArray([...ReportDefinition::fresh(Registry::get('people')), ...$changes]), $limit);
}

/** @return list<string> */
function peopleNames(ReportResult $r): array
{
    return array_map(fn (array $row) => $row['cells']['full_name'], $r->rows);
}

it('runs the default people report sorted by name', function () {
    $r = runPeople();

    expect($r->rowCount)->toBe(4)
        ->and(peopleNames($r))->toBe(['Ali Hasan', 'Coach Karim', 'Mariam Ahmed', 'Omar Saleh Al-Bahraini'])
        ->and($r->lines()[0])->toBe(['kind' => 'row', 'cells' => ['Ali Hasan', 'BJF-AT-0001', 'Athlete', 'Male', '2005-12-05', 'Bahrain Judo Federation']])
        ->and($r->lines()[4])->toBe(['kind' => 'total', 'cells' => ['Total (4)', '', '', '', '', '']]);
});

it('never compares the passport issue date with an empty string (MySQL 8 rejects it)', function () {
    $sql = Registry::get('people')->fields()['complete']->sql;
    expect($sql)->toContain('(people.passport_issue_date IS NOT NULL)')->not->toContain("passport_issue_date <> ''");
});

it('computes age today, completeness and attachments in SQL', function () {
    $r = runPeople(['columns' => ['full_name', 'age', 'complete', 'has_photo'], 'sort' => ['field' => 'age', 'dir' => 'desc']]);

    expect(array_map(fn ($l) => $l['cells'], array_slice($r->lines(), 0, 4)))->toBe([
        ['Coach Karim', 46, 'No', 'No'],
        ['Omar Saleh Al-Bahraini', 22, 'No', 'No'],
        ['Mariam Ahmed', 21, 'Yes', 'Yes'],
        ['Ali Hasan', 20, 'No', 'Yes'],
    ]);
});

it('applies each operator with bound values', function (array $filter, array $expected) {
    expect(peopleNames(runPeople(['filters' => [$filter]])))->toBe($expected);
})->with([
    'contains, any case' => [['field' => 'full_name', 'op' => 'contains', 'value' => 'KARIM'], ['Coach Karim']],
    'contains a wildcard literally' => [['field' => 'full_name', 'op' => 'contains', 'value' => '%'], []],
    'is, any case' => [['field' => 'association', 'op' => 'is', 'value' => 'bahrain judo federation'], ['Ali Hasan', 'Coach Karim', 'Omar Saleh Al-Bahraini']],
    'is not' => [['field' => 'association', 'op' => 'is_not', 'value' => 'Bahrain Judo Federation'], ['Mariam Ahmed']],
    'is empty' => [['field' => 'function', 'op' => 'empty', 'value' => null], ['Ali Hasan', 'Mariam Ahmed', 'Omar Saleh Al-Bahraini']],
    'is not empty' => [['field' => 'function', 'op' => 'not_empty', 'value' => null], ['Coach Karim']],
    'enum any of' => [['field' => 'type', 'op' => 'in', 'value' => ['official']], ['Coach Karim']],
    'enum none of' => [['field' => 'gender', 'op' => 'not_in', 'value' => ['male']], ['Mariam Ahmed']],
    'number between' => [['field' => 'age', 'op' => 'between', 'value' => ['20', '21']], ['Ali Hasan', 'Mariam Ahmed']],
    'number greater than' => [['field' => 'age', 'op' => 'gt', 'value' => '30'], ['Coach Karim']],
    'number equal' => [['field' => 'age', 'op' => 'eq', 'value' => '22'], ['Omar Saleh Al-Bahraini']],
    'date before' => [['field' => 'passport_expiry', 'op' => 'before', 'value' => '2027-02-01'], ['Ali Hasan', 'Mariam Ahmed']],
    'date on' => [['field' => 'passport_expiry', 'op' => 'on', 'value' => '2027-03-01'], ['Coach Karim']],
    'date between, inclusive' => [['field' => 'dob', 'op' => 'between', 'value' => ['2004-01-01', '2005-03-10']], ['Mariam Ahmed', 'Omar Saleh Al-Bahraini']],
    'boolean yes' => [['field' => 'complete', 'op' => 'yes', 'value' => null], ['Mariam Ahmed']],
    'boolean no' => [['field' => 'has_photo', 'op' => 'no', 'value' => null], ['Coach Karim', 'Omar Saleh Al-Bahraini']],
]);

it('caps the rows on screen but counts them all', function () {
    $capped = runPeople([], 2);
    $all = runPeople([], null);

    expect($capped->rows)->toHaveCount(2)->and($capped->rowCount)->toBe(4)->and($capped->truncated())->toBeTrue()
        ->and($all->rows)->toHaveCount(4)->and($all->truncated())->toBeFalse()
        ->and(Engine::SCREEN_LIMIT)->toBe(500);
});

it('groups two levels with counts and totals and lists subtotal lines in order', function () {
    $r = runPeople([
        'columns' => ['association', 'type', 'full_name', 'age'],
        'groups' => [['field' => 'association', 'bucket' => null], ['field' => 'type', 'bucket' => null]],
        'totals' => ['age' => 'sum'],
    ]);

    expect(array_map(fn ($l) => $l['cells'], $r->lines()))->toBe([
        ['Bahrain Judo Federation (3)', '', '', 88],
        ['— Athlete (2)', '', '', 42],
        ['Bahrain Judo Federation', 'Athlete', 'Ali Hasan', 20],
        ['Bahrain Judo Federation', 'Athlete', 'Omar Saleh Al-Bahraini', 22],
        ['— Team official (1)', '', '', 46],
        ['Bahrain Judo Federation', 'Team official', 'Coach Karim', 46],
        ['Bahrain Karate Federation (1)', '', '', 21],
        ['— Athlete (1)', '', '', 21],
        ['Bahrain Karate Federation', 'Athlete', 'Mariam Ahmed', 21],
        ['Total (4)', '', '', 109],
    ])->and(array_column($r->lines(), 'kind'))->toBe(['group', 'group', 'row', 'row', 'group', 'row', 'group', 'group', 'row', 'total']);
});

it('groups dates by year and by month', function () {
    $groupLines = fn (string $bucket) => array_values(array_map(fn ($l) => $l['cells'],
        array_filter(runPeople(['columns' => ['full_name', 'passport_expiry'], 'totals' => ['passport_expiry' => 'min'],
            'groups' => [['field' => 'passport_expiry', 'bucket' => $bucket]]])->lines(), fn ($l) => $l['kind'] === 'group')));

    expect($groupLines('year'))->toBe([['2027 (3)', '2027-01-15'], ['2028 (1)', '2028-06-30']])
        ->and($groupLines('month'))->toBe([['2027-01 (2)', '2027-01-15'], ['2027-03 (1)', '2027-03-01'], ['2028-06 (1)', '2028-06-30']]);
});

it('totals numbers and dates over all rows', function () {
    $total = fn (string $field, string $fn) => runPeople(['columns' => ['full_name', $field], 'totals' => [$field => $fn]])->totals[$field];

    expect($total('age', 'avg'))->toEqual(27.25)
        ->and($total('age', 'min'))->toEqual(20)
        ->and($total('age', 'max'))->toEqual(46)
        ->and($total('passport_expiry', 'max'))->toBe('2028-06-30');
});

it('groups and orders the aggregate by the select alias, so MySQL ONLY_FULL_GROUP_BY accepts it', function () {
    DB::enableQueryLog();
    runPeople(['columns' => ['full_name', 'passport_expiry'], 'groups' => [['field' => 'passport_expiry', 'bucket' => 'year']]]);
    $grouped = collect(DB::getQueryLog())->pluck('query')->first(fn (string $sql) => str_contains($sql, 'group by'));

    expect($grouped)->not->toBeNull()
        ->and(Str::after($grouped, 'group by'))->toBe(' g0 order by g0 IS NULL, g0');
});

it('breaks sort ties by the base table id, so screen and Excel get the same rows', function () {
    DB::enableQueryLog();
    $r = runPeople(['sort' => ['field' => 'association', 'dir' => 'desc']]);
    $detail = collect(DB::getQueryLog())->pluck('query')->last();

    expect(peopleNames($r))->toBe(['Mariam Ahmed', 'Ali Hasan', 'Omar Saleh Al-Bahraini', 'Coach Karim'])
        ->and($detail)->toEndWith('"people"."id" asc limit 500');
});
