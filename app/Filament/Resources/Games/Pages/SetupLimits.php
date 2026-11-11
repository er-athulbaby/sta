<?php

namespace App\Filament\Resources\Games\Pages;

use App\Enums\EventGender;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\Concerns\SetupPage;
use App\Models\Entry;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\NumberEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Setup screen 2 (canvas SetupLimits): Phase 2 limits, age eligibility, entry policy and offered events per sport. */
class SetupLimits extends Page
{
    use SetupPage;

    protected static string $resource = GameResource::class;

    protected const STEP = 'setup-limits';

    protected string $view = 'filament.resources.games.setup.limits';

    public ?int $sportId = null;

    public ?int $men = null;

    public ?int $women = null;

    public ?int $total = null;

    public ?string $dobFrom = null;

    public ?string $dobTo = null;

    public ?string $policy = null;

    /** @var array<int, array{name: string, gender: string, kind: string, offered: bool, max: ?int}> keyed by event id */
    public array $events = [];

    /** @return Collection<int, GameSport> */
    public function gameSports(): Collection
    {
        return $this->record->gameSports()->with('sport.association')->get()->sortBy('sport.name')->values();
    }

    protected function loadState(): void
    {
        $first = $this->gameSports()->first();
        $this->sportId = $first?->id;
        $this->fillSport();
    }

    public function select(int $id): void
    {
        abort_unless($this->record->gameSports()->whereKey($id)->exists(), 404);
        $this->sportId = $id;
        $this->fillSport();
    }

    public function current(): ?GameSport
    {
        return $this->sportId ? $this->record->gameSports()->with('sport.association')->find($this->sportId) : null;
    }

