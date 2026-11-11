<?php

namespace App\Filament\Shared\Phases;

use App\Models\Association;
use App\Models\GameSport;
use App\Models\NumberEntry;
use App\Models\Phase2SportTotal;
use App\Services\Phases\Phase2Limits;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Phase 2 (Entry by Number) state, live limits and save; use together with HandlesSubmission. */
trait EditsPhase2
{
    /** @var array<int|string, int|string|null> game_event_id => count */
    public array $counts = [];

    /** @var array<int|string, array<string, int|string|null>> game_sport_id => Phase2SportTotal::DIGITS field => value */
    public array $totals = [];

    /** @var array<string, string> */
    public array $liveErrors = [];

    abstract protected function association(): Association;

    protected function loadPhase(): void
    {
        $this->counts = $this->submission->numbers()->pluck('count', 'game_event_id')->all();
        $this->totals = $this->submission->sportTotals()->get()
            ->mapWithKeys(fn (Phase2SportTotal $t) => [$t->game_sport_id => $t->only(array_keys(Phase2SportTotal::DIGITS))])->all();
    }

    /** @return Collection<int, GameSport> */
    public function visibleSports(): Collection
    {
        return $this->gameModel->phase2SportsFor($this->association());
    }

    /** @return array<string, string> keyed counts.{gameEventId}, totals.{gameSportId}.{field} or sport.{gameSportId}.{male|female|total} */
    protected function limitErrors(): array
    {
        $errors = [];
        foreach ($this->visibleSports() as $gs) {
            $valid = $this->counts;
            foreach ($gs->gameEvents as $ge) {
                $raw = $this->counts[$ge->id] ?? null;
                if ($raw !== null && $raw !== '' && ! preg_match('/^\d{1,3}$/', trim((string) $raw))) {
                    $errors['counts.'.$ge->id] = "{$ge->event->name}: enter a whole number from 0 to 999.";
                    unset($valid[$ge->id]);
                }
            }
            $totals = [];
            foreach (Phase2SportTotal::DIGITS as $field => $digits) {
                $raw = trim((string) ($this->totals[$gs->id][$field] ?? ''));
                if ($raw === '') {
                    continue;
                }
                if (! preg_match('/^\d{1,'.$digits.'}$/', $raw)) {
                    $errors["totals.{$gs->id}.{$field}"] = "{$gs->sport->name} ".strtolower(Phase2SportTotal::LABELS[$field])
                        .': enter a whole number from 0 to '.str_repeat('9', $digits).'.';

                    continue;
                }
                $totals[$field] = (int) $raw;
            }
            foreach (app(Phase2Limits::class)->errors($gs, $valid, $totals) as $key => $message) {
                $errors[str_starts_with($key, 'event.') ? 'counts.'.substr($key, 6) : "sport.{$gs->id}.".substr($key, 6)] ??= $message;
            }
        }

        return $errors;
    }

    public function updatedCounts(): void
    {
        $this->liveErrors = $this->limitErrors();
    }

    /** Canvas −/+ stepper: one step on an event count (0–999); the same live limits as typing, and save() still validates. */
    public function stepCount(int $gameEventId, int $delta): void
    {
        if ($this->readOnlyReason !== null) {
            return;
        }
        $raw = trim((string) ($this->counts[$gameEventId] ?? ''));
        $current = preg_match('/^\d{1,3}$/', $raw) ? (int) $raw : 0;
        $this->counts[$gameEventId] = max(0, min(999, $current + ($delta <=> 0)));
        $this->updatedCounts();
    }

    public function updatedTotals(): void
    {
        $this->liveErrors = $this->limitErrors();
    }

    public function save(): void
    {
        try {
            $this->guardEditable();
            if ($errors = $this->limitErrors()) {
                throw ValidationException::withMessages($errors);
            }
            $sports = $this->visibleSports();
            $allowedEvents = $sports->flatMap->gameEvents->pluck('id')->all();
            $allowedSports = $sports->pluck('id')->all();
            DB::transaction(function () use ($allowedEvents, $allowedSports) {
                $this->claimOrHalt();
                foreach ($this->counts as $gameEventId => $count) {
                    if (in_array((int) $gameEventId, $allowedEvents, true)) {
                        NumberEntry::updateOrCreate(['submission_id' => $this->submission->id, 'game_event_id' => $gameEventId], ['count' => (int) $count]);
                    }
                }
                foreach ($this->totals as $gameSportId => $row) {
                    if (in_array((int) $gameSportId, $allowedSports, true)) {
                        Phase2SportTotal::updateOrCreate(['submission_id' => $this->submission->id, 'game_sport_id' => $gameSportId],
                            collect(Phase2SportTotal::DIGITS)->map(fn ($digits, $field) => blank($row[$field] ?? null) ? null : (int) $row[$field])->all());
                    }
                }
                // lock_version already bumped by claimOrHalt()
            });
        } catch (Halt) {
            return; // notification already sent; loadedVersion unchanged so submit knows nothing was saved
        }
        $this->resetValidation();
        $this->liveErrors = [];
        $this->refreshLoadedAt();
        Notification::make()->success()->title('Saved')->send();
    }
}
