<?php

namespace App\Filament\Portal\Resources\People\Pages;

use App\Filament\Portal\Resources\People\PersonResource;
use App\Filament\Shared\SavesPersonSizes;
use Filament\Resources\Pages\CreateRecord;

class CreatePerson extends CreateRecord
{
    use SavesPersonSizes;

    protected static string $resource = PersonResource::class;
}
