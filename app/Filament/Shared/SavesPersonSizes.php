<?php

namespace App\Filament\Shared;

/** Used by Create/Edit person pages: `sizes.*` is not a column, it is synced to person_sizes after save. */
trait SavesPersonSizes
{
    protected array $pendingSizes = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['sizes'] = $this->record?->sizesMap() ?? [];

        return $data;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->pullSizes($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->pullSizes($data);
    }

    protected function afterCreate(): void
    {
        $this->record->syncSizes($this->pendingSizes);
    }

    protected function afterSave(): void
    {
        $this->record->syncSizes($this->pendingSizes);
    }

    private function pullSizes(array $data): array
    {
        $this->pendingSizes = $data['sizes'] ?? [];
        unset($data['sizes']);

        return $data;
    }
}
