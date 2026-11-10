<?php

namespace App\Filament\Resources\Games\Pages;

use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\GameResource;
use App\Models\Association;
use App\Models\GamePhase;
use App\Models\Submission;
use App\Notifications\PhaseReminder;
use App\Services\Phases\Recipients;
use App\Services\Phases\SubmissionWorkflow;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GameDashboard extends Page
{
    use InteractsWithRecord;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.resources.games.pages.game-dashboard';

    public bool $pendingOnly = false;

    /** @var Collection<string, Submission>|null */
    protected ?Collection $loaded = null;

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can(StaPermission::ViewSubmissions->value);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return $this->record->name.' - dashboard';
    }

    /** @return Collection<string, Submission> keyed "associationId-phase"; existing rows only, never created here */
    public function submissions(): Collection
    {
        return $this->loaded ??= Submission::withoutGlobalScopes()->where('game_id', $this->record->id)
            ->withExists(['answers', 'numbers', 'entries'])->get()->keyBy(fn ($s) => "{$s->association_id}-{$s->phase}");
    }

    public function loadSubmissions(): void
    {
        $this->loaded = null;
    }

    /** @return Collection<int, GamePhase> */
    public function phases(): Collection
    {
        return $this->record->phases()->where('enabled', true)->orderBy('phase')->get();
    }

    /** @return Collection<int, Association> */
    public function rows(): Collection
    {
        $open = $this->phases()->where('status', PhaseStatus::Open);

        return $this->record->associations()->orderBy('name')->get()
            ->when($this->pendingOnly, fn ($c) => $c->filter(
                fn ($a) => $open->contains(fn ($p) => $this->cell($a, $p->phase)['status'] !== 'submitted')))
            ->values();
    }

    /** @return array{status: string, label: string, submission: ?Submission} */
    public function cell(Association $a, int $phase): array
    {
        $s = $this->submissions()->get("{$a->id}-{$phase}");
        $deadline = $this->record->phase($phase)->effectiveDeadline($s);
        $status = match (true) {
            $s?->status === SubmissionStatus::Submitted => 'submitted',
            $s?->status === SubmissionStatus::AwaitingAssocAdmin => 'awaiting',
            $deadline !== null && now()->greaterThan($deadline) => 'overdue',
            $s !== null && ($s->answers_exists || $s->numbers_exists || $s->entries_exists) => 'draft',
            default => 'no_response',
        };
        $labels = ['submitted' => 'Submitted', 'awaiting' => 'Awaiting assoc. admin', 'overdue' => 'Overdue', 'draft' => 'In progress', 'no_response' => 'No response'];

        return ['status' => $status, 'label' => $labels[$status], 'submission' => $s];
    }

    /** @return list<array{name: string, url: string}> */
    public static function pdfLinks(Submission $s): array
    {
        $disk = Storage::disk(config('filesystems.private'));

        return collect($s->pdf_paths ?? [])->map(fn ($path) => [
            'name' => basename($path), 'url' => $disk->temporaryUrl($path, now()->addMinutes(5)),
        ])->values()->all();
    }

    public function canManage(): bool
    {
        return (bool) auth()->user()->can(StaPermission::ManageGames->value);
    }

    public function extend(int $associationId, int $phase, string $deadline): void
    {
        abort_unless($this->canManage(), 403);
        abort_unless($this->phases()->contains('phase', $phase), 404);
        $a = $this->record->associations()->find($associationId);
        abort_unless($a, 404);
        try {
            $when = Carbon::createFromFormat('Y-m-d H:i', $deadline, 'Asia/Bahrain');
        } catch (\Throwable) {
            $when = null;
        }
        if (! $when || $when->isPast()) {
            Notification::make()->danger()->title('Give a valid deadline in the future.')->send();

            return;
        }
        $this->guarded(fn () => app(SubmissionWorkflow::class)->extend(
            Submission::for($this->record, $a, $phase), auth()->user(), $when));
    }

    public function reopen(int $associationId, int $phase, ?string $deadline = null): void
    {
        abort_unless($this->canManage(), 403);
        abort_unless($this->phases()->contains('phase', $phase), 404);
        $a = $this->record->associations()->find($associationId);
        abort_unless($a, 404);
        $this->guarded(fn () => app(SubmissionWorkflow::class)->reopen(
            Submission::for($this->record, $a, $phase), auth()->user(), $deadline ? Carbon::parse($deadline, 'Asia/Bahrain') : null));
    }

    private function guarded(callable $fn): void
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
        }
        $this->loadSubmissions();
    }

    public function extendAction(): Action
    {
        return Action::make('extend')
            ->visible(fn () => $this->canManage())
            ->modalHeading('Extend deadline')
            ->schema([DateTimePicker::make('deadline')->required()->seconds(false)])
            ->action(fn (array $data, array $arguments) => $this->extend(
                $arguments['association'], $arguments['phase'], Carbon::parse($data['deadline'])->format('Y-m-d H:i')));
    }

    public function reopenAction(): Action
    {
        return Action::make('reopen')
            ->visible(fn () => $this->canManage())
            ->requiresConfirmation()
            ->modalHeading('Re-open submission')
            ->schema([DateTimePicker::make('deadline')->label('New deadline')->required()->seconds(false)])
            ->action(fn (array $data, array $arguments) => $this->reopen(
                $arguments['association'], $arguments['phase'], Carbon::parse($data['deadline'])->format('Y-m-d H:i')));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editGame')->label('Edit game')->color('gray')
                ->url(fn () => GameResource::getUrl('edit', ['record' => $this->record]))
                ->visible(fn () => $this->canManage()),
            Action::make('reports')->label('Reports')->color('gray')
                ->url(fn () => GameResource::getUrl('reports', ['record' => $this->record]))
                ->visible(fn () => (bool) auth()->user()->can(StaPermission::ViewReports->value)),
            Action::make('remindPending')
                ->label('Remind pending')
                ->visible(fn () => $this->record->isPublished() && auth()->user()->can(StaPermission::SendReminders->value))
                ->schema([Select::make('phase')->required()->options(
                    fn () => $this->phases()->where('status', PhaseStatus::Open)
                        ->mapWithKeys(fn ($p) => [$p->phase => "Phase {$p->phase} - ".GamePhase::NAMES[$p->phase]])->all())])
                ->action(function (array $data) {
                    $phase = $this->record->phase((int) $data['phase']);
                    $n = 0;
                    foreach ($this->record->associations as $a) {
                        $c = $this->cell($a, $phase->phase);
                        if ($c['status'] === 'submitted') {
                            continue;
                        }
                        $deadline = $phase->effectiveDeadline($c['submission']);
                        NotificationFacade::send(Recipients::association($a),
                            new PhaseReminder($phase, $a, $deadline !== null && now()->greaterThan($deadline)));
                        $n++;
                    }
                    Notification::make()->success()->title("Reminder sent to {$n} associations")->send();
                }),
        ];
    }
}
