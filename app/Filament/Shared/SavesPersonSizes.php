<?php

namespace App\Filament\Shared;

use App\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Used by Create/Edit person pages: `sizes.*` and `extra.*` are not columns; they are synced after save. */
trait SavesPersonSizes
{
    protected array $pendingSizes = [];

    protected array $pendingExtra = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['sizes'] = $this->record?->sizesMap() ?? [];
        // Sensitive extra values the user may not see never reach the browser.
        $data['extra'] = $this->record?->extraValues(auth()->user()) ?? [];
        if (! Person::canViewMedical(auth()->user())) {
            // Form data is sent to the browser even for hidden fields, so medical values must never be filled.
            $data = array_diff_key($data, array_flip(Person::MEDICAL_GATED));
        }

        return $data;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->pullRelated($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->pullRelated($data);
    }

    /** Row, sizes and extra values commit together: an invalid value rolls back the person. */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $record = parent::handleRecordCreation($data);
            $record->syncSizes($this->pendingSizes);
            $record->syncExtraValues($this->pendingExtra, auth()->user());

            return $record;
        });
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            $record = parent::handleRecordUpdate($record, $data);
            $record->syncSizes($this->pendingSizes);
            $record->syncExtraValues($this->pendingExtra, auth()->user());

            return $record;
        });
    }

    /** Uploads are renamed after save; resync the form so it doesn't keep the stale temp path (Create pages never call this). */
    protected function afterSave(): void
    {
        $this->refreshFormData(array_keys(Person::FILE_COLUMNS));
    }

    private function pullRelated(array $data): array
    {
        $this->pendingSizes = $data['sizes'] ?? [];
        $this->pendingExtra = $data['extra'] ?? [];
        unset($data['sizes'], $data['extra']);

        return $data;
    }
}
