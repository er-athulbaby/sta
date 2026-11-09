# Milestone 1.1 – Registry Update and Passport Reader Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Update the athlete and team official registry to the field set the user approved on 2026-11-09. That covers:
- association and person codes, with code-based file names and view links
- CPR front/back/reader copies, visas and medical/health data
- phone country codes and the completeness check
- a "Read details from passport" action that reads the passport's machine-readable zone locally, with no AI service

**Architecture:** One schema migration extends `people`, and two new tables hold visas and injuries.
- **Person model:** generates its code from the association code. After every save it renames attachments to `{code}_{kind}.{ext}`. It also reports missing fields.
- **Forms:** the shared `PersonForm` and `PersonTable` grow new type-aware sections. Medical and health sections are gated by a new STA permission.
- **Passport reader:** a pure `MrzParser` handles ICAO 9303 TD3 parsing and check digits. A `TesseractMrzReader` turns an image or PDF into MRZ lines; it sits behind an interface so tests use a fake.

**Tech Stack:** PHP 8.5.12 (Herd `php85`), Laravel 12, Filament 4.16, Pest 4, Tesseract OCR and poppler `pdftoppm` (server binaries), GD.

**Spec:** `docs/superpowers/specs/2026-11-04-sports-entry-system-design.md`. Section 13 "Registry update v1.2" is binding here and overrides earlier sections. Scope doc v1.2: sections 4.11, 4.12 and 15 (decisions 13–20).

## Global Constraints

- **PHP:** always run `/c/Users/ababy/.config/herd/bin/php85/php.exe`, and Pest as `$PHP vendor/bin/pest`.
- **Codes:**
  - The association code is 2–10 characters `[A-Z0-9]`, unique, and required in the admin form.
  - Person codes are `{ASSOC}-AT-0001` for athletes and `{ASSOC}-TO-0001` for officials. They run per association and type, are never reused, are unique, and never change once assigned.
- **Attachments:**
  - Stored on `config('filesystems.private')` as `{dir}/{code}_{kind}.{ext}`, where kind is one of `photo`, `passport`, `cpr_front`, `cpr_back`, `cpr_reader`, `wada` or `visa_{visaId}`.
  - Each one has a View link: a 5-minute `temporaryUrl`.
  - `preventFilePathTampering()` stays on globally (Milestone 1 fix C1).
- **Phone fields:** every phone field has a country code select, defaulting to `+973`. "WhatsApp same as mobile" copies the mobile number on save.
- **Completeness:** the save minimum is unchanged from Milestone 1, so the Excel import keeps working. Everything else is reported by `Person::missingFields()` with a Complete/Incomplete badge.
- **Medical and health fields:**
  - Encrypted at rest and excluded from the audit log, which records only a `medical_changed` marker.
  - In `/admin`, visible only with `view_medical` (or as Super Admin). In the portal, always visible to the person's own association.
- **Not stored on the person:** travel insurance and vaccinations. They belong per game, in Milestone 3.
- **MRZ reader:**
  - Local only (Tesseract plus `pdftoppm`), with no external service.
  - A field whose check digit fails is never filled.
  - Nothing is saved automatically.
- **Unchanged rules:** never delete associations, sports, events or clothing items. Tenant isolation (BR-01) is unchanged.
- **Commits:** end every commit with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Never commit `.superpowers/` or `~$*`.

---

## File Structure

```
config/countries.php                         # ISO 3166-1 alpha-3 => English name (+ ICAO extras), used by forms and MRZ
config/phone_codes.php                       # dialling codes for the country-code select, '+973' first
config/services.php (modify)                 # tesseract + pdftoppm binary paths
database/migrations/2026_11_09_000300_add_code_to_associations_table.php
database/migrations/2026_11_09_000400_extend_people_for_registry_v12.php
database/migrations/2026_11_09_000500_create_person_visas_table.php
database/migrations/2026_11_09_000600_create_person_injuries_table.php
database/seeders/ClothingItemSeeder.php      # T-Shirt, Suit, Shoes
app/Enums/StaPermission.php (modify)         # + ViewMedical
app/Models/Association.php (modify)          # code
app/Models/Person.php (modify)               # code, renaming, missingFields, medical, new fields
app/Models/PersonVisa.php, PersonInjury.php
app/Models/Concerns/RenamesAttachments.php   # shared rename-after-save logic
app/Services/Mrz/MrzParser.php, MrzResult.php, MrzReader.php (interface), TesseractMrzReader.php, PassportScanFile.php
app/Filament/Shared/PersonForm.php (modify), PersonTable.php (modify), PhoneField.php, AttachmentActions.php
app/Filament/Shared/ReadPassportAction.php
app/Filament/Resources/Associations/Schemas/AssociationForm.php (modify)
app/Services/PeopleImporter.php, app/Exports/PeopleTemplateExport.php (modify)
tests/Feature/{AssociationCodeTest, PersonCodeAndFilesTest, PersonCompletenessTest, MedicalAccessTest, PersonFormV12Test, MrzParserTest, ReadPassportActionTest}.php
```

---

### Task 1: Association code

**Files:**
- Create: `database/migrations/2026_11_09_000300_add_code_to_associations_table.php`, `tests/Feature/AssociationCodeTest.php`
- Modify: `app/Models/Association.php`, `database/factories/AssociationFactory.php`, `app/Filament/Resources/Associations/Schemas/AssociationForm.php`, `app/Filament/Resources/Associations/Tables/AssociationsTable.php`

**Interfaces:**
- Produces: `associations.code` (string 10, unique, not null after backfill). `Association::$fillable` includes `code`. The factory gives a unique code.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Filament\Resources\Associations\Pages\CreateAssociation;
use App\Models\Association;
use App\Models\User;
use function Pest\Livewire\livewire;

it('requires a unique upper-case association code', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    Association::factory()->create(['code' => 'BWF']);

    livewire(CreateAssociation::class)->fillForm(['name' => 'X', 'email' => 'x@x.bh', 'code' => 'BWF'])->call('create')
        ->assertHasFormErrors(['code']);
    livewire(CreateAssociation::class)->fillForm(['name' => 'X', 'email' => 'x@x.bh', 'code' => 'bad code!'])->call('create')
        ->assertHasFormErrors(['code']);
    livewire(CreateAssociation::class)->fillForm(['name' => 'Judo', 'email' => 'j@x.bh', 'code' => 'bjf'])->call('create')
        ->assertHasNoFormErrors();

    expect(Association::firstWhere('name', 'Judo')->code)->toBe('BJF');
});
```

- [ ] **Step 2: Run it**

Run `$PHP vendor/bin/pest tests/Feature/AssociationCodeTest.php`. Expected: FAIL, because there is no `code` field or column.

- [ ] **Step 3: Implement**

Migration `up()`. It backfills existing rows so the column can be not-null:
```php
Schema::table('associations', function (Blueprint $table) {
    $table->string('code', 10)->nullable()->after('name');
});
DB::table('associations')->whereNull('code')->orderBy('id')->each(
    fn ($a) => DB::table('associations')->where('id', $a->id)->update(['code' => 'A'.$a->id])
);
Schema::table('associations', function (Blueprint $table) {
    $table->string('code', 10)->nullable(false)->unique()->change();
});
```
`down()`: `$table->dropUnique(['code']); $table->dropColumn('code');`

`Association`: add `'code'` to `$fillable`, plus a mutator that upper-cases the value:
```php
protected function code(): \Illuminate\Database\Eloquent\Casts\Attribute
{
    return \Illuminate\Database\Eloquent\Casts\Attribute::make(set: fn (?string $v) => $v === null ? null : strtoupper(trim($v)));
}
```
Factory `definition()`: add `'code' => strtoupper(fake()->unique()->bothify('??##'))`.

`AssociationForm::configure()`: after `name`, add:
```php
TextInput::make('code')->label('Association code')->required()->maxLength(10)
    ->helperText('Used in athlete and official codes, e.g. BWF → BWF-AT-0001')
    ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state)))
    ->rule('regex:/^[A-Za-z0-9]{2,10}$/')
    ->unique(ignoreRecord: true),
```
`AssociationsTable`: add `TextColumn::make('code')->searchable()->sortable()` before `name`.

- [ ] **Step 4: Run the test and the full suite**

Run `$PHP vendor/bin/pest tests/Feature/AssociationCodeTest.php`, then `$PHP vendor/bin/pest`. Expected: all pass. Fix any factory or test that creates associations through `Association::create([...])` without a code by adding `'code' => 'T1'`-style values.

- [ ] **Step 5: Commit**

`git add -A && git commit -m "feat: association code"`

---

### Task 2: Registry schema, person codes and new models

**Files:**
- Create: migrations `2026_11_09_000400_extend_people_for_registry_v12.php`, `..._000500_create_person_visas_table.php`, `..._000600_create_person_injuries_table.php`
- Create: `app/Models/PersonVisa.php`, `app/Models/PersonInjury.php`, `config/countries.php`, `config/phone_codes.php`, `tests/Feature/PersonCodeAndFilesTest.php` (code tests only in this task)
- Modify: `app/Models/Person.php`, `database/factories/PersonFactory.php`, `app/Enums/StaPermission.php`

**Interfaces:**
- Produces:
  - **Person columns** (Step 3), plus:
    - `Person::visas(): HasMany` and `Person::injuries(): HasMany`
    - `Person::ATHLETE_ONLY` and `Person::OFFICIAL_ONLY` (column lists)
    - `Person::MEDICAL` (encrypted, unlogged columns)
  - **Codes:** `people.code` and `people.seq`, auto-assigned on create.
  - **Config:** `config('countries')` (alpha-3 → name) and `config('phone_codes')` (code → label).
  - **Permission:** `StaPermission::ViewMedical`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/PersonCodeAndFilesTest.php`:
