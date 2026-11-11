<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\GameDashboard;
use App\Models\Association;
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
use App\Notifications\PhaseReminder;
use App\Services\Phases\LongListChecks;
use App\Services\Phases\ShortListChecks;
use App\Support\GameProgress;
use App\Support\Sta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    $this->game = Game::factory()->create(['reminder_days' => [7, 3, 0]]);
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(3)]);
    $this->done = Association::factory()->create(['name' => 'Done Fed', 'code' => 'DON']);
    $this->late = Association::factory()->create(['name' => 'Late Fed', 'code' => 'LAT']);
    $this->game->associations()->attach([$this->done->id, $this->late->id]);
    actingInAdmin(User::factory()->superAdmin()->create());
    Submission::for($this->game, $this->done, 2)->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'DON-G001-P2-R1']);
});

it('shows phase cards, KPI tiles and the who-has-submitted table', function () {
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->assertSee('Entry by Number')->assertSeeHtml('sta-phase-card is-active')
        ->assertSee('Submitted to STA')->assertSee('Awaiting association admin')->assertSee('In draft')->assertSee('Not started')->assertSee('Flags to review')
        ->assertSee('Who has submitted')->assertSee('All 2')->assertSee('Pending 1')->assertSee('Submitted 1')->assertSee('Last activity')
        ->assertSee('Remind pending (1)');
});

it('draws the canvas sections, labels and controls in order', function () {
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->assertSeeInOrder(['Games', $this->game->name, 'associations invited', 'Export Excel', 'Remind pending (1)', 'Edit game', 'Reports'])
        ->assertSeeInOrder(['Phase 1', 'Skipped', 'Entry by Sport', 'Not used for this game', 'Phase 2', 'Open', 'Entry by Number', 'Deadline', 'Long List', 'Short List'])
        ->assertSeeInOrder(['Submitted to STA', 'of 2 associations', 'Awaiting association admin', 'editor submitted, not approved',
            'In draft', 'started, not submitted', 'Not started', 'no entries yet', 'Flags to review', 'differences and warnings'])
        ->assertSeeInOrder(['Who has submitted', 'Phase 2 is open · reminders go out automatically 7, 3 and 0 days before the deadline',
            'All 2', 'Pending 1', 'Submitted 1', 'Search associations'])
        ->assertSeeInOrder(['Association', 'Phase 2', 'Flags', 'Last activity', 'Action'])
        ->assertSeeInOrder(['Done Fed', 'Submitted', 'None', 'Open', 'Late Fed', 'Not started', 'None', '—', 'Remind'])
        ->assertSee('Showing 2 of 2 associations')
        ->assertSeeHtml('aria-label="Phase 2 progress"')->assertSeeHtml('aria-label="Submission status by association"');
});

it('filters with the switch and the search box', function () {
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->set('show', 'submitted')->assertSee('Done Fed')->assertDontSee('Late Fed')->assertSee('Showing 1 of 1 submitted associations')
        ->set('show', 'pending')->assertSee('Showing 1 of 1 pending associations')
        ->set('show', 'all')->set('search', 'late')->assertSee('Late Fed')->assertDontSee('Done Fed');
});

it('lists an association under All only when no phase is open (ruling U4)', function () {
    $this->game->phase(2)->update(['status' => PhaseStatus::Closed]);
    $this->game->phase(3)->update(['enabled' => true]);
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->assertSee('All 2')->assertSee('Pending 0')->assertSee('Submitted 0')
        ->assertSee('Closed '.now('Asia/Bahrain')->format('j M').' · 1 of 2 submitted')->assertSee('Opens after Phase 2 closes')
        ->assertDontSee('Remind pending')
        ->set('show', 'submitted')->assertDontSee('Done Fed')->assertDontSee('Late Fed');
});

it('reminds one association from the row', function () {
    $admin = User::factory()->assocAdmin($this->late)->create();
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->callAction('remindOne', arguments: ['association' => $this->late->id])->assertNotified();
    Notification::assertSentTo($admin, PhaseReminder::class);
    Notification::assertSentTimes(PhaseReminder::class, 1);
});

it('hides Export Excel without view_reports and exports with it', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($sta);
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])->assertDontSee('Export Excel')
        ->call('exportExcel')->assertForbidden();

    $sta->givePermissionTo(StaPermission::ViewReports->value);
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])->assertSee('Export Excel')
        ->call('exportExcel')->assertFileDownloaded('game-'.$this->game->id.'-dashboard.xlsx');
});

