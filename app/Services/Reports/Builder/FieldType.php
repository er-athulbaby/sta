<?php

namespace App\Services\Reports\Builder;

/** Spec 14 "Fields and filter operators". */
enum FieldType: string
{
    case Text = 'text';
    case Enum = 'enum';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';

    public const OPERATORS = [
        'is' => 'is', 'is_not' => 'is not', 'contains' => 'contains', 'empty' => 'is empty', 'not_empty' => 'is not empty',
        'in' => 'is any of', 'not_in' => 'is none of',
        'eq' => '=', 'ne' => '≠', 'gt' => '>', 'gte' => '≥', 'lt' => '<', 'lte' => '≤', 'between' => 'between',
        'on' => 'on', 'before' => 'before', 'after' => 'after',
        'yes' => 'is yes', 'no' => 'is no',
    ];

    /** @return list<string> */
    public function operators(): array
    {
        return match ($this) {
            self::Text => ['is', 'is_not', 'contains', 'empty', 'not_empty'],
            self::Enum => ['in', 'not_in'],
            self::Number => ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'between'],
            self::Date => ['on', 'before', 'after', 'between'],
            self::Boolean => ['yes', 'no'],
        };
    }

    /** @return list<string> totals besides the row count, which every subject has */
    public function totals(): array
    {
        return match ($this) {
            self::Number => ['sum', 'avg', 'min', 'max'],
            self::Date => ['min', 'max'],
            default => [],
        };
    }

    public function groupable(): bool
    {
        return $this !== self::Number;
    }
}
