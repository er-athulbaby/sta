<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

/** No canvas screen: titled like the menu item, without a breadcrumb, as the canvas list screens are. */
class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected ?string $heading = 'Audit log';

    protected ?string $subheading = 'Every change with who and when · medical changes are marked, values are never shown';

    public function getTitle(): string
    {
        return 'Audit log';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'sta-canvas-page'];
    }
}
