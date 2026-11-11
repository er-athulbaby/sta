<?php

namespace App\Support;

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\Game;
use App\Models\GamePhase;
use App\Models\Submission;
use App\Services\Phases\LongListChecks;
use App\Services\Phases\ShortListChecks;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Read-only status of one game's submissions (spec 17 game dashboard). Never creates submissions.
 * Loads phases, invited associations, game sports and every submission once; flags are memoised per association (ruling U3).
 */
final class GameProgress
{
    /** @var Collection<string, Submission> keyed "associationId-phase" */
    private Collection $subs;

    /** @var array<string, ?string> */
    private array $flags = [];

    public function __construct(public readonly Game $game)
    {
        $game->loadMissing(['phases', 'associations', 'gameSports.sport']);
        $this->subs = Submission::withoutGlobalScopes()->where('game_id', $game->id)
            ->withExists(['answers', 'numbers', 'entries'])->get()->keyBy(fn (Submission $s) => "{$s->association_id}-{$s->phase}");
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
        return array_key_exists($key = "{$a->id}-{$phase}", $this->flags) ? $this->flags[$key] : $this->flags[$key] = $this->computeFlags($a, $phase);
    }

    private function computeFlags(Association $a, int $phase): ?string
    {
        $s = $this->submission($a, $phase);
        if ($s === null || ! in_array($phase, [3, 4], true)) {
            return null;
        }
        if ($phase === 3) {
            $long = app(LongListChecks::class);
            $p2 = $long->phase2Numbers($s);
            if ($p2 === null) {
                return null;
            }
            [$athletes] = $long->selection($s);
            $n = collect($athletes)->filter(fn ($ids, $ge) => count($ids) > ($p2[$ge] ?? 0))->count();

            return $n ? $n.' '.($n === 1 ? 'differs' : 'differ').' from Phase 2' : null;
        }
        $n = count(app(ShortListChecks::class)->overLimits($s));

        return $n ? $n.' over a maximum' : null;
    }
}
