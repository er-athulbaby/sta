<?php

namespace App\Filament\Resources\Associations\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssociationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')->label('')->disk(config('filesystems.private'))->visibility('private')->circular(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('users_count')->counts('users')->label('Users'),
            ])
            ->recordActions([EditAction::make()]);
    }
}
