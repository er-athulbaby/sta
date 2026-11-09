<?php

namespace App\Filament\Shared;

use App\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    /** Row and sizes commit together: an invalid size rolls back the person. */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $record = parent::handleRecordCreation($data);
            $record->syncSizes($this->pendingSizes);

            return $record;
        });
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            $record = parent::handleRecordUpdate($record, $data);
            $record->syncSizes($this->pendingSizes);

            return $record;
        });
    }

    /** Uploads are renamed after save; resync the form so it doesn't keep the stale temp path (Create pages never call this). */
    protected function afterSave(): void
    {
        $this->refreshFormData(array_keys(Person::FILE_COLUMNS));
    }

    private function pullSizes(array $data): array
    {
        $this->pendingSizes = $data['sizes'] ?? [];
        unset($data['sizes']);

        return $data;
    }
}
