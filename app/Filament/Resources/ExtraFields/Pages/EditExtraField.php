<?php

namespace App\Filament\Resources\ExtraFields\Pages;

use App\Filament\Resources\ExtraFields\ExtraFieldResource;
use Filament\Resources\Pages\EditRecord;

class EditExtraField extends EditRecord
{
    protected static string $resource = ExtraFieldResource::class;

    // No header actions: extra fields are never deleted, only deactivated (spec 15.1).
}
