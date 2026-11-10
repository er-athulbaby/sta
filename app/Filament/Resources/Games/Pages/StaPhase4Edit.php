<?php

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\Concerns\StaEditsSubmission;
use App\Filament\Shared\Phases\EditsPhase4;
use Filament\Resources\Pages\Page;

class StaPhase4Edit extends Page
{
    use EditsPhase4, StaEditsSubmission {
        StaEditsSubmission::save insteadof EditsPhase4;
        EditsPhase4::save as protected saveEntries;
    }

    public const PHASE = 4;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.portal.pages.phase4-entry';
}
