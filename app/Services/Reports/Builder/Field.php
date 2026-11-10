<?php

namespace App\Services\Reports\Builder;

use BackedEnum;

/**
 * One reportable field. `sql` is an expression over the subject's base query, written in code and never built from
 * input. Filtering, grouping, sorting and totals all follow from the type (spec 14 table).
 */
final readonly class Field
{
    /** @param  array<array-key, string>  $options  value => label, for enum fields */
    public function __construct(
        public string $key,
        public string $label,
        public string $section,
        public FieldType $type,
        public string $sql,
        public array $options = [],
    ) {}

    public static function text(string $key, string $label, string $section, string $sql): self
    {
        return new self($key, $label, $section, FieldType::Text, $sql);
    }

    public static function number(string $key, string $label, string $section, string $sql): self
    {
        return new self($key, $label, $section, FieldType::Number, $sql);
    }

    /** DATE() makes datetime columns and SQLite's 'Y-m-d H:i:s' strings compare as plain dates on both databases. */
    public static function date(string $key, string $label, string $section, string $expression): self
    {
        return new self($key, $label, $section, FieldType::Date, "DATE({$expression})");
    }

    public static function boolean(string $key, string $label, string $section, string $sql): self
    {
        return new self($key, $label, $section, FieldType::Boolean, $sql);
    }

    /** @param  array<array-key, string>  $options */
    public static function enum(string $key, string $label, string $section, string $sql, array $options): self
    {
        return new self($key, $label, $section, FieldType::Enum, $sql, $options);
    }

    /**
     * @param  class-string<BackedEnum>  $enum  an app enum with label()
     * @return array<string, string>
     */
    public static function labels(string $enum): array
    {
        $options = [];
        foreach ($enum::cases() as $case) {
            $options[(string) $case->value] = $case->label();
        }

        return $options;
    }

    /** How a raw database value reads on screen and in Excel. */
    public function display(mixed $raw): string|int|float|null
    {
        if ($raw === null) {
            return null;
        }

        return match ($this->type) {
            FieldType::Enum => $this->options[(string) $raw] ?? (string) $raw,
            FieldType::Boolean => (int) $raw === 1 ? 'Yes' : 'No',
            FieldType::Number => is_numeric($raw) ? $raw + 0 : (string) $raw,
            default => (string) $raw,
        };
    }
}
