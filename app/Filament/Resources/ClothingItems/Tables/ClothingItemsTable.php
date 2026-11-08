<?php

namespace App\Filament\Resources\ClothingItems\Tables;

use App\Enums\AppliesTo;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClothingItemsTable
{
    public static function configure(Table $table): Table
    {
        // No delete actions: clothing items are only deactivated (BR-16).
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('sizes')->badge()->separator(','),
                TextColumn::make('applies_to')->formatStateUsing(fn (AppliesTo $state) => $state->label()),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }
}
