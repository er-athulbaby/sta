<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Portal\Concerns\HandlesSubmission;
use App\Filament\Portal\Concerns\HasPersonPanel;
use App\Filament\Shared\Phases\EditsPhase3;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

/** Canvas LongList.dc.html / LongListGrid.dc.html; the event assignment is Phase3Events (LongListEvents.dc.html). */
class Phase3Entry extends Page
{
    use EditsPhase3, HandlesSubmission, HasPersonPanel;

    protected string $view = 'filament.portal.pages.phase3-entry';

    public static function getRoutePath(Panel $panel): string
    {
        return '/games/{game}/phase-3';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->gameModel->name.' – Phase 3 · Long List';
    }

    public function mount(int|string $game): void
    {
        $this->mountPortal($game, 3);
    }

    protected function getHeaderActions(): array
    {
        return [$this->saveDraftAction(), $this->submitAction(), $this->approveAction(), $this->returnAction()];
    }
}