```php
<?php

use App\Enums\PersonType;
use App\Models\Association;
use App\Models\Person;

it('numbers athletes and officials per association and type', function () {
    $bwf = Association::factory()->create(['code' => 'BWF']);
    $bjf = Association::factory()->create(['code' => 'BJF']);

    $a1 = Person::factory()->create(['association_id' => $bwf->id]);
    $a2 = Person::factory()->create(['association_id' => $bwf->id]);
    $o1 = Person::factory()->official()->create(['association_id' => $bwf->id]);
    $j1 = Person::factory()->create(['association_id' => $bjf->id]);

    expect([$a1->code, $a2->code, $o1->code, $j1->code])->toBe(['BWF-AT-0001', 'BWF-AT-0002', 'BWF-TO-0001', 'BJF-AT-0001']);
});

it('never reuses a number after delete and keeps the code on update', function () {
    $assoc = Association::factory()->create(['code' => 'BWF']);
    $a1 = Person::factory()->create(['association_id' => $assoc->id]);
    Person::factory()->create(['association_id' => $assoc->id])->delete();
    $a3 = Person::factory()->create(['association_id' => $assoc->id]);
    $a1->update(['first_name' => 'Changed']);

    expect($a3->code)->toBe('BWF-AT-0003')->and($a1->fresh()->code)->toBe('BWF-AT-0001');
});

it('provides countries and phone codes', function () {
    expect(config('countries.BHR'))->toBe('Bahrain')
        ->and(count(config('countries')))->toBeGreaterThanOrEqual(249)
        ->and(array_key_first(config('phone_codes')))->toBe('+973');
});
```

- [ ] **Step 2: Run it**

Run `$PHP vendor/bin/pest tests/Feature/PersonCodeAndFilesTest.php`. Expected: FAIL, because `code` is null and the config is missing.

- [ ] **Step 3: Implement the migrations**

`2026_11_09_000400_extend_people_for_registry_v12.php`, `up()`:
```php
Schema::table('people', function (Blueprint $table) {
    $table->renameColumn('cpr_scan_path', 'cpr_front_path');
});
Schema::table('people', function (Blueprint $table) {
    $table->string('code', 30)->nullable()->unique()->after('id');
    $table->unsignedInteger('seq')->nullable()->after('code');
    $table->string('full_name')->nullable()->after('type');
    $table->string('passport_issuing_country', 100)->nullable()->after('passport_no_hash');
    $table->date('passport_issue_date')->nullable()->after('passport_issuing_country');
    $table->string('cpr_back_path')->nullable()->after('cpr_front_path');
    $table->string('cpr_reader_path')->nullable()->after('cpr_back_path');
    $table->string('wada_certificate_path')->nullable();
    $table->string('fit', 10)->nullable();
    $table->unsignedSmallInteger('chest_cm')->nullable();
    $table->unsignedSmallInteger('waist_cm')->nullable();
    $table->string('phone_cc', 6)->default('+973');
    $table->boolean('whatsapp_same')->default(false);
    $table->string('whatsapp_cc', 6)->default('+973');
    $table->string('whatsapp', 50)->nullable();
    $table->string('address')->nullable();
    $table->string('emergency_name')->nullable();
    $table->string('emergency_relationship', 100)->nullable();
    $table->string('emergency_phone_cc', 6)->default('+973');
    $table->string('emergency_phone', 50)->nullable();
    $table->string('guardian_name')->nullable();
    $table->string('guardian_relationship', 30)->nullable();
    $table->string('guardian_phone_cc', 6)->default('+973');
    $table->string('guardian_phone', 50)->nullable();
    $table->string('guardian_email')->nullable();
    $table->string('languages')->nullable();
    $table->text('blood_group')->nullable();
    $table->date('medical_clearance_expiry')->nullable();
    $table->text('insurance_policy_no')->nullable();
    $table->text('allergies')->nullable();
    $table->text('chronic_conditions')->nullable();
    $table->text('medication')->nullable();
    $table->string('meal_requirement', 30)->nullable();
    foreach (['consent_data', 'consent_media', 'consent_medical', 'decl_conduct', 'decl_antidoping', 'decl_data_sharing'] as $c) {
        $table->boolean($c)->default(false);
        $table->timestamp($c.'_at')->nullable();
    }
    $table->unique(['association_id', 'type', 'seq']);
});
```
Backfill codes for existing rows in the same `up()`, after the schema change:
```php
$counters = [];
DB::table('people')->orderBy('id')->each(function ($p) use (&$counters) {
    $assoc = DB::table('associations')->where('id', $p->association_id)->value('code');
    $key = $p->association_id.'|'.$p->type;
    $seq = $counters[$key] = ($counters[$key] ?? 0) + 1;
    DB::table('people')->where('id', $p->id)->update([
        'seq' => $seq,
        'code' => sprintf('%s-%s-%04d', $assoc, $p->type === 'official' ? 'TO' : 'AT', $seq),
    ]);
});
```
Write a `down()` that drops the added columns and the unique index, and renames `cpr_front_path` back to `cpr_scan_path`.

`..._000500_create_person_visas_table.php`:
```php
Schema::create('person_visas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
    $table->string('country', 100);
    $table->string('status', 30);
    $table->string('visa_no', 50)->nullable();
    $table->date('valid_until')->nullable();
    $table->string('copy_path')->nullable();
    $table->timestamps();
});
```
`..._000600_create_person_injuries_table.php`:
```php
Schema::create('person_injuries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
    $table->date('injury_date');
    $table->text('description');
    $table->string('status', 30);
    $table->timestamps();
});
```

- [ ] **Step 4: Implement the config files**

`config/countries.php` returns every ISO 3166-1 alpha-3 code mapped to its English short name, in alphabetical code order, from `'ABW' => 'Aruba'` to `'ZWE' => 'Zimbabwe'`. Use the official 249-entry list, including `'BHR' => 'Bahrain'`, `'GBR' => 'United Kingdom'`, `'USA' => 'United States'`, `'KOR' => 'South Korea'` and `'PRK' => 'North Korea'`. Then append these ICAO 9303 MRZ extras:
```php
'D' => 'Germany', 'GBD' => 'United Kingdom', 'GBN' => 'United Kingdom', 'GBO' => 'United Kingdom', 'GBS' => 'United Kingdom', 'GBP' => 'United Kingdom',
'RKS' => 'Kosovo', 'UNO' => 'United Nations', 'XXA' => 'Stateless', 'XXB' => 'Refugee', 'XXC' => 'Refugee', 'XXX' => 'Unspecified',
```
`config/phone_codes.php`. Bahrain comes first, then the Gulf states, then common ones:
```php
<?php

return [
    '+973' => 'Bahrain (+973)', '+966' => 'Saudi Arabia (+966)', '+971' => 'UAE (+971)', '+974' => 'Qatar (+974)',
    '+965' => 'Kuwait (+965)', '+968' => 'Oman (+968)', '+962' => 'Jordan (+962)', '+20' => 'Egypt (+20)',
    '+961' => 'Lebanon (+961)', '+964' => 'Iraq (+964)', '+98' => 'Iran (+98)', '+90' => 'Turkey (+90)',
    '+91' => 'India (+91)', '+92' => 'Pakistan (+92)', '+94' => 'Sri Lanka (+94)', '+880' => 'Bangladesh (+880)',
    '+63' => 'Philippines (+63)', '+62' => 'Indonesia (+62)', '+60' => 'Malaysia (+60)', '+86' => 'China (+86)',
    '+81' => 'Japan (+81)', '+82' => 'South Korea (+82)', '+7' => 'Russia / Kazakhstan (+7)', '+44' => 'United Kingdom (+44)',
    '+1' => 'USA / Canada (+1)', '+33' => 'France (+33)', '+49' => 'Germany (+49)', '+39' => 'Italy (+39)',
    '+34' => 'Spain (+34)', '+61' => 'Australia (+61)', '+27' => 'South Africa (+27)', '+254' => 'Kenya (+254)',
    '+251' => 'Ethiopia (+251)', '+212' => 'Morocco (+212)', '+216' => 'Tunisia (+216)', '+213' => 'Algeria (+213)',
    '+55' => 'Brazil (+55)', '+380' => 'Ukraine (+380)', '+375' => 'Belarus (+375)', '+998' => 'Uzbekistan (+998)',
];
```

- [ ] **Step 5: Implement the models**

Add `ViewMedical = 'view_medical'` to `StaPermission`, with the label `'View medical & health data'`. `PermissionSeeder` already loops over `cases()`.

