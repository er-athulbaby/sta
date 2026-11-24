<?php

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Portal\Concerns\HasPersonPanel;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\Concerns\StaEditsSubmission;
use App\Filament\Shared\Phases\EditsPhase3;
use Filament\Resources\Pages\Page;

/** STA edit of a Long List, canvas LongList.dc.html screen: Add athlete / Add team official like the portal (HasPersonPanel). */
class StaPhase3Edit extends Page
{
    use EditsPhase3, HasPersonPanel, StaEditsSubmission {
        StaEditsSubmission::save insteadof EditsPhase3;
        EditsPhase3::save as protected saveEntries;
    }

    public const PHASE = 3;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.portal.pages.phase3-entry';
}
