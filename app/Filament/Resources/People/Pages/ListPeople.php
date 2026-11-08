<?php

namespace App\Filament\Resources\People\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPeople extends ListRecords
{
    protected static string $resource = \App\Filament\Resources\People\PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
