<?php

namespace App\Services\GameFiles;

use App\Enums\PersonType;
use App\Exports\LiteralRow;
use App\Models\Association;
use App\Models\Entry;
use App\Models\ExportLayout;
use App\Models\ExtraField;
use App\Models\Game;
use App\Models\Person;
use App\Models\Submission;
use App\Models\User;
use App\Services\Reports\GameReports;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSpout\Writer\XLSX\Writer;

/** Spec 15.2: the rows and the file of a game system upload file. */
final class GameFileExporter
{
    public function __construct(private readonly GameReports $reports) {}

    /**
     * The people of the game's included submissions (Short List, else Long List; sports answered "no" in Phase 1 left out).
     * Athletes are entries with an event, team officials entries without.
     *
     * @return Collection<int, array{person: Person, association: Association, entries: Collection<int, Entry>}>
     */
    public function people(Game $game, PersonType $type, ?int $associationId = null, ?int $gameSportId = null): Collection
    {
        $athletes = $type === PersonType::Athlete;

        return $this->reports->includedSubmissions($game, ['entries.person.fieldValues', 'entries.gameEvent.event', 'entries.gameSport.sport'])
            ->when($associationId, fn (Collection $c) => $c->where('association_id', $associationId))
            ->flatMap(fn (Submission $s) => $this->reports->visibleEntries($game, $s)
                ->filter(fn (Entry $e) => ($e->game_event_id !== null) === $athletes && (! $gameSportId || (int) $e->game_sport_id === $gameSportId))
                ->sortBy('id')->groupBy('person_id')
                ->map(fn (Collection $entries) => ['person' => $entries->first()->person, 'association' => $s->association, 'entries' => $entries->values()])
                ->sortBy(fn (array $r) => $r['person']->code)->values())
            ->values();
    }

    /** @return array{headings: list<string>, rows: list<list<scalar|null>>, left_out: list<string>, gone: list<string>} left_out: medical columns the user may not see; gone: columns whose source no longer exists or is an inactive extra field */
    public function table(ExportLayout $layout, ?User $user, ?int $associationId = null, ?int $gameSportId = null): array
    {
        $game = $layout->game;
        $sources = ExportSources::all();
        $medic = Person::canViewMedical($user);
        $columns = [];
        $leftOut = [];
        $gone = [];
        foreach ($layout->columns as $c) {
            $s = $sources[$c['source']] ?? null;
            if (! $s || ! $s['active']) { // an inactive extra field is no longer available, like a gone one
                $gone[] = $c['heading'];

                continue;
            }
            if ($s['medical'] && ! $medic) {
                $leftOut[] = $c['heading'];

                continue;
            }
            $columns[] = [...$c, 'kind' => $s['kind']];
        }
        $extraIds = ExtraField::active()->pluck('id', 'key');
        $rows = [];
        foreach ($this->people($game, $layout->type, $associationId, $gameSportId) as $r) {
            $groups = $layout->row_mode === 'one_per_event' ? $r['entries']->map(fn (Entry $e) => collect([$e])) : [$r['entries']];
            foreach ($groups as $entries) {
                $rows[] = array_map(fn (array $c) => ExportSources::format(
                    $this->raw($c, $r['person'], $r['association'], $entries, $game, $extraIds), $c['kind'], $c['format'] ?? null), $columns);
            }
        }

        return ['headings' => array_column($columns, 'heading'), 'rows' => $rows, 'left_out' => $leftOut, 'gone' => $gone];
    }

    /** @return array{0: string, 1: string} temp file path, download name */
    public function write(ExportLayout $layout, array $table): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'gsf');
        if ($layout->file_format === 'csv') {
            $h = fopen($tmp, 'w');
            foreach ([$table['headings'], ...$table['rows']] as $row) {
                // RFC 4180: comma, double quotes doubled, no backslash escape, CRLF; UTF-8 without BOM.
                fputcsv($h, array_map(self::csvSafe(...), $row), ',', '"', '', "\r\n");
            }
            fclose($h);
        } else {
            $writer = new Writer;
            $writer->openToFile($tmp);
            $writer->addRow(LiteralRow::from($table['headings']));
            foreach ($table['rows'] as $row) {
                $writer->addRow(LiteralRow::from($row));
            }
            $writer->close();
        }
        $who = $layout->type === PersonType::Athlete ? 'athletes' : 'officials';

        return [$tmp, Str::slug($layout->game->name)."-{$who}.{$layout->file_format}"];
    }

    /** Formula-injection guard: a leading = + - @ TAB or CR becomes literal text, except phone numbers like +97333445566. */
    private static function csvSafe(mixed $v): string
    {
        // Only strings: numbers (ages, heights) are written as numbers.
        return is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) && ! preg_match('/^\+\d+$/', $v) ? "'".$v : (string) $v;
    }

    private function raw(array $c, Person $p, Association $a, Collection $entries, Game $game, Collection $extraIds): mixed
    {
        [$group, $name] = explode('.', $c['source'], 2) + [1 => null];
        $joined = fn (callable $value) => $entries->map($value)->filter()->unique()->implode('; ') ?: null;

        return match ($group) {
            'person' => match (true) {
                in_array($name, ExportSources::PHONES, true) => filled($p->{$name}) ? $p->{$name.'_cc'}.$p->{$name} : null,
                $name === 'gender' => $p->gender?->value,
                default => $p->{$name},
            },
            'extra' => $p->fieldValues->firstWhere('extra_field_id', $extraIds[$name] ?? 0)?->value,
            'entry' => $joined(fn (Entry $e) => match ($name) {
                'sport' => $e->gameSport->sport->name,
                'event' => $e->gameEvent?->event->name,
                'event_gender' => $e->gameEvent?->event->gender->label(),
            }),
            'game' => $p->age($game->start_date),
            'association' => $a->{$name},
            'text' => $c['value'] ?? null,
        };
    }
}
