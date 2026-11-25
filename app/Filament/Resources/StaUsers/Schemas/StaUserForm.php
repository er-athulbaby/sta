<?php

namespace App\Filament\Resources\StaUsers\Schemas;

use App\Enums\Role;
use App\Filament\Shared\UserFields;
use App\Models\StaGroup;
use App\Models\User;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StaUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            UserFields::photo(),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            UserFields::mobile(),
            TextInput::make('password')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->minLength(12)
                ->dehydrated(fn (?string $state) => filled($state)),
            // Only a Super Admin grants or removes Super Admin; the last active one keeps it.
            Select::make('role')->label('Account type')
                ->options([Role::Sta->value => 'STA user', Role::SuperAdmin->value => 'Super Admin (everything, whatever the group)'])
                ->default(Role::Sta->value)->required()->live()
                ->in([Role::Sta->value, Role::SuperAdmin->value])
                ->visible(fn () => (bool) auth()->user()?->isSuperAdmin())
                ->rule(fn (?User $record) => self::keepsLastSuperAdmin($record, fn ($value) => $value === Role::SuperAdmin->value)),
            Select::make('group_id')->label('User group')
                ->options(fn () => StaGroup::query()->orderBy('name')->pluck('name', 'id'))
                ->helperText('What this user may open and do. Each STA user belongs to exactly one group.')
                ->exists(StaGroup::class, 'id')
                ->required(fn (Get $get) => ($get('role') ?? Role::Sta->value) === Role::Sta->value)
                ->hidden(fn (Get $get) => $get('role') === Role::SuperAdmin->value),
            Toggle::make('is_active')->default(true)
                ->rule(fn (?User $record) => self::keepsLastSuperAdmin($record, fn ($value) => (bool) $value)),
        ]);
    }

    /** A rule failing when the change would leave no active Super Admin ($keeps: the new value keeps this one). */
    private static function keepsLastSuperAdmin(?User $record, Closure $keeps): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record, $keeps) {
            if ($record === null || ! $record->isSuperAdmin() || ! $record->is_active || $keeps($value)) {
                return;
            }
            if (! User::withoutGlobalScopes()->whereKeyNot($record->getKey())->where('role', Role::SuperAdmin)->where('is_active', true)->exists()) {
                $fail('This is the last active Super Admin: it cannot be demoted or deactivated.');
            }
        };
    }
}
