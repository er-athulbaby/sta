<?php

namespace App\Services\Phases;

use App\Enums\PersonGender;
use App\Enums\SubmissionStatus;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;

/** Phase 4 (Short List) rules: people come only from the submitted Long List; limits warn, and then need a justification. */
final class ShortListChecks
{
    public const LONG_LIST_FIRST = 'Submit the Long List first.';

    public const NOT_ON_LONG_LIST = 'Choose only people on your submitted Long List.';

    public const JUSTIFICATION_REQUIRED = 'The Short List is over a limit: give a justification before submitting.';

    public const KINDS = ['men' => 'men', 'women' => 'women', 'officials' => 'team officials'];

    public function __construct(private readonly LongListChecks $long) {}

    public function longList(Submission $s): ?Submission
    {
        $p3 = Submission::withoutGlobalScope('association')
            ->where(['game_id' => $s->game_id, 'association_id' => $s->association_id, 'phase' => 3])->first();

        return $p3?->status === SubmissionStatus::Submitted ? $p3 : null;
    }

    /** Copy the submitted Long List into an empty Phase 4 submission, once: preloaded_at is claimed atomically, so nothing is ever re-copied. */
    public function preload(Submission $phase4): bool
    {
        if ($phase4->phase !== 4 || $phase4->preloaded_at !== null || ! ($p3 = $this->longList($phase4)) || $phase4->entries()->exists()) {
            return false;
        }

        return DB::transaction(function () use ($phase4, $p3) {
            $claimed = Submission::withoutGlobalScope('association')->whereKey($phase4->id)->whereNull('preloaded_at')
                ->update(['preloaded_at' => now()]);
            if ($claimed !== 1) {
                return false;
            }
            foreach ($p3->entries()->orderBy('id')->get() as $e) {
                $phase4->entries()->create($e->only(['person_id', 'game_event_id', 'game_sport_id']));
            }
            Submission::withoutGlobalScope('association')->whereKey($phase4->id)->increment('lock_version'); // open pages see a stale notice

            return true;
        });
    }

    /** @return array{0: array<int, list<string>>, 1: array<int, list<string>>} the Long List slots open to the association */
    public function allowed(Submission $s): array
    {
        $p3 = $this->longList($s);

        return $p3 ? $this->long->selection($p3) : [[], []];
    }

    /** The saved Short List, restricted to the Long List; every Long List slot is present so checkbox arrays bind. */
    public function selection(Submission $s): array
    {
        [$allowedAthletes, $allowedOfficials] = $this->allowed($s);
        [$athletes, $officials] = $this->long->selection($s);
        $pick = fn (array $allowed, array $saved) => collect($allowed)
            ->map(fn (array $ids, int $slot) => array_values(array_intersect($saved[$slot] ?? [], $ids)))->all();

        return [$pick($allowedAthletes, $athletes), $pick($allowedOfficials, $officials)];
    }

    /**
     * @param  array<int|string, mixed>  $athletes  game_event_id => person ids
     * @param  array<int|string, mixed>  $officials  game_sport_id => person ids
     * @return array<string, string>
     */
    public function errors(Submission $s, array $athletes, array $officials): array
    {
        [$allowedAthletes, $allowedOfficials] = $this->allowed($s);
        $errors = [];
        foreach (['athletes' => [$athletes, $allowedAthletes], 'officials' => [$officials, $allowedOfficials]] as $kind => [$picked, $allowed]) {
            foreach ($picked as $slot => $ids) {
                if (array_diff(array_map('strval', (array) $ids), $allowed[(int) $slot] ?? [])) {
                    $errors["$kind.$slot"] = self::NOT_ON_LONG_LIST;
                }
            }
        }

        return $errors;
    }

    /** @return list<string> */
    public function submitErrors(Submission $s): array
    {
        if (! $this->longList($s)) {
            return [self::LONG_LIST_FIRST];
        }

        return array_values($this->errors($s, ...$this->long->selection($s)));
    }

    /** @return list<array{sport: string, kind: string, count: int, max: int}> */
    public function overLimits(Submission $s, ?array $selection = null): array
    {
        [$athletes, $officials] = $selection ?? $this->long->selection($s);
        $ids = collect([...array_values($athletes), ...array_values($officials)])->flatten()->map(fn ($id) => (int) $id)->unique()->all();
        $genders = Person::withoutGlobalScopes()->where('association_id', $s->association_id)->whereKey($ids)->pluck('gender', 'id')->all();

        return self::over($this->long->visible($s)[0], $athletes, $officials, $genders);
    }

    /**
     * @param  iterable<GameSport>  $sports  with sport and gameEvents loaded
     * @param  array<int, PersonGender>  $genders  person id => gender
     * @return list<array{sport: string, kind: string, count: int, max: int}>
     */
    public static function over(iterable $sports, array $athletes, array $officials, array $genders): array
    {
        $out = [];
        foreach ($sports as $gs) {
            $people = $gs->gameEvents->flatMap(fn ($ge) => (array) ($athletes[$ge->id] ?? []))->map(fn ($id) => (int) $id)->unique();
            $count = [
                'men' => $people->filter(fn ($id) => ($genders[$id] ?? null) === PersonGender::Male)->count(),
                'women' => $people->filter(fn ($id) => ($genders[$id] ?? null) === PersonGender::Female)->count(),
                'officials' => collect((array) ($officials[$gs->id] ?? []))->map(fn ($id) => (int) $id)->unique()->count(),
            ];
            foreach (['men' => $gs->p4_max_men, 'women' => $gs->p4_max_women, 'officials' => $gs->p4_max_officials] as $kind => $max) {
                if ($max !== null && $count[$kind] > (int) $max) {
                    $out[] = ['sport' => $gs->sport->name, 'kind' => $kind, 'count' => $count[$kind], 'max' => (int) $max];
                }
            }
        }

        return $out;
    }

    public static function describe(array $over): string
    {
        return "{$over['sport']}: {$over['count']} ".self::KINDS[$over['kind']]." (limit {$over['max']})";
    }
}
