<?php

use App\Enums\ExtraFieldType;
use App\Enums\StaPermission;
use App\Models\Association;
use App\Models\ExtraField;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\SavedReport;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\Reports\Builder\Engine;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;
use App\Services\Reports\Builder\ReportResult;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $assoc = Association::factory()->create(['code' => 'BJF']);
    $this->shirt = ExtraField::factory()->create(['label' => 'Shirt name', 'required' => true]);
    $this->weight = ExtraField::factory()->create(['label' => 'Weight class', 'type' => ExtraFieldType::Number]);
    $this->vegan = ExtraField::factory()->create(['label' => 'Vegan', 'type' => ExtraFieldType::YesNo]);
    $this->arrival = ExtraField::factory()->create(['label' => 'Arrival', 'type' => ExtraFieldType::Date]);
    $this->donor = ExtraField::factory()->create(['label' => 'Donor type', 'type' => ExtraFieldType::Choice, 'options' => ['A', 'B'], 'sensitive' => true]);
    $this->ali = Person::factory()->complete()->create(['association_id' => $assoc->id]);   // BJF-AT-0001
    $this->omar = Person::factory()->complete()->create(['association_id' => $assoc->id]);  // BJF-AT-0002
    $this->ali->syncExtraValues([$this->shirt->id => 'ALI', $this->weight->id => '100', $this->vegan->id => '1',
        $this->arrival->id => '2026-12-01', $this->donor->id => 'B']);
    $this->omar->syncExtraValues([$this->weight->id => '65', $this->vegan->id => '0', $this->arrival->id => '2025-11-20']);

    $this->game = Game::factory()->create();
    $gs = GameSport::factory()->create(['game_id' => $this->game->id, 'sport_id' => Sport::factory()->create(['association_id' => $assoc->id])->id]);
    $ge = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $gs->id]);
    Submission::for($this->game, $assoc, 3)->entries()->create(['person_id' => $this->ali->id, 'game_event_id' => $ge->id, 'game_sport_id' => $gs->id]);

    $this->admin = User::factory()->superAdmin()->create();
    $this->sta = User::factory()->sta()->create();
    $this->sta->givePermissionTo(StaPermission::ViewReports->value);
});

function extraDef(string $subject, array $columns, array $filters = [], ?int $gameId = null): array
{
    return [...ReportDefinition::fresh(Registry::get($subject), $gameId), 'columns' => $columns, 'filters' => $filters, 'sort' => ['field' => 'code', 'dir' => 'asc']];
}

function runExtra(User $user, string $subject, array $columns, array $filters = [], ?int $gameId = null): ReportResult
{
    return app(Engine::class)->run(ReportDefinition::fromArray(extraDef($subject, $columns, $filters, $gameId), $user));
}

/** @return list<list<mixed>> displayed cells, row by row */
function shownExtra(ReportResult $r): array
{
    return array_map(fn (array $row) => array_map(fn (Field $f) => $f->display($row['cells'][$f->key]), $r->columns), $r->rows);
}

it('offers active extra fields on People and Entries, sensitive ones only with view_medical', function () {
    ExtraField::factory()->create(['label' => 'Old field', 'is_active' => false]);

    expect(array_keys(Registry::get('people')->fields($this->sta)))->toContain('extra_shirt_name', 'extra_weight_class', 'extra_vegan', 'extra_arrival')
        ->not->toContain('extra_donor_type')->not->toContain('extra_old_field')
        ->and(array_keys(Registry::get('entries')->fields($this->sta)))->toContain('extra_shirt_name')->not->toContain('extra_donor_type')
        ->and(array_keys(Registry::get('people')->fields()))->not->toContain('extra_donor_type')
        ->and(array_keys(Registry::get('submissions')->fields($this->admin)))->not->toContain('extra_shirt_name');

    $this->sta->givePermissionTo(StaPermission::ViewMedical->value);
    expect(array_keys(Registry::get('entries')->fields($this->sta->fresh())))->toContain('extra_donor_type')
        ->and(Registry::get('people')->fields($this->admin)['extra_donor_type']->section)->toBe('Additional information');
});

