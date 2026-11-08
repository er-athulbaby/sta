<?php

namespace App\Filament\Resources\ClothingItems\Schemas;

use App\Enums\AppliesTo;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ClothingItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100)->unique(ignoreRecord: true)->helperText('e.g. T-Shirt, Suit, Tracksuit, Shoes'),
            TagsInput::make('sizes')->required()->reorderable()->helperText('Type a size and press Enter. Order is kept, e.g. XS, S, M, L, XL or 38, 39, 40'),
            Select::make('applies_to')->options(collect(AppliesTo::cases())->mapWithKeys(fn ($a) => [$a->value => $a->label()]))->required()->default(AppliesTo::Both->value),
            Toggle::make('is_active')->default(true),
        ]);
    }
}
