<?php

namespace App\Policies;

use App\Enums\StaAction;
use App\Enums\StaArea;
use App\Models\SavedReport;
use App\Models\User;

/**
 * Spec 14: shared reports are visible to everyone with Reports: Access; only the creator changes them, and only with
 * Reports: Save (Delete to delete). Save as copy needs Reports: New. The Super Admin passes via Gate::before.
 */
class SavedReportPolicy
{
    public function viewAny(User $user): bool
    {
        return StaArea::Reports->can(user: $user);
    }

    /** Save as copy creates a new private report owned by the user. */
    public function create(User $user): bool
    {
        return StaArea::Reports->can(StaAction::New, $user);
    }

    /** Run (exporting also needs Reports: Print). */
    public function view(User $user, SavedReport $report): bool
    {
        return $this->viewAny($user)
            && ((int) $report->created_by === $user->id || $report->visibility === 'shared');
    }

    /** Save changes and sharing. */
    public function update(User $user, SavedReport $report): bool
    {
        return StaArea::Reports->can(StaAction::Save, $user) && (int) $report->created_by === $user->id;
    }

    public function delete(User $user, SavedReport $report): bool
    {
        return StaArea::Reports->can(StaAction::Delete, $user) && (int) $report->created_by === $user->id;
    }
}
