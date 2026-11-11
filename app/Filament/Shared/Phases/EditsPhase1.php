<?php

namespace App\Filament\Shared\Phases;

use App\Enums\InterestAnswerValue;
use App\Models\Association;
use App\Models\InterestAnswer;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\DB;

/** Phase 1 (Entry by Sport) state and save; use together with HandlesSubmission. */
trait EditsPhase1
{
    /** @var array<int|string, string> game_sport_id => yes|no|maybe */
    public array $answers = [];

    /** @var array<int|string, ?string> game_sport_id => optional note (spec 17, at most 255 characters) */
    public array $notes = [];

    abstract protected function association(): Association;

    protected function loadPhase(): void
    {
        $this->answers = $this->submission->answers()->pluck('answer', 'game_sport_id')->map(fn ($a) => $a->value)->all();
        $this->notes = $this->submission->answers()->pluck('note', 'game_sport_id')->filter()->all();
    }

    public function getSportsProperty()
    {
        return $this->gameModel->sportsFor($this->association());
    }

    public function save(): void
    {
        try {
            $this->guardEditable();
            $this->validate(['notes.*' => ['nullable', 'string', 'max:255']], ['notes.*.max' => 'A note can be at most 255 characters.']);
            $valid = $this->gameModel->sportsFor($this->association())->pluck('id')->all();
            DB::transaction(function () use ($valid) {
                $this->claimOrHalt();
                foreach ($this->answers as $gameSportId => $answer) {
                    if (! in_array((int) $gameSportId, $valid, true) || InterestAnswerValue::tryFrom((string) $answer) === null) {
                        continue;
                    }
                    $note = trim((string) ($this->notes[$gameSportId] ?? ''));
                    InterestAnswer::updateOrCreate(['submission_id' => $this->submission->id, 'game_sport_id' => $gameSportId], ['answer' => $answer, 'note' => $note === '' ? null : $note]);
                }
                // lock_version already bumped by claimOrHalt()
            });
        } catch (Halt) {
            return; // notification already sent; loadedVersion unchanged so submit knows nothing was saved
        }
        $this->refreshLoadedAt();
        Notification::make()->success()->title('Saved')->send();
    }
}
