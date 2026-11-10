<?php

use App\Enums\StaPermission;
use App\Filament\Pages\ReportBuilder;
use App\Filament\Pages\Reports;
use App\Models\Association;
use App\Models\Game;
use App\Models\Person;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use OpenSpout\Reader\XLSX\Reader;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-10 10:00'));
    $judo = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $karate = Association::factory()->create(['code' => 'BKF', 'name' => 'Bahrain Karate Federation']);
    Person::factory()->create(['association_id' => $judo->id, 'first_name' => 'Ali', 'last_name' => 'Hasan', 'dob' => '2005-12-05']);
    Person::factory()->create(['association_id' => $judo->id, 'first_name' => 'Omar', 'last_name' => 'Saleh', 'dob' => '2004-01-01']);
    Person::factory()->create(['association_id' => $karate->id, 'first_name' => 'Karim', 'last_name' => 'Ahmed', 'dob' => '2005-03-10']);

    $this->owner = User::factory()->sta()->create(['name' => 'Owner']);
    $this->owner->givePermissionTo(StaPermission::ViewReports->value);
    $this->other = User::factory()->sta()->create(['name' => 'Other']);
    $this->other->givePermissionTo(StaPermission::ViewReports->value);
});

function storedReport(User $owner, string $visibility, string $name, array $changes = []): SavedReport
{
    return SavedReport::create(['name' => $name, 'subject' => 'people', 'visibility' => $visibility, 'created_by' => $owner->id, 'updated_by' => $owner->id,
        'definition' => [...ReportDefinition::fresh(Registry::get('people')), ...$changes]]);
}

it('opens the pages only with view_reports', function () {
    $viewer = User::factory()->sta()->create();
    $viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($viewer);
    $this->get(Reports::getUrl())->assertForbidden();
    $this->get(ReportBuilder::getUrl())->assertForbidden();

    actingInAdmin($this->owner);
    $this->get(Reports::getUrl())->assertOk()->assertSee('New report');
    $this->get(ReportBuilder::getUrl())->assertOk();
});

it('lists my reports and the ones shared with me', function () {
    storedReport($this->owner, 'private', 'Mine private');
    storedReport($this->other, 'shared', 'Theirs shared');
    storedReport($this->other, 'private', 'Theirs private');
    actingInAdmin($this->owner);

    $this->get(Reports::getUrl())->assertOk()
        ->assertSeeInOrder(['My reports', 'Mine private', 'Shared', 'Theirs shared', 'by Other'])
        ->assertDontSee('Theirs private');
});

it('updates results live and saves a new report', function () {
    actingInAdmin($this->owner);

    livewire(ReportBuilder::class)
        ->assertSee('Ali Hasan')->assertSee('Karim Ahmed')->assertSee('Total (3)')
        ->call('addFilter')
        ->set('def.filters.0.op', 'contains')
        ->set('def.filters.0.value', 'ali')
        ->assertSee('Ali Hasan')->assertDontSee('Karim Ahmed')->assertSee('Total (1)')
        ->set('name', 'Judo people')
        ->set('visibility', 'shared')
        ->call('save')
        ->assertRedirect(ReportBuilder::getUrl(['report' => SavedReport::sole()]));

    expect(SavedReport::sole())->name->toBe('Judo people')->visibility->toBe('shared')->created_by->toBe($this->owner->id)
        ->and(SavedReport::sole()->definition['filters'])->toBe([['field' => 'full_name', 'op' => 'contains', 'value' => 'ali']]);
});

it('resets the definition when the subject changes and asks for a game', function () {
    actingInAdmin($this->owner);
    $game = Game::factory()->create(['name' => 'Asian Indoor Games']);

    livewire(ReportBuilder::class)
        ->set('def.subject', 'entries')
        ->assertSet('def.columns', Registry::get('entries')->defaultColumns())
        ->assertSee('Choose a game.')
        ->set('def.game_id', (string) $game->id)
        ->assertDontSee('Choose a game.')->assertSee('Total (0)');
});

