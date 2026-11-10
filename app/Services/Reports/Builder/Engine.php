<?php

namespace App\Services\Reports\Builder;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Runs a validated definition (spec 14). Read-only. The database filters, sorts, counts and totals; one aggregate
 * query per group level (portable: no WITH ROLLUP on SQLite).
 */
final class Engine
{
    public const SCREEN_LIMIT = 500;

    /** @param  ?int  $limit  rows to fetch; null = all (Excel) */
    public function run(ReportDefinition $def, ?int $limit = self::SCREEN_LIMIT): ReportResult
    {
        $fields = $def->subject->fields();
        $query = $def->subject->query($def->gameId);
        foreach ($def->filters as $filter) {
            $this->filter($query, $fields[$filter['field']], $filter['op'], $filter['value']);
        }
        $groupSql = array_map(fn (array $g) => self::groupExpression($fields[$g['field']], $g['bucket']), $def->groups);

        $grand = $this->aggregate(clone $query, $fields, $def->totals, [])[0];
        $groups = [];
        foreach (array_keys($groupSql) as $level) {
            $group = $def->groups[$level];
            foreach ($this->aggregate(clone $query, $fields, $def->totals, array_slice($groupSql, 0, $level + 1)) as $row) {
                $raw = $row['keys'][$level];
                $label = $raw === null ? '—' : (string) ($group['bucket'] !== null ? $raw : $fields[$group['field']]->display($raw));
                $groups[ReportResult::keyId($row['keys'])] = ['label' => $label, 'count' => $row['count'], 'totals' => $row['totals']];
            }
        }

        $detail = (clone $query)->select(array_map(fn (string $key) => DB::raw("{$fields[$key]->sql} AS c_{$key}"), $def->columns));
        foreach ($groupSql as $i => $sql) {
            $detail->addSelect(DB::raw("{$sql} AS g{$i}"))->orderByRaw("({$sql}) IS NULL")->orderByRaw($sql);
        }
        $detail->orderByRaw($fields[$def->sort['field']]->sql.' '.($def->sort['dir'] === 'desc' ? 'desc' : 'asc'));
        if ($limit !== null) {
            $detail->limit($limit);
        }

        $rows = [];
        foreach ($detail->get() as $record) {
            $record = (array) $record;
            $cells = [];
            foreach ($def->columns as $key) {
                $cells[$key] = $record["c_{$key}"];
            }
            $keys = array_map(fn (int $i) => $record["g{$i}"] === null ? null : (string) $record["g{$i}"], array_keys($groupSql));
            $rows[] = ['cells' => $cells, 'keys' => $keys];
        }

        return new ReportResult(array_map(fn (string $key) => $fields[$key], $def->columns), $rows, $grand['count'], $grand['totals'], $groups);
    }

    private static function groupExpression(Field $field, ?string $bucket): string
    {
        return match ($bucket) {
            'month' => Sql::month($field->sql),
            'year' => Sql::year($field->sql),
            default => $field->sql,
        };
    }

    /**
     * One row per group of the given expressions (none = the grand total).
     *
     * @param  array<string, Field>  $fields
     * @param  array<string, string>  $totals
     * @param  list<string>  $groupSql
     * @return list<array{keys: list<?string>, count: int, totals: array<string, mixed>}>
     */
    private function aggregate(Builder $query, array $fields, array $totals, array $groupSql): array
    {
        $select = ['COUNT(*) AS row_count'];
        foreach ($groupSql as $i => $sql) {
            $select[] = "{$sql} AS g{$i}";
        }
        $aliases = [];
        foreach (array_keys($totals) as $i => $key) {
            $aliases[$key] = "t{$i}";
            $select[] = self::total($fields[$key]->sql, $totals[$key])." AS t{$i}";
        }
        $query->select(DB::raw(implode(', ', $select)));
        foreach ($groupSql as $sql) {
            $query->groupByRaw($sql)->orderByRaw("({$sql}) IS NULL")->orderByRaw($sql);
        }

        return array_map(function ($record) use ($groupSql, $aliases) {
            $record = (array) $record;

            return [
                'keys' => array_map(fn (int $i) => $record["g{$i}"] === null ? null : (string) $record["g{$i}"], array_keys($groupSql)),
                'count' => (int) $record['row_count'],
                'totals' => array_map(fn (string $alias) => $record[$alias], $aliases),
            ];
        }, $query->get()->all());
    }

    private static function total(string $sql, string $fn): string
    {
        return match ($fn) {
            'sum' => "SUM({$sql})",
            'avg' => "ROUND(AVG({$sql}), 2)",
            'min' => "MIN({$sql})",
            'max' => "MAX({$sql})",
        };
    }

    private function filter(Builder $query, Field $field, string $op, mixed $value): void
    {
        $s = $field->sql;
        $marks = is_array($value) ? implode(', ', array_fill(0, count($value), '?')) : '';
        [$where, $bindings] = match ($op) {
            'is' => ["LOWER({$s}) = LOWER(?)", [$value]],
            'is_not' => ["{$s} IS NULL OR LOWER({$s}) <> LOWER(?)", [$value]],
            'contains' => ["LOWER({$s}) LIKE LOWER(?) ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%']],
            'empty' => ["{$s} IS NULL OR {$s} = ''", []],
            'not_empty' => ["{$s} IS NOT NULL AND {$s} <> ''", []],
            'in' => ["{$s} IN ({$marks})", $value],
            'not_in' => ["{$s} IS NULL OR {$s} NOT IN ({$marks})", $value],
            'eq', 'on' => ["{$s} = ?", [$value]],
            'ne' => ["{$s} <> ?", [$value]],
            'gt', 'after' => ["{$s} > ?", [$value]],
            'gte' => ["{$s} >= ?", [$value]],
            'lt', 'before' => ["{$s} < ?", [$value]],
            'lte' => ["{$s} <= ?", [$value]],
            'between' => ["{$s} BETWEEN ? AND ?", $value],
            'yes' => ["{$s} = 1", []],
            'no' => ["{$s} IS NULL OR {$s} = 0", []],
        };
        $query->whereRaw("({$where})", $bindings);
    }
}
