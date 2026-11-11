<?php

namespace App\Filament\Resources\Games\Pages;

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\Concerns\SetupPage;
use App\Models\GamePhase;
use App\Models\Submission;
use App\Notifications\PhaseOpened;
use App\Services\Phases\Recipients;
use App\Services\Phases\SubmissionWorkflow;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Validation\ValidationException;

/** Setup screen 4 (canvas SetupPhases): turn phases on, set dates, open and close them, reminders and extensions. */
class SetupPhases extends Page
{
    use SetupPage;

    protected static string $resource = GameResource::class;

    protected const STEP = 'setup-phases';

    protected const TZ = 'Asia/Bahrain';

    /** Canvas wording of what each phase is. */
    public const WHAT = [
        1 => 'Associations answer Yes / No / Maybe for each invited sport. Sports answered No are hidden from them later.',
        2 => 'Associations enter how many athletes per event, within the event, male, female and total limits.',
        3 => 'Associations pick athletes for each event and officials for each sport, with clothing sizes. PDF on approval.',
        4 => 'Associations reduce the Long List to the maximums. Going over needs one justification.',
    ];

    protected string $view = 'filament.resources.games.setup.phases';

    /** Every phase edit is saved at once; there is nothing pending to save. */
    public function save(): void {}

    public function saveDraft(): void
    {
        Notification::make()->info()->title('No changes to save')->body('Changes on this screen are saved as you make them.')->send();
    }

    /** @return Collection<int, GamePhase> */
    public function phases(): Collection
    {
        return $this->record->phases()->orderBy('phase')->get();
    }

    public function anyOpen(): bool
    {
        return $this->record->phases()->where('status', PhaseStatus::Open)->exists();
    }

    /** What the phase card says about the associations' progress. */
    public function progress(GamePhase $p): string
    {
        if (! $p->enabled) {
            return 'Turned off for this game — associations skip it.';
        }
        $subs = Submission::withoutGlobalScopes()->where('game_id', $this->record->id)->where('phase', $p->phase)->get();
        if ($p->status === PhaseStatus::Draft && $subs->isEmpty()) {
            return match ($p->phase) {
                1 => 'Optional — often skipped when STA already knows who is coming.',
                4 => 'Opens after the Long List closes',
                default => 'Not opened yet',
            };
        }
        $invited = $this->record->associations()->count();
        $by = fn (SubmissionStatus $s) => $subs->where('status', $s)->count();
        $text = [];
        $text[] = $p->status === PhaseStatus::Open
            ? $by(SubmissionStatus::Submitted).' submitted'
            : $by(SubmissionStatus::Submitted)." of {$invited} submitted";
        if ($p->status === PhaseStatus::Open) {
            $text[] = $by(SubmissionStatus::AwaitingAssocAdmin).' awaiting admin';
            $text[] = $by(SubmissionStatus::Draft).' in progress';
        }
        if ($p->phase === 2 && $by(SubmissionStatus::Submitted) > 0) {
            $text[] = 'Entry by Event PDFs ready';
        }

        return implode(' · ', $text);
    }

    /** @return array{label: string, hint: string, disabled: bool, kind: string} the card's main button */
    public function button(GamePhase $p): array
    {
        $open = $this->anyOpen();
        if ($p->status === PhaseStatus::Open) {
            return ['label' => 'Close phase', 'hint' => 'Associations can no longer edit after closing', 'disabled' => false, 'kind' => 'danger'];
        }
        $reason = match (true) {
            ! $this->record->isPublished() => 'Publish the game first',
            $p->deadline === null => 'Set a deadline first',
            $open => $p->status === PhaseStatus::Closed ? 'Close the open phase first' : 'Another phase is open',
            default => null,
        };
        $closed = $p->status === PhaseStatus::Closed;

        return ['label' => $closed ? 'Re-open' : 'Open phase', 'disabled' => $reason !== null, 'kind' => $closed ? 'secondary' : 'primary',
            'hint' => $reason ?? ($closed ? 'Lets every association edit again' : 'Emails all '.$this->record->associations()->count().' invited associations')];
    }

    public function toggle(int $phase): void
    {
        $p = $this->record->phase($phase);
        $p->update(['enabled' => ! $p->enabled]);
    }

