<?php

namespace App\Filament\Shared;

use Filament\Auth\Pages\EditProfile;
use Filament\Schemas\Schema;

/**
 * Own profile of every user (admin panel "Profile"; the portal extends it as EditMyProfile): photo, name, email, mobile,
 * then Filament's password fields (current password required to change email or password).
 */
class EditUserProfile extends EditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            UserFields::photo(),
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            UserFields::mobile(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }
}
