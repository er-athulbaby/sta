<?php

namespace App\Filament\Resources\Sports\Pages;

use App\Enums\EventGender;
use App\Filament\Resources\Sports\SportResource;
use App\Models\Association;
use App\Models\GameEvent;
use App\Models\Sport;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Canvas AdminSports.dc.html: the sports list (Filament table records; the Association select is the table filter) beside
 * the selected sport's details (saved on change, SportForm's rules) and its events (add, Active switch). Events are only
 * deactivated, never deleted (BR-16). Renaming an event stays on the Edit page (events relation manager).
 */
class ListSports extends ListRecords
{
    protected static string $resource = SportResource::class;

    /** The sport the right side shows; null (or filtered out) = the first row. */
    public ?int $selected = null;

    public string $newName = '';

    public string $newGender = 'men';

    public ?string $eventError = null;

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'sta-canvas-page'];
    }

    public function getHeader(): ?ViewContract
    {
        return view('filament.resources.sports.list-header');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([View::make('filament.resources.sports.list')]);
    }

    /** @return Collection<int, string> */
    public function associationOptions(): Collection
    {
        return Association::query()->orderBy('name')->pluck('name', 'id');
    }

    public function selectSport(int $id): void
    {
        $this->selected = $id;
        $this->reset('newName', 'eventError');
        $this->resetErrorBag();
    }

    public function panelSport(): ?Sport
    {
        $records = $this->getTableRecords();

        return ($records->firstWhere('id', $this->selected) ?? $records->first())?->load(['association', 'events' => fn ($q) => $q->orderBy('id')]);
    }

    /** @return Collection<int, int> games per event id (canvas "Used in games") */
    public function gamesUsing(Sport $sport): Collection
    {
        return GameEvent::query()->whereIn('event_id', $sport->events->modelKeys())
            ->selectRaw('event_id, count(distinct game_id) as n')->groupBy('event_id')->pluck('n', 'event_id');
    }

    /** The details card saves each field on change with the Edit page's rules (SportForm). */
    public function saveSport(int $id, string $field, mixed $value): void
    {
        $sport = Sport::query()->findOrFail($id);
        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique('sports', 'name')->ignore($sport->id)],
            'association_id' => ['required', Rule::exists('associations', 'id')],
            'is_active' => ['boolean'],
        ];
        abort_unless(isset($rules[$field]), 404);

        $this->resetErrorBag("sport.{$field}");
        $value = is_string($value) ? trim($value) : $value;
        $v = Validator::make([$field => $value], [$field => $rules[$field]], [], ['name' => 'sport name', 'association_id' => 'association']);
        if ($v->fails()) {
            $this->addError("sport.{$field}", $v->errors()->first($field));

            return;
        }
        $sport->update([$field => $field === 'is_active' ? (bool) $value : $value]);
    }

    public function addEvent(): void
    {
        $sport = $this->panelSport();
        if (! $sport) {
            return;
        }
        $name = trim($this->newName);
        $gender = EventGender::tryFrom($this->newGender);

        $this->eventError = match (true) {
            $name === '' => 'Type an event name first.',
            mb_strlen($name) > 255 => 'The event name may not be longer than 255 characters.',
            $gender === null => 'Choose a gender.',
            $sport->events()->where('name', $name)->where('gender', $gender)->exists() => "{$name} ({$gender->label()}) already exists in this sport.",
            default => null,
        };
        if ($this->eventError !== null) {
            return;
        }

        $sport->events()->create(['name' => $name, 'gender' => $gender, 'is_active' => true]);
        $this->newName = '';
    }

    public function toggleEvent(int $id): void
    {
        $event = $this->panelSport()?->events()->findOrFail($id);
        $event?->update(['is_active' => ! $event->is_active]);
    }
}
