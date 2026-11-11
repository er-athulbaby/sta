<?php

namespace App\Filament\Portal\Resources\Invitations;

use App\Enums\Role;
use App\Filament\Portal\Resources\Invitations\Pages\ListInvitations;
use App\Filament\Shared\InvitationActions;
use App\Models\Invitation;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvitationResource extends Resource
{
    protected static ?int $navigationSort = 6;

    protected static ?string $model = Invitation::class;

    protected static ?string $tenantOwnershipRelationshipName = 'association';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === Role::AssocAdmin;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email'),
                TextColumn::make('status')->state(fn (Invitation $record) => $record->status())->badge(),
                TextColumn::make('expires_at')->dateTime('d M Y'),
            ])
            ->recordActions([InvitationActions::resend(), InvitationActions::revoke()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListInvitations::route('/')];
    }
}
