# Milestone 4.2 – Game System Upload Files and Extra Fields Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** STA defines extra person fields that associations fill in (form, import, completeness, report builder), and per game builds an athlete and an official export layout that produces the exact `.xlsx`/`.csv` upload file a game's accreditation system expects, plus a queued ZIP of photos and documents with patterned file names.

**Architecture:**
- **Extra fields:** `extra_fields` (definition) + `person_field_values` (one text value per person and field). `ExtraField` owns the type rules (`normalise()`, `rules()`); `Person::syncExtraValues()` is the single write path (form, import), validates, skips sensitive fields the user may not see and writes one audit entry. `Person::missingFields()` appends active required fields, so the existing Long List submit rule blocks on them unchanged.
- **Report builder:** `App\Services\Reports\Builder\ExtraFields::fields()` turns active extra fields into M4.1 `Field`s (a `person_field_values` subquery on `people.id`); `People` and `Entries` override `Subject::fields()` to append them.
- **Exports:** `export_layouts` (one row per game and person type) holds an ordered JSON column list. `ExportSources` is the whitelist of sources, their kinds and formats; `GameFileExporter` picks the included people (M4's `GameReports::includedSubmissions()`), builds rows and writes xlsx (OpenSpout) or CSV (`fputcsv`). `BuildGameFilesZip` (queued) zips files from the private disk and notifies the requester through Filament database notifications. One page, `GameExportFiles` (`/admin/games/{record}/export-files`), holds the layout editor, copy, download and ZIP actions.

**Tech Stack:** PHP 8.5 (Herd `php85`), Laravel 12, Filament 4.16, Livewire 3, Pest 4, `openspout/openspout` (installed), spatie/laravel-activitylog, spatie/laravel-permission, PHP `ext-zip` (`ZipArchive`). No new package.

**Spec:** `docs/superpowers/specs/2026-11-04-sports-entry-system-design.md` — section 15 (binding), plus 8 (private disk, 5-minute `temporaryUrl`), 13 ("Codes and file names", "Required fields and completeness", "Sensitive data") and 14 (report builder subjects, "never a field").

## Global Constraints

- **Runs after Milestones 4 and 4.1.** Do not start before both are merged and green. Names this plan uses from them:
  - M4 (`docs/superpowers/plans/2026-11-10-m4-short-list-reports.md`): `App\Services\Reports\GameReports` and its private `submitted()` / `people()` (Task 6 extracts the "Short List if Phase 4 submitted, else submitted Long List" rule into public `includedSubmissions()`; no second copy of that rule), the OpenSpout download pattern of `App\Filament\Resources\Games\Pages\GameReports::export()`, M4's `GameResource::canAccess()` (which already ORs `ViewReports`), `ShortListChecks` and `Submission::for()` with the Phase 4 preload (tests create Phase 4 before Phase 3 is submitted, as `GameReportsTest` does).
  - M4.1 (`docs/superpowers/plans/2026-11-10-m4-1-report-builder.md`): `App\Services\Reports\Builder\{Field, FieldType, Subject, Registry, ReportDefinition, Engine, ReportResult}`, `Subjects\People` (key `people`) and `Subjects\Entries` (key `entries`, `people` joined), and the documented extension point **`Subject::fields()`**, overridden here.
  - Existing: `LongListChecks::submitErrors()` / `rows()` (call `Person::missingFields()`), `Person::canViewMedical()`, `Person::MEDICAL_GATED`, `Person::FILE_COLUMNS`, `SavesPersonSizes`, `PeopleTemplateExport::COLUMNS`, `RequiresStaPermission`, `actingInAdmin()` / `actingInPortal()` (tests/Pest.php).
- **PHP:** always `/c/Users/ababy/.config/herd/bin/php85/php.exe` (written `$PHP`), Pest as `$PHP vendor/bin/pest`, `$PHP vendor/bin/pint` before each commit.
- **Time zone:** `Asia/Bahrain`; no code converts time zones.
- **Permissions:** extra fields need `StaPermission::ManageMasterData`; layouts, upload files and ZIPs need the new `StaPermission::ExportGameFiles = 'export_game_files'`, label `Export files for game systems — includes passport numbers`. Super Admin passes through the existing `Gate::before`. Production gets the permission row from the Task 5 migration (`Permission::findOrCreate`); `PermissionSeeder` also creates it because it loops `StaPermission::cases()`.
- **Sensitive data (one rule):** `Person::canViewMedical($user)` decides both medical columns (`Person::MEDICAL_GATED`) and `extra_fields.sensitive`. Without it: sensitive extra fields are not in the person form, not filled into Livewire state, not in the report builder, and medical/sensitive export columns can be neither added nor exported (left out, with a note). Sensitive extra values are never written to the audit log (`sensitive_changed: true` marker only). Extra fields are never medical.
- **Report builder (spec 14):** passport/CPR numbers and medical data stay out of the builder; only extra fields are added. Only active extra fields are offered.
- **Value storage:** `person_field_values.value` is text, never blank (a blank input deletes the row). Text ≤ 255; number = numeric string; date = `Y-m-d`; choice = one of `options` exactly; yes/no = `'1'`/`'0'`.
- **Extra field key:** generated once on create from the label (`Str::slug($label, '_')`, max 50 characters, `field` if empty), suffixed `_2`, `_3`… when taken; never changes. Type cannot change once any value exists (model guard + disabled form field). Fields are never deleted (no delete action), only deactivated.
- **Export sources** are only the keys in `ExportSources::all()`: `person.{column}`, `extra.{key}`, `entry.sport|event|event_gender`, `game.age`, `association.code|name`, `text` (fixed value). Multi-valued entry sources join distinct values with `"; "`.
- **Files:** upload file name `{Str::slug(game name)}-{athletes|officials}.{xlsx|csv}`; CSV is UTF-8 without BOM, comma, `"` enclosure, `""` escaping, CRLF line ends. ZIPs live on `config('filesystems.private')` under `exports/{uuid}/` and are linked with `temporaryUrl(…, now()->addMinutes(5))`.
- **Audit:** every upload file and ZIP writes `activity()->performedOn($game)->event('exported')` with `layout`, row/file counts and left-out columns; the causer is the requester.
- **Commits:** end every commit message with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Never commit `.superpowers/` or `~$*`.

---

## File Structure

```
database/migrations/2026_11_13_000100_create_extra_fields_tables.php         # NEW: extra_fields, person_field_values
database/migrations/2026_11_13_000200_create_export_layouts_table.php        # NEW: export_layouts + export_game_files permission
database/migrations/2026_11_13_000300_create_notifications_table.php         # NEW: Filament database notifications
app/Enums/ExtraFieldType.php                                                 # NEW
app/Enums/StaPermission.php                                                  # + ExportGameFiles
app/Models/ExtraField.php, app/Models/PersonFieldValue.php, app/Models/ExportLayout.php   # NEW
app/Models/Person.php                                                        # fieldValues, extraValues, syncExtraValues, missingFields
app/Models/Game.php                                                          # exportLayouts()
database/factories/ExtraFieldFactory.php                                     # NEW
app/Filament/Resources/ExtraFields/{ExtraFieldResource.php, Pages/*, Schemas/ExtraFieldForm.php, Tables/ExtraFieldsTable.php}  # NEW
app/Filament/Shared/PersonForm.php, SavesPersonSizes.php, PersonTable.php    # "Additional information" section, save/fill, eager load
app/Services/Phases/LongListChecks.php                                       # eager-load fieldValues
app/Exports/PeopleTemplateExport.php, app/Services/PeopleImporter.php        # one column per active extra field
app/Services/Reports/Builder/ExtraFields.php                                 # NEW: extra fields as report Fields
app/Services/Reports/Builder/Subjects/{People,Entries}.php                   # fields() override; complete flag
app/Services/Reports/GameReports.php                                         # public includedSubmissions()
app/Services/GameFiles/ExportSources.php                                     # NEW: source whitelist, formats, validation
app/Services/GameFiles/GameFileExporter.php                                  # NEW: people, rows, xlsx/csv
app/Jobs/BuildGameFilesZip.php                                               # NEW
app/Filament/Resources/Games/Pages/GameExportFiles.php                       # NEW: editor, copy, download, ZIP
app/Filament/Resources/Games/{GameResource.php, Pages/GameDashboard.php, Tables/GamesTable.php}  # route, access, links
app/Providers/Filament/AdminPanelProvider.php                                # ->databaseNotifications()
tests/Feature/{ExtraFieldTest, PersonExtraFieldsTest, GameFilesLayoutTest, GameFilesExportTest, GameFilesZipTest}.php
tests/Feature/ReportBuilder/ExtraFieldColumnsTest.php
tests/Feature/{LongListChecksTest, PeopleImportTest}.php                     # appended tests
```

---

### Task 1: Extra fields — tables, models and the admin resource

**Files:**
- Create: `database/migrations/2026_11_13_000100_create_extra_fields_tables.php`, `app/Enums/ExtraFieldType.php`, `app/Models/ExtraField.php`, `app/Models/PersonFieldValue.php`, `database/factories/ExtraFieldFactory.php`, `app/Filament/Resources/ExtraFields/ExtraFieldResource.php`, `app/Filament/Resources/ExtraFields/Pages/{ListExtraFields,CreateExtraField,EditExtraField}.php`, `app/Filament/Resources/ExtraFields/Schemas/ExtraFieldForm.php`, `app/Filament/Resources/ExtraFields/Tables/ExtraFieldsTable.php`
- Test: `tests/Feature/ExtraFieldTest.php`

**Interfaces:**
- Consumes: `AppliesTo` (`includes()`, `label()`), `PersonType`, `Person::canViewMedical()`, `RequiresStaPermission`.
- Produces:
  - `enum App\Enums\ExtraFieldType: string` — `Text='text'`, `Number='number'`, `Date='date'`, `Choice='choice'`, `YesNo='yes_no'`; `label(): string`.
  - `App\Models\ExtraField` — attributes `label, key, applies_to (AppliesTo), type (ExtraFieldType), options (?list<string>), help_text, required, sensitive, is_active`; `values(): HasMany`; scopes `active()`, `forType(PersonType)`; `visibleTo(?User): bool`; `static shownTo(PersonType, ?User): Collection<ExtraField>` (active, for type, visible, by id); `static requiredFor(PersonType): Collection<ExtraField>` (active, required, for type; memoised with `once()`, flushed on save); `normalise(mixed): ?string`; `rules(): array`.
  - `App\Models\PersonFieldValue` — `person_id, extra_field_id, value`; `extraField(): BelongsTo`.
  - Resource route names `filament.admin.resources.extra-fields.{index,create,edit}`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ExtraFieldTest.php`:

```php
<?php

use App\Enums\AppliesTo;
use App\Enums\ExtraFieldType;
use App\Enums\PersonType;
use App\Enums\StaPermission;
use App\Filament\Resources\ExtraFields\ExtraFieldResource;
use App\Filament\Resources\ExtraFields\Pages\CreateExtraField;
use App\Filament\Resources\ExtraFields\Pages\EditExtraField;
use App\Models\ExtraField;
use App\Models\Person;
use App\Models\PersonFieldValue;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

it('generates a unique key from the label once', function () {
    $a = ExtraField::factory()->create(['label' => 'Shirt name']);
    $b = ExtraField::factory()->create(['label' => 'Shirt  Name!']);
    $c = ExtraField::factory()->create(['label' => '***']); // nothing sluggable
    $a->update(['label' => 'Name on shirt']);

    expect($a->fresh()->key)->toBe('shirt_name')->and($b->key)->toBe('shirt_name_2')->and($c->key)->toBe('field');
});

it('refuses a type change once values exist', function () {
    $f = ExtraField::factory()->create(['type' => ExtraFieldType::Text]);
    $f->update(['type' => ExtraFieldType::Number]); // no values yet: allowed
    PersonFieldValue::create(['person_id' => Person::factory()->create()->id, 'extra_field_id' => $f->id, 'value' => '5']);

    expect(fn () => $f->fresh()->update(['type' => ExtraFieldType::Date]))
        ->toThrow(ValidationException::class, 'The type cannot change once people have values for this field.')
        ->and($f->fresh()->type)->toBe(ExtraFieldType::Number);
});

it('normalises and validates values by type', function (ExtraFieldType $type, mixed $in, ?string $stored, bool $valid) {
    $f = ExtraField::factory()->make(['label' => 'X', 'type' => $type, 'options' => ['Red', 'Blue']]);
    $value = $f->normalise($in);

    expect($value)->toBe($stored)
        ->and(Validator::make(['v' => $value], ['v' => $f->rules()])->passes())->toBe($valid);
})->with([
    'text' => [ExtraFieldType::Text, '  Ali ', 'Ali', true],
    'text too long' => [ExtraFieldType::Text, str_repeat('x', 256), str_repeat('x', 256), false],
    'blank' => [ExtraFieldType::Text, '   ', null, true],
    'number' => [ExtraFieldType::Number, '12.5', '12.5', true],
    'not a number' => [ExtraFieldType::Number, 'abc', 'abc', false],
    'date' => [ExtraFieldType::Date, '2026-12-01', '2026-12-01', true],
    'Excel serial date' => [ExtraFieldType::Date, 46357, '2026-12-01', true],
    'date in another format' => [ExtraFieldType::Date, '01/12/2026', '01/12/2026', false],
    'choice' => [ExtraFieldType::Choice, 'Red', 'Red', true],
    'not an option' => [ExtraFieldType::Choice, 'Green', 'Green', false],
    'yes' => [ExtraFieldType::YesNo, 'Yes', '1', true],
    'false' => [ExtraFieldType::YesNo, false, '0', true],
    'maybe' => [ExtraFieldType::YesNo, 'maybe', 'maybe', false],
]);

it('lists required fields per type and sees a new one at once', function () {
    $athlete = ExtraField::factory()->create(['label' => 'Shirt name', 'required' => true, 'applies_to' => AppliesTo::Athlete]);
    ExtraField::factory()->create(['label' => 'Optional', 'required' => false]);
    ExtraField::factory()->create(['label' => 'Old', 'required' => true, 'is_active' => false]);

    expect(ExtraField::requiredFor(PersonType::Athlete)->pluck('id')->all())->toBe([$athlete->id])
        ->and(ExtraField::requiredFor(PersonType::Official))->toBeEmpty();

    $both = ExtraField::factory()->create(['label' => 'Diet', 'required' => true]);
    expect(ExtraField::requiredFor(PersonType::Official)->pluck('id')->all())->toBe([$both->id]);
});

it('lets only ManageMasterData users manage extra fields', function () {
    $sta = User::factory()->sta()->create();
    actingInAdmin($sta);
    $this->get(ExtraFieldResource::getUrl('index'))->assertForbidden();

    $sta->givePermissionTo(StaPermission::ManageMasterData->value);
    $this->get(ExtraFieldResource::getUrl('index'))->assertOk();
});

it('creates a choice field from the form, requiring options, and audits it', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(CreateExtraField::class)
        ->fillForm(['label' => 'Shirt size', 'type' => 'choice', 'applies_to' => 'both'])
        ->call('create')->assertHasFormErrors(['options' => 'required']);
    livewire(CreateExtraField::class)
        ->fillForm(['label' => 'Shirt size', 'type' => 'choice', 'applies_to' => 'both', 'options' => ['S', 'M'], 'sensitive' => true])
        ->call('create')->assertHasNoFormErrors();

    $f = ExtraField::sole();
    expect($f->key)->toBe('shirt_size')->and($f->options)->toBe(['S', 'M'])->and($f->sensitive)->toBeTrue()
        ->and(Activity::where('subject_type', ExtraField::class)->where('event', 'created')->exists())->toBeTrue();
});

