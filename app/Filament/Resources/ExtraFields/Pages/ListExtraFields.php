<?php

namespace App\Filament\Resources\ExtraFields\Pages;

use App\Filament\Resources\ExtraFields\ExtraFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExtraFields extends ListRecords
{
    protected static string $resource = ExtraFieldResource::class;

    /** Canvas list pages have no "X / List" breadcrumb. */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
