<?php

namespace App\Filament\Resources\Games\Pages\Concerns;

use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Concerns\HandlesSubmission;
use App\Filament\Resources\Games\GameResource;
use App\Jobs\GenerateEntryByEventPdfs;
use App\Jobs\GenerateLongListPdf;
use App\Models\Association;
use App\Models\GamePhase;
use App\Models\Submission;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * STA edit of one association's submission (spec 5: any status, audited). The page class sets PHASE, uses the
 * matching EditsPhaseN trait with `EditsPhaseN::save as protected saveEntries` and `StaEditsSubmission::save insteadof EditsPhaseN`.
 */
trait StaEditsSubmission
{
    use HandlesSubmission, InteractsWithRecord;

    public static function canAccess(array $parameters = []): bool
    {
        $u = auth()->user();

        return (bool) ($u?->can(StaPermission::EditSubmissions->value) && $u->can(StaPermission::ViewSubmissions->value));
    }

    /** $submission is a model over HTTP (Livewire binds the typed property) and an id in Livewire tests. */
    public function mount(int|string $record, int|string|Submission $submission): void
    {
        $this->record = $this->resolveRecord($record);
        $this->gameModel = $this->record;
        $this->submission = Submission::withoutGlobalScopes()->where(['game_id' => $this->record->getKey(), 'phase' => static::PHASE])
            ->findOrFail($submission instanceof Submission ? $submission->getKey() : $submission);
        $this->loadPhase();
        $this->refreshLoadedAt();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Edit '.$this->submission->association->name.' – Phase '.static::PHASE.' · '.GamePhase::NAMES[static::PHASE];
    }

    protected function association(): Association
    {
        return $this->submission->association;
    }

    /** STA edits at any status and after the deadline; only a disabled phase stops them. */
    protected function editDenialReason(Submission $s): ?string
    {
        return $s->gamePhase()->enabled ? null : 'This phase is not used for this game.';
    }

    abstract protected function saveEntries(): void;

    /** STA edits are not a submit workflow: the portal actions are inert here (they resolve by name even when not in the header). */
    protected function submitAction(): Action
    {
        return Action::make('submit')->hidden();
    }

    protected function approveAction(): Action
    {
        return Action::make('approve')->hidden();
    }

    protected function returnAction(): Action
    {
        return Action::make('return')->hidden();
    }

    public function save(): void
    {
        $before = $this->loadedVersion;
        $this->saveEntries();
        if ($this->loadedVersion === $before || $this->submission->status !== SubmissionStatus::Submitted) {
            return;
        }
        // ponytail: the PDF is re-rendered under the same reference_no; bump the revision here if paper copies must show STA edits.
        match ($this->submission->phase) {
            2 => GenerateEntryByEventPdfs::dispatch($this->submission),
            3 => GenerateLongListPdf::dispatch($this->submission),
            default => null,
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to submission')->color('gray')
                ->url(fn () => GameResource::getUrl('submission', ['record' => $this->record, 'submission' => $this->submission])),
        ];
    }
}
