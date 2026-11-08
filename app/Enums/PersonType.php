<?php

namespace App\Enums;

enum PersonType: string
{
    case Athlete = 'athlete';
    case Official = 'official';

    public function label(): string
    {
        return $this === self::Athlete ? 'Athlete' : 'Team official';
    }
}
