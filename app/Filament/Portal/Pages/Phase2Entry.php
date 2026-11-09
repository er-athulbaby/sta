<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Portal\Concerns\HandlesSubmission;
use App\Models\Game;
use App\Models\GameSport;
use App\Models\NumberEntry;
use App\Models\Submission;
use App\Services\Phases\Phase2Limits;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Phase2Entry extends Page
{
    use HandlesSubmission;

    protected string $view = 'filament.portal.pages.phase2-entry';

    public Game $gameModel;

    /** @var array<int|string, int|string|null> game_event_id => count */
    public array $counts = [];

    /** @var array<string, string> */
    public array $liveErrors = [];

    public static function getRoutePath(Panel $panel): string
    {
        return '/games/{game}/phase-2';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->gameModel->name.' – Phase 2 · Entry by Number';
    }

    public function mount(int|string $game): void
    {
        $this->gameModel = Game::findOrFail($game);
        $tenant = Filament::getTenant();
        abort_unless($this->gameModel->isInvited($tenant) && $this->gameModel->phase(2)->enabled, 404);
        $this->submission = Submission::for($this->gameModel, $tenant, 2);
        $this->counts = $this->submission->numbers()->pluck('count', 'game_event_id')->all();
        $this->refreshLoadedAt();
    }

    /** @return Collection<int, GameSport> */
    public function visibleSports(): Collection
    {
        $sports = $this->gameModel->sportsFor(Filament::getTenant());
        if (! $this->gameModel->phase(1)->enabled) {
            return $sports;
        }
        $p1 = Submission::withoutGlobalScopes()->where(['game_id' => $this->gameModel->id, 'association_id' => Filament::getTenant()->id, 'phase' => 1])->first();
        $no = $p1 ? $p1->answers()->where('answer', 'no')->pluck('game_sport_id')->all() : [];

        return $sports->reject(fn ($gs) => in_array($gs->id, $no, true))->values();
    }

    /** @return array<string, string> keyed counts.{gameEventId} or sport.{gameSportId}.{men|women|total} */
    protected function limitErrors(): array
    {
        $errors = [];
        foreach ($this->visibleSports() as $gs) {
            foreach (app(Phase2Limits::class)->errors($gs, $this->counts) as $key => $message) {
                $errors[str_starts_with($key, 'event.') ? 'counts.'.substr($key, 6) : "sport.{$gs->id}.".substr($key, 6)] = $message;
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
        $this->liveErrors = [];
        $this->refreshLoadedAt();
        Notification::make()->success()->title('Saved')->send();
    }

    protected function getHeaderActions(): array
    {
        return [$this->submitAction(), $this->approveAction(), $this->returnAction()];
    }
}
