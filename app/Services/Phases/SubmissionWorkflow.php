<?php

namespace App\Services\Phases;

use App\Enums\PhaseStatus;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Jobs\GenerateEntryByEventPdfs;
use App\Jobs\GenerateEntryBySportPdf;
use App\Jobs\GenerateLongListPdf;
use App\Jobs\GenerateShortListPdf;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\DeadlineChanged;
use App\Notifications\SubmissionAwaitingApproval;
use App\Notifications\SubmissionReceived;
use App\Notifications\SubmissionReturned;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class SubmissionWorkflow
{
    public function __construct(private readonly PhaseGate $gate) {}

    public function submit(Submission $s, User $by): Submission
    {
        $this->ownAssociation($s, $by);
        if ($reason = $this->gate->denialReason($s)) {
            $this->fail($reason);
        }
        $this->assertWithinLimits($s, requireTotals: true);
        if ($by->role === Role::AssocAdmin) {
            return $this->finalise($s, $by, submittedBy: $by);
        }
        $this->change($s, ['status' => SubmissionStatus::AwaitingAssocAdmin, 'submitted_by' => $by->id, 'submitted_at' => now()]);
        Notification::send(Recipients::association($s->association, Role::AssocAdmin), new SubmissionAwaitingApproval($s));

        return $s;
    }

    public function approve(Submission $s, User $by): Submission
    {
        $this->ownAssociation($s, $by);
        if ($by->role !== Role::AssocAdmin) {
            $this->fail('Only the association admin can approve.');
        }
        if ($s->status !== SubmissionStatus::AwaitingAssocAdmin) {
            $this->fail('This entry is not waiting for approval.');
        }
        $this->assertPhaseOpen($s);

        $this->assertWithinLimits($s);

        // ponytail: approval is allowed after the deadline — the editor sent it in time; block here if STA wants otherwise.
        return $this->finalise($s, $by, submittedBy: User::find($s->submitted_by) ?? $by);
    }

    public function returnToDraft(Submission $s, User $by): Submission
    {
        $this->ownAssociation($s, $by);
        if ($by->role !== Role::AssocAdmin) {
            $this->fail('Only the association admin can return an entry.');
        }
        if ($s->status !== SubmissionStatus::AwaitingAssocAdmin) {
            $this->fail('This entry is not waiting for approval.');
        }
        $this->assertPhaseOpen($s);
        $editor = User::find($s->submitted_by);
        $this->change($s, ['status' => SubmissionStatus::Draft]);
        $editor?->notify(new SubmissionReturned($s));

        return $s;
    }

    public function reopen(Submission $s, User $by, ?CarbonInterface $newDeadline = null): Submission
    {
        if (! in_array($s->status, [SubmissionStatus::Submitted, SubmissionStatus::AwaitingAssocAdmin], true)) {
            $this->fail('This entry is not submitted, so there is nothing to re-open.');
        }
        $effective = $newDeadline ?? $s->deadline_override ?? $s->gamePhase()->deadline;
        if ($effective === null || $effective->isPast()) {
            $this->fail('Give a new deadline in the future to re-open this entry.');
        }
        $this->change($s, ['status' => SubmissionStatus::Draft, 'deadline_override' => $newDeadline ?? $s->deadline_override]);
        Notification::send(Recipients::association($s->association), new DeadlineChanged($s, reopened: true));

        return $s;
    }

    public function extend(Submission $s, User $by, CarbonInterface $deadline): Submission
    {
        if ($s->status === SubmissionStatus::Submitted) {
            $this->fail('This entry is already submitted — re-open it to change the deadline.');
        }
        $this->change($s, ['deadline_override' => $deadline]);
        Notification::send(Recipients::association($s->association), new DeadlineChanged($s, reopened: false));

        return $s;
    }

    private function finalise(Submission $s, User $approver, User $submittedBy): Submission
    {
        DB::transaction(function () use ($s, $approver, $submittedBy) {
            $revision = $s->revision + 1;
            $this->change($s, [
                'status' => SubmissionStatus::Submitted,
                'pdf_paths' => null,
                'revision' => $revision,
                'reference_no' => sprintf('%s-G%03d-P%d-R%d', $s->association->code, $s->game_id, $s->phase, $revision),
                'submitted_by' => $submittedBy->id,
                'submitted_at' => $s->submitted_at ?? now(),
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);
        });
        Notification::send(Recipients::sta(), new SubmissionReceived($s));
        if ($s->phase === 1) {
            GenerateEntryBySportPdf::dispatch($s);
        }
        if ($s->phase === 2) {
            GenerateEntryByEventPdfs::dispatch($s);
        }
        if ($s->phase === 3) {
            GenerateLongListPdf::dispatch($s);
        }
        if ($s->phase === 4) {
            GenerateShortListPdf::dispatch($s);
        }

        return $s;
    }

    /** Every status change bumps lock_version so editors holding a stale page cannot save over it. */
    private function change(Submission $s, array $attributes): void
    {
        $s->update($attributes);
        $s->increment('lock_version');
    }

    private function assertPhaseOpen(Submission $s): void
    {
        $phase = $s->gamePhase();
        if (! $phase->enabled) {
            $this->fail('This phase is not used for this game.');
        }
        if ($phase->status !== PhaseStatus::Open) {
            $this->fail('This phase is closed.');
        }
    }

    private function assertWithinLimits(Submission $s, bool $requireTotals = false): void
    {
        if ($s->phase === 4) {
            $checks = app(ShortListChecks::class);
            if ($errors = $checks->submitErrors($s)) {
                $this->fail(implode(' ', $errors));
            }
            if ($checks->overLimits($s) && blank($s->justification)) {
                $this->fail(ShortListChecks::JUSTIFICATION_REQUIRED);
            }

            return;
        }
        if ($s->phase === 3) {
            if ($errors = app(LongListChecks::class)->submitErrors($s)) {
                $this->fail(implode(' ', $errors));
            }

            return;
        }
        if ($s->phase !== 2) {
            return;
        }
        $counts = $s->numbers()->pluck('count', 'game_event_id')->all();
        $totals = $s->sportTotals()->get()->keyBy('game_sport_id');
        foreach ($s->game->phase2SportsFor($s->association) as $gs) {
            $t = $totals->get($gs->id);
            // Totals are required on submit only, so entries already awaiting approval can still be approved.
            if ($requireTotals && ($t?->male === null || $t->female === null)) {
                $this->fail("{$gs->sport->name}: enter the total number of male and female participants.");
            }
            if ($errors = app(Phase2Limits::class)->errors($gs, $counts, ['male' => $t?->male, 'female' => $t?->female])) {
                $this->fail(reset($errors));
            }
        }
    }

    private function ownAssociation(Submission $s, User $by): void
    {
        if (! $by->role->isAssociationRole() || $by->association_id !== $s->association_id) {
            $this->fail('You cannot act on another association\'s entry.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['submission' => $message]);
    }
}
