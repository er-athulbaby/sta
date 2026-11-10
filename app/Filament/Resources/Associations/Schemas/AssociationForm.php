<?php

namespace App\Filament\Resources\Associations\Schemas;

use App\Filament\Shared\AttachmentActions;
use App\Models\Association;
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
            TextInput::make('code')->label('Association code')->required()->maxLength(10)
                ->helperText(fn (?Association $record) => $record?->people()->withoutGlobalScopes()->exists()
                    ? 'Locked because athlete/official codes already use it.'
                    : 'Used in athlete and official codes, e.g. BWF → BWF-AT-0001')
                ->disabled(fn (?Association $record) => (bool) $record?->people()->withoutGlobalScopes()->exists())
                ->dehydrated(fn (?Association $record) => ! $record?->people()->withoutGlobalScopes()->exists())
                ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                ->rule('regex:/^[A-Za-z0-9]{2,10}$/')
                ->unique(ignoreRecord: true),
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
            AttachmentActions::view(FileUpload::make('logo_path')->label('Logo')->image()->acceptedFileTypes(['image/jpeg', 'image/png'])->maxSize(2048)
                ->disk(config('filesystems.private'))->directory('logos')->visibility('private')),
        ];
    }
}