    /** $field is opens_at or deadline; $value a date. A deadline is kept while the phase is open. */
    public function setDate(int $phase, string $field, ?string $value): void
    {
        abort_unless(in_array($field, ['opens_at', 'deadline'], true), 404);
        $p = $this->record->phase($phase);
        try {
            $when = filled($value) ? ($field === 'deadline' ? Carbon::parse($value, self::TZ)->setTime(23, 59) : Carbon::parse($value, self::TZ)->startOfDay()) : null;
        } catch (\Throwable) {
            Notification::make()->danger()->title('Give a valid date.')->send();

            return;
        }
        if ($field === 'deadline' && $when === null && $p->status === PhaseStatus::Open) {
            Notification::make()->danger()->title('An open phase needs a deadline.')->send();

            return;
        }
        $opens = $field === 'opens_at' ? $when : $p->opens_at;
        $deadline = $field === 'deadline' ? $when : $p->deadline;
        if ($opens && $deadline && ! $deadline->isAfter($opens)) {
            Notification::make()->danger()->title('The deadline must be after the opening date.')->send();

            return;
        }
        $p->update([$field => $when?->timezone(config('app.timezone'))]);
    }

    public function toggleReminder(int $days): void
    {
        if ($days < 0 || $days > 60) {
            Notification::make()->danger()->title('Reminder days must be between 0 and 60.')->send();

            return;
        }
        $have = collect($this->record->reminder_days ?? []);
        if ($have->contains($days) && $have->count() === 1) {
            Notification::make()->danger()->title('Keep at least one reminder day.')->send();

            return;
        }
        $this->record->update(['reminder_days' => ($have->contains($days) ? $have->reject(fn ($d) => $d === $days) : $have->push($days))
            ->map(fn ($d) => (int) $d)->unique()->sortDesc()->values()->all()]);
    }

    /** @return list<array{days: int, label: string, on: bool}> */
    public function reminders(): array
    {
        $on = collect($this->record->reminder_days ?? [])->map(fn ($d) => (int) $d);

        return $on->merge([7, 3, 1, 0])->unique()->sortDesc()->values()->map(fn (int $d) => [
            'days' => $d, 'on' => $on->contains($d),
            'label' => match (true) {
                $d === 0 => 'On the deadline day', $d === 1 => '1 day before', default => "{$d} days before"
            },
        ])->all();
    }

    /** Submissions whose deadline differs from the phase's, for the extensions table. */
    public function extensions(): Collection
    {
        return Submission::withoutGlobalScopes()->where('game_id', $this->record->id)->whereNotNull('deadline_override')
            ->with('association')->orderBy('phase')->get()->sortBy(fn ($s) => $s->association->name)->values();
    }

    public function openPhaseAction(): Action
    {
        return Action::make('openPhase')->requiresConfirmation()
            ->action(function (array $arguments) {
                $r = $this->record->phase((int) $arguments['phase']);
                if (! $r->enabled || $r->deadline === null || ! $this->record->isPublished() || $this->anyOpen()) {
                    Notification::make()->danger()->title('This phase cannot be opened now.')->send();

                    return;
                }
                $r->update(['status' => PhaseStatus::Open, 'opens_at' => $r->opens_at ?? now()]);
                foreach ($this->record->associations as $association) {
                    NotificationFacade::send(Recipients::association($association), new PhaseOpened($r));
                }
            });
    }

    public function closePhaseAction(): Action
    {
        return Action::make('closePhase')->requiresConfirmation()->color('danger')
            ->action(function (array $arguments) {
                $r = $this->record->phase((int) $arguments['phase']);
                if ($r->status === PhaseStatus::Open) {
                    $r->update(['status' => PhaseStatus::Closed]);
                }
            });
    }

    public function addExtensionAction(): Action
    {
        return Action::make('addExtension')->label('Add extension')->modalHeading('Add extension')
            ->schema([
                Select::make('type')->label('What to do')->required()->default('extend')->options([
                    'extend' => 'Extend the deadline (not yet submitted)', 'reopen' => 'Re-open a submitted phase for corrections']),
                Select::make('association')->required()->options(fn () => $this->record->associations()->orderBy('name')->pluck('associations.name', 'associations.id')),
                Select::make('phase')->required()->options(fn () => $this->record->phases()->where('enabled', true)->orderBy('phase')
                    ->get()->mapWithKeys(fn ($p) => [$p->phase => "Phase {$p->phase} · ".GamePhase::NAMES[$p->phase]])),
                DateTimePicker::make('deadline')->label('New deadline')->required()->seconds(false)->after('now'),
                TextInput::make('note')->maxLength(255),
            ])
            ->action(function (array $data) {
                $a = $this->record->associations()->find($data['association']);
                abort_unless($a && $this->record->phases()->where('enabled', true)->where('phase', $data['phase'])->exists(), 404);
                $reopen = $data['type'] === 'reopen';
                $s = Submission::for($this->record, $a, (int) $data['phase']);
                $when = Carbon::parse($data['deadline'], self::TZ);
                try {
                    $note = $data['note'] ?? null;
                    $reopen ? app(SubmissionWorkflow::class)->reopen($s, auth()->user(), $when, $note) : app(SubmissionWorkflow::class)->extend($s, auth()->user(), $when, $note);
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
                }
            });
    }
}