it('disables the type field once values exist', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $f = ExtraField::factory()->create();
    livewire(EditExtraField::class, ['record' => $f->getRouteKey()])->assertFormFieldIsEnabled('type');

    PersonFieldValue::create(['person_id' => Person::factory()->create()->id, 'extra_field_id' => $f->id, 'value' => 'x']);
    livewire(EditExtraField::class, ['record' => $f->getRouteKey()])->assertFormFieldIsDisabled('type')
        ->fillForm(['label' => 'Renamed', 'is_active' => false])->call('save')->assertHasNoFormErrors();
    expect($f->fresh())->label->toBe('Renamed')->is_active->toBeFalse()->type->toBe(ExtraFieldType::Text);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/ExtraFieldTest.php`
Expected: FAIL with `Class "App\Enums\ExtraFieldType" not found`.

- [ ] **Step 3: Migration, enum and models**

`database/migrations/2026_11_13_000100_create_extra_fields_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_fields', function (Blueprint $table) {
            $table->id();
            $table->string('label', 100);
            $table->string('key', 60)->unique();
            $table->string('applies_to', 10);
            $table->string('type', 10);
            $table->json('options')->nullable();
            $table->string('help_text', 255)->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('sensitive')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('person_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('extra_field_id')->constrained()->restrictOnDelete();
            $table->text('value');
            $table->timestamps();
            $table->unique(['person_id', 'extra_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_field_values');
        Schema::dropIfExists('extra_fields');
    }
};
```

`app/Enums/ExtraFieldType.php`:

```php
<?php

namespace App\Enums;

enum ExtraFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Choice = 'choice';
    case YesNo = 'yes_no';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text', self::Number => 'Number', self::Date => 'Date',
            self::Choice => 'Choice (list of options)', self::YesNo => 'Yes/No',
        };
    }
}
```

`app/Models/ExtraField.php`:

```php
<?php

namespace App\Models;

use App\Enums\AppliesTo;
use App\Enums\ExtraFieldType;
use App\Enums\PersonType;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Once;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** An STA-defined person field (spec 15.1). Never deleted; deactivating hides it and keeps its values. */
class ExtraField extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['label', 'applies_to', 'type', 'options', 'help_text', 'required', 'sensitive', 'is_active'];

    protected function casts(): array
    {
        return ['applies_to' => AppliesTo::class, 'type' => ExtraFieldType::class, 'options' => 'array',
            'required' => 'boolean', 'sensitive' => 'boolean', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // The key is used by the Excel import and export layouts, so it is generated once and never changes.
        static::creating(function (ExtraField $field) {
            $base = Str::limit(Str::slug($field->label, '_'), 50, '') ?: 'field';
            $key = $base;
            for ($n = 2; static::where('key', $key)->exists(); $n++) {
                $key = "{$base}_{$n}";
            }
            $field->key = $key;
        });

        static::saving(function (ExtraField $field) {
            if ($field->type !== ExtraFieldType::Choice) {
                $field->options = null;
            }
        });

        static::updating(function (ExtraField $field) {
            if ($field->isDirty('type') && $field->values()->exists()) {
                throw ValidationException::withMessages(['type' => 'The type cannot change once people have values for this field.']);
            }
        });

        // requiredFor() is memoised per request; any definition change must be seen at once.
        static::saved(fn () => Once::flush());
    }

    public function values(): HasMany
    {
        return $this->hasMany(PersonFieldValue::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForType(Builder $query, PersonType $type): void
    {
        $query->whereIn('applies_to', [AppliesTo::Both->value, $type->value]);
    }

    /** Sensitive fields follow the medical rule (spec 15.1, 13). */
    public function visibleTo(?User $user): bool
    {
        return ! $this->sensitive || Person::canViewMedical($user);
    }

    /** @return Collection<int, ExtraField> active fields of the type that the user may see, in form order */
    public static function shownTo(PersonType $type, ?User $user): Collection
    {
        return static::active()->forType($type)->orderBy('id')->get()->filter(fn (ExtraField $f) => $f->visibleTo($user))->values();
    }

    /** @return Collection<int, ExtraField> active "required to be complete" fields of the type (memoised; flushed on save) */
    public static function requiredFor(PersonType $type): Collection
    {
        return once(fn () => static::active()->forType($type)->where('required', true)->orderBy('id')->get());
    }

    /** Raw input (form, Excel cell) to the stored text; null = blank. */
    public function normalise(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return match ($this->type) {
            ExtraFieldType::YesNo => match (strtolower($value)) {
                'yes', 'y', '1', 'true' => '1',
                'no', 'n', '0', 'false' => '0',
                default => $value,
            },
            // Excel stores dates as day serials from 1899-12-30.
            ExtraFieldType::Date => is_numeric($value)
                ? (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) $value.' days')->format('Y-m-d') : $value,
            default => $value,
        };
    }

    /** Laravel rules for a normalised value. */
    public function rules(): array
    {
        return ['nullable', ...match ($this->type) {
            ExtraFieldType::Text => ['string', 'max:255'],
            ExtraFieldType::Number => ['numeric'],
            ExtraFieldType::Date => ['date_format:Y-m-d'],
            ExtraFieldType::Choice => [Rule::in($this->options ?? [])],
            ExtraFieldType::YesNo => [Rule::in(['0', '1'])],
        }];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
```

`app/Models/PersonFieldValue.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One extra-field value of a person (spec 15.1). Written only through Person::syncExtraValues(), which audits. */
class PersonFieldValue extends Model
{
    protected $fillable = ['person_id', 'extra_field_id', 'value'];

    public function extraField(): BelongsTo
    {
        return $this->belongsTo(ExtraField::class);
    }
}
```

`database/factories/ExtraFieldFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\AppliesTo;
use App\Enums\ExtraFieldType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExtraFieldFactory extends Factory
{
    public function definition(): array
    {
        return ['label' => fake()->unique()->words(2, true), 'applies_to' => AppliesTo::Both, 'type' => ExtraFieldType::Text,
            'required' => false, 'sensitive' => false, 'is_active' => true];
    }
}
```

- [ ] **Step 4: Admin resource (modelled on ClothingItems)**

`app/Filament/Resources/ExtraFields/ExtraFieldResource.php`:

```php
<?php

namespace App\Filament\Resources\ExtraFields;

use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;
use App\Filament\Resources\ExtraFields\Pages\CreateExtraField;
use App\Filament\Resources\ExtraFields\Pages\EditExtraField;
use App\Filament\Resources\ExtraFields\Pages\ListExtraFields;
use App\Filament\Resources\ExtraFields\Schemas\ExtraFieldForm;
use App\Filament\Resources\ExtraFields\Tables\ExtraFieldsTable;
use App\Models\ExtraField;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExtraFieldResource extends Resource
{
    use RequiresStaPermission;

    protected static ?string $model = ExtraField::class;

    protected static ?string $recordTitleAttribute = 'label';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ManageMasterData;
    }

    public static function form(Schema $schema): Schema
    {
        return ExtraFieldForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExtraFieldsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExtraFields::route('/'),
            'create' => CreateExtraField::route('/create'),
            'edit' => EditExtraField::route('/{record}/edit'),
        ];
    }
}
```

`app/Filament/Resources/ExtraFields/Pages/ListExtraFields.php`:

```php
<?php

namespace App\Filament\Resources\ExtraFields\Pages;

use App\Filament\Resources\ExtraFields\ExtraFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExtraFields extends ListRecords
{
    protected static string $resource = ExtraFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

`app/Filament/Resources/ExtraFields/Pages/CreateExtraField.php`:

```php
<?php

namespace App\Filament\Resources\ExtraFields\Pages;

use App\Filament\Resources\ExtraFields\ExtraFieldResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExtraField extends CreateRecord
{
    protected static string $resource = ExtraFieldResource::class;
}
```

`app/Filament/Resources/ExtraFields/Pages/EditExtraField.php`:

```php
<?php

namespace App\Filament\Resources\ExtraFields\Pages;

use App\Filament\Resources\ExtraFields\ExtraFieldResource;
use Filament\Resources\Pages\EditRecord;

class EditExtraField extends EditRecord
{
    protected static string $resource = ExtraFieldResource::class;

    // No header actions: extra fields are never deleted, only deactivated (spec 15.1).
}
```

`app/Filament/Resources/ExtraFields/Schemas/ExtraFieldForm.php`:

```php
<?php

namespace App\Filament\Resources\ExtraFields\Schemas;

use App\Enums\AppliesTo;
use App\Enums\ExtraFieldType;
use App\Models\ExtraField;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ExtraFieldForm
{
    public static function configure(Schema $schema): Schema
    {
        $isChoice = fn (Get $get) => in_array($get('type'), [ExtraFieldType::Choice->value, ExtraFieldType::Choice], true);
        $hasValues = fn (?ExtraField $record) => (bool) $record?->values()->exists();

        return $schema->components([
            TextInput::make('label')->required()->maxLength(100),
            TextInput::make('key')->disabled()->dehydrated(false)->visibleOn('edit')
                ->helperText('Generated from the label. Used as the Excel import column and in export layouts.'),
            Select::make('applies_to')->options(collect(AppliesTo::cases())->mapWithKeys(fn ($a) => [$a->value => $a->label()]))
                ->required()->default(AppliesTo::Both->value),
            Select::make('type')->options(collect(ExtraFieldType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]))
                ->required()->live()->default(ExtraFieldType::Text->value)->disabled($hasValues)
                ->helperText(fn (?ExtraField $record) => $hasValues($record) ? 'People already have values, so the type cannot change.' : null),
            TagsInput::make('options')->reorderable()->visible($isChoice)->required($isChoice)
                ->helperText('Type an option and press Enter. Order is kept.'),
            TextInput::make('help_text')->maxLength(255),
            Toggle::make('required')->label('Required to be complete'),
            Toggle::make('sensitive')->helperText('Hidden from STA users without "View medical & health data".'),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }
}
```

`app/Filament/Resources/ExtraFields/Tables/ExtraFieldsTable.php`:

```php
<?php

namespace App\Filament\Resources\ExtraFields\Tables;

use App\Enums\AppliesTo;
use App\Enums\ExtraFieldType;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExtraFieldsTable
{
    public static function configure(Table $table): Table
    {
        // No delete actions: extra fields are only deactivated.
        return $table
            ->columns([
                TextColumn::make('label')->searchable(),
                TextColumn::make('key'),
                TextColumn::make('type')->formatStateUsing(fn (ExtraFieldType $state) => $state->label()),
                TextColumn::make('applies_to')->formatStateUsing(fn (AppliesTo $state) => $state->label()),
                IconColumn::make('required')->boolean()->label('Required to be complete'),
                IconColumn::make('sensitive')->boolean(),
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->recordActions([EditAction::make()]);
    }
}
```

- [ ] **Step 5: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/ExtraFieldTest.php`
Expected: PASS (all tests, 13 dataset cases).

- [ ] **Step 6: Commit**

```bash
$PHP vendor/bin/pint
git add database/migrations/2026_11_13_000100_create_extra_fields_tables.php app/Enums/ExtraFieldType.php app/Models/ExtraField.php app/Models/PersonFieldValue.php database/factories/ExtraFieldFactory.php app/Filament/Resources/ExtraFields tests/Feature/ExtraFieldTest.php
git commit -m "feat: STA-defined extra fields (key, type rules, choice options, sensitive, active) with admin resource

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Extra values on the person — form, validation, audit, completeness

**Files:**
- Modify: `app/Models/Person.php`, `app/Filament/Shared/PersonForm.php`, `app/Filament/Shared/SavesPersonSizes.php`, `app/Filament/Shared/PersonTable.php`, `app/Services/Phases/LongListChecks.php` (lines 120 and 210: eager loads)
- Test: `tests/Feature/PersonExtraFieldsTest.php`; append to `tests/Feature/LongListChecksTest.php`

**Interfaces:**
- Consumes: `ExtraField::{shownTo, requiredFor, normalise, rules, visibleTo}` (Task 1), `PersonFieldValue`.
- Produces on `Person`:
  - `fieldValues(): HasMany<PersonFieldValue>`
  - `extraValues(?User $user = null): array<int, string>` — `extra_field_id => value`; with a user, sensitive fields that user may not see are left out; `null` = all.
  - `syncExtraValues(array $values, ?User $user = null): void` — `extra_field_id => raw`; blank deletes; skips unknown fields, fields not for the person's type and (with a user) fields the user may not see; throws `ValidationException` keyed `extra.{id}`; one activity `extra fields updated` with `extra_fields: {key: {old, new}}` and `sensitive_changed: true` (values of sensitive fields never logged).
  - `missingFields()` now also lists labels of `ExtraField::requiredFor($type)` without a value.
- Form state: `extra.{extra_field_id}` (like `sizes.{id}`).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/PersonExtraFieldsTest.php`:

```php
<?php

use App\Enums\AppliesTo;
use App\Enums\ExtraFieldType;
use App\Enums\StaPermission;
use App\Filament\Portal\Resources\People\Pages\CreatePerson;
use App\Filament\Resources\People\Pages\EditPerson as AdminEditPerson;
use App\Models\Association;
use App\Models\ExtraField;
use App\Models\Person;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->assoc = Association::factory()->create(['code' => 'BWF']);
    $this->shirt = ExtraField::factory()->create(['label' => 'Shirt name', 'applies_to' => AppliesTo::Athlete, 'required' => true]);
    $this->weight = ExtraField::factory()->create(['label' => 'Weight class', 'type' => ExtraFieldType::Number]);
    $this->blood = ExtraField::factory()->create(['label' => 'Donor type', 'type' => ExtraFieldType::Choice, 'options' => ['A', 'B'], 'sensitive' => true]);
    $this->onDuty = ExtraField::factory()->create(['label' => 'On duty', 'type' => ExtraFieldType::YesNo, 'applies_to' => AppliesTo::Official]);
    $this->old = ExtraField::factory()->create(['label' => 'Old field', 'required' => true, 'is_active' => false]);
});

function extraAthlete(array $o = []): array
{
    return array_merge(['type' => 'athlete', 'first_name' => 'Noor', 'last_name' => 'Alsayed', 'dob' => '2006-05-27', 'gender' => 'female',
        'nationality' => 'Bahrain', 'passport_no' => 'BH7777771', 'passport_expiry' => now()->addYears(3)->toDateString()], $o);
}

