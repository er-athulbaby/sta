<?php

namespace App\Services\Reports\Builder;

use App\Enums\ExtraFieldType;
use App\Enums\PersonType;
use App\Models\ExtraField;
use App\Models\User;

/**
 * Spec 15.1: active extra fields as report builder fields on subjects that join `people`. The SQL is built from the
 * field id (an integer from the database), never from input; values are stored as text, so numbers, dates and yes/no
 * are cast. Sensitive fields only for users who may view medical data (no user = none).
 */
final class ExtraFields
{
    public const PREFIX = 'extra_';

    public const SECTION = 'Additional information';

    /** @return array<string, Field> keyed */
    public static function fields(?User $user): array
    {
        $fields = [];
        foreach (ExtraField::active()->orderBy('id')->get() as $f) {
            // The key ends up in a column alias; keys are slugs, this guards against anything else.
            if (! $f->visibleTo($user) || preg_match('/^[a-z0-9_]+$/', $f->key) !== 1) {
                continue;
            }
            $key = self::PREFIX.$f->key;
            $value = '(SELECT pfv.value FROM person_field_values pfv WHERE pfv.person_id = people.id AND pfv.extra_field_id = '.(int) $f->id.')';
            $fields[$key] = match ($f->type) {
                ExtraFieldType::Text => Field::text($key, $f->label, self::SECTION, $value),
                ExtraFieldType::Number => Field::number($key, $f->label, self::SECTION, Sql::castNumber($value)),
                ExtraFieldType::Date => Field::date($key, $f->label, self::SECTION, Sql::castDate($value)),
                ExtraFieldType::Choice => Field::enum($key, $f->label, self::SECTION, $value, array_combine($f->options ?? [], $f->options ?? [])),
                ExtraFieldType::YesNo => Field::boolean($key, $f->label, self::SECTION, Sql::castInteger($value)),
            };
        }

        return $fields;
    }

    /** Every active required extra field of the type has a value (same rule as Person::missingFields()); '' = none. */
    public static function completeSql(PersonType $type): string
    {
        return ExtraField::requiredFor($type)
            ->map(fn (ExtraField $f) => '(EXISTS (SELECT 1 FROM person_field_values pfv WHERE pfv.person_id = people.id AND pfv.extra_field_id = '
                .(int) $f->id." AND pfv.value <> ''))")
            ->implode(' AND ');
    }

    /** @return list<string> labels of the deactivated extra fields among $keys */
    public static function labels(array $keys): array
    {
        $keys = array_map(fn (string $k) => substr($k, strlen(self::PREFIX)), array_filter($keys, fn ($k) => is_string($k) && str_starts_with($k, self::PREFIX)));

        return $keys === [] ? [] : ExtraField::whereIn('key', $keys)->where('is_active', false)->orderBy('id')->pluck('label')->all();
    }
}
