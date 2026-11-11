<?php

namespace App\Support;

/** Canvas design tokens and small display helpers shared by Blade views and PDFs (spec 17). */
final class Sta
{
    public const FONTS_URL = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap';

    /** Association colours of the canvas (docs/design/canvas/project/Main.dc.html, the LC map). */
    public const AVATAR_COLOURS = ['#9A3412', '#3730A3', '#15803D', '#86198F', '#0F766E', '#BE185D', '#374151', '#854D0E', '#4D7C0F', '#0369A1'];

    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);

        return mb_strtoupper(implode('', array_map(fn (string $w) => mb_substr($w, 0, 1), array_slice($words, 0, 2)))) ?: '?';
    }

    public static function avatarColour(string $key): string
    {
        return self::AVATAR_COLOURS[crc32($key) % count(self::AVATAR_COLOURS)];
    }
}
