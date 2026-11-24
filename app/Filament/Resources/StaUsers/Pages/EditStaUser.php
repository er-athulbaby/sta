<?php

namespace App\Filament\Resources\StaUsers\Pages;

use App\Filament\Resources\StaUsers\StaUserResource;
use Filament\Resources\Pages\EditRecord;

/** No delete: a hard delete would orphan audit-log causers. Deactivate with is_active instead. */
class EditStaUser extends EditRecord
{
    protected static string $resource = StaUserResource::class;

    protected ?int $groupId = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, 'group_id' => $this->record->roles()->value('id')];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->groupId = filled($data['group_id'] ?? null) ? (int) $data['group_id'] : null;
        unset($data['group_id']);

        return $data;
    }

    protected function afterSave(): void
    {
        CreateStaUser::syncGroup($this->record->refresh(), $this->groupId);
    }
}
