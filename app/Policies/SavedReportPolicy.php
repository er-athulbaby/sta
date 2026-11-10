<?php

namespace App\Policies;

use App\Enums\StaPermission;
use App\Models\SavedReport;
use App\Models\User;

/** Spec 14: shared reports are visible to everyone with view_reports; only the creator changes them. The Super Admin passes via Gate::before. */
class SavedReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(StaPermission::ViewReports->value);
    }

    /** Save as copy creates a new private report owned by the user. */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /** Run and export. */
    public function view(User $user, SavedReport $report): bool
    {
        return $this->viewAny($user)
            && ((int) $report->created_by === $user->id || $report->visibility === 'shared');
    }

    /** Save changes and sharing. */
    public function update(User $user, SavedReport $report): bool
    {
        return $this->viewAny($user) && (int) $report->created_by === $user->id;
    }

    public function delete(User $user, SavedReport $report): bool
    {
        return $this->update($user, $report);
    }
}
