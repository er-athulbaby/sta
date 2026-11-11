<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use App\Filament\Shared\PersonPageChrome;
use App\Filament\Shared\SavesPersonSizes;
use Filament\Resources\Pages\CreateRecord;

class CreatePerson extends CreateRecord
{
    use PersonPageChrome;
    use SavesPersonSizes;

    protected static string $resource = PersonResource::class;
}
