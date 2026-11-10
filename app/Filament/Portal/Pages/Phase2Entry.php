<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Portal\Concerns\HandlesSubmission;
use App\Filament\Shared\Phases\EditsPhase2;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class Phase2Entry extends Page
{
    use EditsPhase2, HandlesSubmission;

    protected string $view = 'filament.portal.pages.phase2-entry';

    public static function getRoutePath(Panel $panel): string
    {
        return '/games/{game}/phase-2';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->gameModel->name.' – Phase 2 · Entry by Number';
    }

    public function mount(int|string $game): void
    {
        $this->mountPortal($game, 2);
    }

    protected function getHeaderActions(): array
    {
        return [$this->saveDraftAction(), $this->submitAction(), $this->approveAction(), $this->returnAction()];
    }
}
