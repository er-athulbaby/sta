<?php

namespace App\Services\Reports\Builder;

use Illuminate\Support\Str;

/** What a run produced: raw values; lines() formats them for the screen and Excel. */
final readonly class ReportResult
{
    /**
     * @param  list<Field>  $columns
     * @param  list<array{cells: array<string, mixed>, keys: list<?string>}>  $rows
     * @param  array<string, mixed>  $totals  grand totals, field key => raw value
     * @param  array<string, array{label: string, count: int, totals: array<string, mixed>}>  $groups  keyed by keyId()
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public int $rowCount,
        public array $totals,
        public array $groups,
    ) {}

    public function truncated(): bool
    {
        return $this->rowCount > count($this->rows);
    }

    /**
     * Matches how MySQL's case-insensitive collation groups ("Judo" and "judo " are one group), so each row finds its
     * group on both databases.
     *
     * @param  list<?string>  $keys
     */
    public static function keyId(array $keys): string
    {
        return json_encode(array_map(fn (?string $k) => $k === null ? null : mb_strtolower(rtrim($k)), $keys), JSON_THROW_ON_ERROR);
    }

    /**
     * Rows with a subtotal line before each group (group totals come from SQL over all rows, not only the shown ones),
     * then the grand total line.
     *
     * @return list<array{kind: string, cells: list<string|int|float|null>}>
     */
    public function lines(): array
    {
        $lines = [];
        $open = [];
        foreach ($this->rows as $row) {
            foreach (array_keys($row['keys']) as $level) {
                $id = self::keyId(array_slice($row['keys'], 0, $level + 1));
                if (($open[$level] ?? null) === $id) {
                    continue;
                }
                $open = array_slice($open, 0, $level);
                $open[$level] = $id;
                $group = $this->groups[$id] ?? ['label' => '—', 'count' => 0, 'totals' => []];
                $lines[] = ['kind' => 'group', 'cells' => $this->totalCells(str_repeat('— ', $level).$group['label']." ({$group['count']})", $group['totals'])];
            }
            $lines[] = ['kind' => 'row', 'cells' => array_map(fn (Field $f) => $f->display($row['cells'][$f->key] ?? null), $this->columns)];
        }
        $lines[] = ['kind' => 'total', 'cells' => $this->totalCells("Total ({$this->rowCount})", $this->totals)];

        return $lines;
    }

    /**
     * The screen's lines (canvas AdminReportBuilder): a header opening each group (label, row count), its rows, a
     * subtotal closing it (row count, group totals), then the grand total. Cells of group/subtotal/total lines are the
     * column totals ('' where none). The grand total reads "Grand total · 38 associations" (the number of first-level
     * groups, named after $groupLabel, the first group field's label) or "Grand total · 1,240 rows" without grouping.
     *
     * @return list<array{kind: string, level?: int, label?: string, count?: int, cells: list<string|int|float|null>}>
     */
    public function screenLines(?string $groupLabel = null): array
    {
        $lines = [];
        $open = [];
        $close = function (int $from) use (&$lines, &$open) {
            for ($level = count($open) - 1; $level >= $from; $level--) {
                $g = $open[$level];
                $lines[] = ['kind' => 'subtotal', 'level' => $level, 'label' => $g['label'], 'count' => $g['count'], 'cells' => $this->totalValues($g['totals'])];
                unset($open[$level]);
            }
        };
        foreach ($this->rows as $row) {
            foreach (array_keys($row['keys']) as $level) {
                $id = self::keyId(array_slice($row['keys'], 0, $level + 1));
                if (($open[$level]['id'] ?? null) === $id) {
                    continue;
                }
                $close($level);
                $group = $this->groups[$id] ?? ['label' => '—', 'count' => 0, 'totals' => []];
                $open[$level] = [...$group, 'id' => $id];
                $lines[] = ['kind' => 'group', 'level' => $level, 'label' => $group['label'], 'count' => $group['count'], 'cells' => []];
            }
            $lines[] = ['kind' => 'row', 'cells' => array_map(fn (Field $f) => $f->display($row['cells'][$f->key] ?? null), $this->columns)];
        }
        $close(0);
        $topGroups = count(array_filter(array_keys($this->groups), fn (string $id) => count(json_decode($id, true)) === 1));
        $label = $groupLabel !== null && $this->groups !== []
            ? number_format($topGroups).' '.Str::plural(mb_strtolower($groupLabel), $topGroups)
            : number_format($this->rowCount).' '.Str::plural('row', $this->rowCount);
        $lines[] = ['kind' => 'total', 'label' => "Grand total · {$label}", 'count' => $this->rowCount, 'cells' => $this->totalValues($this->totals)];

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $totals
     * @return list<string|int|float>
     */
    private function totalCells(string $label, array $totals): array
    {
        $cells = $this->totalValues($totals);
        $cells[0] = $cells[0] === '' ? $label : "{$label} · {$cells[0]}";

        return $cells;
    }

    /**
     * @param  array<string, mixed>  $totals
     * @return list<string|int|float>
     */
    private function totalValues(array $totals): array
    {
        return array_map(fn (Field $f) => array_key_exists($f->key, $totals) && $totals[$f->key] !== null ? $f->display($totals[$f->key]) : '', $this->columns);
    }
}