`app/Models/PersonVisa.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonVisa extends Model
{
    public const STATUSES = ['Not started', 'Applied', 'Approved', 'Rejected', 'Visa on arrival', 'e-Visa'];

    protected $fillable = ['person_id', 'country', 'status', 'visa_no', 'valid_until', 'copy_path'];

    protected function casts(): array { return ['valid_until' => 'date']; }

    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
}
```
`app/Models/PersonInjury.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonInjury extends Model
{
    public const STATUSES = ['Recovered', 'In treatment', 'Ongoing'];

    protected $fillable = ['person_id', 'injury_date', 'description', 'status'];

    protected function casts(): array { return ['injury_date' => 'date', 'description' => 'encrypted']; }

    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
}
```
`Person` changes:
```php
public const FILE_COLUMNS = ['photo_path' => 'photo', 'passport_scan_path' => 'passport', 'cpr_front_path' => 'cpr_front',
    'cpr_back_path' => 'cpr_back', 'cpr_reader_path' => 'cpr_reader', 'wada_certificate_path' => 'wada'];

public const ATHLETE_ONLY = ['address', 'guardian_name', 'guardian_relationship', 'guardian_phone_cc', 'guardian_phone', 'guardian_email',
    'wada_certificate_path', 'medical_clearance_expiry', 'insurance_policy_no', 'consent_data', 'consent_media', 'consent_medical'];

public const OFFICIAL_ONLY = ['function', 'languages', 'meal_requirement', 'decl_conduct', 'decl_antidoping', 'decl_data_sharing'];

public const MEDICAL = ['blood_group', 'insurance_policy_no', 'allergies', 'chronic_conditions', 'medication'];

public const CONSENTS = ['consent_data', 'consent_media', 'consent_medical', 'decl_conduct', 'decl_antidoping', 'decl_data_sharing'];
```
- **`$fillable`:** remove `cpr_scan_path`. Add every new column from Step 3 except `code`, `seq` and the `*_at` columns, which are system-set.
- **Casts:**
  - `'encrypted'` for each column in `MEDICAL`
  - `'date'` for `passport_issue_date` and `medical_clearance_expiry`
  - `'boolean'` for `whatsapp_same` and each column in `CONSENTS`
  - `'datetime'` for each `*_at` column
- **`deleted` hook:** change it to delete `array_keys(self::FILE_COLUMNS)` paths and each visa's `copy_path`. Visas cascade in the DB, but their files must be removed first, so delete `$person->visas()->pluck('copy_path')->filter()` before the row goes.

Add a `creating` hook for the code, inside `booted()`. Deleted people are hard-deleted, so `max(seq)` would reuse their numbers. The counter therefore lives on the association, which is row-locked while the number is taken:
```php
static::creating(function (Person $person) {
    DB::transaction(function () use ($person) {
        $association = Association::query()->whereKey($person->association_id)->lockForUpdate()->firstOrFail();
        $column = $person->type === PersonType::Official ? 'last_official_seq' : 'last_athlete_seq';
        $seq = $association->{$column} + 1;
        $association->forceFill([$column => $seq])->saveQuietly();
        $person->seq = $seq;
        $person->code = sprintf('%s-%s-%04d', $association->code, $person->type === PersonType::Official ? 'TO' : 'AT', $seq);
    });
});
```
The counter columns are added in the Step 3 migration, next to the person columns:
```php
Schema::table('associations', function (Blueprint $table) {
    $table->unsignedInteger('last_athlete_seq')->default(0);
    $table->unsignedInteger('last_official_seq')->default(0);
});
```
After the backfill loop, set them from `$counters`, with `DB::table('associations')->where('id', $assocId)->update([$type === 'official' ? 'last_official_seq' : 'last_athlete_seq' => $seq])` for each key. `down()` drops both columns.

In the existing `saving` hook, replace the official-only loop with:
```php
$clear = $person->type === PersonType::Athlete ? self::OFFICIAL_ONLY : self::ATHLETE_ONLY;
foreach ($clear as $column) {
    $person->{$column} = in_array($column, self::CONSENTS, true) ? false : null;
}
if ($person->whatsapp_same) {
    $person->whatsapp_cc = $person->phone_cc;
    $person->whatsapp = $person->phone;
}
foreach (self::CONSENTS as $column) {
    if ($person->isDirty($column)) {
        $person->{$column.'_at'} = $person->{$column} ? now() : null;
    }
}
```
Add the relations:
```php
public function visas(): HasMany { return $this->hasMany(PersonVisa::class); }
public function injuries(): HasMany { return $this->hasMany(PersonInjury::class); }
```
Update every place that referenced `cpr_scan_path`: PersonForm, tests and the importer. Use `grep -rn cpr_scan_path app tests` and rename each to `cpr_front_path`.

- [ ] **Step 6: Run the tests**

Run `$PHP vendor/bin/pest tests/Feature/PersonCodeAndFilesTest.php`, then the full suite. Expected: all pass. Fix the renamed column in existing tests.

- [ ] **Step 7: Commit**

`git add -A && git commit -m "feat: registry v1.2 schema, person codes, visas and injuries"`

---

### Task 3: Attachment naming and view links

**Files:**
- Create: `app/Models/Concerns/RenamesAttachments.php`, `app/Filament/Shared/AttachmentActions.php`
- Modify: `app/Models/Person.php`, `app/Models/PersonVisa.php`, `tests/Feature/PersonCodeAndFilesTest.php`

**Interfaces:**
- Produces:
  - Trait `RenamesAttachments`. It requires `attachmentColumns(): array<string column, string kind>` and `attachmentPrefix(): string`.
  - `AttachmentActions::for(): array<Filament\Actions\Action>`, for a person table row.

- [ ] **Step 1: Write the failing tests**

Append to `PersonCodeAndFilesTest.php`:
```php
use Illuminate\Support\Facades\Storage;

it('renames uploads to code_kind and replaces the old file', function () {
    Storage::fake(config('filesystems.private'));
    $disk = Storage::disk(config('filesystems.private'));
    $assoc = Association::factory()->create(['code' => 'BWF']);
    $disk->put('scans/tmp123.pdf', 'v1');

    $p = Person::factory()->create(['association_id' => $assoc->id, 'passport_scan_path' => 'scans/tmp123.pdf']);
    expect($p->fresh()->passport_scan_path)->toBe('scans/BWF-AT-0001_passport.pdf');
    $disk->assertExists('scans/BWF-AT-0001_passport.pdf');
    $disk->assertMissing('scans/tmp123.pdf');

    $disk->put('scans/tmp456.jpg', 'v2');
    $p->fresh()->update(['passport_scan_path' => 'scans/tmp456.jpg']);
    expect($p->fresh()->passport_scan_path)->toBe('scans/BWF-AT-0001_passport.jpg');
    $disk->assertMissing('scans/BWF-AT-0001_passport.pdf');
    $disk->assertExists('scans/BWF-AT-0001_passport.jpg');
});

it('names visa copies after the person code and visa id', function () {
    Storage::fake(config('filesystems.private'));
    $disk = Storage::disk(config('filesystems.private'));
    $assoc = Association::factory()->create(['code' => 'BJF']);
    $official = Person::factory()->official()->create(['association_id' => $assoc->id]);
    $disk->put('visas/tmp.pdf', 'x');

    $visa = $official->visas()->create(['country' => 'Japan', 'status' => 'Approved', 'copy_path' => 'visas/tmp.pdf']);
    expect($visa->fresh()->copy_path)->toBe("visas/BJF-TO-0001_visa_{$visa->id}.pdf");
});
```

- [ ] **Step 2: Run them**

Expected: FAIL, because the paths are not renamed.

- [ ] **Step 3: Implement the trait**

`app/Models/Concerns/RenamesAttachments.php`:
```php
<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/** After save, move each changed attachment to {dir}/{prefix}_{kind}.{ext}; the previous file is deleted. */
trait RenamesAttachments
{
    abstract protected function attachmentColumns(): array;

    abstract protected function attachmentPrefix(): string;

    /** @var array<string, ?string> paths before this save, captured in `saving` (getOriginal() is already synced in `saved`) */
    public array $attachmentOldPaths = [];

    public static function bootRenamesAttachments(): void
    {
        static::saving(function (self $model) {
            foreach (array_keys($model->attachmentColumns()) as $column) {
                $model->attachmentOldPaths[$column] = $model->getOriginal($column);
            }
        });

        static::saved(function (self $model) {
            $disk = Storage::disk(config('filesystems.private'));
            $updates = [];
            foreach ($model->attachmentColumns() as $column => $kind) {
                $path = $model->{$column};
                $old = $model->attachmentOldPaths[$column] ?? null;
                if (! $model->wasChanged($column) && ! $model->wasRecentlyCreated) {
                    continue;
                }
                if (blank($path)) {
                    if ($old) { $disk->delete($old); }
                    continue;
                }
                if (! $disk->exists($path)) {
                    continue; // e.g. factory paths with no file behind them
                }
                $target = trim(dirname($path), '.\\/').'/'.$model->attachmentPrefix().'_'.$kind.'.'.strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if ($old && $old !== $path && $old !== $target) { $disk->delete($old); }
                if ($path !== $target) {
                    $disk->delete($target);
                    $disk->move($path, $target);
                    $updates[$column] = $target;
                }
            }
            if ($updates) {
                $model->forceFill($updates)->saveQuietly();
            }
        });
    }
}
```
`Person`: add `use RenamesAttachments;` and:
```php
protected function attachmentColumns(): array { return self::FILE_COLUMNS; }
protected function attachmentPrefix(): string { return $this->code; }
```
`PersonVisa`: add `use RenamesAttachments;`. Then use `attachmentColumns()` returning `['copy_path' => 'visa_'.$this->id]`, and `attachmentPrefix()` returning `$this->person->code`.

- [ ] **Step 4: Write the view actions**

`app/Filament/Shared/AttachmentActions.php`:
```php
<?php

namespace App\Filament\Shared;

use App\Models\Person;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Illuminate\Support\Facades\Storage;

final class AttachmentActions
{
    public const LABELS = ['photo_path' => 'Photo', 'passport_scan_path' => 'Passport', 'cpr_front_path' => 'CPR front',
        'cpr_back_path' => 'CPR back', 'cpr_reader_path' => 'CPR reader copy', 'wada_certificate_path' => 'WADA certificate'];

    public static function group(): ActionGroup
    {
        return ActionGroup::make(collect(self::LABELS)->map(fn (string $label, string $column) => Action::make('view_'.$column)
            ->label('View '.$label)->icon('heroicon-o-eye')
            ->visible(fn (Person $record) => filled($record->{$column}))
            ->url(fn (Person $record) => self::url($record->{$column}), shouldOpenInNewTab: true))->values()->all())
            ->label('Attachments')->icon('heroicon-o-paper-clip');
    }

    public static function url(string $path): string
    {
        return Storage::disk(config('filesystems.private'))->temporaryUrl($path, now()->addMinutes(5));
    }
}
```
In `PersonForm::upload()`, add `->openable()->downloadable()` so the form shows the view and download buttons.

