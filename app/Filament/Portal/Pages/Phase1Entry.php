<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Portal\Concerns\CanvasPhaseHeader;
use App\Filament\Portal\Concerns\HandlesSubmission;
use App\Filament\Shared\Phases\EditsPhase1;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class Phase1Entry extends Page
{
    use CanvasPhaseHeader, EditsPhase1, HandlesSubmission;

    protected string $view = 'filament.portal.pages.phase1-entry';

    public static function getRoutePath(Panel $panel): string
    {
        return '/games/{game}/phase-1';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->gameModel->name.' – Phase 1 · Entry by Sport';
    }

    public function mount(int|string $game): void
    {
        $this->mountPortal($game, 1);
    }

    protected function getHeaderActions(): array
    {
        return [$this->saveDraftAction(), $this->submitAction(), $this->approveAction(), $this->returnAction()];
    }
}