it('shows the active fields of the person type in the portal and saves their values', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());

    livewire(CreatePerson::class)
        ->assertFormFieldExists("extra.{$this->shirt->id}")->assertFormFieldExists("extra.{$this->blood->id}")
        ->assertFormFieldDoesNotExist("extra.{$this->onDuty->id}")->assertFormFieldDoesNotExist("extra.{$this->old->id}")
        ->fillForm(extraAthlete(['extra' => [$this->shirt->id => 'NOOR', $this->weight->id => '57', $this->blood->id => 'A']]))
        ->call('create')->assertHasNoFormErrors();

    expect(Person::sole()->extraValues())->toBe([$this->shirt->id => 'NOOR', $this->weight->id => '57', $this->blood->id => 'A']);
});

it('validates values by type in the form', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());

    livewire(CreatePerson::class)->fillForm(extraAthlete(['extra' => [$this->weight->id => 'heavy']]))
        ->call('create')->assertHasFormErrors(["extra.{$this->weight->id}" => 'numeric']);
    expect(Person::count())->toBe(0);
});

it('validates, skips hidden fields and audits without sensitive values when syncing', function () {
    $p = Person::factory()->create(['association_id' => $this->assoc->id]);
    $p->syncExtraValues([$this->shirt->id => ' ALI ', $this->blood->id => 'B', $this->onDuty->id => 'yes']); // onDuty: officials only

    expect($p->extraValues())->toBe([$this->shirt->id => 'ALI', $this->blood->id => 'B']);
    $log = Activity::where('subject_type', Person::class)->where('description', 'extra fields updated')->sole();
    expect($log->properties['extra_fields'])->toBe(['shirt_name' => ['old' => null, 'new' => 'ALI']])
        ->and($log->properties['sensitive_changed'])->toBeTrue()
        ->and(json_encode($log->properties))->not->toContain('"B"');

    expect(fn () => $p->syncExtraValues([$this->weight->id => 'heavy']))->toThrow(ValidationException::class, 'The Weight class field must be a number.');

    $sta = User::factory()->sta()->create();
    $p->syncExtraValues([$this->blood->id => 'A', $this->shirt->id => ''], $sta); // STA without view_medical
    expect($p->extraValues())->toBe([$this->blood->id => 'B']);
});

it('hides sensitive fields from STA without view_medical and keeps their values on save', function () {
    $p = Person::factory()->create(['association_id' => $this->assoc->id]);
    $p->syncExtraValues([$this->blood->id => 'B', $this->shirt->id => 'ALI']);
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ManagePeople->value);
    actingInAdmin($sta);

    $page = livewire(AdminEditPerson::class, ['record' => $p->getRouteKey()])
        ->assertFormFieldDoesNotExist("extra.{$this->blood->id}")->assertFormFieldExists("extra.{$this->shirt->id}");
    expect($page->get("data.extra.{$this->blood->id}"))->toBeNull();
    $page->fillForm(['extra' => [$this->shirt->id => 'ALI H']])->call('save')->assertHasNoFormErrors();
    expect($p->fresh()->extraValues())->toBe([$this->blood->id => 'B', $this->shirt->id => 'ALI H']);

    $sta->givePermissionTo(StaPermission::ViewMedical->value);
    actingInAdmin($sta->fresh());
    livewire(AdminEditPerson::class, ['record' => $p->getRouteKey()])->assertFormFieldExists("extra.{$this->blood->id}")
        ->assertSchemaStateSet(["extra.{$this->blood->id}" => 'B']);
});