- [ ] **Step 5: Run the tests and commit**

Run `$PHP vendor/bin/pest tests/Feature/PersonCodeAndFilesTest.php`, then the full suite. Expected: all pass.

`git add -A && git commit -m "feat: code-based attachment names and view links"`

---

### Task 4: Completeness

**Files:**
- Create: `tests/Feature/PersonCompletenessTest.php`
- Modify: `app/Models/Person.php`

**Interfaces:**
- Produces:
  - `Person::REQUIRED_FOR_COMPLETE`, of type `array<'athlete'|'official', array<column, label>>`.
  - `Person::missingFields(): array<int,string>` (labels).
  - `Person::isComplete(): bool`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Person;

it('lists what an athlete still needs', function () {
    $p = Person::factory()->create(['full_name' => null, 'photo_path' => null, 'passport_issuing_country' => null,
        'passport_issue_date' => null, 'passport_scan_path' => null, 'cpr_no' => null]);

    expect($p->missingFields())->toBe(['Full name', 'Photo', 'Passport issuing country', 'Passport issue date', 'Passport scan', 'CPR number'])
        ->and($p->isComplete())->toBeFalse();
});

it('lists what an official still needs and passes when complete', function () {
    $o = Person::factory()->official()->create(['whatsapp' => null, 'whatsapp_same' => false, 'emergency_name' => null]);
    expect($o->missingFields())->toContain('WhatsApp')->toContain('Emergency contact name');

    $full = Person::factory()->official()->complete()->create();
    expect($full->missingFields())->toBe([])->and($full->isComplete())->toBeTrue();
});
```

- [ ] **Step 2: Run it**

Expected: FAIL, because the methods are undefined.

- [ ] **Step 3: Implement**

In `Person`:
```php
public const REQUIRED_FOR_COMPLETE = [
    'athlete' => ['full_name' => 'Full name', 'photo_path' => 'Photo', 'passport_issuing_country' => 'Passport issuing country',
        'passport_issue_date' => 'Passport issue date', 'passport_scan_path' => 'Passport scan', 'cpr_no' => 'CPR number'],
    'official' => ['full_name' => 'Full name', 'photo_path' => 'Photo', 'passport_issuing_country' => 'Passport issuing country',
        'passport_issue_date' => 'Passport issue date', 'passport_scan_path' => 'Passport scan', 'phone' => 'Mobile',
        'whatsapp' => 'WhatsApp', 'email' => 'Email', 'emergency_name' => 'Emergency contact name',
        'emergency_relationship' => 'Emergency contact relationship', 'emergency_phone' => 'Emergency phone'],
];

/** @return array<int, string> */
public function missingFields(): array
{
    return collect(self::REQUIRED_FOR_COMPLETE[$this->type->value])
        ->filter(fn (string $label, string $column) => blank($this->{$column}))->values()->all();
}

public function isComplete(): bool
{
    return $this->missingFields() === [];
}
```
In `PersonFactory`, add a `complete()` state:
```php
public function complete(): static
{
    return $this->state(fn () => ['full_name' => 'Test Person', 'photo_path' => 'photos/x.jpg', 'passport_issuing_country' => 'Bahrain',
        'passport_issue_date' => now()->subYear()->toDateString(), 'passport_scan_path' => 'scans/x.pdf', 'phone' => '33000000',
        'whatsapp_same' => true, 'email' => fake()->safeEmail(), 'emergency_name' => 'Kin', 'emergency_relationship' => 'Brother',
        'emergency_phone' => '33111111']);
}
```
In the athlete default `definition()`, keep `phone`, `email` etc. null, because athletes don't need them.

The `complete()` state uses paths with no file behind them; the Task 3 rename hook skips paths that don't exist on the disk, so they stay as given.

- [ ] **Step 4: Run the tests and commit**

Run `$PHP vendor/bin/pest tests/Feature/PersonCompletenessTest.php`, then the full suite.

`git add -A && git commit -m "feat: person completeness check"`

---

### Task 5: Medical and health data access and audit

**Files:**
- Create: `tests/Feature/MedicalAccessTest.php`
- Modify: `app/Models/Person.php` (activity log options)

**Interfaces:**
- Consumes: `Person::MEDICAL`, `StaPermission::ViewMedical`.
- Produces: `Person::canViewMedical(?User $user): bool`, a static helper the forms use.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\StaPermission;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

it('encrypts medical fields and never logs them', function () {
    $p = Person::factory()->create(['allergies' => 'Peanuts', 'blood_group' => 'O+']);
    $raw = DB::table('people')->where('id', $p->id)->first();
    expect($raw->allergies)->not->toContain('Peanuts');

    Person::query()->findOrFail($p->id)->update(['allergies' => 'Penicillin']);
    $log = Activity::where('subject_type', Person::class)->where('event', 'updated')->latest('id')->first();
    expect(json_encode($log->properties))->not->toContain('Penicillin')->not->toContain('Peanuts')
        ->and($log->properties['medical_changed'] ?? null)->toBeTrue();
});

it('decides who may see medical data', function () {
    $assocUser = User::factory()->assocEditor()->create();
    $sta = User::factory()->sta()->create();
    $medic = User::factory()->sta()->create();
    $medic->givePermissionTo(StaPermission::ViewMedical->value);

    expect(Person::canViewMedical($assocUser))->toBeTrue()
        ->and(Person::canViewMedical($sta))->toBeFalse()
        ->and(Person::canViewMedical($medic))->toBeTrue()
        ->and(Person::canViewMedical(User::factory()->superAdmin()->create()))->toBeTrue();
});
```

- [ ] **Step 2: Run it**

Expected: FAIL.

- [ ] **Step 3: Implement**

In `getActivitylogOptions()`: `->logExcept(['passport_no', 'cpr_no', ...self::MEDICAL])`.

In `tapActivity`, after the documents block:
```php
$medicalChanged = collect(self::MEDICAL)->contains(fn ($c) => $this->wasChanged($c));
if ($eventName === 'updated' && $medicalChanged) {
    $activity->properties = $activity->properties->put('medical_changed', true);
}
```
Add:
```php
public static function canViewMedical(?User $user): bool
{
    return $user !== null && ($user->role->isAssociationRole() || $user->can(\App\Enums\StaPermission::ViewMedical->value));
}
```
**Important:** an encrypted cast compares decrypted values, so `wasChanged` works.

- [ ] **Step 4: Run the tests and commit**

Run `$PHP vendor/bin/pest tests/Feature/MedicalAccessTest.php`, then the full suite.

`git add -A && git commit -m "feat: medical data encryption, audit exclusion and view permission"`

---

### Task 6: Person form v1.2

**Files:**
- Create: `app/Filament/Shared/PhoneField.php`, `tests/Feature/PersonFormV12Test.php`
- Modify: `app/Filament/Shared/PersonForm.php`

**Interfaces:**
- Consumes: everything from Tasks 2–5. `PersonForm::FUNCTIONS` (expanded) is also consumed by `PeopleImporter`.
- Produces: `PhoneField::make(string $prefix, string $label): Group`. This is a country code select plus a number input. The columns are `{prefix}_cc` and `{prefix}`, with the mobile prefix being `phone`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Enums\StaPermission;
use App\Filament\Portal\Resources\People\Pages\CreatePerson;
use App\Filament\Resources\People\Pages\EditPerson as AdminEditPerson;
use App\Models\Association;
use App\Models\Person;
use App\Models\User;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->assoc = Association::factory()->create(['code' => 'BWF']);
});

function v12Athlete(array $o = []): array
{
    return array_merge(['type' => 'athlete', 'first_name' => 'Noor', 'last_name' => 'Alsayed', 'full_name' => 'Noor Khalid Ahmed Alsayed',
        'dob' => '2006-05-27', 'gender' => 'female', 'nationality' => 'Bahrain', 'passport_no' => 'BH7777777',
        'passport_expiry' => now()->addYears(3)->toDateString(), 'passport_issuing_country' => 'Bahrain',
        'passport_issue_date' => now()->subYears(2)->toDateString(), 'phone_cc' => '+973', 'phone' => '33445566',
        'whatsapp_same' => true, 'guardian_name' => 'Khalid', 'consent_data' => true], $o);
}

it('saves the new athlete fields and copies WhatsApp from mobile', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    livewire(CreatePerson::class)->fillForm(v12Athlete())->call('create')->assertHasNoFormErrors();

    $p = Person::sole();
    expect($p->code)->toBe('BWF-AT-0001')->and($p->whatsapp)->toBe('33445566')->and($p->whatsapp_cc)->toBe('+973')
        ->and($p->guardian_name)->toBe('Khalid')->and($p->consent_data_at)->not->toBeNull();
});

it('defaults phone country codes to +973', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    livewire(CreatePerson::class)->assertSchemaStateSet(['phone_cc' => '+973', 'emergency_phone_cc' => '+973']);
});

it('hides medical fields in admin without view_medical', function () {
    $person = Person::factory()->create(['association_id' => $this->assoc->id]);
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ManagePeople->value);
    actingInAdmin($sta);
    livewire(AdminEditPerson::class, ['record' => $person->getRouteKey()])->assertFormFieldDoesNotExist('allergies');

    $sta->givePermissionTo(StaPermission::ViewMedical->value);
    actingInAdmin($sta->fresh());
    livewire(AdminEditPerson::class, ['record' => $person->getRouteKey()])->assertFormFieldExists('allergies');
});

