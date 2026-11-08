<?php

namespace App\Filament\Resources\StaUsers\Pages;

use App\Filament\Resources\StaUsers\StaUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStaUsers extends ListRecords
{
    protected static string $resource = StaUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
