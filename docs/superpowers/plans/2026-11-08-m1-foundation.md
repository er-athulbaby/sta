# Milestone 1 – Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A running Laravel + Filament app where STA onboards associations by invitation, manages sports, events and clothing items, and associations maintain a secure, permanent registry of athletes and team officials (including clothing sizes, scans and Excel import), with per-module STA permissions and a full audit log.

**Architecture:** One Laravel 12 app with two Filament v4 panels. `/admin` serves Super Admin and STA users, with access controlled by spatie permissions set as checkboxes. `/portal` serves association admins and editors, and uses Filament multi-tenancy with `Association` as the tenant. Sensitive data is protected in four ways:
- Passport and CPR numbers use Laravel's `encrypted` cast.
- Uniqueness is enforced through HMAC "blind index" columns.
- Files live on a private disk (S3 in production) and are only served through temporary URLs.
- A global scope stops association users from reading another association's people, even outside Filament.

**Tech Stack:** PHP 8.5, Laravel 12, Filament `^4.0` (not v5), Livewire 3, Pest 3 + pest-plugin-livewire, spatie/laravel-permission ^6, spatie/laravel-activitylog ^4, maatwebsite/excel ^3.1, league/flysystem-aws-s3-v3 ^3, MySQL 8 (prod) / SQLite in-memory (tests).

**Spec:** `docs/superpowers/specs/2026-11-04-sports-entry-system-design.md` and `docs/Sports-Entry-System-Scope-and-Design.docx` (v1.1). Milestone 1 = section 13 "M1 – Foundation" of the scope document.

## Global Constraints

- PHP 8.5; Laravel 12.x; Filament `^4.0` — pin the major, do not install v5.
- English-only UI.
- Roles: `super_admin`, `sta`, `assoc_admin`, `assoc_editor`. Maximum **2 active Association Editors** per association (BR-02). One active Association Admin per association (scope 2.3).
- Associations never see or change another association's data (BR-01). Enforce with Filament tenancy **and** a model global scope, and prove it with tests.
- Associations cannot create or change sports, events, clothing items or games (BR-03).
- Sports, events, clothing items and associations are never deleted, only deactivated (BR-16). People can be hard-deleted **only by Super Admin** (scope section 12, data retention).
- `passport_no` and `cpr_no` are encrypted at rest. They are unique system-wide (BR-13), and must never appear in the audit log.
- Files go to the private disk only:
  - photos: JPG/PNG, at most 2 MB (FR-25)
  - passport and CPR scans: PDF/JPG/PNG, at most 5 MB, optional (FR-25a)
  - Files are served only through temporary URLs.
- Invitations: single-use, hashed token, expire after **7 days** (FR-03).
- Clothing items have an ordered size list, and `applies_to` is `athlete|official|both` (FR-14). A size must come from that item's list (BR-19).
- Officials also have `function`, `phone` and `email` (FR-21).
- Session idle timeout is 30 minutes. Optional app-based (TOTP) MFA for admin panel users (FR-08).
- Every create, update or delete on people, associations, users, sports, events and clothing items is logged with the user and time (section 12, Audit).

**Plan-level decisions (not stated in the spec; flag in review if wrong):**
- **Passport and CPR:** passport is required, CPR is optional. Foreign officials may not hold a Bahrain CPR.
- **No Filament Shield:** STA rights are spatie *direct* permissions edited with a `CheckboxList`. That is exactly "checkboxes per user", and Shield's role generator isn't needed.
- **Excel import columns:** the import covers person fields only. Photos, scans and clothing sizes are added afterwards in the form.
- **Excel import size:** at most 500 rows per file.

---

## File Structure

```
app/
  Enums/Role.php, StaPermission.php, PersonType.php, PersonGender.php, EventGender.php, AppliesTo.php
  Models/User.php (modify), Association.php, Invitation.php, Sport.php, Event.php,
         ClothingItem.php, Setting.php, Person.php, PersonSize.php
  Models/Concerns/BelongsToAssociation.php        # global scope + creating hook
  Services/AssociationMembership.php              # editor cap, single admin, activate/deactivate
  Services/InvitationService.php                  # invite, accept, resend, revoke
  Services/PeopleImporter.php, ImportResult.php   # Excel parse + validate + create
  Exports/PeopleTemplateExport.php
  Mail/InvitationMail.php
  Http/Controllers/InvitationController.php
  Providers/Filament/AdminPanelProvider.php, PortalPanelProvider.php
  Filament/Concerns/RequiresStaPermission.php
  Filament/Shared/PersonForm.php, PersonTable.php, SavesPersonSizes.php
  Filament/Resources/                             # admin panel
    Associations/ (Resource, Schemas, Tables, Pages, RelationManagers/{Invitations,Users}RelationManager.php)
    StaUsers/, Sports/ (+ RelationManagers/EventsRelationManager.php), ClothingItems/, People/, ActivityLogs/
  Filament/Pages/Settings.php                     # admin
  Filament/Portal/Resources/                      # portal panel (tenant-scoped)
    Members/, Invitations/, Sports/ (read-only), People/
  Filament/Portal/Pages/EditAssociationProfile.php, ImportPeople.php
database/migrations/ (one per table, listed in tasks)
database/factories/ AssociationFactory, UserFactory (modify), SportFactory, EventFactory, ClothingItemFactory, PersonFactory
database/seeders/ DatabaseSeeder (modify), PermissionSeeder
resources/views/ emails/invitation.blade.php, invitations/{accept,invalid}.blade.php,
                 filament/pages/settings.blade.php, filament/portal/pages/import-people.blade.php
routes/web.php (modify)
tests/Pest.php (modify), tests/Feature/*.php (one file per task)
```

**Panel discovery:** the admin panel discovers `App\Filament\Resources` and `App\Filament\Pages`, which is the installer default. The portal panel discovers `App\Filament\Portal\Resources` and `App\Filament\Portal\Pages`, which is the `make:filament-panel portal` default.

---

### Task 0: Project scaffold, packages, panels, test harness

**Files:**
- Create: Laravel 12 skeleton at repo root (keep the existing `docs/`)
- Create: `app/Providers/Filament/AdminPanelProvider.php`, `app/Providers/Filament/PortalPanelProvider.php`
- Modify: `config/filesystems.php`, `config/session.php`, `.env.example`, `phpunit.xml`, `tests/Pest.php`
- Test: `tests/Feature/SmokeTest.php`

**Interfaces:**
- Produces:
  - `config('filesystems.private')`: the disk name for sensitive files. It is `s3` in prod and `local` in dev and tests.
  - Panel ids `admin` and `portal`.
  - Test helpers `actingInAdmin(User $u): User` and `actingInPortal(User $u): User`, defined in `tests/Pest.php` in Task 2.

- [ ] **Step 1: Check prerequisites**

Run: `php -v` → expect 8.5.x. Run `php -m | grep -E '^(intl|gd)$'` → expect both `intl` and `gd`.
If either is missing (Herd Lite ships without them), install full Laravel Herd, or enable both in the `php.ini` that `php --ini` reports by adding `extension=intl` and `extension=gd`. Then re-run the check. Filament requires `ext-intl`, and PhpSpreadsheet requires `ext-gd`.

- [ ] **Step 2: Create the Laravel app in the existing repo**

```bash
composer create-project laravel/laravel:^12.0 _app
cp -rn _app/. .
rm -rf _app
php artisan key:generate
git add -A && git commit -m "chore: laravel 12 skeleton"
```
`cp -n` never overwrites the existing `docs/`. Expect `php artisan --version` to print `Laravel Framework 12.x`.

- [ ] **Step 3: Install packages**

```bash
composer remove phpunit/phpunit --dev
composer require pestphp/pest pestphp/pest-plugin-laravel pestphp/pest-plugin-livewire --dev --with-all-dependencies
./vendor/bin/pest --init
composer require filament/filament:"^4.0" spatie/laravel-permission:"^6.0" spatie/laravel-activitylog:"^4.0" maatwebsite/excel:"^3.1" league/flysystem-aws-s3-v3:"^3.0"
php artisan filament:install --panels      # answer panel id: admin
php artisan make:filament-panel portal
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
```
In PowerShell write `"~4.0"` instead of `"^4.0"`. Confirm that `bootstrap/providers.php` lists both `AdminPanelProvider` and `PortalPanelProvider`.

- [ ] **Step 4: Configure the private disk, session and env**

In `config/filesystems.php`, add a top-level key:
```php
    // Disk for photos, scans, logos and generated PDFs. Never public.
    'private' => env('PRIVATE_DISK', 'local'),
```
In `config/session.php`, change the default lifetime:
```php
    'lifetime' => (int) env('SESSION_LIFETIME', 30),
```
Append to `.env.example`:
```
PRIVATE_DISK=local
SESSION_LIFETIME=30
SUPER_ADMIN_NAME="Super Admin"
SUPER_ADMIN_EMAIL=admin@example.com
SUPER_ADMIN_PASSWORD=change-me-now
# Production: PRIVATE_DISK=s3 with AWS_DEFAULT_REGION=me-south-1, AWS_BUCKET, MAIL_MAILER=ses, DB_CONNECTION=mysql
```
In `phpunit.xml` confirm these lines are present:
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="MAIL_MAILER" value="array"/>
```

- [ ] **Step 5: Write the failing smoke test**

`tests/Feature/SmokeTest.php`:
```php
<?php

it('serves both panel login pages', function (string $path) {
    $this->get($path)->assertOk();
})->with(['/admin/login', '/portal/login']);

it('uses a 30 minute session', function () {
    expect(config('session.lifetime'))->toBe(30);
});
```
`tests/Pest.php`:
```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
```

- [ ] **Step 6: Run the test**

Run: `./vendor/bin/pest tests/Feature/SmokeTest.php`
Expected: the `/portal/login` case fails, because the portal panel has no `->login()` yet.

- [ ] **Step 7: Make both panels log in**

In `app/Providers/Filament/PortalPanelProvider.php`, inside `panel()`, make the chain begin with:
```php
return $panel
    ->id('portal')
    ->path('portal')
    ->login()
    ->passwordReset()
    ->brandName('Association Portal')
```
In `AdminPanelProvider`, keep `->id('admin')->path('admin')->login()` and add `->passwordReset()->brandName('STA Admin')`. Leave the generated middleware and discovery lines unchanged.

- [ ] **Step 8: Run the test again**

Run: `./vendor/bin/pest tests/Feature/SmokeTest.php` → PASS (3 tests).

- [ ] **Step 9: Commit**

```bash
git add -A && git commit -m "chore: filament panels, pest, packages, private disk config"
```

---

### Task 1: Associations

**Files:**
- Create: `database/migrations/2026_11_08_000100_create_associations_table.php`, `app/Models/Association.php`, `database/factories/AssociationFactory.php`
- Test: `tests/Feature/AssociationModelTest.php`

**Interfaces:**
- Produces:
  - `App\Models\Association`: `id`, `name`, `email`, `logo_path`, `phone`, `address`, `website`, and `users(): HasMany`.
  - `Association::factory()`.
  - Later tasks add `sports()` and `people()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Association;
use Illuminate\Database\QueryException;

it('requires a unique name and email', function () {
    Association::factory()->create(['name' => 'Bahrain Athletics', 'email' => 'a@x.bh']);
    expect(fn () => Association::factory()->create(['name' => 'Bahrain Athletics']))->toThrow(QueryException::class)
        ->and(fn () => Association::factory()->create(['email' => 'a@x.bh']))->toThrow(QueryException::class);
});

it('keeps profile fields optional', function () {
    $a = Association::create(['name' => 'Squash', 'email' => 's@x.bh']);
    expect($a->logo_path)->toBeNull()->and($a->phone)->toBeNull();
});
```

- [ ] **Step 2: Run the test**

Run: `./vendor/bin/pest tests/Feature/AssociationModelTest.php` → FAIL, `Class "App\Models\Association" not found`.

- [ ] **Step 3: Implement the migration, model and factory**

Migration `up()`:
```php
Schema::create('associations', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->string('email')->unique();
    $table->string('logo_path')->nullable();
    $table->string('phone')->nullable();
    $table->string('address')->nullable();
    $table->string('website')->nullable();
    $table->timestamps();
});
```
`app/Models/Association.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Association extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'logo_path', 'phone', 'address', 'website'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
```
`database/factories/AssociationFactory.php`:
```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AssociationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Bahrain '.fake()->unique()->word().' Association',
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
```

- [ ] **Step 4: Run the test again**

Run: `./vendor/bin/pest tests/Feature/AssociationModelTest.php` → PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: associations table and model"
```

---

### Task 2: Roles, panel access and portal tenancy

**Files:**
- Create: `app/Enums/Role.php`, `database/migrations/2026_11_08_000200_add_role_columns_to_users_table.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Providers/Filament/PortalPanelProvider.php`, `database/seeders/DatabaseSeeder.php`, `tests/Pest.php`
- Test: `tests/Feature/PanelAccessTest.php`

**Interfaces:**
- Consumes: `Association` (Task 1).
- Produces:
  - `Role` enum, with `isAssociationRole(): bool` and `label(): string`.
  - `User`: `role` (cast to `Role`), `association_id`, `is_active`, `association(): BelongsTo`, `isSuperAdmin(): bool`, `canAccessPanel()`, `getTenants()` and `canAccessTenant()`.
  - Factory states `superAdmin()`, `sta()`, `assocAdmin(?Association)`, `assocEditor(?Association)` and `inactive()`.
  - Pest helpers `actingInAdmin()` and `actingInPortal()`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/PanelAccessTest.php`:
```php
<?php

use App\Models\Association;
use App\Models\User;

it('lets super admin and STA into admin only', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $this->actingAs($user)->get('/admin')->assertOk();
    $this->actingAs($user)->get('/portal')->assertForbidden();
})->with(['superAdmin', 'sta']);

it('lets association users into their own portal only', function (string $state) {
    $assoc = Association::factory()->create();
    $user = User::factory()->{$state}($assoc)->create();
    $this->actingAs($user)->get('/admin')->assertForbidden();
    $this->actingAs($user)->get("/portal/{$assoc->id}")->assertOk();

    $other = Association::factory()->create();
    expect($this->actingAs($user)->get("/portal/{$other->id}")->status())->toBeIn([403, 404]);
})->with(['assocAdmin', 'assocEditor']);

