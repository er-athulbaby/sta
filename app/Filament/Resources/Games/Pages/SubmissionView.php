<?php

namespace App\Filament\Resources\Games\Pages;

use App\Enums\StaPermission;
use App\Filament\Resources\Games\GameResource;
use App\Models\GamePhase;
use App\Models\Submission;
use App\Services\Phases\LongListChecks;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class SubmissionView extends Page
{
    use InteractsWithRecord;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.resources.games.pages.submission-view';

    public Submission $submission;

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can(StaPermission::ViewSubmissions->value);
    }

    /** $submission is a model over HTTP (Livewire binds the typed property) and an id in Livewire tests. */
    public function mount(int|string $record, int|string|Submission $submission): void
    {
        $this->record = $this->resolveRecord($record);
        $this->submission = Submission::withoutGlobalScopes()->where('game_id', $this->record->getKey())
            ->findOrFail($submission instanceof Submission ? $submission->getKey() : $submission);
    }

    public function getTitle(): string
    {
        return $this->submission->association->name.' – Phase '.$this->submission->phase.' · '.GamePhase::NAMES[$this->submission->phase];
    }

    /** @return Collection<int, array{sport: string, answer: string}> */
    public function phase1Rows(): Collection
    {
        $answers = $this->submission->answers()->get()->keyBy('game_sport_id');

        return $this->record->sportsFor($this->submission->association)
            ->map(fn ($gs) => ['sport' => $gs->sport->name, 'answer' => $answers->get($gs->id)?->answer->label() ?? 'No response']);
    }

    /** @return Collection<int, array{sport: string, event: string, count: int}> */
    public function phase2Rows(): Collection
    {
        $counts = $this->submission->numbers()->pluck('count', 'game_event_id');

        return $this->record->phase2SportsFor($this->submission->association)->flatMap(fn ($gs) => $gs->gameEvents->map(
            fn ($ge) => ['sport' => $gs->sport->name, 'event' => $ge->event->name, 'count' => (int) ($counts[$ge->id] ?? 0)]));
    }

    public function longListRows(): Collection
    {
        $checks = app(LongListChecks::class);

        return $checks->rows($this->submission, ...$checks->selection($this->submission));
    }

    /** @return Collection<int, Activity> */
    public function auditTrail(): Collection
    {
        return Activity::where('log_name', 'submission-'.$this->submission->id)->with('causer')->latest('id')->get();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
