<?php

namespace App\Filament\Portal\Resources\People\Pages;

use App\Enums\PersonType;
use App\Exports\PeopleTemplateExport;
use App\Filament\Portal\Pages\ImportPeople;
use App\Filament\Portal\Resources\People\PersonResource;
use App\Filament\Shared\PersonForm;
use App\Models\Person;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Canvas Registry.dc.html: the list (search, All / Athletes / Officials, Name / Type / Date of birth / Passport expiry / Sizes)
 * is drawn from the Filament table's own records, so search, tabs, sorting, pagination and tenant scoping stay Filament's.
 * The aside edits the selected person's documents and clothing sizes with the registry rules (PersonForm::shortFormRules).
 */
class ListPeople extends ListRecords
{
    protected static string $resource = PersonResource::class;

    /** The person the aside shows; null = the first row of the page. */
    public ?int $selected = null;

    /** Which person $panel holds. */
    public ?int $panelFor = null;

    /** @var array<string, mixed> passport_no, passport_expiry, cpr_no, cpr_expiry, sizes */
    public array $panel = [];

    /** @var TemporaryUploadedFile|null */
    public $panelPassportScan = null;

    /** @var TemporaryUploadedFile|null */
    public $panelCprScan = null;

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'sta-canvas-page'];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importExcel')->label('Import Excel')->color('gray')->url(fn () => ImportPeople::getUrl())
                ->visible(fn () => ImportPeople::canAccess()),
            CreateAction::make()->label('Add person'),
        ];
    }

    public function getHeader(): ?ViewContract
    {
        return view('filament.portal.people.registry-header');
    }

    public function downloadTemplate(): ?StreamedResponse
    {
        return ImportPeople::canAccess() ? (new PeopleTemplateExport(user: auth()->user()))->download() : null;
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
            'athletes' => Tab::make('Athletes')->modifyQueryUsing(fn (Builder $query) => $query->where('type', PersonType::Athlete)),
            'officials' => Tab::make('Officials')->modifyQueryUsing(fn (Builder $query) => $query->where('type', PersonType::Official)),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([View::make('filament.portal.people.registry')]);
    }

    public function selectPerson(int $id): void
    {
        $this->selected = $id;
    }

    /** The aside's person: the chosen one when it is still in this association, else the first row of the page. */
    public function panelPerson(): ?Person
    {
        $person = $this->selected ? PersonResource::getEloquentQuery()->with('sizes')->find($this->selected) : null;

        return $person ?? $this->getTableRecords()->first()?->loadMissing('sizes');
    }

    public function rendering(): void
    {
        $person = $this->panelPerson();
        if ($person?->id !== $this->panelFor) {
            $this->fillPanel($person);
        }
    }

    public function cancelPanel(): void
    {
        $this->fillPanel($this->panelPerson());
    }

    private function fillPanel(?Person $person): void
    {
        $this->panelFor = $person?->id;
        $this->panel = $person ? [
            'passport_no' => $person->passport_no, 'passport_expiry' => $person->passport_expiry?->toDateString(),
            'cpr_no' => $person->cpr_no, 'cpr_expiry' => $person->cpr_expiry?->toDateString(), 'sizes' => $person->sizesMap(),
        ] : [];
        $this->panelPassportScan = $this->panelCprScan = null;
        $this->resetErrorBag();
    }

    /** Save changes / Save as draft: the same registry save (an incomplete person can always be saved, spec 13). */
    public function savePanel(): void
    {
        $person = $this->panelPerson();
        if ($person === null || $person->id !== $this->panelFor || ! PersonResource::canEdit($person)) {
            return;
        }
        $rules = collect(PersonForm::shortFormRules($person->type, 'panel.', 'panel', $person))
            ->filter(fn ($r, string $key) => Str::startsWith($key, ['panel.passport_no', 'panel.passport_expiry', 'panel.cpr_', 'panel.sizes.', 'panelPassportScan', 'panelCprScan']))
            ->all();
        Validator::make(['panel' => $this->panel, 'panelPassportScan' => $this->panelPassportScan, 'panelCprScan' => $this->panelCprScan], $rules,
            attributes: ['panel.passport_no' => 'passport number', 'panel.passport_expiry' => 'passport expiry', 'panel.cpr_no' => 'CPR number', 'panel.cpr_expiry' => 'CPR expiry'],
        )->validate();

        $store = fn ($file) => $file?->store('scans', ['disk' => config('filesystems.private'), 'visibility' => 'private']) ?: null;
        $data = collect($this->panel)->only(['passport_no', 'passport_expiry', 'cpr_no', 'cpr_expiry'])->map(fn ($v) => filled($v) ? $v : null)->all();
        $files = array_filter(['passport_scan_path' => $store($this->panelPassportScan), 'cpr_front_path' => $store($this->panelCprScan)]);
        $sizes = PersonForm::sizeItems($person->type)->mapWithKeys(fn ($item) => [$item->id => $this->panel['sizes'][$item->id] ?? null])->all();

        DB::transaction(function () use ($person, $data, $files, $sizes) {
            $person->update([...$data, ...$files]);
            $person->syncSizes($sizes);
        });
        Notification::make()->success()->title('Saved')->send();
        $this->fillPanel($person->refresh());
    }
}
