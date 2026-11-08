<?php

namespace App\Filament\Resources\StaUsers\Schemas;

use App\Enums\StaPermission;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Permission;

class StaUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->minLength(12)
                ->dehydrated(fn (?string $state) => filled($state)),
            Toggle::make('is_active')->default(true),
            CheckboxList::make('permissions')
                ->relationship(titleAttribute: 'name')
                ->getOptionLabelFromRecordUsing(fn (Permission $record) => StaPermission::from($record->name)->label())
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }
}
