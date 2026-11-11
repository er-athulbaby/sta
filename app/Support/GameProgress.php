<?php

namespace App\Support;

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\Entry;
use App\Models\Game;
use App\Models\GamePhase;
use App\Models\InterestAnswer;
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Submission;
use App\Notifications\PhaseReminder;
use App\Services\Phases\LongListChecks;
use App\Services\Phases\Recipients;
use App\Services\Phases\ShortListChecks;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Status of one game's submissions (spec 17 game dashboard, admin home). Never creates submissions.
 * Loads phases, invited associations, game sports and every submission once; flags are memoised per association (ruling U3).
 * many() does the same for several games at once, so the admin home costs the same queries for any number of games.
 */
final class GameProgress
{
    private const RELATIONS = ['phases', 'associations', 'gameSports.sport', 'gameSports.gameEvents'];

    /** @var Collection<string, Submission> keyed "associationId-phase" */
    private Collection $subs;

    /** @var array<int, array<int, string>> phase => association id => flag */
    private array $flags = [];

    /**
     * @param  Collection<int, Submission>|null  $subs  this game's submissions, when many() loaded them
     * @param  array{entries: Collection, numbers: Collection, people: Collection, no: Collection}|null  $shared  the rows flags() needs, loaded by many() for all its games
     */
    public function __construct(public readonly Game $game, ?Collection $subs = null, private readonly ?array $shared = null)
    {
        $game->loadMissing(self::RELATIONS);
        $this->subs = ($subs ?? self::submissions([$game->id]))->keyBy(fn (Submission $s) => "{$s->association_id}-{$s->phase}");
    }

    /** @return EloquentCollection<int, Submission> */
    private static function submissions(array $gameIds): EloquentCollection
    {
        return Submission::withoutGlobalScopes()->whereIn('game_id', $gameIds)->withExists(['answers', 'numbers', 'entries'])->get();
    }

    /**
     * One GameProgress per game; relations, submissions and the flag rows are loaded once for all of them.
     *
     * @param  EloquentCollection<int, Game>  $games
     * @return Collection<int, self>
     */
    public static function many(EloquentCollection $games): Collection
    {
        $games->loadMissing(self::RELATIONS);
        $subs = self::submissions($games->modelKeys());
        $entries = Entry::query()->whereIn('submission_id', $subs->whereIn('phase', [3, 4])->pluck('id'))->orderBy('id')->get();
        $shared = [
            'entries' => $entries->groupBy('submission_id'),
            'numbers' => NumberEntry::query()->whereIn('submission_id', $subs->where('phase', 2)->where('status', SubmissionStatus::Submitted)->pluck('id'))
                ->get()->groupBy('submission_id'),
            'people' => Person::withoutGlobalScopes()->whereKey($entries->pluck('person_id')->unique()->all())->get(['id', 'association_id', 'gender']),
            'no' => InterestAnswer::query()->whereIn('submission_id', $subs->where('phase', 1)->pluck('id'))->where('answer', 'no')
                ->get(['submission_id', 'game_sport_id']),
        ];
        $byGame = $subs->groupBy('game_id');

        return $games->map(fn (Game $g) => new self($g, $byGame->get($g->id, collect()), $shared))->values();
    }

    /** Sends the phase reminder to each association that has not submitted it; returns how many were reminded. */
    public function remind(GamePhase $phase, Collection $associations): int
    {
        $n = 0;
        foreach ($associations as $a) {
            if ($this->status($a, $phase->phase) === 'submitted') {
                continue;
            }
            $deadline = $phase->effectiveDeadline($this->submission($a, $phase->phase));
            Notification::send(Recipients::association($a), new PhaseReminder($phase, $a, $deadline !== null && now()->greaterThan($deadline)));
            $n++;
        }

        return $n;
    }

    public function submission(Association $a, int $phase): ?Submission
    {
        return $this->subs->get("{$a->id}-{$phase}");
    }

    /** submitted | awaiting | overdue | draft | no_response: the rule the game dashboard has always used. */
    public function status(Association $a, int $phase): string
    {
        $s = $this->submission($a, $phase);
        $deadline = $this->game->phase($phase)->effectiveDeadline($s);

        return match (true) {
            $s?->status === SubmissionStatus::Submitted => 'submitted',
            $s?->status === SubmissionStatus::AwaitingAssocAdmin => 'awaiting',
            $deadline !== null && now()->greaterThan($deadline) => 'overdue',
            $this->started($a, $phase) => 'draft',
            default => 'no_response',
        };
    }

    /** The submission has content (answers, numbers or entries). */
    public function started(Association $a, int $phase): bool
    {
        $s = $this->submission($a, $phase);

        return $s !== null && ($s->answers_exists || $s->numbers_exists || $s->entries_exists);
    }

