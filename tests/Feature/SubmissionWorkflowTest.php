<?php

use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Jobs\GenerateEntryByEventPdfs;
use App\Models\Association;
use App\Models\Game;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\DeadlineChanged;
use App\Notifications\SubmissionAwaitingApproval;
use App\Notifications\SubmissionReceived;
use App\Notifications\SubmissionReturned;
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
