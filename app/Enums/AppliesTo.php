<?php

namespace App\Enums;

enum AppliesTo: string
{
    case Athlete = 'athlete';
    case Official = 'official';
    case Both = 'both';

    public function includes(PersonType $type): bool
    {
        return $this === self::Both || $this->value === $type->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Athlete => 'Athletes', self::Official => 'Team officials', self::Both => 'Athletes and officials'
        };
    }
}
