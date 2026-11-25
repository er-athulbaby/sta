<?php

namespace App\Support;

use App\Filament\Portal\Pages\Games;
use App\Models\Association;
use App\Models\Comment;
use App\Models\Game;
use App\Models\GamePhase;
use App\Models\Person;
use App\Services\Reports\Builder\Sql;
use App\Services\Reports\Builder\Subjects\People;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The "General dashboard" tab of the admin home and the portal home: registry figures of ACTIVE people (one grouped aggregate
 * query), a random photo cluster, nationalities, passports expiring soon, athletes by sport and the progress of one game.
 * $association scopes everything to one association (the portal always passes its tenant). No medical column is ever read.
 */
final class GeneralDashboard
{
    public const AGE_GROUPS = ['u18' => 'Under 18', 'a18' => '18–25', 'a26' => '26–35', 'a36' => '36–50', 'a50' => '50+'];

    private ?array $people = null;

    private ?Collection $games = null;

    public function __construct(
        public readonly ?int $association,
        public readonly string $type = 'all', // all | athlete | official
        private readonly ?int $gameId = null,
    ) {}

    /** Active people of the scope and type. */
    private function query(): Builder
    {
        return Person::query()->where('people.is_active', true)
            ->when($this->association, fn (Builder $q) => $q->where('people.association_id', $this->association))
            ->when(in_array($this->type, ['athlete', 'official'], true), fn (Builder $q) => $q->where('people.type', $this->type));
    }

    /**
     * Totals, gender, age groups, complete and expiring in one query grouped by association and type.
     *
     * @return array{total: int, athletes: int, officials: int, complete: int, male: int, female: int, ages: array<string, int>, unknown: int, expiring: int, perAssociation: Collection}
     */
    public function people(): array
    {
        return $this->people ??= (function () {
            $age = Sql::age('people.dob', Sql::today());
            $sum = fn (string $when) => "SUM(CASE WHEN {$when} THEN 1 ELSE 0 END)";
            $rows = $this->query()->join('associations', 'associations.id', '=', 'people.association_id')
                ->selectRaw('people.association_id as id, associations.code as code, associations.name as name, people.type as type, COUNT(*) as total, '
                    .$sum(People::completeSql()).' as complete, '
                    .$sum("people.gender = 'male'").' as male, '.$sum("people.gender = 'female'").' as female, '
                    .$sum('people.dob IS NULL').' as unknown, '
                    .$sum("people.dob IS NOT NULL AND {$age} < 18").' as u18, '
                    .$sum("people.dob IS NOT NULL AND {$age} BETWEEN 18 AND 25").' as a18, '
                    .$sum("people.dob IS NOT NULL AND {$age} BETWEEN 26 AND 35").' as a26, '
                    .$sum("people.dob IS NOT NULL AND {$age} BETWEEN 36 AND 50").' as a36, '
                    .$sum("people.dob IS NOT NULL AND {$age} > 50").' as a50, '
                    // Same predicate as the People page's "Expiring passports" filter.
                    .$sum('people.passport_expiry < ?').' as expiring', [now()->addMonths(6)->toDateString()])
                ->groupBy('people.association_id', 'associations.code', 'associations.name', 'people.type')->toBase()->get();
            $int = fn (string $col, ?string $type = null) => (int) ($type ? $rows->where('type', $type) : $rows)->sum($col);

            return [
                'total' => $int('total'), 'athletes' => $int('total', 'athlete'), 'officials' => $int('total', 'official'),
                'complete' => $int('complete'), 'male' => $int('male'), 'female' => $int('female'), 'unknown' => $int('unknown'),
                'ages' => collect(self::AGE_GROUPS)->map(fn ($label, $key) => $int($key))->all(),
                'expiring' => $int('expiring'),
                'perAssociation' => $rows->groupBy('id')->map(fn ($g) => ['code' => $g[0]->code ?? Sta::initials($g[0]->name), 'name' => $g[0]->name,
                    'complete' => (int) $g->sum('complete'), 'total' => (int) $g->sum('total')])->sortByDesc('total')->values(),
            ];
        })();
    }

    /** Up to $n random people, those with a photo first (re-picked on every render). */
    public function cluster(int $n = 8): Collection
    {
        return $this->query()->orderByRaw('people.photo_path IS NULL')->inRandomOrder()->limit($n)
            ->get(['id', 'association_id', 'type', 'first_name', 'second_name', 'third_name', 'last_name', 'photo_path']);
    }

