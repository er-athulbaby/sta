<?php

namespace App\Filament\Resources\Sports\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SportsTable
{
    public static function configure(Table $table): Table
    {
        // No delete actions: sports are only deactivated (BR-16).
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('association.name')->searchable(),
                TextColumn::make('events_count')->counts('events')->label('Events'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([SelectFilter::make('association')->relationship('association', 'name')])
            ->recordActions([EditAction::make()]);
    }
}
