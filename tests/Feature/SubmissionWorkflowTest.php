<?php

use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Jobs\GenerateEntryByEventPdfs;
use App\Models\Association;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\DeadlineChanged;
use App\Notifications\SubmissionAwaitingApproval;
use App\Notifications\SubmissionReceived;
use App\Notifications\SubmissionReturned;
use App\Services\Phases\PhaseGate;
use App\Services\Phases\SubmissionWorkflow;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    Bus::fake([GenerateEntryByEventPdfs::class]);
    $this->assoc = Association::factory()->create(['code' => 'BWF']);
    $this->game = Game::factory()->create();
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->sub = Submission::for($this->game, $this->assoc, 2);
    $this->admin = User::factory()->assocAdmin($this->assoc)->create();
    $this->editor = User::factory()->assocEditor($this->assoc)->create();
    $this->sta = User::factory()->sta()->create();
    $this->sta->givePermissionTo(StaPermission::ViewSubmissions->value);
    $this->wf = app(SubmissionWorkflow::class);
});

it('sends an editor submit to the association admin', function () {
    $s = $this->wf->submit($this->sub, $this->editor);

    expect($s->status)->toBe(SubmissionStatus::AwaitingAssocAdmin)->and($s->submitted_by)->toBe($this->editor->id);
    Notification::assertSentTo($this->admin, SubmissionAwaitingApproval::class);
    Notification::assertNotSentTo($this->sta, SubmissionReceived::class);
});

it('finalises on approval with a reference number, STA notice and PDF job', function () {
    $this->wf->submit($this->sub, $this->editor);
    $s = $this->wf->approve($this->sub->fresh(), $this->admin);

    expect($s->status)->toBe(SubmissionStatus::Submitted)
        ->and($s->reference_no)->toBe(sprintf('BWF-G%03d-P2-R1', $this->game->id))
        ->and($s->approved_by)->toBe($this->admin->id);
    Notification::assertSentTo($this->sta, SubmissionReceived::class);
    Bus::assertDispatched(GenerateEntryByEventPdfs::class);
});

it('lets an association admin submit directly', function () {
    expect($this->wf->submit($this->sub, $this->admin)->status)->toBe(SubmissionStatus::Submitted);
});

it('returns an awaiting entry to draft and tells the editor', function () {
    $this->wf->submit($this->sub, $this->editor);
    expect($this->wf->returnToDraft($this->sub->fresh(), $this->admin)->status)->toBe(SubmissionStatus::Draft);
    Notification::assertSentTo($this->editor, SubmissionReturned::class);
});

it('refuses moves that are not allowed', function () {
    expect(fn () => $this->wf->approve($this->sub, $this->admin))->toThrow(ValidationException::class);
    expect(fn () => $this->wf->approve($this->sub, $this->editor))->toThrow(ValidationException::class);
    $this->game->phase(2)->update(['status' => PhaseStatus::Closed]);
    expect(fn () => $this->wf->submit($this->sub->fresh(), $this->admin))->toThrow(ValidationException::class);
});

it('re-opens a submitted entry, bumps the revision on the next submit and emails the association', function () {
    $this->wf->submit($this->sub, $this->admin);
    $s = $this->wf->reopen($this->sub->fresh(), $this->sta, now()->addDays(10));
    expect($s->status)->toBe(SubmissionStatus::Draft)->and($s->deadline_override)->not->toBeNull();
    Notification::assertSentTo([$this->admin, $this->editor], DeadlineChanged::class);

    expect($this->wf->submit($s, $this->admin)->reference_no)->toEndWith('-R2');
});

it('extends one association\'s deadline', function () {
    $s = $this->wf->extend($this->sub, $this->sta, now()->addDays(20));
    expect($s->deadline_override->isSameDay(now()->addDays(20)))->toBeTrue();
    Notification::assertSentTo($this->admin, DeadlineChanged::class);
});

function overLimit($t): GameSport
{
    $gs = GameSport::factory()->create([
        'game_id' => $t->game->id, 'sport_id' => Sport::factory()->create(['association_id' => $t->assoc->id])->id,
        'p2_max_total' => 1,
    ]);
    $ge = GameEvent::factory()->create(['game_id' => $t->game->id, 'game_sport_id' => $gs->id, 'p2_max_athletes' => null]);
    $t->sub->numbers()->create(['game_event_id' => $ge->id, 'count' => 2]);

    return $gs;
}

