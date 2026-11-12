<?php

use App\Enums\StaPermission;
use App\Filament\Pages\ReportBuilder;
use App\Filament\Pages\Reports;
use App\Filament\Resources\Games\Pages\GameReports;
use App\Models\Association;
use App\Models\Game;
use App\Models\Person;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;
use App\Services\Reports\GameReports as Service;

use function Pest\Livewire\livewire;

beforeEach(fn () => actingInAdmin($this->admin = User::factory()->superAdmin()->create()));

function savedUiReport(User $owner, string $visibility, string $name, array $changes = []): SavedReport
{
    return SavedReport::create(['name' => $name, 'subject' => 'people', 'visibility' => $visibility, 'created_by' => $owner->id,
        'updated_by' => $owner->id, 'definition' => [...ReportDefinition::fresh(Registry::get('people')), ...$changes]]);
}

it('lays the reports page out as the canvas: header, ready-made band beside the preview, saved band with tabs', function () {
    Game::factory()->create(['name' => 'Asian Indoor Games']);

    livewire(Reports::class)
        ->assertSeeInOrder(['Reports', 'Ready-made reports for every game, plus reports you build and save yourself', 'Game', 'Asian Indoor Games', 'New report',
            'Ready-made reports', 'Phase 1 answers', 'Yes / No / Maybe per association and sport', 'Phase 2 totals', 'Long List', 'Short List', 'Over limit',
            'Expiring documents', 'Any', 'Clothing size counts', 'Clothing sizes by person',
            'Phase 1 answers', 'preview of the first 0 of 0 rows', 'Export to Excel', 'Export to Excel gives every row',
            'Saved reports', 'My reports (0)', 'Shared (0)', 'New report'])
        ->assertSeeHtml('aria-current="true"')->assertSeeHtml('sta-ph-1')->assertSeeHtml('role="tablist"')
        ->set('report', 'longList')->assertSee('Everyone entered in Phase 3')->assertSee('Only submitted entries are included');
});

it('defaults to the first game the user may report on and ignores any other', function () {
    $published = Game::factory()->create(['name' => 'Published Games']);
    $draft = Game::factory()->draft()->create(['name' => 'Draft Games']);
    $user = User::factory()->sta()->create();
    $user->givePermissionTo(StaPermission::ViewReports->value);
    actingInAdmin($user);

    livewire(Reports::class)->assertSet('game', $published->id)->assertDontSee('Draft Games')
        ->set('game', $draft->id)->assertSet('game', $published->id)
        ->set('game', 'abc')->assertSet('game', $published->id)
        ->call('export')->assertFileDownloaded('published-games-phase1-answers.xlsx');
    livewire(Reports::class, ['game' => $draft->id])->assertSet('game', $published->id);
    $this->get(Reports::getUrl().'?game=abc')->assertOk();
    $this->get(Reports::getUrl().'?game='.$draft->id)->assertOk()->assertDontSee('Draft Games');
});

it('refuses to export a saved report whose definition no longer validates, with a message', function () {
    $broken = savedUiReport($this->admin, 'private', 'Broken', ['columns' => ['cpr_no']]);

    livewire(Reports::class)->call('exportSaved', $broken->id)->assertNoFileDownloaded()
        ->assertNotified('This report cannot be exported');
});

it('ignores group and column moves outside the allowed range and refuses an unknown group field', function () {
    $page = livewire(ReportBuilder::class)->call('setGroup', 0, 'association')->call('setGroup', 1, 'gender');
    $groups = $page->get('def.groups');
    $columns = $page->get('def.columns');

    $page->call('setGroup', 5, 'full_name')->call('setGroup', -1, 'full_name')->call('setGroup', 2, 'full_name')
        ->assertSet('def.groups', $groups)
        ->call('moveColumnTo', 0, 99)->call('moveColumnTo', -1, 0)->assertSet('def.columns', $columns)
        ->call('setGroup', 0, 'nope')->assertSee('Choose a field to group by.')
        ->call('save')->assertHasErrors('groups.0.field');
    expect(SavedReport::count())->toBe(0);
});