it('counts active required fields of the person type for completeness', function () {
    $athlete = Person::factory()->complete()->create(['association_id' => $this->assoc->id]);
    $official = Person::factory()->official()->complete()->create(['association_id' => $this->assoc->id]);

    expect($athlete->missingFields())->toBe(['Shirt name'])->and($official->isComplete())->toBeTrue();

    $athlete->syncExtraValues([$this->shirt->id => 'ALI']);
    expect($athlete->isComplete())->toBeTrue();
});
```

Append to `tests/Feature/LongListChecksTest.php` (add imports `use App\Enums\AppliesTo;` and `use App\Models\ExtraField;`):

```php
it('blocks submit while a required extra field is empty', function () {
    $field = ExtraField::factory()->create(['label' => 'Shirt name', 'required' => true, 'applies_to' => AppliesTo::Athlete]);
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $this->man->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    $admin = User::factory()->assocAdmin($this->assoc)->create();

    expect(fn () => app(SubmissionWorkflow::class)->submit($this->sub, $admin))
        ->toThrow(ValidationException::class, 'Ali Hasan is incomplete: Shirt name.');

    $this->man->syncExtraValues([$field->id => 'HASAN']);
    expect(app(SubmissionWorkflow::class)->submit($this->sub->fresh(), $admin)->status)->toBe(SubmissionStatus::Submitted);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `$PHP vendor/bin/pest tests/Feature/PersonExtraFieldsTest.php tests/Feature/LongListChecksTest.php`
Expected: FAIL (`Call to undefined method App\Models\Person::extraValues()`, form field `extra.*` does not exist, the new Long List test submits successfully).

- [ ] **Step 3: Person model**

In `app/Models/Person.php` add imports `use Illuminate\Support\Facades\Validator;` and the methods below; replace `missingFields()`:

```php
    public function fieldValues(): HasMany
    {
        return $this->hasMany(PersonFieldValue::class);
    }

    /** @return array<int, string> extra_field_id => value; with a user, sensitive fields the user may not see are left out */
    public function extraValues(?User $user = null): array
    {
        $hidden = $user && ! self::canViewMedical($user) ? ExtraField::where('sensitive', true)->pluck('id')->all() : [];

        return $this->fieldValues->whereNotIn('extra_field_id', $hidden)->pluck('value', 'extra_field_id')->all();
    }

    /**
     * The only write path for extra values (form, import). Blank deletes. Unknown fields, fields of the other type and,
     * with a user, sensitive fields the user may not see are skipped. One audit entry; sensitive values are never logged.
     *
     * @param  array<int|string, mixed>  $values  extra_field_id => raw value
     */
    public function syncExtraValues(array $values, ?User $user = null): void
    {
        $fields = ExtraField::whereKey(array_keys($values))->get()->keyBy('id');
        $old = $this->fieldValues()->pluck('value', 'extra_field_id');
        $changes = [];
        $sensitiveChanged = false;

        foreach ($values as $id => $raw) {
            $field = $fields->get((int) $id);
            if (! $field || ! $field->applies_to->includes($this->type) || ($user && ! $field->visibleTo($user))) {
                continue;
            }
            $value = $field->normalise($raw);
            $validator = Validator::make(['value' => $value], ['value' => $field->rules()], [], ['value' => $field->label]);
            if ($validator->fails()) {
                throw ValidationException::withMessages(["extra.{$field->id}" => $validator->errors()->first('value')]);
            }
            $before = $old[$field->id] ?? null;
            if ($value === $before) {
                continue;
            }
            $value === null
                ? $this->fieldValues()->where('extra_field_id', $field->id)->delete()
                : $this->fieldValues()->updateOrCreate(['extra_field_id' => $field->id], ['value' => $value]);
            if ($field->sensitive) {
                $sensitiveChanged = true;
            } else {
                $changes[$field->key] = ['old' => $before, 'new' => $value];
            }
        }
        $this->unsetRelation('fieldValues');

        if ($changes || $sensitiveChanged) {
            activity()->performedOn($this)->event('updated')
                ->withProperties(array_filter(['extra_fields' => $changes, 'sensitive_changed' => $sensitiveChanged ?: null]))
                ->log('extra fields updated');
        }
    }

    /** @return array<int, string> */
    public function missingFields(): array
    {
        $values = $this->fieldValues->pluck('value', 'extra_field_id');

        return collect(self::REQUIRED_FOR_COMPLETE[$this->type->value])
            ->filter(fn (string $label, string $column) => blank($this->{$column}))->values()
            ->merge(ExtraField::requiredFor($this->type)->reject(fn (ExtraField $f) => $values->has($f->id))->pluck('label'))
            ->all();
    }
```

- [ ] **Step 4: Form section, fill and save**

In `app/Filament/Shared/PersonForm.php` add imports `use App\Enums\ExtraFieldType;`, `use App\Models\ExtraField;`, `use Filament\Forms\Components\Field;`. Insert this section right after `Section::make('Clothing')…`:

```php
            Section::make('Additional information')->hidden(fn (Get $get) => self::extraFields($get)->isEmpty())->schema([
                Grid::make(2)->schema(fn (Get $get) => self::extraFields($get)->map(fn (ExtraField $f) => self::extraInput($f))->all()),
            ]),
```

and these private methods:

```php
    /** Active extra fields of the chosen type that the user may see (sensitive ones need view_medical in /admin). */
    private static function extraFields(Get $get): \Illuminate\Support\Collection
    {
        return ExtraField::shownTo(PersonType::tryFrom((string) $get('type')) ?? PersonType::Athlete, auth()->user());
    }

    private static function extraInput(ExtraField $f): Field
    {
        $name = "extra.{$f->id}";

        $input = match ($f->type) {
            ExtraFieldType::Text => TextInput::make($name)->maxLength(255),
            ExtraFieldType::Number => TextInput::make($name)->numeric(),
            ExtraFieldType::Date => DatePicker::make($name),
            ExtraFieldType::Choice => Select::make($name)->options(array_combine($f->options ?? [], $f->options ?? [])),
            ExtraFieldType::YesNo => Select::make($name)->options(['1' => 'Yes', '0' => 'No']),
        };

        return $input->label($f->label)->helperText($f->help_text);
    }
```

Replace `app/Filament/Shared/SavesPersonSizes.php` with (sizes unchanged; `extra.*` handled the same way):

```php
<?php

namespace App\Filament\Shared;

use App\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Used by Create/Edit person pages: `sizes.*` and `extra.*` are not columns; they are synced after save. */
trait SavesPersonSizes
{
    protected array $pendingSizes = [];

    protected array $pendingExtra = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['sizes'] = $this->record?->sizesMap() ?? [];
        // Sensitive extra values the user may not see never reach the browser.
        $data['extra'] = $this->record?->extraValues(auth()->user()) ?? [];
        if (! Person::canViewMedical(auth()->user())) {
            // Form data is sent to the browser even for hidden fields, so medical values must never be filled.
            $data = array_diff_key($data, array_flip(Person::MEDICAL_GATED));
        }

        return $data;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->pullRelated($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->pullRelated($data);
    }

    /** Row, sizes and extra values commit together: an invalid value rolls back the person. */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $record = parent::handleRecordCreation($data);
            $record->syncSizes($this->pendingSizes);
            $record->syncExtraValues($this->pendingExtra, auth()->user());

            return $record;
        });
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            $record = parent::handleRecordUpdate($record, $data);
            $record->syncSizes($this->pendingSizes);
            $record->syncExtraValues($this->pendingExtra, auth()->user());

            return $record;
        });
    }

    /** Uploads are renamed after save; resync the form so it doesn't keep the stale temp path (Create pages never call this). */
    protected function afterSave(): void
    {
        $this->refreshFormData(array_keys(Person::FILE_COLUMNS));
    }

    private function pullRelated(array $data): array
    {
        $this->pendingSizes = $data['sizes'] ?? [];
        $this->pendingExtra = $data['extra'] ?? [];
        unset($data['sizes'], $data['extra']);

        return $data;
    }
}
```

- [ ] **Step 5: Eager-load values where completeness is read per row**

`app/Filament/Shared/PersonTable.php` — add `use Illuminate\Database\Eloquent\Builder;` and, as the first call on `$table`:

```php
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('fieldValues')) // missingFields() per row
            ->columns([
```

`app/Services/Phases/LongListChecks.php`:
- in `submitErrors()`: `Person::withoutGlobalScopes()->with('fieldValues')->where('association_id', $s->association_id)`
- in `rows()`: `Person::withoutGlobalScopes()->with(['sizes', 'fieldValues'])->where('association_id', $s->association_id)`

- [ ] **Step 6: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/PersonExtraFieldsTest.php tests/Feature/LongListChecksTest.php tests/Feature/PersonFormV12Test.php tests/Feature/PersonCompletenessTest.php tests/Feature/PeopleScreensTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
$PHP vendor/bin/pint
git add app/Models/Person.php app/Filament/Shared/PersonForm.php app/Filament/Shared/SavesPersonSizes.php app/Filament/Shared/PersonTable.php app/Services/Phases/LongListChecks.php tests/Feature/PersonExtraFieldsTest.php tests/Feature/LongListChecksTest.php
git commit -m "feat: extra field values on the person form (portal and admin), audited, sensitive gating, counted for completeness

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: People import and template — one column per active extra field

**Files:**
- Modify: `app/Exports/PeopleTemplateExport.php`, `app/Services/PeopleImporter.php`
- Test: append to `tests/Feature/PeopleImportTest.php`

**Interfaces:**
- Consumes: `ExtraField::{active, normalise, rules, key}`, `Person::syncExtraValues()` (Task 2); the test file's existing helpers `xlsxWith(array $rows): string` and `row(array $overrides = []): array`.
- Produces: `PeopleTemplateExport::columns(): list<string>` = `[...COLUMNS, ...active extra field keys by id]` (headings of the template and of `writeTo()`).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/PeopleImportTest.php` (add imports `use App\Enums\ExtraFieldType;` and `use App\Models\ExtraField;`):

```php
it('imports one column per active extra field and validates it', function () {
    $shirt = ExtraField::factory()->create(['label' => 'Shirt name']);
    $vegan = ExtraField::factory()->create(['label' => 'Vegan', 'type' => ExtraFieldType::YesNo]);
    ExtraField::factory()->create(['label' => 'Old', 'is_active' => false]);

    expect(PeopleTemplateExport::columns())->toBe([...PeopleTemplateExport::COLUMNS, 'shirt_name', 'vegan']);

    $bad = app(PeopleImporter::class)->import(xlsxWith([
        row(['shirt_name' => 'SARA', 'vegan' => 'yes']),
        row(['passport_no' => 'BH2222222', 'cpr_no' => '070402222', 'vegan' => 'maybe']),
    ]), Association::factory()->create());
    expect(array_keys($bad->errors))->toBe([3])->and($bad->errors[3][0])->toBe('The selected vegan is invalid.')
        ->and(Person::count())->toBe(0);

    $ok = app(PeopleImporter::class)->import(xlsxWith([row(['shirt_name' => 'SARA', 'vegan' => 'yes'])]), Association::factory()->create());
    expect($ok->ok())->toBeTrue()->and(Person::sole()->extraValues())->toBe([$shirt->id => 'SARA', $vegan->id => '1']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/PeopleImportTest.php`
Expected: FAIL with `Call to undefined method App\Exports\PeopleTemplateExport::columns()`.

- [ ] **Step 3: Template**

In `app/Exports/PeopleTemplateExport.php` add `use App\Models\ExtraField;`, add the method, and use it in `data()` and `writeTo()`:

```php
    /** @return list<string> the fixed columns plus one per active extra field (spec 15.1) */
    public static function columns(): array
    {
        return [...self::COLUMNS, ...ExtraField::active()->orderBy('id')->pluck('key')->all()];
    }
```

- in `data()`: `$columns = self::columns();` before the `return`, and `return array_map(fn (array $r) => array_map(fn ($c) => $r[$c] ?? '', $columns), $rows);`
- in `writeTo()`: `$writer->addRow(Row::fromValues(self::columns()));`

- [ ] **Step 4: Importer**

In `app/Services/PeopleImporter.php` add `use App\Models\ExtraField;` and `use Illuminate\Support\Collection;`, then:

```php
    /** @var ?Collection<int, ExtraField> */
    private ?Collection $extraFields = null;

    /** @return Collection<int, ExtraField> */
    private function extraFields(): Collection
    {
        return $this->extraFields ??= ExtraField::active()->orderBy('id')->get();
    }
```

At the end of `normalise()`, before `return $row;`:

```php
        $row['extra'] = [];
        foreach ($this->extraFields() as $field) {
            $row['extra'][$field->id] = $field->normalise($raw[$field->key] ?? null);
        }
```

In `validate()`, build extra rules and pass attribute names so messages name the column:

```php
    /** @return array<int, string> */
    private function validate(array $row): array
    {
        $extraRules = [];
        $names = [];
        foreach ($this->extraFields() as $field) {
            $extraRules["extra.{$field->id}"] = $field->rules();
            $names["extra.{$field->id}"] = $field->key;
        }

        return Validator::make($row, [
            // ... the existing rules, unchanged ...
            ...$extraRules,
        ], [], $names)->errors()->all();
    }
```

In `import()`'s transaction:

```php
            foreach ($clean as $row) {
                $person = Person::create([...$this->toAttributes($row), 'association_id' => $association->id]);
                $person->syncExtraValues($row['extra']);
            }
```

- [ ] **Step 5: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/PeopleImportTest.php`
Expected: PASS (existing tests unchanged: with no extra fields `columns()` equals `COLUMNS`).

- [ ] **Step 6: Commit**

```bash
$PHP vendor/bin/pint
git add app/Exports/PeopleTemplateExport.php app/Services/PeopleImporter.php tests/Feature/PeopleImportTest.php
git commit -m "feat: people import and template get one column per active extra field

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Report builder — extra fields on the People and Entries subjects

**Files:**
- Create: `app/Services/Reports/Builder/ExtraFields.php`
- Modify: `app/Services/Reports/Builder/Subjects/People.php`, `app/Services/Reports/Builder/Subjects/Entries.php`
- Test: `tests/Feature/ReportBuilder/ExtraFieldColumnsTest.php`

**Interfaces:**
- Consumes: M4.1 `Field::{text, number, date, enum, boolean}`, `Field::display()`, `Subject::fields()`, `Registry::get()`, `ReportDefinition::{fresh, fromArray}`, `Engine::run()`, `ReportResult::{columns, rows}`; `ExtraField::{active, visibleTo, requiredFor}`.
- Produces: `App\Services\Reports\Builder\ExtraFields::fields(): list<Field>` (keys `extra_{key}`, section `Additional information`, only active fields visible to `auth()->user()`), `ExtraFields::completeSql(PersonType): string`. People `complete` now also requires active required extra fields (same rule as `Person::missingFields()`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/ReportBuilder/ExtraFieldColumnsTest.php`:

```php
<?php

use App\Enums\ExtraFieldType;
use App\Enums\StaPermission;
use App\Models\Association;
use App\Models\ExtraField;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\Reports\Builder\Engine;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;
use App\Services\Reports\Builder\ReportResult;

beforeEach(function () {
    $assoc = Association::factory()->create(['code' => 'BJF']);
    $this->shirt = ExtraField::factory()->create(['label' => 'Shirt name', 'required' => true]);
    $this->weight = ExtraField::factory()->create(['label' => 'Weight class', 'type' => ExtraFieldType::Number]);
    $this->vegan = ExtraField::factory()->create(['label' => 'Vegan', 'type' => ExtraFieldType::YesNo]);
    $this->donor = ExtraField::factory()->create(['label' => 'Donor type', 'type' => ExtraFieldType::Choice, 'options' => ['A', 'B'], 'sensitive' => true]);
    $this->ali = Person::factory()->complete()->create(['association_id' => $assoc->id]);   // BJF-AT-0001
    $this->omar = Person::factory()->complete()->create(['association_id' => $assoc->id]);  // BJF-AT-0002
    $this->ali->syncExtraValues([$this->shirt->id => 'ALI', $this->weight->id => '72.5', $this->vegan->id => 'yes', $this->donor->id => 'B']);
    $this->omar->syncExtraValues([$this->weight->id => '65']);

    $this->game = Game::factory()->create();
    $gs = GameSport::factory()->create(['game_id' => $this->game->id, 'sport_id' => Sport::factory()->create(['association_id' => $assoc->id])->id]);
    $ge = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $gs->id]);
    Submission::for($this->game, $assoc, 3)->entries()->create(['person_id' => $this->ali->id, 'game_event_id' => $ge->id, 'game_sport_id' => $gs->id]);
});

function runExtra(string $subject, array $columns, array $filters = [], ?int $gameId = null): ReportResult
{
    return app(Engine::class)->run(ReportDefinition::fromArray([...ReportDefinition::fresh(Registry::get($subject), $gameId),
        'columns' => $columns, 'filters' => $filters, 'sort' => ['field' => 'code', 'dir' => 'asc']]));
}

/** @return list<list<mixed>> displayed cells, row by row */
function shownExtra(ReportResult $r): array
{
    return array_map(fn (array $row) => array_map(fn (Field $f) => $f->display($row['cells'][$f->key]), $r->columns), $r->rows);
}

it('offers extra fields on People and Entries, sensitive ones only with view_medical', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ViewReports->value);
    actingInAdmin($sta);
    expect(array_keys(Registry::get('people')->fields()))->toContain('extra_shirt_name', 'extra_weight_class', 'extra_vegan')
        ->not->toContain('extra_donor_type')
        ->and(array_keys(Registry::get('entries')->fields()))->toContain('extra_shirt_name')->not->toContain('extra_donor_type');

    $sta->givePermissionTo(StaPermission::ViewMedical->value);
    actingInAdmin($sta->fresh());
    expect(array_keys(Registry::get('entries')->fields()))->toContain('extra_donor_type');
});

it('shows, filters and checks completeness with extra fields', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $columns = ['code', 'extra_shirt_name', 'extra_weight_class', 'extra_vegan', 'extra_donor_type', 'complete'];

    expect(shownExtra(runExtra('people', $columns)))->toBe([
        ['BJF-AT-0001', 'ALI', 72.5, 'Yes', 'B', 'Yes'],
        ['BJF-AT-0002', null, 65, null, null, 'No'], // required "Shirt name" missing
    ])->and(shownExtra(runExtra('people', ['code'], [['field' => 'extra_weight_class', 'op' => 'gt', 'value' => 70]])))->toBe([['BJF-AT-0001']])
        ->and(shownExtra(runExtra('people', ['code'], [['field' => 'extra_vegan', 'op' => 'no', 'value' => null]])))->toBe([['BJF-AT-0002']])
        ->and(shownExtra(runExtra('people', ['code'], [['field' => 'extra_donor_type', 'op' => 'in', 'value' => ['B']]])))->toBe([['BJF-AT-0001']])
        ->and(shownExtra(runExtra('entries', ['code', 'extra_shirt_name'], [], $this->game->id)))->toBe([['BJF-AT-0001', 'ALI']]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/ReportBuilder/ExtraFieldColumnsTest.php`
Expected: FAIL (`extra_shirt_name` not in fields; definition refused with "Choose a column from the list").

- [ ] **Step 3: Write `ExtraFields`**

`app/Services/Reports/Builder/ExtraFields.php`:

```php
<?php

namespace App\Services\Reports\Builder;

use App\Enums\ExtraFieldType;
use App\Enums\PersonType;
use App\Models\ExtraField;

/**
 * Spec 15.1: extra fields as report builder fields on subjects that join `people`. The SQL is built from the field id
 * (an integer from the database), never from input. Sensitive fields only for users who may view medical data.
 */
final class ExtraFields
{
    /** @return list<Field> */
    public static function fields(): array
    {
        $user = auth()->user();

        return ExtraField::active()->orderBy('id')->get()
            ->filter(fn (ExtraField $f) => $f->visibleTo($user))
            ->map(function (ExtraField $f) {
                $sql = "(SELECT pfv.value FROM person_field_values pfv WHERE pfv.person_id = people.id AND pfv.extra_field_id = {$f->id})";
                $key = 'extra_'.$f->key;
                $section = 'Additional information';

                return match ($f->type) {
                    ExtraFieldType::Text => Field::text($key, $f->label, $section, $sql),
                    ExtraFieldType::Number => Field::number($key, $f->label, $section, "({$sql} + 0)"),
                    ExtraFieldType::Date => Field::date($key, $f->label, $section, $sql),
                    ExtraFieldType::Choice => Field::enum($key, $f->label, $section, $sql, array_combine($f->options ?? [], $f->options ?? [])),
                    ExtraFieldType::YesNo => Field::boolean($key, $f->label, $section, "({$sql} + 0)"),
                };
            })->values()->all();
    }

    /** True when every active required extra field of the type has a value (values are never stored blank). */
    public static function completeSql(PersonType $type): string
    {
        return ExtraField::requiredFor($type)
            ->map(fn (ExtraField $f) => "EXISTS (SELECT 1 FROM person_field_values pfv WHERE pfv.person_id = people.id AND pfv.extra_field_id = {$f->id})")
            ->push('1 = 1')->implode(' AND ');
    }
}
```

- [ ] **Step 4: Override `fields()` and extend `complete`**

In `app/Services/Reports/Builder/Subjects/People.php` add `use App\Services\Reports\Builder\ExtraFields;` and the method:

```php
    public function fields(): array
    {
        return [...parent::fields(), ...collect(ExtraFields::fields())->keyBy('key')->all()];
    }
```

and in `define()` replace the `$complete` closure with:

```php
        // Same rule as Person::missingFields(), as "filled or not" only: cpr_no's value never leaves the database.
        $complete = fn (string $type) => implode(' AND ', [
            ...array_map(fn (string $c) => Sql::filled("people.{$c}"), array_keys(Person::REQUIRED_FOR_COMPLETE[$type])),
            ExtraFields::completeSql(PersonType::from($type)),
        ]);
```

In `app/Services/Reports/Builder/Subjects/Entries.php` add `use App\Services\Reports\Builder\ExtraFields;` and the same `fields()` method.

- [ ] **Step 5: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/ReportBuilder`
Expected: PASS (M4.1's "never exposes" guard still passes: extra keys are `extra_*`).

- [ ] **Step 6: Commit**

```bash
$PHP vendor/bin/pint
git add app/Services/Reports/Builder/ExtraFields.php app/Services/Reports/Builder/Subjects/People.php app/Services/Reports/Builder/Subjects/Entries.php tests/Feature/ReportBuilder/ExtraFieldColumnsTest.php
git commit -m "feat: report builder offers extra fields on People and Entries (sensitive ones only with view_medical)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Export layouts — permission, table, source whitelist and the editor page

**Files:**
- Create: `database/migrations/2026_11_13_000200_create_export_layouts_table.php`, `app/Models/ExportLayout.php`, `app/Services/GameFiles/ExportSources.php`, `app/Filament/Resources/Games/Pages/GameExportFiles.php`
- Modify: `app/Enums/StaPermission.php`, `app/Models/Game.php`, `app/Filament/Resources/Games/GameResource.php`, `app/Filament/Resources/Games/Pages/GameDashboard.php`, `app/Filament/Resources/Games/Tables/GamesTable.php`
- Test: `tests/Feature/GameFilesLayoutTest.php`

**Interfaces:**
- Consumes: `Person::{canViewMedical, MEDICAL_GATED}`, `ExtraField`, `ExtraFieldType`, `PersonType`, `Game`.
- Produces:
  - `StaPermission::ExportGameFiles` (`'export_game_files'`).
  - `App\Models\ExportLayout` — `game_id, type (PersonType), file_format ('xlsx'|'csv'), row_mode ('one_per_athlete'|'one_per_event'), columns (list<array{source: string, heading: string, format?: string, value?: string}>), zip_pattern`; consts `FORMATS`, `ROW_MODES`; `static for(Game, PersonType): self` (`firstOrNew`); `game(): BelongsTo`. `Game::exportLayouts(): HasMany`.
  - `App\Services\GameFiles\ExportSources` — consts `PERSON` (column ⇒ [label, kind]), `PHONES`, `FORMATS` (kind ⇒ format ⇒ label); `static all(): array<string, array{label, group, kind, medical: bool, active: bool}>`; `static options(?User, list<string> $keep = []): array<string, array<string, string>>` (grouped for a Select); `static validate(array $columns, ?User, list<string> $keep = []): list<string>` (error lines, `[]` = valid); `static format(mixed $value, string $kind, ?string $format): string|int|float|null`.
  - Page `GameExportFiles`, route name `export-files` → `/admin/games/{record}/export-files`; public `?array $data` (`data.athlete.*`, `data.official.*`), `save(): void`; header action `copy` (data `game_id`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/GameFilesLayoutTest.php`:

```php
<?php

use App\Enums\ExtraFieldType;
use App\Enums\PersonType;
use App\Enums\StaPermission;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\GameDashboard;
use App\Filament\Resources\Games\Pages\GameExportFiles;
use App\Models\ExportLayout;
use App\Models\ExtraField;
use App\Models\Game;
use App\Models\User;
use App\Services\GameFiles\ExportSources;
use Filament\Forms\Components\Repeater;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    ExtraField::factory()->create(['label' => 'Shirt name']);
    ExtraField::factory()->create(['label' => 'Donor type', 'type' => ExtraFieldType::Choice, 'options' => ['A'], 'sensitive' => true]);
    $this->exporter = User::factory()->sta()->create();
    $this->exporter->givePermissionTo(StaPermission::ExportGameFiles->value);
});

/** @return list<string> */
function sourceKeys(?User $user, array $keep = []): array
{
    return collect(ExportSources::options($user, $keep))->flatMap(fn (array $group) => array_keys($group))->all();
}

it('opens the page only with export_game_files and links it from the dashboard', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($sta);
    $this->get(GameResource::getUrl('export-files', ['record' => $this->game]))->assertForbidden();
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])->assertActionHidden('gameFiles');

    $sta->givePermissionTo(StaPermission::ExportGameFiles->value);
    $this->get(GameResource::getUrl('export-files', ['record' => $this->game]))->assertOk()->assertSee('Athlete layout');
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])->assertActionVisible('gameFiles');
});

it('offers medical and sensitive sources only with view_medical, or when already in the layout', function () {
    expect(sourceKeys($this->exporter))->toContain('person.passport_no', 'person.cpr_no', 'extra.shirt_name', 'entry.event', 'game.age', 'association.code', 'text')
        ->not->toContain('person.allergies', 'person.blood_group', 'extra.donor_type')
        ->and(sourceKeys($this->exporter, ['person.allergies']))->toContain('person.allergies');

    $this->exporter->givePermissionTo(StaPermission::ViewMedical->value);
    expect(sourceKeys($this->exporter->fresh()))->toContain('person.allergies', 'extra.donor_type');
});

