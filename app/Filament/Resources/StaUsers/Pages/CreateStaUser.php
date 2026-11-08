<?php

namespace App\Filament\Resources\StaUsers\Pages;

use App\Enums\Role;
use App\Filament\Resources\StaUsers\StaUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaUser extends CreateRecord
{
    protected static string $resource = StaUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'role' => Role::Sta, 'association_id' => null];
    }
}
