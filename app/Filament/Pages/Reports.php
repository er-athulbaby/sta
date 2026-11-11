<?php

namespace App\Filament\Pages;

use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;
use App\Models\SavedReport;
use App\Services\Reports\Builder\ReportDefinition;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/** Spec 14: "My reports" and "Shared", plus New report. The ready-made M4 reports stay on each game. */
class Reports extends Page
{
    protected static ?int $navigationSort = 6;

    use RequiresStaPermission;

    protected static ?string $navigationLabel = 'Reports';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected string $view = 'filament.pages.reports';

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ViewReports;
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('newReport')->label('New report')->url(ReportBuilder::getUrl())];
    }

    /** @return Collection<int, SavedReport> */
    public function mine(): Collection
    {
        return SavedReport::visibleTo(auth()->user())->mine(auth()->user())->orderBy('name')->get();
    }

    /** @return Collection<int, SavedReport> */
    public function shared(): Collection
    {
        return SavedReport::visibleTo(auth()->user())->shared()->with('creator')->orderBy('name')->get();
    }

    /** @return Collection<int, string> id => name, linked to each game's ready-made reports */
    public function games(): Collection
    {
        return ReportDefinition::selectableGames(auth()->user());
    }
}
