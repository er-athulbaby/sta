<?php

namespace App\Filament\Portal\Resources\Sports;

use App\Models\Sport;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SportResource extends Resource
{
    protected static ?string $model = Sport::class;

    protected static ?string $tenantOwnershipRelationshipName = 'association';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('is_active', true)->with('events'))
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('events')
                    ->state(fn (Sport $record) => $record->events->where('is_active', true)
                        ->map(fn ($e) => $e->name.' - '.$e->gender->label())->values()->all())
                    ->listWithLineBreaks(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSports::route('/')];
    }
}
