<?php

use App\Enums\ExtraFieldType;
use App\Enums\PersonType;
use App\Enums\StaPermission;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\GameDashboard;
use App\Filament\Resources\Games\Pages\GameExportFiles;
use App\Filament\Resources\Games\Pages\ListGames;
use App\Models\ExportLayout;
use App\Models\ExtraField;
use App\Models\Game;
use App\Models\User;
use App\Services\GameFiles\ExportSources;
use Filament\Forms\Components\Repeater;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    ExtraField::factory()->create(['label' => 'Shirt name']);
    ExtraField::factory()->create(['label' => 'Donor type', 'type' => ExtraFieldType::Choice, 'options' => ['A'], 'sensitive' => true]);
    $this->exporter = User::factory()->sta()->create();
    $this->exporter->givePermissionTo(StaPermission::ExportGameFiles->value);
});

/** @return list<string> */
function sourceKeys(?User $user, array $keep = []): array
{
    return collect(ExportSources::options($user, $keep))->flatMap(fn (array $group) => array_keys($group))->all();
}

it('opens the page only with export_game_files and links it from the dashboard', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($sta);
    $this->get(GameResource::getUrl('export-files', ['record' => $this->game]))->assertForbidden();
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])->assertActionHidden('gameFiles');

    $sta->givePermissionTo(StaPermission::ExportGameFiles->value);
    $this->get(GameResource::getUrl('export-files', ['record' => $this->game]))->assertOk()->assertSee('Athlete layout');
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])->assertActionVisible('gameFiles');
});

it('offers medical and sensitive sources only with view_medical, or when already in the layout', function () {
    expect(sourceKeys($this->exporter))->toContain('person.passport_no', 'person.cpr_no', 'extra.shirt_name', 'entry.event', 'game.age', 'association.code', 'text')
        ->not->toContain('person.allergies', 'person.blood_group', 'extra.donor_type')
        ->and(sourceKeys($this->exporter, ['person.allergies']))->toContain('person.allergies');

    $this->exporter->givePermissionTo(StaPermission::ViewMedical->value);
    expect(sourceKeys($this->exporter->fresh()))->toContain('person.allergies', 'extra.donor_type');
});

it('validates layout columns against the source whitelist', function (array $column, ?string $error) {
    expect(ExportSources::validate([$column], $this->exporter))->toBe($error ? ["Column 1: {$error}"] : []);
})->with([
    'person field' => [['source' => 'person.passport_no', 'heading' => 'Passport'], null],
    'date format' => [['source' => 'person.dob', 'heading' => 'DOB', 'format' => 'd/m/Y'], null],
    'fixed text' => [['source' => 'text', 'heading' => 'NOC', 'value' => 'BRN'], null],
    'unknown source' => [['source' => 'people.passport_no_hash', 'heading' => 'X'], 'choose a source from the list.'],
    'format of another kind' => [['source' => 'person.dob', 'heading' => 'DOB', 'format' => 'upper'], 'choose a format from the list.'],
    'no heading' => [['source' => 'person.code', 'heading' => ''], 'give a heading of up to 100 characters.'],
    'fixed text missing' => [['source' => 'text', 'heading' => 'NOC'], 'enter the fixed text.'],
    'medical' => [['source' => 'person.allergies', 'heading' => 'Allergies'], 'only users with "View medical & health data" can add this column.'],
    'sensitive extra' => [['source' => 'extra.donor_type', 'heading' => 'Donor'], 'only users with "View medical & health data" can add this column.'],
]);

it('keeps a medical column a non-medical user did not add', function () {
    expect(ExportSources::validate([['source' => 'person.allergies', 'heading' => 'A']], $this->exporter, ['person.allergies']))->toBe([]);
});

