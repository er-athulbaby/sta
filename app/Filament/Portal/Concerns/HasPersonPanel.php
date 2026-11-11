<?php

namespace App\Filament\Portal\Concerns;

use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Filament\Shared\PersonForm;
use App\Models\GameSport;
use App\Models\Person;
use App\Services\Phases\LongListChecks;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Canvas LongList.dc.html side panel (portal Long List pages only; STA pages never use it): pick a registry person
 * for the Long List, or create one with the registry person form (spec 16.3) and optionally put them on the list.
 * Uses EditsPhase3's chip methods and save(), so the Long List rules stay in one place.
 */
trait HasPersonPanel
{
    /** 'athlete' | 'official' while the panel is open */
    public ?string $panel = null;

    public ?int $panelSport = null;

    public string $panelSearch = '';

    /** @var array<string, mixed>|null */
    public ?array $newPerson = [];

    public ?string $newPersonEvent = null;

    public function openPanel(string $type, ?int $gameSportId = null): void
    {
        if ($this->readOnlyReason !== null || ! in_array($type, ['athlete', 'official'], true)) {
            return;
        }
        $sports = $this->visibleSports();
        $this->panel = $type;
        $this->panelSport = $sports->contains('id', $gameSportId) ? $gameSportId : ($sports->count() === 1 ? $sports->first()->id : null);
        $this->panelSearch = '';
        $this->newPersonEvent = null;
        $this->resetErrorBag();
        $this->newPersonForm->fill(); // defaults (country codes, active …), then the panel's type
        $this->newPerson['type'] = $type;
    }

    public function closePanel(): void
    {
        $this->panel = null;
        $this->newPerson = [];
    }

    public function panelType(): PersonType
    {
        return $this->panel === 'official' ? PersonType::Official : PersonType::Athlete;
    }

    public function newPersonForm(Schema $schema): Schema
    {
        return PersonForm::configure($schema->statePath('newPerson')->model(Person::class), type: $this->panelType(), lockType: true);
    }

    /** @return Collection<int, GameSport> the panel's sport, or every visible sport when the page has several and none was given */
    public function panelSports(): Collection
    {
        $sports = $this->visibleSports();

        return $this->panelSport ? $sports->where('id', $this->panelSport)->values() : $sports;
    }

    /** Registry people for "Pick from your registry": active, this type, not on the list yet; athletes need an event of the panel's sports. */
    public function panelCandidates(): Collection
    {
        $type = $this->panelType();
        $on = collect($type === PersonType::Athlete ? $this->athletes : $this->officials)->flatten()->map(fn ($id) => (int) $id)->all();
        $events = $this->panelSports()->flatMap->gameEvents;
        $q = mb_strtolower(trim($this->panelSearch));
        $cprHash = $q === '' ? null : Person::blindIndex($q);

        return $this->candidates($type)->load('fieldValues')
            ->reject(fn (Person $p) => in_array($p->id, $on, true))
            ->filter(fn (Person $p) => $type === PersonType::Official || $p->gender !== null && $events->contains(fn ($ge) => LongListChecks::genderMatches($p->gender, $ge->event->gender)))
            ->filter(fn (Person $p) => $q === '' || str_contains(mb_strtolower($p->fullName().' '.$p->code), $q) || $p->cpr_no_hash === $cprHash)
            ->values();
    }

    /** "Save as draft" saves the person to the registry; "Save and add to Long List" also puts them on the list and saves the list draft. */
    public function createPerson(bool $addToList = false): void
    {
        if ($this->panel === null || $this->readOnlyReason !== null) {
            return;
        }
        $type = $this->panelType();
        if ($addToList) {
            $this->assertListTarget($type);
        }
        $data = $this->newPersonForm->getState();
        $data['type'] = $type->value;
        $sizes = $data['sizes'] ?? [];
        $extra = $data['extra'] ?? [];
        unset($data['sizes'], $data['extra']);

        // Same as the registry Create page: sizes and extra values commit with the row; association_id is never taken from the form.
        $person = DB::transaction(function () use ($data, $sizes, $extra) {
            $person = Person::create([...$data, 'association_id' => $this->association()->getKey()]);
            $person->syncSizes($sizes);
            $person->syncExtraValues($extra, auth()->user());

            return $person;
        });
        $this->newPersonForm->model($person)->saveRelationships();
        Notification::make()->success()->title('Saved to the registry')->send();

        if ($addToList) {
            $type === PersonType::Athlete
                ? $this->addAthleteChip((int) $this->newPersonEvent, $person->id)
                : $this->addOfficialChip($this->panelSports()->first()->id, $person->id);
            $this->save();
        }
        $this->closePanel();
    }

    private function assertListTarget(PersonType $type): void
    {
        if ($type === PersonType::Official) {
            if ($this->panelSports()->count() !== 1) {
                throw ValidationException::withMessages(['newPersonEvent' => 'Open the panel from a sport to add a team official to the Long List.']);
            }

            return;
        }
        $gender = PersonGender::tryFrom((string) ($this->newPerson['gender'] ?? ''));
        $ok = $this->panelSports()->flatMap->gameEvents->contains(fn ($ge) => (string) $ge->id === (string) $this->newPersonEvent
            && ($gender === null || LongListChecks::genderMatches($gender, $ge->event->gender)));
        if (! $ok) {
            throw ValidationException::withMessages(['newPersonEvent' => 'Choose an event that matches the athlete\'s gender.']);
        }
    }
}
