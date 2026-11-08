<?php

namespace App\Filament\Portal\Resources\People\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPeople extends ListRecords
{
    protected static string $resource = \App\Filament\Portal\Resources\People\PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
