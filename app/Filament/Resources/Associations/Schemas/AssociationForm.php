<?php

namespace App\Filament\Resources\Associations\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AssociationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            ...self::profileFields(),
            // Associations are never deleted, only deactivated (blocks portal access and new invitations).
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    /** Optional profile fields, shared with the portal profile page. */
    public static function profileFields(): array
    {
        return [
            TextInput::make('phone')->tel()->maxLength(50),
            TextInput::make('website')->url()->maxLength(255),
            TextInput::make('address')->maxLength(255)->columnSpanFull(),
            FileUpload::make('logo_path')->label('Logo')->image()->acceptedFileTypes(['image/jpeg', 'image/png'])->maxSize(2048)
                ->disk(config('filesystems.private'))->directory('logos')->visibility('private'),
        ];
    }
}
