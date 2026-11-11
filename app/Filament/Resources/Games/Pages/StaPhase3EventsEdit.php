<?php

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\Concerns\StaEditsSubmission;
use App\Filament\Shared\Phases\EditsPhase3;
use Filament\Resources\Pages\Page;

/** STA edit of a Long List, canvas LongListEvents.dc.html screen (StaPhase3Edit is the LongList.dc.html screen). */
class StaPhase3EventsEdit extends Page
{
    use EditsPhase3, StaEditsSubmission {
        StaEditsSubmission::save insteadof EditsPhase3;
        EditsPhase3::save as protected saveEntries;
    }

    public const PHASE = 3;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.portal.pages.phase3-events';
}
