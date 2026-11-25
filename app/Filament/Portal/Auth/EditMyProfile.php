<?php

namespace App\Filament\Portal\Auth;

use App\Filament\Shared\EditUserProfile;

/**
 * Portal "Profile" in the user menu: every association user edits their own photo, name, email, mobile and password (Filament's profile
 * page, current password required to change email or password). Its own slug keeps portal/{tenant}/profile for the
 * association profile.
 */
class EditMyProfile extends EditUserProfile
{
    protected static ?string $slug = 'my-profile';
}
