<?php

namespace App\Services\Phases;

use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Jobs\GenerateEntryByEventPdfs;
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
        if ($by->role === Role::AssocAdmin) {
            return $this->finalise($s, $by, submittedBy: $by);
        }
        $s->update(['status' => SubmissionStatus::AwaitingAssocAdmin, 'submitted_by' => $by->id, 'submitted_at' => now()]);
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
        $editor = User::find($s->submitted_by);
        $s->update(['status' => SubmissionStatus::Draft]);
        $editor?->notify(new SubmissionReturned($s));

        return $s;
    }

    public function reopen(Submission $s, User $by, ?CarbonInterface $newDeadline = null): Submission
    {
        $s->update(['status' => SubmissionStatus::Draft, 'deadline_override' => $newDeadline ?? $s->deadline_override]);
        Notification::send(Recipients::association($s->association), new DeadlineChanged($s, reopened: true));

        return $s;
    }

    public function extend(Submission $s, User $by, CarbonInterface $deadline): Submission
    {
        $s->update(['deadline_override' => $deadline]);
        Notification::send(Recipients::association($s->association), new DeadlineChanged($s, reopened: false));

        return $s;
    }

    private function finalise(Submission $s, User $approver, User $submittedBy): Submission
    {
        DB::transaction(function () use ($s, $approver, $submittedBy) {
            $revision = $s->revision + 1;
            $s->update([
                'status' => SubmissionStatus::Submitted,
                'revision' => $revision,
                'reference_no' => sprintf('%s-G%03d-P%d-R%d', $s->association->code, $s->game_id, $s->phase, $revision),
                'submitted_by' => $submittedBy->id,
                'submitted_at' => $s->submitted_at ?? now(),
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);
        });
        Notification::send(Recipients::sta(), new SubmissionReceived($s));
        if ($s->phase === 2) {
            GenerateEntryByEventPdfs::dispatch($s);
        }

        return $s;
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
