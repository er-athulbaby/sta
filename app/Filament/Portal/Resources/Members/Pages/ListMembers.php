<?php

namespace App\Filament\Portal\Resources\Members\Pages;

use App\Enums\Role;
use App\Filament\Portal\Resources\Members\MemberResource;
use App\Filament\Shared\InvitationActions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    /** Canvas list pages have no "X / List" breadcrumb. */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [InvitationActions::invite('inviteEditor', Role::AssocEditor, association: fn () => Filament::getTenant())];
    }
}
