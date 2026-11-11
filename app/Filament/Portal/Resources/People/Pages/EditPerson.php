<?php

namespace App\Filament\Portal\Resources\People\Pages;

use App\Filament\Portal\Resources\People\PersonResource;
use App\Filament\Shared\PersonPageChrome;
use App\Filament\Shared\SavesPersonSizes;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditPerson extends EditRecord
{
    use PersonPageChrome;
    use SavesPersonSizes;

    protected static string $resource = PersonResource::class;

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Save as draft');
    }
}
