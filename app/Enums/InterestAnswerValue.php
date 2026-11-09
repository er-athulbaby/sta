<?php

namespace App\Enums;

enum InterestAnswerValue: string
{
    case Yes = 'yes';
    case No = 'no';
    case Maybe = 'maybe';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
