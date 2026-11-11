<?php

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Portal\Concerns\CanvasPhaseHeader;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\Concerns\StaEditsSubmission;
use App\Filament\Shared\Phases\EditsPhase1;
use Filament\Resources\Pages\Page;

class StaPhase1Edit extends Page
{
    use CanvasPhaseHeader, EditsPhase1, StaEditsSubmission {
        StaEditsSubmission::save insteadof EditsPhase1;
        EditsPhase1::save as protected saveEntries;
    }

    public const PHASE = 1;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.portal.pages.phase1-entry';
}
