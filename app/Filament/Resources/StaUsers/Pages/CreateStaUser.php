<?php

namespace App\Filament\Resources\StaUsers\Pages;

use App\Enums\Role;
use App\Filament\Resources\StaUsers\StaUserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateStaUser extends CreateRecord
{
    protected static string $resource = StaUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'role' => Role::Sta, 'association_id' => null];
    }

    /** saveRelationships() runs before this hook, so the permissions are stored. */
    protected function afterCreate(): void
    {
        self::logPermissions($this->record);
    }

    /** Pivot changes are invisible to LogsActivity, so record the permission set explicitly. */
    public static function logPermissions(User $user): void
    {
        activity()->performedOn($user)->causedBy(auth()->user())->event('updated')
            ->withProperties(['permissions' => $user->permissions()->pluck('name')->sort()->values()->all()])
            ->log('permissions updated');
    }
}