it('records the deadline change and note from the dashboard extend and re-open', function () {
    $page = livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()]);
    $page->callAction('extend', ['deadline' => now()->addDays(10)->format('Y-m-d H:i'), 'note' => 'Travel delay'], ['association' => $this->late->id, 'phase' => 2]);
    $late = Submission::withoutGlobalScopes()->where('association_id', $this->late->id)->sole();
    expect($late->deadline_change)->toBe('extended')->and($late->deadline_note)->toBe('Travel delay');

    $page->callAction('reopen', ['deadline' => now()->addDays(12)->format('Y-m-d H:i')], ['association' => $this->done->id, 'phase' => 2]);
    $done = Submission::withoutGlobalScopes()->where('association_id', $this->done->id)->sole();
    expect($done->deadline_change)->toBe('reopened')->and($done->deadline_note)->toBeNull()->and($done->status)->toBe(SubmissionStatus::Draft);
});

it('links the focus phase submission from the Action column', function () {
    $s = Submission::withoutGlobalScopes()->where('association_id', $this->done->id)->sole();
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->assertSeeHtml(e(GameResource::getUrl('submission', ['record' => $this->game, 'submission' => $s])))
        ->assertSeeHtml('aria-label="Open Done Fed Phase 2"');
});

it('flags Long List events above the Phase 2 number', function () {
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $gs = GameSport::factory()->create(['game_id' => $this->game->id, 'sport_id' => Sport::factory()->create(['association_id' => $this->done->id])->id]);
    $ev = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $gs->id,
        'event_id' => Event::factory()->create(['sport_id' => $gs->sport_id, 'name' => '-60kg', 'gender' => EventGender::Men])->id]);
    NumberEntry::create(['submission_id' => Submission::for($this->game, $this->done, 2)->id, 'game_event_id' => $ev->id, 'count' => 1]);
    $p3 = Submission::for($this->game, $this->done, 3);
    foreach (Person::factory()->count(2)->create(['association_id' => $this->done->id]) as $p) {
        $p3->entries()->create(['person_id' => $p->id, 'game_event_id' => $ev->id, 'game_sport_id' => $gs->id]);
    }

    $progress = new GameProgress($this->game->fresh());

    expect($progress->flags($this->done, 3))->toBe('1 differs from Phase 2')
        ->and($progress->flags($this->late, 3))->toBeNull()
        ->and($progress->counts(2))->toMatchArray(['submitted' => 1, 'no_response' => 1, 'total' => 2])
        ->and($progress->lastActivity($this->done))->not->toBeNull();
});

it('counts an overdue association as started or not started so the tiles add up', function () {
    $this->game->phase(2)->update(['deadline' => now()->subDay()]);
    $counts = (new GameProgress($this->game->fresh()))->counts(2);
    expect($counts)->toMatchArray(['submitted' => 1, 'no_response' => 1, 'draft' => 0, 'overdue' => 1, 'total' => 2]);
});

it('formats last activity like the canvas', function () {
    $this->travelTo(now('Asia/Bahrain')->setTime(12, 0));
    expect(Sta::when(now()->subHour()))->toStartWith('Today, ')
        ->and(Sta::when(now()->subDay()))->toBe('Yesterday')
        ->and(Sta::when(now()->subDays(5)))->toBe(now()->subDays(5)->timezone('Asia/Bahrain')->format('j M'));
});

/** One association with a sport, an event, a submitted Phase 2 number and $entries Phase 3 (and Phase 4) athletes. */
function dashboardFixture(Game $game, string $code, int $p2, int $entries, ?int $p4Max = null): Association
{
    $a = Association::factory()->create(['name' => $code.' Fed', 'code' => $code]);
    $game->associations()->attach($a);
    $gs = GameSport::factory()->create(['game_id' => $game->id, 'p4_max_men' => $p4Max,
        'sport_id' => Sport::factory()->create(['association_id' => $a->id, 'name' => $code.' sport'])->id]);
    $ev = GameEvent::factory()->create(['game_id' => $game->id, 'game_sport_id' => $gs->id,
        'event_id' => Event::factory()->create(['sport_id' => $gs->sport_id, 'name' => '-60kg', 'gender' => EventGender::Men])->id]);
    $s2 = Submission::for($game, $a, 2);
    $s2->update(['status' => SubmissionStatus::Submitted]);
    NumberEntry::create(['submission_id' => $s2->id, 'game_event_id' => $ev->id, 'count' => $p2]);
    $people = Person::factory()->count($entries)->create(['association_id' => $a->id]);
    foreach ([3, 4] as $phase) {
        $s = Submission::for($game, $a, $phase);
        foreach ($people as $p) {
            $s->entries()->create(['person_id' => $p->id, 'game_event_id' => $ev->id, 'game_sport_id' => $gs->id]);
        }
    }

    return $a;
}

