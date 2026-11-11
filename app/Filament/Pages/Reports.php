<?php

namespace App\Filament\Pages;

use App\Enums\StaPermission;
use App\Exports\Xlsx;
use App\Filament\Concerns\RequiresStaPermission;
use App\Filament\Concerns\ShowsReadyMadeReports;
use App\Models\Game;
use App\Models\SavedReport;
use App\Services\Reports\Builder\Engine;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\FieldType;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Canvas AdminReports.dc.html: the ready-made reports of the chosen game, then "My reports" and "Shared" (spec 14). */
class Reports extends Page
{
    protected static ?int $navigationSort = 6;

    use RequiresStaPermission;
    use ShowsReadyMadeReports;

    protected static ?string $navigationLabel = 'Reports';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected string $view = 'filament.pages.reports';

    /** The game of the ready-made reports, always one of games() (or null when there is none). Untyped: ?game= may be any text. */
    #[Url]
    public int|string|null $game = null;

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ViewReports;
    }

    public function mount(): void
    {
        $this->game = $this->allowedGame($this->game);
    }

    public function updatedGame(mixed $value): void
    {
        $this->game = $this->allowedGame($value);
    }

    /** The given game id if this user may report on it, else the first game they may. */
    private function allowedGame(mixed $id): ?int
    {
        return is_numeric($id) && $this->games()->has((int) $id) ? (int) $id : $this->games()->keys()->first();
    }

    public function getPageClasses(): array
    {
        return ['sta-canvas-page', 'sta-dashboard-page']; // sections 24px apart, like the canvas
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.reports-header');
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

    /** @return Collection<int, string> id => name of the games this user may report on */
    public function games(): Collection
    {
        return $this->gameList ??= ReportDefinition::selectableGames(auth()->user());
    }

    /** Per request: Livewire does not keep protected properties between requests. */
    protected ?Collection $gameList = null;

    protected function reportGame(): ?Game
    {
        return is_numeric($this->game) && $this->games()->has((int) $this->game) ? Game::find((int) $this->game) : null;
    }

    /**
     * The card's Subject / Filters / Grouped lines, read from the stored definition.
     *
     * @param  Collection<int, string>  $games
     * @return array{subject: string, filters: string, grouped: string}
     */
    #[Unexposed]
    public function describe(SavedReport $report, Collection $games): array
    {
        $def = $report->definition;
        $subject = Registry::get($report->subject);
        $fields = $subject?->fields(auth()->user()) ?? [];
        $label = fn (string $key) => $fields[$key]->label ?? $key;

        $filters = collect($def['filters'] ?? [])->map(function (array $f) use ($fields, $label) {
            $field = $fields[$f['field'] ?? ''] ?? null;
            $value = $f['value'] ?? null;
            $value = match (true) {
                in_array($f['op'] ?? '', ['empty', 'not_empty', 'yes', 'no'], true) => '',
                ($f['op'] ?? '') === 'between' => implode(' and ', (array) $value),
                $field?->type === FieldType::Enum => implode(', ', array_map(fn ($v) => $field->options[$v] ?? $v, (array) $value)),
                default => (string) (is_scalar($value) ? $value : ''),
            };

            return trim($label((string) ($f['field'] ?? '')).' '.(FieldType::OPERATORS[$f['op'] ?? ''] ?? '').' '.$value);
        });
        if (filled($def['game_id'] ?? null)) {
            $filters->prepend('Game is '.($games[(int) $def['game_id']] ?? 'another game'));
        }

        return [
            'subject' => $subject?->label() ?? $report->subject,
            'filters' => Str::limit($filters->implode(', ') ?: 'None', 90),
            'grouped' => collect($def['groups'] ?? [])->map(fn (array $g) => $label((string) ($g['field'] ?? '')))->implode(' › ') ?: 'None',
        ];
    }

    /** A saved card's "Export to Excel": the same file as the builder's export, for a report this user may see. */
    public function exportSaved(int $id): ?StreamedResponse
    {
        $saved = SavedReport::findOrFail($id);
        $this->authorize('view', $saved);
        try {
            $definition = ReportDefinition::fromArray($saved->definition, auth()->user());
        } catch (ValidationException $e) {
            Notification::make()->title('This report cannot be exported')->body(Arr::first(Arr::flatten($e->errors())))->danger()->send();

            return null;
        }
        $result = app(Engine::class)->run($definition, null);
        DB::table('saved_reports')->where('id', $saved->id)->update(['last_run_at' => now()]); // not updated_at: running is not editing

        return Xlsx::download((Str::slug($saved->name) ?: 'report').'.xlsx', array_map(fn (Field $f) => $f->label, $result->columns),
            array_column($result->lines(), 'cells'));
    }
}