it('saves visas for officials', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    livewire(CreatePerson::class)->fillForm(v12Athlete(['type' => 'official', 'function' => 'Head Coach', 'passport_no' => 'BH8888888',
        'visas' => [['country' => 'Japan', 'status' => 'Approved', 'visa_no' => 'J1', 'valid_until' => now()->addMonths(6)->toDateString()]]]))
        ->call('create')->assertHasNoFormErrors();

    expect(Person::sole()->visas()->sole()->country)->toBe('Japan');
});
```
**Note:** `assertSchemaStateSet` and `assertFormFieldExists` are the Filament 4.16 testing names. If 4.16 names them differently, use the 4.16 equivalent and keep the assertion meaning.

- [ ] **Step 2: Run them**

Expected: FAIL.

- [ ] **Step 3: Write the phone field helper**

`app/Filament/Shared/PhoneField.php`:
```php
<?php

namespace App\Filament\Shared;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

final class PhoneField
{
    /** Columns: {$column}_cc and {$column}. */
    public static function make(string $column, string $label): Grid
    {
        return Grid::make(['default' => 3])->schema([
            Select::make($column.'_cc')->label('Code')->options(config('phone_codes'))->default('+973')->searchable()->required(),
            TextInput::make($column)->label($label)->tel()->regex('/^[0-9 ]{4,20}$/')->maxLength(20)->columnSpan(2),
        ])->columnSpan(1);
    }
}
```

- [ ] **Step 4: Rewrite the form**

Rewrite `PersonForm::configure()` with these sections, in order. Keep `upload()`, `uniqueDocument()`, `sizeFields()` and `options()`. In `upload()`, add `->openable()->downloadable()`.
```php
public const FUNCTIONS = ['Head of Delegation', 'Team Manager', 'Administrator', 'Head Coach', 'Assistant Coach', 'Goalkeeper Coach',
    'Fitness Coach', 'Performance Analyst', 'Team Doctor', 'Physiotherapist', 'Massage Therapist', 'Kit Manager', 'Media Officer',
    'Security Officer', 'Interpreter', 'Other'];

public static function configure(Schema $schema, bool $withAssociation = false): Schema
{
    $isOfficial = fn (Get $get) => $get('type') === PersonType::Official->value;
    $isAthlete = fn (Get $get) => $get('type') !== PersonType::Official->value;
    $medical = fn () => Person::canViewMedical(auth()->user());
    $countries = array_combine(array_values(config('countries')), array_values(config('countries')));
    $scanTypes = ['application/pdf', 'image/jpeg', 'image/png'];

    return $schema->components([
        Section::make('Person')->columns(2)->schema([
            ...($withAssociation ? [Select::make('association_id')->relationship('association', 'name')->searchable()->preload()->required()] : []),
            Select::make('type')->options(self::options(PersonType::cases()))->required()->live()->default(PersonType::Athlete->value),
            TextInput::make('full_name')->label('Full name (as in passport)')->maxLength(200)->columnSpanFull(),
            TextInput::make('first_name')->required()->maxLength(100),
            TextInput::make('second_name')->maxLength(100),
            TextInput::make('third_name')->maxLength(100),
            TextInput::make('last_name')->required()->maxLength(100),
            DatePicker::make('dob')->label('Date of birth')->required()->maxDate(now()),
            Select::make('gender')->options(self::options(PersonGender::cases()))->required(),
            Select::make('nationality')->options($countries)->searchable()->required(),
            TextInput::make('height_cm')->label('Height (cm)')->numeric()->minValue(50)->maxValue(250),
            TextInput::make('weight_kg')->label('Weight (kg)')->numeric()->minValue(20)->maxValue(250),
            self::upload('photo_path', 'Photo', 'photos', ['image/jpeg', 'image/png'], 2048),
            Toggle::make('is_active')->default(true),
        ]),
        Section::make('Passport')->columns(3)->schema([
            self::upload('passport_scan_path', 'Passport scan', 'scans', $scanTypes, 5120)->columnSpanFull()
                ->hintAction(ReadPassportAction::make()),
            TextInput::make('passport_no')->label('Passport number')->required()->maxLength(30)
                ->rule(fn (?Model $record) => self::uniqueDocument('passport_no_hash', 'passport', $record)),
            Select::make('passport_issuing_country')->label('Issuing country')->options($countries)->searchable(),
            DatePicker::make('passport_issue_date')->label('Issue date')->maxDate(now()),
            DatePicker::make('passport_expiry')->label('Expiry date')->required(),
        ]),
        Section::make('CPR')->columns(3)->schema([
            TextInput::make('cpr_no')->label('CPR number')->maxLength(30)
                ->rule(fn (?Model $record) => self::uniqueDocument('cpr_no_hash', 'CPR', $record)),
            DatePicker::make('cpr_expiry')->requiredWith('cpr_no'),
            self::upload('cpr_front_path', 'CPR front', 'scans', $scanTypes, 5120),
            self::upload('cpr_back_path', 'CPR back', 'scans', $scanTypes, 5120),
            self::upload('cpr_reader_path', 'CPR reader copy', 'scans', $scanTypes, 5120),
        ]),
        Section::make('Contact')->columns(2)->schema([
            PhoneField::make('phone', 'Mobile')->label(fn (Get $get) => $get('type') === 'official' ? 'Mobile' : 'Player Mobile'),
            Toggle::make('whatsapp_same')->label('WhatsApp is the same as mobile')->live(),
            PhoneField::make('whatsapp', 'WhatsApp')->hidden(fn (Get $get) => (bool) $get('whatsapp_same')),
            TextInput::make('email')->label(fn (Get $get) => $get('type') === 'official' ? 'Email' : 'Player Email')->email()->maxLength(255),
            TextInput::make('address')->label('Player Address')->maxLength(255)->visible($isAthlete),
            TextInput::make('emergency_name')->label('Emergency contact name')->maxLength(200),
            TextInput::make('emergency_relationship')->label('Relationship')->maxLength(100),
            PhoneField::make('emergency_phone', 'Emergency phone'),
        ]),
        Section::make('Guardian')->description('For athletes under 18')->columns(2)->visible($isAthlete)->schema([
            TextInput::make('guardian_name')->maxLength(200),
            Select::make('guardian_relationship')->options(array_combine($g = ['Father', 'Mother', 'Legal guardian'], $g)),
            PhoneField::make('guardian_phone', 'Guardian phone'),
            TextInput::make('guardian_email')->email()->maxLength(255),
        ]),
        Section::make('Team official details')->columns(2)->visible($isOfficial)->schema([
            Select::make('function')->options(array_combine(self::FUNCTIONS, self::FUNCTIONS))->required($isOfficial),
            TextInput::make('languages')->label('Languages spoken')->maxLength(255),
            Repeater::make('visas')->relationship()->columnSpanFull()->defaultItems(0)->addActionLabel('Add visa')->columns(3)->schema([
                Select::make('country')->options($countries)->searchable()->required(),
                Select::make('status')->options(array_combine(PersonVisa::STATUSES, PersonVisa::STATUSES))->required(),
                TextInput::make('visa_no')->label('Visa number')->maxLength(50),
                DatePicker::make('valid_until'),
                self::upload('copy_path', 'Visa copy', 'visas', $scanTypes, 5120)->columnSpan(2),
            ]),
        ]),
        Section::make('Clothing')->columns(3)->schema([
            Select::make('fit')->options(array_combine($f = ["Men's", "Women's", 'Youth'], $f)),
            TextInput::make('chest_cm')->label('Chest (cm)')->numeric()->minValue(40)->maxValue(200),
            TextInput::make('waist_cm')->label('Waist (cm)')->numeric()->minValue(40)->maxValue(200),
            Grid::make(3)->columnSpanFull()
                ->schema(fn (Get $get) => self::sizeFields(PersonType::tryFrom((string) $get('type')) ?? PersonType::Athlete)),
        ]),
        // One section for both types (field names must be unique in a schema); title and type-only fields switch on type.
        // The whole section is left out of the schema (->hidden, not just invisible) for STA users without view_medical.
        Section::make(fn (Get $get) => $isOfficial($get) ? 'Health' : 'Medical')->columns(2)->hidden(fn () => ! $medical())->schema([
            Select::make('blood_group')->options(array_combine($b = ['A+', 'A−', 'B+', 'B−', 'AB+', 'AB−', 'O+', 'O−', 'Unknown'], $b)),
            Select::make('meal_requirement')->visible($isOfficial)
                ->options(array_combine($m = ['Halal', 'Vegetarian', 'Vegan', 'Gluten-free', 'No restriction'], $m)),
            DatePicker::make('medical_clearance_expiry')->visible($isAthlete),
            TextInput::make('insurance_policy_no')->label('Insurance policy number')->maxLength(100)->visible($isAthlete),
            self::upload('wada_certificate_path', 'WADA certificate', 'scans', $scanTypes, 5120)->visible($isAthlete),
            Textarea::make('allergies')->rows(2),
            Textarea::make('chronic_conditions')->label(fn (Get $get) => $isOfficial($get) ? 'Medical conditions' : 'Chronic conditions')->rows(2),
            Textarea::make('medication')->label('Current medication')->rows(2),
            Repeater::make('injuries')->relationship()->visible($isAthlete)->columnSpanFull()->defaultItems(0)->addActionLabel('Add injury')->columns(3)->schema([
                DatePicker::make('injury_date')->required(),
                Select::make('status')->options(array_combine(PersonInjury::STATUSES, PersonInjury::STATUSES))->required(),
                Textarea::make('description')->required()->rows(2)->columnSpanFull(),
            ]),
        ]),
        Section::make('Consents')->visible($isAthlete)->schema([
            Toggle::make('consent_data')->label('Consent to store personal data'),
            Toggle::make('consent_media')->label('Consent to use photos in media'),
            Toggle::make('consent_medical')->label('Consent for emergency medical treatment'),
        ]),
        Section::make('Declarations')->visible($isOfficial)->schema([
            Toggle::make('decl_conduct')->label('Accepts the delegation code of conduct'),
            Toggle::make('decl_antidoping')->label('Will follow anti-doping and integrity rules'),
            Toggle::make('decl_data_sharing')->label('Consents to data being shared with organisers, airline and hotel'),
        ]),
    ]);
}
```
**Hidden medical fields keep their values:** when the section is hidden, Filament does not dehydrate its fields, so an STA edit without `view_medical` leaves the stored medical data untouched. Add a test line for this to "hides medical fields in admin": after saving the edit, `allergies` still holds its original value.

Add these imports:
- `Filament\Forms\Components\{Repeater, Textarea}`
- `Filament\Schemas\Components\Grid`
- `App\Models\{PersonVisa, PersonInjury}`
- `App\Filament\Shared\ReadPassportAction`

**ReadPassportAction:** it comes from Task 10. Until then, create a stub: `final class ReadPassportAction { public static function make(): \Filament\Actions\Action { return \Filament\Actions\Action::make('readPassport')->label('Read details from passport')->hidden(); } }`. Task 10 replaces it.

- [ ] **Step 5: Run the tests and commit**

Run `$PHP vendor/bin/pest tests/Feature/PersonFormV12Test.php tests/Feature/PeopleScreensTest.php`, then the full suite. Fix existing screen tests that relied on the old 7-item `FUNCTIONS` list (e.g. `'Coach'` becomes `'Head Coach'`).

`git add -A && git commit -m "feat: person form v1.2 sections, phones, visas, medical"`

---

### Task 7: People table, completeness badge, attachments and clothing seed

**Files:**
- Create: `database/seeders/ClothingItemSeeder.php`
- Modify: `app/Filament/Shared/PersonTable.php`, `database/seeders/DatabaseSeeder.php`
- Test: append to `tests/Feature/PersonCompletenessTest.php`

**Interfaces:**
- Consumes: `Person::missingFields()` and `AttachmentActions::group()`.

- [ ] **Step 1: Write the failing tests**

```php
use App\Filament\Portal\Resources\People\Pages\ListPeople;
use App\Models\ClothingItem;
use App\Models\User;
use Database\Seeders\ClothingItemSeeder;
use function Pest\Livewire\livewire;

