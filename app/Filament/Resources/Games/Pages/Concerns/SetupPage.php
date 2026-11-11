<?php

namespace App\Filament\Resources\Games\Pages\Concerns;

use App\Filament\Resources\Games\GameResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;

/** Shared by the four game setup screens (canvas SetupInvite, SetupLimits, SetupShortList, SetupPhases). */
trait SetupPage
{
    use InteractsWithRecord;

    public const TABS = [
        'edit' => '1 · Details & associations',
        'setup-limits' => '2 · Sports & limits',
        'setup-short-list' => '3 · Clothing & short list',
        'setup-phases' => '4 · Phases',
    ];

    /** Persists what this screen edits. */
    abstract public function save(): void;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(GameResource::canEdit($this->record), 403);
        $this->loadState();
    }

    protected function loadState(): void {}

    /** Canvas spacing: theme.css removes Filament's extra page padding and gaps for this class. */
    public function getPageClasses(): array
    {
        return ['sta-canvas-page'];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Game setup';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHeader(): ?View
    {
        return view('filament.resources.games.setup.header', ['record' => $this->record->refresh(), 'step' => static::STEP, 'tabs' => self::TABS]);
    }

    public function saveDraft(): void
    {
        $this->save();
        Notification::make()->success()->title('Saved')->send();
    }

    // No delete action: games are never deleted.
    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')->label('Publish')->color('primary')->requiresConfirmation()
                ->modalDescription('Invited associations will see this game, and its phases can be opened.')
                ->visible(fn () => ! $this->record->isPublished())
                ->action(function () {
                    if (! $this->record->associations()->exists() || ! $this->record->gameSports()->exists()) {
                        Notification::make()->danger()->title('Invite at least one association and add at least one sport before publishing.')->send();

                        return;
                    }
                    $this->record->update(['published_at' => now()]);
                    Notification::make()->success()->title('Game published')->send();
                }),
        ];
    }

    /** Stepper arithmetic: empty means "no limit"; + gives 1 from empty, and values never go below $min. */
    protected static function step(?int $value, int $delta, int $min = 0): ?int
    {
        return $value === null ? ($delta > 0 ? max($min, 1) : null) : max($min, $value + $delta);
    }
}
