<?php

namespace App\Filament\Resources\Associations\RelationManagers;

use App\Filament\Shared\InvitationActions;
use App\Models\Invitation;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'invitations';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email'),
                TextColumn::make('role')->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('status')->state(fn (Invitation $record) => $record->status())->badge()
                    ->color(fn (string $state) => match ($state) { 'pending' => 'warning', 'accepted' => 'success', default => 'gray' }),
                TextColumn::make('expires_at')->dateTime('d M Y'),
            ])
            ->recordActions([InvitationActions::resend(), InvitationActions::revoke()]);
    }
}