it('shows code and completeness in the people list', function () {
    $incomplete = Person::factory()->create(['full_name' => null]);
    actingInPortal(User::factory()->assocEditor($incomplete->association)->create());

    livewire(ListPeople::class)
        ->assertTableColumnStateSet('code', $incomplete->code, $incomplete)
        ->assertTableColumnStateSet('completeness', 'Incomplete', $incomplete);
});

it('seeds T-Shirt, Suit and Shoes once', function () {
    $this->seed(ClothingItemSeeder::class);
    $this->seed(ClothingItemSeeder::class);
    expect(ClothingItem::pluck('name')->sort()->values()->all())->toBe(['Shoes', 'Suit', 'T-Shirt']);
});
```

- [ ] **Step 2: Run them**

Expected: FAIL.

- [ ] **Step 3: Update the table**

In `PersonTable::configure()`, add these columns after the photo column:
```php
TextColumn::make('code')->searchable()->sortable(),
TextColumn::make('completeness')->badge()
    ->state(fn (Person $record) => $record->isComplete() ? 'Complete' : 'Incomplete')
    ->color(fn (string $state) => $state === 'Complete' ? 'success' : 'warning')
    ->tooltip(fn (Person $record) => $record->isComplete() ? null : 'Missing: '.implode(', ', $record->missingFields())),
```
In `recordActions([...])`, add `AttachmentActions::group()` before the edit action.

- [ ] **Step 4: Write the seeder**

`database/seeders/ClothingItemSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Enums\AppliesTo;
use App\Models\ClothingItem;
use Illuminate\Database\Seeder;

class ClothingItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'T-Shirt' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'],
            'Suit' => ['44', '46', '48', '50', '52', '54', '56', '58'],
            'Shoes' => array_map('strval', range(35, 48)),
        ];
        foreach ($items as $name => $sizes) {
            ClothingItem::firstOrCreate(['name' => $name], ['sizes' => $sizes, 'applies_to' => AppliesTo::Both, 'is_active' => true]);
        }
    }
}
```
In `DatabaseSeeder::run()`, add `$this->call(ClothingItemSeeder::class);` after `PermissionSeeder`.

- [ ] **Step 5: Run the tests and commit**

`git add -A && git commit -m "feat: person code, completeness badge, attachment links, clothing seed"`

---

### Task 8: Excel import and template v1.2

**Files:**
- Modify: `app/Exports/PeopleTemplateExport.php`, `app/Services/PeopleImporter.php`, `tests/Feature/PeopleImportTest.php`

**Interfaces:**
- Consumes: `PersonForm::FUNCTIONS` (expanded) and `config('countries')`.

- [ ] **Step 1: Write the failing test**

Append to `PeopleImportTest.php`:
```php
it('imports the v1.2 columns and assigns codes', function () {
    $assoc = Association::factory()->create(['code' => 'BWF']);
    $result = app(PeopleImporter::class)->import(xlsxWith([row(['full_name' => 'Sara Ali', 'passport_issuing_country' => 'Bahrain',
        'passport_issue_date' => '2024-01-10', 'mobile' => '33445566', 'email' => 'sara@x.bh'])]), $assoc);

    $p = Person::sole();
    expect($result->ok())->toBeTrue()->and($p->code)->toBe('BWF-AT-0001')->and($p->full_name)->toBe('Sara Ali')
        ->and($p->passport_issuing_country)->toBe('Bahrain')->and($p->phone)->toBe('33445566')->and($p->phone_cc)->toBe('+973');
});

it('rejects an unknown issuing country', function () {
    $result = app(PeopleImporter::class)->import(xlsxWith([row(['passport_issuing_country' => 'Atlantis'])]), Association::factory()->create());
    expect($result->ok())->toBeFalse();
});
```

- [ ] **Step 2: Run it**

Expected: FAIL.

- [ ] **Step 3: Implement**

- **`PeopleTemplateExport::COLUMNS`:** insert `'full_name'` after `'type'`, and `'passport_issuing_country', 'passport_issue_date'` after `'passport_no'`. Rename `'phone'` to `'mobile'`. Keep the order otherwise. Update the example row with `'full_name' => 'Ahmed Ali Hassan'`, `'passport_issuing_country' => 'Bahrain'`, `'passport_issue_date' => '2023-01-01'` and `'mobile' => ''`.
- **`PeopleImporter`:**
  - Add `passport_issue_date` to `DATE_COLUMNS`.
  - New rules: `'full_name' => ['nullable','string','max:200']`, `'passport_issuing_country' => ['nullable', Rule::in(array_values(config('countries')))]`, `'passport_issue_date' => ['nullable','date_format:Y-m-d']`, `'mobile' => ['nullable','regex:/^[0-9 ]{4,20}$/']`.
  - Cast `mobile` to string in `normalise`.
  - In `toAttributes`, map `'phone' => $row['mobile']`, `'phone_cc' => '+973'`, and the three new fields.
- **The `row()` test helper:** add the new keys, with `full_name` `'Sara Ali'`, `passport_issuing_country` `'Bahrain'`, `passport_issue_date` `'2023-01-01'` and `mobile` `''`.

- [ ] **Step 4: Run the tests and commit**

Run `$PHP vendor/bin/pest tests/Feature/PeopleImportTest.php`, then the full suite.

`git add -A && git commit -m "feat: import and template v1.2 columns"`

---

### Task 9: MRZ parser

**Files:**
- Create: `app/Services/Mrz/MrzParser.php`, `app/Services/Mrz/MrzResult.php`, `tests/Feature/MrzParserTest.php`

**Interfaces:**
- Produces:
  - `MrzParser::parse(string $line1, string $line2, ?CarbonInterface $today = null): MrzResult`. It throws `InvalidArgumentException` when the lines are not TD3.
  - `MrzResult` (readonly), with these fields:
    - `?string surname`, `array givenNames`, `?string fullName`
    - `?string passportNo`, `?string issuingCode`, `?string nationalityCode`
    - `?string dob` (Y-m-d), `?string gender` (`male`/`female`/null), `?string expiry` (Y-m-d)
    - `array<int,string> invalid` (field names whose check digit failed)
  - `MrzParser::checkDigit(string $s): int`.

- [ ] **Step 1: Write the failing test**

These are synthetic test lines with valid check digits.
- `BH1234567` → 6
- `060527` → 6
- `310115` → 9
- empty optional → 0
- composite → 0

```php
<?php

use App\Services\Mrz\MrzParser;
use Carbon\Carbon;

const BHR_L1 = 'P<BHRALSAYED<<NOOR<KHALID<AHMED<<<<<<<<<<<<<';
const BHR_L2 = 'BH12345676BHR0605276F3101159<<<<<<<<<<<<<<00';
const ICAO_L1 = 'P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<';
const ICAO_L2 = 'L898902C36UTO7408122F1204159ZE184226B<<<<<10';

