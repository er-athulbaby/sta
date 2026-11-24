<?php

namespace App\Filament\Resources\Associations\RelationManagers;

use App\Enums\StaAction;
use App\Filament\Resources\Associations\AssociationResource;
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
            ->recordActions(array_map(fn ($a) => $a->authorize(AssociationResource::cellCheck(StaAction::Save)),
                [InvitationActions::setActive(false), InvitationActions::setActive(true), InvitationActions::sendPasswordReset()]));
    }
}
