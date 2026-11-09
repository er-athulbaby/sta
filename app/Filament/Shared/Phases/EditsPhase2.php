<?php

namespace App\Filament\Shared\Phases;

use App\Models\Association;
use App\Models\GameSport;
use App\Models\NumberEntry;
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

    /** @var array<string, string> */
    public array $liveErrors = [];

    abstract protected function association(): Association;

    protected function loadPhase(): void
    {
        $this->counts = $this->submission->numbers()->pluck('count', 'game_event_id')->all();
    }

    /** @return Collection<int, GameSport> */
    public function visibleSports(): Collection
    {
        return $this->gameModel->phase2SportsFor($this->association());
    }

    /** @return array<string, string> keyed counts.{gameEventId} or sport.{gameSportId}.{men|women|total} */
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
            foreach (app(Phase2Limits::class)->errors($gs, $valid) as $key => $message) {
                $errors[str_starts_with($key, 'event.') ? 'counts.'.substr($key, 6) : "sport.{$gs->id}.".substr($key, 6)] ??= $message;
            }
        }

        return $errors;
    }

    public function updatedCounts(): void
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
            $allowed = $this->visibleSports()->flatMap->gameEvents->pluck('id')->all();
            DB::transaction(function () use ($allowed) {
                $this->claimOrHalt();
                foreach ($this->counts as $gameEventId => $count) {
                    if (in_array((int) $gameEventId, $allowed, true)) {
                        NumberEntry::updateOrCreate(['submission_id' => $this->submission->id, 'game_event_id' => $gameEventId], ['count' => (int) $count]);
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