    /**
     * KPI counts. Overdue rows are also counted as draft (started) or no_response (not started), so the four tiles add up to total.
     *
     * @return array{submitted: int, awaiting: int, draft: int, overdue: int, no_response: int, total: int}
     */
    public function counts(int $phase): array
    {
        $c = array_fill_keys(['submitted', 'awaiting', 'draft', 'overdue', 'no_response'], 0);
        foreach ($this->game->associations as $a) {
            $status = $this->status($a, $phase);
            if ($status === 'overdue') {
                $c['overdue']++;
                $status = $this->started($a, $phase) ? 'draft' : 'no_response';
            }
            $c[$status]++;
        }

        return $c + ['total' => $this->game->associations->count()];
    }

    /** @return Collection<int, GamePhase> enabled phases that are open, in order */
    public function openPhases(): Collection
    {
        return $this->game->phases->where('enabled', true)->where('status', PhaseStatus::Open)->sortBy('phase')->values();
    }

    public function openPhase(): ?GamePhase
    {
        return $this->openPhases()->first();
    }

    public function lastActivity(Association $a): ?CarbonInterface
    {
        return $this->subs->where('association_id', $a->id)->max('updated_at');
    }

    /** The association's sports in this game, "Judo · Karate" (canvas row subtitle). */
    public function sportsOf(Association $a): string
    {
        return $this->game->gameSports->filter(fn ($gs) => $gs->sport?->association_id === $a->id)
            ->map(fn ($gs) => $gs->sport->name)->sort()->implode(' · ');
    }

    /**
     * What STA should look at for one phase (spec 17 "Flags"): Long List events above the submitted Phase 2 number, or Short List maximums exceeded.
     */
    public function flags(Association $a, int $phase): ?string
    {
        $this->flags[$phase] ??= $this->flagsFor($phase);

        return $this->flags[$phase][$a->id] ?? null;
    }

    /**
     * Every association's flag for one phase in a fixed number of queries (ruling U3). Same rules as
     * LongListChecks::visible()/selection()/phase2Numbers() and ShortListChecks::overLimits(), over batch-loaded rows.
     *
     * @return array<int, string> association id => flag
     */
    private function flagsFor(int $phase): array
    {
        $subs = $this->subs->where('phase', $phase);
        if (! in_array($phase, [3, 4], true) || $subs->isEmpty()) {
            return [];
        }
        // many() already loaded these rows (keyed by submission or person id, so other games' rows are never matched).
        $entries = $this->shared['entries'] ?? Entry::query()->whereIn('submission_id', $subs->pluck('id'))->orderBy('id')->get()->groupBy('submission_id');
        $no = $this->phase1No();
        if ($phase === 3) {
            $p2 = $this->subs->where('phase', 2)->where('status', SubmissionStatus::Submitted)->keyBy('association_id');
            $numbers = $this->shared['numbers'] ?? NumberEntry::query()->whereIn('submission_id', $p2->pluck('id'))->get()->groupBy('submission_id');
        } else {
            $people = ($this->shared['people'] ?? Person::withoutGlobalScopes()->whereKey($entries->flatten()->pluck('person_id')->unique()->all())
                ->get(['id', 'association_id', 'gender']))->groupBy('association_id');
        }

        $out = [];
        foreach ($subs as $s) {
            $sports = $this->game->gameSports->filter(fn ($gs) => $gs->sport?->association_id === $s->association_id)
                ->reject(fn ($gs) => in_array($gs->id, $no[$s->association_id] ?? [], true))->keyBy('id');
            [$athletes, $officials] = LongListChecks::pick($sports, $sports->flatMap->gameEvents->keyBy('id'), $entries->get($s->id, []));
            if ($phase === 3) {
                $p2sub = $p2->get($s->association_id);
                $n = $p2sub ? LongListChecks::phase2Differences($athletes, ($numbers->get($p2sub->id) ?? collect())->pluck('count', 'game_event_id')->all()) : 0;
                $flag = $n ? $n.' '.($n === 1 ? 'differs' : 'differ').' from Phase 2' : null;
            } else {
                $genders = ($people->get($s->association_id) ?? collect())->pluck('gender', 'id')->all();
                $n = count(ShortListChecks::over($sports, $athletes, $officials, $genders));
                $flag = $n ? $n.' over a maximum' : null;
            }
            if ($flag) {
                $out[$s->association_id] = $flag;
            }
        }

        return $out;
    }

    /** @return array<int, list<int>> association id => game sport ids answered "no" in Phase 1 (only when Phase 1 is enabled, as Game::phase2SportsFor) */
    private function phase1No(): array
    {
        if (! $this->game->phase(1)->enabled) {
            return [];
        }
        $p1 = $this->subs->where('phase', 1)->pluck('association_id', 'id');

        $answers = $this->shared ? $this->shared['no']->whereIn('submission_id', $p1->keys()->all())
            : InterestAnswer::query()->whereIn('submission_id', $p1->keys())->where('answer', 'no')->get(['submission_id', 'game_sport_id']);

        return $answers->groupBy(fn ($a) => $p1[$a->submission_id])->map(fn ($g) => $g->pluck('game_sport_id')->all())->all();
    }
}