it('validates layout columns against the source whitelist', function (array $column, ?string $error) {
    expect(ExportSources::validate([$column], $this->exporter))->toBe($error ? ["Column 1: {$error}"] : []);
})->with([
    'person field' => [['source' => 'person.passport_no', 'heading' => 'Passport'], null],
    'date format' => [['source' => 'person.dob', 'heading' => 'DOB', 'format' => 'd/m/Y'], null],
    'fixed text' => [['source' => 'text', 'heading' => 'NOC', 'value' => 'BRN'], null],
    'unknown source' => [['source' => 'people.passport_no_hash', 'heading' => 'X'], 'choose a source from the list.'],
    'format of another kind' => [['source' => 'person.dob', 'heading' => 'DOB', 'format' => 'upper'], 'choose a format from the list.'],
    'no heading' => [['source' => 'person.code', 'heading' => ''], 'give a heading of up to 100 characters.'],
    'fixed text missing' => [['source' => 'text', 'heading' => 'NOC'], 'enter the fixed text.'],
    'medical' => [['source' => 'person.allergies', 'heading' => 'Allergies'], 'only users with "View medical & health data" can add this column.'],
    'sensitive extra' => [['source' => 'extra.donor_type', 'heading' => 'Donor'], 'only users with "View medical & health data" can add this column.'],
]);

it('keeps a medical column a non-medical user did not add', function () {
    expect(ExportSources::validate([['source' => 'person.allergies', 'heading' => 'A']], $this->exporter, ['person.allergies']))->toBe([]);
});

it('formats values by kind', function (mixed $value, string $kind, ?string $format, mixed $out) {
    expect(ExportSources::format($value, $kind, $format))->toBe($out);
})->with([
    ['2005-03-10', 'date', 'd/m/Y', '10/03/2005'],
    ['2005-03-10', 'date', 'm/d/Y', '03/10/2005'],
    ['2005-03-10', 'date', 'Y-m-d', '2005-03-10'],
    ['2005-03-10', 'date', 'd-M-Y', '10-Mar-2005'],
    ['female', 'gender', null, 'Female'],
    ['female', 'gender', 'm_f', 'F'],
    ['male', 'gender', 'men_women', 'Men'],
    ['ali hasan', 'text', 'upper', 'ALI HASAN'],
    ['ALI HASAN', 'text', 'title', 'Ali Hasan'],
    ['Ali', 'text', null, 'Ali'],
    [true, 'yes_no', null, 'Yes'],
    ['0', 'yes_no', 'y_n', 'N'],
    [false, 'yes_no', '1_0', '0'],
    ['72.5', 'number', null, 72.5],
    [null, 'text', 'upper', null],
]);

it('saves both layouts from the editor in column order', function () {
    actingInAdmin($this->exporter);
    $undo = Repeater::fake();
    livewire(GameExportFiles::class, ['record' => $this->game->getRouteKey()])
        ->fillForm(['athlete' => ['file_format' => 'csv', 'row_mode' => 'one_per_event', 'zip_pattern' => '{passport_no}', 'columns' => [
            ['source' => 'text', 'heading' => 'NOC', 'format' => 'upper', 'value' => 'brn'],
            ['source' => 'person.passport_no', 'heading' => 'Passport', 'format' => null],
        ]]])
        ->call('save')->assertHasNoFormErrors()->assertNotified('Layouts saved');
    $undo();

    $layout = ExportLayout::for($this->game, PersonType::Athlete);
    expect($layout->only(['file_format', 'row_mode', 'zip_pattern']))->toBe(['file_format' => 'csv', 'row_mode' => 'one_per_event', 'zip_pattern' => '{passport_no}'])
        ->and($layout->columns)->toBe([
            ['source' => 'text', 'heading' => 'NOC', 'format' => 'upper', 'value' => 'brn'],
            ['source' => 'person.passport_no', 'heading' => 'Passport'],
        ])
        ->and(ExportLayout::for($this->game, PersonType::Official)->exists)->toBeTrue();
});

