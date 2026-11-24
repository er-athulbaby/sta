<?php

namespace App\Filament\Resources\StaUsers\Pages;

use App\Enums\Role;
use App\Filament\Resources\StaUsers\StaUserResource;
use App\Models\StaGroup;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateStaUser extends CreateRecord
{
    protected static string $resource = StaUserResource::class;

    protected ?int $groupId = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->groupId = filled($data['group_id'] ?? null) ? (int) $data['group_id'] : null;
        unset($data['group_id']);
        // Only a Super Admin may create a Super Admin (nobody else sees the field).
        $role = auth()->user()->isSuperAdmin() && ($data['role'] ?? null) === Role::SuperAdmin->value ? Role::SuperAdmin : Role::Sta;

        return [...$data, 'role' => $role, 'association_id' => null];
    }

    protected function afterCreate(): void
    {
        self::syncGroup($this->record, $this->groupId);
    }

    /** One group per STA user (none for a Super Admin); audited, as pivot changes are invisible to LogsActivity. */
    public static function syncGroup(User $user, ?int $groupId): void
    {
        $group = $user->isSuperAdmin() || $groupId === null ? null : StaGroup::findOrFail($groupId);
        $before = $user->roles()->value('name');
        $user->syncRoles($group ? [$group] : []);
        if ($before !== $group?->name) {
            activity()->performedOn($user)->causedBy(auth()->user())->event('updated')
                ->withProperties(['old' => ['group' => $before], 'attributes' => ['group' => $group?->name]])
                ->log('user group changed');
        }
    }
}