it('refuses invalid values and too long names', function () {
    actingInAdmin($this->owner);

    livewire(ReportBuilder::class)
        ->set('def.columns', ['full_name', 'passport_no'])
        ->assertSee('Choose one or more of the listed columns.')
        ->call('save')->assertHasErrors('columns')
        ->set('def.columns', ['full_name'])
        ->set('name', str_repeat('x', 121))
        ->call('save')->assertHasErrors('name');

    expect(SavedReport::count())->toBe(0);
});

it('lets others run a shared report with their own filters but only save a copy', function () {
    $report = storedReport($this->owner, 'shared', 'All people');
    actingInAdmin($this->other);

    livewire(ReportBuilder::class, ['report' => $report->id])
        ->assertSee('Karim Ahmed')->assertDontSee('wire:click="save"', escape: false)
        ->set('def.filters', [['field' => 'association', 'op' => 'is', 'value' => 'Bahrain Judo Federation']])
        ->assertDontSee('Karim Ahmed')
        ->call('save')->assertForbidden();

    livewire(ReportBuilder::class, ['report' => $report->id])
        ->set('def.filters', [['field' => 'association', 'op' => 'is', 'value' => 'Bahrain Judo Federation']])
        ->call('saveAsCopy');

    $copy = SavedReport::where('created_by', $this->other->id)->sole();
    expect($copy)->name->toBe('All people (copy)')->visibility->toBe('private')
        ->and($report->fresh()->definition['filters'])->toBe([])
        ->and($report->fresh()->last_run_at)->not->toBeNull();
});

it('hides a private report from others', function () {
    $report = storedReport($this->owner, 'private', 'Mine');
    actingInAdmin($this->other);

    $this->get(ReportBuilder::getUrl(['report' => $report]))->assertForbidden();
});

it('lets the owner and a super admin delete', function () {
    $report = storedReport($this->other, 'shared', 'Theirs');
    actingInAdmin($this->owner);
    livewire(ReportBuilder::class, ['report' => $report->id])->call('delete')->assertForbidden();

    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(ReportBuilder::class, ['report' => $report->id])->call('delete')->assertRedirect(Reports::getUrl());
    $this->assertModelMissing($report);
});

it('shows an invalid stored definition as an error instead of running it', function () {
    $report = storedReport($this->owner, 'private', 'Broken', ['columns' => ['cpr_no']]);
    actingInAdmin($this->owner);

    livewire(ReportBuilder::class, ['report' => $report->id])
        ->assertOk()->assertSee('Choose one or more of the listed columns.')->assertDontSee('Ali Hasan');
});

it('keeps a super admin\'s view of others\' private reports out of Shared', function () {
    storedReport($this->other, 'private', 'Theirs private');
    actingInAdmin(User::factory()->superAdmin()->create());

    $this->get(Reports::getUrl())->assertOk()->assertDontSee('Theirs private');
});

it('does not let a non-owner save, share or delete', function () {
    $report = storedReport($this->owner, 'shared', 'All people');
    actingInAdmin($this->other);

    livewire(ReportBuilder::class, ['report' => $report->id])
        ->set('name', 'Renamed')->call('save')->assertForbidden();
    livewire(ReportBuilder::class, ['report' => $report->id])
        ->set('visibility', 'private')->call('save')->assertForbidden();
    livewire(ReportBuilder::class, ['report' => $report->id])->call('delete')->assertForbidden();

    expect($report->fresh())->name->toBe('All people')->visibility->toBe('shared');
});

