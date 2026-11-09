<?php

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\Concerns\StaEditsSubmission;
use App\Filament\Shared\Phases\EditsPhase2;
use Filament\Resources\Pages\Page;

class StaPhase2Edit extends Page
{
    use EditsPhase2, StaEditsSubmission {
        StaEditsSubmission::save insteadof EditsPhase2;
        EditsPhase2::save as protected saveEntries;
    }

    public const PHASE = 2;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.portal.pages.phase2-entry';
}
