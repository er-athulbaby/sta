<?php

namespace App\Filament\Resources\People\Pages;

use App\Enums\PersonType;
use App\Exports\LiteralRow;
use App\Filament\Resources\People\PersonResource;
use App\Models\Association;
use App\Models\Person;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Canvas AdminPeople.dc.html: KPI tiles, the list (search, association select, Type switch, "Expiring passports") drawn from
 * the Filament table's own records (search, filters, sorting, pagination stay Filament's) and the selected person's aside.
 */
class ListPeople extends ListRecords
{
    protected static string $resource = PersonResource::class;

    /** The person the aside shows; null = the first row of the page. */
    public ?int $selected = null;

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'sta-canvas-page'];
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    public function getHeader(): ?ViewContract
    {
        return view('filament.resources.people.list-header');
    }

    public function getSubheading(): ?string
    {
        return 'Athletes and team officials from every association · passport and CPR numbers are encrypted';
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'athletes' => Tab::make('Athletes')->modifyQueryUsing(fn (Builder $query) => $query->where('type', PersonType::Athlete)),
            'officials' => Tab::make('Officials')->modifyQueryUsing(fn (Builder $query) => $query->where('type', PersonType::Official)),
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
            View::make('filament.resources.people.list'),
        ]);
    }

    /** @return Collection<int, string> the association select's options */
    public function associationOptions(): Collection
    {
        return Association::query()->orderBy('name')->pluck('name', 'id');
    }

    public function selectPerson(int $id): void
    {
        $this->selected = $id;
    }

    public function panelPerson(): ?Person
    {
        $person = $this->selected ? Person::query()->with(['association', 'sizes.clothingItem'])->find($this->selected) : null;

        return $person ?? $this->getTableRecords()->first()?->loadMissing(['association', 'sizes.clothingItem']);
    }

    /** "Last changed by {name}, {date}" from the audit log. */
    public function lastChange(Person $person): ?string
    {
        $a = Activity::query()->where('subject_type', $person->getMorphClass())->where('subject_id', $person->id)->latest('id')->with('causer')->first();

        return $a ? trim(($a->causer?->name ?? 'System').', '.$a->created_at->format('j M')) : null;
    }

    /** Export Excel: the rows of the current search, filters and sort (no document numbers or medical data). */
    public function exportExcel(): StreamedResponse
    {
        $people = $this->getFilteredSortedTableQuery()->with(['association', 'fieldValues'])->get();

        return response()->streamDownload(function () use ($people) {
            $tmp = tempnam(sys_get_temp_dir(), 'ppl');
            $writer = new Writer;
            $writer->openToFile($tmp);
            $writer->addRow(LiteralRow::from(['Code', 'Name', 'Association', 'Type', 'Function', 'Gender', 'Date of birth', 'Nationality', 'Passport expiry', 'Passport scan', 'CPR scan', 'Complete']));
            foreach ($people as $p) {
                $writer->addRow(LiteralRow::from([$p->code, $p->fullName(), $p->association?->name ?? '', $p->type->label(), $p->function ?? '',
                    $p->gender?->label() ?? '', $p->dob?->toDateString() ?? '', $p->nationality ?? '', $p->passport_expiry?->toDateString() ?? '',
                    filled($p->passport_scan_path) ? 'Yes' : 'No', filled($p->cpr_front_path) ? 'Yes' : 'No', $p->isComplete() ? 'Yes' : 'No']));
            }
            $writer->close();
            readfile($tmp);
            unlink($tmp);
        }, 'people.xlsx');
    }
}
