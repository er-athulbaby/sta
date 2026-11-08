<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Sta = 'sta';
    case AssocAdmin = 'assoc_admin';
    case AssocEditor = 'assoc_editor';

    public function isAssociationRole(): bool
    {
        return $this === self::AssocAdmin || $this === self::AssocEditor;
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Sta => 'STA User',
            self::AssocAdmin => 'Association Admin',
            self::AssocEditor => 'Association Editor',
        };
    }
}