it('computes the same flags as the per-association Long List and Short List checks', function () {
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Closed]);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->game->phase(4)->update(['enabled' => true]);
    $over = dashboardFixture($this->game, 'OVR', 1, 3, 1);
    $within = dashboardFixture($this->game, 'WIT', 3, 2, 5);
    $hidden = dashboardFixture($this->game, 'HID', 1, 3, 1);
    InterestAnswer::create(['submission_id' => Submission::for($this->game, $hidden, 1)->id,
        'game_sport_id' => GameSport::where('game_id', $this->game->id)->whereHas('sport', fn ($q) => $q->where('association_id', $hidden->id))->value('id'), 'answer' => 'no']);

    $old = function (Association $a, int $phase) {
        $s = Submission::withoutGlobalScopes()->where(['game_id' => $this->game->id, 'association_id' => $a->id, 'phase' => $phase])->first();
        if ($phase === 3) {
            $n = LongListChecks::phase2Differences(app(LongListChecks::class)->selection($s)[0], app(LongListChecks::class)->phase2Numbers($s) ?? []);

            return $n ? $n.' '.($n === 1 ? 'differs' : 'differ').' from Phase 2' : null;
        }
        $n = count(app(ShortListChecks::class)->overLimits($s));

        return $n ? $n.' over a maximum' : null;
    };
    $progress = new GameProgress($this->game->fresh());
    foreach ([$over, $within, $hidden, $this->done, $this->late] as $a) {
        foreach ([3, 4] as $phase) {
            $expected = in_array($a->id, [$this->done->id, $this->late->id], true) ? null : $old($a, $phase);
            expect($progress->flags($a, $phase))->toBe($expected, "{$a->code} phase {$phase}");
        }
    }
    expect($progress->flags($over, 3))->toBe('1 differs from Phase 2')->and($progress->flags($over, 4))->toBe('1 over a maximum')
        ->and($progress->flags($within, 3))->toBeNull()->and($progress->flags($hidden, 3))->toBeNull();
});

it('renders with the same number of queries for 2 and 10 associations (ruling U3)', function () {
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });
    $run = 0;
    $count = function (int $n) use (&$queries, &$run) {
        $run++;
        $game = Game::factory()->create();
        $game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Closed]);
        $game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
        foreach (range(1, $n) as $i) {
            dashboardFixture($game, 'R'.$run.'X'.$i, 1, 2);
        }
        $queries = 0;
        livewire(GameDashboard::class, ['record' => $game->getRouteKey()])->assertSee('1 differs from Phase 2');

        return $queries;
    };
    $count(2); // warm-up: permission and settings caches
    $two = $count(2);
    expect($count(10))->toBe($two);
});

it('exports only the rows on screen, names as literal text', function () {
    $evil = Association::factory()->create(['name' => '=HYPERLINK("http://evil.test","x")', 'code' => 'EVL']);
    $this->game->associations()->attach($evil);
    $t = livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])->set('show', 'pending')->call('exportExcel');
    $file = tempnam(sys_get_temp_dir(), 'gdx').'.xlsx';
    file_put_contents($file, base64_decode(data_get($t->effects, 'download.content')));
    $reader = new XlsxReader;
    $reader->open($file);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->getCells();
        }
    }
    $reader->close();
    // OpenSpout's reader turns any text starting with "=" back into a FormulaCell, so check the sheet XML itself:
    // the name must be an inline string, and the sheet must have no <f> formula node.
    $zip = new ZipArchive;
    $zip->open($file);
    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($file);

    $names = array_map(fn ($cells) => $cells[0]->getValue(), array_slice($rows, 1));
    expect($names)->toEqualCanonicalizing(['Late Fed', '=HYPERLINK("http://evil.test","x")'])
        ->and($xml)->not->toContain('<f>')->toMatch('/t="inlineStr"><is><t>=HYPERLINK\(/');
});

it('hides and refuses the row Remind on an unpublished game or without send_reminders', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($sta);
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->assertActionHidden('remindOne')->assertDontSeeHtml('aria-label="Remind Late Fed"')
        ->call('mountAction', 'remindOne', ['association' => $this->late->id])->call('callMountedAction');

    actingInAdmin(User::factory()->superAdmin()->create());
    $this->game->update(['published_at' => null]);
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->assertActionHidden('remindOne')->assertDontSeeHtml('aria-label="Remind Late Fed"')
        ->call('mountAction', 'remindOne', ['association' => $this->late->id])->call('callMountedAction');
    Notification::assertNothingSent();
});

it('counts Remind pending for the default (first open) phase only', function () {
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->assertSee('Pending 2')->assertSee('Remind pending (1)');
});
