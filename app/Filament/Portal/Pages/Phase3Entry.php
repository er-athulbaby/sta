<?php

namespace App\Filament\Portal\Pages;

use App\Enums\PersonType;
use App\Filament\Portal\Concerns\HandlesSubmission;
use App\Filament\Portal\Resources\People\PersonResource;
use App\Filament\Shared\PersonForm;
use App\Filament\Shared\Phases\EditsPhase3;
use App\Models\Person;
use Filament\Actions\CreateAction;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

class Phase3Entry extends Page
{
    use EditsPhase3, HandlesSubmission;

    protected string $view = 'filament.portal.pages.phase3-entry';

    public static function getRoutePath(Panel $panel): string
    {
        return '/games/{game}/phase-3';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->gameModel->name.' – Phase 3 · Long List';
    }

    public function mount(int|string $game): void
    {
        $this->mountPortal($game, 3);
    }

    public function registryUrl(): ?string
    {
        return PersonResource::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [$this->addPersonAction(PersonType::Athlete), $this->addPersonAction(PersonType::Official),
            $this->submitAction(), $this->approveAction(), $this->returnAction()];
    }

    /** Spec 16.3: the registry person form, without leaving the page; the new person is in the pickers on the next render. */
    protected function addPersonAction(PersonType $type): CreateAction
    {
        $label = $type === PersonType::Athlete ? 'Add athlete' : 'Add team official';

        return CreateAction::make($type === PersonType::Athlete ? 'addAthlete' : 'addOfficial')
            ->label($label)->modalHeading($label)
            ->color('gray')->slideOver()->createAnother(false)
            ->visible(fn () => $this->readOnlyReason === null)
            ->model(Person::class)
            ->schema(fn (Schema $schema) => PersonForm::configure($schema, type: $type))
            ->modalSubmitActionLabel('Save as draft')
            // Same as the registry Create page (SavesPersonSizes): sizes are not a column, row and sizes commit together.
            // association_id is set here, never from form state; BelongsToAssociation's creating hook pins it again.
            ->using(function (array $data): Person {
                $sizes = $data['sizes'] ?? [];
                unset($data['sizes']);

                return DB::transaction(function () use ($data, $sizes) {
                    $person = Person::create([...$data, 'association_id' => $this->association()->getKey()]);
                    $person->syncSizes($sizes);

                    return $person;
                });
            })
            ->successNotificationTitle('Saved to the registry');
    }
}