it('formats values by kind', function (mixed $value, string $kind, ?string $format, mixed $out) {
    expect(ExportSources::format($value, $kind, $format))->toBe($out);
})->with([
    ['2005-03-10', 'date', 'd/m/Y', '10/03/2005'],
    ['2005-03-10', 'date', 'm/d/Y', '03/10/2005'],
    ['2005-03-10', 'date', 'Y-m-d', '2005-03-10'],
    ['2005-03-10', 'date', 'd-M-Y', '10-Mar-2005'],
    ['female', 'gender', null, 'Female'],
    ['female', 'gender', 'm_f', 'F'],
    ['male', 'gender', 'm_f', 'M'],
    ['ali hasan', 'text', 'upper', 'ALI HASAN'],
    ['ALI HASAN', 'text', 'title', 'Ali Hasan'],
    ['Ali', 'text', null, 'Ali'],
    [true, 'yes_no', null, 'Yes'],
    ['0', 'yes_no', 'y_n', 'N'],
    [false, 'yes_no', '1_0', '0'],
    ['72.5', 'number', null, 72.5],
    [null, 'text', 'upper', null],
]);

it('saves both layouts from the editor in column order', function () {
    actingInAdmin($this->exporter);
    $undo = Repeater::fake();
    livewire(GameExportFiles::class, ['record' => $this->game->getRouteKey()])
        ->fillForm(['athlete' => ['file_format' => 'csv', 'row_mode' => 'one_per_event', 'zip_pattern' => '{passport_no}', 'columns' => [
            ['source' => 'text', 'heading' => 'NOC', 'format' => 'upper', 'value' => 'brn'],
            ['source' => 'person.passport_no', 'heading' => 'Passport', 'format' => null],
        ]]])
        ->call('save')->assertHasNoFormErrors()->assertNotified('Layouts saved');
    $undo();

    $layout = ExportLayout::for($this->game, PersonType::Athlete);
    expect($layout->only(['file_format', 'row_mode', 'zip_pattern']))->toBe(['file_format' => 'csv', 'row_mode' => 'one_per_event', 'zip_pattern' => '{passport_no}'])
        ->and($layout->columns)->toBe([
            ['source' => 'text', 'heading' => 'NOC', 'format' => 'upper', 'value' => 'brn'],
            ['source' => 'person.passport_no', 'heading' => 'Passport'],
        ])
        ->and(ExportLayout::for($this->game, PersonType::Official)->exists)->toBeTrue();
});

it('copies layouts from another game, leaving out medical columns for a non-medical user', function () {
    $other = Game::factory()->create(['name' => 'Gulf Games']);
    ExportLayout::create(['game_id' => $other->id, 'type' => 'athlete', 'file_format' => 'csv', 'row_mode' => 'one_per_athlete',
        'zip_pattern' => '{code}', 'columns' => [['source' => 'person.code', 'heading' => 'ID'], ['source' => 'person.allergies', 'heading' => 'Allergies']]]);
    actingInAdmin($this->exporter);

    livewire(GameExportFiles::class, ['record' => $this->game->getRouteKey()])
        ->callAction('copy', ['game_id' => $other->id])->assertNotified('Layouts copied from Gulf Games');

    expect(ExportLayout::for($this->game, PersonType::Athlete))
        ->file_format->toBe('csv')->zip_pattern->toBe('{code}')
        ->columns->toBe([['source' => 'person.code', 'heading' => 'ID']]);
});

it('offers only Male/Female and M/F gender formats', function () {
    expect(ExportSources::FORMATS['gender'])->toBe(['male_female' => 'Male/Female', 'm_f' => 'M/F']);
});

it('offers layouts only for published games', function () {
    $draft = Game::factory()->draft()->create();
    $this->exporter->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($this->exporter);

    $this->get(GameResource::getUrl('export-files', ['record' => $draft]))->assertNotFound();
    livewire(GameDashboard::class, ['record' => $draft->getRouteKey()])->assertActionHidden('gameFiles');
    livewire(ListGames::class)->assertTableActionHidden('gameFiles', $draft)->assertTableActionVisible('gameFiles', $this->game);
});
