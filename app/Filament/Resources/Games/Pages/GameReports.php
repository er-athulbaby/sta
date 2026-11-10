<?php

namespace App\Filament\Resources\Games\Pages;

use App\Enums\StaPermission;
use App\Exports\LiteralRow;
use App\Filament\Resources\Games\GameResource;
use App\Services\Reports\GameReports as Reports;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Str;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GameReports extends Page
{
    use InteractsWithRecord;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.resources.games.pages.game-reports';

    public string $report = 'phase1Answers';

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can(StaPermission::ViewReports->value);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return $this->record->name.' - reports';
    }

    /** @return array{headings: list<string>, rows: list<list<scalar|null>>} */
    public function reportData(): array
    {
        abort_unless(array_key_exists($this->report, Reports::REPORTS), 404);

        return app(Reports::class)->{$this->report}($this->record);
    }

    public function export(): StreamedResponse
    {
        $data = $this->reportData();

        return response()->streamDownload(function () use ($data) {
            $tmp = tempnam(sys_get_temp_dir(), 'rep');
            $writer = new Writer;
            $writer->openToFile($tmp);
            $writer->addRow(LiteralRow::from($data['headings']));
            foreach ($data['rows'] as $row) {
                $writer->addRow(LiteralRow::from($row));
            }
            $writer->close();
            readfile($tmp);
            unlink($tmp);
        }, Str::slug($this->record->name).'-'.Str::kebab($this->report).'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
