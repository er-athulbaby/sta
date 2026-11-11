<?php

namespace App\Filament\Portal\Pages;

use App\Enums\PhaseStatus;
use App\Models\Association;
use App\Models\Game;
use App\Models\GamePhase;
use App\Models\Submission;
use App\Support\Sta;
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

    /** Canvas: "Games" stays the active menu item on every phase page. */
    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [static::getRouteName(), Phase1Entry::getRouteName(), Phase2Entry::getRouteName(),
            Phase3Entry::getRouteName(), Phase3Events::getRouteName(), Phase4Entry::getRouteName()];
    }

    public function getPageClasses(): array
    {
        return ['sta-canvas-page'];
    }

    /** @return Collection<int, array{game: Game, rows: Collection}> */
    public function getGamesProperty(): Collection
    {
        return static::forTenant(Filament::getTenant());
    }

    /** @return Collection<int, array{game: Game, rows: Collection}> published games the association is invited to, with a row per enabled phase */
    public static function forTenant(Association $tenant): Collection
    {
        return Game::whereNotNull('published_at')->whereHas('associations', fn ($q) => $q->whereKey($tenant->getKey()))
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

                $deadline = $p->effectiveDeadline($sub);
                $before = $g->phases->where('enabled', true)->where('phase', '<', $p->phase)->sortBy('phase')->last();
                // Main.dc.html phase-card meta wording.
                $meta = match ($p->status) {
                    PhaseStatus::Open => $deadline ? 'Deadline '.$deadline->format('j M Y').' · '.Sta::daysLeft($deadline) : 'Open',
                    PhaseStatus::Closed => 'Closed'.($deadline ? ' '.$deadline->format('j M') : ''),
                    PhaseStatus::Draft => $before && $before->status !== PhaseStatus::Closed ? "Opens after Phase {$before->phase} closes" : 'Not opened',
                };

                return [
                    'phase' => $p->phase,
                    'meta' => $meta,
                    'name' => GamePhase::NAMES[$p->phase],
                    'status' => Sta::phaseStatus($p)[0], // Open, Closed or Not opened (canvas wording)
                    'pill' => $sub ? Sta::submissionPill($sub->status) : 'none',
                    'deadline' => $deadline,
                    'submission' => $sub?->status->label() ?? 'No response',
                    'url' => $page && $p->status !== PhaseStatus::Draft ? $page::getUrl(['game' => $g->id]) : null,
                ];
            })]);
    }
}