it('blocks inactive users from every panel', function () {
    $user = User::factory()->superAdmin()->inactive()->create();
    $this->actingAs($user)->get('/admin')->assertForbidden();
});
```

- [ ] **Step 2: Run the test**

Run: `./vendor/bin/pest tests/Feature/PanelAccessTest.php` → FAIL, with an undefined factory state `superAdmin` or a missing `role` column.

- [ ] **Step 3: Implement the enum, migration, User, factory and tenancy**

`app/Enums/Role.php`:
```php
<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Sta = 'sta';
    case AssocAdmin = 'assoc_admin';
    case AssocEditor = 'assoc_editor';

    public function isAssociationRole(): bool
    {
        return $this === self::AssocAdmin || $this === self::AssocEditor;
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Sta => 'STA User',
            self::AssocAdmin => 'Association Admin',
            self::AssocEditor => 'Association Editor',
        };
    }
}
```
Migration `up()`:
```php
Schema::table('users', function (Blueprint $table) {
    $table->string('role')->default('sta')->after('password');
    $table->foreignId('association_id')->nullable()->after('role')->constrained()->restrictOnDelete();
    $table->boolean('is_active')->default(true)->after('association_id');
});
```
`down()`:
```php
Schema::table('users', function (Blueprint $table) {
    $table->dropConstrainedForeignId('association_id');
    $table->dropColumn(['role', 'is_active']);
});
```
`app/Models/User.php`. This replaces the generated file. `HasRoles` is added now for Task 3:
```php
<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'association_id', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->role === Role::SuperAdmin || $this->role === Role::Sta,
            'portal' => $this->role->isAssociationRole() && $this->association_id !== null,
            default => false,
        };
    }

    public function getTenants(Panel $panel): Collection
    {
        return collect([$this->association])->filter();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Association && $tenant->getKey() === $this->association_id;
    }
}
```
`database/factories/UserFactory.php`. Replace `definition()` and add the states:
```php
public function definition(): array
{
    return [
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => static::$password ??= Hash::make('password'),
        'remember_token' => Str::random(10),
        'role' => Role::Sta,
        'association_id' => null,
        'is_active' => true,
    ];
}

public function superAdmin(): static { return $this->state(['role' => Role::SuperAdmin]); }
public function sta(): static { return $this->state(['role' => Role::Sta]); }
public function inactive(): static { return $this->state(['is_active' => false]); }

public function assocAdmin(?Association $association = null): static
{
    return $this->state(fn () => ['role' => Role::AssocAdmin, 'association_id' => $association?->id ?? Association::factory()]);
}

public function assocEditor(?Association $association = null): static
{
    return $this->state(fn () => ['role' => Role::AssocEditor, 'association_id' => $association?->id ?? Association::factory()]);
}
```
Add `use App\Enums\Role; use App\Models\Association;` to the factory.

`PortalPanelProvider`, after `->login()`, add:
```php
    ->tenant(\App\Models\Association::class)
```
The ownership relationship defaults to `association`, and every tenant-scoped model in this plan defines `association()`.

`database/seeders/DatabaseSeeder.php`, `run()`:
```php
$this->call(PermissionSeeder::class);

User::query()->firstOrCreate(
    ['email' => env('SUPER_ADMIN_EMAIL', 'admin@example.com')],
    ['name' => env('SUPER_ADMIN_NAME', 'Super Admin'), 'password' => env('SUPER_ADMIN_PASSWORD', 'change-me-now'), 'role' => Role::SuperAdmin],
);
```
`PermissionSeeder` is created in Task 3. Until then, comment out the `$this->call(...)` line and restore it in Task 3, Step 3.

Append the helpers to `tests/Pest.php`:
```php
use App\Models\User;
use Filament\Facades\Filament;

function actingInAdmin(User $user): User
{
    test()->actingAs($user);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    return $user;
}

function actingInPortal(User $user): User
{
    test()->actingAs($user);
    Filament::setCurrentPanel('portal');
    Filament::setTenant($user->association);
    Filament::bootCurrentPanel();

    return $user;
}
```

- [ ] **Step 4: Run the tests**

Run: `./vendor/bin/pest tests/Feature/PanelAccessTest.php tests/Feature/SmokeTest.php` → PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: roles, panel access and association tenancy"
```

---

### Task 3: STA permissions and STA user management

**Files:**
- Create: `app/Enums/StaPermission.php`, `database/seeders/PermissionSeeder.php`, `app/Filament/Concerns/RequiresStaPermission.php`
- Create: `app/Filament/Resources/StaUsers/StaUserResource.php`, `.../Schemas/StaUserForm.php`, `.../Tables/StaUsersTable.php`, and `.../Pages/{ListStaUsers,CreateStaUser,EditStaUser}.php` (generated)
- Modify: `app/Providers/AppServiceProvider.php`, `database/seeders/DatabaseSeeder.php`, `tests/Pest.php`
- Test: `tests/Feature/StaUserResourceTest.php`

**Interfaces:**
- Consumes: `User`, `Role`, `actingInAdmin()`.
- Produces:
  - `StaPermission` enum. Its cases are the 9 permissions in scope section 3.3, and it has `label()`.
  - Trait `RequiresStaPermission`. A resource or page using it implements `protected static function requiredPermission(): StaPermission`, and the trait provides `canAccess()`.
  - `Gate::before` lets Super Admin do everything.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\Role;
use App\Enums\StaPermission;
use App\Filament\Resources\StaUsers\Pages\CreateStaUser;
use App\Models\User;
use function Pest\Livewire\livewire;

it('lets super admin create an STA user with permissions', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    $permissionIds = \Spatie\Permission\Models\Permission::whereIn('name', [
        StaPermission::ManageAssociations->value, StaPermission::ViewReports->value,
    ])->pluck('id')->all();

    livewire(CreateStaUser::class)
        ->fillForm(['name' => 'Fatima', 'email' => 'fatima@sta.bh', 'password' => 'a-long-password-1', 'is_active' => true, 'permissions' => $permissionIds])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'fatima@sta.bh')->first();
    expect($user->role)->toBe(Role::Sta)
        ->and($user->association_id)->toBeNull()
        ->and($user->can(StaPermission::ManageAssociations->value))->toBeTrue()
        ->and($user->can(StaPermission::ManageGames->value))->toBeFalse();
});

it('hides STA user management from STA users', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::cases());
    $this->actingAs($sta)->get('/admin/sta-users')->assertForbidden();
});

it('gives super admin every permission', function () {
    expect(User::factory()->superAdmin()->create()->can(StaPermission::ViewAuditLog->value))->toBeTrue();
});
```

- [ ] **Step 2: Run the test**

Run: `./vendor/bin/pest tests/Feature/StaUserResourceTest.php` → FAIL, `Class "App\Enums\StaPermission" not found`.

- [ ] **Step 3: Implement the permissions**

`app/Enums/StaPermission.php`:
```php
<?php

namespace App\Enums;

enum StaPermission: string
{
    case ManageAssociations = 'manage_associations';
    case ManageMasterData = 'manage_master_data';
    case ManageGames = 'manage_games';
    case ViewSubmissions = 'view_submissions';
    case EditSubmissions = 'edit_submissions';
    case ManagePeople = 'manage_people';
    case SendReminders = 'send_reminders';
    case ViewReports = 'view_reports';
    case ViewAuditLog = 'view_audit_log';

    public function label(): string
    {
        return match ($this) {
            self::ManageAssociations => 'Manage associations and invitations',
            self::ManageMasterData => 'Manage sports, events, clothing items and settings',
            self::ManageGames => 'Manage games, limits and phases',
            self::ViewSubmissions => 'View submissions and PDFs',
            self::EditSubmissions => 'Edit submissions and comment',
            self::ManagePeople => 'View and edit people of any association',
            self::SendReminders => 'Send "Remind pending"',
            self::ViewReports => 'View reports and export Excel',
            self::ViewAuditLog => 'View the audit log',
        };
    }
}
```
`database/seeders/PermissionSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Enums\StaPermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (StaPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
```
In `DatabaseSeeder`, uncomment `$this->call(PermissionSeeder::class);`.

In `AppServiceProvider::boot()`:
```php
\Illuminate\Support\Facades\Gate::before(
    fn (\App\Models\User $user) => $user->isSuperAdmin() ? true : null
);
```
`app/Filament/Concerns/RequiresStaPermission.php`:
```php
<?php

namespace App\Filament\Concerns;

use App\Enums\StaPermission;

trait RequiresStaPermission
{
    abstract protected static function requiredPermission(): StaPermission;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(static::requiredPermission()->value);
    }
}
```
In `tests/Pest.php`, change the `pest()` line to seed permissions for every feature test:
```php
pest()->extend(TestCase::class)->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(\Database\Seeders\PermissionSeeder::class))
    ->in('Feature');
```

- [ ] **Step 4: Generate and write the STA user resource**

Run: `php artisan make:filament-resource StaUser --model=User --panel=admin --no-interaction`. This generates the files under `app/Filament/Resources/StaUsers/`. Then set the files as follows.

`StaUserResource.php`:
```php
<?php

namespace App\Filament\Resources\StaUsers;

use App\Enums\Role;
use App\Filament\Resources\StaUsers\Pages;
use App\Filament\Resources\StaUsers\Schemas\StaUserForm;
use App\Filament\Resources\StaUsers\Tables\StaUsersTable;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaUserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $slug = 'sta-users';
    protected static ?string $modelLabel = 'STA user';
    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', Role::Sta);
    }

    public static function form(Schema $schema): Schema { return StaUserForm::configure($schema); }
    public static function table(Table $table): Table { return StaUsersTable::configure($table); }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaUsers::route('/'),
            'create' => Pages\CreateStaUser::route('/create'),
            'edit' => Pages\EditStaUser::route('/{record}/edit'),
        ];
    }
}
```
`Schemas/StaUserForm.php`:
```php
<?php

namespace App\Filament\Resources\StaUsers\Schemas;

use App\Enums\StaPermission;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Permission;

class StaUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->minLength(12)
                ->dehydrated(fn (?string $state) => filled($state)),
            Toggle::make('is_active')->default(true),
            CheckboxList::make('permissions')
                ->relationship(titleAttribute: 'name')
                ->getOptionLabelFromRecordUsing(fn (Permission $record) => StaPermission::from($record->name)->label())
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }
}
```
`Tables/StaUsersTable.php`:
```php
<?php

namespace App\Filament\Resources\StaUsers\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StaUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('permissions_count')->counts('permissions')->label('Permissions'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }
}
```
In `Pages/CreateStaUser.php`, add inside the class:
```php
protected function mutateFormDataBeforeCreate(array $data): array
{
    return [...$data, 'role' => \App\Enums\Role::Sta, 'association_id' => null];
}
```

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Feature/StaUserResourceTest.php` → PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: STA permission checkboxes and STA user management"
```

---

### Task 4: Association management (admin) and association profile (portal)

**Files:**
- Create: `app/Filament/Resources/Associations/{AssociationResource.php, Schemas/AssociationForm.php, Tables/AssociationsTable.php, Pages/*}` (generated)
- Create: `app/Filament/Portal/Pages/EditAssociationProfile.php`
- Modify: `app/Providers/Filament/PortalPanelProvider.php`
- Test: `tests/Feature/AssociationResourceTest.php`

**Interfaces:**
- Consumes: `RequiresStaPermission`, `StaPermission::ManageAssociations`, `config('filesystems.private')`.
- Produces:
  - `AssociationResource`, at URL `/admin/associations`.
  - Record page `EditAssociation`. Task 6 adds its relation managers.
  - Portal tenant profile page `EditAssociationProfile`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\StaPermission;
use App\Filament\Portal\Pages\EditAssociationProfile;
use App\Filament\Resources\Associations\Pages\CreateAssociation;
use App\Models\Association;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Livewire\livewire;

it('lets STA with permission create an association with a private logo', function () {
    Storage::fake(config('filesystems.private'));
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ManageAssociations->value);
    actingInAdmin($sta);

    livewire(CreateAssociation::class)
        ->fillForm(['name' => 'Bahrain Athletics', 'email' => 'info@bah-athletics.bh', 'logo_path' => UploadedFile::fake()->image('logo.png')])
        ->call('create')
        ->assertHasNoFormErrors();

    $assoc = Association::firstWhere('name', 'Bahrain Athletics');
    Storage::disk(config('filesystems.private'))->assertExists($assoc->logo_path);
});

it('requires name and email', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(CreateAssociation::class)->fillForm(['name' => null, 'email' => null])->call('create')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'required']);
});

it('forbids STA without the permission', function () {
    $this->actingAs(User::factory()->sta()->create())->get('/admin/associations')->assertForbidden();
});

it('lets the association admin edit the profile but not the name', function () {
    $assoc = Association::factory()->create(['name' => 'Squash']);
    actingInPortal(User::factory()->assocAdmin($assoc)->create());

    livewire(EditAssociationProfile::class)
        ->fillForm(['phone' => '+973 1700 0000'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($assoc->fresh()->phone)->toBe('+973 1700 0000')->and($assoc->fresh()->name)->toBe('Squash');
});

it('blocks editors from the profile page', function () {
    $assoc = Association::factory()->create();
    $editor = User::factory()->assocEditor($assoc)->create();
    $this->actingAs($editor)->get("/portal/{$assoc->id}/profile")->assertForbidden();
});
```

- [ ] **Step 2: Run the test**

Run: `./vendor/bin/pest tests/Feature/AssociationResourceTest.php` → FAIL, because the classes don't exist yet.

- [ ] **Step 3: Generate and write the admin resource**

Run: `php artisan make:filament-resource Association --panel=admin --no-interaction`.

`AssociationResource.php`. Keep the generated `getPages()` with index, create and edit, and add:
```php
use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;

class AssociationResource extends Resource
{
    use RequiresStaPermission;

    protected static ?string $model = Association::class;
    protected static ?string $recordTitleAttribute = 'name';

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ManageAssociations;
    }

    public static function form(Schema $schema): Schema { return AssociationForm::configure($schema); }
    public static function table(Table $table): Table { return AssociationsTable::configure($table); }
    // getPages(): generated (index, create, edit)
}
```
`Schemas/AssociationForm.php`:
```php
<?php

namespace App\Filament\Resources\Associations\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AssociationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            ...self::profileFields(),
        ]);
    }

    /** Optional profile fields, shared with the portal profile page. */
    public static function profileFields(): array
    {
        return [
            TextInput::make('phone')->tel()->maxLength(50),
            TextInput::make('website')->url()->maxLength(255),
            TextInput::make('address')->maxLength(255)->columnSpanFull(),
            FileUpload::make('logo_path')->label('Logo')->image()->maxSize(2048)
                ->disk(config('filesystems.private'))->directory('logos')->visibility('private'),
        ];
    }
}
```
`Tables/AssociationsTable.php`:
```php
<?php

namespace App\Filament\Resources\Associations\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssociationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')->label('')->disk(config('filesystems.private'))->visibility('private')->circular(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('users_count')->counts('users')->label('Users'),
            ])
            ->recordActions([EditAction::make()]);
    }
}
```
There is deliberately no delete action (BR-16).

- [ ] **Step 4: Write the portal profile page**

`app/Filament/Portal/Pages/EditAssociationProfile.php`:
```php
<?php

namespace App\Filament\Portal\Pages;

