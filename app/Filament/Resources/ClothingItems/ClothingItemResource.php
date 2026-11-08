<?php

namespace App\Filament\Resources\ClothingItems;

use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;
use App\Filament\Resources\ClothingItems\Pages\CreateClothingItem;
use App\Filament\Resources\ClothingItems\Pages\EditClothingItem;
use App\Filament\Resources\ClothingItems\Pages\ListClothingItems;
use App\Filament\Resources\ClothingItems\Schemas\ClothingItemForm;
use App\Filament\Resources\ClothingItems\Tables\ClothingItemsTable;
use App\Models\ClothingItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClothingItemResource extends Resource
{
    use RequiresStaPermission;

    protected static ?string $model = ClothingItem::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ManageMasterData;
    }

    public static function form(Schema $schema): Schema
    {
        return ClothingItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClothingItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClothingItems::route('/'),
            'create' => CreateClothingItem::route('/create'),
            'edit' => EditClothingItem::route('/{record}/edit'),
        ];
    }
}