it('refuses a crafted id for someone else\'s private report and keeps the id locked', function () {
    $private = storedReport($this->owner, 'private', 'Mine');
    $shared = storedReport($this->other, 'shared', 'Theirs');
    actingInAdmin($this->other);

    livewire(ReportBuilder::class, ['report' => $private->id])->assertForbidden();
    expect(fn () => livewire(ReportBuilder::class, ['report' => $shared->id])->set('reportId', $private->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('stops running a report that is no longer visible', function () {
    $report = storedReport($this->owner, 'shared', 'All people');
    actingInAdmin($this->other);
    $page = livewire(ReportBuilder::class, ['report' => $report->id])->assertSee('Ali Hasan');

    $report->update(['visibility' => 'private']);
    $page->call('addFilter')->assertForbidden();
});

it('refuses an unknown visibility on save', function () {
    actingInAdmin($this->owner);

    livewire(ReportBuilder::class)->set('visibility', 'public')->call('save')->assertHasErrors('visibility');
    expect(SavedReport::count())->toBe(0);
});

it('offers only published games unless super admin', function () {
    Game::factory()->create(['name' => 'Published Games']);
    Game::factory()->draft()->create(['name' => 'Draft Games']);
    actingInAdmin($this->owner);
    livewire(ReportBuilder::class)->assertSee('Published Games')->assertDontSee('Draft Games');

    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(ReportBuilder::class)->assertSee('Draft Games');
});

function builderXlsx($page): string
{
    $path = sys_get_temp_dir().'/'.uniqid('builder').'.xlsx';
    file_put_contents($path, base64_decode($page->effects['download']['content']));

    return $path;
}

it('exports all rows to Excel with group subtotal rows and a grand total', function () {
    actingInAdmin($this->owner);

    $page = livewire(ReportBuilder::class)
        ->set('name', 'People by association')
        ->set('def.columns', ['association', 'full_name', 'age'])
        ->set('def.groups', [['field' => 'association', 'bucket' => null]])
        ->set('def.totals', ['age' => 'sum'])
        ->call('export')
        ->assertFileDownloaded('people-by-association.xlsx',
            contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $path = builderXlsx($page);
    $reader = new Reader;
    $reader->open($path);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    unlink($path);

    expect($rows)->toHaveCount(7)
        ->and($rows[0])->toBe(['Association', 'Name', 'Age (today)'])
        ->and([$rows[1][0], $rows[1][2]])->toEqual(['Bahrain Judo Federation (2)', 42])
        ->and($rows[2])->toEqual(['Bahrain Judo Federation', 'Ali Hasan', 20])
        ->and($rows[3])->toEqual(['Bahrain Judo Federation', 'Omar Saleh', 22])
        ->and([$rows[4][0], $rows[4][2]])->toEqual(['Bahrain Karate Federation (1)', 21])
        ->and($rows[5])->toEqual(['Bahrain Karate Federation', 'Karim Ahmed', 21])
        ->and([$rows[6][0], $rows[6][2]])->toEqual(['Total (3)', 63]);
});

it('refuses to export an invalid definition', function () {
    actingInAdmin($this->owner);

    livewire(ReportBuilder::class)->set('def.columns', [])->call('export')->assertHasErrors('columns')->assertNoFileDownloaded();
});

it('exports text starting with "=" from the builder as literal text, never a formula', function () {
    Person::where('first_name', 'Ali')->first()->update(['first_name' => '=1+1']);
    actingInAdmin($this->owner);

    $page = livewire(ReportBuilder::class)->set('def.columns', ['full_name'])->call('export');

    // The OpenSpout reader re-parses "=..." strings as formulas itself, so inspect the written XML.
    $path = builderXlsx($page);
    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $strings = $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();
    unlink($path);

    expect($sheet)->not->toMatch('/<f[ >]/')
        ->and($sheet.$strings)->toContain('=1+1');
});

it('refuses to export a report that is no longer visible, or a draft game for non super admins', function () {
    $report = storedReport($this->owner, 'shared', 'All people');
    actingInAdmin($this->other);
    $page = livewire(ReportBuilder::class, ['report' => $report->id]);
    $report->update(['visibility' => 'private']);
    $page->call('export')->assertForbidden();

    $draft = Game::factory()->draft()->create();
    livewire(ReportBuilder::class)->set('def.game_id', $draft->id)->call('export')->assertHasErrors('game_id')->assertNoFileDownloaded();
});

it('records the run time of a saved report on export', function () {
    $report = storedReport($this->owner, 'private', 'All people');
    actingInAdmin($this->owner);
    $page = livewire(ReportBuilder::class, ['report' => $report->id]);
    $this->travelTo(Carbon::parse('2026-11-11 09:00'));

    $page->call('export')->assertFileDownloaded('all-people.xlsx');
    expect($report->fresh()->last_run_at->toDateTimeString())->toBe('2026-11-11 09:00:00');
});
