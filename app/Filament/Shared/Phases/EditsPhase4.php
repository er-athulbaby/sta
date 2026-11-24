<?php

namespace App\Filament\Shared\Phases;

use App\Models\Association;
use App\Models\GameSport;
use App\Models\Person;
use App\Services\Phases\LongListChecks;
use App\Services\Phases\ShortListChecks;
use Filament\Notifications\Notification;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Phase 4 (Short List) state, checks and save; use together with HandlesSubmission. */
trait EditsPhase4
{
    /** @var array<int|string, mixed> game_event_id => person ids (ticked) */
    public array $athletes = [];

    /** @var array<int|string, mixed> game_sport_id => person ids (ticked) */
    public array $officials = [];

    public ?string $justification = null;

    abstract protected function association(): Association;

    protected function loadPhase(): void
    {
        app(ShortListChecks::class)->preload($this->submission);
        $this->submission->refresh();
        [$this->athletes, $this->officials] = app(ShortListChecks::class)->selection($this->submission);
        $this->justification = $this->submission->justification;
    }

    public function longListSubmitted(): bool
    {
        return app(ShortListChecks::class)->longList($this->submission) !== null;
    }

    /** @return array{0: array<int, list<string>>, 1: array<int, list<string>>} */
    public function longListSlots(): array
    {
        return app(ShortListChecks::class)->allowed($this->submission);
    }

    /** @return Collection<int, Person> people on the Long List keyed by id, for the tick labels */
    #[Unexposed]
    public function longListPeople(): Collection
    {
        [$athletes, $officials] = $this->longListSlots();

        return Person::withoutGlobalScopes()->where('association_id', $this->submission->association_id)
            ->whereKey(collect([...$athletes, ...$officials])->flatten()->unique()->all())->get()->keyBy('id');
    }

    /** @return Collection<int, GameSport> */
    #[Unexposed]
    public function visibleSports(): Collection
    {
        return $this->gameModel->phase2SportsFor($this->association());
    }

    /** @return array{male: int, female: int, officials: int} people ticked in one sport (spec 17: Short List meters) */
    public function tickedCounts(GameSport $gs): array
    {
        $genders = $this->longListPeople()->map(fn (Person $p) => $p->gender)->all();
        $c = ShortListChecks::counts($gs, $this->athletes, $this->officials, $genders);

        return ['male' => $c['men'], 'female' => $c['women'], 'officials' => $c['officials']];
    }

    /** @return list<array{sport: string, kind: string, count: int, max: int}> for the ticks on screen */
    public function overLimits(): array
    {
        return app(ShortListChecks::class)->overLimits($this->submission, [$this->athletes, $this->officials]);
    }

    /**
     * Moves one Long List slot onto the Short List (athletes: per game event, officials: per game sport). Inactive people are
     * refused; going over a maximum stays allowed (it needs a justification) but is warned with the limit message.
     */
    public function addToShortList(string $kind, int $slot, int $personId): void
    {
        if ($this->readOnlyReason !== null || ! in_array($kind, ['athletes', 'officials'], true)) {
            return;
        }
        [$athletes, $officials] = $this->longListSlots();
        if (! in_array((string) $personId, ($kind === 'athletes' ? $athletes : $officials)[$slot] ?? [], true)) {
            return;
        }
        $person = $this->longListPeople()->get($personId);
        if (! $person?->is_active) {
            Notification::make()->danger()->title(($person?->fullName() ?? 'This person').' is inactive.')->send();

            return;
        }
        $before = collect($this->overLimits())->map(fn ($o) => $o['game_sport_id'].$o['kind'])->all();
        $this->{$kind}[$slot] = array_values(array_unique([...array_map('strval', (array) ($this->{$kind}[$slot] ?? [])), (string) $personId]));
        foreach ($this->overLimits() as $o) {
            if (! in_array($o['game_sport_id'].$o['kind'], $before, true)) {
                Notification::make()->warning()->title('Over the maximum: '.ShortListChecks::describe($o))
                    ->body('Give a justification before submitting.')->send();
            }
        }
    }

    public function removeFromShortList(string $kind, int $slot, int $personId): void
    {
        if ($this->readOnlyReason !== null || ! in_array($kind, ['athletes', 'officials'], true)) {
            return;
        }
        $this->{$kind}[$slot] = array_values(array_filter(array_map('strval', (array) ($this->{$kind}[$slot] ?? [])), fn ($id) => $id !== (string) $personId));
    }

    public function save(): void
    {
        try {
            $this->guardEditable();
            if (! $this->longListSubmitted()) {
                Notification::make()->danger()->title(ShortListChecks::LONG_LIST_FIRST)->send();
                throw new Halt;
            }
            if (mb_strlen((string) $this->justification) > 2000) {
                throw ValidationException::withMessages(['justification' => 'The justification may not be longer than 2000 characters.']);
            }
            if ($errors = app(ShortListChecks::class)->errors($this->submission, $this->athletes, $this->officials)) {
                throw ValidationException::withMessages($errors);
            }
            DB::transaction(function () {
                $this->claimOrHalt();
                app(LongListChecks::class)->sync($this->submission, $this->athletes, $this->officials);
                $this->submission->update(['justification' => filled($this->justification) ? $this->justification : null]);
            });
        } catch (Halt) {
            return; // notification already sent; loadedVersion unchanged so submit knows nothing was saved
        }
        $this->refreshLoadedAt();
        Notification::make()->success()->title('Saved')->send();
    }
}