it('parses a Bahrain-style passport with four names', function () {
    $r = (new MrzParser)->parse(BHR_L1, BHR_L2, Carbon::parse('2026-11-09'));

    expect($r->surname)->toBe('Alsayed')
        ->and($r->givenNames)->toBe(['Noor', 'Khalid', 'Ahmed'])
        ->and($r->fullName)->toBe('Noor Khalid Ahmed Alsayed')
        ->and($r->passportNo)->toBe('BH1234567')
        ->and($r->issuingCode)->toBe('BHR')->and($r->nationalityCode)->toBe('BHR')
        ->and($r->dob)->toBe('2006-05-27')->and($r->gender)->toBe('female')
        ->and($r->expiry)->toBe('2031-01-15')->and($r->invalid)->toBe([]);
});

it('parses the ICAO specimen with a 19xx birth year', function () {
    $r = (new MrzParser)->parse(ICAO_L1, ICAO_L2, Carbon::parse('2026-11-09'));
    expect($r->dob)->toBe('1974-08-12')->and($r->passportNo)->toBe('L898902C3')->and($r->givenNames)->toBe(['Anna', 'Maria'])
        ->and($r->invalid)->toBe([]);
});

it('flags fields whose check digit fails and does not return them', function () {
    $bad = substr_replace(BHR_L2, '5', 19, 1); // dob check digit 6 -> 5
    $r = (new MrzParser)->parse(BHR_L1, $bad, Carbon::parse('2026-11-09'));
    expect($r->invalid)->toContain('dob')->and($r->dob)->toBeNull()->and($r->passportNo)->toBe('BH1234567');
});

it('tolerates OCR spaces and lower case, and rejects non-TD3 input', function () {
    $r = (new MrzParser)->parse(strtolower(' '.BHR_L1.' '), str_replace('<', ' <', BHR_L2));
    expect($r->passportNo)->toBe('BH1234567');
    expect(fn () => (new MrzParser)->parse('P<SHORT', 'X'))->toThrow(InvalidArgumentException::class);
});

it('computes ICAO check digits', function () {
    expect(MrzParser::checkDigit('L898902C3'))->toBe(6)->and(MrzParser::checkDigit('<<<<<<<<<<<<<<'))->toBe(0);
});
```
In the third test, `str_replace('<', ' <', ...)` adds spaces, and the parser must strip all whitespace first. The parser normalises with `strtoupper(preg_replace('/\s+/', '', $line))`, then requires exactly 44 characters.

- [ ] **Step 2: Run it**

Expected: FAIL, because the class is missing.

- [ ] **Step 3: Implement**

`app/Services/Mrz/MrzResult.php`:
```php
<?php

namespace App\Services\Mrz;

final readonly class MrzResult
{
    /** @param array<int, string> $givenNames @param array<int, string> $invalid */
    public function __construct(
        public ?string $surname, public array $givenNames, public ?string $fullName, public ?string $passportNo,
        public ?string $issuingCode, public ?string $nationalityCode, public ?string $dob, public ?string $gender,
        public ?string $expiry, public array $invalid,
    ) {}
}
```
`app/Services/Mrz/MrzParser.php`:
```php
<?php

namespace App\Services\Mrz;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/** ICAO 9303 TD3 (passport) machine-readable zone parser. Pure PHP, no OCR. */
final class MrzParser
{
    public function parse(string $line1, string $line2, ?CarbonInterface $today = null): MrzResult
    {
        $l1 = strtoupper(preg_replace('/\s+/', '', $line1));
        $l2 = strtoupper(preg_replace('/\s+/', '', $line2));
        if (strlen($l1) !== 44 || strlen($l2) !== 44 || $l1[0] !== 'P') {
            throw new InvalidArgumentException('Not a passport (TD3) machine-readable zone.');
        }
        $today ??= Carbon::now();
        $invalid = [];

        [$surnameRaw, $givenRaw] = array_pad(explode('<<', substr($l1, 5), 2), 2, '');
        $surname = self::name($surnameRaw);
        $given = array_values(array_filter(array_map([self::class, 'name'], explode('<', $givenRaw))));

        $doc = substr($l2, 0, 9);
        $passportNo = self::ok($doc, $l2[9]) ? rtrim($doc, '<') : null;
        if ($passportNo === null) { $invalid[] = 'passport_no'; }

        $dobRaw = substr($l2, 13, 6);
        $dob = self::ok($dobRaw, $l2[19]) ? self::date($dobRaw, $today, past: true) : null;
        if ($dob === null) { $invalid[] = 'dob'; }

        $expRaw = substr($l2, 21, 6);
        $expiry = self::ok($expRaw, $l2[27]) ? self::date($expRaw, $today, past: false) : null;
        if ($expiry === null) { $invalid[] = 'expiry'; }

        $composite = substr($l2, 0, 10).substr($l2, 13, 7).substr($l2, 21, 22);
        if (! self::ok($composite, $l2[43])) {
            $invalid[] = 'composite';
        }

        return new MrzResult(
            surname: $surname ?: null,
            givenNames: $given,
            fullName: trim(implode(' ', [...$given, $surname])) ?: null,
            passportNo: $passportNo,
            issuingCode: rtrim(substr($l1, 2, 3), '<') ?: null,
            nationalityCode: rtrim(substr($l2, 10, 3), '<') ?: null,
            dob: $dob,
            gender: ['M' => 'male', 'F' => 'female'][$l2[20]] ?? null,
            expiry: $expiry,
            invalid: $invalid,
        );
    }

    public static function checkDigit(string $s): int
    {
        $weights = [7, 3, 1];
        $sum = 0;
        foreach (str_split($s) as $i => $c) {
            $v = ctype_digit($c) ? (int) $c : (ctype_alpha($c) ? ord($c) - 55 : 0);
            $sum += $v * $weights[$i % 3];
        }

        return $sum % 10;
    }

    private static function ok(string $field, string $digit): bool
    {
        return ctype_digit($digit) && self::checkDigit($field) === (int) $digit;
    }

    private static function name(string $raw): string
    {
        return ucwords(strtolower(trim(str_replace('<', ' ', $raw))));
    }

    private static function date(string $yymmdd, CarbonInterface $today, bool $past): ?string
    {
        if (! ctype_digit($yymmdd)) { return null; }
        $yy = (int) substr($yymmdd, 0, 2);
        $century = $past ? ($yy > (int) $today->format('y') ? 1900 : 2000) : 2000;
        $date = Carbon::createFromFormat('Y-m-d', sprintf('%04d-%s-%s', $century + $yy, substr($yymmdd, 2, 2), substr($yymmdd, 4, 2)));

        return $date && $date->format('ymd') === $yymmdd ? $date->toDateString() : null;
    }
}
```

- [ ] **Step 4: Run the tests and commit**

Run `$PHP vendor/bin/pest tests/Feature/MrzParserTest.php`. Expected: 5 passed.

`git add -A && git commit -m "feat: ICAO TD3 MRZ parser with check digits"`

---

### Task 10: Passport reader (Tesseract) and form action

**Files:**
- Create: `app/Services/Mrz/MrzReader.php`, `app/Services/Mrz/TesseractMrzReader.php`, `app/Services/Mrz/PassportScanFile.php`, `tests/Feature/ReadPassportActionTest.php`
- Replace stub: `app/Filament/Shared/ReadPassportAction.php`
- Modify: `config/services.php`, `app/Providers/AppServiceProvider.php`, `README.md`

**Interfaces:**
- Consumes: `MrzParser` and `MrzResult` (Task 9), `config('countries')` (Task 2), and the form field names (Task 6).
- Produces:
  - `interface MrzReader { /** @return array{0:string,1:string}|null */ public function read(string $absolutePath): ?array; }`, bound to `TesseractMrzReader`.
  - `PassportScanFile::localPath(mixed $state): ?string`.
  - `ReadPassportAction::make(): Action`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Filament\Portal\Resources\People\Pages\CreatePerson;
use App\Models\Association;
use App\Models\User;
use App\Services\Mrz\MrzReader;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Livewire\livewire;

function fakeReader(?array $lines): void
{
    app()->instance(MrzReader::class, new class($lines) implements MrzReader {
        public function __construct(private ?array $lines) {}
        public function read(string $absolutePath): ?array { return $this->lines; }
    });
}

beforeEach(function () {
    Storage::fake(config('filesystems.private'));
    actingInPortal(User::factory()->assocEditor(Association::factory()->create(['code' => 'BWF']))->create());
});

it('fills the form from the passport MRZ', function () {
    fakeReader(['P<BHRALSAYED<<NOOR<KHALID<AHMED<<<<<<<<<<<<<', 'BH12345676BHR0605276F3101159<<<<<<<<<<<<<<00']);

    livewire(CreatePerson::class)
        ->fillForm(['passport_scan_path' => UploadedFile::fake()->image('passport.jpg')])
        ->callAction(TestAction::make('readPassport')->schemaComponent('passport_scan_path'))
        ->assertSchemaStateSet([
            'full_name' => 'Noor Khalid Ahmed Alsayed', 'first_name' => 'Noor', 'second_name' => 'Khalid', 'third_name' => 'Ahmed',
            'last_name' => 'Alsayed', 'passport_no' => 'BH1234567', 'passport_issuing_country' => 'Bahrain', 'nationality' => 'Bahrain',
            'dob' => '2006-05-27', 'gender' => 'female', 'passport_expiry' => '2031-01-15',
        ])
        ->assertNotified('Passport read — please check the details before saving');
});

it('leaves fields whose check digit fails empty and warns', function () {
    fakeReader(['P<BHRALSAYED<<NOOR<KHALID<AHMED<<<<<<<<<<<<<', 'BH12345676BHR0605275F3101159<<<<<<<<<<<<<<00']);

    livewire(CreatePerson::class)
        ->fillForm(['passport_scan_path' => UploadedFile::fake()->image('passport.jpg'), 'dob' => null])
        ->callAction(TestAction::make('readPassport')->schemaComponent('passport_scan_path'))
        ->assertSchemaStateSet(['dob' => null, 'passport_no' => 'BH1234567']);
});

it('says when the passport cannot be read', function () {
    fakeReader(null);

    livewire(CreatePerson::class)
        ->fillForm(['passport_scan_path' => UploadedFile::fake()->image('passport.jpg'), 'first_name' => 'Keep'])
        ->callAction(TestAction::make('readPassport')->schemaComponent('passport_scan_path'))
        ->assertSchemaStateSet(['first_name' => 'Keep'])
        ->assertNotified("Couldn't read the passport. Try a clearer, flat scan.");
});
```
**Filament 4.16:** the testing calls for a field's hint action (`schemaComponent(...)`) and state assertions (`assertSchemaStateSet`) may be named differently. Check `vendor/filament/*/src/Testing`, use the 4.16 equivalents, and keep the assertions' meaning.

