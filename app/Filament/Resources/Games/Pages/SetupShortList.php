<?php

namespace App\Filament\Resources\Games\Pages;

use App\Enums\PersonGender;
use App\Filament\Resources\ClothingItems\ClothingItemResource;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\Concerns\SetupPage;
use App\Models\ClothingItem;
use App\Models\GameSport;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Setup screen 3 (canvas SetupShortList): Phase 3 clothing items and Phase 4 Short List maximums. */
class SetupShortList extends Page
{
    use SetupPage;

    protected static string $resource = GameResource::class;

    protected const STEP = 'setup-short-list';

    protected string $view = 'filament.resources.games.setup.short-list';

    /** @var list<int> ticked clothing item ids */
    public array $items = [];

    /** @var array<int, array{men: ?int, women: ?int, officials: ?int}> keyed by game sport id */
    public array $caps = [];

    protected function loadState(): void
    {
        $this->items = $this->record->clothingItems()->pluck('clothing_items.id')->map(fn ($id) => (int) $id)->all();
        $this->caps = $this->gameSports()->mapWithKeys(fn (GameSport $gs) => [$gs->id => [
            'men' => $gs->p4_max_men, 'women' => $gs->p4_max_women, 'officials' => $gs->p4_max_officials]])->all();
    }

    /** @return Collection<int, GameSport> */
    public function gameSports(): Collection
    {
        return $this->record->gameSports()->with('sport.association')->get()->sortBy('sport.name')->values();
    }

    /** @return Collection<int, ClothingItem> active items, plus ticked ones that were deactivated since */
    public function clothingItems(): Collection
    {
        return ClothingItem::query()->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $this->items))->orderBy('name')->get();
    }

    public function toggleItem(int $id): void
    {
        if (in_array($id, $this->items, true)) {
            $this->items = array_values(array_diff($this->items, [$id]));
        } elseif (ClothingItem::whereKey($id)->where('is_active', true)->exists()) {
            $this->items = [...$this->items, $id];
        }
    }

    public function bump(int $gameSportId, string $kind, int $delta): void
    {
        abort_unless(isset($this->caps[$gameSportId]) && in_array($kind, ['men', 'women', 'officials'], true), 404);
        $this->caps[$gameSportId][$kind] = self::step($this->caps[$gameSportId][$kind], $delta);
    }

    /**
     * What the Long Lists hold today, per game sport: male and female athletes (distinct people over the sport's events) and officials.
     *
     * @return array<int, array{men: int, women: int, officials: int}>
     */
    public function longListToday(): array
    {
        $rows = DB::table('entries')
            ->join('submissions', 'submissions.id', '=', 'entries.submission_id')
            ->join('people', 'people.id', '=', 'entries.person_id')
            ->leftJoin('game_events', 'game_events.id', '=', 'entries.game_event_id')
            ->where('submissions.game_id', $this->record->id)->where('submissions.phase', 3)
            ->get(['entries.person_id', 'entries.game_event_id', 'entries.game_sport_id', 'game_events.game_sport_id as event_sport_id', 'people.gender']);
        $out = [];
        foreach ($rows as $r) {
            $kind = $r->game_event_id === null ? 'officials' : ($r->gender === PersonGender::Female->value ? 'women' : ($r->gender === PersonGender::Male->value ? 'men' : null));
            $sport = $r->game_event_id === null ? $r->game_sport_id : $r->event_sport_id;
            if ($kind && $sport) {
                $out[$sport][$kind][$r->person_id] = true;
            }
        }

        return collect($out)->map(fn (array $k) => ['men' => count($k['men'] ?? []), 'women' => count($k['women'] ?? []), 'officials' => count($k['officials'] ?? [])])->all();
    }

    /** @return list<string> e.g. ["Male +1", "officials +2"] */
    public function overBy(int $gameSportId, array $now): array
    {
        $cap = $this->caps[$gameSportId] ?? [];
        $over = [];
        foreach ([['men', 'Male'], ['women', 'Female'], ['officials', 'officials']] as [$k, $label]) {
            if (($cap[$k] ?? null) !== null && $now[$k] > $cap[$k]) {
                $over[] = "{$label} +".($now[$k] - $cap[$k]);
            }
        }

        return $over;
    }

    public function clothingUrl(): string
    {
        return ClothingItemResource::getUrl();
    }

    public function save(): void
    {
        $valid = $this->validate([
            'items' => ['array'], 'items.*' => ['integer', 'exists:clothing_items,id'],
            'caps.*.men' => ['nullable', 'integer', 'min:0'], 'caps.*.women' => ['nullable', 'integer', 'min:0'], 'caps.*.officials' => ['nullable', 'integer', 'min:0'],
        ]);
        // Only active items, or ones already ticked that were deactivated since.
        $allowed = ClothingItem::where('is_active', true)->pluck('id')->merge($this->record->clothingItems()->pluck('clothing_items.id'))->map(fn ($id) => (int) $id);
        $items = collect($valid['items'])->map(fn ($id) => (int) $id)->intersect($allowed)->unique()->values()->all();
        DB::transaction(function () use ($items) {
            $this->record->clothingItems()->sync($items);
            foreach ($this->record->gameSports()->get() as $gs) {
                if (isset($this->caps[$gs->id])) {
                    $c = $this->caps[$gs->id];
                    $gs->update(['p4_max_men' => $c['men'], 'p4_max_women' => $c['women'], 'p4_max_officials' => $c['officials']]);
                }
            }
        });
    }

    public function saveAndContinue()
    {
        $this->save();

        return redirect(GameResource::getUrl('setup-phases', ['record' => $this->record]));
    }
}
