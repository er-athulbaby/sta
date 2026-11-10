<?php

namespace App\Filament\Resources\Games\Tables;

use App\Enums\StaPermission;
use App\Filament\Resources\Games\GameResource;
use App\Models\Game;
use Filament\Actions\Action;
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
                TextColumn::make('published_at')->label('Status')->badge()
                    ->state(fn (Game $record) => $record->isPublished() ? 'Published' : 'Draft')
                    ->color(fn (string $state) => $state === 'Published' ? 'success' : 'gray'),
                TextColumn::make('start_date')->date()->sortable(),
                TextColumn::make('end_date')->date()->sortable(),
                TextColumn::make('associations_count')->counts('associations')->label('Associations'),
            ])
            ->defaultSort('start_date', 'desc')
            ->recordActions([
                Action::make('dashboard')->icon('heroicon-o-table-cells')
                    ->visible(fn () => (bool) auth()->user()->can(StaPermission::ViewSubmissions->value))
                    ->url(fn ($record) => GameResource::getUrl('dashboard', ['record' => $record])),
                Action::make('reports')->icon('heroicon-o-document-chart-bar')
                    ->visible(fn () => (bool) auth()->user()->can(StaPermission::ViewReports->value))
                    ->url(fn ($record) => GameResource::getUrl('reports', ['record' => $record])),
                EditAction::make()->visible(fn ($record) => GameResource::getEditAuthorizationResponse($record)->allowed()),
            ]);
    }
}
