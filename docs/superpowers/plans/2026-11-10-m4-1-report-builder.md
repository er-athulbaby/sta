# Milestone 4.1 – Custom Report Builder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** STA users with `view_reports` (and the Super Admin) build their own tabular reports in `/admin` over six subjects (People, Entries, Submissions, Phase 1 answers, Phase 2 numbers, Clothing sizes): pick columns, AND-ed filters, up to two group levels with counts and totals, and a sort; see up to 500 rows live with a grand total; save, share, copy, delete; and export all rows to Excel with group subtotal rows.

**Architecture:**
- **Builder core** (`app/Services/Reports/Builder/`, beside M4's `App\Services\Reports\GameReports`): a `Field` is a whitelisted SQL expression with a `FieldType`; a `Subject` declares its base query (narrowed to a game) and its fields; `Registry` lists the six subjects; `ReportDefinition` is the versioned JSON shape, validated against the subject's fields on save **and** on every run; `Engine` turns a definition into one detail query (500-row cap) plus one aggregate query per group level and one for the grand total, all in SQL, values always bound; `ReportResult::lines()` interleaves group subtotal lines with rows for both the screen and the Excel file.
- **Portability:** the only dialect-specific SQL (month/year buckets, age in whole years, name concatenation) lives in `Sql`, which emits `strftime`/`||` for SQLite (tests) and `DATE_FORMAT`/`TIMESTAMPDIFF`/`CONCAT_WS` for MySQL 8 (production). Grouping uses one `GROUP BY` query per level instead of MySQL-only `WITH ROLLUP`.
- **Storage and screens:** `saved_reports` + `SavedReport` + `SavedReportPolicy`; a `Reports` page (`/admin/reports`: My reports, Shared, New report) and a `ReportBuilder` page (`/admin/report-builder/{report?}`), both plain Filament pages with Blade views and Livewire `wire:model.live`; Excel is written with OpenSpout exactly like M4's `GameReports` page.

**Tech Stack:** PHP 8.5 (Herd `php85`), Laravel 12, Filament 4.16, Livewire 3, Pest 4, `openspout/openspout` (already installed), spatie/laravel-permission. No new package.

**Spec:** `docs/superpowers/specs/2026-11-04-sports-entry-system-design.md` — section 14 (binding: approach, screens, subjects, field types and operators, permissions and safety, storage, what is left out), section 8 (security) and section 13 ("Sensitive data"). Reference design copied in structure only: `C:/Users/ababy/Documents/RentalManagementSystem/docs/superpowers/specs/2026-10-15-report-builder-design.md` and its `app/Reports/Builder/**`.

## Global Constraints

- **Runs after Milestone 4.** This plan uses names M4 produces (`docs/superpowers/plans/2026-11-10-m4-short-list-reports.md`): `App\Services\Phases\ShortListChecks::overLimits(Submission): list<array{sport, kind, count, max}>` (Task 3 test oracle), `Submission::for()` with its Phase 4 preload (copies only when Phase 3 is `submitted`), the `App\Services\Reports` namespace, and the OpenSpout download pattern of `App\Filament\Resources\Games\Pages\GameReports::export()`. Do not start before M4 is merged and green.
- **PHP:** always run `/c/Users/ababy/.config/herd/bin/php85/php.exe` (written `$PHP` below), Pest as `$PHP vendor/bin/pest`. Format with `$PHP vendor/bin/pint` before each commit.
- **Time zone:** the app runs in `Asia/Bahrain`; "today" is `now()->toDateString()`. Tests freeze time with `$this->travelTo(...)`.
- **Access (spec 14):** both pages require `StaPermission::ViewReports` (`view_reports`); the Super Admin passes through the existing `Gate::before`. Associations have no builder. Editing, sharing and deleting a saved report: only its creator (still holding `view_reports`) or a Super Admin. A shared report is visible to everyone with `view_reports`; a private one only to its creator (and the Super Admin).
- **Running someone else's report:** anyone who can see it may change anything for that run; only the creator or a Super Admin saves changes; anyone else uses **Save as copy** (a new private report owned by them).
- **Definition (spec 14 Storage):** JSON, never SQL: `{version: 1, subject, game_id: int|null, columns: list<key>, filters: list<{field, op, value}>, groups: list<{field, bucket: 'month'|'year'|null}>, totals: {key: fn}, sort: {field, dir: 'asc'|'desc'}}`. Validated by `ReportDefinition::fromArray()` on save and on every run (screen and export). Unknown subject, field or operator, a bad value, an unknown game, or another `version` is refused with a `ValidationException`.
- **Limits:** 30 columns, 20 filters, 2 group levels. 500 rows on screen (`Engine::SCREEN_LIMIT`) with the total row count; the Excel export has all rows.
- **Field types (spec 14 table):** text — `is`, `is_not`, `contains`, `empty`, `not_empty`, groupable, count; enum — `in`, `not_in`, groupable, count; number — `eq`, `ne`, `gt`, `gte`, `lt`, `lte`, `between`, not groupable, totals `sum`/`avg`/`min`/`max`; date — `on`, `before`, `after`, `between`, grouped by `month` or `year`, totals `min`/`max`; boolean — `yes`, `no`, groupable, count. Every subject has **Count of rows** (grand total and per group). Totals may only be set on chosen columns.
- **Game filter:** every definition has `game_id`. Required for game-based subjects (Entries, Submissions, Phase 1 answers, Phase 2 numbers: "Choose a game."). For People and Clothing sizes it is optional and means "people on any Long or Short List of that game" (Clothing sizes: and only that game's clothing items).
- **Never a field (spec 14, 13):** medical and health data (`Person::MEDICAL_GATED`, injuries), insurance policy numbers, vaccinations, passport numbers and CPR numbers. No field key or output may contain them. The `complete` flag reads `cpr_no` only as "filled or not".
- **SQL safety:** `Field::$sql` is written in code, never from input; every filter value is a bound parameter; sort direction is checked against `asc|desc` before it is embedded. Queries are read-only.
- **Portability:** no MySQL-only syntax outside `Sql` (no `WITH ROLLUP`, no `GROUPING()`, no bare `CONCAT`, no `DATE_FORMAT`). `LIKE` uses `ESCAPE '!'`. Text `is`/`is_not` compare `LOWER()` on both sides. Age is computed in SQL (so it can be filtered, sorted and totalled).
- **Extension point for spec 15 (M4.2):** `Subject::fields()` is the only place the engine and validator read fields from; M4.2 overrides it in `People`/`Entries` to append extra-field `Field`s (sql = a `person_field_values` subquery). Nothing is built for that now.
- **Excel:** OpenSpout `OpenSpout\Writer\XLSX\Writer` + `OpenSpout\Common\Entity\Row::fromValues`, one sheet, column labels in row 1, `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, filename `{Str::slug(report name)}.xlsx`.
- **Commits:** end every commit message with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Never commit `.superpowers/` or `~$*`.

---

## File Structure

```
app/Services/Reports/Builder/FieldType.php              # NEW: types, their operators, totals, groupability, operator labels
app/Services/Reports/Builder/Field.php                  # NEW: one whitelisted field (key, label, section, type, sql, options) + display()
app/Services/Reports/Builder/Subject.php                # NEW: abstract subject (key, label, gameBased, query, fields, defaultColumns)
app/Services/Reports/Builder/Sql.php                    # NEW: the only dialect-specific SQL (month, year, age, today, filled, fullName)
app/Services/Reports/Builder/Registry.php               # NEW: the six subjects
app/Services/Reports/Builder/ReportDefinition.php       # NEW: versioned definition, validation, limits
app/Services/Reports/Builder/Engine.php                 # NEW: filters, detail rows, group and grand totals
app/Services/Reports/Builder/ReportResult.php           # NEW: result + lines() for screen and Excel
app/Services/Reports/Builder/Subjects/People.php        # NEW (Task 1)
app/Services/Reports/Builder/Subjects/{Entries,Submissions,Phase1Answers,Phase2Numbers,ClothingSizes}.php  # NEW (Task 3)
database/migrations/2026_11_12_000100_create_saved_reports_table.php  # NEW
app/Models/SavedReport.php                              # NEW
app/Policies/SavedReportPolicy.php                      # NEW (auto-discovered)
app/Filament/Pages/Reports.php + resources/views/filament/pages/reports.blade.php                # NEW: /admin/reports
app/Filament/Pages/ReportBuilder.php + resources/views/filament/pages/report-builder.blade.php    # NEW: /admin/report-builder/{report?}
tests/Feature/ReportBuilder/{DefinitionTest,EngineTest,SubjectsTest,SavedReportTest,BuilderPageTest}.php
```

---

### Task 1: Fields, subjects, People subject and the validated definition

**Files:**
- Create: `app/Services/Reports/Builder/{FieldType,Field,Subject,Sql,Registry,ReportDefinition}.php`, `app/Services/Reports/Builder/Subjects/People.php`
- Test: `tests/Feature/ReportBuilder/DefinitionTest.php`

**Interfaces:**
- Consumes: `Person::REQUIRED_FOR_COMPLETE`, enums `PersonType`, `PersonGender` (each has `label()`), `Game`.
- Produces (namespace `App\Services\Reports\Builder`):
  - `enum FieldType: string` (`Text`, `Enum`, `Number`, `Date`, `Boolean`) with `operators(): list<string>`, `totals(): list<string>`, `groupable(): bool`, `const OPERATORS` (op ⇒ label).
  - `final readonly class Field(string $key, string $label, string $section, FieldType $type, string $sql, array $options = [])`; static `text|number|boolean(key, label, section, sql)`, `date(key, label, section, sqlExpr)` (wraps in `DATE(...)`), `enum(key, label, section, sql, array $options)`, `labels(class-string<BackedEnum>): array<string,string>`; `display(mixed $raw): string|int|float|null`.
  - `abstract class Subject`: `key(): string`, `label(): string`, `gameBased(): bool`, `query(?int $gameId): Illuminate\Database\Query\Builder`, `defaultColumns(): list<string>`, `fields(): array<string, Field>` (built from protected `define(): list<Field>`).
  - `final class Sql`: `month(string)`, `year(string)`, `age(string $dob, string $on)`, `today()`, `filled(string)`, `fullName()` — all return SQL strings.
  - `final class Registry`: `const SUBJECTS` (class list), `all(): array<string, Subject>`, `get(string): ?Subject`.
  - `Subjects\People` (key `people`), static `onLists(int $gameId): Closure` (subquery of person ids on any list of the game).
  - `final readonly class ReportDefinition` with public `Subject $subject`, `?int $gameId`, `array $columns`, `array $filters`, `array $groups`, `array $totals`, `array $sort`; consts `VERSION = 1`, `MAX_COLUMNS = 30`, `MAX_FILTERS = 20`, `MAX_GROUPS = 2`, `BUCKETS = ['month', 'year']`; `static fresh(Subject, ?int $gameId = null): array`, `static fromArray(array): self` (throws `ValidationException`), `toArray(): array`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ReportBuilder/DefinitionTest.php`:

```php
<?php

use App\Models\Game;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;
use Illuminate\Validation\ValidationException;

/** @return array<string, string> first message per error key; [] when the definition is valid */
function definitionErrors(array $changes): array
{
    try {
        ReportDefinition::fromArray([...ReportDefinition::fresh(Registry::get('people')), ...$changes]);

        return [];
    } catch (ValidationException $e) {
        return array_map(fn (array $messages) => $messages[0], $e->errors());
    }
}

it('round-trips a fresh people definition with its version', function () {
    $def = ReportDefinition::fromArray(ReportDefinition::fresh(Registry::get('people')));

    expect($def->toArray())->toBe([
        'version' => 1,
        'subject' => 'people',
        'game_id' => null,
        'columns' => ['full_name', 'code', 'type', 'gender', 'dob', 'association'],
        'filters' => [],
        'groups' => [],
        'totals' => [],
        'sort' => ['field' => 'full_name', 'dir' => 'asc'],
    ]);
});

it('accepts and normalises filters, groups and totals', function () {
    $game = Game::factory()->create();

    $def = ReportDefinition::fromArray([...ReportDefinition::fresh(Registry::get('people')),
        'game_id' => (string) $game->id,
        'columns' => ['full_name', 'age', 'dob'],
        'filters' => [
            ['field' => 'age', 'op' => 'between', 'value' => ['18', '25.5']],
            ['field' => 'type', 'op' => 'in', 'value' => ['athlete']],
            ['field' => 'has_photo', 'op' => 'yes', 'value' => 'ignored'],
        ],
        'groups' => [['field' => 'dob', 'bucket' => 'year'], ['field' => 'gender', 'bucket' => '']],
        'totals' => ['age' => 'avg', 'dob' => 'min', 'full_name' => ''],
        'sort' => ['field' => 'age', 'dir' => 'desc'],
    ]);

    expect($def->toArray())->toMatchArray([
        'game_id' => $game->id,
        'filters' => [
            ['field' => 'age', 'op' => 'between', 'value' => [18, 25.5]],
            ['field' => 'type', 'op' => 'in', 'value' => ['athlete']],
            ['field' => 'has_photo', 'op' => 'yes', 'value' => null],
        ],
        'groups' => [['field' => 'dob', 'bucket' => 'year'], ['field' => 'gender', 'bucket' => null]],
        'totals' => ['age' => 'avg', 'dob' => 'min'],
        'sort' => ['field' => 'age', 'dir' => 'desc'],
    ]);
});

it('refuses a bad definition', function (array $changes, string $key, string $message) {
    expect(definitionErrors($changes))->toHaveKey($key, $message);
})->with([
    'unknown subject' => [['subject' => 'invoices'], 'subject', 'Choose a subject from the list.'],
    'another version' => [['version' => 2], 'version', 'This report was saved by another version of the builder.'],
    'unknown column' => [['columns' => ['full_name', 'passport_no']], 'columns', 'Choose one or more of the listed columns.'],
    'no columns' => [['columns' => []], 'columns', 'Choose one or more of the listed columns.'],
    'duplicate column' => [['columns' => ['code', 'code']], 'columns', 'Choose one or more of the listed columns.'],
    'too many columns' => [['columns' => array_fill(0, 31, 'code')], 'columns', 'At most 30 columns.'],
    'unknown filter field' => [['filters' => [['field' => 'blood_group', 'op' => 'is', 'value' => 'A+']]], 'filters.0.field', 'Choose a field to filter on.'],
    'operator of another type' => [['filters' => [['field' => 'age', 'op' => 'contains', 'value' => '2']]], 'filters.0.op', 'Choose how to compare.'],
    'number that is not a number' => [['filters' => [['field' => 'age', 'op' => 'gt', 'value' => 'abc']]], 'filters.0.value', 'Enter a valid value for Age (today).'],
    'impossible date' => [['filters' => [['field' => 'dob', 'op' => 'before', 'value' => '2026-02-30']]], 'filters.0.value', 'Enter a valid value for Date of birth.'],
    'between with one value' => [['filters' => [['field' => 'age', 'op' => 'between', 'value' => [18]]]], 'filters.0.value', 'Enter a valid value for Age (today).'],
    'enum value not offered' => [['filters' => [['field' => 'type', 'op' => 'in', 'value' => ['coach']]]], 'filters.0.value', 'Enter a valid value for Type.'],
    'empty text' => [['filters' => [['field' => 'code', 'op' => 'is', 'value' => '']]], 'filters.0.value', 'Enter a valid value for Person code.'],
    'too many filters' => [['filters' => array_fill(0, 21, ['field' => 'code', 'op' => 'not_empty', 'value' => null])], 'filters', 'At most 20 filters.'],
    'three group levels' => [['groups' => array_fill(0, 3, ['field' => 'type', 'bucket' => null])], 'groups', 'At most 2 group levels.'],
    'group by a number' => [['groups' => [['field' => 'age', 'bucket' => null]]], 'groups.0.field', 'Choose a field to group by.'],
    'date group by day' => [['groups' => [['field' => 'dob', 'bucket' => 'day']]], 'groups.0.bucket', 'Group dates by month or year.'],
    'total on text' => [['totals' => ['code' => 'sum']], 'totals', 'Totals apply to chosen number columns (sum, average, min, max) and date columns (min, max).'],
    'total on a column not chosen' => [['totals' => ['age' => 'sum']], 'totals', 'Totals apply to chosen number columns (sum, average, min, max) and date columns (min, max).'],
    'sort on an unknown field' => [['sort' => ['field' => 'cpr_no', 'dir' => 'asc']], 'sort', 'Choose a field to sort by.'],
    'sort direction injected' => [['sort' => ['field' => 'code', 'dir' => 'asc; drop table people']], 'sort', 'Choose a field to sort by.'],
    'unknown game' => [['game_id' => 999], 'game_id', 'Choose a game from the list.'],
]);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/ReportBuilder/DefinitionTest.php`
Expected: FAIL with `Class "App\Services\Reports\Builder\Registry" not found`.

- [ ] **Step 3: Write `FieldType`, `Field`, `Sql`, `Subject`, `Registry`**

`app/Services/Reports/Builder/FieldType.php`:

```php
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
```

`app/Services/Reports/Builder/Field.php`:

```php
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
```

`app/Services/Reports/Builder/Sql.php`:

```php
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

    public static function filled(string $column): string
    {
        return "({$column} IS NOT NULL AND {$column} <> '')";
    }

    /** The person's full name, else first and last name. */
    public static function fullName(): string
    {
        return self::sqlite()
            ? "COALESCE(NULLIF(people.full_name, ''), people.first_name || ' ' || people.last_name)"
            : "COALESCE(NULLIF(people.full_name, ''), CONCAT_WS(' ', people.first_name, people.last_name))";
    }
}
```

`app/Services/Reports/Builder/Subject.php`:

```php
<?php

namespace App\Services\Reports\Builder;

use Illuminate\Database\Query\Builder;

/** A reportable subject (spec 14): where its rows come from and the fields it offers. Columns are never exposed automatically. */
abstract class Subject
{
    abstract public function key(): string;

    abstract public function label(): string;

    /** True when a game must be chosen (Entries, Submissions, Phase 1 answers, Phase 2 numbers). */
    abstract public function gameBased(): bool;

    /** The subject's rows, narrowed to the game when one is given. No select, no order: the engine adds them. */
    abstract public function query(?int $gameId): Builder;

    /** @return list<string> the first one is also the default sort */
    abstract public function defaultColumns(): array;

    /** @return list<Field> */
    abstract protected function define(): array;

    /**
     * The fields, keyed. The engine and the validator read fields only from here, so a subject can append dynamic
     * fields (spec 15 extra fields, M4.2) by overriding this method.
     *
     * @return array<string, Field>
     */
    public function fields(): array
    {
        $fields = [];
        foreach ($this->define() as $field) {
            $fields[$field->key] = $field;
        }

        return $fields;
    }
}
```

`app/Services/Reports/Builder/Registry.php`:

```php
<?php

namespace App\Services\Reports\Builder;

/** The subjects, in picker order. */
final class Registry
{
    public const SUBJECTS = [
        Subjects\People::class,
    ];

    /** @return array<string, Subject> */
    public static function all(): array
    {
        $subjects = [];
        foreach (self::SUBJECTS as $class) {
            $subject = new $class;
            $subjects[$subject->key()] = $subject;
        }

        return $subjects;
    }

    public static function get(string $key): ?Subject
    {
        return self::all()[$key] ?? null;
    }
}
```

- [ ] **Step 4: Write the People subject**

`app/Services/Reports/Builder/Subjects/People.php`:

```php
<?php

namespace App\Services\Reports\Builder\Subjects;

use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Models\Person;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\Sql;
use App\Services\Reports\Builder\Subject;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One row is a registry person. Admin-only (no association has the builder), so no tenant scope applies. */
final class People extends Subject
{
    public function key(): string
    {
        return 'people';
    }

    public function label(): string
    {
        return 'People';
    }

    public function gameBased(): bool
    {
        return false;
    }

    /** Person ids on any Long or Short List of the game (subquery for whereIn). */
    public static function onLists(int $gameId): Closure
    {
        return fn (Builder $q) => $q->select('entries.person_id')->from('entries')
            ->join('submissions', 'submissions.id', '=', 'entries.submission_id')
            ->where('submissions.game_id', $gameId);
    }

    public function query(?int $gameId): Builder
    {
        return DB::table('people')
            ->join('associations', 'associations.id', '=', 'people.association_id')
            ->when($gameId, fn (Builder $q) => $q->whereIn('people.id', self::onLists($gameId)));
    }

    public function defaultColumns(): array
    {
        return ['full_name', 'code', 'type', 'gender', 'dob', 'association'];
    }

    protected function define(): array
    {
        // Same rule as Person::missingFields(), as "filled or not" only: cpr_no's value never leaves the database.
        $complete = fn (string $type) => implode(' AND ', array_map(fn (string $c) => Sql::filled("people.{$c}"), array_keys(Person::REQUIRED_FOR_COMPLETE[$type])));

        return [
            Field::text('full_name', 'Name', 'Person', Sql::fullName()),
            Field::text('code', 'Person code', 'Person', 'people.code'),
            Field::enum('type', 'Type', 'Person', 'people.type', Field::labels(PersonType::class)),
            Field::enum('gender', 'Gender', 'Person', 'people.gender', Field::labels(PersonGender::class)),
            Field::text('nationality', 'Nationality', 'Person', 'people.nationality'),
            Field::date('dob', 'Date of birth', 'Person', 'people.dob'),
            Field::number('age', 'Age (today)', 'Person', Sql::age('people.dob', Sql::today())),
            Field::text('function', 'Function', 'Person', 'people.function'),
            Field::boolean('complete', 'Complete', 'Person',
                "(CASE WHEN people.type = 'athlete' THEN ({$complete('athlete')}) ELSE ({$complete('official')}) END)"),
            Field::text('association', 'Association', 'Association', 'associations.name'),
            Field::text('association_code', 'Association code', 'Association', 'associations.code'),
            Field::date('passport_expiry', 'Passport expiry', 'Documents', 'people.passport_expiry'),
            Field::date('cpr_expiry', 'CPR expiry', 'Documents', 'people.cpr_expiry'),
            Field::boolean('has_photo', 'Has photo', 'Documents', Sql::filled('people.photo_path')),
            Field::boolean('has_passport_scan', 'Has passport scan', 'Documents', Sql::filled('people.passport_scan_path')),
        ];
    }
}
```

- [ ] **Step 5: Write `ReportDefinition`**

`app/Services/Reports/Builder/ReportDefinition.php`:

```php
<?php

namespace App\Services\Reports\Builder;

use App\Models\Game;
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
     */
    private function __construct(
        public Subject $subject,
        public ?int $gameId,
        public array $columns,
        public array $filters,
        public array $groups,
        public array $totals,
        public array $sort,
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

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $errors = [];
        if (($data['version'] ?? null) !== self::VERSION) {
            $errors['version'] = 'This report was saved by another version of the builder.';
        }
        $subject = is_string($data['subject'] ?? null) ? Registry::get($data['subject']) : null;
        if ($subject === null) {
            throw ValidationException::withMessages(['subject' => 'Choose a subject from the list.', ...$errors]);
        }
        $fields = $subject->fields();

        $gameId = $data['game_id'] ?? null;
        $gameId = $gameId === '' ? null : $gameId;
        if ($gameId !== null && (! is_numeric($gameId) || ! Game::whereKey((int) $gameId)->exists())) {
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

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return new self($subject, $gameId === null ? null : (int) $gameId, $columns, $cleanFilters, $cleanGroups, $totals,
            ['field' => $sort['field'], 'dir' => $sort['dir']]);
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
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `$PHP vendor/bin/pest tests/Feature/ReportBuilder/DefinitionTest.php`
Expected: PASS (24 tests).

- [ ] **Step 7: Commit**

```bash
$PHP vendor/bin/pint
git add app/Services/Reports/Builder tests/Feature/ReportBuilder/DefinitionTest.php
git commit -m "feat: report builder fields, People subject and validated report definition

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Engine — filters, 500-row cap, grouping and totals in SQL

**Files:**
- Create: `app/Services/Reports/Builder/Engine.php`, `app/Services/Reports/Builder/ReportResult.php`
- Test: `tests/Feature/ReportBuilder/EngineTest.php`

**Interfaces:**
- Consumes: `ReportDefinition` (public props), `Subject::query()/fields()`, `Field`, `Sql::month()/year()` (Task 1).
- Produces:
  - `Engine` (resolve with `app(Engine::class)`): `const SCREEN_LIMIT = 500`; `run(ReportDefinition $def, ?int $limit = self::SCREEN_LIMIT): ReportResult` (`null` = all rows, for Excel).
  - `final readonly class ReportResult`: `list<Field> $columns`; `list<array{cells: array<string, mixed>, keys: list<?string>}> $rows` (raw values); `int $rowCount` (all matching rows); `array<string, mixed> $totals` (grand totals, field key ⇒ raw value); `array<string, array{label: string, count: int, totals: array<string, mixed>}> $groups` (keyed by `keyId(keys prefix)`); `truncated(): bool`; `lines(): list<array{kind: 'group'|'row'|'total', cells: list<string|int|float|null>}>`; `static keyId(list<?string>): string`.
  - Line format: a group line's first cell is `{'— ' × level}{label} ({count})`; the grand total line's first cell is `Total ({rowCount})`; their other cells hold the column's total or `''`. If the first column itself has a total, it is appended as `label · value`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ReportBuilder/EngineTest.php`:

```php
<?php

use App\Enums\PersonGender;
use App\Models\Association;
use App\Models\Person;
use App\Services\Reports\Builder\Engine;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;
use App\Services\Reports\Builder\ReportResult;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-10 10:00'));
    $judo = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $karate = Association::factory()->create(['code' => 'BKF', 'name' => 'Bahrain Karate Federation']);
    Person::factory()->create(['association_id' => $judo->id, 'first_name' => 'Ali', 'last_name' => 'Hasan', 'dob' => '2005-12-05',
        'passport_expiry' => '2027-01-15', 'photo_path' => 'photos/a.jpg']);
    Person::factory()->create(['association_id' => $judo->id, 'first_name' => 'Omar', 'last_name' => 'Saleh', 'full_name' => 'Omar Saleh Al-Bahraini',
        'dob' => '2004-01-01', 'passport_expiry' => '2028-06-30']);
    Person::factory()->official()->create(['association_id' => $judo->id, 'first_name' => 'Coach', 'last_name' => 'Karim', 'dob' => '1980-05-20',
        'passport_expiry' => '2027-03-01']);
    Person::factory()->complete()->create(['association_id' => $karate->id, 'first_name' => 'Mariam', 'last_name' => 'Ahmed', 'full_name' => 'Mariam Ahmed',
        'gender' => PersonGender::Female, 'dob' => '2005-03-10', 'passport_expiry' => '2027-01-20']);
});

function runPeople(array $changes = [], ?int $limit = Engine::SCREEN_LIMIT): ReportResult
{
    return app(Engine::class)->run(ReportDefinition::fromArray([...ReportDefinition::fresh(Registry::get('people')), ...$changes]), $limit);
}

/** @return list<string> */
function peopleNames(ReportResult $r): array
{
    return array_map(fn (array $row) => $row['cells']['full_name'], $r->rows);
}

it('runs the default people report sorted by name', function () {
    $r = runPeople();

    expect($r->rowCount)->toBe(4)
        ->and(peopleNames($r))->toBe(['Ali Hasan', 'Coach Karim', 'Mariam Ahmed', 'Omar Saleh Al-Bahraini'])
        ->and($r->lines()[0])->toBe(['kind' => 'row', 'cells' => ['Ali Hasan', 'BJF-AT-0001', 'Athlete', 'Male', '2005-12-05', 'Bahrain Judo Federation']])
        ->and($r->lines()[4])->toBe(['kind' => 'total', 'cells' => ['Total (4)', '', '', '', '', '']]);
});

it('computes age today, completeness and attachments in SQL', function () {
    $r = runPeople(['columns' => ['full_name', 'age', 'complete', 'has_photo'], 'sort' => ['field' => 'age', 'dir' => 'desc']]);

    expect(array_map(fn ($l) => $l['cells'], array_slice($r->lines(), 0, 4)))->toBe([
        ['Coach Karim', 46, 'No', 'No'],
        ['Omar Saleh Al-Bahraini', 22, 'No', 'No'],
        ['Mariam Ahmed', 21, 'Yes', 'Yes'],
        ['Ali Hasan', 20, 'No', 'Yes'],
    ]);
});

it('applies each operator with bound values', function (array $filter, array $expected) {
    expect(peopleNames(runPeople(['filters' => [$filter]])))->toBe($expected);
})->with([
    'contains, any case' => [['field' => 'full_name', 'op' => 'contains', 'value' => 'KARIM'], ['Coach Karim']],
    'contains a wildcard literally' => [['field' => 'full_name', 'op' => 'contains', 'value' => '%'], []],
    'is, any case' => [['field' => 'association', 'op' => 'is', 'value' => 'bahrain judo federation'], ['Ali Hasan', 'Coach Karim', 'Omar Saleh Al-Bahraini']],
    'is not' => [['field' => 'association', 'op' => 'is_not', 'value' => 'Bahrain Judo Federation'], ['Mariam Ahmed']],
    'is empty' => [['field' => 'function', 'op' => 'empty', 'value' => null], ['Ali Hasan', 'Mariam Ahmed', 'Omar Saleh Al-Bahraini']],
    'is not empty' => [['field' => 'function', 'op' => 'not_empty', 'value' => null], ['Coach Karim']],
    'enum any of' => [['field' => 'type', 'op' => 'in', 'value' => ['official']], ['Coach Karim']],
    'enum none of' => [['field' => 'gender', 'op' => 'not_in', 'value' => ['male']], ['Mariam Ahmed']],
    'number between' => [['field' => 'age', 'op' => 'between', 'value' => ['20', '21']], ['Ali Hasan', 'Mariam Ahmed']],
    'number greater than' => [['field' => 'age', 'op' => 'gt', 'value' => '30'], ['Coach Karim']],
    'number equal' => [['field' => 'age', 'op' => 'eq', 'value' => '22'], ['Omar Saleh Al-Bahraini']],
    'date before' => [['field' => 'passport_expiry', 'op' => 'before', 'value' => '2027-02-01'], ['Ali Hasan', 'Mariam Ahmed']],
    'date on' => [['field' => 'passport_expiry', 'op' => 'on', 'value' => '2027-03-01'], ['Coach Karim']],
    'date between, inclusive' => [['field' => 'dob', 'op' => 'between', 'value' => ['2004-01-01', '2005-03-10']], ['Mariam Ahmed', 'Omar Saleh Al-Bahraini']],
    'boolean yes' => [['field' => 'complete', 'op' => 'yes', 'value' => null], ['Mariam Ahmed']],
    'boolean no' => [['field' => 'has_photo', 'op' => 'no', 'value' => null], ['Coach Karim', 'Omar Saleh Al-Bahraini']],
]);

it('caps the rows on screen but counts them all', function () {
    $capped = runPeople([], 2);
    $all = runPeople([], null);

    expect($capped->rows)->toHaveCount(2)->and($capped->rowCount)->toBe(4)->and($capped->truncated())->toBeTrue()
        ->and($all->rows)->toHaveCount(4)->and($all->truncated())->toBeFalse()
        ->and(Engine::SCREEN_LIMIT)->toBe(500);
});

it('groups two levels with counts and totals and lists subtotal lines in order', function () {
    $r = runPeople([
        'columns' => ['association', 'type', 'full_name', 'age'],
        'groups' => [['field' => 'association', 'bucket' => null], ['field' => 'type', 'bucket' => null]],
        'totals' => ['age' => 'sum'],
    ]);

    expect(array_map(fn ($l) => $l['cells'], $r->lines()))->toBe([
        ['Bahrain Judo Federation (3)', '', '', 88],
        ['— Athlete (2)', '', '', 42],
        ['Bahrain Judo Federation', 'Athlete', 'Ali Hasan', 20],
        ['Bahrain Judo Federation', 'Athlete', 'Omar Saleh Al-Bahraini', 22],
        ['— Team official (1)', '', '', 46],
        ['Bahrain Judo Federation', 'Team official', 'Coach Karim', 46],
        ['Bahrain Karate Federation (1)', '', '', 21],
        ['— Athlete (1)', '', '', 21],
        ['Bahrain Karate Federation', 'Athlete', 'Mariam Ahmed', 21],
        ['Total (4)', '', '', 109],
    ])->and(array_column($r->lines(), 'kind'))->toBe(['group', 'group', 'row', 'row', 'group', 'row', 'group', 'group', 'row', 'total']);
});

it('groups dates by year and by month', function () {
    $groupLines = fn (string $bucket) => array_values(array_map(fn ($l) => $l['cells'],
        array_filter(runPeople(['columns' => ['full_name', 'passport_expiry'], 'totals' => ['passport_expiry' => 'min'],
            'groups' => [['field' => 'passport_expiry', 'bucket' => $bucket]]])->lines(), fn ($l) => $l['kind'] === 'group')));

    expect($groupLines('year'))->toBe([['2027 (3)', '2027-01-15'], ['2028 (1)', '2028-06-30']])
        ->and($groupLines('month'))->toBe([['2027-01 (2)', '2027-01-15'], ['2027-03 (1)', '2027-03-01'], ['2028-06 (1)', '2028-06-30']]);
});

it('totals numbers and dates over all rows', function () {
    $total = fn (string $field, string $fn) => runPeople(['columns' => ['full_name', $field], 'totals' => [$field => $fn]])->totals[$field];

    expect($total('age', 'avg'))->toEqual(27.25)
        ->and($total('age', 'min'))->toEqual(20)
        ->and($total('age', 'max'))->toEqual(46)
        ->and($total('passport_expiry', 'max'))->toBe('2028-06-30');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/ReportBuilder/EngineTest.php`
Expected: FAIL with `Class "App\Services\Reports\Builder\Engine" not found`.

- [ ] **Step 3: Write `ReportResult`**

`app/Services/Reports/Builder/ReportResult.php`:

```php
<?php

namespace App\Services\Reports\Builder;

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
     * @param  array<string, mixed>  $totals
     * @return list<string|int|float>
     */
    private function totalCells(string $label, array $totals): array
    {
        $cells = array_map(fn (Field $f) => array_key_exists($f->key, $totals) && $totals[$f->key] !== null ? $f->display($totals[$f->key]) : '', $this->columns);
        $cells[0] = $cells[0] === '' ? $label : "{$label} · {$cells[0]}";

        return $cells;
    }
}
```

- [ ] **Step 4: Write `Engine`**

`app/Services/Reports/Builder/Engine.php`:

```php
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
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `$PHP vendor/bin/pest tests/Feature/ReportBuilder`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
$PHP vendor/bin/pint
git add app/Services/Reports/Builder/Engine.php app/Services/Reports/Builder/ReportResult.php tests/Feature/ReportBuilder/EngineTest.php
git commit -m "feat: report builder engine with portable SQL grouping, totals and the 500-row screen cap

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: The five other subjects, the game filter and the sensitive-data guard

**Files:**
- Create: `app/Services/Reports/Builder/Subjects/{Entries,Submissions,Phase1Answers,Phase2Numbers,ClothingSizes}.php`
- Modify: `app/Services/Reports/Builder/Registry.php` (`SUBJECTS`)
- Test: `tests/Feature/ReportBuilder/SubjectsTest.php`

**Interfaces:**
- Consumes: `Subject`, `Field`, `Sql`, `People::onLists()` (Task 1); `Engine`, `ReportResult` (Task 2); `GamePhase::NAMES`; enums `SubmissionStatus`, `EventGender`, `InterestAnswerValue`, `PersonType`, `PersonGender`; M4's `ShortListChecks::overLimits()` (test oracle only).
- Produces subject keys and fields:
  - `entries` (game-based): `game, phase (3 Long List / 4 Short List), status, association, sport, event, event_gender, code, full_name, type, gender, dob, age (at game start), nationality, passport_expiry`.
  - `submissions` (game-based): `game, association, phase, status, reference, revision, submitted_at, approved_at, deadline, over_limit, justification`.
  - `phase1_answers` (game-based): `game, association, sport, answer, status`.
  - `phase2_numbers` (game-based): `game, association, sport, event, gender, count, status`.
  - `clothing_sizes` (game optional): `association, code, full_name, type, item, size`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ReportBuilder/SubjectsTest.php`:

```php
<?php

use App\Enums\EventGender;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Services\Phases\ShortListChecks;
use App\Services\Reports\Builder\Engine;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;
use App\Services\Reports\Builder\ReportResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-10 10:00'));
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games', 'start_date' => '2026-12-01', 'end_date' => '2026-12-10']);
    $this->otherGame = Game::factory()->create(['name' => 'Gulf Games']);
    $this->fed = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $this->game->associations()->attach($this->fed);
    $shirt = ClothingItem::factory()->create(['name' => 'T-Shirt', 'sizes' => ['S', 'M', 'L']]);
    $shoes = ClothingItem::factory()->create(['name' => 'Shoes', 'sizes' => ['42', '43']]);
    $this->game->clothingItems()->attach($shirt);
    $judo = GameSport::factory()->create(['game_id' => $this->game->id, 'p4_max_men' => 1,
        'sport_id' => Sport::factory()->create(['association_id' => $this->fed->id, 'name' => 'Judo'])->id]);
    $m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);

    $this->ali = Person::factory()->create(['association_id' => $this->fed->id, 'first_name' => 'Ali', 'last_name' => 'Hasan', 'dob' => '2005-12-05',
        'passport_no' => 'SECRETPP1', 'cpr_no' => '990000001', 'blood_group' => 'SECRET-BLOOD', 'allergies' => 'SECRET-ALLERGY',
        'chronic_conditions' => 'SECRET-CONDITION', 'medication' => 'SECRET-MEDICATION', 'insurance_policy_no' => 'SECRET-POLICY']);
    $omar = Person::factory()->create(['association_id' => $this->fed->id, 'first_name' => 'Omar', 'last_name' => 'Saleh', 'dob' => '2004-01-01']);
    $coach = Person::factory()->official()->create(['association_id' => $this->fed->id, 'first_name' => 'Coach', 'last_name' => 'Karim',
        'meal_requirement' => 'Vegan']);
    Person::factory()->create(['association_id' => $this->fed->id, 'first_name' => 'Not', 'last_name' => 'Entered']);
    $this->ali->syncSizes([$shirt->id => 'L', $shoes->id => '42']);
    $coach->syncSizes([$shirt->id => 'M']);

    $sub = fn (int $phase) => Submission::for($this->game, $this->fed, $phase);
    $athlete = fn (Submission $s, Person $p) => $s->entries()->create(['person_id' => $p->id, 'game_event_id' => $m60->id, 'game_sport_id' => $judo->id]);
    $sub(1)->answers()->create(['game_sport_id' => $judo->id, 'answer' => 'yes']);
    $sub(2)->numbers()->create(['game_event_id' => $m60->id, 'count' => 2]);
    $p3 = $sub(3);
    $athlete($p3, $this->ali);
    $athlete($p3, $omar);
    $p3->entries()->create(['person_id' => $coach->id, 'game_event_id' => null, 'game_sport_id' => $judo->id]);
    $p3->details()->create(['person_id' => $this->ali->id, 'insurance_policy_no' => 'SECRET-TRAVEL-POLICY', 'vaccinations' => 'SECRET-VACCINES']);
    $this->p4 = $sub(4); // created while Phase 3 is a draft: nothing preloaded
    $athlete($this->p4, $this->ali);
    $athlete($this->p4, $omar);
    $p3->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BJF-G001-P3-R1', 'submitted_at' => '2026-11-05 09:00:00']);
    $this->p4->update(['justification' => 'Both can medal', 'deadline_override' => '2026-11-20 23:59:00']);
    $this->game->phase(4)->update(['deadline' => '2026-11-15 23:59:00']);
});

function runSubject(string $subject, ?int $gameId, array $changes = []): ReportResult
{
    $s = Registry::get($subject);

    return app(Engine::class)->run(ReportDefinition::fromArray([...ReportDefinition::fresh($s, $gameId), 'columns' => array_keys($s->fields()), ...$changes]));
}

/** @return list<list<mixed>> displayed values of the given columns, row by row */
function shown(ReportResult $r, string ...$keys): array
{
    $fields = collect($r->columns)->keyBy('key');

    return array_map(fn (array $row) => array_map(fn (string $k) => $fields[$k]->display($row['cells'][$k]), $keys), $r->rows);
}

it('offers the six subjects', function () {
    expect(array_keys(Registry::all()))->toBe(['people', 'entries', 'submissions', 'phase1_answers', 'phase2_numbers', 'clothing_sizes']);
});

it('requires a game for game-based subjects only', function (string $subject, bool $required) {
    $errors = [];
    try {
        ReportDefinition::fromArray(ReportDefinition::fresh(Registry::get($subject)));
    } catch (ValidationException $e) {
        $errors = $e->errors();
    }

    expect($errors === ['game_id' => ['Choose a game.']])->toBe($required)
        ->and($required || $errors === [])->toBeTrue();
})->with([
    ['people', false], ['entries', true], ['submissions', true], ['phase1_answers', true], ['phase2_numbers', true], ['clothing_sizes', false],
]);

it('narrows people to those on a list of the game', function () {
    $names = fn (?int $game) => array_column(shown(runSubject('people', $game), 'full_name'), 0);

    expect($names($this->game->id))->toBe(['Ali Hasan', 'Coach Karim', 'Omar Saleh'])
        ->and($names(null))->toHaveCount(4)
        ->and($names($this->otherGame->id))->toBe([]);
});

it('reports entries with list, event or Official, and age at game start', function () {
    $r = runSubject('entries', $this->game->id, ['filters' => [['field' => 'phase', 'op' => 'in', 'value' => ['3']]]]);

    expect(shown($r, 'game', 'phase', 'status', 'sport', 'event', 'event_gender', 'full_name', 'age'))->toBe([
        ['Asian Indoor Games', 'Long List', 'Submitted', 'Judo', 'Men -60kg', 'Men', 'Ali Hasan', 20],
        ['Asian Indoor Games', 'Long List', 'Submitted', 'Judo', 'Official', null, 'Coach Karim', 21],
        ['Asian Indoor Games', 'Long List', 'Submitted', 'Judo', 'Men -60kg', 'Men', 'Omar Saleh', 22],
    ])->and(runSubject('entries', $this->game->id)->rowCount)->toBe(5)
        ->and(runSubject('entries', $this->otherGame->id)->rowCount)->toBe(0);
});

it('reports submissions with the effective deadline and the Short List over-limit flag', function () {
    $run = fn () => shown(runSubject('submissions', $this->game->id, ['sort' => ['field' => 'phase', 'dir' => 'asc']]),
        'phase', 'status', 'reference', 'submitted_at', 'deadline', 'over_limit', 'justification');

    expect($run())->toBe([
        ['Phase 1 – Entry by Sport', 'Draft', null, null, null, 'No', null],
        ['Phase 2 – Entry by Number', 'Draft', null, null, null, 'No', null],
        ['Phase 3 – Long List', 'Submitted', 'BJF-G001-P3-R1', '2026-11-05', null, 'No', null],
        ['Phase 4 – Short List', 'Draft', null, null, '2026-11-20', 'Yes', 'Both can medal'],
    ])->and(app(ShortListChecks::class)->overLimits($this->p4))->not->toBe([]);

    $this->p4->update(['deadline_override' => null]);
    expect($run()[3][4])->toBe('2026-11-15');
});

it('reports phase 1 answers and phase 2 numbers', function () {
    expect(shown(runSubject('phase1_answers', $this->game->id), 'game', 'association', 'sport', 'answer', 'status'))
        ->toBe([['Asian Indoor Games', 'Bahrain Judo Federation', 'Judo', 'Yes', 'Draft']])
        ->and(shown(runSubject('phase2_numbers', $this->game->id), 'sport', 'event', 'gender', 'count'))
        ->toBe([['Judo', 'Men -60kg', 'Men', 2]]);
});

it('reports clothing sizes, narrowed by game to listed people and the game items', function () {
    expect(shown(runSubject('clothing_sizes', $this->game->id), 'full_name', 'type', 'item', 'size'))->toBe([
        ['Ali Hasan', 'Athlete', 'T-Shirt', 'L'],
        ['Coach Karim', 'Team official', 'T-Shirt', 'M'],
    ])->and(runSubject('clothing_sizes', null)->rowCount)->toBe(3);
});

it('never exposes passport, CPR, medical, insurance or vaccination data', function () {
    foreach (Registry::all() as $subject) {
        foreach (array_keys($subject->fields()) as $key) {
            expect($key)->not->toMatch('/passport_no|cpr_no|blood|allerg|condition|medic|insurance|vaccin|injur|meal|clearance|wada/');
        }
    }

    $stored = [
        ...array_values((array) DB::table('people')->where('id', $this->ali->id)
            ->first(['passport_no', 'cpr_no', 'blood_group', 'allergies', 'chronic_conditions', 'medication', 'insurance_policy_no'])),
        ...array_values((array) DB::table('long_list_details')->first(['insurance_policy_no', 'vaccinations'])),
    ];
    $secrets = array_filter([...$stored, 'SECRETPP1', '990000001', 'SECRET-', 'Vegan']); // all set in the fixture; filter guards str_contains('')
    expect($secrets)->toHaveCount(13);

    foreach (array_keys(Registry::all()) as $subject) {
        foreach (runSubject($subject, $this->game->id)->rows as $row) {
            foreach (array_filter($row['cells'], fn ($c) => $c !== null) as $cell) {
                foreach ($secrets as $secret) {
                    expect(str_contains((string) $cell, (string) $secret))->toBeFalse("{$subject} exposed a sensitive value");
                }
            }
        }
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/ReportBuilder/SubjectsTest.php`
Expected: FAIL (`offers the six subjects` lists only `people`; the others fail on `Registry::get()` returning null).

- [ ] **Step 3: Write the subjects**

`app/Services/Reports/Builder/Subjects/Entries.php`:

```php
<?php

namespace App\Services\Reports\Builder\Subjects;

use App\Enums\EventGender;
use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Enums\SubmissionStatus;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\Sql;
use App\Services\Reports\Builder\Subject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One row is a Long List (Phase 3) or Short List (Phase 4) entry. */
final class Entries extends Subject
{
    public function key(): string
    {
        return 'entries';
    }

    public function label(): string
    {
        return 'Entries (Long and Short List)';
    }

    public function gameBased(): bool
    {
        return true;
    }

    public function query(?int $gameId): Builder
    {
        return DB::table('entries')
            ->join('submissions', 'submissions.id', '=', 'entries.submission_id')
            ->join('games', 'games.id', '=', 'submissions.game_id')
            ->join('associations', 'associations.id', '=', 'submissions.association_id')
            ->join('people', 'people.id', '=', 'entries.person_id')
            ->join('game_sports', 'game_sports.id', '=', 'entries.game_sport_id')
            ->join('sports', 'sports.id', '=', 'game_sports.sport_id')
            ->leftJoin('game_events', 'game_events.id', '=', 'entries.game_event_id')
            ->leftJoin('events', 'events.id', '=', 'game_events.event_id')
            ->whereIn('submissions.phase', [3, 4])
            ->where('submissions.game_id', $gameId);
    }

    public function defaultColumns(): array
    {
        return ['full_name', 'association', 'sport', 'event', 'phase'];
    }

    protected function define(): array
    {
        return [
            Field::text('game', 'Game', 'Game', 'games.name'),
            Field::enum('phase', 'List', 'Game', 'submissions.phase', [3 => 'Long List', 4 => 'Short List']),
            Field::enum('status', 'Submission status', 'Game', 'submissions.status', Field::labels(SubmissionStatus::class)),
            Field::text('association', 'Association', 'Association', 'associations.name'),
            Field::text('sport', 'Sport', 'Entry', 'sports.name'),
            Field::text('event', 'Event', 'Entry', "COALESCE(events.name, 'Official')"),
            Field::enum('event_gender', 'Event gender', 'Entry', 'events.gender', Field::labels(EventGender::class)),
            Field::text('code', 'Person code', 'Person', 'people.code'),
            Field::text('full_name', 'Name', 'Person', Sql::fullName()),
            Field::enum('type', 'Type', 'Person', 'people.type', Field::labels(PersonType::class)),
            Field::enum('gender', 'Gender', 'Person', 'people.gender', Field::labels(PersonGender::class)),
            Field::date('dob', 'Date of birth', 'Person', 'people.dob'),
            Field::number('age', 'Age at game start', 'Person', Sql::age('people.dob', 'games.start_date')),
            Field::text('nationality', 'Nationality', 'Person', 'people.nationality'),
            Field::date('passport_expiry', 'Passport expiry', 'Person', 'people.passport_expiry'),
        ];
    }
}
```

`app/Services/Reports/Builder/Subjects/Submissions.php`:

```php
<?php

namespace App\Services\Reports\Builder\Subjects;

use App\Enums\SubmissionStatus;
use App\Models\GamePhase;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\Subject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One row is a submission (one association, one phase). */
final class Submissions extends Subject
{
    public function key(): string
    {
        return 'submissions';
    }

    public function label(): string
    {
        return 'Submissions';
    }

    public function gameBased(): bool
    {
        return true;
    }

    public function query(?int $gameId): Builder
    {
        return DB::table('submissions')
            ->join('games', 'games.id', '=', 'submissions.game_id')
            ->join('associations', 'associations.id', '=', 'submissions.association_id')
            ->leftJoin('game_phases', fn ($j) => $j->on('game_phases.game_id', '=', 'submissions.game_id')->on('game_phases.phase', '=', 'submissions.phase'))
            ->where('submissions.game_id', $gameId);
    }

    public function defaultColumns(): array
    {
        return ['association', 'phase', 'status', 'reference', 'submitted_at'];
    }

    protected function define(): array
    {
        $phases = [];
        foreach (GamePhase::NAMES as $n => $name) {
            $phases[$n] = "Phase {$n} – {$name}";
        }

        return [
            Field::text('game', 'Game', 'Game', 'games.name'),
            Field::text('association', 'Association', 'Association', 'associations.name'),
            Field::enum('phase', 'Phase', 'Submission', 'submissions.phase', $phases),
            Field::enum('status', 'Status', 'Submission', 'submissions.status', Field::labels(SubmissionStatus::class)),
            Field::text('reference', 'Reference', 'Submission', 'submissions.reference_no'),
            Field::number('revision', 'Revision', 'Submission', 'submissions.revision'),
            Field::date('submitted_at', 'Submitted at', 'Submission', 'submissions.submitted_at'),
            Field::date('approved_at', 'Approved at', 'Submission', 'submissions.approved_at'),
            Field::date('deadline', 'Deadline (effective)', 'Submission', 'COALESCE(submissions.deadline_override, game_phases.deadline)'),
            Field::boolean('over_limit', 'Over limit (Short List)', 'Submission', self::overLimit()),
            Field::text('justification', 'Justification', 'Submission', 'submissions.justification'),
        ];
    }

    /** Same counting as ShortListChecks::over(): distinct athletes by person gender per sport, distinct officials per sport. */
    private static function overLimit(): string
    {
        $count = fn (string $where) => '(SELECT COUNT(DISTINCT e.person_id) FROM entries e JOIN people p ON p.id = e.person_id'
            ." WHERE e.submission_id = submissions.id AND e.game_sport_id = gs.id AND {$where})";

        return '(CASE WHEN submissions.phase = 4 AND EXISTS (SELECT 1 FROM game_sports gs WHERE gs.game_id = submissions.game_id AND ('
            ."(gs.p4_max_men IS NOT NULL AND {$count("e.game_event_id IS NOT NULL AND p.gender = 'male'")} > gs.p4_max_men)"
            ." OR (gs.p4_max_women IS NOT NULL AND {$count("e.game_event_id IS NOT NULL AND p.gender = 'female'")} > gs.p4_max_women)"
            ." OR (gs.p4_max_officials IS NOT NULL AND {$count('e.game_event_id IS NULL')} > gs.p4_max_officials)"
            .')) THEN 1 ELSE 0 END)';
    }
}
```

`app/Services/Reports/Builder/Subjects/Phase1Answers.php`:

```php
<?php

namespace App\Services\Reports\Builder\Subjects;

use App\Enums\InterestAnswerValue;
use App\Enums\SubmissionStatus;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\Subject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One row is a Phase 1 answer (Yes / No / Maybe) for a sport. */
final class Phase1Answers extends Subject
{
    public function key(): string
    {
        return 'phase1_answers';
    }

    public function label(): string
    {
        return 'Phase 1 answers';
    }

    public function gameBased(): bool
    {
        return true;
    }

    public function query(?int $gameId): Builder
    {
        return DB::table('interest_answers')
            ->join('submissions', 'submissions.id', '=', 'interest_answers.submission_id')
            ->join('games', 'games.id', '=', 'submissions.game_id')
            ->join('associations', 'associations.id', '=', 'submissions.association_id')
            ->join('game_sports', 'game_sports.id', '=', 'interest_answers.game_sport_id')
            ->join('sports', 'sports.id', '=', 'game_sports.sport_id')
            ->where('submissions.game_id', $gameId);
    }

    public function defaultColumns(): array
    {
        return ['association', 'sport', 'answer'];
    }

    protected function define(): array
    {
        return [
            Field::text('game', 'Game', 'Game', 'games.name'),
            Field::text('association', 'Association', 'Association', 'associations.name'),
            Field::text('sport', 'Sport', 'Answer', 'sports.name'),
            Field::enum('answer', 'Answer', 'Answer', 'interest_answers.answer', Field::labels(InterestAnswerValue::class)),
            Field::enum('status', 'Submission status', 'Answer', 'submissions.status', Field::labels(SubmissionStatus::class)),
        ];
    }
}
```

`app/Services/Reports/Builder/Subjects/Phase2Numbers.php`:

```php
<?php

namespace App\Services\Reports\Builder\Subjects;

use App\Enums\EventGender;
use App\Enums\SubmissionStatus;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\Subject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One row is a Phase 2 number for an event. */
final class Phase2Numbers extends Subject
{
    public function key(): string
    {
        return 'phase2_numbers';
    }

    public function label(): string
    {
        return 'Phase 2 numbers';
    }

    public function gameBased(): bool
    {
        return true;
    }

    public function query(?int $gameId): Builder
    {
        return DB::table('number_entries')
            ->join('submissions', 'submissions.id', '=', 'number_entries.submission_id')
            ->join('games', 'games.id', '=', 'submissions.game_id')
            ->join('associations', 'associations.id', '=', 'submissions.association_id')
            ->join('game_events', 'game_events.id', '=', 'number_entries.game_event_id')
            ->join('events', 'events.id', '=', 'game_events.event_id')
            ->join('game_sports', 'game_sports.id', '=', 'game_events.game_sport_id')
            ->join('sports', 'sports.id', '=', 'game_sports.sport_id')
            ->where('submissions.game_id', $gameId);
    }

    public function defaultColumns(): array
    {
        return ['association', 'sport', 'event', 'count'];
    }

    protected function define(): array
    {
        return [
            Field::text('game', 'Game', 'Game', 'games.name'),
            Field::text('association', 'Association', 'Association', 'associations.name'),
            Field::text('sport', 'Sport', 'Number', 'sports.name'),
            Field::text('event', 'Event', 'Number', 'events.name'),
            Field::enum('gender', 'Event gender', 'Number', 'events.gender', Field::labels(EventGender::class)),
            Field::number('count', 'Count', 'Number', 'number_entries.count'),
            Field::enum('status', 'Submission status', 'Number', 'submissions.status', Field::labels(SubmissionStatus::class)),
        ];
    }
}
```

`app/Services/Reports/Builder/Subjects/ClothingSizes.php`:

```php
<?php

namespace App\Services\Reports\Builder\Subjects;

use App\Enums\PersonType;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\Sql;
use App\Services\Reports\Builder\Subject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One row is a person's size for one clothing item. With a game: listed people and that game's items only. */
final class ClothingSizes extends Subject
{
    public function key(): string
    {
        return 'clothing_sizes';
    }

    public function label(): string
    {
        return 'Clothing sizes';
    }

    public function gameBased(): bool
    {
        return false;
    }

    public function query(?int $gameId): Builder
    {
        return DB::table('person_sizes')
            ->join('people', 'people.id', '=', 'person_sizes.person_id')
            ->join('associations', 'associations.id', '=', 'people.association_id')
            ->join('clothing_items', 'clothing_items.id', '=', 'person_sizes.clothing_item_id')
            ->when($gameId, fn (Builder $q) => $q
                ->whereIn('person_sizes.clothing_item_id', fn (Builder $s) => $s->select('clothing_item_id')->from('game_clothing_items')->where('game_id', $gameId))
                ->whereIn('people.id', People::onLists($gameId)));
    }

    public function defaultColumns(): array
    {
        return ['full_name', 'association', 'item', 'size'];
    }

    protected function define(): array
    {
        return [
            Field::text('association', 'Association', 'Association', 'associations.name'),
            Field::text('code', 'Person code', 'Person', 'people.code'),
            Field::text('full_name', 'Name', 'Person', Sql::fullName()),
            Field::enum('type', 'Type', 'Person', 'people.type', Field::labels(PersonType::class)),
            Field::text('item', 'Item', 'Size', 'clothing_items.name'),
            Field::text('size', 'Size', 'Size', 'person_sizes.size'),
        ];
    }
}
```

In `app/Services/Reports/Builder/Registry.php`, replace `SUBJECTS` with:

```php
    public const SUBJECTS = [
        Subjects\People::class,
        Subjects\Entries::class,
        Subjects\Submissions::class,
        Subjects\Phase1Answers::class,
        Subjects\Phase2Numbers::class,
        Subjects\ClothingSizes::class,
    ];
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `$PHP vendor/bin/pest tests/Feature/ReportBuilder`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
$PHP vendor/bin/pint
git add app/Services/Reports/Builder tests/Feature/ReportBuilder/SubjectsTest.php
git commit -m "feat: report builder subjects for entries, submissions, phase 1-2 and clothing sizes

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Saved reports — table, model and policy

**Files:**
- Create: `database/migrations/2026_11_12_000100_create_saved_reports_table.php`, `app/Models/SavedReport.php`, `app/Policies/SavedReportPolicy.php`
- Test: `tests/Feature/ReportBuilder/SavedReportTest.php`

**Interfaces:**
- Consumes: `ReportDefinition::fresh()`, `Registry::get()` (Task 1); `StaPermission::ViewReports`; `User::isSuperAdmin()`; `Gate::before` (Super Admin).
- Produces:
  - `App\Models\SavedReport`: fillable `name, subject, definition, visibility, created_by, updated_by, last_run_at`; casts `definition` ⇒ array, `last_run_at` ⇒ datetime; `const VISIBILITIES = ['private' => 'Only me', 'shared' => 'Everyone with report access']`; `creator(): BelongsTo`; scope `visibleTo(User)`.
  - `App\Policies\SavedReportPolicy`: `view`, `update`, `delete` (auto-discovered by Laravel).

- [ ] **Step 1: Write the failing test**

`tests/Feature/ReportBuilder/SavedReportTest.php`:

```php
<?php

use App\Enums\StaPermission;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;

function reportUser(): User
{
    $user = User::factory()->sta()->create();
    $user->givePermissionTo(StaPermission::ViewReports->value);

    return $user;
}

function savedReport(User $owner, string $visibility, string $name = 'Report'): SavedReport
{
    return SavedReport::create(['name' => $name, 'subject' => 'people', 'definition' => ReportDefinition::fresh(Registry::get('people')),
        'visibility' => $visibility, 'created_by' => $owner->id, 'updated_by' => $owner->id]);
}

it('lets the creator and a super admin change a report; others only see shared ones', function () {
    $owner = reportUser();
    $other = reportUser();
    $admin = User::factory()->superAdmin()->create();
    $private = savedReport($owner, 'private');
    $shared = savedReport($owner, 'shared');

    expect($owner->can('view', $private))->toBeTrue()
        ->and($owner->can('update', $private))->toBeTrue()
        ->and($owner->can('delete', $shared))->toBeTrue()
        ->and($other->can('view', $private))->toBeFalse()
        ->and($other->can('view', $shared))->toBeTrue()
        ->and($other->can('update', $shared))->toBeFalse()
        ->and($other->can('delete', $shared))->toBeFalse()
        ->and($admin->can('update', $private))->toBeTrue()
        ->and($admin->can('delete', $shared))->toBeTrue();

    $owner->revokePermissionTo(StaPermission::ViewReports->value);
    expect($owner->fresh()->can('view', $private))->toBeFalse();
});

it('lists the reports a user may see', function () {
    $owner = reportUser();
    $other = reportUser();
    savedReport($owner, 'private', 'Mine private');
    savedReport($owner, 'shared', 'Mine shared');
    savedReport($other, 'private', 'Theirs private');

    expect(SavedReport::visibleTo($owner)->orderBy('name')->pluck('name')->all())->toBe(['Mine private', 'Mine shared'])
        ->and(SavedReport::visibleTo($other)->orderBy('name')->pluck('name')->all())->toBe(['Mine shared', 'Theirs private'])
        ->and(SavedReport::visibleTo(User::factory()->superAdmin()->create())->count())->toBe(3);
});

it('stores the definition as versioned JSON', function () {
    $report = savedReport(reportUser(), 'private');

    expect($report->fresh()->definition)->toMatchArray(['version' => 1, 'subject' => 'people'])
        ->and($report->creator)->toBeInstanceOf(User::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/ReportBuilder/SavedReportTest.php`
Expected: FAIL with `Class "App\Models\SavedReport" not found`.

- [ ] **Step 3: Write the migration, model and policy**

`database/migrations/2026_11_12_000100_create_saved_reports_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('subject', 40);
            $t->json('definition');
            $t->string('visibility', 10)->default('private');
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('last_run_at')->nullable();
            $t->timestamps();
            $t->index(['created_by', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
```

`app/Models/SavedReport.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A report builder definition (spec 14 Storage). The definition is re-validated on every run. */
class SavedReport extends Model
{
    public const VISIBILITIES = ['private' => 'Only me', 'shared' => 'Everyone with report access'];

    protected $fillable = ['name', 'subject', 'definition', 'visibility', 'created_by', 'updated_by', 'last_run_at'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'last_run_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Own reports and shared ones; the Super Admin sees all. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isSuperAdmin()) {
            $query->where(fn (Builder $q) => $q->where('created_by', $user->id)->orWhere('visibility', 'shared'));
        }
    }
}
```

`app/Policies/SavedReportPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\StaPermission;
use App\Models\SavedReport;
use App\Models\User;

/** Spec 14: shared reports are visible to everyone with view_reports; only the creator changes them. The Super Admin passes via Gate::before. */
class SavedReportPolicy
{
    public function view(User $user, SavedReport $report): bool
    {
        return $user->can(StaPermission::ViewReports->value)
            && ((int) $report->created_by === $user->id || $report->visibility === 'shared');
    }

    public function update(User $user, SavedReport $report): bool
    {
        return $user->can(StaPermission::ViewReports->value) && (int) $report->created_by === $user->id;
    }

    public function delete(User $user, SavedReport $report): bool
    {
        return $this->update($user, $report);
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `$PHP vendor/bin/pest tests/Feature/ReportBuilder/SavedReportTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
$PHP vendor/bin/pint
git add database/migrations/2026_11_12_000100_create_saved_reports_table.php app/Models/SavedReport.php app/Policies/SavedReportPolicy.php tests/Feature/ReportBuilder/SavedReportTest.php
git commit -m "feat: saved_reports table, SavedReport model and sharing policy

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Reports list page and the builder page (live results, save, share, copy, delete)

**Files:**
- Create: `app/Filament/Pages/Reports.php`, `resources/views/filament/pages/reports.blade.php`, `app/Filament/Pages/ReportBuilder.php`, `resources/views/filament/pages/report-builder.blade.php`
- Test: `tests/Feature/ReportBuilder/BuilderPageTest.php`

**Interfaces:**
- Consumes: everything in `App\Services\Reports\Builder` (Tasks 1–3), `SavedReport` + policy (Task 4), `App\Filament\Concerns\RequiresStaPermission`, `actingInAdmin()` (tests/Pest.php).
- Produces:
  - `App\Filament\Pages\Reports` → `/admin/reports` (navigation "Reports"); `mine(): Collection`, `shared(): Collection`; header action `newReport`.
  - `App\Filament\Pages\ReportBuilder` → `/admin/report-builder/{report?}` (route name `filament.admin.pages.report-builder`, not in navigation); public `?int $reportId`, `string $name`, `string $visibility`, `array $def`; methods `result(): array{0: ?ReportResult, 1: list<string>}`, `canEdit(): bool`, `subject(): ?Subject`, `save()`, `saveAsCopy()`, `delete()`, `addFilter()`, `removeFilter(int)`, `addGroup()`, `removeGroup(int)`, `moveColumn(int, int)`. Task 6 adds `export()`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ReportBuilder/BuilderPageTest.php`:

```php
<?php

use App\Enums\StaPermission;
use App\Filament\Pages\ReportBuilder;
use App\Filament\Pages\Reports;
use App\Models\Association;
use App\Models\Game;
use App\Models\Person;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;
use Illuminate\Support\Carbon;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-10 10:00'));
    $judo = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $karate = Association::factory()->create(['code' => 'BKF', 'name' => 'Bahrain Karate Federation']);
    Person::factory()->create(['association_id' => $judo->id, 'first_name' => 'Ali', 'last_name' => 'Hasan', 'dob' => '2005-12-05']);
    Person::factory()->create(['association_id' => $judo->id, 'first_name' => 'Omar', 'last_name' => 'Saleh', 'dob' => '2004-01-01']);
    Person::factory()->create(['association_id' => $karate->id, 'first_name' => 'Karim', 'last_name' => 'Ahmed', 'dob' => '2005-03-10']);

    $this->owner = User::factory()->sta()->create(['name' => 'Owner']);
    $this->owner->givePermissionTo(StaPermission::ViewReports->value);
    $this->other = User::factory()->sta()->create(['name' => 'Other']);
    $this->other->givePermissionTo(StaPermission::ViewReports->value);
});

function storedReport(User $owner, string $visibility, string $name, array $changes = []): SavedReport
{
    return SavedReport::create(['name' => $name, 'subject' => 'people', 'visibility' => $visibility, 'created_by' => $owner->id, 'updated_by' => $owner->id,
        'definition' => [...ReportDefinition::fresh(Registry::get('people')), ...$changes]]);
}

it('opens the pages only with view_reports', function () {
    $viewer = User::factory()->sta()->create();
    $viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($viewer);
    $this->get(Reports::getUrl())->assertForbidden();
    $this->get(ReportBuilder::getUrl())->assertForbidden();

    actingInAdmin($this->owner);
    $this->get(Reports::getUrl())->assertOk()->assertSee('New report');
    $this->get(ReportBuilder::getUrl())->assertOk();
});

it('lists my reports and the ones shared with me', function () {
    storedReport($this->owner, 'private', 'Mine private');
    storedReport($this->other, 'shared', 'Theirs shared');
    storedReport($this->other, 'private', 'Theirs private');
    actingInAdmin($this->owner);

    $this->get(Reports::getUrl())->assertOk()
        ->assertSeeInOrder(['My reports', 'Mine private', 'Shared', 'Theirs shared', 'by Other'])
        ->assertDontSee('Theirs private');
});

it('updates results live and saves a new report', function () {
    actingInAdmin($this->owner);

    livewire(ReportBuilder::class)
        ->assertSee('Ali Hasan')->assertSee('Karim Ahmed')->assertSee('Total (3)')
        ->call('addFilter')
        ->set('def.filters.0.op', 'contains')
        ->set('def.filters.0.value', 'ali')
        ->assertSee('Ali Hasan')->assertDontSee('Karim Ahmed')->assertSee('Total (1)')
        ->set('name', 'Judo people')
        ->set('visibility', 'shared')
        ->call('save')
        ->assertRedirect(ReportBuilder::getUrl(['report' => SavedReport::sole()]));

    expect(SavedReport::sole())->name->toBe('Judo people')->visibility->toBe('shared')->created_by->toBe($this->owner->id)
        ->and(SavedReport::sole()->definition['filters'])->toBe([['field' => 'full_name', 'op' => 'contains', 'value' => 'ali']]);
});

it('resets the definition when the subject changes and asks for a game', function () {
    actingInAdmin($this->owner);
    $game = Game::factory()->create(['name' => 'Asian Indoor Games']);

    livewire(ReportBuilder::class)
        ->set('def.subject', 'entries')
        ->assertSet('def.columns', Registry::get('entries')->defaultColumns())
        ->assertSee('Choose a game.')
        ->set('def.game_id', (string) $game->id)
        ->assertDontSee('Choose a game.')->assertSee('Total (0)');
});

it('refuses invalid values and too long names', function () {
    actingInAdmin($this->owner);

    livewire(ReportBuilder::class)
        ->set('def.columns', ['full_name', 'passport_no'])
        ->assertSee('Choose one or more of the listed columns.')
        ->call('save')->assertHasErrors('columns')
        ->set('def.columns', ['full_name'])
        ->set('name', str_repeat('x', 121))
        ->call('save')->assertHasErrors('name');

    expect(SavedReport::count())->toBe(0);
});

it('lets others run a shared report with their own filters but only save a copy', function () {
    $report = storedReport($this->owner, 'shared', 'All people');
    actingInAdmin($this->other);

    livewire(ReportBuilder::class, ['report' => $report->id])
        ->assertSee('Karim Ahmed')->assertDontSee('wire:click="save"', escape: false)
        ->set('def.filters', [['field' => 'association', 'op' => 'is', 'value' => 'Bahrain Judo Federation']])
        ->assertDontSee('Karim Ahmed')
        ->call('save')->assertForbidden();

    livewire(ReportBuilder::class, ['report' => $report->id])
        ->set('def.filters', [['field' => 'association', 'op' => 'is', 'value' => 'Bahrain Judo Federation']])
        ->call('saveAsCopy');

    $copy = SavedReport::where('created_by', $this->other->id)->sole();
    expect($copy)->name->toBe('All people (copy)')->visibility->toBe('private')
        ->and($report->fresh()->definition['filters'])->toBe([])
        ->and($report->fresh()->last_run_at)->not->toBeNull();
});

it('hides a private report from others', function () {
    $report = storedReport($this->owner, 'private', 'Mine');
    actingInAdmin($this->other);

    $this->get(ReportBuilder::getUrl(['report' => $report]))->assertForbidden();
});

it('lets the owner and a super admin delete', function () {
    $report = storedReport($this->other, 'shared', 'Theirs');
    actingInAdmin($this->owner);
    livewire(ReportBuilder::class, ['report' => $report->id])->call('delete')->assertForbidden();

    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(ReportBuilder::class, ['report' => $report->id])->call('delete')->assertRedirect(Reports::getUrl());
    $this->assertModelMissing($report);
});

it('shows an invalid stored definition as an error instead of running it', function () {
    $report = storedReport($this->owner, 'private', 'Broken', ['columns' => ['cpr_no']]);
    actingInAdmin($this->owner);

    livewire(ReportBuilder::class, ['report' => $report->id])
        ->assertOk()->assertSee('Choose one or more of the listed columns.')->assertDontSee('Ali Hasan');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/ReportBuilder/BuilderPageTest.php`
Expected: FAIL with `Class "App\Filament\Pages\Reports" not found`.

- [ ] **Step 3: Write the Reports list page**

`app/Filament/Pages/Reports.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;
use App\Models\SavedReport;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/** Spec 14: "My reports" and "Shared", plus New report. The ready-made M4 reports stay on each game. */
class Reports extends Page
{
    use RequiresStaPermission;

    protected static ?string $navigationLabel = 'Reports';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected string $view = 'filament.pages.reports';

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ViewReports;
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('newReport')->label('New report')->url(ReportBuilder::getUrl())];
    }

    /** @return Collection<int, SavedReport> */
    public function mine(): Collection
    {
        return SavedReport::where('created_by', auth()->id())->orderBy('name')->get();
    }

    /** @return Collection<int, SavedReport> */
    public function shared(): Collection
    {
        return SavedReport::visibleTo(auth()->user())->where('created_by', '<>', auth()->id())->with('creator')->orderBy('name')->get();
    }
}
```

`resources/views/filament/pages/reports.blade.php`:

```blade
<x-filament-panels::page>
    @php $subjects = \App\Services\Reports\Builder\Registry::all(); @endphp

    @foreach (['My reports' => $this->mine(), 'Shared' => $this->shared()] as $heading => $reports)
        <x-filament::section :heading="$heading">
            @forelse ($reports as $report)
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-t border-gray-200 py-2 first:border-t-0 dark:border-white/10">
                    <a href="{{ \App\Filament\Pages\ReportBuilder::getUrl(['report' => $report]) }}" class="font-medium text-primary-600 hover:underline">{{ $report->name }}</a>
                    <span class="text-xs text-gray-500">
                        {{ ($subjects[$report->subject] ?? null)?->label() ?? $report->subject }}
                        · {{ \App\Models\SavedReport::VISIBILITIES[$report->visibility] ?? $report->visibility }}
                        @if ($heading === 'Shared') · by {{ $report->creator->name }} @endif
                        · last run {{ $report->last_run_at?->format('j M Y H:i') ?? 'never' }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-500">No reports yet.</p>
            @endforelse
        </x-filament::section>
    @endforeach

    <p class="text-sm text-gray-500">The ready-made reports for a game are on that game's Reports page.</p>
</x-filament-panels::page>
```

- [ ] **Step 4: Write the builder page**

`app/Filament/Pages/ReportBuilder.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;
use App\Models\SavedReport;
use App\Services\Reports\Builder\Engine;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\FieldType;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;
use App\Services\Reports\Builder\ReportResult;
use App\Services\Reports\Builder\Subject;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Spec 14 builder. $def is raw input; ReportDefinition::fromArray() validates it on every run and save. */
class ReportBuilder extends Page
{
    use RequiresStaPermission;

    protected static ?string $slug = 'report-builder';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.report-builder';

    public ?int $reportId = null;

    public string $name = 'New report';

    public string $visibility = 'private';

    /** @var array<string, mixed> */
    public array $def = [];

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ViewReports;
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/report-builder/{report?}';
    }

    public function mount(int|string|null $report = null): void
    {
        if ($report === null) {
            $this->def = ReportDefinition::fresh(Registry::get('people'));

            return;
        }
        $saved = SavedReport::findOrFail($report);
        $this->authorize('view', $saved);
        $this->reportId = $saved->id;
        $this->name = $saved->name;
        $this->visibility = $saved->visibility;
        $this->def = $saved->definition;
        DB::table('saved_reports')->where('id', $saved->id)->update(['last_run_at' => now()]); // not updated_at: running is not editing
    }

    public function getTitle(): string
    {
        return $this->reportId ? $this->name : 'New report';
    }

    public function subject(): ?Subject
    {
        return Registry::get((string) ($this->def['subject'] ?? ''));
    }

    public function canEdit(): bool
    {
        return $this->reportId === null || auth()->user()->can('update', SavedReport::findOrFail($this->reportId));
    }

    /** @return array{0: ?ReportResult, 1: list<string>} the result, or the reasons the definition was refused */
    public function result(): array
    {
        try {
            return [app(Engine::class)->run(ReportDefinition::fromArray($this->def)), []];
        } catch (ValidationException $e) {
            return [null, array_values(array_unique(Arr::flatten($e->errors())))];
        }
    }

    public function updatedDef(mixed $value, string $key): void
    {
        $fields = $this->subject()?->fields() ?? [];
        if ($key === 'subject') {
            $subject = Registry::get((string) $value);
            $game = $this->def['game_id'] ?? null;
            if ($subject !== null) {
                $this->def = ReportDefinition::fresh($subject, filled($game) ? (int) $game : null);
            }
        } elseif (str_starts_with($key, 'columns')) {
            $this->def['totals'] = array_intersect_key((array) ($this->def['totals'] ?? []), array_flip((array) $this->def['columns']));
        } elseif (preg_match('/^filters\.(\d+)\.(field|op)$/', $key, $m) === 1) {
            $i = (int) $m[1];
            $field = $fields[$this->def['filters'][$i]['field'] ?? ''] ?? null;
            if ($field !== null) {
                $m[2] === 'field'
                    ? $this->def['filters'][$i] = self::blankFilter($field)
                    : $this->def['filters'][$i]['value'] = self::blankValue($field, (string) $value);
            }
        } elseif (preg_match('/^groups\.(\d+)\.field$/', $key, $m) === 1) {
            $field = $fields[(string) $value] ?? null;
            $this->def['groups'][(int) $m[1]]['bucket'] = $field?->type === FieldType::Date ? 'month' : null;
        }
    }

    public function addFilter(): void
    {
        $fields = $this->subject()?->fields() ?? [];
        if ($fields !== [] && count($this->def['filters'] ?? []) < ReportDefinition::MAX_FILTERS) {
            $this->def['filters'][] = self::blankFilter(reset($fields));
        }
    }

    public function removeFilter(int $i): void
    {
        unset($this->def['filters'][$i]);
        $this->def['filters'] = array_values($this->def['filters']);
    }

    public function addGroup(): void
    {
        $field = collect($this->subject()?->fields() ?? [])->first(fn (Field $f) => $f->type->groupable());
        if ($field !== null && count($this->def['groups'] ?? []) < ReportDefinition::MAX_GROUPS) {
            $this->def['groups'][] = ['field' => $field->key, 'bucket' => $field->type === FieldType::Date ? 'month' : null];
        }
    }

    public function removeGroup(int $i): void
    {
        unset($this->def['groups'][$i]);
        $this->def['groups'] = array_values($this->def['groups']);
    }

    public function moveColumn(int $i, int $by): void
    {
        $columns = array_values((array) ($this->def['columns'] ?? []));
        $j = $i + $by;
        if (isset($columns[$i], $columns[$j])) {
            [$columns[$i], $columns[$j]] = [$columns[$j], $columns[$i]];
            $this->def['columns'] = $columns;
        }
    }

    public function save(): void
    {
        $saved = $this->reportId ? SavedReport::findOrFail($this->reportId) : null;
        if ($saved !== null) {
            $this->authorize('update', $saved);
        }
        $this->persist($saved, $this->name, $this->visibility);
    }

    /** Anyone who can see a report may copy it; the copy is theirs and private. */
    public function saveAsCopy(): void
    {
        $this->persist(null, Str::limit($this->name.' (copy)', 120, ''), 'private');
    }

    public function delete(): void
    {
        $saved = SavedReport::findOrFail($this->reportId);
        $this->authorize('delete', $saved);
        $saved->delete();
        $this->redirect(Reports::getUrl());
    }

    private function persist(?SavedReport $saved, string $name, string $visibility): void
    {
        Validator::make(['name' => $name, 'visibility' => $visibility], [
            'name' => ['required', 'string', 'max:120'],
            'visibility' => ['required', Rule::in(array_keys(SavedReport::VISIBILITIES))],
        ])->validate();
        $definition = ReportDefinition::fromArray($this->def)->toArray();
        $attributes = ['name' => $name, 'subject' => $definition['subject'], 'definition' => $definition,
            'visibility' => $visibility, 'updated_by' => auth()->id()];

        if ($saved !== null) {
            $saved->update($attributes);
            Notification::make()->title('Report saved')->success()->send();

            return;
        }
        $saved = SavedReport::create([...$attributes, 'created_by' => auth()->id()]);
        $this->redirect(static::getUrl(['report' => $saved]));
    }

    /** @return array{field: string, op: string, value: mixed} */
    private static function blankFilter(Field $field): array
    {
        $op = $field->type->operators()[0];

        return ['field' => $field->key, 'op' => $op, 'value' => self::blankValue($field, $op)];
    }

    private static function blankValue(Field $field, string $op): mixed
    {
        return $field->type === FieldType::Enum ? [] : ($op === 'between' ? ['', ''] : null);
    }
}
```

`resources/views/filament/pages/report-builder.blade.php`:

```blade
<x-filament-panels::page>
    @php
        [$result, $problems] = $this->result();
        $subject = $this->subject();
        $fields = $subject?->fields() ?? [];
        $canEdit = $this->canEdit();
        $input = 'rounded border-gray-300 text-sm dark:bg-white/5';
        $noValue = ['empty', 'not_empty', 'yes', 'no'];
    @endphp

    {{-- Top bar --}}
    <div class="flex flex-wrap items-end gap-3">
        <label class="text-sm">Name
            <input type="text" wire:model="name" maxlength="120" @disabled(! $canEdit) class="block {{ $input }}">
        </label>
        <label class="text-sm">Share
            <select wire:model="visibility" @disabled(! $canEdit) class="block {{ $input }}">
                @foreach (\App\Models\SavedReport::VISIBILITIES as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @if ($canEdit)
            <x-filament::button wire:click="save">Save</x-filament::button>
        @endif
        @if ($reportId)
            <x-filament::button color="gray" wire:click="saveAsCopy">Save as copy</x-filament::button>
        @endif
        <x-filament::button color="gray" icon="heroicon-o-arrow-down-tray" wire:click="export">Export to Excel</x-filament::button>
        @if ($reportId && $canEdit)
            <x-filament::button color="danger" wire:click="delete" wire:confirm="Delete this report? This cannot be undone.">Delete</x-filament::button>
        @endif
    </div>
    @error('name') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror

    {{-- Settings --}}
    <x-filament::section heading="Settings">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm">Subject
                <select wire:model.live="def.subject" class="block w-full {{ $input }}">
                    @foreach (\App\Services\Reports\Builder\Registry::all() as $key => $s)
                        <option value="{{ $key }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Game
                <select wire:model.live="def.game_id" class="block w-full {{ $input }}">
                    <option value="">{{ $subject?->gameBased() ? 'Choose a game' : 'Any game' }}</option>
                    @foreach (\App\Models\Game::orderByDesc('start_date')->pluck('name', 'id') as $id => $gameName)
                        <option value="{{ $id }}">{{ $gameName }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <h3 class="mt-4 text-sm font-semibold">Columns</h3>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                @foreach (collect($fields)->groupBy(fn ($f) => $f->section, preserveKeys: true) as $section => $sectionFields)
                    <p class="mt-2 text-xs uppercase text-gray-500">{{ $section }}</p>
                    @foreach ($sectionFields as $key => $field)
                        <label class="mr-3 inline-flex items-center gap-1 text-sm">
                            <input type="checkbox" value="{{ $key }}" wire:model.live="def.columns"> {{ $field->label }}
                        </label>
                    @endforeach
                @endforeach
            </div>
            <ol class="text-sm">
                @foreach ($def['columns'] ?? [] as $i => $key)
                    <li class="flex items-center gap-2" wire:key="col-{{ $key }}">
                        <button type="button" wire:click="moveColumn({{ $i }}, -1)" aria-label="Move up">▲</button>
                        <button type="button" wire:click="moveColumn({{ $i }}, 1)" aria-label="Move down">▼</button>
                        <span>{{ $fields[$key]->label ?? $key }}</span>
                        @if (($fields[$key] ?? null) && $fields[$key]->type->totals() !== [])
                            <select wire:model.live="def.totals.{{ $key }}" class="{{ $input }}" aria-label="Total">
                                <option value="">no total</option>
                                @foreach ($fields[$key]->type->totals() as $fn)
                                    <option value="{{ $fn }}">{{ ['sum' => 'sum', 'avg' => 'average', 'min' => 'minimum', 'max' => 'maximum'][$fn] }}</option>
                                @endforeach
                            </select>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>

        <h3 class="mt-4 text-sm font-semibold">Filters (all must match)</h3>
        @foreach ($def['filters'] ?? [] as $i => $filter)
            @php $f = $fields[$filter['field'] ?? ''] ?? null; $op = $filter['op'] ?? ''; @endphp
            <div class="mt-2 flex flex-wrap items-center gap-2" wire:key="filter-{{ $i }}">
                <select wire:model.live="def.filters.{{ $i }}.field" class="{{ $input }}">
                    @foreach ($fields as $key => $field)
                        <option value="{{ $key }}">{{ $field->section }} › {{ $field->label }}</option>
                    @endforeach
                </select>
                @if ($f)
                    <select wire:model.live="def.filters.{{ $i }}.op" class="{{ $input }}">
                        @foreach ($f->type->operators() as $o)
                            <option value="{{ $o }}">{{ \App\Services\Reports\Builder\FieldType::OPERATORS[$o] }}</option>
                        @endforeach
                    </select>
                    @php $type = match ($f->type) { \App\Services\Reports\Builder\FieldType::Number => 'number', \App\Services\Reports\Builder\FieldType::Date => 'date', default => 'text' }; @endphp
                    @if ($f->type === \App\Services\Reports\Builder\FieldType::Enum)
                        <select multiple wire:model.live="def.filters.{{ $i }}.value" class="{{ $input }}">
                            @foreach ($f->options as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @elseif ($op === 'between')
                        <input type="{{ $type }}" wire:model.live.debounce.500ms="def.filters.{{ $i }}.value.0" class="{{ $input }}"> and
                        <input type="{{ $type }}" wire:model.live.debounce.500ms="def.filters.{{ $i }}.value.1" class="{{ $input }}">
                    @elseif (! in_array($op, $noValue, true))
                        <input type="{{ $type }}" wire:model.live.debounce.500ms="def.filters.{{ $i }}.value" class="{{ $input }}">
                    @endif
                @endif
                <x-filament::button size="xs" color="gray" wire:click="removeFilter({{ $i }})">Remove</x-filament::button>
            </div>
        @endforeach
        @if (count($def['filters'] ?? []) < \App\Services\Reports\Builder\ReportDefinition::MAX_FILTERS)
            <x-filament::button class="mt-2" size="sm" color="gray" wire:click="addFilter">Add filter</x-filament::button>
        @endif

        <h3 class="mt-4 text-sm font-semibold">Group by</h3>
        @foreach ($def['groups'] ?? [] as $i => $group)
            @php $g = $fields[$group['field'] ?? ''] ?? null; @endphp
            <div class="mt-2 flex flex-wrap items-center gap-2" wire:key="group-{{ $i }}">
                <select wire:model.live="def.groups.{{ $i }}.field" class="{{ $input }}">
                    @foreach ($fields as $key => $field)
                        @if ($field->type->groupable())
                            <option value="{{ $key }}">{{ $field->label }}</option>
                        @endif
                    @endforeach
                </select>
                @if ($g?->type === \App\Services\Reports\Builder\FieldType::Date)
                    <select wire:model.live="def.groups.{{ $i }}.bucket" class="{{ $input }}">
                        <option value="month">by month</option>
                        <option value="year">by year</option>
                    </select>
                @endif
                <x-filament::button size="xs" color="gray" wire:click="removeGroup({{ $i }})">Remove</x-filament::button>
            </div>
        @endforeach
        @if (count($def['groups'] ?? []) < \App\Services\Reports\Builder\ReportDefinition::MAX_GROUPS)
            <x-filament::button class="mt-2" size="sm" color="gray" wire:click="addGroup">Add group level</x-filament::button>
        @endif

        <h3 class="mt-4 text-sm font-semibold">Sort</h3>
        <div class="flex gap-2">
            <select wire:model.live="def.sort.field" class="{{ $input }}">
                @foreach ($fields as $key => $field)
                    <option value="{{ $key }}">{{ $field->label }}</option>
                @endforeach
            </select>
            <select wire:model.live="def.sort.dir" class="{{ $input }}">
                <option value="asc">ascending</option>
                <option value="desc">descending</option>
            </select>
        </div>
    </x-filament::section>

    {{-- Results --}}
    <x-filament::section heading="Results">
        @if ($problems !== [])
            <ul class="text-sm text-danger-600">
                @foreach ($problems as $problem)
                    <li>{{ $problem }}</li>
                @endforeach
            </ul>
        @elseif ($result)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            @foreach ($result->columns as $column)
                                <th class="py-1 pr-3">{{ $column->label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result->lines() as $line)
                            <tr @class(['border-t border-gray-200 dark:border-white/10', 'bg-gray-50 font-semibold dark:bg-white/5' => $line['kind'] !== 'row'])>
                                @foreach ($line['cells'] as $cell)
                                    <td class="py-1 pr-3">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($result->truncated())
                <p class="mt-2 text-xs text-gray-500">Showing the first {{ count($result->rows) }} of {{ $result->rowCount }} rows; the Excel export has all of them.</p>
            @endif
        @endif
    </x-filament::section>
</x-filament-panels::page>
```

Note: the `Export to Excel` button calls `export()`, added in Task 6. Until then clicking it errors; no Task 5 test clicks it.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `$PHP vendor/bin/pest tests/Feature/ReportBuilder`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
$PHP vendor/bin/pint
git add app/Filament/Pages/Reports.php app/Filament/Pages/ReportBuilder.php resources/views/filament/pages/reports.blade.php resources/views/filament/pages/report-builder.blade.php tests/Feature/ReportBuilder/BuilderPageTest.php
git commit -m "feat: Reports page and report builder page with live results, save, share, copy and delete

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Excel export with group subtotal rows

**Files:**
- Modify: `app/Filament/Pages/ReportBuilder.php` (add `export()`)
- Test: `tests/Feature/ReportBuilder/BuilderPageTest.php` (append)

**Interfaces:**
- Consumes: `Engine::run($def, null)`, `ReportResult::lines()`, `Field::$label` (Tasks 1–2); OpenSpout `Writer` / `Row::fromValues` as in M4's `GameReports::export()`; OpenSpout `Reader` and Livewire's `effects['download']['content']` (base64) in the test, as in M4's `GameReportsTest`.
- Produces: `ReportBuilder::export(): StreamedResponse` — file `{Str::slug(name)}.xlsx`; row 1 = column labels; then `lines()` cells in order (group subtotal lines, rows, grand total line); updates `last_run_at` of a saved report.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/ReportBuilder/BuilderPageTest.php` (and add `use OpenSpout\Reader\XLSX\Reader;` to the imports):

```php
it('exports all rows to Excel with group subtotal rows and a grand total', function () {
    actingInAdmin($this->owner);

    $page = livewire(ReportBuilder::class)
        ->set('name', 'People by association')
        ->set('def.columns', ['association', 'full_name', 'age'])
        ->set('def.groups', [['field' => 'association', 'bucket' => null]])
        ->set('def.totals', ['age' => 'sum'])
        ->call('export')
        ->assertFileDownloaded('people-by-association.xlsx',
            contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $path = sys_get_temp_dir().'/'.uniqid('builder').'.xlsx';
    file_put_contents($path, base64_decode($page->effects['download']['content']));
    $reader = new Reader;
    $reader->open($path);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    unlink($path);

    expect($rows)->toHaveCount(7)
        ->and($rows[0])->toBe(['Association', 'Name', 'Age (today)'])
        ->and([$rows[1][0], $rows[1][2]])->toEqual(['Bahrain Judo Federation (2)', 42])
        ->and($rows[2])->toEqual(['Bahrain Judo Federation', 'Ali Hasan', 20])
        ->and($rows[3])->toEqual(['Bahrain Judo Federation', 'Omar Saleh', 22])
        ->and([$rows[4][0], $rows[4][2]])->toEqual(['Bahrain Karate Federation (1)', 21])
        ->and($rows[5])->toEqual(['Bahrain Karate Federation', 'Karim Ahmed', 21])
        ->and([$rows[6][0], $rows[6][2]])->toEqual(['Total (3)', 63]);
});

it('refuses to export an invalid definition', function () {
    actingInAdmin($this->owner);

    livewire(ReportBuilder::class)->set('def.columns', [])->call('export')->assertHasErrors('columns')->assertNoFileDownloaded();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/ReportBuilder/BuilderPageTest.php`
Expected: the two new tests FAIL (`Method App\Filament\Pages\ReportBuilder::export does not exist`).

- [ ] **Step 3: Add `export()`**

In `app/Filament/Pages/ReportBuilder.php` add the imports:

```php
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
```

and the method (after `delete()`):

```php
    /** All rows (no screen cap), with the same group subtotal lines and grand total as the screen. */
    public function export(): StreamedResponse
    {
        $result = app(Engine::class)->run(ReportDefinition::fromArray($this->def), null);
        if ($this->reportId !== null) {
            DB::table('saved_reports')->where('id', $this->reportId)->update(['last_run_at' => now()]);
        }

        return response()->streamDownload(function () use ($result) {
            $tmp = tempnam(sys_get_temp_dir(), 'rb');
            $writer = new Writer;
            $writer->openToFile($tmp);
            $writer->addRow(Row::fromValues(array_map(fn (Field $f) => $f->label, $result->columns)));
            foreach ($result->lines() as $line) {
                $writer->addRow(Row::fromValues($line['cells']));
            }
            $writer->close();
            readfile($tmp);
            unlink($tmp);
        }, (Str::slug($this->name) ?: 'report').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
```

- [ ] **Step 4: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/ReportBuilder`
Expected: PASS.
Run: `$PHP vendor/bin/pest`
Expected: PASS (whole suite).

- [ ] **Step 5: Commit**

```bash
$PHP vendor/bin/pint
git add app/Filament/Pages/ReportBuilder.php tests/Feature/ReportBuilder/BuilderPageTest.php
git commit -m "feat: report builder Excel export with group subtotal rows

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Spec coverage (section 14)

| Spec item | Task |
|---|---|
| Subject = small PHP class with base query and fields; DB columns never exposed automatically | 1, 3 |
| One engine; definition is validated JSON, never SQL; values bound | 1, 2 |
| Reports page: My reports, Shared, New report; M4 reports unchanged | 5 |
| Builder `/admin/report-builder/{report?}`: subject, columns (tick and order), AND filters, group by ≤ 2 with counts, sort | 5 |
| Live results, 500 rows on screen, grand total row | 2, 5 |
| Top bar: name, Save, Save as copy, Share, Export to Excel (all rows, group subtotal rows), Delete | 5, 6 |
| Others change filters for that run only; only creator / Super Admin saves; else Save as copy | 5 |
| Six subjects and their main fields | 1, 3 |
| Game filter on every subject, required for game-based ones | 1, 3 |
| Field types, operators, grouping (month/year for dates), totals; Count of rows | 1, 2 |
| Access: `ViewReports`; Super Admin always; no association builder | 4, 5 |
| Never a field: medical/health, insurance policy numbers, vaccinations, passport and CPR numbers | 3 |
| Validation on save and on every run; unknown subject/field/operator or bad value refused | 1, 5, 6 |
| Limits 30 columns, 20 filters, 2 group levels | 1 |
| Editing and deleting: creator or Super Admin | 4, 5 |
| `saved_reports` columns incl. versioned definition, visibility, created_by, updated_by, last_run_at | 4 |
| Left out: charts, comparison, schedules, relative ranges | not built |
| Spec 15 hook: subjects can append dynamic fields without engine changes | 1 (`Subject::fields()`) |

## Decisions added beyond the spec

- Optional game filter on People and Clothing sizes means "people on any Long or Short List of that game" (Clothing sizes: also only that game's items).
- Entries, Phase 1 answers and Phase 2 numbers get a **submission status** field (not in the spec list), so drafts can be filtered out; no status filter is applied by default.
- Filterable / groupable / sortable / totals follow from the field type only (no per-field flags) — the spec table defines them per type.
- Date fields are dates (`DATE(...)`): "submitted at" / "approved at" lose their time of day.
- Group lines are written **before** their rows ("Judo (3)", level 2 prefixed "— "); the grand total is last. No bold styling in Excel.
- Saved reports are not audit-logged and have no group-count cap; add either if asked.
