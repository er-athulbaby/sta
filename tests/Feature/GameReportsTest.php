<?php

use App\Enums\EventGender;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\GameDashboard;
use App\Filament\Resources\Games\Pages\GameReports as GameReportsPage;
use App\Filament\Resources\Games\Pages\ListGames;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\Reports\GameReports;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games', 'start_date' => '2026-12-01', 'end_date' => '2026-12-10']);
    $this->judoFed = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $karateFed = Association::factory()->create(['code' => 'BKF', 'name' => 'Bahrain Karate Federation']);
    $this->game->associations()->attach([$this->judoFed->id, $karateFed->id]);
    $shirt = ClothingItem::factory()->create(['name' => 'T-Shirt', 'sizes' => ['S', 'M', 'L']]);
    $this->game->clothingItems()->attach($shirt);

    $sport = fn (Association $a, string $name, array $limits = []) => GameSport::factory()->create(['game_id' => $this->game->id, ...$limits,
        'sport_id' => Sport::factory()->create(['association_id' => $a->id, 'name' => $name])->id]);
    $event = fn (GameSport $gs, string $name, EventGender $g) => GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $gs->id,
        'event_id' => Event::factory()->create(['sport_id' => $gs->sport_id, 'name' => $name, 'gender' => $g])->id]);
    $this->judo = $sport($this->judoFed, 'Judo', ['p4_max_men' => 1]);
    $this->m60 = $event($this->judo, 'Men -60kg', EventGender::Men);
    $event($this->judo, 'Women -52kg', EventGender::Women);
    $karate = $sport($karateFed, 'Karate');
    $kumite = $event($karate, 'Kumite', EventGender::Men);

    $this->ali = Person::factory()->create(['association_id' => $this->judoFed->id, 'first_name' => 'Ali', 'last_name' => 'Hasan',
        'passport_expiry' => '2026-12-05', 'allergies' => 'Peanut allergy']);
    $this->omar = Person::factory()->create(['association_id' => $this->judoFed->id, 'first_name' => 'Omar', 'last_name' => 'Saleh']);
    $this->coach = Person::factory()->official()->create(['association_id' => $this->judoFed->id, 'first_name' => 'Coach', 'last_name' => 'Karim',
        'cpr_expiry' => '2026-11-30']);
    $karim = Person::factory()->create(['association_id' => $karateFed->id, 'first_name' => 'Karim', 'last_name' => 'Ahmed']);
    $this->ali->syncSizes([$shirt->id => 'L']);
    $this->omar->syncSizes([$shirt->id => 'L']);
    $this->coach->syncSizes([$shirt->id => 'M']);

    $sub = fn (Association $a, int $phase) => Submission::for($this->game, $a, $phase);
    $athlete = fn (Submission $s, Person $p, GameEvent $ge) => $s->entries()->create(['person_id' => $p->id, 'game_event_id' => $ge->id, 'game_sport_id' => $ge->game_sport_id]);

    // Judo federation: every phase submitted.
    $p1 = $sub($this->judoFed, 1);
    $p1->answers()->create(['game_sport_id' => $this->judo->id, 'answer' => 'yes']);
    $p2 = $sub($this->judoFed, 2);
    $p2->numbers()->create(['game_event_id' => $this->m60->id, 'count' => 2]);
    $this->p3 = $sub($this->judoFed, 3);
    $athlete($this->p3, $this->ali, $this->m60);
    $athlete($this->p3, $this->omar, $this->m60);
    $this->p3->entries()->create(['person_id' => $this->coach->id, 'game_event_id' => null, 'game_sport_id' => $this->judo->id]);
    $this->p4 = $sub($this->judoFed, 4); // created while Phase 3 is a draft: nothing preloaded
    $athlete($this->p4, $this->ali, $this->m60);
    $athlete($this->p4, $this->omar, $this->m60);
    $this->p4->update(['justification' => 'Both can medal']);
    foreach ([$p1, $p2, $this->p3, $this->p4] as $s) {
        $s->update(['status' => SubmissionStatus::Submitted]);
    }

    // Karate federation: drafts only, so it must not appear in any report.
    $sub($karateFed, 1)->answers()->create(['game_sport_id' => $karate->id, 'answer' => 'yes']);
    $sub($karateFed, 2)->numbers()->create(['game_event_id' => $kumite->id, 'count' => 1]);
    $athlete($sub($karateFed, 3), $karim, $kumite);

    $this->reports = app(GameReports::class);
});

it('lists phase 1 answers', function () {
    expect($this->reports->phase1Answers($this->game))->toBe([
        'headings' => ['Association', 'Sport', 'Answer'],
        'rows' => [['Bahrain Judo Federation', 'Judo', 'Yes']],
    ]);
});

