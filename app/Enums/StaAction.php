<?php

namespace App\Enums;

/** The columns of the user-group permissions grid. Print = PDFs, Excel and other exports. */
enum StaAction: string
{
    case Access = 'access';
    case New = 'new';
    case Save = 'save';
    case Delete = 'delete';
    case Print = 'print';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
