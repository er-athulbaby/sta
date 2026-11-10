<?php

namespace App\Services\Reports\Builder;

use Illuminate\Support\Facades\DB;

/**
 * The only dialect-specific SQL: SQLite in tests, MySQL 8 in production. Inputs are expressions written in code,
 * never user input.
 */
final class Sql
{
    private static function sqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    /** 'YYYY-MM' of a date expression. */
    public static function month(string $date): string
    {
        return self::sqlite() ? "strftime('%Y-%m', {$date})" : "DATE_FORMAT({$date}, '%Y-%m')";
    }

    /** 'YYYY' of a date expression. */
    public static function year(string $date): string
    {
        return self::sqlite() ? "strftime('%Y', {$date})" : "DATE_FORMAT({$date}, '%Y')";
    }

    /** Whole years from $dob to $on (both date expressions). */
    public static function age(string $dob, string $on): string
    {
        return self::sqlite()
            ? "(CAST(strftime('%Y', {$on}) AS INTEGER) - CAST(strftime('%Y', {$dob}) AS INTEGER) - (strftime('%m-%d', {$on}) < strftime('%m-%d', {$dob})))"
            : "TIMESTAMPDIFF(YEAR, {$dob}, {$on})";
    }

    /** Today (Asia/Bahrain) as a code-made literal. */
    public static function today(): string
    {
        return "'".now()->toDateString()."'";
    }

    /** Dates only get IS NOT NULL: MySQL 8 rejects comparing a DATE column with ''. */
    public static function filled(string $column, bool $date = false): string
    {
        return $date ? "({$column} IS NOT NULL)" : "({$column} IS NOT NULL AND {$column} <> '')";
    }

    /** A text expression holding a number, as a number (so 100 > 70, not '100' < '70'). */
    public static function castNumber(string $text): string
    {
        return self::sqlite() ? "CAST({$text} AS REAL)" : "CAST({$text} AS DECIMAL(15,4))";
    }

    /** A text expression holding '0'/'1', as an integer. */
    public static function castInteger(string $text): string
    {
        return self::sqlite() ? "CAST({$text} AS INTEGER)" : "CAST({$text} AS SIGNED)";
    }

    /** A text expression holding 'Y-m-d', as a date (SQLite has no date type: its dates are 'Y-m-d' text). */
    public static function castDate(string $text): string
    {
        return self::sqlite() ? "DATE({$text})" : "CAST({$text} AS DATE)";
    }

    /** The person's full name, else first, second, third and last name with blanks skipped (like Person::fullName()). */
    public static function fullName(): string
    {
        $parts = array_map(fn (string $c) => "NULLIF(people.{$c}, '')", ['first_name', 'second_name', 'third_name', 'last_name']);

        $joined = self::sqlite()
            ? 'TRIM('.implode(' || ', array_map(fn (string $p) => "COALESCE({$p} || ' ', '')", $parts)).')'
            : "CONCAT_WS(' ', ".implode(', ', $parts).')';

        return "COALESCE(NULLIF(people.full_name, ''), {$joined})";
    }
}
