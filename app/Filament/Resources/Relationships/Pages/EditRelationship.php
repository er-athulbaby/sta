<?php

namespace App\Filament\Resources\Relationships\Pages;

use App\Filament\Resources\Relationships\RelationshipResource;
use Filament\Resources\Pages\EditRecord;

class EditRelationship extends EditRecord
{
    protected static string $resource = RelationshipResource::class;

    // No header actions: relationships are only deactivated (like clothing items).
}
