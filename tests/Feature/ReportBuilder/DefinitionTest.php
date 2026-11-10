<?php

use App\Enums\Role;
use App\Models\Game;
use App\Models\Person;
use App\Models\User;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;
use Illuminate\Validation\ValidationException;

/** @return array<string, string> first message per error key; [] when the definition is valid */
function definitionErrors(array $changes): array
{
    try {
        ReportDefinition::fromArray([...ReportDefinition::fresh(Registry::get('people')), ...$changes]);

        return [];
    } catch (ValidationException $e) {
        return array_map(fn (array $messages) => $messages[0], $e->errors());
    }
}

it('round-trips a fresh people definition with its version', function () {
    $def = ReportDefinition::fromArray(ReportDefinition::fresh(Registry::get('people')));

    expect($def->toArray())->toBe([
        'version' => 1,
        'subject' => 'people',
        'game_id' => null,
        'columns' => ['full_name', 'code', 'type', 'gender', 'dob', 'association'],
        'filters' => [],
        'groups' => [],
        'totals' => [],
        'sort' => ['field' => 'full_name', 'dir' => 'asc'],
    ]);
});

it('accepts and normalises filters, groups and totals', function () {
    $game = Game::factory()->create();

    $def = ReportDefinition::fromArray([...ReportDefinition::fresh(Registry::get('people')),
        'game_id' => (string) $game->id,
        'columns' => ['full_name', 'age', 'dob'],
        'filters' => [
            ['field' => 'age', 'op' => 'between', 'value' => ['18', '25.5']],
            ['field' => 'type', 'op' => 'in', 'value' => ['athlete']],
            ['field' => 'has_photo', 'op' => 'yes', 'value' => 'ignored'],
        ],
        'groups' => [['field' => 'dob', 'bucket' => 'year'], ['field' => 'gender', 'bucket' => '']],
        'totals' => ['age' => 'avg', 'dob' => 'min', 'full_name' => ''],
        'sort' => ['field' => 'age', 'dir' => 'desc'],
    ]);

    expect($def->toArray())->toMatchArray([
        'game_id' => $game->id,
        'filters' => [
            ['field' => 'age', 'op' => 'between', 'value' => [18, 25.5]],
            ['field' => 'type', 'op' => 'in', 'value' => ['athlete']],
            ['field' => 'has_photo', 'op' => 'yes', 'value' => null],
        ],
        'groups' => [['field' => 'dob', 'bucket' => 'year'], ['field' => 'gender', 'bucket' => null]],
        'totals' => ['age' => 'avg', 'dob' => 'min'],
        'sort' => ['field' => 'age', 'dir' => 'desc'],
    ]);
});

it('refuses a bad definition', function (array $changes, string $key, string $message) {
    expect(definitionErrors($changes))->toHaveKey($key, $message);
})->with([
    'unknown subject' => [['subject' => 'invoices'], 'subject', 'Choose a subject from the list.'],
    'another version' => [['version' => 2], 'version', 'This report was saved by another version of the builder.'],
    'unknown column' => [['columns' => ['full_name', 'passport_no']], 'columns', 'Choose one or more of the listed columns.'],
    'no columns' => [['columns' => []], 'columns', 'Choose one or more of the listed columns.'],
    'duplicate column' => [['columns' => ['code', 'code']], 'columns', 'Choose one or more of the listed columns.'],
    'too many columns' => [['columns' => array_fill(0, 31, 'code')], 'columns', 'At most 30 columns.'],
    'unknown filter field' => [['filters' => [['field' => 'blood_group', 'op' => 'is', 'value' => 'A+']]], 'filters.0.field', 'Choose a field to filter on.'],
    'operator of another type' => [['filters' => [['field' => 'age', 'op' => 'contains', 'value' => '2']]], 'filters.0.op', 'Choose how to compare.'],
    'number that is not a number' => [['filters' => [['field' => 'age', 'op' => 'gt', 'value' => 'abc']]], 'filters.0.value', 'Enter a valid value for Age (today).'],
    'impossible date' => [['filters' => [['field' => 'dob', 'op' => 'before', 'value' => '2026-02-30']]], 'filters.0.value', 'Enter a valid value for Date of birth.'],
    'between with one value' => [['filters' => [['field' => 'age', 'op' => 'between', 'value' => [18]]]], 'filters.0.value', 'Enter a valid value for Age (today).'],
    'enum value not offered' => [['filters' => [['field' => 'type', 'op' => 'in', 'value' => ['coach']]]], 'filters.0.value', 'Enter a valid value for Type.'],
    'empty text' => [['filters' => [['field' => 'code', 'op' => 'is', 'value' => '']]], 'filters.0.value', 'Enter a valid value for Person code.'],
    'too many filters' => [['filters' => array_fill(0, 21, ['field' => 'code', 'op' => 'not_empty', 'value' => null])], 'filters', 'At most 20 filters.'],
    'three group levels' => [['groups' => array_fill(0, 3, ['field' => 'type', 'bucket' => null])], 'groups', 'At most 2 group levels.'],
    'group by a number' => [['groups' => [['field' => 'age', 'bucket' => null]]], 'groups.0.field', 'Choose a field to group by.'],
    'date group by day' => [['groups' => [['field' => 'dob', 'bucket' => 'day']]], 'groups.0.bucket', 'Group dates by month or year.'],
    'total on text' => [['totals' => ['code' => 'sum']], 'totals', 'Totals apply to chosen number columns (sum, average, min, max) and date columns (min, max).'],
    'total on a column not chosen' => [['totals' => ['age' => 'sum']], 'totals', 'Totals apply to chosen number columns (sum, average, min, max) and date columns (min, max).'],
    'sort on an unknown field' => [['sort' => ['field' => 'cpr_no', 'dir' => 'asc']], 'sort', 'Choose a field to sort by.'],
    'sort direction injected' => [['sort' => ['field' => 'code', 'dir' => 'asc; drop table people']], 'sort', 'Choose a field to sort by.'],
    'unknown game' => [['game_id' => 999], 'game_id', 'Choose a game from the list.'],
]);

it('refuses a draft game unless the user is a Super Admin (spec 16.6)', function () {
    $draft = Game::factory()->draft()->create();
    $def = [...ReportDefinition::fresh(Registry::get('people')), 'game_id' => $draft->id];

    expect(fn () => ReportDefinition::fromArray($def))->toThrow(ValidationException::class);
    expect(fn () => ReportDefinition::fromArray($def, User::factory()->create(['role' => Role::Sta])))->toThrow(ValidationException::class);
    expect(ReportDefinition::fromArray($def, User::factory()->create(['role' => Role::SuperAdmin]))->gameId)->toBe($draft->id);
});

it('lists only published games for a non-Super-Admin', function () {
    $published = Game::factory()->create();
    $draft = Game::factory()->draft()->create();

    expect(ReportDefinition::selectableGames(User::factory()->create(['role' => Role::Sta]))->keys()->all())->toBe([$published->id])
        ->and(ReportDefinition::selectableGames(User::factory()->create(['role' => Role::SuperAdmin]))->keys()->all())->toEqualCanonicalizing([$published->id, $draft->id]);
});

it('builds the full name from all four name parts, skipping blanks', function () {
    Person::factory()->create(['full_name' => null, 'first_name' => 'Ali', 'second_name' => '', 'third_name' => 'Hassan', 'last_name' => 'Nasser']);

    $people = Registry::get('people');
    $name = $people->query(null)->selectRaw($people->fields()['full_name']->sql.' as n')->value('n');

    expect($name)->toBe('Ali Hassan Nasser');
});
