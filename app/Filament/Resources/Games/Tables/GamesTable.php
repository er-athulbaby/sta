<?php

namespace App\Filament\Resources\Games\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GamesTable
{
    public static function configure(Table $table): Table
    {
        // No delete actions: games are never deleted.
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('start_date')->date()->sortable(),
                TextColumn::make('end_date')->date()->sortable(),
                TextColumn::make('associations_count')->counts('associations')->label('Associations'),
            ])
            ->defaultSort('start_date', 'desc')
            ->recordActions([EditAction::make()]);
    }
}
