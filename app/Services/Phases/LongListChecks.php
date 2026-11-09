<?php

namespace App\Services\Phases;

use App\Enums\EventGender;
use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Enums\SubmissionStatus;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\LongListDetail;
use App\Models\Person;
use App\Models\Submission;
use Illuminate\Support\Collection;

/** Phase 3 (Long List) rules: hard errors block save and submit, warnings never block. */
final class LongListChecks
{
    public static function genderMatches(PersonGender $person, EventGender $event): bool
    {
        return match ($event) {
            EventGender::Mixed => true,
            EventGender::Men => $person === PersonGender::Male,
            EventGender::Women => $person === PersonGender::Female,
        };
    }

    /** @return array{0: Collection<int, GameSport>, 1: Collection<int, GameEvent>} the sports and events open to the association, each keyed by id */
    public function visible(Submission $s): array
    {
        $sports = $s->game->phase2SportsFor($s->association)->keyBy('id');

        return [$sports, $sports->flatMap->gameEvents->keyBy('id')];
    }

    /** Saved entries in sports still open to the association; entries in sports hidden later are ignored. @return array{0: array<int, list<string>>, 1: array<int, list<string>>} [game_event_id => person ids, game_sport_id => person ids] */
    public function selection(Submission $s): array
    {
        [$sports, $events] = $this->visible($s);
        $athletes = [];
        $officials = [];
        foreach ($s->entries()->orderBy('id')->get() as $e) {
            if ($e->game_event_id) {
                if ($events->has($e->game_event_id)) {
                    $athletes[$e->game_event_id][] = (string) $e->person_id;
                }
            } elseif ($sports->has($e->game_sport_id)) {
                $officials[$e->game_sport_id][] = (string) $e->person_id;
            }
        }

        return [$athletes, $officials];
    }

    /**
     * @param  array<int|string, mixed>  $athletes  game_event_id => person ids
     * @param  array<int|string, mixed>  $officials  game_sport_id => person ids
     * @return array<string, string> keyed athletes.{game_event_id} / officials.{game_sport_id}
     */
    public function errors(Submission $s, array $athletes, array $officials): array
    {
        [$sports, $events] = $this->visible($s);
        $ids = collect([...array_values($athletes), ...array_values($officials)])->flatten()->map(fn ($id) => (int) $id)->unique()->all();
        $people = Person::withoutGlobalScopes()->where('association_id', $s->association_id)->whereKey($ids)->get()->keyBy('id');
        $errors = [];

        foreach ($athletes as $geId => $personIds) {
            $ge = $events->get((int) $geId);
            foreach ((array) $personIds as $id) {
                if ($ge === null) {
                    $errors["athletes.$geId"] = 'This event is not open to your association in this game.';
                    break;
                }
                $p = $people->get((int) $id);
                $msg = $this->personError($s, $p, PersonType::Athlete)
                    ?? (self::genderMatches($p->gender, $ge->event->gender) ? null
                        : "{$p->fullName()} ({$p->gender->label()}) cannot enter a {$ge->event->gender->label()} event.");
                if ($msg) {
                    $errors["athletes.$geId"] = "{$ge->event->name}: $msg";
                    break;
                }
            }
        }

        foreach ($officials as $gsId => $personIds) {
            $gs = $sports->get((int) $gsId);
            foreach ((array) $personIds as $id) {
                if ($gs === null) {
                    $errors["officials.$gsId"] = 'This sport is not open to your association in this game.';
                    break;
                }
                if ($msg = $this->personError($s, $people->get((int) $id), PersonType::Official)) {
                    $errors["officials.$gsId"] = "{$gs->sport->name}: $msg";
                    break;
                }
            }
        }

        return $errors;
    }

    private function personError(Submission $s, ?Person $p, PersonType $type): ?string
    {
        return match (true) {
            $p === null || (int) $p->association_id !== (int) $s->association_id => "Choose people from your association's registry.",
            ! $p->is_active => "{$p->fullName()} is inactive.",
            $p->type !== $type => $type === PersonType::Athlete
                ? "{$p->fullName()} is a team official, not an athlete."
                : "{$p->fullName()} is an athlete, not a team official.",
            default => null,
        };
    }

