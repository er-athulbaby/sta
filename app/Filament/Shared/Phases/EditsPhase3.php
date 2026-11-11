<?php

namespace App\Filament\Shared\Phases;

use App\Enums\PersonType;
use App\Filament\Shared\AttachmentActions;
use App\Models\Association;
use App\Models\GameSport;
use App\Models\LongListDetail;
use App\Models\Person;
use App\Services\Phases\LongListChecks;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Phase 3 (Long List) state, checks, save and travel details; use together with HandlesSubmission. */
trait EditsPhase3
{
    /** @var array<int|string, mixed> game_event_id => person ids */
    public array $athletes = [];

    /** @var array<int|string, mixed> game_sport_id => person ids */
    public array $officials = [];

    abstract protected function association(): Association;

    protected function loadPhase(): void
    {
        [$this->athletes, $this->officials] = app(LongListChecks::class)->selection($this->submission);
    }

    /** @return Collection<int, GameSport> */
    public function visibleSports(): Collection
    {
        return $this->gameModel->phase2SportsFor($this->association());
    }

    /** @return Collection<int, Person> active people of this association and type, for the pickers */
    public function candidates(PersonType $type): Collection
    {
        return Person::withoutGlobalScopes()->where('association_id', $this->association()->getKey())
            ->where('is_active', true)->where('type', $type)->orderBy('last_name')->orderBy('first_name')->get();
    }

    /** @return array<int, int>|null */
    public function phase2Numbers(): ?array
    {
        return app(LongListChecks::class)->phase2Numbers($this->submission);
    }

    public function listRows(): Collection
    {
        return app(LongListChecks::class)->rows($this->submission, $this->athletes, $this->officials);
    }

    /** Link offered next to the pickers; the portal page overrides it (STA edit pages have no registry link). */
    public function registryUrl(): ?string
    {
        return null;
    }

    /** Chip labels: only people of this submission's association (never another association's). */
    public function chipPeople(): Collection
    {
        $ids = collect([...$this->athletes, ...$this->officials])->flatten()->map(fn ($i) => (int) $i)->unique()->all();

        return Person::withoutGlobalScopes()->where('association_id', $this->association()->getKey())->whereIn('id', $ids)->get()->keyBy('id');
    }

    /** Chip picker (canvas LongListEvents): edits the selection only; save() runs LongListChecks as before. */
    public function addAthleteChip(int $gameEventId, int|string $personId): void
    {
        $this->athletes[$gameEventId] = $this->withPerson($this->athletes[$gameEventId] ?? [], $personId, PersonType::Athlete);
    }

    public function removeAthleteChip(int $gameEventId, int|string $personId): void
    {
        $this->athletes[$gameEventId] = $this->withoutPerson($this->athletes[$gameEventId] ?? [], $personId);
    }

    public function addOfficialChip(int $gameSportId, int|string $personId): void
    {
        $this->officials[$gameSportId] = $this->withPerson($this->officials[$gameSportId] ?? [], $personId, PersonType::Official);
    }

    public function removeOfficialChip(int $gameSportId, int|string $personId): void
    {
        $this->officials[$gameSportId] = $this->withoutPerson($this->officials[$gameSportId] ?? [], $personId);
    }

    /** @return list<string> */
    private function withPerson(array|string $ids, int|string $personId, PersonType $type): array
    {
        $ids = array_map('strval', (array) $ids);
        if ($this->readOnlyReason === null && ! in_array((string) $personId, $ids, true)
            && $this->candidates($type)->contains('id', (int) $personId)) {
            $ids[] = (string) $personId;
        }

        return $ids;
    }

    /** @return list<string> */
    private function withoutPerson(array|string $ids, int|string $personId): array
    {
        $ids = array_map('strval', (array) $ids);

        return $this->readOnlyReason === null ? array_values(array_diff($ids, [(string) $personId])) : $ids;
    }

    public function save(): void
    {
        try {
            $this->guardEditable();
            if ($errors = app(LongListChecks::class)->errors($this->submission, $this->athletes, $this->officials)) {
                throw ValidationException::withMessages($errors);
            }
            DB::transaction(function () {
                $this->claimOrHalt();
                app(LongListChecks::class)->sync($this->submission, $this->athletes, $this->officials);
            });
        } catch (Halt) {
            return; // notification already sent; loadedVersion unchanged so submit knows nothing was saved
        }
        $this->refreshLoadedAt();
        Notification::make()->success()->title('Saved')->send();
    }

    public function travelAction(): Action
    {
        return Action::make('travel')->label('Travel & insurance')->modalHeading('Travel & insurance')
            ->modalSubmitActionLabel('Save as draft')
            ->visible(fn () => $this->readOnlyReason === null)
            ->fillForm(function (array $arguments): array {
                $d = $this->travelDetail((int) ($arguments['person'] ?? 0));
                if (! $d) {
                    return [];
                }
                // The policy number is only ever sent to the browser for users allowed to see it.
                $keys = ['insurance_company', ...(Person::canViewMedical(auth()->user()) ? ['insurance_policy_no', 'vaccinations'] : [])];

                return [...$d->only($keys), 'insurance_valid_until' => $d->insurance_valid_until?->toDateString(),
                    'insurance_certificate_path' => $d->insurance_certificate_path ? [$d->insurance_certificate_path] : []];
            })
            ->schema([
                TextInput::make('insurance_company')->label('Travel insurance company')->maxLength(255),
                TextInput::make('insurance_policy_no')->label('Policy number')->maxLength(100)
                    ->visible(fn () => Person::canViewMedical(auth()->user())),
                DatePicker::make('insurance_valid_until')->label('Insurance valid until'),
                AttachmentActions::view(FileUpload::make('insurance_certificate_path')->label('Insurance certificate')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240)
                    ->disk(config('filesystems.private'))->directory(fn () => 'submissions/'.$this->submission->id)->visibility('private')
                    // No record backs this modal, so accept exactly the stored certificate of the person being edited.
                    ->preventFilePathTampering(allowFilePathUsing: fn (string $file) => $file === $this->travelDetail(
                        (int) ($this->getMountedAction()?->getArguments()['person'] ?? 0))?->insurance_certificate_path)),
                Textarea::make('vaccinations')->label('Vaccinations for the destination')->rows(3)->maxLength(2000)
                    ->visible(fn () => Person::canViewMedical(auth()->user())),
            ])
            ->action(function (array $data, array $arguments) {
                $personId = (int) ($arguments['person'] ?? 0);
                $cert = $data['insurance_certificate_path'] ?? null;
                $data['insurance_certificate_path'] = is_array($cert) ? (array_values($cert)[0] ?? null) : $cert;
                try {
                    $this->guardEditable();
                    if (! $this->submission->entries()->where('person_id', $personId)->exists()) {
                        Notification::make()->danger()->title('Save the Long List with this person first.')->send();

                        return;
                    }
                    DB::transaction(function () use ($data, $personId) {
                        $this->claimOrHalt();
                        LongListDetail::updateOrCreate(['submission_id' => $this->submission->id, 'person_id' => $personId], $data);
                    });
                } catch (Halt) {
                    return;
                }
                $this->refreshLoadedAt();
                Notification::make()->success()->title('Travel details saved')->send();
            });
    }

    private function travelDetail(int $personId): ?LongListDetail
    {
        return $this->submission->details()->where('person_id', $personId)->first();
    }
}
