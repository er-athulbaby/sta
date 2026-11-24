<?php

namespace App\Filament\Resources\StaGroups\Pages;

use App\Enums\StaArea;
use App\Filament\Resources\StaGroups\StaGroupResource;
use Filament\Resources\Pages\EditRecord;

class EditStaGroup extends EditRecord
{
    protected static string $resource = StaGroupResource::class;

    /** @var list<string> */
    protected array $cells = [];

    protected function getHeaderActions(): array
    {
        return [StaGroupResource::deleteAction()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, 'cells' => $this->record->permissions()->pluck('name')->all()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->cells = StaArea::normalise((array) ($data['cells'] ?? []));
        unset($data['cells']);

        return $data;
    }

    protected function afterSave(): void
    {
        CreateStaGroup::syncCells($this->record, $this->cells);
    }
}
