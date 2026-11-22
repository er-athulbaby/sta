<?php

use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\GameDashboard;
use App\Models\Association;
use App\Models\Game;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

// Game dashboard "Who has submitted": each phase cell is the status pill + one "⋯" Actions menu (+ a note when special).
beforeEach(function () {
    Notification::fake();
    $this->game = Game::factory()->create();
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Closed, 'deadline' => now()->subDay()]);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(3)]);
    $this->done = Association::factory()->create(['name' => 'Done Fed', 'code' => 'DON']);
    $this->late = Association::factory()->create(['name' => 'Late Fed', 'code' => 'LAT']);
    $this->game->associations()->attach([$this->done->id, $this->late->id]);
    $this->done2 = Submission::for($this->game, $this->done, 2);
    $this->done2->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'DON-G001-P2-R1']);
    actingInAdmin(User::factory()->superAdmin()->create());
});

function cellMenuDashboard(Game $game)
{
    return livewire(GameDashboard::class, ['record' => $game->getRouteKey()]);
}

it('renders the status pill and one Actions button in every phase cell, the pill linking to an existing entry', function () {
    $page = cellMenuDashboard($this->game);
    foreach (['Done Fed', 'Late Fed'] as $name) {
        foreach ([2, 3] as $p) {
            $page->assertSeeHtml('aria-label="Actions for '.$name.' Phase '.$p.'"');
        }
    }
    $page->assertSeeHtml('href="'.e(GameResource::getUrl('submission', ['record' => $this->game, 'submission' => $this->done2])).'" class="sta-cell-pill" aria-label="Submitted: view Done Fed Phase 2"')
        ->assertDontSeeHtml('aria-label="Not started: view Late Fed Phase 3"')
        // The old inline links are gone.
        ->assertDontSeeHtml('aria-label="Extend Late Fed Phase 3"')->assertDontSeeHtml('aria-label="Re-open Done Fed Phase 2"');
});

it('lists the menu items in order for a user who may do everything', function () {
    $page = cellMenuDashboard($this->game);
    expect(cellMenuLabels($page, $this->done, 2))->toBe(['View entry', 'Edit as STA', 'Send remark', 'Re-open'])
        ->and(cellMenuLabels($page, $this->late, 2))->toBe(['Edit as STA', 'Send remark', 'Re-open'])
        ->and(cellMenuLabels($page, $this->late, 3))->toBe(['Edit as STA', 'Send remark', 'Extend deadline']);

    $menu = $page->instance()->cellMenu($this->late, $this->game->phase(3));
    expect($menu[2])->toMatchArray(['click' => "mountAction('extend', { association: {$this->late->id}, phase: 3 })", 'divider' => true])
        ->and($menu[1]['click'])->toBe("mountAction('remark', { association: {$this->late->id}, phase: 3 })");
});

it('adds View PDF after View entry when the submitted entry has a PDF', function () {
    $this->done2->update(['pdf_paths' => ['entry_by_event' => 'submissions/DON-G001-P2-R1/entry-by-event.pdf']]);
    expect(cellMenuLabels(cellMenuDashboard($this->game), $this->done, 2))->toBe(['View entry', 'View PDF', 'Edit as STA', 'Send remark', 'Re-open']);
});

it('hides Edit as STA and Send remark without edit_submissions, and Re-open / Extend without manage_games', function () {
    $viewer = User::factory()->sta()->create();
    $viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($viewer);
    $page = cellMenuDashboard($this->game);
    expect(cellMenuLabels($page, $this->done, 2))->toBe(['View entry'])
        ->and(cellMenuLabels($page, $this->late, 3))->toBe([]);
    // A cell with nothing to do shows no Actions button.
    $page->assertSeeHtml('aria-label="Actions for Done Fed Phase 2"')->assertDontSeeHtml('aria-label="Actions for Late Fed Phase 3"')
        ->assertDontSee('Edit as STA')->assertDontSee('Send remark');

    $viewer->givePermissionTo(StaPermission::EditSubmissions->value);
    expect(cellMenuLabels(cellMenuDashboard($this->game), $this->late, 3))->toBe(['Edit as STA', 'Send remark']);
});

it('shows the special line only for re-opened or extended entries', function () {
    $page = cellMenuDashboard($this->game)->assertDontSee('Re-opened · until')->assertDontSee('Deadline extended to');
    expect($page->instance()->cellNote($this->late, $this->game->phase(3)))->toBeNull();

    $page->call('extend', $this->late->id, 3, now()->addDays(6)->timezone('Asia/Bahrain')->format('Y-m-d H:i'))
        ->call('reopen', $this->late->id, 2, now()->addDays(5)->timezone('Asia/Bahrain')->format('Y-m-d H:i'));

    cellMenuDashboard($this->game)
        ->assertSee('Deadline extended to '.now()->addDays(6)->timezone('Asia/Bahrain')->format('j M Y'))
        ->assertSee('Re-opened · until '.now()->addDays(5)->timezone('Asia/Bahrain')->format('j M Y'));
});

it('says a re-open or extension ended once its own deadline has passed', function () {
    $this->done2->update(['deadline_override' => now()->subHour(), 'deadline_change' => 'reopened']);
    cellMenuDashboard($this->game)->assertSee('Re-open ended')->assertDontSee('Re-opened · until');

    $this->done2->update(['deadline_override' => now()->addDay(), 'deadline_change' => 'reopened']);
    cellMenuDashboard($this->game)->assertSee('Re-opened · until')->assertDontSee('Re-open ended');
});
