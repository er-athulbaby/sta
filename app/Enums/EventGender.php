<?php

namespace App\Enums;

enum EventGender: string
{
    case Men = 'men';
    case Women = 'women';
    case Mixed = 'mixed';

    public function label(): string
    {
        // Stored values stay men/women/mixed; spec 16.1 shows Male/Female/Mixed everywhere.
        return match ($this) {
            self::Men => 'Male',
            self::Women => 'Female',
            self::Mixed => 'Mixed',
        };
    }
}
