<?php

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Resources\Games\GameResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditGame extends EditRecord
{
    protected static string $resource = GameResource::class;

    public function getSubheading(): string
    {
        return $this->record->isPublished() ? 'Published' : 'Draft';
    }

    // No delete action: games are never deleted.
    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')->label('Publish')->color('success')->requiresConfirmation()
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
}
