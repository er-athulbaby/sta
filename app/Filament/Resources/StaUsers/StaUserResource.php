<?php

namespace App\Filament\Resources\StaUsers;

use App\Enums\Role;
use App\Filament\Resources\StaUsers\Pages;
use App\Filament\Resources\StaUsers\Schemas\StaUserForm;
use App\Filament\Resources\StaUsers\Tables\StaUsersTable;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaUserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $slug = 'sta-users';
    protected static ?string $modelLabel = 'STA user';
    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', Role::Sta);
    }

    public static function form(Schema $schema): Schema { return StaUserForm::configure($schema); }
    public static function table(Table $table): Table { return StaUsersTable::configure($table); }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaUsers::route('/'),
            'create' => Pages\CreateStaUser::route('/create'),
            'edit' => Pages\EditStaUser::route('/{record}/edit'),
        ];
    }
}