use App\Enums\Role;
use App\Filament\Resources\Associations\Schemas\AssociationForm;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditAssociationProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Association profile';
    }

    public static function canView(Model $tenant): bool
    {
        return auth()->user()?->role === Role::AssocAdmin;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->disabled()->dehydrated(false),
            TextInput::make('email')->disabled()->dehydrated(false),
            ...AssociationForm::profileFields(),
        ]);
    }
}
```
`PortalPanelProvider`, after `->tenant(...)`:
```php
    ->tenantProfile(\App\Filament\Portal\Pages\EditAssociationProfile::class)
```

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Feature/AssociationResourceTest.php` → PASS.
If the editor case returns 404 instead of 403, Filament hides tenant-profile routes from users who fail `canView`. In that case change the assertion to `->assertStatus(403)` or `assertNotFound()` to match the observed framework behaviour, and note it in the commit message.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: association management and portal association profile"
```

---

### Task 5: Invitations – service, email and accept page

**Files:**
- Create: `database/migrations/2026_11_08_000300_create_invitations_table.php`, `app/Models/Invitation.php`
- Create: `app/Services/AssociationMembership.php`, `app/Services/InvitationService.php`
- Create: `app/Mail/InvitationMail.php`, `resources/views/emails/invitation.blade.php`
- Create: `app/Http/Controllers/InvitationController.php`, `resources/views/invitations/accept.blade.php`, `resources/views/invitations/invalid.blade.php`
- Modify: `routes/web.php`, `app/Models/Association.php` (add `invitations()`)
- Test: `tests/Feature/InvitationServiceTest.php`, `tests/Feature/InvitationAcceptTest.php`

**Interfaces:**
- Consumes: `Association`, `User`, `Role`.
- Produces:
  - `Invitation` model, with `scopePending()`, `isPending()`, `status(): string` (`pending|accepted|revoked|expired`) and `association(): BelongsTo`.
  - `AssociationMembership`:
    - `ensureCanAddEditor(Association $a, ?Invitation $ignore = null): void`
    - `ensureCanAddAdmin(Association $a, ?Invitation $ignore = null): void`
    - `setActive(User $user, bool $active): void`
    - Each throws `Illuminate\Validation\ValidationException` with the key `email` when it blocks.
  - `InvitationService`:
    - `invite(Association $a, string $email, Role $role, ?User $invitedBy): string`. Returns the accept URL.
    - `accept(string $token, string $name, string $password): User`
    - `findPending(string $token): ?Invitation`
    - `revoke(Invitation $i): void`
  - Route names `invitations.show` and `invitations.accept`.

- [ ] **Step 1: Write the failing service tests**

`tests/Feature/InvitationServiceTest.php`:
```php
<?php

use App\Enums\Role;
use App\Mail\InvitationMail;
use App\Models\Association;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Mail::fake();
    $this->service = app(InvitationService::class);
    $this->assoc = Association::factory()->create();
});

function tokenFrom(string $url): string
{
    return basename(parse_url($url, PHP_URL_PATH));
}

it('stores only a hash of the token and emails the link', function () {
    $url = $this->service->invite($this->assoc, 'Head@Assoc.bh', Role::AssocAdmin, null);
    $token = tokenFrom($url);
    $inv = Invitation::sole();

    expect($inv->email)->toBe('head@assoc.bh')
        ->and($inv->token_hash)->toBe(hash('sha256', $token))
        ->and($inv->token_hash)->not->toBe($token)
        ->and($inv->expires_at->isSameDay(now()->addDays(7)))->toBeTrue();
    Mail::assertQueued(InvitationMail::class, fn ($m) => $m->hasTo('head@assoc.bh') && $m->url === $url);
});

it('creates the user on accept and is single use', function () {
    $token = tokenFrom($this->service->invite($this->assoc, 'ed@assoc.bh', Role::AssocEditor, null));
    $user = $this->service->accept($token, 'Editor One', 'a-long-password-1');

    expect($user->role)->toBe(Role::AssocEditor)->and($user->association_id)->toBe($this->assoc->id)
        ->and(Invitation::sole()->accepted_at)->not->toBeNull();
    expect(fn () => $this->service->accept($token, 'Again', 'a-long-password-1'))->toThrow(ValidationException::class);
});

it('rejects expired and revoked invitations', function () {
    $token = tokenFrom($this->service->invite($this->assoc, 'x@assoc.bh', Role::AssocEditor, null));
    $this->travel(8)->days();
    expect(fn () => $this->service->accept($token, 'X', 'a-long-password-1'))->toThrow(ValidationException::class);
});

it('caps editors at two, counting active editors and pending invites', function () {
    User::factory()->assocEditor($this->assoc)->create();
    $this->service->invite($this->assoc, 'two@assoc.bh', Role::AssocEditor, null);
    expect(fn () => $this->service->invite($this->assoc, 'three@assoc.bh', Role::AssocEditor, null))
        ->toThrow(ValidationException::class);
});

it('allows only one active association admin', function () {
    User::factory()->assocAdmin($this->assoc)->create();
    expect(fn () => $this->service->invite($this->assoc, 'admin2@assoc.bh', Role::AssocAdmin, null))
        ->toThrow(ValidationException::class);
});

it('refuses emails that already have an account', function () {
    User::factory()->create(['email' => 'taken@assoc.bh']);
    expect(fn () => $this->service->invite($this->assoc, 'taken@assoc.bh', Role::AssocEditor, null))
        ->toThrow(ValidationException::class);
});

it('re-inviting the same email revokes the older invitation', function () {
    $first = tokenFrom($this->service->invite($this->assoc, 'ed@assoc.bh', Role::AssocEditor, null));
    $this->service->invite($this->assoc, 'ed@assoc.bh', Role::AssocEditor, null);
    expect($this->service->findPending($first))->toBeNull()
        ->and(Invitation::pending()->count())->toBe(1);
});
```

- [ ] **Step 2: Run the tests**

Run: `./vendor/bin/pest tests/Feature/InvitationServiceTest.php` → FAIL, `Class "App\Services\InvitationService" not found`.

- [ ] **Step 3: Implement the migration, model and services**

Migration `up()`:
```php
Schema::create('invitations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('association_id')->constrained()->cascadeOnDelete();
    $table->string('email');
    $table->string('role');
    $table->string('token_hash', 64)->unique();
    $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('expires_at');
    $table->timestamp('accepted_at')->nullable();
    $table->timestamp('revoked_at')->nullable();
    $table->timestamps();
    $table->index(['association_id', 'email']);
});
```
`app/Models/Invitation.php`:
```php
<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $fillable = ['association_id', 'email', 'role', 'token_hash', 'invited_by', 'expires_at', 'accepted_at', 'revoked_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['role' => Role::class, 'expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isPending(): bool
    {
        return $this->status() === 'pending';
    }

    public function status(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->revoked_at !== null => 'revoked',
            $this->expires_at->isPast() => 'expired',
            default => 'pending',
        };
    }
}
```
Add to `Association`:
```php
public function invitations(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Invitation::class);
}
```
`app/Services/AssociationMembership.php`:
```php
<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Association;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class AssociationMembership
{
    public const MAX_EDITORS = 2;

    public function ensureCanAddEditor(Association $association, ?Invitation $ignore = null): void
    {
        if ($this->seatCount($association, Role::AssocEditor, $ignore) >= self::MAX_EDITORS) {
            throw ValidationException::withMessages(['email' => 'This association already has 2 editors (active or invited).']);
        }
    }

    public function ensureCanAddAdmin(Association $association, ?Invitation $ignore = null): void
    {
        if ($this->seatCount($association, Role::AssocAdmin, $ignore) >= 1) {
            throw ValidationException::withMessages(['email' => 'This association already has an active or invited admin. Deactivate or revoke it first.']);
        }
    }

    public function setActive(User $user, bool $active): void
    {
        if ($active && ! $user->is_active) {
            $user->role === Role::AssocEditor
                ? $this->ensureCanAddEditor($user->association)
                : $this->ensureCanAddAdmin($user->association);
        }
        $user->update(['is_active' => $active]);
    }

    private function seatCount(Association $association, Role $role, ?Invitation $ignore): int
    {
        $active = $association->users()->where('role', $role)->where('is_active', true)->count();
        $pending = Invitation::pending()
            ->where('association_id', $association->id)
            ->where('role', $role)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->count();

        return $active + $pending;
    }
}
```
`app/Services/InvitationService.php`:
```php
<?php

namespace App\Services;

use App\Enums\Role;
use App\Mail\InvitationMail;
use App\Models\Association;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class InvitationService
{
    public const EXPIRY_DAYS = 7;

    public function __construct(private readonly AssociationMembership $membership) {}

    /** Creates an invitation, emails it and returns the accept URL (also shown to the admin for copying). */
    public function invite(Association $association, string $email, Role $role, ?User $invitedBy): string
    {
        if (! $role->isAssociationRole()) {
            throw new InvalidArgumentException('Only association roles can be invited.');
        }
        $email = Str::lower(trim($email));

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'A user with this email already exists.']);
        }

        return DB::transaction(function () use ($association, $email, $role, $invitedBy) {
            Invitation::pending()->where('association_id', $association->id)->where('email', $email)
                ->update(['revoked_at' => now()]);

            $role === Role::AssocEditor
                ? $this->membership->ensureCanAddEditor($association)
                : $this->membership->ensureCanAddAdmin($association);

            $token = Str::random(48);
            $invitation = Invitation::create([
                'association_id' => $association->id,
                'email' => $email,
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'invited_by' => $invitedBy?->id,
                'expires_at' => now()->addDays(self::EXPIRY_DAYS),
            ]);

            $url = route('invitations.show', $token);
            Mail::to($email)->queue(new InvitationMail($invitation, $url));

            return $url;
        });
    }

    public function findPending(string $token): ?Invitation
    {
        return Invitation::pending()->where('token_hash', hash('sha256', $token))->first();
    }

    public function accept(string $token, string $name, string $password): User
    {
        return DB::transaction(function () use ($token, $name, $password) {
            $invitation = Invitation::pending()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $invitation) {
                throw ValidationException::withMessages(['token' => 'This invitation is invalid or has expired.']);
            }

            $invitation->role === Role::AssocEditor
                ? $this->membership->ensureCanAddEditor($invitation->association, ignore: $invitation)
                : $this->membership->ensureCanAddAdmin($invitation->association, ignore: $invitation);

            $user = User::create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
                'role' => $invitation->role,
                'association_id' => $invitation->association_id,
                'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
    }

    public function revoke(Invitation $invitation): void
    {
        $invitation->update(['revoked_at' => now()]);
    }
}
```
`app/Mail/InvitationMail.php`:
```php
<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Invitation to the Sports Entry Portal – '.$this->invitation->association->name);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.invitation');
    }
}
```
`resources/views/emails/invitation.blade.php`:
```blade
<x-mail::message>
# You are invited

You have been invited to join **{{ $invitation->association->name }}** on the Sports Entry Portal as **{{ $invitation->role->label() }}**.

<x-mail::button :url="$url">Accept invitation</x-mail::button>

This link can be used once and expires on {{ $invitation->expires_at->format('d M Y') }}.
</x-mail::message>
```

- [ ] **Step 4: Run the service tests**

Run: `./vendor/bin/pest tests/Feature/InvitationServiceTest.php` → it fails only on `Route [invitations.show] not defined`. Continue to Step 5.

- [ ] **Step 5: Write the failing accept-page test**

`tests/Feature/InvitationAcceptTest.php`:
```php
<?php

use App\Enums\Role;
use App\Models\Association;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->assoc = Association::factory()->create(['name' => 'Bahrain Squash']);
    $this->url = app(InvitationService::class)->invite($this->assoc, 'boss@squash.bh', Role::AssocAdmin, null);
});

it('shows the accept form', function () {
    $this->get($this->url)->assertOk()->assertSee('Bahrain Squash')->assertSee('boss@squash.bh');
});

