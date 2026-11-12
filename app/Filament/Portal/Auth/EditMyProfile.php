<?php

namespace App\Filament\Portal\Auth;

use Filament\Auth\Pages\EditProfile;

/**
 * Portal "Profile" in the user menu: every association user edits their own name, email and password (Filament's profile
 * page, current password required to change email or password). Its own slug keeps portal/{tenant}/profile for the
 * association profile.
 */
class EditMyProfile extends EditProfile
{
    protected static ?string $slug = 'my-profile';
}
