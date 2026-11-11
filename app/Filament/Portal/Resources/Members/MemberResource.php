<?php

namespace App\Filament\Portal\Resources\Members;

use App\Enums\Role;
use App\Filament\Portal\Resources\Members\Pages\ListMembers;
use App\Filament\Shared\InvitationActions;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?int $navigationSort = 4;

    protected static ?string $model = User::class;

    protected static ?string $tenantOwnershipRelationshipName = 'association';

    protected static ?string $slug = 'members';

    protected static ?string $navigationLabel = 'Team';

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
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('email'),
                TextColumn::make('role')->formatStateUsing(fn (Role $state) => $state->label()),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([InvitationActions::setActive(false), InvitationActions::setActive(true)]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMembers::route('/')];
    }
}
