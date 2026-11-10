<?php

namespace App\Services\Reports\Builder;

use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * A report as validated JSON, never SQL (spec 14). Built only through fromArray(), so every instance is safe to run.
 * Checked again on every run, so a stored definition that no longer fits its subject is refused, not run.
 */
final readonly class ReportDefinition
{
    public const VERSION = 1;

    public const MAX_COLUMNS = 30;

    public const MAX_FILTERS = 20;

    public const MAX_GROUPS = 2;

    public const BUCKETS = ['month', 'year'];

    public const TOTALS_MESSAGE = 'Totals apply to chosen number columns (sum, average, min, max) and date columns (min, max).';

    /**
     * @param  list<string>  $columns
     * @param  list<array{field: string, op: string, value: mixed}>  $filters
     * @param  list<array{field: string, bucket: ?string}>  $groups
     * @param  array<string, string>  $totals  field key => sum|avg|min|max
     * @param  array{field: string, dir: string}  $sort
     * @param  array<string, Field>  $fields  the subject's fields for the user it was validated for; the engine reads only these
     */
    private function __construct(
        public Subject $subject,
        public ?int $gameId,
        public array $columns,
        public array $filters,
        public array $groups,
        public array $totals,
        public array $sort,
        public array $fields,
    ) {}

    /** @return array<string, mixed> a new definition for the builder screen (raw; validated when run) */
    public static function fresh(Subject $subject, ?int $gameId = null): array
    {
        return [
            'version' => self::VERSION,
            'subject' => $subject->key(),
            'game_id' => $gameId,
            'columns' => $subject->defaultColumns(),
            'filters' => [],
            'groups' => [],
            'totals' => [],
            'sort' => ['field' => $subject->defaultColumns()[0], 'dir' => 'asc'],
        ];
    }

    /**
     * Games a user may report on: every game for a Super Admin, only published ones for anyone else (spec 16.6).
     *
     * @return Collection<int, string> id => name
     */
    public static function selectableGames(?User $user): Collection
    {
        return Game::query()->when(! $user?->isSuperAdmin(), fn ($q) => $q->whereNotNull('published_at'))->orderBy('name')->pluck('name', 'id');
    }

    /** @param  array<string, mixed>  $data  $user decides which games (drafts: Super Admin only) and fields (sensitive extra fields) are allowed */
    public static function fromArray(array $data, ?User $user = null): self
    {
        $errors = [];
        if (($data['version'] ?? null) !== self::VERSION) {
            $errors['version'] = 'This report was saved by another version of the builder.';
        }
        $subject = is_string($data['subject'] ?? null) ? Registry::get($data['subject']) : null;
        if ($subject === null) {
            throw ValidationException::withMessages(['subject' => 'Choose a subject from the list.', ...$errors]);
        }
        $fields = $subject->fields($user);

        $gameId = $data['game_id'] ?? null;
        $gameId = $gameId === '' ? null : $gameId;
        if ($gameId !== null && (! is_numeric($gameId) || ! self::selectableGames($user)->has((int) $gameId))) {
            $errors['game_id'] = 'Choose a game from the list.';
        } elseif ($gameId === null && $subject->gameBased()) {
            $errors['game_id'] = 'Choose a game.';
        }

        $columns = array_values((array) ($data['columns'] ?? []));
        if (count($columns) > self::MAX_COLUMNS) {
            $errors['columns'] = 'At most 30 columns.';
        } elseif ($columns === [] || array_filter($columns, fn ($c) => ! is_string($c) || ! isset($fields[$c])) !== []
            || count(array_unique($columns)) !== count($columns)) {
            $errors['columns'] = 'Choose one or more of the listed columns.';
        }

        $filters = array_values((array) ($data['filters'] ?? []));
        if (count($filters) > self::MAX_FILTERS) {
            $errors['filters'] = 'At most 20 filters.';
        }
        $cleanFilters = [];
        foreach ($filters as $i => $filter) {
            $field = is_array($filter) && is_string($filter['field'] ?? null) ? ($fields[$filter['field']] ?? null) : null;
            if ($field === null) {
                $errors["filters.$i.field"] = 'Choose a field to filter on.';

                continue;
            }
            $op = $filter['op'] ?? null;
            if (! is_string($op) || ! in_array($op, $field->type->operators(), true)) {
                $errors["filters.$i.op"] = 'Choose how to compare.';

                continue;
            }
            $value = self::value($field, $op, $filter['value'] ?? null);
            if ($value === false) {
                $errors["filters.$i.value"] = "Enter a valid value for {$field->label}.";

                continue;
            }
            $cleanFilters[] = ['field' => $field->key, 'op' => $op, 'value' => $value];
        }

        $groups = array_values((array) ($data['groups'] ?? []));
        if (count($groups) > self::MAX_GROUPS) {
            $errors['groups'] = 'At most 2 group levels.';
        }
        $cleanGroups = [];
        foreach ($groups as $i => $group) {
            $field = is_array($group) && is_string($group['field'] ?? null) ? ($fields[$group['field']] ?? null) : null;
            if ($field === null || ! $field->type->groupable()) {
                $errors["groups.$i.field"] = 'Choose a field to group by.';

                continue;
            }
            $bucket = ($group['bucket'] ?? null) ?: null;
            if ($field->type === FieldType::Date ? ! in_array($bucket, self::BUCKETS, true) : $bucket !== null) {
                $errors["groups.$i.bucket"] = 'Group dates by month or year.';

                continue;
            }
            $cleanGroups[] = ['field' => $field->key, 'bucket' => $bucket];
        }

        $totals = array_filter((array) ($data['totals'] ?? []), fn ($fn) => $fn !== null && $fn !== '');
        foreach ($totals as $key => $fn) {
            $field = $fields[$key] ?? null;
            if ($field === null || ! in_array($key, $columns, true) || ! in_array($fn, $field->type->totals(), true)) {
                $errors['totals'] = self::TOTALS_MESSAGE;
            }
        }

        $sort = $data['sort'] ?? null;
        if (! is_array($sort) || ! is_string($sort['field'] ?? null) || ! isset($fields[$sort['field']])
            || ! in_array($sort['dir'] ?? null, ['asc', 'desc'], true)) {
            $errors['sort'] = 'Choose a field to sort by.';
        }

        // A field deactivated after the report was saved gets a message that says so.
        $referenced = [...$columns, ...array_map(fn ($x) => is_array($x) ? ($x['field'] ?? null) : null, [...$filters, ...$groups]),
            ...array_keys($totals), is_array($sort) ? ($sort['field'] ?? null) : null];
        $gone = ExtraFields::labels(array_unique(array_filter($referenced, fn ($k) => is_string($k) && ! isset($fields[$k]))));
        if ($gone !== []) {
            $errors['fields'] = 'This report uses fields that are no longer available: '.implode(', ', $gone).'. Remove them and save again.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return new self($subject, $gameId === null ? null : (int) $gameId, $columns, $cleanFilters, $cleanGroups, $totals,
            ['field' => $sort['field'], 'dir' => $sort['dir']], $fields);
    }

    /** @return array<string, mixed> what saved_reports.definition stores */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'subject' => $this->subject->key(),
            'game_id' => $this->gameId,
            'columns' => $this->columns,
            'filters' => $this->filters,
            'groups' => $this->groups,
            'totals' => $this->totals,
            'sort' => $this->sort,
        ];
    }

    /** The normalised filter value, or false when it is not valid for the field and operator. */
    private static function value(Field $field, string $op, mixed $value): mixed
    {
        if (in_array($op, ['empty', 'not_empty', 'yes', 'no'], true)) {
            return null;
        }
        if ($op === 'between') {
            if (! is_array($value) || count($value) !== 2) {
                return false;
            }
            [$from, $to] = array_map(fn ($v) => self::scalar($field, $v), array_values($value));

            return $from === false || $to === false ? false : [$from, $to];
        }
        if ($field->type === FieldType::Enum) {
            if (! is_array($value) || $value === []) {
                return false;
            }
            $out = [];
            foreach ($value as $v) {
                if (! is_scalar($v) || ! array_key_exists((string) $v, $field->options)) {
                    return false;
                }
                $out[] = (string) $v;
            }

            return array_values(array_unique($out));
        }

        return self::scalar($field, $value);
    }

    private static function scalar(Field $field, mixed $v): string|int|float|false
    {
        return match ($field->type) {
            FieldType::Text => is_string($v) && trim($v) !== '' && mb_strlen($v) <= 100 ? $v : false,
            // Numbers are bound as numbers: SQLite compares an integer expression with a text parameter as never equal.
            FieldType::Number => is_numeric($v) && abs((float) $v) < 1e9 ? $v + 0 : false,
            FieldType::Date => is_string($v) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) === 1
                && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $v : false,
            default => false,
        };
    }
}
