<?php

namespace App\Filament\Resources\Associations\RelationManagers;

use App\Filament\Shared\InvitationActions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('email'),
                TextColumn::make('role')->formatStateUsing(fn ($state) => $state->label()),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([InvitationActions::setActive(false), InvitationActions::setActive(true), InvitationActions::sendPasswordReset()]);
    }
}
