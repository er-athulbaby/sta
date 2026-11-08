<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Association;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class AssociationMembership
{
    public const MAX_EDITORS = 2;

    public function ensureCanAddEditor(Association $association, ?Invitation $ignore = null): void
    {
        if ($this->seatCount($association, Role::AssocEditor, $ignore) >= self::MAX_EDITORS) {
            throw ValidationException::withMessages(['email' => 'This association already has 2 editors (active or invited).']);
        }
    }

    public function ensureCanAddAdmin(Association $association, ?Invitation $ignore = null): void
    {
        if ($this->seatCount($association, Role::AssocAdmin, $ignore) >= 1) {
            throw ValidationException::withMessages(['email' => 'This association already has an active or invited admin. Deactivate or revoke it first.']);
        }
    }

    public function setActive(User $user, bool $active): void
    {
        if ($active && ! $user->is_active) {
            $user->role === Role::AssocEditor
                ? $this->ensureCanAddEditor($user->association)
                : $this->ensureCanAddAdmin($user->association);
        }
        $user->update(['is_active' => $active]);
    }

    private function seatCount(Association $association, Role $role, ?Invitation $ignore): int
    {
        $active = $association->users()->where('role', $role)->where('is_active', true)->count();
        $pending = Invitation::pending()
            ->where('association_id', $association->id)
            ->where('role', $role)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->count();

        return $active + $pending;
    }
}
