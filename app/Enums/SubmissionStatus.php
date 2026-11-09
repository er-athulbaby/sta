<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Draft = 'draft';
    case AwaitingAssocAdmin = 'awaiting_assoc_admin';
    case Submitted = 'submitted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::AwaitingAssocAdmin => 'Awaiting association admin',
            self::Submitted => 'Submitted',
        };
    }
}
