<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Portal\Concerns\HandlesSubmission;
use App\Filament\Shared\Phases\EditsPhase4;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class Phase4Entry extends Page
{
    use EditsPhase4, HandlesSubmission;

    protected string $view = 'filament.portal.pages.phase4-entry';

    public static function getRoutePath(Panel $panel): string
    {
        return '/games/{game}/phase-4';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->gameModel->name.' – Phase 4 · Short List';
    }

    public function mount(int|string $game): void
    {
        $this->mountPortal($game, 4);
    }

    protected function getHeaderActions(): array
    {
        return [$this->submitAction()->visible(fn () => $this->readOnlyReason === null && $this->longListSubmitted()), $this->approveAction(), $this->returnAction()];
    }
}
