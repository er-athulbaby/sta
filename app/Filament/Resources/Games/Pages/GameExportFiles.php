<?php

namespace App\Filament\Resources\Games\Pages;

use App\Enums\PersonType;
use App\Enums\StaPermission;
use App\Filament\Resources\Games\GameResource;
use App\Jobs\BuildGameFilesZip;
use App\Models\ExportLayout;
use App\Models\Game;
use App\Models\Person;
use App\Services\GameFiles\ExportSources;
use App\Services\GameFiles\GameFileExporter;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Spec 15.2/15.3: the game's athlete and official upload-file layouts, the upload file and the files ZIP. */
class GameExportFiles extends Page
{
    use InteractsWithRecord;

    protected static string $resource = GameResource::class;

    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can(StaPermission::ExportGameFiles->value);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless($this->record->isPublished(), 404); // spec 16.6: drafts have no game system files
        $this->fillLayouts();
    }

    public function getTitle(): string
    {
        return $this->record->name.' - game system files';
    }

    private function fillLayouts(): void
    {
        $this->form->fill(collect(PersonType::cases())->mapWithKeys(fn (PersonType $t) => [
            $t->value => ExportLayout::for($this->record, $t)->only(['file_format', 'row_mode', 'zip_pattern', 'columns']),
        ])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('layouts')->tabs([
                Tab::make('Athlete layout')->schema($this->layoutFields(PersonType::Athlete)),
                Tab::make('Official layout')->schema($this->layoutFields(PersonType::Official)),
            ]),
        ])->statePath('data');
    }

    private function layoutFields(PersonType $type): array
    {
        $p = $type->value;
        $keep = array_column(ExportLayout::for($this->record, $type)->columns ?? [], 'source');
        $options = ExportSources::options(auth()->user(), $keep);
        $all = ExportSources::all();
        $formats = fn (Get $get) => ExportSources::FORMATS[$all[$get('source')]['kind'] ?? 'number'];

        return [
            Grid::make(3)->schema([
                Select::make("{$p}.file_format")->label('File type')->options(ExportLayout::FORMATS)->required(),
                Select::make("{$p}.row_mode")->label('Rows')->options(ExportLayout::ROW_MODES)->required(),
                TextInput::make("{$p}.zip_pattern")->label('ZIP file names')->required()->maxLength(200)
                    ->helperText('Tokens: {code} {passport_no} {last_name} {first_name} {association_code} {kind}'),
            ]),
            Repeater::make("{$p}.columns")->label('Columns')->reorderable()->defaultItems(0)->addActionLabel('Add column')->columns(4)->schema([
                Select::make('source')->options($options)->searchable()->required()->live()
                    ->in(collect($options)->flatMap(fn (array $group) => array_keys($group))->all())
                    ->afterStateUpdated(fn (Set $set) => $set('format', null)),
                TextInput::make('heading')->required()->maxLength(100),
                Select::make('format')->options($formats)->placeholder('Default')->visible(fn (Get $get) => $formats($get) !== []),
                TextInput::make('value')->label('Fixed text')->required()->maxLength(100)->visible(fn (Get $get) => $get('source') === 'text'),
            ]),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Save layouts')->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        $this->authorizeExport();
        $state = $this->form->getState();
        $layouts = [];
        $errors = [];
        foreach (PersonType::cases() as $t) {
            $layout = ExportLayout::for($this->record, $t);
            // Fixed key order (source, heading, format, value) whatever order the form state holds them in.
            $keys = array_fill_keys(['source', 'heading', 'format', 'value'], null);
            $columns = array_map(fn (array $c) => array_filter(array_replace($keys, array_intersect_key($c, $keys)), fn ($v) => filled($v)),
                array_values($state[$t->value]['columns'] ?? []));
            foreach (ExportSources::validate($columns, auth()->user(), array_column($layout->columns ?? [], 'source')) as $line) {
                $errors[] = $t->label().' layout, '.lcfirst($line);
            }
            $layouts[] = $layout->fill([...Arr::only($state[$t->value], ['file_format', 'row_mode', 'zip_pattern']), 'columns' => $columns]);
        }
        if ($errors) {
            Notification::make()->danger()->title('Layouts not saved')->body(implode("\n", $errors))->send();

            return;
        }
        foreach ($layouts as $layout) {
            $layout->save();
        }
        Notification::make()->success()->title('Layouts saved')->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')->label('Download upload file')->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => $this->record->isPublished()) // spec 16.6
                ->schema(fn () => $this->filterFields())
                ->action(fn (array $data) => $this->download(PersonType::from($data['type']),
                    filled($data['association_id'] ?? null) ? (int) $data['association_id'] : null,
                    filled($data['game_sport_id'] ?? null) ? (int) $data['game_sport_id'] : null)),
            Action::make('zip')->label('Download files ZIP')->icon('heroicon-o-archive-box-arrow-down')
                ->visible(fn () => $this->record->isPublished()) // spec 16.6
                ->schema(fn () => [...$this->filterFields(), CheckboxList::make('columns')->label('Besides photos, include')
                    ->options(array_filter(['passport_scan_path' => 'Passport scans', 'cpr_front_path' => 'CPR front scans',
                        'wada_certificate_path' => Person::canViewMedical(auth()->user()) ? 'WADA certificates' : null]))])
                ->action(function (array $data) {
                    $this->authorizeExport();
                    BuildGameFilesZip::dispatch(
                        ExportLayout::firstOrCreate(['game_id' => $this->record->id, 'type' => PersonType::from($data['type'])->value]),
                        auth()->user(), array_values(array_intersect($data['columns'] ?? [], array_keys(BuildGameFilesZip::KINDS))),
                        filled($data['association_id'] ?? null) ? (int) $data['association_id'] : null,
                        filled($data['game_sport_id'] ?? null) ? (int) $data['game_sport_id'] : null);
                    Notification::make()->success()->title('Building the files ZIP')
                        ->body('You will be notified here with a download link when it is ready.')->send();
                }),
            Action::make('copy')->label('Copy layouts from another game')->color('gray')
                ->modalDescription('Replaces this game\'s layouts with the layouts the chosen game has; a layout it does not have is kept.')
                ->schema([Select::make('game_id')->label('Game')->required()
                    ->options(fn () => Game::whereKeyNot($this->record->id)->whereNotNull('published_at')->whereHas('exportLayouts')->orderBy('name')->pluck('name', 'id'))])
                ->action(fn (array $data) => $this->copyFrom(Game::findOrFail($data['game_id']))),
        ];
    }

    /** Type, association and sport choices shared by the upload file and the files ZIP. */
    public function filterFields(): array
    {
        return [
            Select::make('type')->label('People')->options(['athlete' => 'Athletes', 'official' => 'Team officials'])
                ->required()->default('athlete'),
            Select::make('association_id')->label('Association')->placeholder('All associations')
                ->options($this->record->associations()->orderBy('name')->pluck('name', 'associations.id')),
            Select::make('game_sport_id')->label('Sport')->placeholder('All sports')
                ->options($this->record->gameSports()->with('sport')->get()->mapWithKeys(fn ($gs) => [$gs->id => $gs->sport->name])->sort()),
        ];
    }

    /** Public Livewire methods can be called directly: re-check what mount and the action visibility check. */
    private function authorizeExport(): void
    {
        abort_unless(static::canAccess() && $this->record->fresh()->isPublished(), 403);
    }

    public function download(PersonType $type, ?int $associationId = null, ?int $gameSportId = null): ?StreamedResponse
    {
        $this->authorizeExport();
        $layout = ExportLayout::for($this->record, $type);
        if (($layout->columns ?? []) === []) {
            Notification::make()->warning()->title("Set up the {$type->value} layout first.")->send();

            return null;
        }
        $exporter = app(GameFileExporter::class);
        $table = $exporter->table($layout, auth()->user(), $associationId, $gameSportId);
        [$path, $name] = $exporter->write($layout, $table);

        activity()->performedOn($this->record)->event('exported')
            ->withProperties(['layout' => $type->value, 'file' => $name, 'rows' => count($table['rows']), 'left_out' => $table['left_out'], 'gone' => $table['gone'],
                'association_id' => $associationId, 'game_sport_id' => $gameSportId])
            ->log('game system file exported');
        if ($table['left_out'] || $table['gone']) {
            Notification::make()->warning()->title('Some columns were left out')->body(implode("\n", array_filter([
                $table['left_out'] ? 'They need "View medical & health data": '.implode(', ', $table['left_out']) : null,
                $table['gone'] ? 'Their source is no longer available: '.implode(', ', $table['gone']) : null,
            ])))->send();
        }

        return response()->streamDownload(function () use ($path) {
            try {
                readfile($path);
            } finally {
                @unlink($path);
            }
        }, $name, ['Content-Type' => $layout->file_format === 'csv' ? 'text/csv; charset=UTF-8'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function copyFrom(Game $source): void
    {
        $this->authorizeExport();
        $all = ExportSources::all();
        $medic = Person::canViewMedical(auth()->user());
        $dropped = [];
        foreach ($source->exportLayouts as $from) {
            $columns = collect($from->columns)->reject(function (array $c) use ($all, $medic, &$dropped) {
                $leave = ! $medic && ($all[$c['source']]['medical'] ?? false);
                if ($leave) {
                    $dropped[] = $c['heading'];
                }

                return $leave;
            })->values()->all();
            ExportLayout::for($this->record, $from->type)
                ->fill([...$from->only(['file_format', 'row_mode', 'zip_pattern']), 'columns' => $columns])->save();
        }
        $this->fillLayouts();
        Notification::make()->success()->title("Layouts copied from {$source->name}")
            ->body($dropped ? 'Left out (needs "View medical & health data"): '.implode(', ', $dropped) : null)->send();
    }
}