it('ignores stored numbers for sports answered No in phase 1', function () {
    $gs = overLimit($this);
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(1)->update(['enabled' => true]);
    Submission::for($this->game, $this->assoc, 1)->answers()->create(['game_sport_id' => $gs->id, 'answer' => 'no']);
    expect($this->wf->submit($this->sub, $this->admin)->status)->toBe(SubmissionStatus::Submitted);
});

it('blocks submit and approve over the Phase 2 limits', function () {
    overLimit($this);
    expect(fn () => $this->wf->submit($this->sub, $this->admin))->toThrow(ValidationException::class);
    expect(fn () => $this->wf->submit($this->sub, $this->editor))->toThrow(ValidationException::class);
    $this->sub->update(['status' => SubmissionStatus::AwaitingAssocAdmin, 'submitted_by' => $this->editor->id]);
    expect(fn () => $this->wf->approve($this->sub->fresh(), $this->admin))->toThrow(ValidationException::class);
});

it('passes submit within the Phase 2 limits', function () {
    overLimit($this);
    $this->sub->numbers()->update(['count' => 1]);
    expect($this->wf->submit($this->sub, $this->admin)->status)->toBe(SubmissionStatus::Submitted);
});

it('refuses reopen of a draft and extend of a submitted entry without notifying', function () {
    expect(fn () => $this->wf->reopen($this->sub, $this->sta))->toThrow(ValidationException::class);
    $this->wf->submit($this->sub, $this->admin);
    expect(fn () => $this->wf->extend($this->sub->fresh(), $this->sta, now()->addDays(20)))->toThrow(ValidationException::class);
    Notification::assertNothingSentTo($this->editor);
});

it('does not dispatch the PDF job on a Phase 1 submit', function () {
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->wf->submit(Submission::for($this->game, $this->assoc, 1), $this->admin);
    Bus::assertNotDispatched(GenerateEntryByEventPdfs::class);
});

it('stops an editor of another association submitting', function () {
    $other = User::factory()->assocEditor(Association::factory()->create())->create();
    expect(fn () => $this->wf->submit($this->sub, $other))->toThrow(ValidationException::class);
});

it('refuses re-open after the deadline without a future deadline, and works with one', function () {
    $this->wf->submit($this->sub, $this->admin);
    $this->game->phase(2)->update(['deadline' => now()->subDay()]);
    Notification::fake();

    expect(fn () => $this->wf->reopen($this->sub->fresh(), $this->sta))->toThrow(ValidationException::class);
    expect(fn () => $this->wf->reopen($this->sub->fresh(), $this->sta, now()->subHour()))->toThrow(ValidationException::class);
    expect($this->sub->fresh()->status)->toBe(SubmissionStatus::Submitted);
    Notification::assertNothingSent();

    $s = $this->wf->reopen($this->sub->fresh(), $this->sta, now()->addDays(3));
    expect($s->status)->toBe(SubmissionStatus::Draft)->and(app(PhaseGate::class)->canEdit($s->fresh()))->toBeTrue();
});

it('refuses approve and return once the phase is closed or disabled', function () {
    $this->wf->submit($this->sub, $this->editor);
    $this->game->phase(2)->update(['status' => PhaseStatus::Closed]);
    expect(fn () => $this->wf->approve($this->sub->fresh(), $this->admin))->toThrow(ValidationException::class, 'This phase is closed.');
    expect(fn () => $this->wf->returnToDraft($this->sub->fresh(), $this->admin))->toThrow(ValidationException::class, 'This phase is closed.');
    $this->game->phase(2)->update(['status' => PhaseStatus::Open, 'enabled' => false]);
    expect(fn () => $this->wf->approve($this->sub->fresh(), $this->admin))->toThrow(ValidationException::class, 'not used');
});

it('clears stale PDF links on re-submit', function () {
    $this->wf->submit($this->sub, $this->admin);
    $this->sub->forceFill(['pdf_paths' => ['1' => 'x.pdf']])->saveQuietly();
    $s = $this->wf->reopen($this->sub->fresh(), $this->sta);
    $this->wf->submit($s, $this->admin);
    expect($this->sub->fresh()->pdf_paths)->toBeNull();
});

it('bumps lock_version on every status change', function () {
    $v = $this->sub->fresh()->lock_version;
    $this->wf->submit($this->sub, $this->editor);
    expect($this->sub->fresh()->claimVersion($v))->toBeFalse();
    $v = $this->sub->fresh()->lock_version;
    $this->wf->returnToDraft($this->sub->fresh(), $this->admin);
    expect($this->sub->fresh()->lock_version)->toBeGreaterThan($v);
});
