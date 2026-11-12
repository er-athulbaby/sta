<?php

namespace App\Filament\Portal\Resources\Invitations\Pages;

use App\Filament\Portal\Resources\Invitations\InvitationResource;
use Filament\Resources\Pages\ListRecords;

class ListInvitations extends ListRecords
{
    protected static string $resource = InvitationResource::class;

    /** Canvas list pages have no "X / List" breadcrumb. */
    public function getBreadcrumbs(): array
    {
        return [];
    }
}
