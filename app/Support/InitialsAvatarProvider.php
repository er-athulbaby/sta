<?php

namespace App\Support;

use App\Models\Association;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/** Canvas-style initials circles, drawn locally (Filament's default asks ui-avatars.com). */
final class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        if ($record instanceof Association && filled($record->code)) {
            return self::svg($record->code, Sta::avatarColour($record->code));
        }

        return self::svg(Sta::initials(Filament::getNameForDefaultAvatar($record)));
    }

    public static function svg(string $text, string $fill = '#2B4C7E'): string
    {
        $size = mb_strlen($text) > 2 ? 18 : 23; // 23/64 of a 36px circle = the canvas 13px
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" rx="32" fill="'.$fill.'"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" font-family="Inter, Arial, sans-serif" font-size="'.$size.'" font-weight="600" fill="#FFFFFF">'.e($text).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
