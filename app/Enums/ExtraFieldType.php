<?php

namespace App\Enums;

enum ExtraFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Choice = 'choice';
    case YesNo = 'yes_no';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text', self::Number => 'Number', self::Date => 'Date',
            self::Choice => 'Choice (list of options)', self::YesNo => 'Yes/No',
        };
    }
}
