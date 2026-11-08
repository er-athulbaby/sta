<?php

namespace App\Filament\Portal\Pages;

use App\Enums\Role;
use App\Filament\Resources\Associations\Schemas\AssociationForm;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditAssociationProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Association profile';
    }

    public static function canView(Model $tenant): bool
    {
        return auth()->user()?->role === Role::AssocAdmin;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->disabled()->dehydrated(false),
            TextInput::make('email')->disabled()->dehydrated(false),
            ...AssociationForm::profileFields(),
        ]);
    }
}
