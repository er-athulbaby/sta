<?php

namespace App\Filament\Resources\Associations\Pages;

use App\Filament\Resources\Associations\AssociationResource;
use Filament\Resources\Pages\EditRecord;

class EditAssociation extends EditRecord
{
    protected static string $resource = AssociationResource::class;

    // No header actions: associations are never deleted (BR-16).
}
