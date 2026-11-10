<?php

namespace App\Filament\Pages;

use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;
use App\Models\SavedReport;
use App\Services\Reports\Builder\Engine;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\FieldType;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;
use App\Services\Reports\Builder\ReportResult;
use App\Services\Reports\Builder\Subject;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/** Spec 14 builder. $def is raw input; ReportDefinition::fromArray() validates it on every run and save. */
class ReportBuilder extends Page
{
    use RequiresStaPermission;

    protected static ?string $slug = 'report-builder';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.report-builder';

    /** Locked: the client cannot point the page at another report; every action re-authorizes against it. */
    #[Locked]
    public ?int $reportId = null;

    public string $name = 'New report';

    public string $visibility = 'private';

    /** @var array<string, mixed> */
    public array $def = [];

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ViewReports;
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/report-builder/{report?}';
    }

    public function mount(int|string|null $report = null): void
    {
        if ($report === null) {
            $this->def = ReportDefinition::fresh(Registry::get('people'));

            return;
        }
        $saved = SavedReport::findOrFail($report);
        $this->authorize('view', $saved);
        $this->reportId = $saved->id;
        $this->name = $saved->name;
        $this->visibility = $saved->visibility;
        $this->def = $saved->definition;
        DB::table('saved_reports')->where('id', $saved->id)->update(['last_run_at' => now()]); // not updated_at: running is not editing
    }

    public function getTitle(): string
    {
        return $this->reportId ? $this->name : 'New report';
    }

    public function subject(): ?Subject
    {
        return Registry::get((string) ($this->def['subject'] ?? ''));
    }

    public function canEdit(): bool
    {
        return $this->reportId === null || auth()->user()->can('update', $this->saved());
    }

    /** The saved report this page is on, after checking the user may still run it (it may have been made private). */
    private function saved(): SavedReport
    {
        $saved = SavedReport::findOrFail($this->reportId);
        $this->authorize('view', $saved);

        return $saved;
    }

    /** @return array{0: ?ReportResult, 1: list<string>} the result, or the reasons the definition was refused */
    public function result(): array
    {
        if ($this->reportId !== null) {
            $this->saved();
        }
        try {
            return [app(Engine::class)->run(ReportDefinition::fromArray($this->def, auth()->user())), []];
        } catch (ValidationException $e) {
            return [null, array_values(array_unique(Arr::flatten($e->errors())))];
        }
    }

    public function updatedDef(mixed $value, string $key): void
    {
        $fields = $this->subject()?->fields() ?? [];
        if ($key === 'subject') {
            $subject = Registry::get((string) $value);
            $game = $this->def['game_id'] ?? null;
            if ($subject !== null) {
                $this->def = ReportDefinition::fresh($subject, filled($game) ? (int) $game : null);
            }
        } elseif (str_starts_with($key, 'columns')) {
            $this->def['totals'] = array_intersect_key((array) ($this->def['totals'] ?? []), array_flip((array) $this->def['columns']));
        } elseif (preg_match('/^filters\.(\d+)\.(field|op)$/', $key, $m) === 1) {
            $i = (int) $m[1];
            $field = $fields[$this->def['filters'][$i]['field'] ?? ''] ?? null;
            if ($field !== null) {
                $m[2] === 'field'
                    ? $this->def['filters'][$i] = self::blankFilter($field)
                    : $this->def['filters'][$i]['value'] = self::blankValue($field, (string) $value);
            }
        } elseif (preg_match('/^groups\.(\d+)\.field$/', $key, $m) === 1) {
            $field = $fields[(string) $value] ?? null;
            $this->def['groups'][(int) $m[1]]['bucket'] = $field?->type === FieldType::Date ? 'month' : null;
        }
    }

    public function addFilter(): void
    {
        $fields = $this->subject()?->fields() ?? [];
        if ($fields !== [] && count($this->def['filters'] ?? []) < ReportDefinition::MAX_FILTERS) {
            $this->def['filters'][] = self::blankFilter(reset($fields));
        }
    }

    public function removeFilter(int $i): void
    {
        unset($this->def['filters'][$i]);
        $this->def['filters'] = array_values($this->def['filters']);
    }

    public function addGroup(): void
    {
        $field = collect($this->subject()?->fields() ?? [])->first(fn (Field $f) => $f->type->groupable());
        if ($field !== null && count($this->def['groups'] ?? []) < ReportDefinition::MAX_GROUPS) {
            $this->def['groups'][] = ['field' => $field->key, 'bucket' => $field->type === FieldType::Date ? 'month' : null];
        }
    }

    public function removeGroup(int $i): void
    {
        unset($this->def['groups'][$i]);
        $this->def['groups'] = array_values($this->def['groups']);
    }

    public function moveColumn(int $i, int $by): void
    {
        $columns = array_values((array) ($this->def['columns'] ?? []));
        $j = $i + $by;
        if (isset($columns[$i], $columns[$j])) {
            [$columns[$i], $columns[$j]] = [$columns[$j], $columns[$i]];
            $this->def['columns'] = $columns;
        }
    }

    public function save(): void
    {
        $saved = $this->reportId ? $this->saved() : null;
        $saved !== null ? $this->authorize('update', $saved) : $this->authorize('create', SavedReport::class);
        $this->persist($saved, $this->name, $this->visibility);
    }

    /** Anyone who can see a report may copy it; the copy is theirs and private. */
    public function saveAsCopy(): void
    {
        if ($this->reportId !== null) {
            $this->saved();
        }
        $this->authorize('create', SavedReport::class);
        $this->persist(null, Str::limit($this->name.' (copy)', 120, ''), 'private');
    }

    public function delete(): void
    {
        $saved = $this->saved();
        $this->authorize('delete', $saved);
        $saved->delete();
        $this->redirect(Reports::getUrl());
    }

    private function persist(?SavedReport $saved, string $name, string $visibility): void
    {
        Validator::make(['name' => $name, 'visibility' => $visibility], [
            'name' => ['required', 'string', 'max:120'],
            'visibility' => ['required', Rule::in(array_keys(SavedReport::VISIBILITIES))],
        ])->validate();
        $definition = ReportDefinition::fromArray($this->def, auth()->user())->toArray();
        $attributes = ['name' => $name, 'subject' => $definition['subject'], 'definition' => $definition,
            'visibility' => $visibility, 'updated_by' => auth()->id()];

        if ($saved !== null) {
            $saved->update($attributes);
            Notification::make()->title('Report saved')->success()->send();

            return;
        }
        $saved = SavedReport::create([...$attributes, 'created_by' => auth()->id()]);
        $this->redirect(static::getUrl(['report' => $saved]));
    }

    /** @return array{field: string, op: string, value: mixed} */
    private static function blankFilter(Field $field): array
    {
        $op = $field->type->operators()[0];

        return ['field' => $field->key, 'op' => $op, 'value' => self::blankValue($field, $op)];
    }

    private static function blankValue(Field $field, string $op): mixed
    {
        return $field->type === FieldType::Enum ? [] : ($op === 'between' ? ['', ''] : null);
    }
}
