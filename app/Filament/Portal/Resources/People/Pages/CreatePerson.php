<?php

namespace App\Filament\Portal\Resources\People\Pages;

use App\Filament\Portal\Resources\People\PersonResource;
use App\Filament\Shared\PersonPageChrome;
use App\Filament\Shared\SavesPersonSizes;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreatePerson extends CreateRecord
{
    use PersonPageChrome;
    use SavesPersonSizes;

    protected static string $resource = PersonResource::class;

    protected function getCreateFormAction(): Action
    {
        // Incomplete people can be saved (spec 13); the Complete/Incomplete badge shows the state (spec 16.6).
        return parent::getCreateFormAction()->label('Save as draft');
    }
}
