<?php

namespace App\Filament\Portal\Pages;

use App\Enums\PhaseStatus;
use App\Models\Game;
use App\Models\GamePhase;
use App\Models\Submission;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class Games extends Page
{
    protected string $view = 'filament.portal.pages.games';

    protected static ?string $navigationLabel = 'Games';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?int $navigationSort = 1;

    /** @return Collection<int, array{game: Game, rows: Collection}> */
    public function getGamesProperty(): Collection
    {
        $tenant = Filament::getTenant();

        return Game::whereHas('associations', fn ($q) => $q->whereKey($tenant->getKey()))
            ->with('phases')->orderBy('start_date')->get()
            ->map(fn (Game $g) => ['game' => $g, 'rows' => $g->phases->where('enabled', true)->sortBy('phase')->map(function (GamePhase $p) use ($g, $tenant) {
                $sub = Submission::withoutGlobalScope('association')
                    ->where(['game_id' => $g->id, 'association_id' => $tenant->id, 'phase' => $p->phase])->first();
                $page = match ($p->phase) {
                    1 => Phase1Entry::class,
                    2 => Phase2Entry::class,
                    3 => Phase3Entry::class,
                    4 => Phase4Entry::class,
                    default => null,
                };

                return [
                    'phase' => $p->phase,
                    'name' => GamePhase::NAMES[$p->phase],
                    'status' => ucfirst($p->status->value),
                    'deadline' => $p->effectiveDeadline($sub),
                    'submission' => $sub?->status->label() ?? 'No response',
                    'url' => $page && $p->status !== PhaseStatus::Draft ? $page::getUrl(['game' => $g->id]) : null,
                ];
            })]);
    }
}
