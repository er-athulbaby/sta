<?php

namespace App\Filament\Portal\Resources\People\Pages;

use App\Filament\Shared\SavesPersonSizes;
use Filament\Resources\Pages\EditRecord;

class EditPerson extends EditRecord
{
    use SavesPersonSizes;

    protected static string $resource = \App\Filament\Portal\Resources\People\PersonResource::class;
}