it('shows, filters (numbers and dates compared as such) and checks completeness with extra fields', function () {
    $columns = ['code', 'extra_shirt_name', 'extra_weight_class', 'extra_vegan', 'extra_arrival', 'extra_donor_type', 'complete'];

    expect(shownExtra(runExtra($this->admin, 'people', $columns)))->toBe([
        ['BJF-AT-0001', 'ALI', 100.0, 'Yes', '2026-12-01', 'B', 'Yes'],
        ['BJF-AT-0002', null, 65.0, 'No', '2025-11-20', null, 'No'], // required "Shirt name" missing
    ])->and(shownExtra(runExtra($this->admin, 'people', ['code'], [['field' => 'extra_weight_class', 'op' => 'gt', 'value' => 70]])))->toBe([['BJF-AT-0001']])
        ->and(shownExtra(runExtra($this->admin, 'people', ['code'], [['field' => 'extra_weight_class', 'op' => 'lt', 'value' => 70]])))->toBe([['BJF-AT-0002']])
        ->and(shownExtra(runExtra($this->admin, 'people', ['code'], [['field' => 'extra_arrival', 'op' => 'after', 'value' => '2026-11-25']])))->toBe([['BJF-AT-0001']])
        ->and(shownExtra(runExtra($this->admin, 'people', ['code'], [['field' => 'extra_vegan', 'op' => 'no', 'value' => null]])))->toBe([['BJF-AT-0002']])
        ->and(shownExtra(runExtra($this->admin, 'people', ['code'], [['field' => 'extra_donor_type', 'op' => 'in', 'value' => ['B']]])))->toBe([['BJF-AT-0001']])
        ->and(shownExtra(runExtra($this->admin, 'entries', ['code', 'extra_shirt_name'], [], $this->game->id)))->toBe([['BJF-AT-0001', 'ALI']]);
});

it('keeps the complete flag on the person fields when no extra field is required', function () {
    $this->shirt->update(['required' => false]);

    expect(shownExtra(runExtra($this->admin, 'people', ['code', 'complete'])))->toBe([['BJF-AT-0001', 'Yes'], ['BJF-AT-0002', 'Yes']]);
});

it('refuses a sensitive extra field in a definition from a user without view_medical', function () {
    expect(fn () => ReportDefinition::fromArray(extraDef('people', ['code', 'extra_donor_type']), $this->sta))->toThrow(ValidationException::class)
        ->and(fn () => ReportDefinition::fromArray(extraDef('people', ['code'], [['field' => 'extra_donor_type', 'op' => 'in', 'value' => ['B']]]), $this->sta))
        ->toThrow(ValidationException::class)
        ->and(fn () => ReportDefinition::fromArray([...extraDef('people', ['code']), 'sort' => ['field' => 'extra_donor_type', 'dir' => 'asc']], $this->sta))
        ->toThrow(ValidationException::class);
});

it('fails a saved report whose extra field was deactivated with a clear message', function () {
    $report = SavedReport::create(['name' => 'Shirts', 'subject' => 'people', 'visibility' => 'private', 'created_by' => $this->admin->id,
        'definition' => ReportDefinition::fromArray(extraDef('people', ['code', 'extra_shirt_name']), $this->admin)->toArray()]);
    $this->shirt->update(['is_active' => false]);

    try {
        $report->fresh()->toDefinition($this->admin);
        $this->fail('Expected the definition to be refused.');
    } catch (ValidationException $e) {
        expect($e->errors()['fields'][0])->toBe('This report uses fields that are no longer available: Shirt name. Remove them and save again.');
    }
});

it('groups by extra fields (text, date by year) with correct counts', function () {
    $groups = fn (array $group) => array_values(array_map(fn (array $g) => $g['label'].' ('.$g['count'].')',
        app(Engine::class)->run(ReportDefinition::fromArray([...extraDef('people', ['code']), 'groups' => [$group]], $this->admin))->groups));

    ExtraField::factory()->create(['label' => 'Unused']);
    expect($groups(['field' => 'extra_shirt_name', 'bucket' => null]))->toBe(['ALI (1)', '— (1)'])
        ->and($groups(['field' => 'extra_arrival', 'bucket' => 'year']))->toBe(['2025 (1)', '2026 (1)']);
});

it('shows number totals of extra fields as plain numbers (MySQL DECIMAL comes back as "165.0000")', function () {
    $r = app(Engine::class)->run(ReportDefinition::fromArray([...extraDef('people', ['code', 'extra_weight_class']),
        'totals' => ['extra_weight_class' => 'sum']], $this->admin));

    expect(array_slice($r->lines(), -1)[0]['cells'])->toEqual(['Total (2)', 165])
        ->and((string) Registry::get('people')->fields($this->admin)['extra_weight_class']->display('165.0000'))->toBe('165');
});
