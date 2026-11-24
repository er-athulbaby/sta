<?php

namespace App\Filament\Shared\Phases;

use App\Enums\PersonType;
use App\Filament\Shared\PersonForm;
use App\Models\Person;
use App\Services\Phases\LongListChecks;
use App\Support\Sta;
use Filament\Notifications\Notification;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The "Add {name} to {game}" pop-up (portal Long List pages and STA "Edit as STA"): every Add asks for the event (athletes) or
 * the function in this game (team officials), shows the passport with an optional newer scan and a "latest one" tick, and the
 * clothing sizes. The passport and sizes go to the registry; the person goes on the list through the chip methods and save(),
 * so the Long List rules stay in LongListChecks. Part of EditsPhase3.
 */
trait AddsToLongList
{
    /** @var array{person: int, target: string, function: ?string, passport_no: ?string, passport_expiry: ?string, sizes: array<int|string, ?string>, confirmed: bool}|null */
    public ?array $adding = null;

    /** @var TemporaryUploadedFile|null "Upload latest passport" */
    public $addingPassportScan = null;

    /** @var array<string, array<string, mixed>> entry columns of people added through the pop-up, written by the next successful save() */
    #[Locked]
    public array $entryMeta = [];

    /** Opens the pop-up; $target is the game event (athletes) or game sport (officials) the Add came from, if any. */
    public function openAdd(int $personId, ?int $target = null): void
    {
        $p = $this->readOnlyReason === null ? $this->ownPerson($personId) : null;
        if ($p === null) {
            return;
        }
        if (! $p->is_active) {
            Notification::make()->danger()->title("{$p->fullName()} is inactive.")->send();

            return;
        }
        $targets = $this->addTargets($p);
        $this->resetErrorBag();
        $this->addingPassportScan = null;
        $this->adding = [
            'person' => $p->id,
            'target' => (string) ($targets->contains('id', $target) ? $target : ($targets->count() === 1 ? $targets->first()['id'] : '')),
            'function' => $p->function,
            'passport_no' => $p->passport_no,
            'passport_expiry' => $p->passport_expiry?->toDateString(),
            'sizes' => $p->sizes()->pluck('size', 'clothing_item_id')->all(),
            'confirmed' => false,
        ];
    }

    public function closeAdd(): void
    {
        $this->adding = null;
        $this->addingPassportScan = null;
        $this->resetErrorBag();
    }

    #[Unexposed]
    public function addingPerson(): ?Person
    {
        return $this->adding ? $this->ownPerson((int) $this->adding['person']) : null;
    }

    /** @return Collection<int, array{id: int, label: string}> events open to the association that match the athlete's gender, or the sports for an official */
    #[Unexposed]
    public function addTargets(Person $p): Collection
    {
        $sports = $this->visibleSports();
        if ($p->type === PersonType::Official) {
            return $sports->map(fn ($gs) => ['id' => $gs->id, 'label' => $gs->sport->name])->values();
        }
        $several = $sports->count() > 1;

        return $sports->flatMap(fn ($gs) => $gs->gameEvents
            ->filter(fn ($ge) => LongListChecks::genderMatches($p->gender, $ge->event->gender))
            ->map(fn ($ge) => ['id' => $ge->id, 'label' => ($several ? $gs->sport->name.' · ' : '').Sta::eventLabel($ge->event)]))->values();
    }

    /** "Add to Long List": registry passport and sizes first, then the chip and the Long List draft save (same rules as Add). */
    public function confirmAdd(): void
    {
        $p = $this->readOnlyReason === null ? $this->addingPerson() : null;
        if ($p === null) {
            $this->closeAdd();

            return;
        }
        if (! $p->is_active) {
            throw ValidationException::withMessages(['adding.person' => "{$p->fullName()} is inactive."]);
        }
        $official = $p->type === PersonType::Official;
        $registry = array_filter(PersonForm::shortFormRules($p->type, 'adding.', 'adding', $p),
            fn ($key) => in_array($key, ['adding.passport_no', 'adding.passport_expiry', 'addingPassportScan'], true) || str_starts_with($key, 'adding.sizes.'),
            ARRAY_FILTER_USE_KEY);
        $this->validate([
            'adding.target' => ['required', Rule::in($this->addTargets($p)->pluck('id')->map(fn ($id) => (string) $id)->all())],
            'adding.function' => $official ? ['required', Rule::in(PersonForm::FUNCTIONS)] : [],
            ...$registry,
            'adding.confirmed' => ['accepted'],
        ], [
            'adding.target.required' => $official ? 'Choose the sport.' : 'Choose the event.',
            'adding.target.in' => $official ? 'Choose the sport.' : 'Choose an event that matches the athlete\'s gender.',
            'adding.confirmed.accepted' => 'Confirm that the passport shown is the latest one.',
        ], ['adding.function' => 'function in this game', 'adding.passport_no' => 'passport number', 'adding.passport_expiry' => 'passport expiry',
            'addingPassportScan' => 'passport scan']);

        // The newer scan replaces the main one (RenamesAttachments deletes the old file); other passport pages stay.
        $scan = $this->addingPassportScan?->store('scans', ['disk' => config('filesystems.private'), 'visibility' => 'private']) ?: null;
        try {
            DB::transaction(function () use ($p, $scan) {
                $p->fill(['passport_no' => $this->adding['passport_no'], 'passport_expiry' => $this->adding['passport_expiry'],
                    ...($scan ? ['passport_scan_path' => $scan] : [])])->save();
                $p->syncSizes(Arr::wrap($this->adding['sizes'] ?? []));
            });
        } catch (\Throwable $e) {
            if ($scan) {
                Storage::disk(config('filesystems.private'))->delete($scan);
            }

            throw $e;
        }

        $target = (int) $this->adding['target'];
        $official ? $this->addOfficialChip($target, $p->id) : $this->addAthleteChip($target, $p->id);
        $gameSportId = $official ? $target : (int) $this->visibleSports()->flatMap->gameEvents->firstWhere('id', $target)->game_sport_id;
        $this->entryMeta[$p->id.'-'.($official ? 0 : $target).'-'.$gameSportId] = [
            'person_id' => $p->id, 'game_event_id' => $official ? null : $target, 'game_sport_id' => $gameSportId,
            'values' => ['function' => $official ? $this->adding['function'] : null,
                'passport_confirmed_at' => now()->toDateTimeString(), 'passport_confirmed_by' => auth()->id()],
        ];
        // Closed first, so a Long List error below shows on the page instead of a filled pop-up that would only add the chip again.
        $this->closeAdd();
        try {
            $this->save();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title('Added, but the Long List was not saved')->body(collect($e->errors())->flatten()->first())->send();

            throw $e;
        }
    }

    /** Writes the pop-up's entry columns; called by save() right after LongListChecks::sync, inside its transaction. */
    private function applyEntryMeta(): void
    {
        foreach ($this->entryMeta as $m) {
            $this->submission->entries()->where(['person_id' => $m['person_id'], 'game_sport_id' => $m['game_sport_id']])
                ->where('game_event_id', $m['game_event_id'])->get()->each->update($m['values']);
        }
    }

    private function ownPerson(int $id): ?Person
    {
        return Person::withoutGlobalScopes()->where('association_id', $this->association()->getKey())->find($id);
    }
}
