<?php

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\Concerns\StaEditsSubmission;
use App\Filament\Shared\Phases\EditsPhase3;
use Filament\Resources\Pages\Page;

class StaPhase3Edit extends Page
{
    use EditsPhase3, StaEditsSubmission {
        StaEditsSubmission::save insteadof EditsPhase3;
        EditsPhase3::save as protected saveEntries;
    }

    public const PHASE = 3;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.portal.pages.phase3-entry';
}
