<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use App\Filament\Shared\SavesPersonSizes;
use Filament\Resources\Pages\EditRecord;

class EditPerson extends EditRecord
{
    use SavesPersonSizes;

    protected static string $resource = PersonResource::class;
}
