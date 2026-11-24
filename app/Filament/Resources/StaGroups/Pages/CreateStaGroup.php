<?php

namespace App\Filament\Resources\StaGroups\Pages;

use App\Enums\StaArea;
use App\Filament\Resources\StaGroups\StaGroupResource;
use App\Models\StaGroup;
use Filament\Resources\Pages\CreateRecord;

class CreateStaGroup extends CreateRecord
{
    protected static string $resource = StaGroupResource::class;

    /** @var list<string> */
    protected array $cells = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->cells = StaArea::normalise((array) ($data['cells'] ?? []));
        unset($data['cells']);

        return [...$data, 'guard_name' => 'web'];
    }

    protected function afterCreate(): void
    {
        self::syncCells($this->record, $this->cells);
    }

    /** Stores the ticked cells (normalised: New/Save/Delete/Print imply Access) and audits them when they changed. */
    public static function syncCells(StaGroup $group, array $cells): void
    {
        $before = $group->permissions()->pluck('name')->sort()->values()->all();
        $group->syncPermissions($cells);
        $after = $group->permissions()->pluck('name')->sort()->values()->all();
        if ($before !== $after || $group->wasRecentlyCreated) {
            $group->logPermissions();
        }
    }
}
