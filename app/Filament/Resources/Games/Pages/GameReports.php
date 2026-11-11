<?php

namespace App\Filament\Resources\Games\Pages;

use App\Enums\StaPermission;
use App\Filament\Concerns\ShowsReadyMadeReports;
use App\Filament\Resources\Games\GameResource;
use App\Models\Game;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/** One game's ready-made reports (linked from the games list and the game dashboard): the same band as the Reports page. */
class GameReports extends Page
{
    use InteractsWithRecord;
    use ShowsReadyMadeReports;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.resources.games.pages.game-reports';

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can(StaPermission::ViewReports->value);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return $this->record->name.' - reports';
    }

    public function getPageClasses(): array
    {
        return ['sta-canvas-page'];
    }

    protected function reportGame(): ?Game
    {
        return $this->record;
    }
}
