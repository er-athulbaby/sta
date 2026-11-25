<?php

namespace App\Filament\Portal\Resources\Members;

use App\Enums\Role;
use App\Filament\Portal\Resources\Members\Pages\ListMembers;
use App\Filament\Shared\InvitationActions;
use App\Filament\Shared\UserFields;
use App\Models\User;
use Filament\Actions\Action;
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
                UserFields::avatarColumn(),
                TextColumn::make('name'),
                TextColumn::make('email'),
                UserFields::mobileColumn(),
                TextColumn::make('role')->formatStateUsing(fn (Role $state) => $state->label()),
                IconColumn::make('is_active')->boolean(),
            ])
            // The association admin manages its editors only (never itself or another admin); the table is tenant-scoped,
            // and Filament refuses to run a hidden action, so these rules hold server-side too.
            ->recordActions(collect([self::editMobile(), InvitationActions::setActive(false), InvitationActions::setActive(true), InvitationActions::sendPasswordReset()])
                ->map(fn ($action) => $action->hidden(fn (User $record) => $record->role !== Role::AssocEditor))->all());
    }

    /** The editor's mobile (the photo is the user's own, set on their profile and only shown here). */
    private static function editMobile(): Action
    {
        return Action::make('editMobile')->label('Mobile')->icon('heroicon-o-device-phone-mobile')
            ->modalHeading(fn (User $record) => "Mobile of {$record->name}")
            ->fillForm(fn (User $record) => ['phone_cc' => $record->phone_cc, 'phone' => $record->phone])
            ->schema([UserFields::mobile()])
            ->action(function (User $record, array $data, Action $action) {
                $record->update($data);
                $action->success();
            })
            ->successNotificationTitle('Mobile saved');
    }

    public static function getPages(): array
    {
        return ['index' => ListMembers::route('/')];
    }
}