it('copies layouts from another game, leaving out medical columns for a non-medical user', function () {
    $other = Game::factory()->create(['name' => 'Gulf Games']);
    ExportLayout::create(['game_id' => $other->id, 'type' => 'athlete', 'file_format' => 'csv', 'row_mode' => 'one_per_athlete',
        'zip_pattern' => '{code}', 'columns' => [['source' => 'person.code', 'heading' => 'ID'], ['source' => 'person.allergies', 'heading' => 'Allergies']]]);
    actingInAdmin($this->exporter);

    livewire(GameExportFiles::class, ['record' => $this->game->getRouteKey()])
        ->callAction('copy', ['game_id' => $other->id])->assertNotified('Layouts copied from Gulf Games');

    expect(ExportLayout::for($this->game, PersonType::Athlete))
        ->file_format->toBe('csv')->zip_pattern->toBe('{code}')
        ->columns->toBe([['source' => 'person.code', 'heading' => 'ID']]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/GameFilesLayoutTest.php`
Expected: FAIL with `Undefined constant App\Enums\StaPermission::ExportGameFiles`.

- [ ] **Step 3: Permission, migration, model**

`app/Enums/StaPermission.php`: add `case ExportGameFiles = 'export_game_files';` after `ViewMedical`, and in `label()`:
`self::ExportGameFiles => 'Export files for game systems — includes passport numbers',`

`database/migrations/2026_11_13_000200_create_export_layouts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('file_format', 4)->default('xlsx');
            $table->string('row_mode', 20)->default('one_per_athlete');
            $table->json('columns');
            $table->string('zip_pattern', 200)->default('{code}_{kind}');
            $table->timestamps();
            $table->unique(['game_id', 'type']);
        });

        // Permissions live in the database; production gets the new one here (PermissionSeeder also creates it).
        Permission::findOrCreate('export_game_files', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('export_layouts');
        Permission::where('name', 'export_game_files')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
```

`app/Models/ExportLayout.php`:

```php
<?php

namespace App\Models;

use App\Enums\PersonType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** A game system upload file layout (spec 15.2): one per game and person type; columns are validated by ExportSources. */
class ExportLayout extends Model
{
    use LogsActivity;

    public const FORMATS = ['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV (UTF-8, comma)'];

    public const ROW_MODES = ['one_per_athlete' => 'One row per person, events joined by "; "', 'one_per_event' => 'One row per person per event'];

    protected $fillable = ['game_id', 'type', 'file_format', 'row_mode', 'columns', 'zip_pattern'];

    protected $attributes = ['file_format' => 'xlsx', 'row_mode' => 'one_per_athlete', 'columns' => '[]', 'zip_pattern' => '{code}_{kind}'];

    protected function casts(): array
    {
        return ['type' => PersonType::class, 'columns' => 'array'];
    }

    public static function for(Game $game, PersonType $type): self
    {
        return static::firstOrNew(['game_id' => $game->id, 'type' => $type->value]);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
```

In `app/Models/Game.php`:

```php
    public function exportLayouts(): HasMany
    {
        return $this->hasMany(ExportLayout::class);
    }
```

- [ ] **Step 4: Source whitelist**

`app/Services/GameFiles/ExportSources.php`:

```php
<?php

namespace App\Services\GameFiles;

use App\Enums\ExtraFieldType;
use App\Models\ExtraField;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The only sources an export layout column may use (spec 15.2), their kinds and format options. Passport and CPR
 * numbers are allowed (guarded by export_game_files); medical columns and sensitive extra fields need view_medical.
 */
final class ExportSources
{
    /** person column => [label, kind] */
    public const PERSON = [
        'code' => ['Person code', 'text'], 'full_name' => ['Full name', 'text'], 'first_name' => ['First name', 'text'],
        'second_name' => ['Second name', 'text'], 'third_name' => ['Third name', 'text'], 'last_name' => ['Last name', 'text'],
        'dob' => ['Date of birth', 'date'], 'gender' => ['Gender', 'gender'], 'nationality' => ['Nationality', 'text'],
        'height_cm' => ['Height (cm)', 'number'], 'weight_kg' => ['Weight (kg)', 'number'],
        'passport_no' => ['Passport number', 'text'], 'passport_issuing_country' => ['Passport issuing country', 'text'],
        'passport_issue_date' => ['Passport issue date', 'date'], 'passport_expiry' => ['Passport expiry', 'date'],
        'cpr_no' => ['CPR number', 'text'], 'cpr_expiry' => ['CPR expiry', 'date'],
        'function' => ['Function (team officials)', 'text'], 'languages' => ['Languages spoken', 'text'],
        'phone' => ['Mobile', 'text'], 'whatsapp' => ['WhatsApp', 'text'], 'email' => ['Email', 'text'], 'address' => ['Address', 'text'],
        'emergency_name' => ['Emergency contact name', 'text'], 'emergency_relationship' => ['Emergency contact relationship', 'text'],
        'emergency_phone' => ['Emergency phone', 'text'], 'guardian_name' => ['Guardian name', 'text'],
        'guardian_relationship' => ['Guardian relationship', 'text'], 'guardian_phone' => ['Guardian phone', 'text'],
        'guardian_email' => ['Guardian email', 'text'], 'fit' => ['Clothing fit', 'text'],
        'chest_cm' => ['Chest (cm)', 'number'], 'waist_cm' => ['Waist (cm)', 'number'],
        'consent_data' => ['Consent: store personal data', 'yes_no'], 'consent_media' => ['Consent: photos in media', 'yes_no'],
        'consent_medical' => ['Consent: emergency treatment', 'yes_no'], 'decl_conduct' => ['Declaration: code of conduct', 'yes_no'],
        'decl_antidoping' => ['Declaration: anti-doping', 'yes_no'], 'decl_data_sharing' => ['Declaration: data sharing', 'yes_no'],
        'blood_group' => ['Blood group', 'text'], 'meal_requirement' => ['Meal requirement', 'text'],
        'medical_clearance_expiry' => ['Medical clearance expiry', 'date'], 'insurance_policy_no' => ['Insurance policy number', 'text'],
        'allergies' => ['Allergies', 'text'], 'chronic_conditions' => ['Medical conditions', 'text'], 'medication' => ['Medication', 'text'],
    ];

    /** Exported as "{country code}{number}", e.g. +97333445566. */
    public const PHONES = ['phone', 'whatsapp', 'emergency_phone', 'guardian_phone'];

    /** kind => format => label; the first format is the default */
    public const FORMATS = [
        'date' => ['d/m/Y' => 'DD/MM/YYYY', 'm/d/Y' => 'MM/DD/YYYY', 'Y-m-d' => 'YYYY-MM-DD', 'd-M-Y' => 'DD-MMM-YYYY'],
        'gender' => ['male_female' => 'Male/Female', 'm_f' => 'M/F', 'men_women' => 'Men/Women'],
        'text' => ['as_is' => 'As is', 'upper' => 'UPPER CASE', 'title' => 'Title Case'],
        'yes_no' => ['yes_no' => 'Yes/No', 'y_n' => 'Y/N', '1_0' => '1/0'],
        'number' => [],
    ];

    private const GENDER = ['male_female' => ['male' => 'Male', 'female' => 'Female'], 'm_f' => ['male' => 'M', 'female' => 'F'],
        'men_women' => ['male' => 'Men', 'female' => 'Women']];

    private const YES_NO = ['yes_no' => ['Yes', 'No'], 'y_n' => ['Y', 'N'], '1_0' => ['1', '0']];

    /** @return array<string, array{label: string, group: string, kind: string, medical: bool, active: bool}> */
    public static function all(): array
    {
        $src = fn (string $label, string $group, string $kind, bool $medical = false, bool $active = true) => compact('label', 'group', 'kind', 'medical', 'active');
        $out = [];
        foreach (self::PERSON as $column => [$label, $kind]) {
            $out["person.{$column}"] = $src($label, 'Person', $kind, in_array($column, Person::MEDICAL_GATED, true));
        }
        // Inactive fields stay resolvable so stored layouts keep working; they are not offered for new columns.
        foreach (ExtraField::orderBy('id')->get() as $f) {
            $out["extra.{$f->key}"] = $src($f->label, 'Additional information', match ($f->type) {
                ExtraFieldType::Date => 'date', ExtraFieldType::Number => 'number', ExtraFieldType::YesNo => 'yes_no', default => 'text',
            }, $f->sensitive, $f->is_active);
        }

        return $out + [
            'entry.sport' => $src('Sport', 'Entry', 'text'),
            'entry.event' => $src('Event', 'Entry', 'text'),
            'entry.event_gender' => $src('Event gender', 'Entry', 'text'),
            'game.age' => $src('Age at game start', 'Game', 'number'),
            'association.code' => $src('Association code', 'Association', 'text'),
            'association.name' => $src('Association name', 'Association', 'text'),
            'text' => $src('Fixed text', 'Other', 'text'),
        ];
    }

    /**
     * @param  list<string>  $keep  sources already in the stored layout (kept even when the user could not add them)
     * @return array<string, array<string, string>> group => source => label
     */
    public static function options(?User $user, array $keep = []): array
    {
        $medic = Person::canViewMedical($user);
        $options = [];
        foreach (self::all() as $key => $s) {
            if (in_array($key, $keep, true) || ($s['active'] && ($medic || ! $s['medical']))) {
                $options[$s['group']][$key] = $s['label'];
            }
        }

        return $options;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  list<string>  $keep  sources already in the stored layout
     * @return list<string> one line per problem; [] when valid
     */
    public static function validate(array $columns, ?User $user, array $keep = []): array
    {
        $all = self::all();
        $errors = [];
        foreach (array_values($columns) as $i => $c) {
            $line = fn (string $message) => 'Column '.($i + 1).': '.$message;
            $s = $all[$c['source'] ?? ''] ?? null;
            if (! $s) {
                $errors[] = $line('choose a source from the list.');

                continue;
            }
            if ($s['medical'] && ! Person::canViewMedical($user) && ! in_array($c['source'], $keep, true)) {
                $errors[] = $line('only users with "View medical & health data" can add this column.');
            }
            if (blank($c['heading'] ?? null) || mb_strlen((string) $c['heading']) > 100) {
                $errors[] = $line('give a heading of up to 100 characters.');
            }
            if (filled($c['format'] ?? null) && ! array_key_exists($c['format'], self::FORMATS[$s['kind']])) {
                $errors[] = $line('choose a format from the list.');
            }
            if ($c['source'] === 'text' && blank($c['value'] ?? null)) {
                $errors[] = $line('enter the fixed text.');
            }
        }

        return $errors;
    }

    public static function format(mixed $value, string $kind, ?string $format): string|int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($kind) {
            'date' => Carbon::parse($value)->format($format ?: 'd/m/Y'),
            'gender' => self::GENDER[$format ?: 'male_female'][$value] ?? (string) $value,
            'yes_no' => self::YES_NO[$format ?: 'yes_no'][$value ? 0 : 1],
            'number' => is_numeric($value) ? $value + 0 : (string) $value,
            default => match ($format) {
                'upper' => mb_strtoupper((string) $value),
                'title' => mb_convert_case(mb_strtolower((string) $value), MB_CASE_TITLE),
                default => (string) $value,
            },
        };
    }
}
```

- [ ] **Step 5: Editor page, route and links**

`app/Filament/Resources/Games/Pages/GameExportFiles.php` (Task 6 and 7 add the `download` and `zip` header actions):

```php
<?php

namespace App\Filament\Resources\Games\Pages;

use App\Enums\PersonType;
use App\Enums\StaPermission;
use App\Filament\Resources\Games\GameResource;
use App\Models\ExportLayout;
use App\Models\Game;
use App\Models\Person;
use App\Services\GameFiles\ExportSources;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

/** Spec 15.2/15.3: the game's athlete and official upload-file layouts, the upload file and the files ZIP. */
class GameExportFiles extends Page
{
    use InteractsWithRecord;

    protected static string $resource = GameResource::class;

    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can(StaPermission::ExportGameFiles->value);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->fillLayouts();
    }

    public function getTitle(): string
    {
        return $this->record->name.' - game system files';
    }

    private function fillLayouts(): void
    {
        $this->form->fill(collect(PersonType::cases())->mapWithKeys(fn (PersonType $t) => [
            $t->value => ExportLayout::for($this->record, $t)->only(['file_format', 'row_mode', 'zip_pattern', 'columns']),
        ])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('layouts')->tabs([
                Tab::make('Athlete layout')->schema($this->layoutFields(PersonType::Athlete)),
                Tab::make('Official layout')->schema($this->layoutFields(PersonType::Official)),
            ]),
        ])->statePath('data');
    }

    private function layoutFields(PersonType $type): array
    {
        $p = $type->value;
        $keep = array_column(ExportLayout::for($this->record, $type)->columns ?? [], 'source');
        $options = ExportSources::options(auth()->user(), $keep);
        $all = ExportSources::all();
        $formats = fn (Get $get) => ExportSources::FORMATS[$all[$get('source')]['kind'] ?? 'number'];

        return [
            Grid::make(3)->schema([
                Select::make("{$p}.file_format")->label('File type')->options(ExportLayout::FORMATS)->required(),
                Select::make("{$p}.row_mode")->label('Rows')->options(ExportLayout::ROW_MODES)->required(),
                TextInput::make("{$p}.zip_pattern")->label('ZIP file names')->required()->maxLength(200)
                    ->helperText('Tokens: {code} {passport_no} {last_name} {first_name} {association_code} {kind}'),
            ]),
            Repeater::make("{$p}.columns")->label('Columns')->reorderable()->defaultItems(0)->addActionLabel('Add column')->columns(4)->schema([
                Select::make('source')->options($options)->searchable()->required()->live()
                    ->in(collect($options)->flatMap(fn (array $group) => array_keys($group))->all())
                    ->afterStateUpdated(fn (Set $set) => $set('format', null)),
                TextInput::make('heading')->required()->maxLength(100),
                Select::make('format')->options($formats)->placeholder('Default')->visible(fn (Get $get) => $formats($get) !== []),
                TextInput::make('value')->label('Fixed text')->required()->maxLength(100)->visible(fn (Get $get) => $get('source') === 'text'),
            ]),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Save layouts')->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $layouts = [];
        $errors = [];
        foreach (PersonType::cases() as $t) {
            $layout = ExportLayout::for($this->record, $t);
            $columns = array_map(fn (array $c) => array_filter(Arr::only($c, ['source', 'heading', 'format', 'value']), fn ($v) => filled($v)),
                array_values($state[$t->value]['columns'] ?? []));
            foreach (ExportSources::validate($columns, auth()->user(), array_column($layout->columns ?? [], 'source')) as $line) {
                $errors[] = $t->label().' layout, '.lcfirst($line);
            }
            $layouts[] = $layout->fill([...Arr::only($state[$t->value], ['file_format', 'row_mode', 'zip_pattern']), 'columns' => $columns]);
        }
        if ($errors) {
            Notification::make()->danger()->title('Layouts not saved')->body(implode("\n", $errors))->send();

            return;
        }
        foreach ($layouts as $layout) {
            $layout->save();
        }
        Notification::make()->success()->title('Layouts saved')->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copy')->label('Copy layouts from another game')->color('gray')
                ->modalDescription('Replaces both layouts of this game.')
                ->schema([Select::make('game_id')->label('Game')->required()
                    ->options(fn () => Game::whereKeyNot($this->record->id)->whereHas('exportLayouts')->orderBy('name')->pluck('name', 'id'))])
                ->action(fn (array $data) => $this->copyFrom(Game::findOrFail($data['game_id']))),
        ];
    }

    private function copyFrom(Game $source): void
    {
        $all = ExportSources::all();
        $medic = Person::canViewMedical(auth()->user());
        $dropped = [];
        foreach ($source->exportLayouts as $from) {
            $columns = collect($from->columns)->reject(function (array $c) use ($all, $medic, &$dropped) {
                $leave = ! $medic && ($all[$c['source']]['medical'] ?? false);
                if ($leave) {
                    $dropped[] = $c['heading'];
                }

                return $leave;
            })->values()->all();
            ExportLayout::for($this->record, $from->type)
                ->fill([...$from->only(['file_format', 'row_mode', 'zip_pattern']), 'columns' => $columns])->save();
        }
        $this->fillLayouts();
        Notification::make()->success()->title("Layouts copied from {$source->name}")
            ->body($dropped ? 'Left out (needs "View medical & health data"): '.implode(', ', $dropped) : null)->send();
    }
}
```

`app/Filament/Resources/Games/GameResource.php`:
- import `App\Filament\Resources\Games\Pages\GameExportFiles;`
- `getPages()`: add `'export-files' => GameExportFiles::route('/{record}/export-files'),`
- `canAccess()` (after M4 it already ORs `ViewReports`) becomes:

```php
    public static function canAccess(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->can(StaPermission::ManageGames->value) || $u?->can(StaPermission::ViewSubmissions->value)
            || $u?->can(StaPermission::ViewReports->value) || $u?->can(StaPermission::ExportGameFiles->value));
    }
```

`app/Filament/Resources/Games/Pages/GameDashboard.php` — add to `getHeaderActions()` after `editGame`:

```php
            Action::make('gameFiles')->label('Game system files')->color('gray')
                ->url(fn () => GameResource::getUrl('export-files', ['record' => $this->record]))
                ->visible(fn () => (bool) auth()->user()->can(StaPermission::ExportGameFiles->value)),
```

`app/Filament/Resources/Games/Tables/GamesTable.php` — add to `recordActions([...])` after `dashboard` (so an export-only user can reach the page):

```php
                Action::make('gameFiles')->label('Game system files')->icon('heroicon-o-arrow-up-on-square')
                    ->visible(fn () => (bool) auth()->user()->can(StaPermission::ExportGameFiles->value))
                    ->url(fn ($record) => GameResource::getUrl('export-files', ['record' => $record])),
```

- [ ] **Step 6: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/GameFilesLayoutTest.php tests/Feature/GameDashboardTest.php tests/Feature/StaUserResourceTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
$PHP vendor/bin/pint
git add app/Enums/StaPermission.php database/migrations/2026_11_13_000200_create_export_layouts_table.php app/Models/ExportLayout.php app/Models/Game.php app/Services/GameFiles/ExportSources.php app/Filament/Resources/Games/Pages/GameExportFiles.php app/Filament/Resources/Games/GameResource.php app/Filament/Resources/Games/Pages/GameDashboard.php app/Filament/Resources/Games/Tables/GamesTable.php tests/Feature/GameFilesLayoutTest.php
git commit -m "feat: per-game athlete and official export layouts (source whitelist, formats, copy) behind export_game_files

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Upload file — included people, rows, xlsx and CSV, audited download

**Files:**
- Create: `app/Services/GameFiles/GameFileExporter.php`
- Modify: `app/Services/Reports/GameReports.php` (public `includedSubmissions()`), `app/Filament/Resources/Games/Pages/GameExportFiles.php` (header action `download`, `filterFields()`)
- Test: `tests/Feature/GameFilesExportTest.php`

**Interfaces:**
- Consumes: M4 `GameReports` private `submitted()`; `ExportLayout`, `ExportSources::{all, format, PHONES}` (Task 5); `Person::{canViewMedical, age, fieldValues}`; OpenSpout `Writer`/`Row::fromValues`.
- Produces:
  - `GameReports::includedSubmissions(Game $game, array $with = []): Collection<int, Submission>` — per association its submitted Phase 4, else its submitted Phase 3; `association` loaded; sorted by association name. M4's private `people()` now calls it.
  - `GameFileExporter` (resolve with `app()`):
    - `people(Game, PersonType, ?int $associationId = null, ?int $gameSportId = null): Collection<int, array{person: Person, association: Association, entries: Collection<int, Entry>}>` — athletes = entries with an event, officials = without; sorted by association name, then person code.
    - `table(ExportLayout, ?User, ?int $associationId = null, ?int $gameSportId = null): array{headings: list<string>, rows: list<list<scalar|null>>, left_out: list<string>}`
    - `write(ExportLayout, array $table): array{0: string, 1: string}` — temp path and download name.
  - `GameExportFiles::filterFields(): array` (type, association, sport selects; reused by Task 7), header action `download` (data `type`, `association_id`, `game_sport_id`), `download(PersonType, ?int, ?int): ?StreamedResponse`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/GameFilesExportTest.php`:

```php
<?php

use App\Enums\EventGender;
use App\Enums\ExtraFieldType;
use App\Enums\PersonType;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\Pages\GameExportFiles;
use App\Models\Association;
use App\Models\Event;
use App\Models\ExportLayout;
use App\Models\ExtraField;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\GameFiles\GameFileExporter;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games', 'start_date' => '2026-12-01', 'end_date' => '2026-12-10']);
    $judoFed = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $karateFed = Association::factory()->create(['code' => 'BKF', 'name' => 'Bahrain Karate Federation']);
    $this->game->associations()->attach([$judoFed->id, $karateFed->id]);
    $sport = fn (Association $a, string $name) => GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $a->id, 'name' => $name])->id]);
    $event = fn (GameSport $gs, string $name) => GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $gs->id,
        'event_id' => Event::factory()->create(['sport_id' => $gs->sport_id, 'name' => $name, 'gender' => EventGender::Men])->id]);
    $this->judo = $sport($judoFed, 'Judo');
    $m60 = $event($this->judo, 'Men -60kg');
    $m66 = $event($this->judo, 'Men -66kg');
    $karate = $sport($karateFed, 'Karate');
    $kumite = $event($karate, 'Kumite');
    $this->karateFed = $karateFed;

    $shirt = ExtraField::factory()->create(['label' => 'Shirt name']);
    $donor = ExtraField::factory()->create(['label' => 'Donor type', 'type' => ExtraFieldType::Choice, 'options' => ['A', 'B'], 'sensitive' => true]);
    $this->ali = Person::factory()->create(['association_id' => $judoFed->id, 'first_name' => 'Ali', 'last_name' => 'Hasan',
        'full_name' => 'Ali "The Lion" Hasan', 'passport_no' => 'BH1234567', 'allergies' => 'Peanuts', 'consent_media' => true]);
    $this->ali->syncExtraValues([$shirt->id => 'ali h', $donor->id => 'B']);
    $omar = Person::factory()->create(['association_id' => $judoFed->id, 'first_name' => 'Omar', 'last_name' => 'Saleh']);
    $coach = Person::factory()->official()->create(['association_id' => $judoFed->id, 'function' => 'Head Coach']);
    $karim = Person::factory()->create(['association_id' => $karateFed->id, 'first_name' => 'Karim', 'last_name' => 'Ahmed',
        'full_name' => 'كريم أحمد', 'passport_no' => 'BH7654321']);

    $athlete = fn (Submission $s, Person $p, GameEvent $ge) => $s->entries()->create(['person_id' => $p->id, 'game_event_id' => $ge->id, 'game_sport_id' => $ge->game_sport_id]);
    $official = fn (Submission $s, Person $p, GameSport $gs) => $s->entries()->create(['person_id' => $p->id, 'game_event_id' => null, 'game_sport_id' => $gs->id]);
    // Judo: Long List (Ali, Omar, coach) and Short List (Ali in two events, coach) both submitted.
    $p3 = Submission::for($this->game, $judoFed, 3);
    $this->p4 = Submission::for($this->game, $judoFed, 4); // created while Phase 3 is a draft: nothing preloaded
    $athlete($p3, $this->ali, $m60);
    $athlete($p3, $omar, $m60);
    $official($p3, $coach, $this->judo);
    $athlete($this->p4, $this->ali, $m60);
    $athlete($this->p4, $this->ali, $m66);
    $official($this->p4, $coach, $this->judo);
    // Karate: Long List only.
    $k3 = Submission::for($this->game, $karateFed, 3);
    $athlete($k3, $karim, $kumite);
    foreach ([$p3, $this->p4, $k3] as $s) {
        $s->update(['status' => SubmissionStatus::Submitted]);
    }

    $this->layout = ExportLayout::create(['game_id' => $this->game->id, 'type' => 'athlete', 'file_format' => 'xlsx', 'row_mode' => 'one_per_athlete', 'columns' => [
        ['source' => 'person.code', 'heading' => 'ID'],
        ['source' => 'person.last_name', 'heading' => 'SURNAME', 'format' => 'upper'],
        ['source' => 'person.dob', 'heading' => 'DOB', 'format' => 'd/m/Y'],
        ['source' => 'person.gender', 'heading' => 'Sex', 'format' => 'm_f'],
        ['source' => 'person.passport_no', 'heading' => 'Passport'],
        ['source' => 'entry.event', 'heading' => 'Events'],
        ['source' => 'game.age', 'heading' => 'Age'],
        ['source' => 'association.code', 'heading' => 'Member'],
        ['source' => 'text', 'heading' => 'NOC', 'value' => 'BRN'],
        ['source' => 'extra.shirt_name', 'heading' => 'Shirt', 'format' => 'title'],
        ['source' => 'extra.donor_type', 'heading' => 'Donor'],
        ['source' => 'person.allergies', 'heading' => 'Allergies'],
        ['source' => 'person.consent_media', 'heading' => 'Media', 'format' => 'y_n'],
    ]]);
    $this->exporter = User::factory()->sta()->create();
    $this->exporter->givePermissionTo(StaPermission::ExportGameFiles->value);
});

it('builds rows from each association\'s Short List, else its Long List, leaving medical columns out for a non-medical user', function () {
    $table = app(GameFileExporter::class)->table($this->layout, $this->exporter);

    expect($table['headings'])->toBe(['ID', 'SURNAME', 'DOB', 'Sex', 'Passport', 'Events', 'Age', 'Member', 'NOC', 'Shirt', 'Media'])
        ->and($table['left_out'])->toBe(['Donor', 'Allergies'])
        ->and($table['rows'])->toBe([
            ['BJF-AT-0001', 'HASAN', '10/03/2005', 'M', 'BH1234567', 'Men -60kg; Men -66kg', 21, 'BJF', 'BRN', 'Ali H', 'Y'],
            ['BKF-AT-0001', 'AHMED', '10/03/2005', 'M', 'BH7654321', 'Kumite', 21, 'BKF', 'BRN', null, 'N'],
        ]);

    $this->exporter->givePermissionTo(StaPermission::ViewMedical->value);
    $medic = app(GameFileExporter::class)->table($this->layout, $this->exporter->fresh());
    expect($medic['left_out'])->toBe([])->and(array_slice($medic['rows'][0], 10, 2))->toBe(['B', 'Peanuts']);
});

it('falls back to the Long List, splits rows per event and filters by association and sport', function () {
    $this->p4->update(['status' => SubmissionStatus::Draft]);
    $codes = fn (array $t) => array_column($t['rows'], 0);
    $exporter = app(GameFileExporter::class);

    expect($codes($exporter->table($this->layout, $this->exporter)))->toBe(['BJF-AT-0001', 'BJF-AT-0002', 'BKF-AT-0001']);

    $this->p4->update(['status' => SubmissionStatus::Submitted]);
    $this->layout->update(['row_mode' => 'one_per_event']);
    $perEvent = $exporter->table($this->layout->fresh(), $this->exporter);
    expect(array_map(fn ($r) => [$r[0], $r[5]], $perEvent['rows']))->toBe([
        ['BJF-AT-0001', 'Men -60kg'], ['BJF-AT-0001', 'Men -66kg'], ['BKF-AT-0001', 'Kumite'],
    ])->and($codes($exporter->table($this->layout, $this->exporter, $this->karateFed->id)))->toBe(['BKF-AT-0001'])
        ->and($codes($exporter->table($this->layout, $this->exporter, null, $this->judo->id)))->toBe(['BJF-AT-0001', 'BJF-AT-0001']);
});

it('exports team officials with the official layout', function () {
    $layout = ExportLayout::create(['game_id' => $this->game->id, 'type' => 'official', 'columns' => [
        ['source' => 'person.code', 'heading' => 'ID'], ['source' => 'person.function', 'heading' => 'Role'], ['source' => 'entry.sport', 'heading' => 'Sport'],
    ]]);

    expect(app(GameFileExporter::class)->table($layout, $this->exporter)['rows'])->toBe([['BJF-TO-0001', 'Head Coach', 'Judo']]);
});

it('writes CSV as UTF-8 with commas and proper quoting', function () {
    $this->layout->update(['file_format' => 'csv', 'columns' => [
        ['source' => 'person.full_name', 'heading' => 'Name, full'], ['source' => 'text', 'heading' => 'NOC', 'value' => 'BRN'],
    ]]);
    $exporter = app(GameFileExporter::class);
    [$path, $name] = $exporter->write($this->layout->fresh(), $exporter->table($this->layout->fresh(), $this->exporter));

    expect($name)->toBe('asian-indoor-games-athletes.csv')
        ->and(file_get_contents($path))->toBe("\"Name, full\",NOC\r\n\"Ali \"\"The Lion\"\" Hasan\",BRN\r\n\"كريم أحمد\",BRN\r\n");
    unlink($path);
});

it('downloads the upload file as Excel from the game page and audits it', function () {
    actingInAdmin($this->exporter);
    $page = livewire(GameExportFiles::class, ['record' => $this->game->getRouteKey()])
        ->callAction('download', ['type' => 'athlete'])
        ->assertFileDownloaded('asian-indoor-games-athletes.xlsx')
        ->assertNotified('Some columns were left out');

    $path = sys_get_temp_dir().'/'.uniqid('gsf').'.xlsx';
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
    expect($rows[0][0])->toBe('ID')->and($rows[1][4])->toBe('BH1234567')->and(count($rows))->toBe(3);

    $log = Activity::where('description', 'game system file exported')->sole();
    expect($log->causer_id)->toBe($this->exporter->id)->and($log->subject_id)->toBe($this->game->id)
        ->and($log->properties->only(['layout', 'file', 'rows', 'left_out'])->all())
        ->toBe(['layout' => 'athlete', 'file' => 'asian-indoor-games-athletes.xlsx', 'rows' => 2, 'left_out' => ['Donor', 'Allergies']]);
});

it('asks for a layout before downloading', function () {
    actingInAdmin($this->exporter);
    livewire(GameExportFiles::class, ['record' => $this->game->getRouteKey()])
        ->callAction('download', ['type' => 'official'])->assertNotified('Set up the official layout first.')->assertNoFileDownloaded();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/GameFilesExportTest.php`
Expected: FAIL with `Class "App\Services\GameFiles\GameFileExporter" not found`.

- [ ] **Step 3: Share the inclusion rule in `GameReports`**

In `app/Services/Reports/GameReports.php` replace the private `people()` with:

```php
    /**
     * Per association: its submitted Short List (Phase 4) if any, else its submitted Long List (Phase 3).
     * Shared with the game system upload files (M4.2).
     *
     * @return Collection<int, Submission> association loaded, sorted by association name
     */
    public function includedSubmissions(Game $game, array $with = []): Collection
    {
        return $this->submitted($game, 4, $with)->keyBy('association_id')
            ->union($this->submitted($game, 3, $with)->keyBy('association_id'))
            ->sortBy('association.name')->values();
    }

    /**
     * Distinct people per association from includedSubmissions().
     *
     * @return Collection<int, array{0: Submission, 1: Person}>
     */
    private function people(Game $game): Collection
    {
        return $this->includedSubmissions($game, ['entries.person.sizes'])
            ->flatMap(fn (Submission $s) => $s->entries->pluck('person')->unique('id')->sortBy('code')
                ->map(fn (Person $p) => [$s, $p]))->values();
    }
```

- [ ] **Step 4: Write the exporter**

`app/Services/GameFiles/GameFileExporter.php`:

```php
<?php

namespace App\Services\GameFiles;

use App\Enums\PersonType;
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
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Spec 15.2: the rows and the file of a game system upload file. */
final class GameFileExporter
{
    public function __construct(private readonly GameReports $reports) {}

    /** @return Collection<int, array{person: Person, association: Association, entries: Collection<int, Entry>}> */
    public function people(Game $game, PersonType $type, ?int $associationId = null, ?int $gameSportId = null): Collection
    {
        $athletes = $type === PersonType::Athlete;

        return $this->reports->includedSubmissions($game, ['entries.person.fieldValues', 'entries.gameEvent.event', 'entries.gameSport.sport'])
            ->when($associationId, fn (Collection $c) => $c->where('association_id', $associationId))
            ->flatMap(fn (Submission $s) => $s->entries
                ->filter(fn (Entry $e) => ($e->game_event_id !== null) === $athletes && (! $gameSportId || (int) $e->game_sport_id === $gameSportId))
                ->sortBy('id')->groupBy('person_id')
                ->map(fn (Collection $entries) => ['person' => $entries->first()->person, 'association' => $s->association, 'entries' => $entries->values()])
                ->sortBy(fn (array $r) => $r['person']->code)->values())
            ->values();
    }

    /** @return array{headings: list<string>, rows: list<list<scalar|null>>, left_out: list<string>} */
    public function table(ExportLayout $layout, ?User $user, ?int $associationId = null, ?int $gameSportId = null): array
    {
        $game = $layout->game;
        $sources = ExportSources::all();
        $medic = Person::canViewMedical($user);
        $columns = [];
        $leftOut = [];
        foreach ($layout->columns as $c) {
            $s = $sources[$c['source']] ?? null;
            if (! $s || ($s['medical'] && ! $medic)) {
                $leftOut[] = $c['heading'];

                continue;
            }
            $columns[] = [...$c, 'kind' => $s['kind']];
        }
        $extraIds = ExtraField::pluck('id', 'key');
        $rows = [];
        foreach ($this->people($game, $layout->type, $associationId, $gameSportId) as $r) {
            $groups = $layout->row_mode === 'one_per_event' ? $r['entries']->map(fn (Entry $e) => collect([$e])) : [$r['entries']];
            foreach ($groups as $entries) {
                $rows[] = array_map(fn (array $c) => ExportSources::format(
                    $this->raw($c, $r['person'], $r['association'], $entries, $game, $extraIds), $c['kind'], $c['format'] ?? null), $columns);
            }
        }

        return ['headings' => array_column($columns, 'heading'), 'rows' => $rows, 'left_out' => $leftOut];
    }

    /** @return array{0: string, 1: string} temp file path, download name */
    public function write(ExportLayout $layout, array $table): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'gsf');
        if ($layout->file_format === 'csv') {
            $h = fopen($tmp, 'w');
            foreach ([$table['headings'], ...$table['rows']] as $row) {
                // RFC 4180: comma, double quotes doubled, no backslash escape, CRLF.
                fputcsv($h, array_map(fn ($v) => $v ?? '', $row), ',', '"', '', "\r\n");
            }
            fclose($h);
        } else {
            $writer = new Writer;
            $writer->openToFile($tmp);
            $writer->addRow(Row::fromValues($table['headings']));
            foreach ($table['rows'] as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->close();
        }
        $who = $layout->type === PersonType::Athlete ? 'athletes' : 'officials';

        return [$tmp, Str::slug($layout->game->name)."-{$who}.{$layout->file_format}"];
    }

    private function raw(array $c, Person $p, Association $a, Collection $entries, Game $game, Collection $extraIds): mixed
    {
        [$group, $name] = explode('.', $c['source'], 2) + [1 => null];
        $joined = fn (callable $value) => $entries->map($value)->filter()->unique()->implode('; ') ?: null;

        return match ($group) {
            'person' => match (true) {
                in_array($name, ExportSources::PHONES, true) => filled($p->{$name}) ? $p->{$name.'_cc'}.$p->{$name} : null,
                $name === 'gender' => $p->gender->value,
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
```

- [ ] **Step 5: Download action on the page**

In `app/Filament/Resources/Games/Pages/GameExportFiles.php` add imports `use App\Services\GameFiles\GameFileExporter;` and `use Symfony\Component\HttpFoundation\StreamedResponse;`, add the `download` action as the **first** entry of `getHeaderActions()`, and the two methods:

```php
            Action::make('download')->label('Download upload file')->icon('heroicon-o-arrow-down-tray')
                ->schema(fn () => $this->filterFields())
                ->action(fn (array $data) => $this->download(PersonType::from($data['type']),
                    filled($data['association_id'] ?? null) ? (int) $data['association_id'] : null,
                    filled($data['game_sport_id'] ?? null) ? (int) $data['game_sport_id'] : null)),
```

```php
    /** Type, association and sport choices shared by the upload file and the files ZIP. */
    public function filterFields(): array
    {
        return [
            Select::make('type')->label('People')->options(['athlete' => 'Athletes', 'official' => 'Team officials'])
                ->required()->default('athlete'),
            Select::make('association_id')->label('Association')->placeholder('All associations')
                ->options($this->record->associations()->orderBy('name')->pluck('name', 'associations.id')),
            Select::make('game_sport_id')->label('Sport')->placeholder('All sports')
                ->options($this->record->gameSports()->with('sport')->get()->mapWithKeys(fn ($gs) => [$gs->id => $gs->sport->name])->sort()),
        ];
    }

    public function download(PersonType $type, ?int $associationId = null, ?int $gameSportId = null): ?StreamedResponse
    {
        $layout = ExportLayout::for($this->record, $type);
        if ($layout->columns === []) {
            Notification::make()->warning()->title("Set up the {$type->value} layout first.")->send();

            return null;
        }
        $exporter = app(GameFileExporter::class);
        $table = $exporter->table($layout, auth()->user(), $associationId, $gameSportId);
        [$path, $name] = $exporter->write($layout, $table);

        activity()->performedOn($this->record)->event('exported')
            ->withProperties(['layout' => $type->value, 'file' => $name, 'rows' => count($table['rows']), 'left_out' => $table['left_out'],
                'association_id' => $associationId, 'game_sport_id' => $gameSportId])
            ->log('game system file exported');
        if ($table['left_out']) {
            Notification::make()->warning()->title('Some columns were left out')
                ->body('They need "View medical & health data": '.implode(', ', $table['left_out']))->send();
        }

        return response()->streamDownload(function () use ($path) {
            readfile($path);
            unlink($path);
        }, $name, ['Content-Type' => $layout->file_format === 'csv' ? 'text/csv; charset=UTF-8'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
```

- [ ] **Step 6: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/GameFilesExportTest.php tests/Feature/GameReportsTest.php`
Expected: PASS (M4's reports unchanged).

- [ ] **Step 7: Commit**

```bash
$PHP vendor/bin/pint
git add app/Services/Reports/GameReports.php app/Services/GameFiles/GameFileExporter.php app/Filament/Resources/Games/Pages/GameExportFiles.php tests/Feature/GameFilesExportTest.php
git commit -m "feat: game system upload file (xlsx or CSV) from the layout, Short List else Long List, filters, audited

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Photos and documents ZIP — queued job, naming pattern, in-app notification

**Files:**
- Create: `app/Jobs/BuildGameFilesZip.php`, `database/migrations/2026_11_13_000300_create_notifications_table.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php` (`->databaseNotifications()`), `app/Filament/Resources/Games/Pages/GameExportFiles.php` (header action `zip`)
- Test: `tests/Feature/GameFilesZipTest.php`

**Interfaces:**
- Consumes: `GameFileExporter::people()`, `GameExportFiles::filterFields()` (Task 6), `ExportLayout` (Task 5), `Person::canViewMedical()`, `Filament\Notifications\Notification::sendToDatabase()`, `ZipArchive`.
- Produces:
  - `App\Jobs\BuildGameFilesZip(ExportLayout $layout, User $user, array $columns = [], ?int $associationId = null, ?int $gameSportId = null)` — `$columns` ⊆ `['passport_scan_path', 'cpr_front_path', 'wada_certificate_path']` (the photo is always included); const `KINDS` (column ⇒ kind token); `static fileName(string $pattern, Person, Association, string $kind, string $ext, array &$used): string`.
  - ZIP at `exports/{uuid}/{game slug}-{athletes|officials}-files.zip` on the private disk; database notification "Files ZIP ready" with a `Download ZIP` action whose URL is a 5-minute `temporaryUrl`; activity `game files ZIP exported`.
  - Page header action `zip` (data: `type`, `association_id`, `game_sport_id`, `columns`).
- **Notification choice:** Filament database notifications (the bell in `/admin`), enabled here with the standard `notifications` table. This is the smallest way to be "notified in the app"; the link expires 5 minutes after the ZIP is built, and the user runs the action again if it lapsed.

- [ ] **Step 1: Write the failing test**

`tests/Feature/GameFilesZipTest.php`:

```php
<?php

use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\Pages\GameExportFiles;
use App\Jobs\BuildGameFilesZip;
use App\Models\Association;
use App\Models\ExportLayout;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\GameFiles\GameFileExporter;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->disk = Storage::fake(config('filesystems.private'));
    $this->disk->buildTemporaryUrlsUsing(fn (string $path) => 'https://files.test/'.$path);

    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $gs = GameSport::factory()->create(['game_id' => $this->game->id, 'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id])->id]);
    $ge = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $gs->id]);
    // Same last name on purpose: the pattern below makes their photo names collide.
    $ali = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan',
        'photo_path' => 'photos/a.jpg', 'passport_scan_path' => 'scans/a.pdf', 'wada_certificate_path' => 'scans/w.pdf']);
    $omar = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Omar', 'last_name' => 'Hasan',
        'photo_path' => 'photos/o.JPG']);
    $this->disk->put('photos/a.jpg', 'ali-photo');
    $this->disk->put('scans/a.pdf', 'ali-passport');
    $this->disk->put('scans/w.pdf', 'ali-wada');
    $this->disk->put('photos/o.JPG', 'omar-photo');
    $s = Submission::for($this->game, $this->assoc, 3);
    foreach ([$ali, $omar] as $p) {
        $s->entries()->create(['person_id' => $p->id, 'game_event_id' => $ge->id, 'game_sport_id' => $gs->id]);
    }
    $s->update(['status' => SubmissionStatus::Submitted]);

    $this->layout = ExportLayout::create(['game_id' => $this->game->id, 'type' => 'athlete', 'columns' => [],
        'zip_pattern' => '{association_code} {last_name}:{kind}']);
    $this->user = User::factory()->sta()->create();
    $this->user->givePermissionTo(StaPermission::ExportGameFiles->value);
});

/** @return array<string, string> file name => contents */
function zipContents(): array
{
    $paths = Storage::disk(config('filesystems.private'))->allFiles('exports');
    expect($paths)->toHaveCount(1)->and($paths[0])->toEndWith('/asian-indoor-games-athletes-files.zip');
    $tmp = tempnam(sys_get_temp_dir(), 'zt');
    file_put_contents($tmp, Storage::disk(config('filesystems.private'))->get($paths[0]));
    $zip = new ZipArchive;
    $zip->open($tmp);
    $out = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $out[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
    }
    $zip->close();
    unlink($tmp);
    ksort($out);

    return $out;
}

it('names files by the pattern, replaces bad characters, numbers duplicates and lists missing files', function () {
    (new BuildGameFilesZip($this->layout, $this->user, ['passport_scan_path']))->handle(app(GameFileExporter::class));

    expect(zipContents())->toBe([
        'BJF Hasan_passport.pdf' => 'ali-passport',
        'BJF Hasan_photo-2.jpg' => 'omar-photo',
        'BJF Hasan_photo.jpg' => 'ali-photo',
        'missing.txt' => "BJF-AT-0002 Omar Hasan: passport\n",
    ]);

    $notification = $this->user->notifications()->sole();
    expect(json_encode($notification->data, JSON_UNESCAPED_SLASHES))->toContain('Files ZIP ready')
        ->toContain('https://files.test/exports/');
    $log = Activity::where('description', 'game files ZIP exported')->sole();
    expect($log->causer_id)->toBe($this->user->id)->and($log->subject_id)->toBe($this->game->id)
        ->and($log->properties->only(['layout', 'people', 'files', 'missing'])->all())->toBe(['layout' => 'athlete', 'people' => 2, 'files' => 3, 'missing' => 1]);
});

it('includes WADA certificates only for users who may view medical data', function () {
    (new BuildGameFilesZip($this->layout, $this->user, ['wada_certificate_path']))->handle(app(GameFileExporter::class));
    expect(zipContents())->not->toHaveKey('BJF Hasan_wada.pdf')
        ->and(zipContents()['missing.txt'])->toStartWith("WADA certificates left out: they need \"View medical & health data\".\n");

    Storage::disk(config('filesystems.private'))->deleteDirectory('exports');
    $this->user->givePermissionTo(StaPermission::ViewMedical->value);
    (new BuildGameFilesZip($this->layout, $this->user->fresh(), ['wada_certificate_path']))->handle(app(GameFileExporter::class));
    expect(zipContents())->toHaveKey('BJF Hasan_wada.pdf');
});

it('makes safe, unique file names', function (string $pattern, array $expected) {
    $p = Person::factory()->make(['code' => 'BJF-AT-0001', 'first_name' => 'Ali', 'last_name' => 'Al/Hasan', 'passport_no' => 'BH1']);
    $used = [];
    $names = [
        BuildGameFilesZip::fileName($pattern, $p, $this->assoc, 'photo', 'jpg', $used),
        BuildGameFilesZip::fileName($pattern, $p, $this->assoc, 'photo', 'jpg', $used),
    ];
    expect($names)->toBe($expected);
})->with([
    'default' => ['{code}_{kind}', ['BJF-AT-0001_photo.jpg', 'BJF-AT-0001_photo-2.jpg']],
    'slash and colon' => ['{last_name}:{first_name}', ['Al_Hasan_Ali.jpg', 'Al_Hasan_Ali-2.jpg']],
    'passport' => ['{passport_no}', ['BH1.jpg', 'BH1-2.jpg']],
    'nothing left' => ['***', ['___.jpg', '___-2.jpg']],
]);

it('queues the ZIP from the game page for export_game_files users', function () {
    Bus::fake();
    actingInAdmin($this->user);
    livewire(GameExportFiles::class, ['record' => $this->game->getRouteKey()])
        ->callAction('zip', ['type' => 'athlete', 'columns' => ['passport_scan_path', 'cpr_front_path']])
        ->assertNotified('Building the files ZIP');

    Bus::assertDispatched(BuildGameFilesZip::class, fn (BuildGameFilesZip $job) => $job->layout->is($this->layout)
        && $job->user->is($this->user) && $job->columns === ['passport_scan_path', 'cpr_front_path']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/GameFilesZipTest.php`
Expected: FAIL with `Class "App\Jobs\BuildGameFilesZip" not found`.

- [ ] **Step 3: Database notifications**

`database/migrations/2026_11_13_000300_create_notifications_table.php` (Laravel's standard table):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
```

In `app/Providers/Filament/AdminPanelProvider.php`, add `->databaseNotifications()` after `->multiFactorAuthentication([...])`.

- [ ] **Step 4: Write the job**

`app/Jobs/BuildGameFilesZip.php`:

```php
<?php

namespace App\Jobs;

use App\Enums\PersonType;
use App\Models\Association;
use App\Models\ExportLayout;
use App\Models\Person;
use App\Models\User;
use App\Services\GameFiles\GameFileExporter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/** Spec 15.3: photos and optional documents of the included people, named by the layout's pattern, on the private disk. */
class BuildGameFilesZip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = 60;

    public const KINDS = ['photo_path' => 'photo', 'passport_scan_path' => 'passport', 'cpr_front_path' => 'cpr_front',
        'wada_certificate_path' => 'wada'];

    /** @param list<string> $columns optional document columns; the photo is always included */
    public function __construct(
        public ExportLayout $layout,
        public User $user,
        public array $columns = [],
        public ?int $associationId = null,
        public ?int $gameSportId = null,
    ) {}

    public function handle(GameFileExporter $exporter): void
    {
        $disk = Storage::disk(config('filesystems.private'));
        $game = $this->layout->game;
        $columns = array_values(array_intersect(array_keys(self::KINDS), ['photo_path', ...$this->columns]));
        $missing = [];
        if (in_array('wada_certificate_path', $columns, true) && ! Person::canViewMedical($this->user)) {
            $columns = array_values(array_diff($columns, ['wada_certificate_path']));
            $missing[] = 'WADA certificates left out: they need "View medical & health data".';
        }
        $people = $exporter->people($game, $this->layout->type, $this->associationId, $this->gameSportId);

        // Files are copied to a local temp dir first: ZipArchive::addFile reads them only on close(), and the disk may be S3.
        $dir = sys_get_temp_dir().'/zip-'.Str::uuid();
        File::ensureDirectoryExists($dir);
        $tmp = "{$dir}.zip";
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $used = [];
        $count = 0;
        foreach ($people as ['person' => $p, 'association' => $a]) {
            foreach ($columns as $column) {
                $kind = self::KINDS[$column];
                $file = $p->{$column};
                if (blank($file) || ! $disk->exists($file)) {
                    $missing[] = "{$p->code} ".($p->full_name ?: $p->fullName()).": {$kind}";

                    continue;
                }
                $local = $dir.'/'.(++$count);
                $in = $disk->readStream($file);
                $out = fopen($local, 'w');
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
                $zip->addFile($local, self::fileName($this->layout->zip_pattern, $p, $a, $kind, strtolower(pathinfo($file, PATHINFO_EXTENSION)), $used));
            }
        }
        if ($missing || $count === 0) {
            $zip->addFromString('missing.txt', implode("\n", $missing ?: ['No files.'])."\n");
        }
        $zip->close();

        $who = $this->layout->type === PersonType::Athlete ? 'athletes' : 'officials';
        $path = 'exports/'.Str::uuid().'/'.Str::slug($game->name)."-{$who}-files.zip";
        $stream = fopen($tmp, 'r');
        $disk->put($path, $stream);
        fclose($stream);
        File::deleteDirectory($dir);
        unlink($tmp);
        // ponytail: ZIPs stay under exports/ until removed; add a scheduled prune of exports/ older than a day if storage grows.

        activity()->performedOn($game)->causedBy($this->user)->event('exported')
            ->withProperties(['layout' => $this->layout->type->value, 'people' => $people->count(), 'files' => $count,
                'missing' => count($missing), 'documents' => $columns, 'association_id' => $this->associationId, 'game_sport_id' => $this->gameSportId])
            ->log('game files ZIP exported');

        Notification::make()->success()->title('Files ZIP ready')
            ->body("{$game->name}, {$who}: {$count} files. The link works for 5 minutes.")
            ->actions([Action::make('download')->label('Download ZIP')
                ->url($disk->temporaryUrl($path, now()->addMinutes(5)), shouldOpenInNewTab: true)])
            ->sendToDatabase($this->user);
    }

    /** @param array<string, true> $used lower-cased names already in the ZIP (duplicates get -2, -3, …) */
    public static function fileName(string $pattern, Person $p, Association $a, string $kind, string $ext, array &$used): string
    {
        $base = strtr($pattern, ['{code}' => (string) $p->code, '{passport_no}' => (string) $p->passport_no, '{last_name}' => (string) $p->last_name,
            '{first_name}' => (string) $p->first_name, '{association_code}' => (string) $a->code, '{kind}' => $kind]);
        $base = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]/u', '_', $base), ' .') ?: 'file';
        $name = "{$base}.{$ext}";
        for ($n = 2; isset($used[mb_strtolower($name)]); $n++) {
            $name = "{$base}-{$n}.{$ext}";
        }
        $used[mb_strtolower($name)] = true;

        return $name;
    }
}
```

- [ ] **Step 5: ZIP action on the page**

In `app/Filament/Resources/Games/Pages/GameExportFiles.php` add imports `use App\Jobs\BuildGameFilesZip;` and `use Filament\Forms\Components\CheckboxList;`, and add as the second entry of `getHeaderActions()` (after `download`):

```php
            Action::make('zip')->label('Download files ZIP')->icon('heroicon-o-archive-box-arrow-down')
                ->schema(fn () => [...$this->filterFields(), CheckboxList::make('columns')->label('Besides photos, include')
                    ->options(array_filter(['passport_scan_path' => 'Passport scans', 'cpr_front_path' => 'CPR front scans',
                        'wada_certificate_path' => Person::canViewMedical(auth()->user()) ? 'WADA certificates' : null]))])
                ->action(function (array $data) {
                    BuildGameFilesZip::dispatch(
                        ExportLayout::firstOrCreate(['game_id' => $this->record->id, 'type' => $data['type']]),
                        auth()->user(), array_values($data['columns'] ?? []),
                        filled($data['association_id'] ?? null) ? (int) $data['association_id'] : null,
                        filled($data['game_sport_id'] ?? null) ? (int) $data['game_sport_id'] : null);
                    Notification::make()->success()->title('Building the files ZIP')
                        ->body('You will be notified here with a download link when it is ready.')->send();
                }),
