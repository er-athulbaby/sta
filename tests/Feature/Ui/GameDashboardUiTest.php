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
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\PhaseReminder;
use App\Support\GameProgress;
use App\Support\Sta;
use Illuminate\Support\Facades\Notification;

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
        ->assertSeeInOrder(['Done Fed', 'Submitted', 'None', 'Open', 'Late Fed', 'No response', 'None', '—', 'Remind'])
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
