<?php

namespace App\Filament\Resources\Sports\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** The list page draws the canvas AdminSports.dc.html sports list itself from these records (the "Association" select is the filter). */
class SportsTable
{
    public static function configure(Table $table): Table
    {
        // No delete actions: sports are only deactivated (BR-16).
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('association')->withCount('events'))
            ->defaultSort('name')
            ->paginated(false)
            ->deferFilters(false)
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('association.name')->searchable(),
            ])
            ->filters([SelectFilter::make('association')->relationship('association', 'name')])
            ->recordActions([EditAction::make()]);
    }
}
