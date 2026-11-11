<?php

namespace App\Filament\Shared;

use App\Enums\PersonType;
use App\Enums\PhaseStatus;
use App\Models\Entry;
use App\Models\Person;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\DB;

/**
 * Athlete / official create and edit pages (canvas PlayerForm.dc.html, OfficialForm.dc.html): soft blue page, the header card
 * (photo, breadcrumb, name, chips, Missing, Cancel / Save as draft / Save), the required-fields note, the jump links and the
 * bottom buttons. "Save as draft" saves and stays; "Save" saves and returns to the list. Both use the page's own create/save.
 */
trait PersonPageChrome
{
    /** @var array{tone: string, text: string}|null the "Read details from passport" result box (ReadPassportAction) */
    public ?array $passportRead = null;

    public function getPageClasses(): array
    {
        return ['sta-bg-alt', 'sta-canvas-page', 'sta-person-page'];
    }

    public function getHeader(): ?ViewContract
    {
        return view('filament.people.form-header');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.people.form-intro'),
            $this->getFormContentComponent(),
        ]);
    }

    public function getFormActionsContentComponent(): Component
    {
        return View::make('filament.people.form-footer');
    }

    public function personSubmitMethod(): string
    {
        return $this instanceof CreateRecord ? 'create' : 'save';
    }

    public function saveAndClose(): void
    {
        $this->{$this->personSubmitMethod()}();
        // Reached only when the save passed validation; replaces the page's own redirect (Livewire keeps the last one).
        $this->redirect(static::getResource()::getUrl('index'));
    }

    public function personType(): PersonType
    {
        return PersonType::tryFrom((string) ($this->data['type'] ?? '')) ?? PersonType::Athlete;
    }

    /** The saved person on Edit pages, null on Create. */
    public function personRecord(): ?Person
    {
        $record = $this->record ?? null;

        return $record instanceof Person && $record->exists ? $record : null;
    }

    /** "Phase 3 · Long List" / "Phase 4 · Short List" when the person is on a list of a game whose phase is open (canvas header chip). */
    public function personPhase(): ?int
    {
        $person = $this->personRecord();
        if ($person === null) {
            return null;
        }

        return Entry::query()->where('entries.person_id', $person->id)
            ->join('submissions', 'submissions.id', '=', 'entries.submission_id')
            ->join('game_phases', fn ($j) => $j->on('game_phases.game_id', '=', 'submissions.game_id')->on('game_phases.phase', '=', 'submissions.phase'))
            ->whereIn('submissions.phase', [3, 4])->where('game_phases.status', PhaseStatus::Open->value)
            ->max(DB::raw('submissions.phase'));
    }
}
