<?php

namespace App\Filament\Portal\Resources\People\Pages;

use App\Filament\Portal\Resources\People\PersonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPeople extends ListRecords
{
    protected static string $resource = PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