it('creates the account, logs in and redirects to the portal', function () {
    $this->post($this->url, ['name' => 'Ali', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1'])
        ->assertRedirect("/portal/{$this->assoc->id}");

    $this->assertAuthenticatedAs(User::firstWhere('email', 'boss@squash.bh'));
});

it('validates the password', function () {
    $this->post($this->url, ['name' => 'Ali', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');
});

it('shows an invalid page for unknown tokens', function () {
    $this->get('/invitations/not-a-real-token')->assertStatus(410)->assertSee('invalid or has expired');
});
```

- [ ] **Step 6: Implement the routes, controller and views**

`routes/web.php`. Add:
```php
use App\Http\Controllers\InvitationController;

Route::middleware(['guest', 'throttle:10,1'])->group(function () {
    Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{token}', [InvitationController::class, 'store'])->name('invitations.accept');
});
```
`app/Http/Controllers/InvitationController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Services\InvitationService;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function show(string $token): Response
    {
        $invitation = $this->invitations->findPending($token);

        return $invitation
            ? response()->view('invitations.accept', ['invitation' => $invitation, 'token' => $token])
            : response()->view('invitations.invalid', status: 410);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $user = $this->invitations->accept($token, $data['name'], $data['password']);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect(Filament::getPanel('portal')->getUrl($user->association));
    }
}
```
`resources/views/invitations/accept.blade.php`:
```blade
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accept invitation</title>
    <style>
        body{font-family:system-ui,Arial,sans-serif;background:#f1f5f9;margin:0;padding:24px}
        main{max-width:420px;margin:40px auto;background:#fff;padding:28px;border-radius:12px}
        label{display:block;margin-top:14px;font-weight:600}
        input{width:100%;padding:10px;margin-top:6px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}
        button{margin-top:20px;width:100%;padding:12px;background:#1f3a5f;color:#fff;border:0;border-radius:8px;font-weight:600}
        .error{color:#b91c1c;font-size:14px}
    </style>
</head>
<body>
<main>
    <h1>Join {{ $invitation->association->name }}</h1>
    <p>You are invited as <strong>{{ $invitation->role->label() }}</strong> with <strong>{{ $invitation->email }}</strong>.</p>
    <form method="post" action="{{ route('invitations.accept', $token) }}">
        @csrf
        <label for="name">Your full name</label>
        <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name">
        @error('name')<p class="error">{{ $message }}</p>@enderror
        <label for="password">Password (at least 12 characters)</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">
        @error('password')<p class="error">{{ $message }}</p>@enderror
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        @error('token')<p class="error">{{ $message }}</p>@enderror
        <button type="submit">Create account</button>
    </form>
</main>
</body>
</html>
```
`resources/views/invitations/invalid.blade.php`:
```blade
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Invitation unavailable</title></head>
<body style="font-family:system-ui,Arial,sans-serif;padding:40px">
<h1>Invitation unavailable</h1>
<p>This invitation is invalid or has expired. Please ask your administrator to send a new one.</p>
</body></html>
```

- [ ] **Step 7: Run both test files**

Run: `./vendor/bin/pest tests/Feature/InvitationServiceTest.php tests/Feature/InvitationAcceptTest.php` → PASS.

- [ ] **Step 8: Commit**

```bash
git add -A && git commit -m "feat: invitation service, email and accept page with editor and admin caps"
```

---

### Task 6: Invitation and member management UI

**Files:**
- Create: `app/Filament/Resources/Associations/RelationManagers/InvitationsRelationManager.php`, `.../UsersRelationManager.php`
- Modify: `app/Filament/Resources/Associations/AssociationResource.php` (`getRelations()`), `app/Filament/Resources/Associations/Tables/AssociationsTable.php` (Invite action)
- Create: `app/Filament/Portal/Resources/Members/{MemberResource.php, Pages/ListMembers.php}`, `app/Filament/Portal/Resources/Invitations/{InvitationResource.php, Pages/ListInvitations.php}`
- Create: `app/Filament/Shared/InvitationActions.php`
- Test: `tests/Feature/InvitationUiTest.php`

**Interfaces:**
- Consumes: `InvitationService`, `AssociationMembership`, `Invitation::status()`.
- Produces:
  - Admin table action `invite` on associations.
  - Relation managers `InvitationsRelationManager` (actions `resend`, `revoke`) and `UsersRelationManager` (actions `deactivate`, `activate`).
  - Portal `ListMembers` (header action `inviteEditor`, record actions `deactivate` and `activate`).
  - Portal `ListInvitations` (`resend`, `revoke`).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\Role;
use App\Filament\Portal\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Associations\Pages\ListAssociations;
use App\Models\Association;
use App\Models\Invitation;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Mail;
use function Pest\Livewire\livewire;

beforeEach(fn () => Mail::fake());

it('lets STA invite the association admin from the list', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $assoc = Association::factory()->create(['email' => 'info@judo.bh']);

    livewire(ListAssociations::class)
        ->callAction(TestAction::make('invite')->table($assoc), data: ['email' => 'info@judo.bh'])
        ->assertNotified();

    expect(Invitation::sole())->role->toBe(Role::AssocAdmin)->email->toBe('info@judo.bh');
});

it('lets the association admin invite editors up to the cap', function () {
    $assoc = Association::factory()->create();
    actingInPortal(User::factory()->assocAdmin($assoc)->create());

    livewire(ListMembers::class)->callAction('inviteEditor', data: ['email' => 'e1@x.bh'])->assertNotified();
    livewire(ListMembers::class)->callAction('inviteEditor', data: ['email' => 'e2@x.bh'])->assertNotified();
    livewire(ListMembers::class)->callAction('inviteEditor', data: ['email' => 'e3@x.bh'])->assertNotified();

    expect(Invitation::where('role', Role::AssocEditor)->count())->toBe(2);
});

it('lets the association admin deactivate an editor', function () {
    $assoc = Association::factory()->create();
    actingInPortal(User::factory()->assocAdmin($assoc)->create());
    $editor = User::factory()->assocEditor($assoc)->create();

    livewire(ListMembers::class)->callAction(TestAction::make('deactivate')->table($editor));

    expect($editor->fresh()->is_active)->toBeFalse();
});

it('hides member management from editors', function () {
    $assoc = Association::factory()->create();
    $editor = User::factory()->assocEditor($assoc)->create();
    $this->actingAs($editor)->get("/portal/{$assoc->id}/members")->assertForbidden();
});
```

- [ ] **Step 2: Run the test**

Run: `./vendor/bin/pest tests/Feature/InvitationUiTest.php` → FAIL, because the classes don't exist yet.

- [ ] **Step 3: Write the shared action builders**

`app/Filament/Shared/InvitationActions.php`:
```php
<?php

namespace App\Filament\Shared;

use App\Enums\Role;
use App\Models\Association;
use App\Models\Invitation;
use App\Models\User;
use App\Services\AssociationMembership;
use App\Services\InvitationService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

final class InvitationActions
{
    /** @param Closure(?Association): Association $association resolves the target association */
    public static function invite(string $name, Role $role, Closure $association, ?Closure $defaultEmail = null): Action
    {
        return Action::make($name)
            ->label($role === Role::AssocAdmin ? 'Invite admin' : 'Invite editor')
            ->icon('heroicon-o-envelope')
            ->schema([TextInput::make('email')->email()->required()->default($defaultEmail)])
            ->action(function (array $data, ?Association $record) use ($role, $association) {
                self::guard(function () use ($data, $record, $role, $association) {
                    $url = app(InvitationService::class)->invite($association($record), $data['email'], $role, auth()->user());
                    self::linkNotification($url);
                });
            });
    }

    public static function resend(): Action
    {
        return Action::make('resend')->icon('heroicon-o-arrow-path')
            ->visible(fn (Invitation $record) => in_array($record->status(), ['pending', 'expired'], true))
            ->action(fn (Invitation $record) => self::guard(function () use ($record) {
                self::linkNotification(app(InvitationService::class)->invite($record->association, $record->email, $record->role, auth()->user()));
            }));
    }

    public static function revoke(): Action
    {
        return Action::make('revoke')->color('danger')->requiresConfirmation()
            ->visible(fn (Invitation $record) => $record->isPending())
            ->action(fn (Invitation $record) => app(InvitationService::class)->revoke($record));
    }

    public static function setActive(bool $active): Action
    {
        return Action::make($active ? 'activate' : 'deactivate')
            ->color($active ? 'success' : 'danger')
            ->requiresConfirmation()
            ->visible(fn (User $record) => $record->is_active !== $active && $record->id !== auth()->id())
            ->action(fn (User $record) => self::guard(fn () => app(AssociationMembership::class)->setActive($record, $active)));
    }

    private static function guard(Closure $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
        }
    }

    private static function linkNotification(string $url): void
    {
        Notification::make()->success()->persistent()
            ->title('Invitation sent')
            ->body("The invitation email has been sent. You can also share this link (valid 7 days, single use):\n{$url}")
            ->send();
    }
}
```

- [ ] **Step 4: Wire up the admin side**

In `AssociationsTable::configure()`, set:
```php
->recordActions([
    EditAction::make(),
    \App\Filament\Shared\InvitationActions::invite('invite', \App\Enums\Role::AssocAdmin,
        association: fn (\App\Models\Association $record) => $record,
        defaultEmail: fn (?\App\Models\Association $record) => $record?->email),
])
```
Run `php artisan make:filament-relation-manager AssociationResource invitations email --panel=admin`. Then write `InvitationsRelationManager`:
```php
<?php

namespace App\Filament\Resources\Associations\RelationManagers;

use App\Filament\Shared\InvitationActions;
use App\Models\Invitation;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'invitations';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email'),
                TextColumn::make('role')->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('status')->state(fn (Invitation $record) => $record->status())->badge()
                    ->color(fn (string $state) => match ($state) { 'pending' => 'warning', 'accepted' => 'success', default => 'gray' }),
                TextColumn::make('expires_at')->dateTime('d M Y'),
            ])
            ->recordActions([InvitationActions::resend(), InvitationActions::revoke()]);
    }
}
```
Run `php artisan make:filament-relation-manager AssociationResource users name --panel=admin`. Then write `UsersRelationManager`:
```php
<?php

namespace App\Filament\Resources\Associations\RelationManagers;

use App\Filament\Shared\InvitationActions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('email'),
                TextColumn::make('role')->formatStateUsing(fn ($state) => $state->label()),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([InvitationActions::setActive(false), InvitationActions::setActive(true)]);
    }
}
```
In `AssociationResource`:
```php
public static function getRelations(): array
{
    return [RelationManagers\UsersRelationManager::class, RelationManagers\InvitationsRelationManager::class];
}
```

- [ ] **Step 5: Wire up the portal side**

`app/Filament/Portal/Resources/Members/MemberResource.php`:
```php
<?php

namespace App\Filament\Portal\Resources\Members;

use App\Enums\Role;
use App\Filament\Shared\InvitationActions;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $slug = 'members';
    protected static ?string $navigationLabel = 'Team';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === Role::AssocAdmin;
    }

    public static function canCreate(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('email'),
                TextColumn::make('role')->formatStateUsing(fn (Role $state) => $state->label()),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([InvitationActions::setActive(false), InvitationActions::setActive(true)]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListMembers::route('/')];
    }
}
```
`Members/Pages/ListMembers.php`:
```php
<?php

namespace App\Filament\Portal\Resources\Members\Pages;

use App\Enums\Role;
use App\Filament\Portal\Resources\Members\MemberResource;
use App\Filament\Shared\InvitationActions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [InvitationActions::invite('inviteEditor', Role::AssocEditor, association: fn () => Filament::getTenant())];
    }
}
```
`app/Filament/Portal/Resources/Invitations/InvitationResource.php`:
```php
<?php

namespace App\Filament\Portal\Resources\Invitations;

use App\Enums\Role;
use App\Filament\Shared\InvitationActions;
use App\Models\Invitation;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvitationResource extends Resource
{
    protected static ?string $model = Invitation::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === Role::AssocAdmin;
    }

    public static function canCreate(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email'),
                TextColumn::make('status')->state(fn (Invitation $record) => $record->status())->badge(),
                TextColumn::make('expires_at')->dateTime('d M Y'),
            ])
            ->recordActions([InvitationActions::resend(), InvitationActions::revoke()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListInvitations::route('/')];
    }
}
```
`Invitations/Pages/ListInvitations.php`:
```php
<?php

namespace App\Filament\Portal\Resources\Invitations\Pages;

use App\Filament\Portal\Resources\Invitations\InvitationResource;
use Filament\Resources\Pages\ListRecords;

class ListInvitations extends ListRecords
{
    protected static string $resource = InvitationResource::class;
}
```

- [ ] **Step 6: Run the tests**

Run: `./vendor/bin/pest tests/Feature/InvitationUiTest.php` → PASS. Then run `./vendor/bin/pest` → all green.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat: invitation and member management for STA and association admins"
```

---

### Task 7: Sports and events

**Files:**
- Create: `app/Enums/EventGender.php`, migrations `2026_11_08_000400_create_sports_table.php` and `2026_11_08_000500_create_events_table.php`
- Create: `app/Models/Sport.php`, `app/Models/Event.php`, `database/factories/SportFactory.php`, `database/factories/EventFactory.php`
- Create: `app/Filament/Resources/Sports/{SportResource.php, Schemas/SportForm.php, Tables/SportsTable.php, RelationManagers/EventsRelationManager.php, Pages/*}`
- Create: `app/Filament/Portal/Resources/Sports/{SportResource.php, Pages/ListSports.php}`
- Modify: `app/Models/Association.php` (add `sports()`)
- Test: `tests/Feature/SportsTest.php`

**Interfaces:**
- Produces:
  - `Sport`: `association_id`, `name`, `is_active`, `association()`, `events()`.
  - `Event`: `sport_id`, `name`, `gender` (`EventGender`), `is_active`, `sport()`.
  - `EventGender` cases: `Men`, `Women`, `Mixed`, with `label()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\EventGender;
use App\Enums\StaPermission;
use App\Filament\Portal\Resources\Sports\Pages\ListSports;
use App\Filament\Resources\Sports\Pages\CreateSport;
use App\Filament\Resources\Sports\Pages\EditSport;
use App\Filament\Resources\Sports\RelationManagers\EventsRelationManager;
use App\Models\Association;
use App\Models\Sport;
use App\Models\User;
use function Pest\Livewire\livewire;

it('lets STA create a sport for an association', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ManageMasterData->value);
    actingInAdmin($sta);
    $assoc = Association::factory()->create();

    livewire(CreateSport::class)->fillForm(['association_id' => $assoc->id, 'name' => 'Badminton', 'is_active' => true])
        ->call('create')->assertHasNoFormErrors();

    expect(Sport::sole())->association_id->toBe($assoc->id);
});

it('adds events and rejects a duplicate name and gender in the same sport', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $sport = Sport::factory()->create();

    $rm = fn () => livewire(EventsRelationManager::class, ['ownerRecord' => $sport, 'pageClass' => EditSport::class]);
    $rm()->callAction(\Filament\Actions\Testing\TestAction::make('create')->table(), data: ['name' => '100m', 'gender' => EventGender::Men->value, 'is_active' => true])
        ->assertHasNoFormErrors();
    $rm()->callAction(\Filament\Actions\Testing\TestAction::make('create')->table(), data: ['name' => '100m', 'gender' => EventGender::Men->value, 'is_active' => true])
        ->assertHasFormErrors(['name']);

    expect($sport->events()->count())->toBe(1);
});

it('shows associations only their own sports, read only', function () {
    $mine = Sport::factory()->create();
    $theirs = Sport::factory()->create();
    actingInPortal(User::factory()->assocEditor($mine->association)->create());

    livewire(ListSports::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs]);
    expect(\App\Filament\Portal\Resources\Sports\SportResource::canCreate())->toBeFalse();
});
```

- [ ] **Step 2: Run the test**

Run: `./vendor/bin/pest tests/Feature/SportsTest.php` → FAIL, because the classes don't exist yet.

- [ ] **Step 3: Implement the enum, migrations, models and factories**

`app/Enums/EventGender.php`:
```php
<?php

namespace App\Enums;

enum EventGender: string
{
    case Men = 'men';
    case Women = 'women';
    case Mixed = 'mixed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
```
Sports migration `up()`:
```php
Schema::create('sports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('association_id')->constrained()->restrictOnDelete();
    $table->string('name')->unique();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```
Events migration `up()`:
```php
Schema::create('events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sport_id')->constrained()->restrictOnDelete();
    $table->string('name');
    $table->string('gender');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->unique(['sport_id', 'name', 'gender']);
});
```
`app/Models/Sport.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sport extends Model
{
    use HasFactory;

    protected $fillable = ['association_id', 'name', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function association(): BelongsTo { return $this->belongsTo(Association::class); }

    public function events(): HasMany { return $this->hasMany(Event::class); }
}
```
`app/Models/Event.php`:
```php
<?php

namespace App\Models;

use App\Enums\EventGender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory;

    protected $fillable = ['sport_id', 'name', 'gender', 'is_active'];

    protected function casts(): array { return ['gender' => EventGender::class, 'is_active' => 'boolean']; }

    public function sport(): BelongsTo { return $this->belongsTo(Sport::class); }
}
```
Factories:
```php
// SportFactory::definition()
return ['association_id' => Association::factory(), 'name' => fake()->unique()->word().' Sport', 'is_active' => true];
// EventFactory::definition()
return ['sport_id' => Sport::factory(), 'name' => fake()->unique()->numberBetween(60, 10000).'m', 'gender' => EventGender::Men, 'is_active' => true];
```
Add to `Association`: `public function sports(): HasMany { return $this->hasMany(Sport::class); }`.

- [ ] **Step 4: Write the admin resource**

Run: `php artisan make:filament-resource Sport --panel=admin --no-interaction` and `php artisan make:filament-relation-manager SportResource events name --panel=admin`.

`SportResource.php`. Mirror the AssociationResource pattern, with `use RequiresStaPermission;` and `requiredPermission()` returning `StaPermission::ManageMasterData`. Set `form()` and `table()` to delegate to `SportForm` and `SportsTable`. Add `getRelations(): [RelationManagers\EventsRelationManager::class]` and keep the generated pages.

`Schemas/SportForm.php`:
```php
return $schema->components([
    \Filament\Forms\Components\Select::make('association_id')->relationship('association', 'name')->searchable()->preload()->required(),
    \Filament\Forms\Components\TextInput::make('name')->required()->maxLength(255)->unique(ignoreRecord: true),
    \Filament\Forms\Components\Toggle::make('is_active')->default(true),
]);
```
`Tables/SportsTable.php`:
```php
return $table
    ->columns([
        TextColumn::make('name')->searchable()->sortable(),
        TextColumn::make('association.name')->searchable(),
        TextColumn::make('events_count')->counts('events')->label('Events'),
        IconColumn::make('is_active')->boolean(),
    ])
    ->filters([SelectFilter::make('association')->relationship('association', 'name')])
    ->recordActions([EditAction::make()]);
```
Import `Filament\Tables\Columns\{TextColumn, IconColumn}`, `Filament\Tables\Filters\SelectFilter` and `Filament\Actions\EditAction`.

`RelationManagers/EventsRelationManager.php`:
```php
<?php

namespace App\Filament\Resources\Sports\RelationManagers;

use App\Enums\EventGender;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                    ->where('sport_id', $this->getOwnerRecord()->getKey())
                    ->where('gender', $get('gender'))),
            Select::make('gender')->options(collect(EventGender::cases())->mapWithKeys(fn ($g) => [$g->value => $g->label()]))->required(),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('gender')->formatStateUsing(fn (EventGender $state) => $state->label())->badge(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()]);
    }
}
```

- [ ] **Step 5: Write the read-only portal list**

`app/Filament/Portal/Resources/Sports/SportResource.php`:
```php
<?php

namespace App\Filament\Portal\Resources\Sports;

use App\Models\Sport;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SportResource extends Resource
{
    protected static ?string $model = Sport::class;

    public static function canCreate(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('is_active', true)->with('events'))
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('events')
                    ->state(fn (Sport $record) => $record->events->where('is_active', true)
                        ->map(fn ($e) => $e->name.' – '.$e->gender->label())->values()->all())
                    ->listWithLineBreaks(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSports::route('/')];
    }
}
```
`Pages/ListSports.php`:
```php
<?php

namespace App\Filament\Portal\Resources\Sports\Pages;

use App\Filament\Portal\Resources\Sports\SportResource;
use Filament\Resources\Pages\ListRecords;

class ListSports extends ListRecords
{
    protected static string $resource = SportResource::class;
}
```

- [ ] **Step 6: Run the tests**

Run: `./vendor/bin/pest tests/Feature/SportsTest.php` → PASS.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat: sports and events (admin) with read-only portal list"
```

---

### Task 8: Clothing items and PDF settings

**Files:**
- Create: `app/Enums/PersonType.php`, `app/Enums/AppliesTo.php`
- Create: migrations `2026_11_08_000600_create_clothing_items_table.php` and `2026_11_08_000700_create_settings_table.php`
- Create: `app/Models/ClothingItem.php`, `app/Models/Setting.php`, `database/factories/ClothingItemFactory.php`
- Create: `app/Filament/Resources/ClothingItems/{ClothingItemResource.php, Schemas/ClothingItemForm.php, Tables/ClothingItemsTable.php, Pages/*}`
- Create: `app/Filament/Pages/Settings.php`, `resources/views/filament/pages/settings.blade.php`
- Test: `tests/Feature/ClothingAndSettingsTest.php`

**Interfaces:**
- Produces:
  - `PersonType` (`Athlete`, `Official`, with `label()`).
  - `AppliesTo` (`Athlete`, `Official`, `Both`, with `includes(PersonType): bool` and `label()`).
  - `ClothingItem`: `name`, `sizes` (array), `applies_to`, `is_active`, `scopeActive()`, `scopeForType(PersonType)`, `hasSize(string): bool`.
  - `Setting::get(string $key, mixed $default = null)` and `Setting::put(string $key, ?string $value)`.
  - Constants `Setting::NOC_LOGO = 'noc_logo_path'` and `Setting::DECLARATION = 'pdf_declaration'`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\AppliesTo;
use App\Enums\PersonType;
use App\Filament\Pages\Settings;
use App\Filament\Resources\ClothingItems\Pages\CreateClothingItem;
use App\Models\ClothingItem;
use App\Models\Setting;
use App\Models\User;
use function Pest\Livewire\livewire;

it('creates a clothing item with an ordered size list', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(CreateClothingItem::class)
        ->fillForm(['name' => 'T-Shirt', 'sizes' => ['S', 'M', 'L', 'XL'], 'applies_to' => AppliesTo::Both->value, 'is_active' => true])
        ->call('create')->assertHasNoFormErrors();

    expect(ClothingItem::sole()->sizes)->toBe(['S', 'M', 'L', 'XL']);
});

it('filters items by person type', function () {
    ClothingItem::factory()->create(['name' => 'Suit', 'applies_to' => AppliesTo::Official]);
    ClothingItem::factory()->create(['name' => 'Spikes', 'applies_to' => AppliesTo::Athlete]);
    ClothingItem::factory()->create(['name' => 'T-Shirt', 'applies_to' => AppliesTo::Both]);
    ClothingItem::factory()->create(['name' => 'Old', 'applies_to' => AppliesTo::Both, 'is_active' => false]);

    expect(ClothingItem::active()->forType(PersonType::Official)->pluck('name')->sort()->values()->all())->toBe(['Suit', 'T-Shirt']);
});

it('saves the declaration text in settings', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(Settings::class)->fillForm(['pdf_declaration' => 'I confirm all information is accurate.'])->call('save')->assertHasNoFormErrors();

    expect(Setting::get(Setting::DECLARATION))->toBe('I confirm all information is accurate.');
});
```

- [ ] **Step 2: Run the test**

Run: `./vendor/bin/pest tests/Feature/ClothingAndSettingsTest.php` → FAIL.

- [ ] **Step 3: Implement the enums, migrations and models**

`app/Enums/PersonType.php`:
```php
<?php

namespace App\Enums;

enum PersonType: string
{
    case Athlete = 'athlete';
    case Official = 'official';

    public function label(): string { return $this === self::Athlete ? 'Athlete' : 'Team official'; }
}
```
`app/Enums/AppliesTo.php`:
```php
<?php

namespace App\Enums;

enum AppliesTo: string
{
    case Athlete = 'athlete';
    case Official = 'official';
    case Both = 'both';

    public function includes(PersonType $type): bool
    {
        return $this === self::Both || $this->value === $type->value;
    }

    public function label(): string
    {
        return match ($this) { self::Athlete => 'Athletes', self::Official => 'Team officials', self::Both => 'Athletes and officials' };
    }
}
```
Clothing items migration `up()`:
```php
Schema::create('clothing_items', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->json('sizes');
    $table->string('applies_to');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```
Settings migration `up()`:
```php
Schema::create('settings', function (Blueprint $table) {
    $table->string('key')->primary();
    $table->text('value')->nullable();
    $table->timestamps();
});
```
`app/Models/ClothingItem.php`:
```php
<?php

namespace App\Models;

use App\Enums\AppliesTo;
use App\Enums\PersonType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClothingItem extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sizes', 'applies_to', 'is_active'];

    protected function casts(): array
    {
        return ['sizes' => 'array', 'applies_to' => AppliesTo::class, 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForType(Builder $query, PersonType $type): void
    {
        $query->whereIn('applies_to', [AppliesTo::Both->value, $type->value]);
    }

    public function hasSize(string $size): bool
    {
        return in_array($size, $this->sizes, true);
    }
}
```
`ClothingItemFactory::definition()`:
```php
return ['name' => fake()->unique()->word(), 'sizes' => ['S', 'M', 'L', 'XL'], 'applies_to' => AppliesTo::Both, 'is_active' => true];
```
`app/Models/Setting.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public const NOC_LOGO = 'noc_logo_path';
    public const DECLARATION = 'pdf_declaration';
    public const DEFAULT_DECLARATION = 'By signing below, the Federation Representative confirms that all information provided in this document is accurate, final, and complies with the regulations of the event.';

    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting.$key", fn () => static::find($key)?->value) ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting.$key");
    }
}
```

- [ ] **Step 4: Write the resource and the settings page**

Run `php artisan make:filament-resource ClothingItem --panel=admin --no-interaction`. In `ClothingItemResource`, use `RequiresStaPermission` with `StaPermission::ManageMasterData`.

`Schemas/ClothingItemForm.php`:
```php
return $schema->components([
    TextInput::make('name')->required()->maxLength(100)->unique(ignoreRecord: true)->helperText('e.g. T-Shirt, Suit, Tracksuit, Shoes'),
    TagsInput::make('sizes')->required()->reorderable()->helperText('Type a size and press Enter. Order is kept, e.g. XS, S, M, L, XL or 38, 39, 40'),
    Select::make('applies_to')->options(collect(AppliesTo::cases())->mapWithKeys(fn ($a) => [$a->value => $a->label()]))->required()->default(AppliesTo::Both->value),
    Toggle::make('is_active')->default(true),
]);
```
Imports: `Filament\Forms\Components\{TextInput, TagsInput, Select, Toggle}` and `App\Enums\AppliesTo`.

`Tables/ClothingItemsTable.php`:
```php
return $table
    ->columns([
        TextColumn::make('name')->searchable(),
        TextColumn::make('sizes')->badge()->separator(','),
        TextColumn::make('applies_to')->formatStateUsing(fn (AppliesTo $state) => $state->label()),
        IconColumn::make('is_active')->boolean(),
    ])
    ->recordActions([EditAction::make()]);
```
Run `php artisan make:filament-page Settings --panel=admin`. Then write `app/Filament/Pages/Settings.php`:
```php
<?php

namespace App\Filament\Pages;

use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

class Settings extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use RequiresStaPermission;

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ManageMasterData;
    }

    public function mount(): void
    {
        $logo = Setting::get(Setting::NOC_LOGO);
        $this->form->fill([
            'noc_logo_path' => $logo ? [$logo] : [],
            'pdf_declaration' => Setting::get(Setting::DECLARATION, Setting::DEFAULT_DECLARATION),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('noc_logo_path')->label('NOC logo (printed on PDFs)')->image()->maxSize(2048)
                ->disk(config('filesystems.private'))->directory('settings')->visibility('private'),
            Textarea::make('pdf_declaration')->label('Declaration printed above signatures')->required()->rows(4),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('save')->label('Save')->action('save')];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $logo = $state['noc_logo_path'] ?? null;
        Setting::put(Setting::NOC_LOGO, is_array($logo) ? (array_values($logo)[0] ?? null) : $logo);
        Setting::put(Setting::DECLARATION, $state['pdf_declaration']);
        Notification::make()->success()->title('Settings saved')->send();
    }
}
```
`resources/views/filament/pages/settings.blade.php`:
```blade
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
```

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Feature/ClothingAndSettingsTest.php` → PASS.

If `{{ $this->form }}` does not render on a v4 plain Page, replace the view body with `{{ $this->content }}`, and rename `form(Schema)` to `content(Schema)`. Re-run the tests. The v4 docs show `content()` for resource pages, and the embedded-form API was not confirmed when this plan was written.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: clothing items and PDF settings"
```

---

### Task 9: Person model – encryption, blind index, scope, sizes

**Files:**
- Create: `app/Enums/PersonGender.php`, `app/Models/Concerns/BelongsToAssociation.php`
- Create: migrations `2026_11_08_000800_create_people_table.php` and `2026_11_08_000900_create_person_sizes_table.php`
- Create: `app/Models/Person.php`, `app/Models/PersonSize.php`, `database/factories/PersonFactory.php`
- Modify: `app/Models/Association.php` (add `people()`)
- Test: `tests/Feature/PersonModelTest.php`

**Interfaces:**
- Consumes: `PersonType`, `ClothingItem`, `User`, `Role`.
- Produces:
  - `Person`, with:
    - `fullName(): string` and `age(?CarbonInterface $on = null): int`
    - `sizes(): HasMany`, `sizesMap(): array<int,string>` and `syncSizes(array<int|string, ?string> $sizes): void`
    - `static documentExists(string $hashColumn, string $number, ?int $ignoreId = null): bool`
    - `static blindIndex(string $number): string`
  - Trait `BelongsToAssociation`, which provides `association()`, a global scope `association`, and auto-fill of `association_id` for association users.
  - `PersonGender` (`Male`, `Female`).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\AppliesTo;
use App\Enums\PersonType;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('encrypts passport and CPR numbers at rest', function () {
    $p = Person::factory()->create(['passport_no' => 'BH1234567', 'cpr_no' => '990101234']);
    $raw = DB::table('people')->where('id', $p->id)->first();

    expect($raw->passport_no)->not->toContain('BH1234567')
        ->and($raw->cpr_no)->not->toContain('990101234')
        ->and($p->fresh()->passport_no)->toBe('BH1234567');
});

it('enforces system-wide uniqueness ignoring case and spaces', function () {
    Person::factory()->create(['passport_no' => 'bh 123 4567']);
    expect(fn () => Person::factory()->create(['passport_no' => 'BH1234567']))->toThrow(QueryException::class)
        ->and(Person::documentExists('passport_no_hash', 'BH 1234567'))->toBeTrue()
        ->and(Person::documentExists('passport_no_hash', 'X999'))->toBeFalse();
});

it('scopes people to the signed-in association user', function () {
    $mine = Person::factory()->create();
    Person::factory()->create();
    $this->actingAs(User::factory()->assocEditor($mine->association)->create());

    expect(Person::pluck('id')->all())->toBe([$mine->id]);
});

it('does not scope STA users', function () {
    Person::factory()->count(2)->create();
    $this->actingAs(User::factory()->sta()->create());
    expect(Person::count())->toBe(2);
});

it('fills association_id from the signed-in association user', function () {
    $assoc = Association::factory()->create();
    $this->actingAs(User::factory()->assocEditor($assoc)->create());
    $p = Person::factory()->make(['association_id' => null]);
    $p->save();
    expect($p->association_id)->toBe($assoc->id);
});

it('syncs sizes and validates them against the item', function () {
    $shirt = ClothingItem::factory()->create(['sizes' => ['S', 'M', 'L'], 'applies_to' => AppliesTo::Both]);
    $suit = ClothingItem::factory()->create(['sizes' => ['48', '50'], 'applies_to' => AppliesTo::Official]);
    $athlete = Person::factory()->create(['type' => PersonType::Athlete]);

    $athlete->syncSizes([$shirt->id => 'M']);
    expect($athlete->sizesMap())->toBe([$shirt->id => 'M']);

    expect(fn () => $athlete->syncSizes([$shirt->id => 'XXL']))->toThrow(ValidationException::class)
        ->and(fn () => $athlete->syncSizes([$suit->id => '48']))->toThrow(ValidationException::class);

    $athlete->syncSizes([$shirt->id => null]);
    expect($athlete->sizesMap())->toBe([]);
});

it('clears official-only fields on athletes and computes age', function () {
    $p = Person::factory()->create(['type' => PersonType::Athlete, 'function' => 'Coach', 'dob' => '2008-06-15']);
    expect($p->function)->toBeNull()
        ->and($p->age(\Carbon\Carbon::parse('2026-06-14')))->toBe(17)
        ->and($p->age(\Carbon\Carbon::parse('2026-06-15')))->toBe(18);
});
```

- [ ] **Step 2: Run the test**

Run: `./vendor/bin/pest tests/Feature/PersonModelTest.php` → FAIL.

- [ ] **Step 3: Implement the enum, trait and migrations**

`app/Enums/PersonGender.php`:
```php
<?php

namespace App\Enums;

enum PersonGender: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string { return ucfirst($this->value); }
}
```
`app/Models/Concerns/BelongsToAssociation.php`:
```php
<?php

namespace App\Models\Concerns;

use App\Models\Association;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Defence in depth on top of Filament tenancy: association users can only read and
 * create rows of their own association, in any code path (jobs, exports, controllers).
 */
trait BelongsToAssociation
{
    public static function bootBelongsToAssociation(): void
    {
        static::addGlobalScope('association', function (Builder $query) {
            $user = auth()->user();
            if ($user instanceof User && $user->role->isAssociationRole()) {
                $query->where($query->qualifyColumn('association_id'), $user->association_id);
            }
        });

        static::creating(function (Model $model) {
            $user = auth()->user();
            if ($user instanceof User && $user->role->isAssociationRole()) {
                $model->association_id = $user->association_id;
            }
        });
    }

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }
}
```
People migration `up()`:
```php
Schema::create('people', function (Blueprint $table) {
    $table->id();
    $table->foreignId('association_id')->constrained()->restrictOnDelete();
    $table->string('type');
    $table->string('first_name');
    $table->string('second_name')->nullable();
    $table->string('third_name')->nullable();
    $table->string('last_name');
    $table->string('photo_path')->nullable();
    $table->text('passport_no');
    $table->string('passport_no_hash', 64)->unique();
    $table->date('passport_expiry');
    $table->string('passport_scan_path')->nullable();
    $table->text('cpr_no')->nullable();
    $table->string('cpr_no_hash', 64)->nullable()->unique();
    $table->date('cpr_expiry')->nullable();
    $table->string('cpr_scan_path')->nullable();
    $table->string('nationality');
    $table->date('dob');
    $table->string('gender');
    $table->unsignedSmallInteger('height_cm')->nullable();
    $table->decimal('weight_kg', 5, 1)->nullable();
    $table->string('function')->nullable();
    $table->string('phone')->nullable();
    $table->string('email')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->index(['association_id', 'type']);
});
```
Person sizes migration `up()`:
```php
Schema::create('person_sizes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
    $table->foreignId('clothing_item_id')->constrained()->restrictOnDelete();
    $table->string('size');
    $table->timestamps();
    $table->unique(['person_id', 'clothing_item_id']);
});
```

- [ ] **Step 4: Implement the models and factory**

`app/Models/PersonSize.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonSize extends Model
{
    protected $fillable = ['person_id', 'clothing_item_id', 'size'];

    public function clothingItem(): BelongsTo { return $this->belongsTo(ClothingItem::class); }
}
```
`app/Models/Person.php`:
```php
<?php

namespace App\Models;

use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Models\Concerns\BelongsToAssociation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Person extends Model
{
    use BelongsToAssociation, HasFactory;

    public const FILE_COLUMNS = ['photo_path', 'passport_scan_path', 'cpr_scan_path'];
    public const OFFICIAL_ONLY = ['function', 'phone', 'email'];

    protected $fillable = [
        'association_id', 'type', 'first_name', 'second_name', 'third_name', 'last_name', 'photo_path',
        'passport_no', 'passport_expiry', 'passport_scan_path', 'cpr_no', 'cpr_expiry', 'cpr_scan_path',
        'nationality', 'dob', 'gender', 'height_cm', 'weight_kg', 'function', 'phone', 'email', 'is_active',
    ];

    protected $hidden = ['passport_no_hash', 'cpr_no_hash'];

    protected function casts(): array
    {
        return [
            'type' => PersonType::class,
            'gender' => PersonGender::class,
            'passport_no' => 'encrypted',
            'cpr_no' => 'encrypted',
            'passport_expiry' => 'date',
            'cpr_expiry' => 'date',
            'dob' => 'date',
            'weight_kg' => 'decimal:1',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Person $person) {
            $person->passport_no_hash = self::blindIndex($person->passport_no);
            $person->cpr_no_hash = filled($person->cpr_no) ? self::blindIndex($person->cpr_no) : null;
            if ($person->type === PersonType::Athlete) {
                foreach (self::OFFICIAL_ONLY as $column) {
                    $person->{$column} = null;
                }
            }
        });

        static::deleted(function (Person $person) {
            $paths = array_filter(array_map(fn ($c) => $person->{$c}, self::FILE_COLUMNS));
            Storage::disk(config('filesystems.private'))->delete($paths);
        });
    }

    // ponytail: HMAC keyed by APP_KEY; rotating APP_KEY requires re-computing both *_hash columns (artisan command when needed).
    public static function blindIndex(string $number): string
    {
        return hash_hmac('sha256', strtoupper(preg_replace('/\s+/', '', $number)), config('app.key'));
    }

    /** System-wide check (bypasses the association scope) used by form validation. */
    public static function documentExists(string $hashColumn, string $number, ?int $ignoreId = null): bool
    {
        return static::withoutGlobalScope('association')
            ->where($hashColumn, self::blindIndex($number))
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(PersonSize::class);
    }

    public function fullName(): string
    {
        return collect([$this->first_name, $this->second_name, $this->third_name, $this->last_name])->filter()->implode(' ');
    }

    public function age(?CarbonInterface $on = null): int
    {
        return (int) $this->dob->diffInYears($on ?? now());
    }

    /** @return array<int, string> clothing_item_id => size */
    public function sizesMap(): array
    {
        return $this->sizes()->pluck('size', 'clothing_item_id')->all();
    }

    /** @param array<int|string, ?string> $sizes clothing_item_id => size (null removes) */
    public function syncSizes(array $sizes): void
    {
        $items = ClothingItem::whereKey(array_keys($sizes))->get()->keyBy('id');

        DB::transaction(function () use ($sizes, $items) {
            foreach ($sizes as $itemId => $size) {
                if (blank($size)) {
                    $this->sizes()->where('clothing_item_id', $itemId)->delete();
                    continue;
                }
                $item = $items->get((int) $itemId);
                if (! $item || ! $item->applies_to->includes($this->type) || ! $item->hasSize($size)) {
                    throw ValidationException::withMessages(["sizes.$itemId" => 'Choose a size from the list for this item.']);
                }
                $this->sizes()->updateOrCreate(['clothing_item_id' => $item->id], ['size' => $size]);
            }
        });
    }
}
```
Add to `Association`: `public function people(): HasMany { return $this->hasMany(Person::class); }`.

`database/factories/PersonFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Models\Association;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'association_id' => Association::factory(),
            'type' => PersonType::Athlete,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'passport_no' => strtoupper(fake()->unique()->bothify('??#######')),
            'passport_expiry' => now()->addYears(3)->toDateString(),
            'cpr_no' => fake()->unique()->numerify('#########'),
            'cpr_expiry' => now()->addYears(2)->toDateString(),
            'nationality' => 'Bahrain',
            'dob' => '2005-03-10',
            'gender' => PersonGender::Male,
            'height_cm' => 178,
            'weight_kg' => 72.5,
        ];
    }

    public function official(): static
    {
        return $this->state(['type' => PersonType::Official, 'function' => 'Coach', 'phone' => '+97333000000', 'email' => fake()->safeEmail()]);
    }
}
```

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Feature/PersonModelTest.php` → PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: person registry model with encryption, blind index, scoping and sizes"
```

---

### Task 10: People screens (portal and admin)

**Files:**
- Create: `app/Filament/Shared/PersonForm.php`, `app/Filament/Shared/PersonTable.php`, `app/Filament/Shared/SavesPersonSizes.php`
- Create: `app/Filament/Portal/Resources/People/{PersonResource.php, Pages/{ListPeople,CreatePerson,EditPerson}.php}`
- Create: `app/Filament/Resources/People/{PersonResource.php, Pages/{ListPeople,CreatePerson,EditPerson}.php}`
- Test: `tests/Feature/PeopleScreensTest.php`

**Interfaces:**
- Consumes: `Person::documentExists`, `syncSizes` and `sizesMap`; `ClothingItem::active()->forType()`; `RequiresStaPermission`; `StaPermission::ManagePeople`.
- Produces:
  - `PersonForm::configure(Schema $schema, bool $withAssociation = false): Schema`
  - `PersonTable::configure(Table $table, bool $withAssociation = false): Table`
  - Trait `SavesPersonSizes`, for the Create and Edit pages.
  - Portal URLs `/portal/{tenant}/people`. Admin URL `/admin/people`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\AppliesTo;
use App\Enums\PersonType;
use App\Enums\StaPermission;
use App\Filament\Portal\Resources\People\Pages\CreatePerson;
use App\Filament\Portal\Resources\People\Pages\ListPeople;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake(config('filesystems.private'));
    $this->assoc = Association::factory()->create();
    $this->editor = actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    $this->shirt = ClothingItem::factory()->create(['name' => 'T-Shirt', 'sizes' => ['S', 'M', 'L'], 'applies_to' => AppliesTo::Both]);
});

function athleteData(array $overrides = []): array
{
    return array_merge([
        'type' => PersonType::Athlete->value, 'first_name' => 'Ali', 'last_name' => 'Hassan',
        'dob' => '2006-01-01', 'gender' => 'male', 'nationality' => 'Bahrain',
        'passport_no' => 'BH7654321', 'passport_expiry' => now()->addYears(2)->toDateString(),
    ], $overrides);
}

it('creates an athlete with photo and clothing size in the current association', function () {
    livewire(CreatePerson::class)
        ->fillForm(athleteData(['photo_path' => UploadedFile::fake()->image('ali.jpg'), "sizes.{$this->shirt->id}" => 'M']))
        ->call('create')
        ->assertHasNoFormErrors();

    $p = Person::sole();
    expect($p->association_id)->toBe($this->assoc->id)->and($p->sizesMap())->toBe([$this->shirt->id => 'M']);
    Storage::disk(config('filesystems.private'))->assertExists($p->photo_path);
});

it('requires function for team officials', function () {
    livewire(CreatePerson::class)->fillForm(athleteData(['type' => PersonType::Official->value]))->call('create')
        ->assertHasFormErrors(['function' => 'required']);
});

it('rejects a passport already registered by another association', function () {
    Person::factory()->create(['passport_no' => 'BH7654321']); // other association
    livewire(CreatePerson::class)->fillForm(athleteData())->call('create')->assertHasFormErrors(['passport_no']);
});

it('never shows or opens another association\'s people', function () {
    $mine = Person::factory()->create(['association_id' => $this->assoc->id]);
    $theirs = Person::factory()->create();

    livewire(ListPeople::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs]);
    $this->get("/portal/{$this->assoc->id}/people/{$theirs->id}/edit")->assertNotFound();
});

it('lets STA with manage_people see all people, and blocks others', function () {
    Person::factory()->count(2)->create();
    $sta = User::factory()->sta()->create();
    $this->actingAs($sta)->get('/admin/people')->assertForbidden();

    $sta->givePermissionTo(StaPermission::ManagePeople->value);
    actingInAdmin($sta);
    livewire(\App\Filament\Resources\People\Pages\ListPeople::class)->assertCountTableRecords(2);
});
```

- [ ] **Step 2: Run the test**

Run: `./vendor/bin/pest tests/Feature/PeopleScreensTest.php` → FAIL.

- [ ] **Step 3: Write the shared form, table and sizes trait**

`app/Filament/Shared/PersonForm.php`:
```php
<?php

namespace App\Filament\Shared;

use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Models\ClothingItem;
use App\Models\Person;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

final class PersonForm
{
    public const FUNCTIONS = ['Coach', 'Assistant Coach', 'Team Manager', 'Team Admin', 'Physio', 'Doctor', 'Other'];

    public static function configure(Schema $schema, bool $withAssociation = false): Schema
    {
        $isOfficial = fn (Get $get) => $get('type') === PersonType::Official->value;

        return $schema->components([
            Section::make('Person')->columns(2)->schema([
                ...($withAssociation ? [Select::make('association_id')->relationship('association', 'name')->searchable()->preload()->required()] : []),
                Select::make('type')->options(self::options(PersonType::cases()))->required()->live()->default(PersonType::Athlete->value),
                TextInput::make('first_name')->required()->maxLength(100),
                TextInput::make('second_name')->maxLength(100),
                TextInput::make('third_name')->maxLength(100),
                TextInput::make('last_name')->required()->maxLength(100),
                DatePicker::make('dob')->label('Date of birth')->required()->maxDate(now()),
                Select::make('gender')->options(self::options(PersonGender::cases()))->required(),
                TextInput::make('nationality')->required()->maxLength(100),
                TextInput::make('height_cm')->label('Height (cm)')->numeric()->minValue(50)->maxValue(250),
                TextInput::make('weight_kg')->label('Weight (kg)')->numeric()->minValue(20)->maxValue(250),
                Toggle::make('is_active')->default(true),
            ]),
            Section::make('Team official details')->columns(3)->visible($isOfficial)->schema([
                Select::make('function')->options(array_combine(self::FUNCTIONS, self::FUNCTIONS))->required($isOfficial),
                TextInput::make('phone')->label('Mobile')->tel()->maxLength(50),
                TextInput::make('email')->email()->maxLength(255),
            ]),
            Section::make('Documents')->columns(3)->schema([
                TextInput::make('passport_no')->label('Passport number')->required()->maxLength(30)
                    ->rule(fn (?Model $record) => self::uniqueDocument('passport_no_hash', 'passport', $record)),
                DatePicker::make('passport_expiry')->required(),
                self::upload('passport_scan_path', 'Passport scan', 'scans', ['application/pdf', 'image/jpeg', 'image/png'], 5120),
                TextInput::make('cpr_no')->label('CPR number')->maxLength(30)
                    ->rule(fn (?Model $record) => self::uniqueDocument('cpr_no_hash', 'CPR', $record)),
                DatePicker::make('cpr_expiry')->requiredWith('cpr_no'),
                self::upload('cpr_scan_path', 'CPR scan', 'scans', ['application/pdf', 'image/jpeg', 'image/png'], 5120),
                self::upload('photo_path', 'Photo', 'photos', ['image/jpeg', 'image/png'], 2048)->image(),
            ]),
            Section::make('Clothing sizes')->columns(3)
                ->schema(fn (Get $get) => self::sizeFields(PersonType::tryFrom((string) $get('type')) ?? PersonType::Athlete)),
        ]);
    }

    private static function upload(string $name, string $label, string $dir, array $types, int $maxKb): FileUpload
    {
        return FileUpload::make($name)->label($label)->acceptedFileTypes($types)->maxSize($maxKb)
            ->disk(config('filesystems.private'))->directory($dir)->visibility('private');
    }

    private static function uniqueDocument(string $hashColumn, string $label, ?Model $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($hashColumn, $label, $record) {
            if (filled($value) && Person::documentExists($hashColumn, (string) $value, $record?->getKey())) {
                $fail("This $label number is already registered in the system.");
            }
        };
    }

    private static function sizeFields(PersonType $type): array
    {
        return ClothingItem::active()->forType($type)->orderBy('name')->get()
            ->map(fn (ClothingItem $item) => Select::make("sizes.{$item->id}")->label($item->name)
                ->options(array_combine($item->sizes, $item->sizes)))
            ->all();
    }

    private static function options(array $cases): array
    {
        return collect($cases)->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
```
`app/Filament/Shared/PersonTable.php`:
```php
<?php

namespace App\Filament\Shared;

use App\Enums\PersonType;
use App\Models\Person;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class PersonTable
{
    public static function configure(Table $table, bool $withAssociation = false): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_path')->label('')->circular()->disk(config('filesystems.private'))->visibility('private'),
                TextColumn::make('last_name')->label('Name')->formatStateUsing(fn (Person $record) => $record->fullName())
                    ->searchable(['first_name', 'last_name'])->sortable(),
                ...($withAssociation ? [TextColumn::make('association.name')->searchable()] : []),
                TextColumn::make('type')->formatStateUsing(fn (PersonType $state) => $state->label())->badge(),
                TextColumn::make('function'),
                TextColumn::make('dob')->date('d M Y')->label('Date of birth'),
                TextColumn::make('passport_expiry')->date('d M Y')
                    ->color(fn (Person $record) => $record->passport_expiry->lt(now()->addMonths(6)) ? 'danger' : null),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->options(collect(PersonType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
                ...($withAssociation ? [SelectFilter::make('association')->relationship('association', 'name')] : []),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn () => (bool) auth()->user()?->isSuperAdmin()),
            ]);
    }
}
```
`app/Filament/Shared/SavesPersonSizes.php`:
```php
<?php

namespace App\Filament\Shared;

/** Used by Create/Edit person pages: `sizes.*` is not a column, it is synced to person_sizes after save. */
trait SavesPersonSizes
{
    protected array $pendingSizes = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['sizes'] = $this->record?->sizesMap() ?? [];

        return $data;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->pullSizes($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->pullSizes($data);
    }

    protected function afterCreate(): void
    {
        $this->record->syncSizes($this->pendingSizes);
    }

    protected function afterSave(): void
    {
        $this->record->syncSizes($this->pendingSizes);
    }

    private function pullSizes(array $data): array
    {
        $this->pendingSizes = $data['sizes'] ?? [];
        unset($data['sizes']);

        return $data;
    }
}
```

- [ ] **Step 4: Write the portal and admin resources**

`app/Filament/Portal/Resources/People/PersonResource.php`:
```php
<?php

namespace App\Filament\Portal\Resources\People;

use App\Filament\Shared\PersonForm;
use App\Filament\Shared\PersonTable;
use App\Models\Person;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PersonResource extends Resource
{
    protected static ?string $model = Person::class;
    protected static ?string $slug = 'people';
    protected static ?string $navigationLabel = 'Athletes & officials';
    protected static ?string $modelLabel = 'person';
    protected static ?string $pluralModelLabel = 'people';

    public static function form(Schema $schema): Schema { return PersonForm::configure($schema); }
    public static function table(Table $table): Table { return PersonTable::configure($table); }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPeople::route('/'),
            'create' => Pages\CreatePerson::route('/create'),
            'edit' => Pages\EditPerson::route('/{record}/edit'),
        ];
    }
}
```
Portal pages:
```php
// Pages/ListPeople.php
class ListPeople extends \Filament\Resources\Pages\ListRecords
{
    protected static string $resource = \App\Filament\Portal\Resources\People\PersonResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()]; }
}

// Pages/CreatePerson.php
class CreatePerson extends \Filament\Resources\Pages\CreateRecord
{
    use \App\Filament\Shared\SavesPersonSizes;
    protected static string $resource = \App\Filament\Portal\Resources\People\PersonResource::class;
}

// Pages/EditPerson.php
class EditPerson extends \Filament\Resources\Pages\EditRecord
{
    use \App\Filament\Shared\SavesPersonSizes;
    protected static string $resource = \App\Filament\Portal\Resources\People\PersonResource::class;
}
```
Each page sits in namespace `App\Filament\Portal\Resources\People\Pages`, with a `<?php` header.

`app/Filament/Resources/People/PersonResource.php` (admin) is the same as the portal resource, except for these differences:
```php
use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;

class PersonResource extends Resource
{
    use RequiresStaPermission;
    // ...same static properties...
    protected static function requiredPermission(): StaPermission { return StaPermission::ManagePeople; }
    public static function form(Schema $schema): Schema { return PersonForm::configure($schema, withAssociation: true); }
    public static function table(Table $table): Table { return PersonTable::configure($table, withAssociation: true); }
    // getPages(): same three routes, pointing at App\Filament\Resources\People\Pages\*
}
```
The admin pages are the same three classes in namespace `App\Filament\Resources\People\Pages`, with `$resource` pointing at the admin `PersonResource` and the same `SavesPersonSizes` trait.

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Feature/PeopleScreensTest.php` → PASS.

If the dynamic `Section::schema(fn (Get $get) => ...)` does not refresh when `type` changes, keep `->live()` on `type`, add `->key('sizes-section')` to the section, and re-run. Filament re-renders the section when a live field changes.

- [ ] **Step 6: Run the full suite and commit**

Run: `./vendor/bin/pest` → all green.
```bash
git add -A && git commit -m "feat: people registry screens with sizes, scans, duplicate checks and isolation tests"
```

---

### Task 11: Excel import of people (portal)

**Files:**
- Create: `app/Exports/PeopleTemplateExport.php`, `app/Services/ImportResult.php`, `app/Services/PeopleImporter.php`
- Create: `app/Filament/Portal/Pages/ImportPeople.php`, `resources/views/filament/portal/pages/import-people.blade.php`
- Test: `tests/Feature/PeopleImportTest.php`

**Interfaces:**
- Consumes: `Person`, `Association`, `PersonType`, `PersonGender`.
- Produces:
  - `PeopleImporter::import(string $absolutePath, Association $association): ImportResult`.
  - `ImportResult` (readonly), with `int $created`, `array<int,array<string>> $errors` (spreadsheet row number → messages) and `ok(): bool`.
  - `PeopleTemplateExport::COLUMNS`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Exports\PeopleTemplateExport;
use App\Models\Association;
use App\Models\Person;
use App\Services\PeopleImporter;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

function xlsxWith(array $rows): string
{
    Excel::store(new PeopleTemplateExport($rows), 'import-test.xlsx', 'local');

    return Storage::disk('local')->path('import-test.xlsx');
}

function row(array $overrides = []): array
{
    return array_merge([
        'type' => 'athlete', 'first_name' => 'Sara', 'second_name' => '', 'third_name' => '', 'last_name' => 'Ali',
        'date_of_birth' => '2007-04-02', 'gender' => 'female', 'nationality' => 'Bahrain', 'height_cm' => 165, 'weight_kg' => 55,
        'passport_no' => 'BH1111111', 'passport_expiry' => '2029-01-01', 'cpr_no' => '070402111', 'cpr_expiry' => '2028-01-01',
        'function' => '', 'phone' => '', 'email' => '',
    ], $overrides);
}

it('imports all rows when every row is valid', function () {
    $assoc = Association::factory()->create();
    $result = app(PeopleImporter::class)->import(xlsxWith([row(), row(['first_name' => 'Huda', 'passport_no' => 'BH2222222', 'cpr_no' => '070402222'])]), $assoc);

    expect($result->ok())->toBeTrue()->and($result->created)->toBe(2)
        ->and(Person::where('association_id', $assoc->id)->count())->toBe(2);
});

it('reports errors with spreadsheet row numbers and imports nothing', function () {
    Person::factory()->create(['passport_no' => 'BH9999999']);
    $result = app(PeopleImporter::class)->import(xlsxWith([
        row(),
        row(['passport_no' => 'BH9999999', 'cpr_no' => '070402333']),           // row 3: duplicate in system
        row(['type' => 'official', 'passport_no' => 'BH4444444', 'cpr_no' => '']), // row 4: official without function
        row(['passport_no' => 'BH1111111', 'cpr_no' => '070402555']),           // row 5: duplicate inside the file
    ]), Association::factory()->create());

    expect($result->ok())->toBeFalse()->and($result->created)->toBe(0)
        ->and(array_keys($result->errors))->toBe([3, 4, 5])
        ->and(Person::count())->toBe(1);
});

it('accepts Excel serial dates', function () {
    $result = app(PeopleImporter::class)->import(xlsxWith([row(['date_of_birth' => 39174, 'passport_expiry' => 47119, 'cpr_expiry' => ''])]), Association::factory()->create());
    expect($result->ok())->toBeTrue()->and(Person::sole()->dob->toDateString())->toBe('2007-04-02');
});
```

- [ ] **Step 2: Run the test**

Run: `./vendor/bin/pest tests/Feature/PeopleImportTest.php` → FAIL.

- [ ] **Step 3: Implement the export, result and importer**

`app/Exports/PeopleTemplateExport.php`:
```php
<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PeopleTemplateExport implements FromArray, WithHeadings
{
    public const COLUMNS = [
        'type', 'first_name', 'second_name', 'third_name', 'last_name', 'date_of_birth', 'gender', 'nationality',
        'height_cm', 'weight_kg', 'passport_no', 'passport_expiry', 'cpr_no', 'cpr_expiry', 'function', 'phone', 'email',
    ];

    /** @param array<int, array<string, mixed>> $rows rows keyed by COLUMNS; empty = one example row */
    public function __construct(private readonly array $rows = []) {}

    public function headings(): array
    {
        return self::COLUMNS;
    }

    public function array(): array
    {
        $rows = $this->rows ?: [[
            'type' => 'athlete', 'first_name' => 'Ahmed', 'second_name' => 'Ali', 'third_name' => '', 'last_name' => 'Hassan',
            'date_of_birth' => '2006-05-20', 'gender' => 'male', 'nationality' => 'Bahrain', 'height_cm' => 180, 'weight_kg' => 75,
            'passport_no' => 'BH0000000', 'passport_expiry' => '2030-12-31', 'cpr_no' => '060520000', 'cpr_expiry' => '2029-12-31',
            'function' => '(officials only: Coach, Team Manager, Physio...)', 'phone' => '', 'email' => '',
        ]];

        return array_map(fn (array $r) => array_map(fn ($c) => $r[$c] ?? '', self::COLUMNS), $rows);
    }
}
```
`app/Services/ImportResult.php`:
```php
<?php

namespace App\Services;

final readonly class ImportResult
{
    /** @param array<int, array<int, string>> $errors spreadsheet row number => messages */
    public function __construct(public int $created, public array $errors) {}

    public function ok(): bool
    {
        return $this->errors === [];
    }
}
```
`app/Services/PeopleImporter.php`:
```php
<?php

namespace App\Services;

use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Filament\Shared\PersonForm;
use App\Models\Association;
use App\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

final class PeopleImporter
{
    public const MAX_ROWS = 500;
    private const DATE_COLUMNS = ['date_of_birth', 'passport_expiry', 'cpr_expiry'];

    public function import(string $absolutePath, Association $association): ImportResult
    {
        $rows = Excel::toArray(new class implements WithHeadingRow {}, $absolutePath)[0] ?? [];
        $rows = array_values(array_filter($rows, fn (array $r) => filled($r['first_name'] ?? null) || filled($r['passport_no'] ?? null)));

        if (count($rows) > self::MAX_ROWS) {
            return new ImportResult(0, [1 => ['The file has more than '.self::MAX_ROWS.' rows. Split it into smaller files.']]);
        }

        $errors = [];
        $clean = [];
        $seen = ['passport_no_hash' => [], 'cpr_no_hash' => []];

        foreach ($rows as $i => $raw) {
            $rowNumber = $i + 2; // row 1 is the heading
            $row = $this->normalise($raw);
            $messages = $this->validate($row);

            foreach (['passport_no' => 'passport_no_hash', 'cpr_no' => 'cpr_no_hash'] as $field => $hashColumn) {
                if (blank($row[$field])) {
                    continue;
                }
                $hash = Person::blindIndex($row[$field]);
                if (isset($seen[$hashColumn][$hash])) {
                    $messages[] = "$field is repeated in row {$seen[$hashColumn][$hash]}.";
                } elseif (Person::documentExists($hashColumn, $row[$field])) {
                    $messages[] = "$field is already registered in the system.";
                }
                $seen[$hashColumn][$hash] ??= $rowNumber;
            }

            $messages ? $errors[$rowNumber] = $messages : $clean[] = $row;
        }

        if ($errors) {
            return new ImportResult(0, $errors);
        }

        DB::transaction(function () use ($clean, $association) {
            foreach ($clean as $row) {
                Person::create([...$this->toAttributes($row), 'association_id' => $association->id]);
            }
        });

        return new ImportResult(count($clean), []);
    }

    private function normalise(array $raw): array
    {
        $row = [];
        foreach (\App\Exports\PeopleTemplateExport::COLUMNS as $column) {
            $value = $raw[$column] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            if (in_array($column, self::DATE_COLUMNS, true) && is_numeric($value)) {
                $value = ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            }
            $row[$column] = $value === '' ? null : $value;
        }
        $row['type'] = strtolower((string) $row['type']);
        $row['gender'] = strtolower((string) $row['gender']);

        return $row;
    }

    /** @return array<int, string> */
    private function validate(array $row): array
    {
        return Validator::make($row, [
            'type' => ['required', Rule::enum(PersonType::class)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'gender' => ['required', Rule::enum(PersonGender::class)],
            'nationality' => ['required', 'string', 'max:100'],
            'height_cm' => ['nullable', 'numeric', 'between:50,250'],
            'weight_kg' => ['nullable', 'numeric', 'between:20,250'],
            'passport_no' => ['required', 'string', 'max:30'],
            'passport_expiry' => ['required', 'date_format:Y-m-d'],
            'cpr_no' => ['nullable', 'string', 'max:30'],
            'cpr_expiry' => ['nullable', 'required_with:cpr_no', 'date_format:Y-m-d'],
            'function' => ['nullable', 'required_if:type,official', Rule::in(PersonForm::FUNCTIONS)],
            'email' => ['nullable', 'email'],
        ])->errors()->all();
    }

    private function toAttributes(array $row): array
    {
        return [
            'type' => $row['type'], 'first_name' => $row['first_name'], 'second_name' => $row['second_name'],
            'third_name' => $row['third_name'], 'last_name' => $row['last_name'], 'dob' => $row['date_of_birth'],
            'gender' => $row['gender'], 'nationality' => $row['nationality'], 'height_cm' => $row['height_cm'],
            'weight_kg' => $row['weight_kg'], 'passport_no' => (string) $row['passport_no'], 'passport_expiry' => $row['passport_expiry'],
            'cpr_no' => $row['cpr_no'] !== null ? (string) $row['cpr_no'] : null, 'cpr_expiry' => $row['cpr_expiry'],
            'function' => $row['function'], 'phone' => $row['phone'], 'email' => $row['email'],
        ];
    }
}
```

- [ ] **Step 4: Run the importer tests**

Run: `./vendor/bin/pest tests/Feature/PeopleImportTest.php` → PASS.

- [ ] **Step 5: Write the portal import page**

`app/Filament/Portal/Pages/ImportPeople.php`:
```php
<?php

namespace App\Filament\Portal\Pages;

use App\Exports\PeopleTemplateExport;
use App\Services\PeopleImporter;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportPeople extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.portal.pages.import-people';
    protected static ?string $title = 'Import people from Excel';
    protected static ?string $navigationLabel = 'Excel import';

    public ?array $data = [];
    /** @var array<int, array<int, string>> */
    public array $rowErrors = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('file')->label('Excel file (.xlsx)')->required()->disk('local')->directory('imports')
                ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->maxSize(5120),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('template')->label('Download template')->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => Excel::download(new PeopleTemplateExport(), 'people-template.xlsx')),
            Action::make('import')->label('Validate and import')->action('import'),
        ];
    }

    public function import(PeopleImporter $importer): void
    {
        $file = $this->form->getState()['file'];
        $path = is_array($file) ? array_values($file)[0] : $file;

        $result = $importer->import(Storage::disk('local')->path($path), Filament::getTenant());
        Storage::disk('local')->delete($path);
        $this->rowErrors = $result->errors;

        $result->ok()
            ? Notification::make()->success()->title("{$result->created} people imported")->send()
            : Notification::make()->danger()->title('Nothing was imported. Fix the rows below and upload again.')->send();

        $this->form->fill();
    }
}
```
`resources/views/filament/portal/pages/import-people.blade.php`:
```blade
<x-filament-panels::page>
    <p>Download the template, fill one row per athlete or team official, then upload it. Every row is checked first; nothing is saved unless all rows are valid. Photos, scans and clothing sizes are added afterwards on each person.</p>

    {{ $this->form }}

    @if ($rowErrors)
        <x-filament::section heading="Rows with errors">
            <table class="w-full text-sm">
                <thead><tr><th class="text-left p-2">Row</th><th class="text-left p-2">Problems</th></tr></thead>
                <tbody>
                @foreach ($rowErrors as $row => $messages)
                    <tr class="border-t"><td class="p-2 align-top">{{ $row }}</td><td class="p-2"><ul>@foreach ($messages as $m)<li>{{ $m }}</li>@endforeach</ul></td></tr>
                @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
```
Apply the same `{{ $this->form }}` / `content()` fallback note as in Task 8, Step 5.

- [ ] **Step 6: Add a page smoke test, then run it**

Append to `tests/Feature/PeopleImportTest.php`:
```php
it('opens the import page for association users', function () {
    $assoc = Association::factory()->create();
    $this->actingAs(\App\Models\User::factory()->assocEditor($assoc)->create())
        ->get("/portal/{$assoc->id}/import-people")->assertOk()->assertSee('Download template');
});
```
Run: `./vendor/bin/pest tests/Feature/PeopleImportTest.php` → PASS.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat: validated Excel import of athletes and officials"
```

---

### Task 12: Audit log

**Files:**
- Modify: `app/Models/{Association,User,Sport,Event,ClothingItem,Person}.php` (add `LogsActivity`)
- Create: `app/Filament/Resources/ActivityLogs/{ActivityLogResource.php, Pages/ListActivityLogs.php}`
- Test: `tests/Feature/AuditLogTest.php`

**Interfaces:**
- Consumes: `spatie/laravel-activitylog` (migrations published in Task 0) and `StaPermission::ViewAuditLog`.
- Produces: activity rows for every create, update and delete on the six models, with `causer` = the signed-in user. Also `/admin/activity-logs`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\StaPermission;
use App\Models\Person;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

it('logs person changes with the causer and never the document numbers', function () {
    $person = Person::factory()->create(['passport_no' => 'BH5555555', 'cpr_no' => '010101555']);
    $editor = User::factory()->assocEditor($person->association)->create();
    $this->actingAs($editor);

    $person->update(['first_name' => 'Changed', 'passport_no' => 'BH6666666']);

    $log = Activity::where('subject_type', Person::class)->where('event', 'updated')->latest('id')->first();
    expect($log->causer_id)->toBe($editor->id)
        ->and($log->properties['attributes']['first_name'])->toBe('Changed')
        ->and($log->properties['documents_changed'])->toBe(['passport_no'])
        ->and(json_encode($log->properties))->not->toContain('BH6666666')->not->toContain('BH5555555');
});

it('shows the audit log only with the permission', function () {
    $sta = User::factory()->sta()->create();
    $this->actingAs($sta)->get('/admin/activity-logs')->assertForbidden();

    $sta->givePermissionTo(StaPermission::ViewAuditLog->value);
    $this->actingAs($sta)->get('/admin/activity-logs')->assertOk();
});
```

- [ ] **Step 2: Run the test**

Run: `./vendor/bin/pest tests/Feature/AuditLogTest.php` → FAIL, because no activity row is written. The `activity_log` table already exists: its migration was published in Task 0, and RefreshDatabase runs it.

- [ ] **Step 3: Add logging to the models**

For `Association`, `Sport`, `Event` and `ClothingItem`, add `use Spatie\Activitylog\Traits\LogsActivity;` and `use Spatie\Activitylog\LogOptions;`, put `LogsActivity` in the class `use` list, and add:
```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
}
```
For `User`:
```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()->logOnly(['name', 'email', 'role', 'association_id', 'is_active'])->logOnlyDirty()->dontSubmitEmptyLogs();
}
```
For `Person` (the document numbers are never logged, only the fact they changed):
```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logExcept(['passport_no', 'cpr_no'])
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs();
}

public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName): void
{
    $changed = array_keys(array_filter([
        'passport_no' => $this->wasChanged('passport_no_hash'),
        'cpr_no' => $this->wasChanged('cpr_no_hash'),
    ]));
    if ($eventName === 'updated' && $changed) {
        $activity->properties = $activity->properties->put('documents_changed', $changed);
    }
}
```

- [ ] **Step 4: Write the read-only log viewer**

`app/Filament/Resources/ActivityLogs/ActivityLogResource.php`:
```php
<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    use RequiresStaPermission;

    protected static ?string $model = Activity::class;
    protected static ?string $slug = 'activity-logs';
    protected static ?string $navigationLabel = 'Audit log';
    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ViewAuditLog;
    }

    public static function canCreate(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime('d M Y H:i')->label('When'),
                TextColumn::make('causer.name')->label('Who')->default('System'),
                TextColumn::make('event')->badge(),
                TextColumn::make('subject_type')->label('Record')->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '-'),
                TextColumn::make('subject_id')->label('ID'),
            ])
            ->filters([
                SelectFilter::make('subject_type')->label('Record type')->options(fn () => Activity::query()->distinct()->pluck('subject_type')
                    ->filter()->mapWithKeys(fn ($t) => [$t => class_basename($t)])->all()),
            ])
            ->recordActions([
                Action::make('details')->modalSubmitAction(false)
                    ->modalContent(fn (Activity $record) => new \Illuminate\Support\HtmlString(
                        '<pre style="white-space:pre-wrap;font-size:12px">'.e(json_encode($record->properties, JSON_PRETTY_PRINT)).'</pre>'
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListActivityLogs::route('/')];
    }
}
```
`Pages/ListActivityLogs.php`:
```php
<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;
}
```

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Feature/AuditLogTest.php` → PASS. Then run `./vendor/bin/pest` → all green.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: audit log on core models with admin viewer, document numbers excluded"
```

---

### Task 13: Optional MFA, profile page, final checks and README

**Files:**
- Create: `database/migrations/2026_11_08_001000_add_app_authentication_secret_to_users_table.php`
- Modify: `app/Models/User.php`, `app/Providers/Filament/AdminPanelProvider.php`, `README.md`
- Test: `tests/Feature/SecurityTest.php`

**Interfaces:**
- Consumes: the `User` model and the admin panel.
- Produces: optional authenticator-app MFA for admin panel users, set up from `/admin/profile`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\User;

it('gives admin users a profile page where MFA can be set up', function () {
    $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/profile')->assertOk();
});

it('does not force MFA on login', function () {
    $this->actingAs(User::factory()->sta()->create())->get('/admin')->assertOk();
});

it('hides the MFA secret from serialisation', function () {
    $user = User::factory()->sta()->create();
    $user->forceFill(['app_authentication_secret' => 'SECRET'])->save();
    expect($user->fresh()->toArray())->not->toHaveKey('app_authentication_secret');
});
```

- [ ] **Step 2: Run the test**

Run: `./vendor/bin/pest tests/Feature/SecurityTest.php` → FAIL, because `/admin/profile` returns 404 and the column is missing.

- [ ] **Step 3: Implement**

Read the installed version's docs first: `vendor/filament/filament/docs/07-users/02-multi-factor-authentication.md`, or the same path on GitHub for the installed tag. Confirm the column name and trait. The code below matches Filament v4.16.

Migration `up()`:
```php
Schema::table('users', function (Blueprint $table) {
    $table->text('app_authentication_secret')->nullable()->after('password');
});
```
`User`:
- Add `implements ..., HasAppAuthentication` with `use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;`.
- Add the trait `use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;`, and put `InteractsWithAppAuthentication` in the class `use` list.
- Add `'app_authentication_secret'` to `$hidden`.
- Add `'app_authentication_secret' => 'encrypted'` to `casts()`.

`AdminPanelProvider`. Add to the chain:
```php
    ->profile()
    ->multiFactorAuthentication([\Filament\Auth\MultiFactor\App\AppAuthentication::make()])
```
Do not pass `isRequired: true`. MFA is optional (FR-08 "S").

`README.md`. Replace the Laravel default content with:
```markdown
# Sports Entry Management System

Laravel 12 + Filament 4. `/admin` = Super Admin and STA users. `/portal` = association admins and editors.

## Local setup
1. PHP 8.5 with `intl`, `gd`, `pdo_sqlite` (and `pdo_mysql` for MySQL).
2. `composer install && cp .env.example .env && php artisan key:generate`
3. Set DB_* (SQLite is fine locally) and SUPER_ADMIN_* in `.env`.
4. `php artisan migrate --seed` creates permissions and the super admin.
5. `php artisan serve` and `php artisan queue:work` (invitation emails are queued).
6. Tests: `./vendor/bin/pest`

## Production notes
- `PRIVATE_DISK=s3`, `AWS_DEFAULT_REGION=me-south-1`, `AWS_BUCKET=<private bucket>`; the bucket must block public access.
- `MAIL_MAILER=ses`, `QUEUE_CONNECTION=database` with Supervisor running `queue:work`; cron runs `schedule:run`.
- Never rotate `APP_KEY` without re-encrypting people documents and recomputing `passport_no_hash` / `cpr_no_hash`.

Design: `docs/superpowers/specs/2026-11-04-sports-entry-system-design.md`, scope: `docs/Sports-Entry-System-Scope-and-Design.docx`.
```

- [ ] **Step 4: Run the whole suite and style check**

Run: `./vendor/bin/pest tests/Feature/SecurityTest.php` → PASS.
Run: `./vendor/bin/pest` → all green.
Run: `./vendor/bin/pint` → files fixed. Then run `./vendor/bin/pest` again → all green.

- [ ] **Step 5: Manual check in the browser**

```bash
php artisan migrate:fresh --seed
php artisan serve
```
Then:
1. Log in at `/admin` as the super admin.
2. Create an association and click **Invite admin**.
3. Open the link from the notification in a private window, and accept the invitation.
4. In `/portal`, invite an editor and add an athlete with a photo and a T-Shirt size. Add the T-Shirt clothing item in `/admin` first.
5. Download the import template, fill two rows and import them.
6. Back in `/admin`, check the audit log.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: optional admin MFA, profile page, README"
```

---

## Spec coverage (Milestone 1)

| Requirement | Task |
|---|---|
| FR-01 association with optional profile fields | 1, 4 |
| FR-02/03/04 invitation link, email + copy link, 7-day single use, resend/revoke, set name+password | 5, 6 |
| FR-05/06 max 2 editors, deactivate frees slot | 5, 6 |
| FR-07 STA users with permission checkboxes; 3.3 permission list | 3 |
| FR-08 password reset, login rate limit (Filament built-in), optional MFA | 0, 13 |
| FR-10/11/12/13 sports per association, events with gender, read-only for associations, no delete | 7 |
| FR-14 clothing items with size list and applies_to; FR-15 NOC logo + declaration | 8 |
| FR-20/21/21a person fields, officials function/phone/email, sizes | 9, 10 |
| FR-22 permanent registry | 9 |
| FR-23 Excel import with row validation before save | 11 |
| FR-24 duplicate passport/CPR check (BR-13) | 9, 10, 11 |
| FR-25/25a private photo and scans | 10 |
| FR-27 STA search across associations | 10 |
| BR-01 isolation (tenancy + global scope + tests) | 2, 9, 10 |
| BR-16 deactivate not delete; Super Admin-only person delete | 4, 7, 8, 10 |
| BR-19 size from item list | 9 |
| Section 12 audit log, 30-min session, encryption | 0, 9, 12 |

FR-26 is deferred to Milestone 2, where games exist; it covers the expiry warning against a game end date. The passport-expiry colour in the people table (Task 10) is the M1 stand-in.
