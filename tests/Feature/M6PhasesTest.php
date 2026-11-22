<?php

use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Portal\Pages\Games;
use App\Filament\Portal\Pages\Phase2Entry;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\GameDashboard;
use App\Filament\Resources\Games\Pages\SetupPhases;
use App\Models\Association;
use App\Models\Game;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\DeadlineChanged;
use App\Notifications\PhaseReminder;
use App\Services\Phases\PhaseGate;
use App\Services\Phases\SubmissionWorkflow;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

// M6 Task C: several phases open at once, per-association re-open of a closed phase, "Edit as STA" from every cell.
beforeEach(function () {
    Notification::fake();
    $this->game = Game::factory()->create(['reminder_days' => [3]]);
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Closed, 'deadline' => now()->subDay()]);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(3)]);
    $this->game->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(3)]);
    $this->done = Association::factory()->create(['name' => 'Done Fed', 'code' => 'DON']);
    $this->late = Association::factory()->create(['name' => 'Late Fed', 'code' => 'LAT']);
    $this->game->associations()->attach([$this->done->id, $this->late->id]);
    Submission::for($this->game, $this->done, 2)->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'DON-G001-P2-R1']);
    Submission::for($this->game, $this->done, 4)->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'DON-G001-P4-R1']);
    $this->lateAdmin = User::factory()->assocAdmin($this->late)->create();
    $this->doneAdmin = User::factory()->assocAdmin($this->done)->create();
    $this->sta = actingInAdmin(User::factory()->superAdmin()->create());
});

function m6Dashboard(Game $game)
{
    return livewire(GameDashboard::class, ['record' => $game->getRouteKey()]);
}

it('follows the latest open phase by default and lets STA pick another phase', function () {
    $page = m6Dashboard($this->game);
    expect($page->instance()->focusPhase()->phase)->toBe(4);
    $page->assertSeeHtml('aria-label="Phase shown in the KPIs, flags and Action column"')->assertSee('Phase 4 is open');

    $page->set('focus', 3);
    expect($page->instance()->focusPhase()->phase)->toBe(3);
    $page->assertSee('Phase 3 is open')->set('focus', 2)->assertSee('Phase 2 is closed');
});

it('reminds pending associations for the chosen open phase only', function () {
    // Default phase is the latest open one (4): Done Fed submitted it, so only Late Fed is reminded.
    m6Dashboard($this->game)->assertActionHasLabel('remindPending', 'Remind pending (1)')->callAction('remindPending', ['phase' => 3]);
    Notification::assertSentTimes(PhaseReminder::class, 2);

    m6Dashboard($this->game)->callAction('remindOne', arguments: ['association' => $this->late->id, 'phase' => 3]);
    Notification::assertSentTimes(PhaseReminder::class, 3);
    Notification::assertSentTo($this->lateAdmin, PhaseReminder::class, fn ($n) => $n->phase->phase === 3);
});

it('shows every open phase on the admin home and an attention item for each', function () {
    $home = livewire(Dashboard::class);
    $home->assertSee('Phase 4 · Short List')->assertSee('Phase 3 · Long List')
        ->assertSee('haven’t submitted the Long List')->assertSee('hasn’t submitted the Short List');
    $home->callAction('remind', arguments: ['game' => $this->game->id, 'phase' => 3]);
    Notification::assertSentTimes(PhaseReminder::class, 2);
});

it('re-opens a closed phase for one association that never started it, while other phases are open', function () {
    $deadline = now()->addDays(5)->format('Y-m-d H:i');
    $page = m6Dashboard($this->game)->assertSeeHtml('aria-label="Actions for Late Fed Phase 2"');
    expect(cellMenuLabels($page, $this->late, 2))->toContain('Re-open')->not->toContain('Extend deadline');
    $page->call('reopen', $this->late->id, 2, $deadline, 'Late registration');

    $s = Submission::withoutGlobalScopes()->where(['association_id' => $this->late->id, 'phase' => 2])->sole();
    expect($s->status)->toBe(SubmissionStatus::Draft)->and($s->deadline_change)->toBe('reopened')
        ->and($s->deadline_override->timezone('Asia/Bahrain')->format('Y-m-d H:i'))->toBe($deadline)
        ->and($this->game->phase(2)->fresh()->status)->toBe(PhaseStatus::Closed)
        ->and(app(PhaseGate::class)->canEdit($s))->toBeTrue()
        ->and(app(PhaseGate::class)->denialReason(Submission::for($this->game, $this->done, 2)))->toBe('This phase is closed.');
    Notification::assertSentTo($this->lateAdmin, DeadlineChanged::class);

    $page = m6Dashboard($this->game)->assertSee('Re-opened · until '.now()->addDays(5)->format('j M Y H:i'));
    expect(cellMenuLabels($page, $this->late, 2))->toContain('Extend deadline')->not->toContain('Re-open');

    // The association sees the phase open (only for them) and can edit it.
    actingInPortal($this->lateAdmin);
    $row = fn (Association $a) => Games::forTenant($a)->first()['rows']->firstWhere('phase', 2);
    expect($row($this->late)['status'])->toBe('Open')->and($row($this->late)['meta'])->toStartWith('Deadline ')
        ->and($row($this->done)['status'])->toBe('Closed');
    livewire(Phase2Entry::class, ['game' => $this->game->id])->assertSet('readOnlyReason', null);

    actingInPortal($this->doneAdmin);
    livewire(Phase2Entry::class, ['game' => $this->game->id])->assertSet('readOnlyReason', 'This phase is closed.');
});

