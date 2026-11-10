<?php

namespace App\Filament\Resources\Games\Pages;

use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\GameResource;
use App\Models\GamePhase;
use App\Models\Submission;
use App\Notifications\NewComment;
use App\Services\Phases\LongListChecks;
use App\Services\Phases\Recipients;
use App\Services\Phases\ShortListChecks;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
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

    /** @return list<string> e.g. "Judo: 2 men (limit 1)" */
    public function overLimitLines(): array
    {
        return array_map(ShortListChecks::describe(...), app(ShortListChecks::class)->overLimits($this->submission));
    }

    /** @return list<string> problems with a submitted Short List, e.g. after the Long List was re-opened */
    public function shortListWarnings(): array
    {
        if ($this->submission->phase !== 4 || $this->submission->status !== SubmissionStatus::Submitted) {
            return [];
        }

        return app(ShortListChecks::class)->submitErrors($this->submission);
    }

    public function longListRows(): Collection
    {
        $checks = app(LongListChecks::class);

        return $checks->rows($this->submission, ...$checks->selection($this->submission));
    }

    /** @return Collection<int, Activity> */
    public function auditTrail(): Collection
    {
        return Activity::where('log_name', 'submission-'.$this->submission->id)->with('causer')->latest('id')->limit(200)->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')->label('Edit as STA')
                ->visible(fn () => auth()->user()->can(StaPermission::EditSubmissions->value) && in_array($this->submission->phase, [1, 2, 3, 4], true))
                ->url(fn () => GameResource::getUrl('submission-edit-'.$this->submission->phase, ['record' => $this->record, 'submission' => $this->submission])),
            Action::make('comment')->label('Add comment')
                ->visible(fn () => (bool) auth()->user()->can(StaPermission::EditSubmissions->value))
                ->schema([Textarea::make('body')->label('Comment (emailed to the association)')->required()->rows(4)->maxLength(5000)])
                ->action(function (array $data) {
                    $comment = $this->submission->comments()->create(['user_id' => auth()->id(), 'body' => $data['body']]);
                    NotificationFacade::send(Recipients::association($this->submission->association), new NewComment($comment));
                    Notification::make()->success()->title('Comment added and emailed to the association')->send();
                }),
        ];
    }
}