it('totals phase 2 numbers per event and per sport with participants and medals', function () {
    Submission::where(['game_id' => $this->game->id, 'association_id' => $this->judoFed->id, 'phase' => 2])->sole()
        ->sportTotals()->create(['game_sport_id' => $this->judo->id, 'male' => 2, 'female' => 0, 'gold' => 1]);

    expect($this->reports->phase2Totals($this->game))->toBe([
        'headings' => ['Association', 'Sport', 'Event', 'Gender', 'Count', 'Male participants', 'Female participants', 'Gold', 'Silver', 'Bronze'],
        'rows' => [
            ['Bahrain Judo Federation', 'Judo', 'Men -60kg', 'Male', 2, null, null, null, null, null],
            ['Bahrain Judo Federation', 'Judo', 'Women -52kg', 'Female', 0, null, null, null, null, null],
            ['Bahrain Judo Federation', 'Judo', 'Total', null, 2, 2, 0, 1, null, null],
        ],
    ]);
});

it('lists the Long List and the Short List one row per entry', function () {
    $long = $this->reports->longList($this->game);

    expect($long['headings'])->toBe(GameReports::PERSON_HEADINGS)
        ->and($long['rows'][0])->toBe(['Bahrain Judo Federation', $this->ali->code, 'Ali Hasan', 'Athlete', 'Judo', 'Men -60kg', 'Male',
            '2005-03-10', 21, 'Bahrain', '2026-12-05'])
        ->and(array_column($long['rows'], 2))->toBe(['Ali Hasan', 'Omar Saleh', 'Coach Karim'])
        ->and($long['rows'][2][3])->toBe('Team official')->and($long['rows'][2][5])->toBe('Official')
        ->and(array_column($this->reports->shortList($this->game)['rows'], 2))->toBe(['Ali Hasan', 'Omar Saleh']);
});

it('lists Short Lists over a limit with their justification', function () {
    expect($this->reports->overLimit($this->game))->toBe([
        'headings' => ['Association', 'Sport', 'Limit', 'Count', 'Max', 'Justification'],
        'rows' => [['Bahrain Judo Federation', 'Judo', 'male athletes', 2, 1, 'Both can medal']],
    ]);
});

it('lists documents expiring before the games end, from the Short List when Phase 4 is submitted', function () {
    expect($this->reports->expiringDocuments($this->game))->toBe([
        'headings' => ['Association', 'Code', 'Name', 'Document', 'Expiry'],
        'rows' => [['Bahrain Judo Federation', $this->ali->code, 'Ali Hasan', 'Passport', '2026-12-05']],
    ]);

    $this->p4->update(['status' => SubmissionStatus::Draft]); // falls back to the Long List, which has the coach
    expect($this->reports->expiringDocuments($this->game)['rows'])->toBe([
        ['Bahrain Judo Federation', $this->ali->code, 'Ali Hasan', 'Passport', '2026-12-05'],
        ['Bahrain Judo Federation', $this->coach->code, 'Coach Karim', 'CPR', '2026-11-30'],
    ]);
});

it('counts clothing sizes by association and overall, and lists them per person', function () {
    expect($this->reports->clothingSizeCounts($this->game))->toBe([
        'headings' => ['Association', 'Item', 'Size', 'Count'],
        'rows' => [['Bahrain Judo Federation', 'T-Shirt', 'L', 2], ['All associations', 'T-Shirt', 'L', 2]],
    ])->and($this->reports->clothingSizesByPerson($this->game))->toBe([
        'headings' => ['Association', 'Code', 'Name', 'Item', 'Size'],
        'rows' => [
            ['Bahrain Judo Federation', $this->ali->code, 'Ali Hasan', 'T-Shirt', 'L'],
            ['Bahrain Judo Federation', $this->omar->code, 'Omar Saleh', 'T-Shirt', 'L'],
        ],
    ]);

    $this->p4->update(['status' => SubmissionStatus::Draft]);
    expect($this->reports->clothingSizeCounts($this->game)['rows'])->toBe([
        ['Bahrain Judo Federation', 'T-Shirt', 'M', 1], ['Bahrain Judo Federation', 'T-Shirt', 'L', 2],
        ['All associations', 'T-Shirt', 'M', 1], ['All associations', 'T-Shirt', 'L', 2],
    ]);
});

it('includes only submitted submissions and never document numbers or medical data', function () {
    $all = json_encode(collect(array_keys(GameReports::REPORTS))->map(fn ($r) => $this->reports->{$r}($this->game)));

    expect($all)->not->toContain('Bahrain Karate Federation')->not->toContain('Karim Ahmed')
        ->not->toContain($this->ali->passport_no)->not->toContain($this->ali->cpr_no)->not->toContain('Peanut allergy');
});

