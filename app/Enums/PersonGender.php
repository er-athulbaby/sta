<?php

namespace App\Enums;

enum PersonGender: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
