<?php

namespace App\Filament\Portal\Concerns;

use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Filament\Shared\PersonForm;
use App\Models\GameSport;
use App\Models\Person;
use App\Services\Phases\LongListChecks;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Canvas LongList.dc.html side panel (portal Long List pages only; STA pages never use it): pick a registry person
 * for the Long List, or create one with the canvas short form (registry rules: PersonForm::shortFormRules) and optionally
 * put them on the list. Uses EditsPhase3's chip methods and save(), so the Long List rules stay in one place.
 */
trait HasPersonPanel
{
    /** Columns the short form writes; anything else in $newPerson is ignored. */
    private const SHORT_FORM_FIELDS = ['function', 'first_name', 'last_name', 'gender', 'dob', 'nationality', 'phone', 'email',
        'passport_no', 'passport_expiry', 'cpr_no', 'cpr_expiry'];

    /** 'athlete' | 'official' while the panel is open */
    public ?string $panel = null;

    public ?int $panelSport = null;

    public string $panelSearch = '';

    /** @var array<string, mixed> the short form (SHORT_FORM_FIELDS and sizes) */
    public array $newPerson = [];

    public ?string $newPersonEvent = null;

    /** @var TemporaryUploadedFile|null */
    public $newPersonPhoto = null;

    /** @var TemporaryUploadedFile|null */
    public $newPersonPassportScan = null;

    /** @var TemporaryUploadedFile|null */
    public $newPersonCprScan = null;

    public function openPanel(string $type, ?int $gameSportId = null): void
    {
        if ($this->readOnlyReason !== null || ! in_array($type, ['athlete', 'official'], true)) {
            return;
        }
        $sports = $this->visibleSports();
        $this->panel = $type;
        $this->panelSport = $sports->contains('id', $gameSportId) ? $gameSportId : ($sports->count() === 1 ? $sports->first()->id : null);
        $this->panelSearch = '';
        $this->resetShortForm();
    }

    public function closePanel(): void
    {
        $this->panel = null;
        $this->resetShortForm();
    }

    private function resetShortForm(): void
    {
        $this->newPerson = ['sizes' => []];
        $this->newPersonEvent = null;
        $this->newPersonPhoto = $this->newPersonPassportScan = $this->newPersonCprScan = null;
        $this->resetErrorBag();
    }

    public function panelType(): PersonType
    {
        return $this->panel === 'official' ? PersonType::Official : PersonType::Athlete;
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

    /** @return list<string> what the entered person would still miss (Person::missingFields plus empty sizes), for the canvas banner */
    public function shortFormMissing(): array
    {
        $type = $this->panelType();
        $p = new Person(Arr::only($this->newPerson, ['first_name', 'last_name', 'function', 'nationality', 'phone', 'email', 'cpr_no']));
        $p->type = $type;
        $p->photo_path = $this->newPersonPhoto ? 'new' : null;
        $p->passport_scan_path = $this->newPersonPassportScan ? 'new' : null;
        $p->setRelation('fieldValues', collect());
        $sizes = PersonForm::sizeItems($type)->filter(fn ($item) => blank($this->newPerson['sizes'][$item->id] ?? null))->map(fn ($item) => $item->name.' size');

        return [...$p->missingFields(), ...($this->newPersonCprScan ? [] : ['CPR scan']), ...$sizes];
    }

    /** "Save as draft" saves the person to the registry; "Save and add to Long List" also puts them on the list and saves the list draft. */
    public function createPerson(bool $addToList = false): void
    {
        if ($this->panel === null || $this->readOnlyReason !== null) {
            return;
        }
        $type = $this->panelType();
        Validator::make(
            ['newPerson' => $this->newPerson, 'newPersonPhoto' => $this->newPersonPhoto, 'newPersonPassportScan' => $this->newPersonPassportScan, 'newPersonCprScan' => $this->newPersonCprScan],
            PersonForm::shortFormRules($type, 'newPerson.', 'newPerson'),
            attributes: collect(self::SHORT_FORM_FIELDS)->mapWithKeys(fn ($f) => ["newPerson.$f" => str_replace('_', ' ', $f)])->all(),
        )->validate();
        if ($addToList) {
            $this->assertListTarget($type);
        }
        $data = collect(Arr::only($this->newPerson, self::SHORT_FORM_FIELDS))->map(fn ($v) => filled($v) ? $v : null)->all();
        $sizes = array_filter(Arr::wrap($this->newPerson['sizes'] ?? []), 'filled');
        $store = fn ($file, string $dir) => $file?->store($dir, ['disk' => config('filesystems.private'), 'visibility' => 'private']) ?: null;
        $files = ['photo_path' => $store($this->newPersonPhoto, 'photos'), 'passport_scan_path' => $store($this->newPersonPassportScan, 'scans'),
            'cpr_front_path' => $store($this->newPersonCprScan, 'scans')];

        // Like the registry Create page: sizes commit with the row; the association is never taken from the form;
        // RenamesAttachments renames the stored files after the person is saved.
        $person = DB::transaction(function () use ($data, $files, $sizes, $type) {
            $person = Person::create([...$data, ...$files, 'type' => $type->value, 'is_active' => true, 'association_id' => $this->association()->getKey()]);
            $person->syncSizes($sizes);

            return $person;
        });
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