    /** Hard errors of the saved list plus incomplete people (spec 13): blocks submit and approve. @return list<string> */
    public function submitErrors(Submission $s): array
    {
        [$athletes, $officials] = $this->selection($s);
        $errors = array_values($this->errors($s, $athletes, $officials));
        $people = Person::withoutGlobalScopes()->where('association_id', $s->association_id)
            ->whereIn('id', collect([...$athletes, ...$officials])->flatten()->unique())->orderBy('last_name')->orderBy('first_name')->get();
        foreach ($people as $p) {
            if ($missing = $p->missingFields()) {
                $errors[] = "{$p->fullName()} is incomplete: ".implode(', ', $missing).'.';
            }
        }

        return $errors;
    }

    /** @return array<int, int>|null game_event_id => Phase 2 count; null unless the association's Phase 2 is submitted */
    public function phase2Numbers(Submission $s): ?array
    {
        $p2 = Submission::withoutGlobalScope('association')
            ->where(['game_id' => $s->game_id, 'association_id' => $s->association_id, 'phase' => 2])->first();

        return $p2?->status === SubmissionStatus::Submitted ? $p2->numbers()->pluck('count', 'game_event_id')->all() : null;
    }

    /**
     * @param  iterable<GameSport>  $gameSports  the sports the person is entered in (for the athlete age range)
     * @return list<string>
     */
    public function warnings(Game $game, Person $p, iterable $gameSports): array
    {
        $w = [];
        if ($p->passport_expiry && $p->passport_expiry->lt($game->end_date)) {
            $w[] = 'Passport expires before the games end ('.$p->passport_expiry->format('j M Y').').';
        }
        if ($p->cpr_expiry && $p->cpr_expiry->lt($game->end_date)) {
            $w[] = 'CPR expires before the games end ('.$p->cpr_expiry->format('j M Y').').';
        }
        if (blank($p->passport_scan_path)) {
            $w[] = 'Passport scan missing.';
        }
        if (blank($p->cpr_front_path)) {
            $w[] = 'CPR scan missing.';
        }
        if ($p->type === PersonType::Athlete) {
            foreach ($gameSports as $gs) {
                if (($gs->dob_from && $p->dob->lt($gs->dob_from)) || ($gs->dob_to && $p->dob->gt($gs->dob_to))) {
                    $w[] = "Date of birth is outside the {$gs->sport->name} age range.";
                }
            }
        }
        $sizes = $p->sizes->pluck('size', 'clothing_item_id');
        foreach ($game->clothingItems as $item) {
            if ($item->applies_to->includes($p->type) && blank($sizes->get($item->id))) {
                $w[] = "{$item->name} size missing.";
            }
        }

        return $w;
    }

    /**
     * The people table under the pickers (and on the admin submission page), for a proposed or saved selection.
     *
     * @return Collection<int, array{person: Person, roles: list<string>, age: int, missing: list<string>, warnings: list<string>, detail: ?LongListDetail, on_list: bool}>
     */
    public function rows(Submission $s, array $athletes, array $officials): Collection
    {
        $game = $s->game;
        [$sports, $events] = $this->visible($s);
        $roles = [];
        $sportsOf = [];
        foreach ($athletes as $geId => $ids) {
            $ge = $events->get((int) $geId);
            if (! $ge) {
                continue;
            }
            $gs = $sports->get($ge->game_sport_id);
            foreach ((array) $ids as $id) {
                $roles[(int) $id][] = $gs->sport->name.' · '.$ge->event->name;
                $sportsOf[(int) $id][$gs->id] = $gs;
            }
        }
        foreach ($officials as $gsId => $ids) {
            $gs = $sports->get((int) $gsId);
            if (! $gs) {
                continue;
            }
            foreach ((array) $ids as $id) {
                $roles[(int) $id][] = $gs->sport->name.' · team official';
            }
        }
        $saved = $s->entries()->pluck('person_id')->map(fn ($id) => (int) $id)->all();
        $details = $s->details()->get()->keyBy('person_id');

        return Person::withoutGlobalScopes()->with('sizes')->where('association_id', $s->association_id)->whereKey(array_keys($roles))
            ->orderBy('type')->orderBy('last_name')->orderBy('first_name')->get()
            ->map(fn (Person $p) => [
                'person' => $p,
                'roles' => $roles[$p->id],
                'age' => $p->age($game->start_date),
                'missing' => $p->missingFields(),
                'warnings' => $this->warnings($game, $p, $sportsOf[$p->id] ?? []),
                'detail' => $details->get($p->id),
                'on_list' => in_array($p->id, $saved, true),
            ]);
    }
}