it('adds up only the per-sport totals of Phase 2 and leaves out the "All associations" clothing rows', function () {
    $phase2 = ['headings' => [], 'rows' => [
        ['A', 'Judo', '-60kg', 'Male', 2, null, null, null, null, null],
        ['A', 'Judo', 'Total', null, 2, 2, 0, 1, null, null],
        ['B', 'Karate', 'Total', null, 3, 1, 2, null, null, null],
    ]];
    $sizes = ['headings' => [], 'rows' => [['A', 'T-Shirt', 'L', 2], ['B', 'T-Shirt', 'L', 1], ['All associations', 'T-Shirt', 'L', 3]]];

    expect(Service::summary('phase2Totals', $phase2))->toBe(['associations' => 2, 'sums' => [4 => 5, 5 => 3, 6 => 2, 7 => 1, 8 => 0, 9 => 0]])
        ->and(Service::summary('clothingSizeCounts', $sizes))->toBe(['associations' => 2, 'sums' => [3 => 3]])
        ->and(Service::summary('longList', ['headings' => [], 'rows' => [['A'], ['A'], ['B']]]))->toBe(['associations' => 2, 'sums' => []]);
});

it('renders a game\'s own reports page with the same band', function () {
    $game = Game::factory()->create();

    livewire(GameReports::class, ['record' => $game->getRouteKey()])
        ->assertSeeInOrder(['Ready-made reports', 'Phase 1 answers', 'Clothing sizes by person', 'Export to Excel'])
        ->assertSeeHtml('aria-current="true"')->assertSeeHtml('sta-ph-4');
});

it('shows saved reports as cards with subject, filters and grouping, and exports one', function () {
    Association::factory()->create(['name' => 'Bahrain Judo Federation']);
    $other = User::factory()->sta()->create(['name' => 'Other']);
    $other->givePermissionTo(StaPermission::ViewReports->value);
    $mine = savedUiReport($this->admin, 'private', 'Judo people', [
        'filters' => [['field' => 'full_name', 'op' => 'contains', 'value' => 'ali']],
        'groups' => [['field' => 'association', 'bucket' => null]],
    ]);
    savedUiReport($other, 'shared', 'Theirs shared');
    savedUiReport($other, 'private', 'Theirs private');

    livewire(Reports::class)
        ->assertSeeInOrder(['My reports (1)', 'Shared (1)', 'Judo people', 'Only me', 'Subject', 'People', 'Filters', 'Name contains ali',
            'Grouped', 'Association', 'Open', 'Export to Excel', 'New report', 'Theirs shared', 'Everyone with report access', 'by Other'])
        ->assertDontSee('Theirs private')
        ->call('exportSaved', $mine->id)->assertFileDownloaded('judo-people.xlsx');

    actingInAdmin($other);
    livewire(Reports::class)->call('exportSaved', $mine->id)->assertForbidden();
});

it('lays the builder out as the canvas: settings aside beside live results', function () {
    livewire(ReportBuilder::class)
        ->assertSeeHtml('sta-builder-settings')
        ->assertSeeInOrder(['Reports', 'Report builder', 'Share', 'Export to Excel', 'Save',
            'Subject', 'Columns', 'shown · drag to reorder', 'Filters', 'Match all rules', 'Game', '+ Add rule',
            'Group by (up to 2)', '1st', '2nd', 'Count of rows (subtotals and grand total)', 'Sort', 'A → Z',
            'Results', 'Updates as you change settings', 'Showing', 'export for all']);
});

it('groups from the 1st / 2nd selects with group rows, subtotals and a grand total, and reorders columns by drag', function () {
    $judo = Association::factory()->create(['name' => 'Bahrain Judo Federation']);
    Person::factory()->count(2)->create(['association_id' => $judo->id]);

    $page = livewire(ReportBuilder::class)
        ->set('def.columns', ['full_name', 'association'])
        ->call('setGroup', 0, 'association')
        ->assertSet('def.groups', [['field' => 'association', 'bucket' => null]]);
    expect($page->html())->toMatch('/grouped by Association.*background: var\(--sta-bg-selected, #E8EEF6\).*Bahrain Judo Federation <span[^>]*>· 2 rows.*Count · Bahrain Judo Federation.*border-top: 2px solid var\(--sta-bd-brand, #1F3A5F\).*Grand total · 1 association<\/th>.*?<td[^>]*font-size: 18px[^>]*>2<\/td>/su');

    $page->call('setGroup', 1, 'full_name')->assertCount('def.groups', 2)
        ->call('setGroup', 0, '')->assertSet('def.groups', [['field' => 'full_name', 'bucket' => null]])
        ->call('moveColumnTo', 1, 0)->assertSet('def.columns', ['association', 'full_name']);
});