```

- [ ] **Step 6: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/GameFilesZipTest.php`
Expected: PASS.
Run: `$PHP vendor/bin/pest`
Expected: PASS (whole suite).

- [ ] **Step 7: Commit**

```bash
$PHP vendor/bin/pint
git add app/Jobs/BuildGameFilesZip.php database/migrations/2026_11_13_000300_create_notifications_table.php app/Providers/Filament/AdminPanelProvider.php app/Filament/Resources/Games/Pages/GameExportFiles.php tests/Feature/GameFilesZipTest.php
git commit -m "feat: queued photos and documents ZIP with naming pattern, missing.txt and a 5-minute link in admin notifications

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Spec coverage (section 15)

| Spec item | Task |
|---|---|
| 15.1 Extra fields in `/admin`, only Super Admin / `ManageMasterData` | 1 |
| Label, generated unique key, applies to, type (text ≤255, number, date, choice, yes/no), help text, required to be complete, active | 1 |
| No type change once values exist; deactivating hides and keeps values | 1, 2 |
| Values in `person_field_values`, reused across games | 1, 2 |
| "Additional information" section, portal and admin, active fields for the type | 2 |
| Validation by type; choice must be an option | 1, 2, 3 |
| Required fields join `missingFields()` and block Long List submit | 2 |
| Offered in the report builder's People and Entries subjects | 4 |
| Changes audited | 1 (definitions), 2 (values) |
| Excel import: one column per active field | 3 |
| Sensitive flag hides from STA without `view_medical` | 2, 4, 5, 6 |
| 15.2 Athlete and official layout per game; copy from another game | 5 |
| `export_game_files` permission, Super Admin always | 5 |
| Ordered columns: source (person, extra, entry, age at game start, association code/name, fixed text), heading, format | 5 |
| Formats for dates, gender, text, yes/no | 5 |
| xlsx or CSV (UTF-8, comma) | 6 |
| Short List if Phase 4 submitted, else submitted Long List; filters by association and sport | 6 |
| One row per athlete (events joined by "; ", default) or per event | 6 |
| "Download upload file", `{game slug}-{athletes|officials}.{ext}`, audited (who, when, game, layout, rows) | 6 |
| Passport/CPR allowed; medical and sensitive only with `view_medical`, left out with a note | 5, 6 |
| 15.3 ZIP of photos + optional passport, CPR front, WADA | 7 |
| Naming tokens, default `{code}_{kind}`, bad characters → `_`, duplicates `-2`, `-3` | 7 |
| `missing.txt` | 7 |
| Queued job on the private disk, in-app notification with a 5-minute link, audited, `export_game_files` | 7 |

---

## Amendments after Milestone 4.3 (2026-11-11) — binding over the tasks above
- Drop the `men_women` gender export format (spec 16.1: gender is always Male/Female); keep Male/Female and M/F.
- Game sport limit labels are "Max male" / "Max female". Export layouts and ZIPs are available only for published games (`games.published_at` not null).
- Every xlsx string cell is written as a literal OpenSpout StringCell; every CSV value starting with = + - @ is prefixed with an apostrophe (formula-injection guard).