it('closes the re-opened phase again for that association at its own deadline', function () {
    m6Dashboard($this->game)->call('reopen', $this->late->id, 2, now()->addDays(2)->format('Y-m-d H:i'));
    $s = Submission::withoutGlobalScopes()->where(['association_id' => $this->late->id, 'phase' => 2])->sole();

    // An extension keeps it re-opened.
    m6Dashboard($this->game)->call('extend', $this->late->id, 2, now()->addDays(4)->format('Y-m-d H:i'));
    expect($s->fresh()->deadline_change)->toBe('reopened')->and(app(PhaseGate::class)->canEdit($s->fresh()))->toBeTrue();

    $this->travel(5)->days();
    expect(app(PhaseGate::class)->denialReason($s->fresh()))->toBe('This phase is closed.');
});

it('re-opens a submitted entry of a closed phase while other phases are open', function () {
    $page = m6Dashboard($this->game);
    expect(cellMenuLabels($page, $this->done, 2))->toContain('Re-open');
    $page->call('reopen', $this->done->id, 2, now()->addDays(5)->format('Y-m-d H:i'));
    $s = Submission::withoutGlobalScopes()->where(['association_id' => $this->done->id, 'phase' => 2])->sole();
    expect($s->status)->toBe(SubmissionStatus::Draft)->and(app(PhaseGate::class)->canEdit($s))->toBeTrue();
});

it('still refuses to re-open a draft in an open phase (extend it instead)', function () {
    m6Dashboard($this->game)->call('reopen', $this->late->id, 3, now()->addDays(5)->format('Y-m-d H:i'))
        ->assertNotified('This entry is not submitted, so there is nothing to re-open.');
    expect(Submission::withoutGlobalScopes()->where(['association_id' => $this->late->id, 'phase' => 3])->exists())->toBeFalse();
});

it('sends reminders for a closed phase only to the association it was re-opened for', function () {
    m6Dashboard($this->game)->call('reopen', $this->late->id, 2, now()->addDays(3)->setTime(12, 0)->format('Y-m-d H:i'));
    Notification::fake();

    $this->artisan('phases:send-reminders')->assertSuccessful();

    // Phases 3 and 4 (open, deadline in 3 days): Late Fed both, Done Fed phase 3; phase 2 (closed): only Late Fed.
    Notification::assertSentTo($this->lateAdmin, PhaseReminder::class, fn ($n) => $n->phase->phase === 2);
    Notification::assertNotSentTo($this->doneAdmin, PhaseReminder::class, fn ($n) => $n->phase->phase === 2);
    Notification::assertSentTimes(PhaseReminder::class, 4);
});

it('shows Edit as STA in every phase cell, linking existing submissions and creating missing ones', function () {
    $done2 = Submission::for($this->game, $this->done, 2);
    $page = m6Dashboard($this->game);
    foreach ([2, 3, 4] as $p) {
        expect(cellMenuLabels($page, $this->late, $p))->toContain('Edit as STA')->and(cellMenuLabels($page, $this->done, $p))->toContain('Edit as STA');
    }
    $page->assertSeeHtml('wire:click="editAsSta('.$this->late->id.', 3)"');
    $page->assertSeeHtml(e(GameResource::getUrl('submission-edit-2', ['record' => $this->game, 'submission' => $done2])));

    foreach ([2, 3, 4] as $p) {
        $page = m6Dashboard($this->game)->call('editAsSta', $this->late->id, $p);
        $s = Submission::withoutGlobalScopes()->where(['association_id' => $this->late->id, 'phase' => $p])->sole();
        $url = GameResource::getUrl('submission-edit-'.$p, ['record' => $this->game, 'submission' => $s]);
        $page->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Edit Late Fed');
        expect($s->status)->toBe(SubmissionStatus::Draft);
    }
});

