<?php

namespace App\Filament\Portal\Resources\People\Pages;

use App\Enums\PersonType;
use App\Filament\Portal\Pages\ImportPeople;
use App\Filament\Portal\Resources\People\PersonResource;
use App\Models\Person;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPeople extends ListRecords
{
    protected static string $resource = PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importExcel')->label('Import Excel')->color('gray')->url(fn () => ImportPeople::getUrl())
                ->visible(fn () => ImportPeople::canAccess()),
            CreateAction::make()->label('Add person'),
        ];
    }

    public function getSubheading(): ?string
    {
        // SQL counts scoped to the tenant association, like the table.
        $count = fn (PersonType $t) => Person::query()->where('association_id', Filament::getTenant()?->getKey())->where('type', $t)->count();

        return 'Your permanent registry · reused for every game · '.$count(PersonType::Athlete).' athletes, '.$count(PersonType::Official).' team officials';
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'athletes' => Tab::make('Athletes')->modifyQueryUsing(fn (Builder $q) => $q->where('type', PersonType::Athlete)),
            'officials' => Tab::make('Officials')->modifyQueryUsing(fn (Builder $q) => $q->where('type', PersonType::Official)),
        ];
    }
}
