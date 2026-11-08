<?php

namespace App\Filament\Resources\ClothingItems\Pages;

use App\Filament\Resources\ClothingItems\ClothingItemResource;
use Filament\Resources\Pages\EditRecord;

class EditClothingItem extends EditRecord
{
    protected static string $resource = ClothingItemResource::class;

    // No header actions: clothing items are never deleted (BR-16).
}
