<?php

namespace App\Filament\Resources\ClothingItems\Pages;

use App\Filament\Resources\ClothingItems\ClothingItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClothingItems extends ListRecords
{
    protected static string $resource = ClothingItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
