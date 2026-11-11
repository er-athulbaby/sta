<?php

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\Concerns\SetupPage;
use App\Filament\Shared\AttachmentActions;
use App\Models\Association;
use App\Models\GameSport;
use App\Models\Sport;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** Setup screen 1 (canvas SetupInvite): game details, invited associations and their sports. */
class EditGame extends Page
{
    use SetupPage, WithFileUploads;

    protected static string $resource = GameResource::class;

    protected const STEP = 'edit';

    protected string $view = 'filament.resources.games.setup.invite';

    public string $name = '';

    public ?string $start_date = null;

    public ?string $end_date = null;

    public ?TemporaryUploadedFile $logo = null;

    public string $search = '';

    /** @var list<int> */
    public array $invited = [];

    /** @var list<int> sport ids ticked for the game */
    public array $picked = [];

    protected function loadState(): void
    {
        $g = $this->record;
        $this->name = $g->name;
        $this->start_date = $g->start_date?->format('Y-m-d');
        $this->end_date = $g->end_date?->format('Y-m-d');
        $this->invited = $g->associations()->pluck('associations.id')->map(fn ($id) => (int) $id)->all();
        $this->picked = $g->gameSports()->pluck('sport_id')->map(fn ($id) => (int) $id)->all();
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? $this->logo->temporaryUrl() : ($this->record->logo_path ? AttachmentActions::url($this->record->logo_path) : null);
    }

    /** @return Collection<int, Association> active (or already invited) associations with their active sports */
    public function associations()
    {
        return Association::query()->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $this->invited))
            ->with(['sports' => fn ($q) => $q->where('is_active', true)->orderBy('name')])->orderBy('name')->get();
    }

    public function toggleAssociation(int $id): void
    {
        if (in_array($id, $this->invited, true)) {
            $this->invited = array_values(array_diff($this->invited, [$id]));
        } elseif (Association::whereKey($id)->where('is_active', true)->exists()) {
            $this->invited = [...$this->invited, $id];
        }
    }

    public function toggleSport(int $id): void
    {
        if (in_array($id, $this->picked, true)) {
            $this->picked = array_values(array_diff($this->picked, [$id]));
        } elseif (Sport::whereKey($id)->where('is_active', true)->exists()) {
            $this->picked = [...$this->picked, $id];
        }
    }

    /** Ticks every association that matches the search, or clears them when they are all ticked. */
    public function selectAll(): void
    {
        $ids = $this->visible()->pluck('id')->map(fn ($id) => (int) $id);
        $this->invited = $ids->diff($this->invited)->isEmpty()
            ? array_values(array_diff($this->invited, $ids->all()))
            : $ids->merge($this->invited)->unique()->values()->all();
    }

    public function visible()
    {
        $q = mb_strtolower(trim($this->search));

        return $this->associations()->filter(fn (Association $a) => $q === '' || str_contains(mb_strtolower($a->name.' '.$a->code.' '.$a->email), $q));
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:200'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);
        $game = $this->record;
        // Public state can be set from the browser: keep only active associations (or ones already invited).
        $allowed = Association::where('is_active', true)->pluck('id')->merge($game->associations()->pluck('associations.id'))->map(fn ($id) => (int) $id);
        $this->invited = collect($this->invited)->map(fn ($id) => (int) $id)->intersect($allowed)->unique()->values()->all();
        $attributes = ['name' => $data['name'], 'start_date' => $data['start_date'], 'end_date' => $data['end_date']];
        if ($this->logo) {
            $attributes['logo_path'] = $this->logo->store('game-logos', ['disk' => config('filesystems.private'), 'visibility' => 'private']);
        }
        $locked = false;
        DB::transaction(function () use ($game, $attributes, &$locked) {
            $game->update($attributes);
            $game->associations()->sync($this->invited);
            $ticked = Sport::query()->whereIn('association_id', $this->invited)->whereIn('id', $this->picked)->where('is_active', true)->pluck('id')->all();
            $have = $game->gameSports()->with('sport')->get();
            foreach (array_diff($ticked, $have->pluck('sport_id')->all()) as $sportId) {
                GameSport::create(['game_id' => $game->id, 'sport_id' => $sportId])->addMissingEvents();
            }
            // Sports of invited associations that were un-ticked go, unless the game already has submissions.
            foreach ($have->whereIn('sport.association_id', $this->invited)->whereNotIn('sport_id', $this->picked) as $gs) {
                if ($game->submissions()->exists()) {
                    $locked = true;
                } else {
                    $gs->delete();
                }
            }
        });
        $this->logo = null;
        $this->loadState();
        if ($locked) {
            Notification::make()->warning()->title('Sports cannot be removed once the game has submissions.')->send();
        }
    }

    public function saveAndContinue()
    {
        $this->save();

        return redirect(GameResource::getUrl('setup-limits', ['record' => $this->record]));
    }
}
