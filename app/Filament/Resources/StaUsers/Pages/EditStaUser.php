<?php

namespace App\Filament\Resources\StaUsers\Pages;

use App\Filament\Resources\StaUsers\StaUserResource;
use Filament\Resources\Pages\EditRecord;

/** No delete: a hard delete would orphan audit-log causers. Deactivate with is_active instead. */
class EditStaUser extends EditRecord
{
    protected static string $resource = StaUserResource::class;
}
