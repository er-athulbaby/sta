<?php

namespace App\Filament\Resources\StaUsers;

use App\Enums\Role;
use App\Enums\StaArea;
use App\Filament\Resources\StaUsers\Schemas\StaUserForm;
use App\Filament\Resources\StaUsers\Tables\StaUsersTable;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * "Users & groups" > Users: STA users and Super Admins. Needs Users & groups: Access / New / Save; users are never
 * deleted (deactivate instead). Only a Super Admin edits a Super Admin or changes an account type.
 */
class StaUserResource extends Resource
{
    protected static ?string $navigationLabel = 'Users & groups';

    protected static ?int $navigationSort = 8;

    protected static ?string $model = User::class;

    protected static ?string $slug = 'sta-users';

    protected static ?string $modelLabel = 'STA user';

    public static function canAccess(): bool
    {
        return StaArea::Users->can();
    }

    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        if (in_array($action, ['delete', 'deleteAny', 'forceDelete', 'forceDeleteAny'], true)) {
            return Response::deny('Users are deactivated, not deleted.');
        }
        if ($record instanceof User && $record->isSuperAdmin() && ! auth()->user()?->isSuperAdmin()) {
            return Response::deny('Only a Super Admin can change a Super Admin.');
        }

        return StaArea::Users->authorize($action);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('role', [Role::Sta, Role::SuperAdmin])->with('roles');
    }

    public static function form(Schema $schema): Schema
    {
        return StaUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaUsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaUsers::route('/'),
            'create' => Pages\CreateStaUser::route('/create'),
            'edit' => Pages\EditStaUser::route('/{record}/edit'),
        ];
    }
}
