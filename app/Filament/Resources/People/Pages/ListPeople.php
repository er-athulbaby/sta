<?php

namespace App\Filament\Resources\People\Pages;

use App\Enums\PersonType;
use App\Filament\Resources\People\PersonResource;
use App\Models\Person;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;

class ListPeople extends ListRecords
{
    protected static string $resource = PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    public function getSubheading(): ?string
    {
        return 'Athletes and team officials from every association · passport and CPR numbers are encrypted';
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'athletes' => Tab::make('Athletes')->modifyQueryUsing(fn (Builder $q) => $q->where('type', PersonType::Athlete)),
            'officials' => Tab::make('Officials')->modifyQueryUsing(fn (Builder $q) => $q->where('type', PersonType::Official)),
        ];
    }

    /** @return array{athletes: int, officials: int, expiring: int, missingScan: int} canvas AdminPeople tiles */
    public function kpis(): array
    {
        return [
            'athletes' => Person::query()->where('type', PersonType::Athlete)->count(),
            'officials' => Person::query()->where('type', PersonType::Official)->count(),
            'expiring' => Person::query()->whereNotNull('passport_expiry')->where('passport_expiry', '<', now()->addMonths(6))->count(),
            'missingScan' => Person::query()->whereNull('passport_scan_path')->count(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.resources.people.kpis')->viewData(['kpis' => $this->kpis()]),
            $this->getTabsContentComponent(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            EmbeddedTable::make(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
        ]);
    }
}
