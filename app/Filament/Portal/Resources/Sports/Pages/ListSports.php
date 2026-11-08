<?php

namespace App\Filament\Portal\Resources\Sports\Pages;

use App\Filament\Portal\Resources\Sports\SportResource;
use Filament\Resources\Pages\ListRecords;

class ListSports extends ListRecords
{
    protected static string $resource = SportResource::class;
}
