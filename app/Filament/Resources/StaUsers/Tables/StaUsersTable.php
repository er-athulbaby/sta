<?php

namespace App\Filament\Resources\StaUsers\Tables;

use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StaUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('group')->label('Group')
                    ->state(fn (User $record) => $record->isSuperAdmin() ? 'Super Admin (everything)' : ($record->group()?->name ?? 'No group'))
                    ->badge()->color(fn (User $record) => $record->isSuperAdmin() ? 'primary' : ($record->group() ? 'gray' : 'danger')),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }
}