it('works for Phase 1 too and hides Edit as STA without edit_submissions', function () {
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Closed, 'deadline' => now()->subDays(9)]);
    m6Dashboard($this->game)->assertSeeHtml('wire:click="editAsSta('.$this->late->id.', 1)"')->call('editAsSta', $this->late->id, 1)
        ->assertRedirect(GameResource::getUrl('submission-edit-1', ['record' => $this->game, 'submission' => Submission::for($this->game, $this->late, 1)]));

    $viewer = User::factory()->sta()->create();
    $viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($viewer);
    m6Dashboard($this->game)->assertDontSee('Edit as STA')->call('editAsSta', $this->late->id, 3)->assertForbidden();
    expect(Submission::withoutGlobalScopes()->where(['association_id' => $this->late->id, 'phase' => 3])->exists())->toBeFalse();
});

it('refuses Edit as STA for a phase that is off or not opened, and for an uninvited association', function () {
    $other = Association::factory()->create();
    m6Dashboard($this->game)->call('editAsSta', $this->late->id, 1)->assertNotFound();
    m6Dashboard($this->game)->call('editAsSta', $other->id, 3)->assertNotFound();
});

// Fix round 1.
it('keeps a re-opened entry editable when STA closes the phase, and says so', function () {
    m6Dashboard($this->game)->call('reopen', $this->done->id, 4, now()->addDays(6)->format('Y-m-d H:i'));
    $s = Submission::withoutGlobalScopes()->where(['association_id' => $this->done->id, 'phase' => 4])->sole();

    $page = livewire(SetupPhases::class, ['record' => $this->game->getRouteKey()])->assertSee(SetupPhases::CLOSE_HINT);
    expect($page->instance()->reopenedAssociations(4)->all())->toBe(['Done Fed']);
    $page->mountAction('closePhase', ['phase' => 4])->assertMountedActionModalSee('Still open for 1 re-opened: Done Fed.')->callMountedAction();

    expect($this->game->phase(4)->fresh()->status)->toBe(PhaseStatus::Closed)
        ->and(app(PhaseGate::class)->canEdit($s->fresh()))->toBeTrue()
        ->and(app(PhaseGate::class)->denialReason(Submission::for($this->game, $this->late, 4)))->toBe('This phase is closed.');
});

it('refuses to extend an entry in a closed phase that was not re-opened, leaving no row', function () {
    m6Dashboard($this->game)->call('extend', $this->late->id, 2, now()->addDays(5)->format('Y-m-d H:i'))
        ->assertNotified('This phase is closed: re-open it for this association instead.');
    expect(Submission::withoutGlobalScopes()->where(['association_id' => $this->late->id, 'phase' => 2])->exists())->toBeFalse();
});

it('keeps an extended re-open a re-open, also after the phase closes', function () {
    m6Dashboard($this->game)->call('reopen', $this->done->id, 4, now()->addDays(2)->format('Y-m-d H:i'))
        ->call('extend', $this->done->id, 4, now()->addDays(4)->format('Y-m-d H:i'));
    $s = Submission::withoutGlobalScopes()->where(['association_id' => $this->done->id, 'phase' => 4])->sole();
    expect($s->deadline_change)->toBe('reopened');
    $this->game->phase(4)->update(['status' => PhaseStatus::Closed]);
    expect(app(PhaseGate::class)->canEdit($s->fresh()))->toBeTrue();
});

it('lets the association admin approve or return an awaiting entry in a phase re-opened for them', function () {
    $editor = User::factory()->assocEditor($this->late)->create();
    m6Dashboard($this->game)->call('reopen', $this->late->id, 2, now()->addDays(5)->format('Y-m-d H:i'));
    $s = Submission::withoutGlobalScopes()->where(['association_id' => $this->late->id, 'phase' => 2])->sole();
    $s->update(['status' => SubmissionStatus::AwaitingAssocAdmin, 'submitted_by' => $editor->id]);

    app(SubmissionWorkflow::class)->returnToDraft($s->fresh(), $this->lateAdmin);
    expect($s->fresh()->status)->toBe(SubmissionStatus::Draft);

    $s->refresh()->update(['status' => SubmissionStatus::AwaitingAssocAdmin]);
    app(SubmissionWorkflow::class)->approve($s->fresh(), $this->lateAdmin);
    expect($s->fresh()->status)->toBe(SubmissionStatus::Submitted);

    // Another association's awaiting entry in the same closed phase is refused.
    $other = Submission::for($this->game, $this->done, 2);
    $other->update(['status' => SubmissionStatus::AwaitingAssocAdmin]);
    expect(fn () => app(SubmissionWorkflow::class)->approve($other->fresh(), $this->doneAdmin))->toThrow(ValidationException::class, 'This phase is closed.');
});

it('refuses Edit as STA for an enabled phase that was never opened', function () {
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Draft, 'deadline' => now()->addDays(9)]);
    m6Dashboard($this->game)->assertDontSeeHtml('wire:click="editAsSta('.$this->late->id.', 1)"')->call('editAsSta', $this->late->id, 1)->assertNotFound();
    expect(Submission::withoutGlobalScopes()->where(['association_id' => $this->late->id, 'phase' => 1])->exists())->toBeFalse();
});