it('runs a fixed number of queries whatever the number of people', function () {
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->reports->longList($this->game);
        $this->reports->clothingSizesByPerson($this->game);

        return count(DB::getQueryLog());
    };
    $count(); // warm-up: the game loads its phases once
    $before = $count();
    foreach (range(1, 3) as $i) {
        $this->p3->entries()->create(['person_id' => Person::factory()->create(['association_id' => $this->judoFed->id])->id,
            'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    }

    expect($count())->toBe($before);
});

it('leaves out sports later answered "no" in Phase 1, except in the Phase 1 answers', function () {
    $this->game->phases()->where('phase', 1)->update(['enabled' => true]);
    Submission::withoutGlobalScopes()->where(['game_id' => $this->game->id, 'association_id' => $this->judoFed->id, 'phase' => 1])
        ->first()->answers()->update(['answer' => 'no']);
    $this->game->unsetRelation('phases');

    expect($this->reports->phase1Answers($this->game)['rows'])->toBe([['Bahrain Judo Federation', 'Judo', 'No']])
        ->and($this->reports->phase2Totals($this->game)['rows'])->toBe([])
        ->and($this->reports->longList($this->game)['rows'])->toBe([])
        ->and($this->reports->overLimit($this->game)['rows'])->toBe([]);
});

it('opens the reports page only with view_reports', function () {
    $reporter = User::factory()->sta()->create();
    $reporter->givePermissionTo(StaPermission::ViewReports->value);
    actingInAdmin($reporter);
    $this->get(GameResource::getUrl('reports', ['record' => $this->game]))->assertOk()
        ->assertSee('Only submitted entries are included');

    $viewer = User::factory()->sta()->create();
    $viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($viewer);
    $this->get(GameResource::getUrl('reports', ['record' => $this->game]))->assertForbidden();
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])->assertActionHidden('reports');

    $viewer->givePermissionTo(StaPermission::ViewReports->value);
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])->assertActionVisible('reports');
});

it('previews the chosen report and exports it to Excel', function () {
    $reporter = User::factory()->sta()->create();
    $reporter->givePermissionTo(StaPermission::ViewReports->value);
    actingInAdmin($reporter);

    $page = livewire(GameReportsPage::class, ['record' => $this->game->getRouteKey()])
        ->assertSee('Judo')->assertSee('Yes')
        ->set('report', 'longList')->assertSee('Ali Hasan')->assertSee('Coach Karim')
        ->call('export')
        ->assertFileDownloaded('asian-indoor-games-long-list.xlsx',
            contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $path = sys_get_temp_dir().'/'.uniqid('report').'.xlsx';
    file_put_contents($path, base64_decode($page->effects['download']['content']));
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

    expect($rows[0])->toBe(GameReports::PERSON_HEADINGS)->and($rows[1][2])->toBe('Ali Hasan')->and(count($rows))->toBe(4);
});

it('refuses an unknown report', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(GameReportsPage::class, ['record' => $this->game->getRouteKey()])->set('report', 'longListX')->assertStatus(404);
});

it('gives a reports-only user nothing beyond the reports page', function () {
    $reporter = User::factory()->sta()->create();
    $reporter->givePermissionTo(StaPermission::ViewReports->value);
    actingInAdmin($reporter);

    $this->get(GameResource::getUrl('reports', ['record' => $this->game]))->assertOk();
    $this->get(GameResource::getUrl('edit', ['record' => $this->game]))->assertForbidden();
    $this->get(GameResource::getUrl('create'))->assertForbidden();
    $this->get(GameResource::getUrl('dashboard', ['record' => $this->game]))->assertForbidden();
    $this->get(GameResource::getUrl('submission', ['record' => $this->game, 'submission' => $this->p3]))->assertForbidden();
    $this->get(GameResource::getUrl('submission-edit-3', ['record' => $this->game, 'submission' => $this->p3]))->assertForbidden();

    livewire(ListGames::class)->assertSuccessful()
        ->assertTableActionHidden('edit', $this->game)
        ->assertTableActionHidden('dashboard', $this->game)
        ->assertTableActionVisible('reports', $this->game)
        ->assertActionHidden('create');
});

it('exports text starting with "=" as literal text, never a formula', function () {
    $this->ali->update(['first_name' => '=1+1']);
    actingInAdmin(User::factory()->superAdmin()->create());

    $page = livewire(GameReportsPage::class, ['record' => $this->game->getRouteKey()])->set('report', 'longList')->call('export');

    // The OpenSpout reader re-parses "=..." strings as formulas itself, so inspect the written XML.
    $path = sys_get_temp_dir().'/'.uniqid('report').'.xlsx';
    file_put_contents($path, base64_decode($page->effects['download']['content']));
    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $strings = $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();
    unlink($path);

    expect($sheet)->not->toMatch('/<f[ >]/')
        ->and($sheet.$strings)->toContain('=1+1');
});

it('previews the first rows with an "All N associations" total row of the per-sport totals', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    $html = livewire(GameReportsPage::class, ['record' => $this->game->getRouteKey()])->set('report', 'phase2Totals')->html();
    expect($html)->toContain('Athletes per association, sport and gender · preview of the first 3 of 3 rows')
        ->toMatch('/All 1 association<\/th>(\s|<!--.*?-->)*<td[^>]*>2<\/td>/s');
});
