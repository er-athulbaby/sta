<?php

namespace App\Filament\Resources\StaUsers\Pages;

use App\Filament\Resources\StaUsers\StaUserResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListStaUsers extends ListRecords
{
    protected static string $resource = StaUserResource::class;

    /** Canvas list pages have no "X / List" breadcrumb. */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** "Users & groups" header with the Groups / Users tabs and New user. */
    public function getHeader(): ?View
    {
        return view('filament.resources.sta-users.header', ['tab' => 'users']);
    }
}
