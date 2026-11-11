<?php

namespace App\Filament\Concerns;

use App\Exports\Xlsx;
use App\Models\Game;
use App\Services\Reports\GameReports;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The canvas "Ready-made reports" band (filament/shared/ready-made-reports): the list, the preview and Excel export. */
trait ShowsReadyMadeReports
{
    public string $report = 'phase1Answers';

    /** The game the band reports on, or null when there is none to choose. */
    abstract protected function reportGame(): ?Game;

    /** @return array{headings: list<string>, rows: list<list<scalar|null>>} */
    public function reportData(): array
    {
        abort_unless(array_key_exists($this->report, GameReports::REPORTS), 404);
        $game = $this->reportGame();

        return $game ? app(GameReports::class)->{$this->report}($game) : ['headings' => [], 'rows' => []];
    }

    public function export(): ?StreamedResponse
    {
        $game = $this->reportGame();
        if ($game === null) {
            return null;
        }
        $data = $this->reportData();

        return Xlsx::download(Str::slug($game->name).'-'.Str::kebab($this->report).'.xlsx', $data['headings'], $data['rows']);
    }
}