- [ ] **Step 2: Run it**

Expected: FAIL, because the stub action is hidden.

- [ ] **Step 3: Implement the reader**

`config/services.php`, added to the returned array:
```php
'tesseract' => ['binary' => env('TESSERACT_PATH', 'tesseract'), 'pdftoppm' => env('PDFTOPPM_PATH', 'pdftoppm')],
```
`app/Services/Mrz/MrzReader.php`:
```php
<?php

namespace App\Services\Mrz;

interface MrzReader
{
    /** @return array{0: string, 1: string}|null the two TD3 lines, or null when no MRZ was found */
    public function read(string $absolutePath): ?array;
}
```
`app/Services/Mrz/TesseractMrzReader.php`:
```php
<?php

namespace App\Services\Mrz;

use Illuminate\Support\Facades\Process;

/** Local OCR: PDF → PNG (pdftoppm), crop the bottom of the page, threshold with GD, Tesseract with an MRZ whitelist. */
final class TesseractMrzReader implements MrzReader
{
    public function read(string $absolutePath): ?array
    {
        $tmp = sys_get_temp_dir().'/mrz_'.bin2hex(random_bytes(6));
        try {
            $image = $absolutePath;
            if (strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) === 'pdf' || mime_content_type($absolutePath) === 'application/pdf') {
                $ok = Process::run([config('services.tesseract.pdftoppm'), '-r', '300', '-png', '-f', '1', '-l', '1', '-singlefile', $absolutePath, $tmp]);
                if (! $ok->successful()) { return null; }
                $image = $tmp.'.png';
            }
            $strip = $this->bottomStrip($image, $tmp.'_strip.png');
            if ($strip === null) { return null; }

            $result = Process::run([config('services.tesseract.binary'), $strip, 'stdout', '--psm', '6',
                '-c', 'tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<']);
            if (! $result->successful()) { return null; }

            return self::pickLines($result->output());
        } finally {
            foreach (glob($tmp.'*') ?: [] as $f) { @unlink($f); }
        }
    }

    /** @return array{0:string,1:string}|null */
    public static function pickLines(string $ocr): ?array
    {
        $lines = array_values(array_filter(array_map(fn ($l) => preg_replace('/[^A-Z0-9<]/', '', strtoupper($l)), preg_split('/\R/', $ocr)),
            fn ($l) => strlen($l) >= 40));
        $n = count($lines);
        if ($n < 2) { return null; }
        $fix = fn (string $l) => substr(str_pad($l, 44, '<'), 0, 44);

        return [$fix($lines[$n - 2]), $fix($lines[$n - 1])];
    }

    private function bottomStrip(string $image, string $out): ?string
    {
        $src = @imagecreatefromstring((string) file_get_contents($image));
        if (! $src) { return null; }
        $w = imagesx($src); $h = imagesy($src);
        $stripH = (int) round($h * 0.3);
        $strip = imagecrop($src, ['x' => 0, 'y' => $h - $stripH, 'width' => $w, 'height' => $stripH]);
        imagefilter($strip, IMG_FILTER_GRAYSCALE);
        imagefilter($strip, IMG_FILTER_CONTRAST, -40);
        imagepng($strip, $out);

        return $out;
    }
}
```
Add a unit test for `pickLines` to `MrzParserTest.php`:
```php
it('picks the last two MRZ-like lines from OCR text', function () {
    $ocr = "REPUBLIC OF BAHRAIN\nPASSPORT\nP<BHRALSAYED<<NOOR<KHALID<AHMED<<<<<<<<<<<<<\nBH12345676BHR0605276F3101159<<<<<<<<<<<<<<00\n";
    expect(\App\Services\Mrz\TesseractMrzReader::pickLines($ocr))->toBe([
        'P<BHRALSAYED<<NOOR<KHALID<AHMED<<<<<<<<<<<<<', 'BH12345676BHR0605276F3101159<<<<<<<<<<<<<<00']);
});
```
`app/Services/Mrz/PassportScanFile.php`:
```php
<?php

namespace App\Services\Mrz;

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** Turns a FileUpload state (new temporary upload or stored path) into a local file path the OCR can read. */
final class PassportScanFile
{
    public static function localPath(mixed $state): ?string
    {
        $item = is_array($state) ? (array_values($state)[0] ?? null) : $state;
        if ($item instanceof TemporaryUploadedFile) {
            return $item->getRealPath() ?: null;
        }
        if (is_string($item) && $item !== '') {
            $disk = Storage::disk(config('filesystems.private'));
            if (! $disk->exists($item)) { return null; }
            $local = tempnam(sys_get_temp_dir(), 'pp_').'.'.pathinfo($item, PATHINFO_EXTENSION);
            file_put_contents($local, $disk->get($item)); // works for local and S3

            return $local;
        }

        return null;
    }
}
```
In `AppServiceProvider::register()`: `$this->app->bind(\App\Services\Mrz\MrzReader::class, \App\Services\Mrz\TesseractMrzReader::class);`.

- [ ] **Step 4: Implement the form action**

Replace `app/Filament/Shared/ReadPassportAction.php`:
```php
<?php

namespace App\Filament\Shared;

use App\Services\Mrz\MrzParser;
use App\Services\Mrz\MrzReader;
use App\Services\Mrz\PassportScanFile;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use InvalidArgumentException;

final class ReadPassportAction
{
    public static function make(): Action
    {
        return Action::make('readPassport')
            ->label('Read details from passport')
            ->icon('heroicon-o-identification')
            ->visible(fn (Get $get) => filled($get('passport_scan_path')))
            ->action(function (Get $get, Set $set) {
                $path = PassportScanFile::localPath($get('passport_scan_path'));
                $lines = $path ? app(MrzReader::class)->read($path) : null;
                try {
                    $r = $lines ? (new MrzParser)->parse($lines[0], $lines[1]) : null;
                } catch (InvalidArgumentException) {
                    $r = null;
                }
                if ($r === null) {
                    Notification::make()->danger()->title("Couldn't read the passport. Try a clearer, flat scan.")->send();

                    return;
                }
                $country = fn (?string $code) => $code ? (config('countries')[$code] ?? $code) : null;
                [$first, $second] = [$r->givenNames[0] ?? null, $r->givenNames[1] ?? null];
                $third = count($r->givenNames) > 2 ? implode(' ', array_slice($r->givenNames, 2)) : null;

                $values = ['full_name' => $r->fullName, 'first_name' => $first, 'second_name' => $second, 'third_name' => $third,
                    'last_name' => $r->surname, 'passport_no' => $r->passportNo, 'passport_issuing_country' => $country($r->issuingCode),
                    'nationality' => $country($r->nationalityCode), 'dob' => $r->dob, 'gender' => $r->gender, 'passport_expiry' => $r->expiry];
                foreach ($values as $field => $value) {
                    if ($value !== null) { $set($field, $value); }
                }

                $note = Notification::make()->title('Passport read — please check the details before saving');
                $r->invalid ? $note->warning()->body('Could not confirm: '.implode(', ', $r->invalid).'. Enter these by hand.') : $note->success();
                $note->send();
            });
    }
}
```

- [ ] **Step 5: Update the README**

Add to the README's "Production notes":
```markdown
- Passport reader: install Tesseract OCR and poppler on the server (`sudo dnf install -y tesseract poppler-utils`, or `apt install tesseract-ocr poppler-utils`). Optional `.env`: `TESSERACT_PATH`, `PDFTOPPM_PATH`. Locally on Windows, install Tesseract (UB Mannheim build) and poppler and set both paths. Tests do not need them.
```

- [ ] **Step 6: Run the tests, pint and commit**

Run `$PHP vendor/bin/pest tests/Feature/ReadPassportActionTest.php tests/Feature/MrzParserTest.php`, then the full suite, then `$PHP vendor/bin/pint`, then the full suite again.

`git add -A && git commit -m "feat: read passport details from the MRZ with local OCR"`

---

## Spec coverage (section 13)

| Spec item | Task |
|---|---|
| Association code, person codes, never reused | 1, 2 |
| File naming `{code}_{kind}`, View links | 3 |
| Full name, passport issuing country and issue date, CPR front/back/reader | 2, 6 |
| Clothing measurements plus T-Shirt/Suit/Shoes items | 2, 6, 7 |
| Phone country code `+973`, WhatsApp same as mobile | 2, 6 |
| Athlete contact, guardian, WADA, medical, injuries, consents | 2, 6 |
| Official function list, languages, visas list, health, declarations | 2, 6 |
| Completeness (save minimum vs complete) and badge | 4, 7 |
| Medical encryption, audit exclusion, `view_medical` | 5, 6 |
| Excel import still works and gains the new columns | 8 |
| Passport reader (MRZ, check digits, no AI) | 9, 10 |
| Per-game insurance and vaccinations | Out of scope (Milestone 3) |
