<?php

namespace App\Filament\Pages;

use App\Enums\PhaseStatus;
use App\Enums\Role;
use App\Enums\StaPermission;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Associations\AssociationResource;
use App\Filament\Resources\Associations\Tables\AssociationsTable;
use App\Filament\Resources\Games\GameResource;
use App\Models\Association;
use App\Models\Game;
use App\Models\GamePhase;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Reports\Builder\Subjects\People;
use App\Support\GameProgress;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Admin home, canvas AdminDashboard.dc.html: active games, what needs attention, registry health, associations and recent
 * activity. Each band shows only with the permission that guards its data, and the page runs a fixed number of queries.
 */
class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    /** @var Collection<int, array{game: Game, progress: GameProgress, phase: ?GamePhase, closed: ?GamePhase, counts: ?array}>|null */
    protected ?Collection $games = null;

    protected ?array $registry = null;

    public function getPageClasses(): array
    {
        return ['sta-bg-alt', 'sta-canvas-page', 'sta-dashboard-page'];
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.dashboard-header');
    }

    public function getSubheading(): ?string
    {
        return now('Asia/Bahrain')->format('l, j F Y').' · all games and associations';
    }

    #[Unexposed]
    public function allowed(StaPermission $p): bool
    {
        return (bool) auth()->user()?->can($p->value);
    }

    /** Published games that have not ended, soonest first. */
    #[Unexposed]
    public function activeGames(): Collection
    {
        if (! $this->allowed(StaPermission::ViewSubmissions)) {
            return collect();
        }

        return $this->games ??= GameProgress::many(Game::whereNotNull('published_at')->whereDate('end_date', '>=', today())
            ->withCount('gameSports')->orderBy('start_date')->orderBy('id')->get())
            ->map(function (GameProgress $progress) {
                $phase = $progress->openPhase();
                $closed = $progress->game->phases->where('enabled', true)->where('status', PhaseStatus::Closed)
                    ->when($phase, fn ($c) => $c->where('phase', '<', $phase->phase))->sortBy('phase')->last();

                return ['game' => $progress->game, 'progress' => $progress, 'phase' => $phase, 'closed' => $closed,
                    'counts' => $phase ? $progress->counts($phase->phase) : null];
            });
    }

    #[Unexposed]
    public function canRemind(Game $game): bool
    {
        return $game->isPublished() && $this->allowed(StaPermission::SendReminders);
    }

    /**
     * "Needs your attention": deadlines in the next 7 days, associations that have not submitted, flagged entries and pending
     * invitations. ("Comments awaiting your reply" is left out: comments go one way, STA to associations.)
     *
     * @return list<array{icon: string, tone: string, title: string, text: string, pill: ?string, url: ?string, link: ?string, remind: ?int}>
     */
    #[Unexposed]
    public function attention(): array
    {
        $games = $this->activeGames();
        $many = $games->count() > 1;
        $prefix = fn (array $g) => $many ? $g['game']->name.' · ' : '';
        $dashboard = fn (Game $game) => GameResource::getUrl('dashboard', ['record' => $game]);
        $items = [];

        $soon = $games->filter(fn ($g) => $g['phase']?->deadline?->between(now(), now()->addDays(7)))->sortBy(fn ($g) => $g['phase']->deadline);
        if ($soon->isNotEmpty()) {
            $items[] = ['icon' => 'clock', 'tone' => 'warn',
                'title' => $soon->count().' phase '.str('deadline')->plural($soon->count()).' this week',
                'text' => $soon->map(fn ($g) => $g['game']->name.' Phase '.$g['phase']->phase.' '.GamePhase::NAMES[$g['phase']->phase]
                    .' closes '.$g['phase']->deadline->timezone('Asia/Bahrain')->format('D j M'))->implode(' · '),
                'pill' => null, 'url' => $dashboard($soon->first()['game']), 'link' => 'Open', 'remind' => null];
        }

        foreach ($games->filter(fn ($g) => $g['counts']) as $g) {
            $c = $g['counts'];
            $pending = $c['total'] - $c['submitted'];
            if ($pending === 0) {
                continue;
            }
            $notStarted = $g['game']->associations->filter(fn ($a) => ! in_array($g['progress']->status($a, $g['phase']->phase), ['submitted', 'awaiting'], true)
                && ! $g['progress']->started($a, $g['phase']->phase))->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
            $text = $notStarted->isEmpty()
                ? collect([$c['awaiting'] ? $c['awaiting'].' awaiting association admin' : null, $c['draft'] ? $c['draft'].' in draft' : null])->filter()->implode(' · ')
                : $notStarted->count().' not started'.($notStarted->count() > 2 ? ', incl. ' : ': ').$notStarted->take(2)->pluck('name')->implode(' and ');
            $remind = $this->canRemind($g['game']);
            $items[] = ['icon' => 'mail', 'tone' => 'none',
                'title' => $pending.' '.str('association')->plural($pending).' '.($pending === 1 ? 'hasn’t' : 'haven’t').' submitted the '.GamePhase::NAMES[$g['phase']->phase],
                'text' => $prefix($g).$text, 'pill' => null,
                'url' => $remind ? null : $dashboard($g['game']), 'link' => $remind ? "Remind {$pending}" : 'Open', 'remind' => $remind ? $g['game']->id : null];
        }

        foreach ($games->filter(fn ($g) => in_array($g['phase']?->phase, [3, 4], true)) as $g) {
            $flags = $g['game']->associations->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->map(fn ($a) => ['code' => $a->code ?? $a->name, 'n' => (int) $g['progress']->flags($a, $g['phase']->phase)])->where('n', '>', 0);
            if ($flags->isEmpty()) {
                continue;
            }
            $n = $flags->sum('n');
            $items[] = ['icon' => 'flag', 'tone' => 'warn',
                'title' => $n.' '.str('entry')->plural($n).' '.($g['phase']->phase === 3 ? ($n === 1 ? 'differs' : 'differ').' from Phase 2' : 'over the event limit'),
                'text' => $prefix($g).$flags->map(fn ($f) => $f['code'].' ('.$f['n'].')')->implode(', '),
                'pill' => 'Allowed but flagged', 'url' => $dashboard($g['game']), 'link' => 'Review', 'remind' => null];
        }

        if ($this->allowed(StaPermission::ManageAssociations) && ($n = Invitation::pending()->count())) {
            $first = Invitation::pending()->with('association')->oldest('id')->limit(2)->get()
                ->map(fn (Invitation $i) => ($i->association?->name ?? $i->email).' '.match ($i->role) {
                    Role::AssocAdmin => 'admin', Role::AssocEditor => 'editor', default => mb_strtolower($i->role->label()),
                });
            $items[] = ['icon' => 'invite', 'tone' => 'none', 'title' => $n.' '.str('invitation')->plural($n).' pending',
                'text' => $first->implode(' · ').($n > 2 ? ' · '.($n - 2).' more' : ''),
                'pill' => null, 'url' => AssociationResource::getUrl(), 'link' => 'Manage', 'remind' => null];
        }

        return $items;
    }

    /** "Remind N" on an attention item: the game dashboard's Remind pending, for the game's open phase. */
    public function remindAction(): Action
    {
        return Action::make('remind')
            ->requiresConfirmation()
            ->modalHeading('Send reminders')
            ->modalDescription(fn (array $arguments) => ($g = $this->activeGame($arguments)) && $g['phase']
                ? 'Remind every association that hasn’t submitted the '.GamePhase::NAMES[$g['phase']->phase].' of '.$g['game']->name.'?' : null)
            ->modalSubmitActionLabel('Send reminders')
            ->action(function (array $arguments) {
                $g = $this->activeGame($arguments);
                abort_unless($g && $g['phase'] && $this->canRemind($g['game']), 403);
                $n = $g['progress']->remind($g['phase'], $g['game']->associations);
                Notification::make()->success()->title("Reminder sent to {$n} ".str('association')->plural($n))->send();
            });
    }

    private function activeGame(array $arguments): ?array
    {
        return $this->activeGames()->first(fn ($g) => $g['game']->id === (int) ($arguments['game'] ?? 0));
    }

    /**
     * Registry health in one aggregate query (ruling U7): totals, complete (same rule as Person::missingFields()), passports
     * expiring within 6 months, and complete / total per association.
     *
     * @return array{athletes: int, officials: int, complete: int, incomplete: int, expiring: int, perAssociation: Collection, top: Collection}|null
     */
    #[Unexposed]
    public function registry(): ?array
    {
        if (! $this->allowed(StaPermission::ManagePeople)) {
            return null;
        }

        return $this->registry ??= (function () {
            $rows = DB::table('people')->join('associations', 'associations.id', '=', 'people.association_id')
                ->selectRaw('people.association_id as id, associations.code as code, associations.name as name, people.type as type, COUNT(*) as total, '
                    .'SUM(CASE WHEN '.People::completeSql().' THEN 1 ELSE 0 END) as complete, '
                    .'SUM(CASE WHEN people.passport_expiry < ? THEN 1 ELSE 0 END) as expiring', [now()->addMonths(6)->toDateString()])
                ->groupBy('people.association_id', 'associations.code', 'associations.name', 'people.type')->get();
            $per = $rows->groupBy('id')->map(fn ($g) => ['code' => $g[0]->code ?? $g[0]->name, 'complete' => (int) $g->sum('complete'), 'total' => (int) $g->sum('total')]);

            return [
                'athletes' => (int) $rows->where('type', 'athlete')->sum('total'),
                'officials' => (int) $rows->where('type', 'official')->sum('total'),
                'complete' => (int) $rows->sum('complete'),
                'incomplete' => (int) ($rows->sum('total') - $rows->sum('complete')),
                'expiring' => (int) $rows->sum('expiring'),
                'perAssociation' => $per,
                'top' => $per->sortByDesc('total')->take(6)->values(),
            ];
        })();
    }

    /** @return array{total: int, active: int} */
    #[Unexposed]
    public function associationTotals(): array
    {
        $row = Association::query()->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_active THEN 1 ELSE 0 END) as active')->toBase()->first();

        return ['total' => (int) $row->total, 'active' => (int) $row->active];
    }

    /**
     * The 8 associations with the latest activity (a person of theirs changed). People, Complete and Last activity are
     * registry data, shown only with manage_people (ruling U6).
     *
     * @return Collection<int, array{association: Association, admin: string, state: string, editors: int, people: int, pct: ?int, last: ?Carbon}>
     */
    #[Unexposed]
    public function associations(): Collection
    {
        if (! $this->allowed(StaPermission::ManageAssociations)) {
            return collect();
        }
        $per = $this->registry()['perAssociation'] ?? collect();

        return Association::with('users')->withCount('people')->withMax('people', 'updated_at')
            ->withCount(['invitations as admin_invites_count' => fn ($q) => $q->pending()->where('role', Role::AssocAdmin)])
            ->orderByDesc('people_max_updated_at')->orderBy('name')->limit(8)->get()
            ->map(function (Association $a) use ($per) {
                [$admin, $state] = AssociationsTable::adminState($a);
                $row = $per->get($a->id);

                return [
                    'association' => $a, 'admin' => $admin, 'state' => $state, 'editors' => AssociationsTable::editorCount($a),
                    'people' => $a->people_count,
                    'pct' => $row && $row['total'] ? (int) round($row['complete'] / $row['total'] * 100) : null,
                    'last' => $a->people_max_updated_at ? Carbon::parse($a->people_max_updated_at) : null,
                ];
            });
    }

    /** The row's Active switch: the same change as the association edit form's Active toggle, with its permission. */
    public function toggleActive(int $id): void
    {
        abort_unless($this->allowed(StaPermission::ManageAssociations), 403);
        $a = Association::query()->findOrFail($id);
        $a->update(['is_active' => ! $a->is_active]);
    }

    /** @return Collection<int, Activity> */
    #[Unexposed]
    public function recentActivity(): Collection
    {
        if (! $this->allowed(StaPermission::ViewAuditLog)) {
            return collect();
        }

        return Activity::query()->latest('id')->limit(6)
            ->with(['subject', 'causer' => fn (MorphTo $m) => $m->morphWith([User::class => ['association']])])->get();
    }

    #[Unexposed]
    public function auditLogUrl(): string
    {
        return ActivityLogResource::getUrl();
    }

    /** "BWF editor", "STA Super Admin" (canvas Recent activity). */
    #[Unexposed]
    public static function roleLabel(User $u): string
    {
        return match ($u->role) {
            Role::SuperAdmin => 'STA Super Admin',
            Role::Sta => 'STA user',
            Role::AssocAdmin => trim(($u->association?->code ?? '').' admin'),
            Role::AssocEditor => trim(($u->association?->code ?? '').' editor'),
        };
    }

    /** "updated BWF-AT-0012": the event and the record's code, name or reference (never any changed values). */
    #[Unexposed]
    public static function activityText(Activity $e): string
    {
        $s = $e->subject;
        $label = $s?->getAttribute('code') ?? $s?->getAttribute('reference_no') ?? $s?->getAttribute('name')
            ?? ($e->subject_type ? str(class_basename($e->subject_type))->snake(' ').($e->subject_id ? ' #'.$e->subject_id : '') : '');

        return trim($e->description.' '.$label);
    }
}
