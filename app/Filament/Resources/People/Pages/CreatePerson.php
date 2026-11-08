<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Shared\SavesPersonSizes;
use Filament\Resources\Pages\CreateRecord;

class CreatePerson extends CreateRecord
{
    use SavesPersonSizes;

    protected static string $resource = \App\Filament\Resources\People\PersonResource::class;
}
