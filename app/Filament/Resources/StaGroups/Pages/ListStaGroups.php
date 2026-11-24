<?php

namespace App\Filament\Resources\StaGroups\Pages;

use App\Filament\Resources\StaGroups\StaGroupResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListStaGroups extends ListRecords
{
    protected static string $resource = StaGroupResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** "Users & groups" header with the Groups / Users tabs and New group. */
    public function getHeader(): ?View
    {
        return view('filament.resources.sta-users.header', ['tab' => 'groups']);
    }
}
