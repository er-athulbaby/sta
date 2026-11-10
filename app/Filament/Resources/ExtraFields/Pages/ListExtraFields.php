<?php

namespace App\Filament\Resources\ExtraFields\Pages;

use App\Filament\Resources\ExtraFields\ExtraFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExtraFields extends ListRecords
{
    protected static string $resource = ExtraFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