    /** @return array{top: Collection<string, int>, other: int} the 5 commonest nationalities and the rest */
    public function nationalities(): array
    {
        $rows = $this->query()->whereNotNull('nationality')->where('nationality', '<>', '')
            ->selectRaw('nationality, COUNT(*) as n')->groupBy('nationality')->orderByDesc('n')->orderBy('nationality')->toBase()->get();

        return ['top' => $rows->take(5)->pluck('n', 'nationality')->map(fn ($n) => (int) $n), 'other' => (int) $rows->slice(5)->sum('n')];
    }

    /** The next 5 passports to expire (expired ones first), within 6 months. */
    public function expiring(): Collection
    {
        return $this->query()->whereNotNull('passport_expiry')->where('passport_expiry', '<', now()->addMonths(6)->toDateString())
            ->with('association:id,code,name')->orderBy('passport_expiry')->limit(5)
            ->get(['id', 'association_id', 'type', 'first_name', 'second_name', 'third_name', 'last_name', 'passport_expiry']);
    }

    /** Published games that have not ended (the association's own for the portal), soonest first like the Games dashboard. */
    public function games(): Collection
    {
        return $this->games ??= Game::whereNotNull('published_at')->whereDate('end_date', '>=', today())
            ->when($this->association, fn ($q) => $q->whereHas('associations', fn ($a) => $a->whereKey($this->association)))
            ->orderBy('start_date')->orderBy('id')->get();
    }

    public function game(): ?Game
    {
        return $this->games()->firstWhere('id', $this->gameId) ?? $this->games()->first();
    }

    /** Distinct athletes per sport on the chosen game's Long List (Phase 3 entries, any status). @return Collection<string, int> */
    public function athletesBySport(): Collection
    {
        if (! $game = $this->game()) {
            return collect();
        }

        return DB::table('entries')->join('submissions', 'submissions.id', '=', 'entries.submission_id')
            ->join('game_sports', 'game_sports.id', '=', 'entries.game_sport_id')->join('sports', 'sports.id', '=', 'game_sports.sport_id')
            ->where('submissions.game_id', $game->id)->where('submissions.phase', 3)->whereNotNull('entries.game_event_id')
            ->when($this->association, fn ($q) => $q->where('submissions.association_id', $this->association))
            ->selectRaw('sports.name as name, COUNT(DISTINCT entries.person_id) as n')->groupBy('sports.name')
            ->orderByDesc('n')->orderBy('sports.name')->pluck('n', 'name')->map(fn ($n) => (int) $n);
    }

    /**
     * STA: the chosen game's current open phase with its association counts, pending remarks and over-limit entries.
     *
     * @return array{phase: GamePhase, counts: array, remarks: int, flagged: ?int}|null
     */
    public function progress(): ?array
    {
        if (! $game = $this->game()) {
            return null;
        }
        $progress = new GameProgress($game);
        if (! $phase = $progress->openPhase()) {
            return null;
        }
        $flagged = in_array($phase->phase, [3, 4], true)
            ? $game->associations->filter(fn (Association $a) => $progress->flags($a, $phase->phase))->count() : null;

        return ['phase' => $phase, 'counts' => $progress->counts($phase->phase), 'remarks' => $this->pendingRemarks($game, $phase->phase), 'flagged' => $flagged];
    }

    /** Submissions of the phase whose latest remark is STA's (awaiting the association's reply). */
    public function pendingRemarks(Game $game, int $phase): int
    {
        return Comment::query()->whereIn('id', Comment::query()->selectRaw('MAX(id)')->groupBy('submission_id'))->where('from_association', false)
            ->whereHas('submission', fn ($q) => $q->where('game_id', $game->id)->where('phase', $phase)
                ->when($this->association, fn ($s) => $s->where('association_id', $this->association)))->count();
    }

    /** Portal: the association's phase rows (Games::forTenant) of each active game. */
    public function entries(): Collection
    {
        $tenant = Association::query()->find($this->association);

        return $tenant ? Games::forTenant($tenant)->filter(fn ($item) => $this->games()->contains('id', $item['game']->id))->values() : collect();
    }
}
