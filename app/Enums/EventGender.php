<?php

namespace App\Enums;

enum EventGender: string
{
    case Men = 'men';
    case Women = 'women';
    case Mixed = 'mixed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
