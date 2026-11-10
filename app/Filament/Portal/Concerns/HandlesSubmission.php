<?php

namespace App\Filament\Portal\Concerns;

use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\Game;
use App\Models\Submission;
use App\Services\Phases\PhaseGate;
use App\Services\Phases\StaleSubmission;
use App\Services\Phases\SubmissionWorkflow;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * Submission plumbing shared by the portal phase pages and the STA edit pages.
 * The phase state and save() come from App\Filament\Shared\Phases\EditsPhaseN.
 */
trait HandlesSubmission
{
    public Game $gameModel;

    public Submission $submission;

    #[Locked]
    public int $loadedVersion = 0;

    #[Locked]
    public ?string $readOnlyReason = null;

    /** Fills the phase state from $this->submission. */
    abstract protected function loadPhase(): void;

    /** Called before submit so unsaved changes go in. */
    abstract public function save(): void;

    protected function mountPortal(int|string $game, int $phase): void
    {
        $this->gameModel = Game::findOrFail($game);
        $tenant = Filament::getTenant();
        abort_unless($this->gameModel->isInvited($tenant) && $this->gameModel->phase($phase)->enabled, 404);
        $this->submission = Submission::for($this->gameModel, $tenant, $phase);
        $this->loadPhase();
        $this->refreshLoadedAt();
    }

    protected function association(): Association
    {
        return Filament::getTenant();
    }

    /** Why the current user may not edit; associations follow the phase gate. STA pages override this. */
    protected function editDenialReason(Submission $s): ?string
    {
        return app(PhaseGate::class)->denialReason($s);
    }

    protected function refreshLoadedAt(): void
    {
        $this->submission->refresh();
        $this->loadedVersion = (int) $this->submission->lock_version;
        $this->readOnlyReason = $this->editDenialReason($this->submission);
    }

    /** Status/phase checks; call before validating input. */
    protected function guardEditable(): void
    {
        if ($reason = $this->editDenialReason($this->submission->fresh())) {
            Notification::make()->danger()->title($reason)->send();
            throw new Halt;
        }
    }

    /** Call FIRST inside the save's DB::transaction: atomically claims the loaded version, else halts (rolling back). */
    protected function claimOrHalt(): void
    {
        try {
            app(PhaseGate::class)->assertFresh($this->submission, $this->loadedVersion);
        } catch (StaleSubmission $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            throw new Halt;
        }
    }

    /** Spec 16.6: Save as draft beside Submit; a draft is never submitted. */
    protected function saveDraftAction(): Action
    {
        return Action::make('saveDraft')->label('Save as draft')->color('gray')
            ->visible(fn () => $this->readOnlyReason === null)
            ->action(fn () => $this->save());
    }

    protected function submitAction(): Action
    {
        return Action::make('submit')->label(fn () => auth()->user()->role === Role::AssocAdmin ? 'Submit to STA' : 'Send for approval')
            ->requiresConfirmation()->visible(fn () => $this->readOnlyReason === null)
            ->action(fn () => $this->runWorkflow(function () {
                $before = $this->loadedVersion;
                $this->save();
                if ($this->loadedVersion === $before) {
                    throw new Halt; // save() halted (stale or closed); do not submit
                }

                return app(SubmissionWorkflow::class)->submit($this->submission->fresh(), auth()->user());
            }));
    }

    protected function approveAction(): Action
    {
        return Action::make('approve')->label('Approve and submit to STA')->requiresConfirmation()->color('success')
            ->visible(fn () => auth()->user()->role === Role::AssocAdmin && $this->submission->status === SubmissionStatus::AwaitingAssocAdmin)
            ->action(fn () => $this->runWorkflow(fn () => app(SubmissionWorkflow::class)->approve($this->submission->fresh(), auth()->user())));
    }

    protected function returnAction(): Action
    {
        return Action::make('return')->label('Return to draft')->color('gray')->requiresConfirmation()
            ->visible(fn () => auth()->user()->role === Role::AssocAdmin && $this->submission->status === SubmissionStatus::AwaitingAssocAdmin)
            ->action(fn () => $this->runWorkflow(fn () => app(SubmissionWorkflow::class)->returnToDraft($this->submission->fresh(), auth()->user())));
    }

    private function runWorkflow(callable $step): void
    {
        try {
            $this->submission = $step();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }
        $this->refreshLoadedAt();
        Notification::make()->success()->title('Saved — status: '.$this->submission->status->label())->send();
    }
}
