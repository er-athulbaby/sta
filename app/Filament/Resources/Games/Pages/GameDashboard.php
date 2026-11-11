<?php

namespace App\Filament\Resources\Games\Pages;

use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Exports\LiteralRow;
use App\Filament\Resources\Games\GameResource;
use App\Models\Association;
use App\Models\GamePhase;
use App\Models\Submission;
use App\Notifications\PhaseReminder;
use App\Services\Phases\Recipients;
use App\Services\Phases\SubmissionWorkflow;
use App\Support\GameProgress;
use App\Support\Sta;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Canvas Main.dc.html (spec 17): header, phase cards, the focus phase KPIs and the "Who has submitted" table
 * (All / Pending / Submitted switch, search, flags, last activity, Open / Remind).
 */
class GameDashboard extends Page
{
    use InteractsWithRecord;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.resources.games.pages.game-dashboard';

    /** all | pending | submitted (canvas switch, ruling U4). */
    public string $show = 'all';

    public string $search = '';

    protected ?GameProgress $progress = null;

    /** @var array{all: int, pending: int, submitted: int}|null */
    protected ?array $tally = null;

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can(StaPermission::ViewSubmissions->value);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getPageClasses(): array
    {
        return ['sta-canvas-page', 'sta-dashboard-page'];
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHeader(): ?View
    {
        return view('filament.resources.games.pages.game-dashboard-header');
    }

    /** "20 Nov – 30 Nov 2026 · 50 associations invited · 22 sports" (the game has no place field). */
    public function getSubheading(): ?string
    {
        $game = $this->progress()->game;
        $dates = $game->start_date ? $game->start_date->format('j M').' – '.$game->end_date?->format('j M Y') : null;

        return collect([$dates, $game->associations->count().' associations invited', $game->gameSports->count().' sports'])->filter()->implode(' · ');
    }

    #[Unexposed]
    public function progress(): GameProgress
    {
        return $this->progress ??= new GameProgress($this->record);
    }

    public function loadSubmissions(): void
    {
        $this->progress = null;
        $this->tally = null;
    }

    /** @return Collection<int, GamePhase> enabled phases, in order */
    #[Unexposed]
    public function phases(): Collection
    {
        return $this->progress()->game->phases->where('enabled', true)->sortBy('phase')->values();
    }

    /** @return Collection<int, GamePhase> the table's phase columns: enabled phases that have been opened (canvas Main) */
    #[Unexposed]
    public function tablePhases(): Collection
    {
        return $this->phases()->where('status', '!==', PhaseStatus::Draft)->values();
    }

    /** @return Collection<int, Association> */
    #[Unexposed]
    public function rows(): Collection
    {
        $needle = mb_strtolower(trim($this->search));

        return $this->progress()->game->associations->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->when($this->show === 'pending', fn ($c) => $c->filter(fn ($a) => $this->isPending($a)))
            ->when($this->show === 'submitted', fn ($c) => $c->filter(fn ($a) => $this->isSubmitted($a)))
            ->when($needle !== '', fn ($c) => $c->filter(fn ($a) => str_contains(mb_strtolower($a->name.' '.$a->code), $needle)))
            ->values();
    }

    /** Pending: not submitted in at least one open phase. */
    private function isPending(Association $a): bool
    {
        return $this->progress()->openPhases()->contains(fn ($p) => $this->progress()->status($a, $p->phase) !== 'submitted');
    }

    /** Submitted: every open phase submitted; with no open phase an association is neither (ruling U4). */
    private function isSubmitted(Association $a): bool
    {
        $open = $this->progress()->openPhases();

        return $open->isNotEmpty() && ! $this->isPending($a);
    }

    /** @return array{all: int, pending: int, submitted: int} counts for the switch, ignoring the current filter */
    #[Unexposed]
    public function tally(): array
    {
        return $this->tally ??= (function () {
            $all = $this->progress()->game->associations;

            return ['all' => $all->count(), 'pending' => $all->filter(fn ($a) => $this->isPending($a))->count(),
                'submitted' => $all->filter(fn ($a) => $this->isSubmitted($a))->count()];
        })();
    }

    /** @return array{status: string, label: string, submission: ?Submission} */
    #[Unexposed]
    public function cell(Association $a, int $phase): array
    {
        $status = $this->progress()->status($a, $phase);

        return ['status' => $status, 'label' => Sta::CELL_LABELS[$status], 'submission' => $this->progress()->submission($a, $phase)];
    }

    /** The phase the KPIs, flags and Action column follow: the first open phase, else the last opened one. */
    #[Unexposed]
    public function focusPhase(): ?GamePhase
    {
        return $this->progress()->openPhase() ?? $this->tablePhases()->last() ?? $this->phases()->first();
    }

    /** The phase card meta line (canvas Main). */
    #[Unexposed]
    public function phaseMeta(GamePhase $p): string
    {
        if (! $p->enabled) {
            return 'Not used for this game';
        }

        return match ($p->status) {
            PhaseStatus::Open => $p->deadline ? 'Deadline '.$p->deadline->timezone('Asia/Bahrain')->format('j M Y').' · '.Sta::daysLeft($p->deadline) : 'Open · no deadline',
            PhaseStatus::Closed => (function () use ($p) {
                $c = $this->progress()->counts($p->phase);
                $closed = Activity::query()->where('subject_type', $p->getMorphClass())->where('subject_id', $p->id)
                    ->where('properties->attributes->status', PhaseStatus::Closed->value)->latest('id')->value('created_at');

                return 'Closed'.($closed ? ' '.Carbon::parse($closed)->timezone('Asia/Bahrain')->format('j M') : '')." · {$c['submitted']} of {$c['total']} submitted";
            })(),
            PhaseStatus::Draft => match (true) {
                $p->opens_at !== null => 'Opens '.$p->opens_at->timezone('Asia/Bahrain')->format('j M Y'),
                ($prev = $this->phases()->where('phase', '<', $p->phase)->last()) !== null => "Opens after Phase {$prev->phase} closes",
                default => 'Not opened yet',
            },
        };
    }

    /** "Phase 3 is open · reminders go out automatically 7, 3 and 0 days before the deadline" */
    #[Unexposed]
    public function tableDescription(): ?string
    {
        $focus = $this->focusPhase();
        if ($focus === null) {
            return null;
        }
        $days = array_map('intval', $this->record->reminder_days ?? []);
        $text = "Phase {$focus->phase} is ".strtolower(Sta::phaseStatus($focus)[0]);
        if ($days) {
            $last = array_pop($days);
            $text .= ' · reminders go out automatically '.($days ? implode(', ', $days).' and ' : '').$last.' days before the deadline';
        }

        return $text;
    }

    #[Unexposed]
    public function flagCount(): int
    {
        $focus = $this->focusPhase();

        return $focus ? $this->progress()->game->associations->filter(fn ($a) => $this->progress()->flags($a, $focus->phase) !== null)->count() : 0;
    }

    #[Unexposed]
    public function sportsOf(Association $a): string
    {
        return $this->progress()->sportsOf($a);
    }

    /** @return list<array{name: string, url: string}> */
    public static function pdfLinks(Submission $s): array
    {
        $disk = Storage::disk(config('filesystems.private'));

        return collect($s->pdf_paths ?? [])->map(fn ($path) => [
            'name' => basename($path), 'url' => $disk->temporaryUrl($path, now()->addMinutes(5)),
        ])->values()->all();
    }

    #[Unexposed]
    public function canManage(): bool
    {
        return (bool) auth()->user()->can(StaPermission::ManageGames->value);
    }

    #[Unexposed]
    public function canRemind(): bool
    {
        return $this->record->isPublished() && (bool) auth()->user()->can(StaPermission::SendReminders->value);
    }

    #[Unexposed]
    public function canExport(): bool
    {
        return (bool) auth()->user()->can(StaPermission::ViewReports->value);
    }

    public function extend(int $associationId, int $phase, string $deadline, ?string $note = null): void
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
            Submission::for($this->record, $a, $phase), auth()->user(), $when, $note));
    }

    public function reopen(int $associationId, int $phase, ?string $deadline = null, ?string $note = null): void
    {
        abort_unless($this->canManage(), 403);
        abort_unless($this->phases()->contains('phase', $phase), 404);
        $a = $this->record->associations()->find($associationId);
        abort_unless($a, 404);
        $this->guarded(fn () => app(SubmissionWorkflow::class)->reopen(
            Submission::for($this->record, $a, $phase), auth()->user(), $deadline ? Carbon::parse($deadline, 'Asia/Bahrain') : null, $note));
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
            ->schema([DateTimePicker::make('deadline')->required()->seconds(false), TextInput::make('note')->maxLength(255)])
            ->action(fn (array $data, array $arguments) => $this->extend(
                $arguments['association'], $arguments['phase'], Carbon::parse($data['deadline'])->format('Y-m-d H:i'), $data['note'] ?? null));
    }

    public function reopenAction(): Action
    {
        return Action::make('reopen')
            ->visible(fn () => $this->canManage())
            ->requiresConfirmation()
            ->modalHeading('Re-open submission')
            ->schema([DateTimePicker::make('deadline')->label('New deadline')->required()->seconds(false), TextInput::make('note')->maxLength(255)])
            ->action(fn (array $data, array $arguments) => $this->reopen(
                $arguments['association'], $arguments['phase'], Carbon::parse($data['deadline'])->format('Y-m-d H:i'), $data['note'] ?? null));
    }

    /** The row's "Remind" (canvas Action column): the Remind pending reminder for one association and the focus phase. */
    public function remindOneAction(): Action
    {
        return Action::make('remindOne')
            ->visible(fn () => $this->canRemind())
            ->requiresConfirmation()
            ->modalHeading('Send a reminder')
            ->modalSubmitActionLabel('Send reminder')
            ->action(function (array $arguments) {
                abort_unless($this->canRemind(), 403);
                $phase = $this->progress()->openPhase();
                $a = $this->record->associations()->find($arguments['association'] ?? 0);
                abort_unless($phase && $a, 404);
                $n = $this->remind($phase, collect([$a]));
                Notification::make()->success()->title($n ? "Reminder sent to {$a->name}" : 'Nothing to remind: already submitted.')->send();
            });
    }

    /** Sends the phase reminder to each association that has not submitted; returns how many were reminded. */
    private function remind(GamePhase $phase, Collection $associations): int
    {
        $n = 0;
        foreach ($associations as $a) {
            $c = $this->cell($a, $phase->phase);
            if ($c['status'] === 'submitted') {
                continue;
            }
            $deadline = $phase->effectiveDeadline($c['submission']);
            NotificationFacade::send(Recipients::association($a), new PhaseReminder($phase, $a, $deadline !== null && now()->greaterThan($deadline)));
            $n++;
        }

        return $n;
    }

    /** Export Excel: the table as shown (switch and search applied). */
    public function exportExcel(): StreamedResponse
    {
        abort_unless($this->canExport(), 403);
        $phases = $this->tablePhases();
        $focus = $this->focusPhase();
        $progress = $this->progress();
        $lines = $this->rows()->map(function (Association $a) use ($phases, $focus, $progress) {
            $last = $progress->lastActivity($a);

            return [$a->name, $a->code ?? '', $this->sportsOf($a),
                ...$phases->map(fn ($p) => $this->cell($a, $p->phase)['label'])->all(),
                ($focus ? $progress->flags($a, $focus->phase) : null) ?? 'None',
                $last ? $last->copy()->timezone('Asia/Bahrain')->format('Y-m-d H:i') : ''];
        });
        $head = ['Association', 'Code', 'Sports', ...$phases->map(fn ($p) => "Phase {$p->phase}")->all(), 'Flags', 'Last activity'];

        return response()->streamDownload(function () use ($head, $lines) {
            $tmp = tempnam(sys_get_temp_dir(), 'gd');
            $writer = new Writer;
            $writer->openToFile($tmp);
            $writer->addRow(LiteralRow::from($head));
            foreach ($lines as $line) {
                $writer->addRow(LiteralRow::from($line));
            }
            $writer->close();
            readfile($tmp);
            unlink($tmp);
        }, 'game-'.$this->record->id.'-dashboard.xlsx');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editGame')->label('Edit game')->color('gray')
                ->url(fn () => GameResource::getUrl('edit', ['record' => $this->record]))
                ->visible(fn () => $this->canManage()),
            Action::make('gameFiles')->label('Game system files')->color('gray')
                ->url(fn () => GameResource::getUrl('export-files', ['record' => $this->record]))
                ->visible(fn () => $this->record->isPublished() && auth()->user()->can(StaPermission::ExportGameFiles->value)),
            Action::make('reports')->label('Reports')->color('gray')
                ->url(fn () => GameResource::getUrl('reports', ['record' => $this->record]))
                ->visible(fn () => (bool) auth()->user()->can(StaPermission::ViewReports->value)),
            Action::make('remindPending')
                // N = who the modal's default phase (the first open one) would remind.
                ->label(function () {
                    $p = $this->progress()->openPhase();
                    $c = $p ? $this->progress()->counts($p->phase) : ['total' => 0, 'submitted' => 0];

                    return 'Remind pending ('.($c['total'] - $c['submitted']).')';
                })
                // No open phase = nothing to remind about (the phase select would be empty).
                ->visible(fn () => $this->canRemind() && $this->progress()->openPhase() !== null)
                ->schema([Select::make('phase')->required()
                    ->default(fn () => $this->progress()->openPhase()?->phase)
                    ->options(fn () => $this->progress()->openPhases()
                        ->mapWithKeys(fn ($p) => [$p->phase => "Phase {$p->phase} - ".GamePhase::NAMES[$p->phase]])->all())])
                ->action(function (array $data) {
                    $phase = $this->record->phase((int) $data['phase']);
                    $n = $this->remind($phase, $this->record->associations);
                    Notification::make()->success()->title("Reminder sent to {$n} associations")->send();
                }),
        ];
    }
}
