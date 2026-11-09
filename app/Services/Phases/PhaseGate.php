<?php

namespace App\Services\Phases;

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use Carbon\CarbonInterface;

final class PhaseGate
{
    public function denialReason(Submission $s, ?CarbonInterface $now = null): ?string
    {
        $phase = $s->gamePhase();
        $deadline = $phase->effectiveDeadline($s);

        return match (true) {
            ! $phase->enabled => 'This phase is not used for this game.',
            $phase->status === PhaseStatus::Draft => 'This phase is not open yet.',
            $phase->status === PhaseStatus::Closed => 'This phase is closed.',
            $deadline !== null && ($now ?? now())->greaterThan($deadline) => 'The deadline has passed.',
            $s->status === SubmissionStatus::AwaitingAssocAdmin => 'This entry has been sent for approval.',
            $s->status === SubmissionStatus::Submitted => 'This entry has been submitted.',
            default => null,
        };
    }

    public function canEdit(Submission $s, ?CarbonInterface $now = null): bool
    {
        return $this->denialReason($s, $now) === null;
    }

    /**
     * Checks and claims in one atomic step: throws StaleSubmission unless the row still holds
     * $loadedVersion, and on success moves it to $loadedVersion + 1. Call it inside the save
     * transaction, right before writing.
     */
    public function assertFresh(Submission $s, int $loadedVersion): void
    {
        if (! $s->claimVersion($loadedVersion)) {
            throw new StaleSubmission;
        }
    }
}
