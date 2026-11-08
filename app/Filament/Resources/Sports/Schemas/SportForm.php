<?php

namespace App\Filament\Resources\Sports\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('association_id')->relationship('association', 'name')->searchable()->preload()->required(),
            TextInput::make('name')->required()->maxLength(255)->unique(ignoreRecord: true),
            Toggle::make('is_active')->default(true),
        ]);
    }
}
