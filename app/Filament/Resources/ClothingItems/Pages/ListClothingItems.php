<?php

namespace App\Filament\Resources\ClothingItems\Pages;

use App\Filament\Resources\ClothingItems\ClothingItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClothingItems extends ListRecords
{
    protected static string $resource = ClothingItemResource::class;

    /** Canvas list pages have no "X / List" breadcrumb. */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
