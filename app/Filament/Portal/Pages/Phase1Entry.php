<?php

namespace App\Filament\Portal\Pages;

use App\Enums\InterestAnswerValue;
use App\Filament\Portal\Concerns\HandlesSubmission;
use App\Models\Game;
use App\Models\InterestAnswer;
use App\Models\Submission;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

class Phase1Entry extends Page
{
    use HandlesSubmission;

    protected string $view = 'filament.portal.pages.phase1-entry';

    public Game $gameModel;

    /** @var array<int|string, string> */
    public array $answers = [];

    public static function getRoutePath(Panel $panel): string
    {
        return '/games/{game}/phase-1';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->gameModel->name.' – Phase 1 · Entry by Sport';
    }

    public function mount(int|string $game): void
    {
        $this->gameModel = Game::findOrFail($game);
        $tenant = Filament::getTenant();
        abort_unless($this->gameModel->isInvited($tenant) && $this->gameModel->phase(1)->enabled, 404);
        $this->submission = Submission::for($this->gameModel, $tenant, 1);
        $this->answers = $this->submission->answers()->pluck('answer', 'game_sport_id')->map(fn ($a) => $a->value)->all();
        $this->refreshLoadedAt();
    }

    public function getSportsProperty()
    {
        return $this->gameModel->sportsFor(Filament::getTenant());
    }

    public function save(): void
    {
        try {
            $this->guardEditable();
            $valid = $this->gameModel->sportsFor(Filament::getTenant())->pluck('id')->all();
            DB::transaction(function () use ($valid) {
                $this->claimOrHalt();
                foreach ($this->answers as $gameSportId => $answer) {
                    if (! in_array((int) $gameSportId, $valid, true) || InterestAnswerValue::tryFrom((string) $answer) === null) {
                        continue;
                    }
                    InterestAnswer::updateOrCreate(['submission_id' => $this->submission->id, 'game_sport_id' => $gameSportId], ['answer' => $answer]);
                }
                // lock_version already bumped by claimOrHalt()
            });
        } catch (Halt) {
            return; // notification already sent; loadedVersion unchanged so submit knows nothing was saved
        }
        $this->refreshLoadedAt();
        Notification::make()->success()->title('Saved')->send();
    }

    protected function getHeaderActions(): array
    {
        return [$this->submitAction(), $this->approveAction(), $this->returnAction()];
    }
}