    private function fillSport(): void
    {
        $gs = $this->current();
        $this->men = $gs?->p2_max_men;
        $this->women = $gs?->p2_max_women;
        $this->total = $gs?->p2_max_total;
        $this->dobFrom = $gs?->dob_from?->format('Y-m-d');
        $this->dobTo = $gs?->dob_to?->format('Y-m-d');
        $this->policy = $gs?->entry_policy;
        $this->events = [];
        if (! $gs) {
            return;
        }
        $have = $gs->gameEvents()->get()->keyBy('event_id');
        $events = $gs->sport->events()->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $have->keys()))->orderBy('name')->get();
        foreach ($events as $e) {
            $this->events[$e->id] = ['name' => $e->name, 'gender' => $e->gender->label(), 'kind' => $e->gender->value,
                'offered' => $have->has($e->id), 'max' => $have->get($e->id)?->p2_max_athletes];
        }
    }

    public function bump(string $field, int $delta): void
    {
        abort_unless(in_array($field, ['men', 'women', 'total'], true), 404);
        $this->{$field} = self::step($this->{$field}, $delta);
    }

    public function toggleEvent(int $eventId): void
    {
        if (isset($this->events[$eventId])) {
            $this->events[$eventId]['offered'] = ! $this->events[$eventId]['offered'];
        }
    }

    public function bumpEvent(int $eventId, int $delta): void
    {
        if (isset($this->events[$eventId]) && $this->events[$eventId]['offered']) {
            $this->events[$eventId]['max'] = self::step($this->events[$eventId]['max'], $delta, 1);
        }
    }

    /** Same wording as the canvas: why the limits cannot all be reached. */
    public function warning(): ?string
    {
        $cap = ['men' => 0, 'women' => 0];
        foreach ($this->events as $e) {
            if ($e['offered'] && isset($cap[$e['kind']])) {
                $cap[$e['kind']] += $e['max'] ?? 0;
            }
        }
        $men = $this->men ?? 0;
        $women = $this->women ?? 0;

        return match (true) {
            $this->total !== null && $men + $women < $this->total => "Max total ({$this->total}) is more than male + female (".($men + $women).'), so the total can never be reached.',
            $this->men !== null && $cap['men'] < $this->men && $cap['men'] > 0 => "Offered male events allow only {$cap['men']} athletes, below the male limit of {$this->men}.",
            $this->women !== null && $cap['women'] < $this->women && $cap['women'] > 0 => "Offered female events allow only {$cap['women']} athletes, below the female limit of {$this->women}.",
            default => null,
        };
    }

    /** Canvas pill of a sport in the left list: Editing, Set or Needs limits. */
    public function stateOf(GameSport $gs): array
    {
        if ($gs->id === $this->sportId) {
            return ['Editing', 'open'];
        }

        return $gs->p2_max_men !== null || $gs->p2_max_women !== null || $gs->p2_max_total !== null ? ['Set', 'submitted'] : ['Needs limits', 'warn'];
    }

    public function save(): void
    {
        $gs = $this->current();
        if (! $gs) {
            return;
        }
        $this->validate([
            'men' => ['nullable', 'integer', 'min:0'], 'women' => ['nullable', 'integer', 'min:0'], 'total' => ['nullable', 'integer', 'min:0'],
            'dobFrom' => ['nullable', 'date'], 'dobTo' => ['nullable', 'date', 'after_or_equal:dobFrom'],
            'policy' => ['nullable', 'string', 'max:5000'],
        ]);
        $kept = [];
        DB::transaction(function () use ($gs, &$kept) {
            $gs->update(['p2_max_men' => $this->men, 'p2_max_women' => $this->women, 'p2_max_total' => $this->total,
                'dob_from' => $this->dobFrom ?: null, 'dob_to' => $this->dobTo ?: null, 'entry_policy' => $this->policy ?: null]);
            $have = $gs->gameEvents()->get()->keyBy('event_id');
            foreach ($this->events as $eventId => $row) {
                $ge = $have->get($eventId);
                if ($row['offered'] && ! $ge) {
                    GameEvent::create(['game_id' => $gs->game_id, 'game_sport_id' => $gs->id, 'event_id' => $eventId, 'p2_max_athletes' => $row['max']]);
                } elseif ($row['offered']) {
                    $ge->update(['p2_max_athletes' => $row['max']]);
                } elseif ($ge) {
                    // Never delete a game event that has entries (deleting cascades to them).
                    if (Entry::withoutGlobalScopes()->where('game_event_id', $ge->id)->exists() || NumberEntry::where('game_event_id', $ge->id)->exists()) {
                        $kept[] = $row['name'];
                    } else {
                        $ge->delete();
                    }
                }
            }
        });
        $this->fillSport();
        if ($kept) {
            Notification::make()->warning()->title('Events with entries stay offered')->body(implode(', ', $kept))->send();
        }
    }

    public function saveSport(): void
    {
        $this->save();
        Notification::make()->success()->title('Saved '.$this->current()?->sport->name)->send();
    }

    public function copyLimitsAction(): Action
    {
        return Action::make('copyLimits')->label('Copy limits from another game')
            ->modalDescription('Fills this sport\'s limits, ages, policy and event maximums from the same sport in the chosen game. Nothing is saved until you press Save.')
            ->schema([Select::make('game_id')->label('Game')->required()->options(fn () => Game::whereKeyNot($this->record->id)
                ->whereHas('gameSports', fn ($q) => $q->where('sport_id', $this->current()?->sport_id))->orderBy('name')->pluck('name', 'id'))])
            ->action(function (array $data) {
                $from = GameSport::where('game_id', $data['game_id'])->where('sport_id', $this->current()?->sport_id)->with('gameEvents')->firstOrFail();
                $this->men = $from->p2_max_men;
                $this->women = $from->p2_max_women;
                $this->total = $from->p2_max_total;
                $this->dobFrom = $from->dob_from?->format('Y-m-d');
                $this->dobTo = $from->dob_to?->format('Y-m-d');
                $this->policy = $from->entry_policy;
                $theirs = $from->gameEvents->keyBy('event_id');
                foreach ($this->events as $eventId => $row) {
                    $this->events[$eventId]['offered'] = $theirs->has($eventId);
                    $this->events[$eventId]['max'] = $theirs->get($eventId)?->p2_max_athletes;
                }
            });
    }

    public function eventGenderLabel(string $kind): string
    {
        return EventGender::from($kind)->label();
    }
}
