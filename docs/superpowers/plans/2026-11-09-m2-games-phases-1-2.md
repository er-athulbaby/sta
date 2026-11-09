# Milestone 2 – Games and Phases 1–2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** STA sets up a game (associations, sports and events with limits, clothing items, phases with deadlines), associations answer Phase 1 (Yes/No/Maybe per sport) and Phase 2 (number of athletes per event) in the portal, the submit flow runs editor → association admin → submitted, an Entry by Event PDF is produced per sport, STA tracks everything on a status matrix, and reminders go out automatically.

**Architecture:**
- **Data:** `Game` owns its invited associations, `GameSport` (limits), `GameEvent` (event maximum), clothing items and four `GamePhase` rows. A `Submission` per game × association × phase holds the workflow state. Phase 1 answers and Phase 2 numbers hang off the submission.
- **Rules:** these live in three small services, with Filament screens as thin callers:
  - `PhaseGate` decides whether an association can edit.
  - `Phase2Limits` checks the numbers.
  - `SubmissionWorkflow` moves the status and sends notifications.
- **Background work:** PDFs are rendered with dompdf in a queued job. Reminders come from a daily scheduled command.

**Tech Stack:** PHP 8.5.12 (Herd `php85`), Laravel 12, Filament 4.16, Livewire 3, Pest 4, `barryvdh/laravel-dompdf` (new), spatie/laravel-activitylog, Laravel notifications (mail, queued).

**Spec:** `docs/superpowers/specs/2026-11-04-sports-entry-system-design.md`.
- Sections 4 (Game setup, Workflow), 5 (Phases and Gating, Phase 1, Phase 2, Submit flow), 6 (`/admin` game setup and dashboard; `/portal` games), 7 (Notifications), 9 (Validation) and 12 (Milestone 2).
- Section 13 (registry v1.2) is already built.
- Comments, STA editing of submissions, and Phases 3–4 are **out of scope** (Milestones 3–4).

## Global Constraints

- **PHP:** always run `/c/Users/ababy/.config/herd/bin/php85/php.exe`, and Pest as `$PHP vendor/bin/pest`.
- **Time zone:** the app runs in `Asia/Bahrain` time (`config/app.php` `'timezone' => env('APP_TIMEZONE', 'Asia/Bahrain')`, set in Task 1). Deadlines are entered, stored, compared and shown in Bahrain time, so no code converts time zones.
- **Phases:** a phase is editable by an association only while all of these hold:
  - the phase is enabled;
  - its status is `open`;
  - now ≤ the association's `deadline_override` (if set), otherwise the phase `deadline`;
  - the association's submission is in `draft`.
  - Closed phases are read-only to associations.
- **Phase 1:** Yes / No / Maybe per invited sport. Not responding is shown as "No response". When Phase 1 is enabled, sports answered `no` are hidden for that association in Phase 2.
- **Phase 2 counts athletes only.** These hard limits block save and submit:
  - per event: `count ≤ game_events.p2_max_athletes`;
  - per sport: the sum of men's events ≤ `p2_max_men`, the sum of women's events ≤ `p2_max_women`, and the sum of all events ≤ `p2_max_total`;
  - mixed events count only against their own event maximum (they are included in `p2_max_total`).
  - A null maximum means no limit.
- **Submit flow:**
  - An editor's submit sets `awaiting_assoc_admin` and emails the association admins.
  - The association admin approves, which sets `submitted` and notifies STA.
  - An association admin who submits directly goes straight to `submitted`.
  - The association admin can return a submission to `draft`.
- **STA actions:** STA can extend one association's deadline (`deadline_override`) or re-open one association's submitted phase, which returns it to `draft`. The association is emailed either way.
- **Reference number:** `reference_no` = `{ASSOC CODE}-G{game id, 3 digits}-P{phase}-R{revision}`, for example `BWF-G007-P2-R1`. The revision goes up by one on every final submit.
- **PDF:** each final Phase 2 submit generates one "Entry by Event" PDF per sport, with dompdf, in a queued job. It is stored on `config('filesystems.private')` and offered through a 5-minute `temporaryUrl`. It contains:
  - the NOC logo (from `Setting::NOC_LOGO`) and the game logo;
  - events with Male / Female columns (the other gender greyed out) and totals;
  - the entry policy and age eligibility;
  - the declaration (`Setting::get(Setting::DECLARATION, Setting::DEFAULT_DECLARATION)`);
  - Name / Position / Date / Stamp boxes and the `reference_no`.
- **Notifications:** sent by email through queued Laravel notifications to active users only.
  - Association recipients are the association's active users.
  - STA recipients are active Super Admins plus active STA users with `view_submissions`.
- **Optimistic locking:** each submission has an integer `lock_version` (added in a Task 2 fix). A save claims the version it loaded with one conditional UPDATE (`Submission::claimVersion(int)`, wrapped by `PhaseGate::assertFresh(Submission, int)`) inside its DB transaction, before writing. If the claim fails, show "This entry was changed by someone else — reload to see the latest" and do not overwrite. `updated_at` is not used for locking.
- **Audit log:** spatie activitylog is on `Submission`, `InterestAnswer`, `NumberEntry`, `Game`, `GameSport`, `GameEvent` and `GamePhase`.
- **Tenant isolation (BR-01):** `Submission` uses `BelongsToAssociation`. Portal pages abort with 404 for a game the tenant association is not invited to.
- **Permissions:**
  - Game setup: `StaPermission::ManageGames`.
  - Dashboard and PDF download: `ViewSubmissions`.
  - Remind pending: `SendReminders`.
  - Extend or re-open: `ManageGames`.
- **Commits:** end every commit with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Never commit `.superpowers/` or `~$*`.

---

## File Structure

```
app/Enums/{PhaseStatus, SubmissionStatus, InterestAnswerValue}.php
database/migrations/2026_11_10_000100_create_games_tables.php        # games, game_associations, game_sports, game_events, game_clothing_items, game_phases
database/migrations/2026_11_10_000200_create_submissions_tables.php  # submissions, interest_answers, number_entries
app/Models/{Game, GameSport, GameEvent, GamePhase, Submission, InterestAnswer, NumberEntry}.php
database/factories/{Game, GameSport, GameEvent, Submission}Factory.php
app/Services/Phases/{PhaseGate, Phase2Limits, SubmissionWorkflow, StaleSubmission, Recipients}.php
app/Notifications/{PhaseOpened, SubmissionAwaitingApproval, SubmissionReturned, SubmissionReceived, DeadlineChanged, PhaseReminder}.php
app/Jobs/GenerateEntryByEventPdfs.php
resources/views/pdf/entry-by-event.blade.php
app/Console/Commands/SendPhaseReminders.php ; routes/console.php (schedule)
app/Filament/Resources/Games/...                                       # GameResource, form, table, relation managers, dashboard page
app/Filament/Portal/Pages/{Games, Phase1Entry, Phase2Entry}.php + resources/views/filament/portal/pages/*.blade.php
app/Filament/Portal/Concerns/HandlesSubmission.php                     # shared submit/approve/return actions
tests/Feature/{GameModelTest, PhaseGateTest, Phase2LimitsTest, SubmissionWorkflowTest, GameSetupTest, Phase1EntryTest, Phase2EntryTest, EntryByEventPdfTest, GameDashboardTest, PhaseRemindersTest}.php
```

---

### Task 1: Game and submission schema, enums, models and factories

**Files:**
- Create: the two migrations, the three enums, the seven models, and the four factories listed above.
- Test: `tests/Feature/GameModelTest.php`

**Interfaces:**
- Produces:
  - **Enums:** `PhaseStatus` (`Draft='draft'`, `Open='open'`, `Closed='closed'`), `SubmissionStatus` (`Draft`, `AwaitingAssocAdmin='awaiting_assoc_admin'`, `Submitted`), `InterestAnswerValue` (`Yes`, `No`, `Maybe`), each with `label()`.
  - **`Game` relations:** `associations()` BelongsToMany, `gameSports()` HasMany, `gameEvents()` HasMany, `clothingItems()` BelongsToMany, `phases()` HasMany, `submissions()` HasMany.
  - **`Game` methods:**
    - `phase(int $n): GamePhase` returns the row for phase 1–4.
    - `isInvited(Association $a): bool`.
    - `sportsFor(Association $a): Collection<GameSport>` returns the game sports whose sport belongs to `$a`, ordered by sport name, with `sport` and `gameEvents.event` eager-loaded.
  - **`Game` hook:** the `created` hook creates phases 1–4 (`enabled=false`, `status=draft`).
  - **Other models:**
    - `GameSport`: `game()`, `sport()`, `gameEvents()`.
    - `GameEvent`: `game()`, `gameSport()`, `event()`.
    - `GamePhase`: `game()` and `effectiveDeadline(?Submission $s): ?CarbonInterface`.
  - **`Submission`:**
    - Uses `BelongsToAssociation`.
    - Relations: `game()`, `answers()`, `numbers()`.
    - Static `Submission::for(Game $g, Association $a, int $phase): Submission`, which is firstOrCreate as `draft`.
    - Cast `pdf_paths` as array (game_sport_id ⇒ path).
  - `InterestAnswer` and `NumberEntry` models.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameSport;
use App\Models\Sport;
use App\Models\Submission;

it('creates four disabled draft phases with a new game', function () {
    $game = Game::factory()->create();

    expect($game->phases()->orderBy('phase')->pluck('phase')->all())->toBe([1, 2, 3, 4])
        ->and($game->phase(2)->enabled)->toBeFalse()
        ->and($game->phase(2)->status)->toBe(PhaseStatus::Draft);
});

it('lists only the association\'s own sports in a game', function () {
    $bwf = Association::factory()->create(['code' => 'BWF']);
    $bjf = Association::factory()->create(['code' => 'BJF']);
    $wl = Sport::factory()->create(['association_id' => $bwf->id, 'name' => 'Weightlifting']);
    $judo = Sport::factory()->create(['association_id' => $bjf->id, 'name' => 'Judo']);
    $game = Game::factory()->create();
    $game->associations()->attach([$bwf->id, $bjf->id]);
    GameSport::factory()->create(['game_id' => $game->id, 'sport_id' => $wl->id]);
    GameSport::factory()->create(['game_id' => $game->id, 'sport_id' => $judo->id]);

    expect($game->sportsFor($bwf)->pluck('sport.name')->all())->toBe(['Weightlifting'])
        ->and($game->isInvited($bwf))->toBeTrue()
        ->and($game->isInvited(Association::factory()->create()))->toBeFalse();
});

it('gets or creates one draft submission per game, association and phase', function () {
    $game = Game::factory()->create();
    $assoc = Association::factory()->create();

    $a = Submission::for($game, $assoc, 2);
    $b = Submission::for($game, $assoc, 2);

    expect($a->id)->toBe($b->id)->and($a->status)->toBe(SubmissionStatus::Draft)
        ->and(Submission::count())->toBe(1);
});

it('uses the association override before the phase deadline', function () {
    $game = Game::factory()->create();
    $phase = $game->phase(2);
    $phase->update(['deadline' => '2026-12-01 23:59:00']);
    $sub = Submission::for($game, Association::factory()->create(), 2);

    expect($phase->effectiveDeadline($sub)->toDateString())->toBe('2026-12-01');
    $sub->update(['deadline_override' => '2026-12-05 23:59:00']);
    expect($phase->effectiveDeadline($sub->fresh())->toDateString())->toBe('2026-12-05');
});
```

- [ ] **Step 2: Run it**

Run `$PHP vendor/bin/pest tests/Feature/GameModelTest.php`. Expected: FAIL, because the classes are missing.

- [ ] **Step 3: Write the migrations**

`2026_11_10_000100_create_games_tables.php` (`up()`; `down()` drops the tables in reverse order):
```php
Schema::create('games', function (Blueprint $t) {
    $t->id();
    $t->string('name');
    $t->string('logo_path')->nullable();
    $t->date('start_date');
    $t->date('end_date');
    $t->json('reminder_days');
    $t->timestamps();
});
Schema::create('game_associations', function (Blueprint $t) {
    $t->foreignId('game_id')->constrained()->cascadeOnDelete();
    $t->foreignId('association_id')->constrained()->cascadeOnDelete();
    $t->primary(['game_id', 'association_id']);
});
Schema::create('game_sports', function (Blueprint $t) {
    $t->id();
    $t->foreignId('game_id')->constrained()->cascadeOnDelete();
    $t->foreignId('sport_id')->constrained();
    foreach (['p2_max_men', 'p2_max_women', 'p2_max_total', 'p4_max_men', 'p4_max_women', 'p4_max_officials'] as $c) {
        $t->unsignedSmallInteger($c)->nullable();
    }
    $t->date('dob_from')->nullable();
    $t->date('dob_to')->nullable();
    $t->text('entry_policy')->nullable();
    $t->timestamps();
    $t->unique(['game_id', 'sport_id']);
});
Schema::create('game_events', function (Blueprint $t) {
    $t->id();
    $t->foreignId('game_id')->constrained()->cascadeOnDelete();
    $t->foreignId('game_sport_id')->constrained()->cascadeOnDelete();
    $t->foreignId('event_id')->constrained();
    $t->unsignedSmallInteger('p2_max_athletes')->nullable();
    $t->timestamps();
    $t->unique(['game_id', 'event_id']);
});
Schema::create('game_clothing_items', function (Blueprint $t) {
    $t->foreignId('game_id')->constrained()->cascadeOnDelete();
    $t->foreignId('clothing_item_id')->constrained();
    $t->primary(['game_id', 'clothing_item_id']);
});
Schema::create('game_phases', function (Blueprint $t) {
    $t->id();
    $t->foreignId('game_id')->constrained()->cascadeOnDelete();
    $t->unsignedTinyInteger('phase');
    $t->boolean('enabled')->default(false);
    $t->string('status', 10)->default('draft');
    $t->dateTime('opens_at')->nullable();
    $t->dateTime('deadline')->nullable();
    $t->timestamps();
    $t->unique(['game_id', 'phase']);
});
```
`2026_11_10_000200_create_submissions_tables.php`:
```php
Schema::create('submissions', function (Blueprint $t) {
    $t->id();
    $t->foreignId('game_id')->constrained()->cascadeOnDelete();
    $t->foreignId('association_id')->constrained();
    $t->unsignedTinyInteger('phase');
    $t->string('status', 30)->default('draft');
    $t->string('reference_no', 40)->nullable()->unique();
    $t->unsignedSmallInteger('revision')->default(0);
    $t->dateTime('deadline_override')->nullable();
    $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
    $t->dateTime('submitted_at')->nullable();
    $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $t->dateTime('approved_at')->nullable();
    $t->json('pdf_paths')->nullable();
    $t->text('justification')->nullable();
    $t->timestamps();
    $t->unique(['game_id', 'association_id', 'phase']);
});
Schema::create('interest_answers', function (Blueprint $t) {
    $t->id();
    $t->foreignId('submission_id')->constrained()->cascadeOnDelete();
    $t->foreignId('game_sport_id')->constrained()->cascadeOnDelete();
    $t->string('answer', 5);
    $t->timestamps();
    $t->unique(['submission_id', 'game_sport_id']);
});
Schema::create('number_entries', function (Blueprint $t) {
    $t->id();
    $t->foreignId('submission_id')->constrained()->cascadeOnDelete();
    $t->foreignId('game_event_id')->constrained()->cascadeOnDelete();
    $t->unsignedSmallInteger('count')->default(0);
    $t->timestamps();
    $t->unique(['submission_id', 'game_event_id']);
});
```
Each table gets its own `Schema::create` call. Write `down()` dropping them in reverse order.

- [ ] **Step 4: Write the enums**

```php
<?php

namespace App\Enums;

enum PhaseStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string { return ucfirst($this->value); }
}
```
```php
enum SubmissionStatus: string
{
    case Draft = 'draft';
    case AwaitingAssocAdmin = 'awaiting_assoc_admin';
    case Submitted = 'submitted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::AwaitingAssocAdmin => 'Awaiting association admin',
            self::Submitted => 'Submitted',
        };
    }
}
```
```php
enum InterestAnswerValue: string
{
    case Yes = 'yes';
    case No = 'no';
    case Maybe = 'maybe';

    public function label(): string { return ucfirst($this->value); }
}
```

- [ ] **Step 5: Write the models**

All models use the `HasFactory` and `LogsActivity` traits, with `getActivitylogOptions()` returning `LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()`. `InterestAnswer` and `NumberEntry` also log, which is what makes the audit trail work.

`Game`:
```php
protected $fillable = ['name', 'logo_path', 'start_date', 'end_date', 'reminder_days'];
protected function casts(): array { return ['start_date' => 'date', 'end_date' => 'date', 'reminder_days' => 'array']; }

protected static function booted(): void
{
    static::created(function (Game $game) {
        foreach ([1, 2, 3, 4] as $n) {
            $game->phases()->create(['phase' => $n, 'enabled' => false, 'status' => PhaseStatus::Draft]);
        }
    });
}

public function associations(): BelongsToMany { return $this->belongsToMany(Association::class, 'game_associations'); }
public function clothingItems(): BelongsToMany { return $this->belongsToMany(ClothingItem::class, 'game_clothing_items'); }
public function gameSports(): HasMany { return $this->hasMany(GameSport::class); }
public function gameEvents(): HasMany { return $this->hasMany(GameEvent::class); }
public function phases(): HasMany { return $this->hasMany(GamePhase::class); }
public function submissions(): HasMany { return $this->hasMany(Submission::class); }

public function phase(int $n): GamePhase
{
    return $this->phases->firstWhere('phase', $n) ?? $this->phases()->where('phase', $n)->firstOrFail();
}

public function isInvited(Association $a): bool
{
    return $this->associations()->whereKey($a->getKey())->exists();
}

/** @return Collection<int, GameSport> */
public function sportsFor(Association $a): Collection
{
    return $this->gameSports()->whereHas('sport', fn ($q) => $q->where('association_id', $a->getKey()))
        ->with(['sport', 'gameEvents.event'])->get()->sortBy('sport.name')->values();
}
```
`GameSport`: fillable is every column except `id` and the timestamps. Casts `dob_from` and `dob_to` to `date`. Relations: `game()`, `sport()`, `gameEvents()` (HasMany).

`GameEvent`: fillable `game_id`, `game_sport_id`, `event_id`, `p2_max_athletes`. Relations: `game()`, `gameSport()`, `event()`.

`GamePhase`:
```php
protected $fillable = ['game_id', 'phase', 'enabled', 'status', 'opens_at', 'deadline'];
protected function casts(): array { return ['enabled' => 'boolean', 'status' => PhaseStatus::class, 'opens_at' => 'datetime', 'deadline' => 'datetime']; }
public function game(): BelongsTo { return $this->belongsTo(Game::class); }
public function effectiveDeadline(?Submission $s = null): ?CarbonInterface { return $s?->deadline_override ?? $this->deadline; }
```
`Submission` uses `BelongsToAssociation, HasFactory, LogsActivity`:
```php
protected $fillable = ['game_id', 'association_id', 'phase', 'status', 'reference_no', 'revision', 'deadline_override',
    'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'pdf_paths', 'justification'];
protected function casts(): array
{
    return ['status' => SubmissionStatus::class, 'deadline_override' => 'datetime', 'submitted_at' => 'datetime',
        'approved_at' => 'datetime', 'pdf_paths' => 'array'];
}
public function game(): BelongsTo { return $this->belongsTo(Game::class); }
public function answers(): HasMany { return $this->hasMany(InterestAnswer::class); }
public function numbers(): HasMany { return $this->hasMany(NumberEntry::class); }
public function gamePhase(): GamePhase { return $this->game->phase($this->phase); }

public static function for(Game $game, Association $association, int $phase): self
{
    return static::withoutGlobalScopes()->firstOrCreate(
        ['game_id' => $game->id, 'association_id' => $association->id, 'phase' => $phase],
        ['status' => SubmissionStatus::Draft],
    );
}
```
`for()` skips the global scope because the callers have already checked access: the portal pages pass the tenant, and the admin pages hold the permission. Leave a short code comment saying so.

`InterestAnswer`:
- fillable `submission_id`, `game_sport_id`, `answer`;
- cast `answer` to `InterestAnswerValue`;
- relations `submission()` and `gameSport()`.

`NumberEntry`:
- fillable `submission_id`, `game_event_id`, `count`;
- cast `count` to integer;
- relations `submission()` and `gameEvent()`.

- [ ] **Step 6: Write the factories**

- `GameFactory`: `name => '6th Asian Indoor & Martial Arts Games 2026'`, `start_date => now()->addMonths(3)`, `end_date => now()->addMonths(3)->addDays(10)`, `reminder_days => [7, 3, 0]`.
- `GameSportFactory`: `game_id => Game::factory()`, `sport_id => Sport::factory()`.
- `GameEventFactory`: `game_sport_id => GameSport::factory()`, then `game_id` and `event_id` filled from that game sport in `configure()->afterMaking` (the event must belong to the sport, so create one with `Event::factory()` for the sport when none is given).
- `SubmissionFactory`: `game_id => Game::factory()`, `association_id => Association::factory()`, `phase => 2`, `status => SubmissionStatus::Draft`.

- [ ] **Step 7: Set the time zone**

In `config/app.php`, set `'timezone' => env('APP_TIMEZONE', 'Asia/Bahrain')`. In `.env.example`, add `APP_TIMEZONE=Asia/Bahrain`. Add a test to `GameModelTest`: `expect(config('app.timezone'))->toBe('Asia/Bahrain');`.

- [ ] **Step 8: Run the tests and commit**

Run `$PHP vendor/bin/pest tests/Feature/GameModelTest.php`, then the full suite. Expected: all pass. If a date-based existing test breaks because of the time zone change, fix the test's expectation and note it in the report.

`git add` the new files, then `git commit -m "feat: game, phase and submission schema"`.

---

### Task 2: Phase gate and optimistic locking

**Files:**
- Create: `app/Services/Phases/PhaseGate.php`, `app/Services/Phases/StaleSubmission.php`
- Test: `tests/Feature/PhaseGateTest.php`

**Interfaces:**
- Produces:
  - `PhaseGate::denialReason(Submission $s, ?CarbonInterface $now = null): ?string`. It returns null when the association may edit, and otherwise one of these exact messages:
    - `'This phase is not used for this game.'`
    - `'This phase is not open yet.'`
    - `'This phase is closed.'`
    - `'The deadline has passed.'`
    - `'This entry has been sent for approval.'`
    - `'This entry has been submitted.'`
  - `PhaseGate::canEdit(Submission $s, ?CarbonInterface $now = null): bool`.
  - `PhaseGate::assertFresh(Submission $s, string $loadedUpdatedAt): void`, which throws `StaleSubmission`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\Game;
use App\Models\Submission;
use App\Services\Phases\PhaseGate;
use App\Services\Phases\StaleSubmission;
use Carbon\Carbon;

function gatedSubmission(array $phase = [], array $sub = []): Submission
{
    $game = Game::factory()->create();
    $game->phase(2)->update(array_merge(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => '2026-12-01 23:59:00'], $phase));
    $s = Submission::for($game, Association::factory()->create(), 2);
    $s->update($sub);

    return $s->fresh();
}

it('allows editing an open, enabled phase before the deadline', function () {
    expect(app(PhaseGate::class)->denialReason(gatedSubmission(), Carbon::parse('2026-11-20')))->toBeNull();
});

it('explains why editing is refused', function (array $phase, array $sub, string $now, string $reason) {
    expect(app(PhaseGate::class)->denialReason(gatedSubmission($phase, $sub), Carbon::parse($now)))->toBe($reason);
})->with([
    'disabled' => [['enabled' => false], [], '2026-11-20', 'This phase is not used for this game.'],
    'draft phase' => [['status' => PhaseStatus::Draft], [], '2026-11-20', 'This phase is not open yet.'],
    'closed' => [['status' => PhaseStatus::Closed], [], '2026-11-20', 'This phase is closed.'],
    'past deadline' => [[], [], '2026-12-02', 'The deadline has passed.'],
    'awaiting' => [[], ['status' => SubmissionStatus::AwaitingAssocAdmin], '2026-11-20', 'This entry has been sent for approval.'],
    'submitted' => [[], ['status' => SubmissionStatus::Submitted], '2026-11-20', 'This entry has been submitted.'],
]);

it('lets an extension override the phase deadline', function () {
    $s = gatedSubmission([], ['deadline_override' => '2026-12-05 23:59:00']);
    expect(app(PhaseGate::class)->canEdit($s, Carbon::parse('2026-12-03')))->toBeTrue();
});

it('refuses a stale save', function () {
    $s = gatedSubmission();
    $loaded = $s->updated_at->toIso8601String();
    $this->travel(5)->seconds();
    $s->touch();

    expect(fn () => app(PhaseGate::class)->assertFresh($s->fresh(), $loaded))->toThrow(StaleSubmission::class);
    app(PhaseGate::class)->assertFresh($s->fresh(), $s->fresh()->updated_at->toIso8601String());
});
```

- [ ] **Step 2: Run it**

Expected: FAIL.

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Services\Phases;

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use Carbon\CarbonInterface;

final class PhaseGate
{
    public function denialReason(Submission $s, ?CarbonInterface $now = null): ?string
    {
        $phase = $s->gamePhase();
        $deadline = $phase->effectiveDeadline($s);

        return match (true) {
            ! $phase->enabled => 'This phase is not used for this game.',
            $phase->status === PhaseStatus::Draft => 'This phase is not open yet.',
            $phase->status === PhaseStatus::Closed => 'This phase is closed.',
            $deadline !== null && ($now ?? now())->greaterThan($deadline) => 'The deadline has passed.',
            $s->status === SubmissionStatus::AwaitingAssocAdmin => 'This entry has been sent for approval.',
            $s->status === SubmissionStatus::Submitted => 'This entry has been submitted.',
            default => null,
        };
    }

    public function canEdit(Submission $s, ?CarbonInterface $now = null): bool
    {
        return $this->denialReason($s, $now) === null;
    }

    public function assertFresh(Submission $s, string $loadedUpdatedAt): void
    {
        if ($s->updated_at?->toIso8601String() !== $loadedUpdatedAt) {
            throw new StaleSubmission;
        }
    }
}
```
```php
<?php

namespace App\Services\Phases;

use RuntimeException;

final class StaleSubmission extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This entry was changed by someone else — reload to see the latest.');
    }
}
```

- [ ] **Step 4: Run the tests and commit**

`git commit -m "feat: phase gate and stale-save check"`

---

### Task 3: Phase 2 limits

**Files:**
- Create: `app/Services/Phases/Phase2Limits.php`
- Test: `tests/Feature/Phase2LimitsTest.php`

**Interfaces:**
- Produces: `Phase2Limits::errors(GameSport $gs, array $counts): array<string, string>`. `$counts` maps game_event_id ⇒ int. The keys of the result are:
  - `"event.{gameEventId}"`, with message `"{Event name}: at most {max} athletes."`;
  - `"sport.men"`, with `"{Sport}: men's events total {sum}, the limit is {max}."`;
  - `"sport.women"`, with `"{Sport}: women's events total {sum}, the limit is {max}."`;
  - `"sport.total"`, with `"{Sport}: total {sum}, the limit is {max}."`.
  - A negative count gives `"event.{id}"` with `"{Event name}: enter 0 or more."`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\EventGender;
use App\Models\Event;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Sport;
use App\Services\Phases\Phase2Limits;

beforeEach(function () {
    $this->gs = GameSport::factory()->create(['sport_id' => Sport::factory()->create(['name' => 'Judo'])->id,
        'p2_max_men' => 3, 'p2_max_women' => 2, 'p2_max_total' => 4]);
    $mk = fn (string $name, EventGender $g, ?int $max) => GameEvent::factory()->create([
        'game_id' => $this->gs->game_id, 'game_sport_id' => $this->gs->id, 'p2_max_athletes' => $max,
        'event_id' => Event::factory()->create(['sport_id' => $this->gs->sport_id, 'name' => $name, 'gender' => $g])->id]);
    $this->m60 = $mk('Men -60kg', EventGender::Men, 2);
    $this->m73 = $mk('Men -73kg', EventGender::Men, 2);
    $this->w52 = $mk('Women -52kg', EventGender::Women, 1);
    $this->mix = $mk('Mixed team', EventGender::Mixed, 1);
});

it('accepts counts within every limit', function () {
    expect(app(Phase2Limits::class)->errors($this->gs, [$this->m60->id => 2, $this->m73->id => 1, $this->w52->id => 1]))->toBe([]);
});

it('flags an event over its maximum', function () {
    expect(app(Phase2Limits::class)->errors($this->gs, [$this->m60->id => 3]))
        ->toBe(["event.{$this->m60->id}" => 'Men -60kg: at most 2 athletes.']);
});

it('flags sport totals by gender and overall, with mixed only in the total', function () {
    $errors = app(Phase2Limits::class)->errors($this->gs, [$this->m60->id => 2, $this->m73->id => 2, $this->w52->id => 1, $this->mix->id => 1]);

    expect($errors)->toBe([
        'sport.men' => "Judo: men's events total 4, the limit is 3.",
        'sport.total' => 'Judo: total 6, the limit is 4.',
    ]);
});

it('treats a null maximum as no limit and rejects negatives', function () {
    $this->gs->update(['p2_max_men' => null, 'p2_max_total' => null]);
    $this->m60->update(['p2_max_athletes' => null]);

    expect(app(Phase2Limits::class)->errors($this->gs->fresh(), [$this->m60->id => 40, $this->m73->id => -1]))
        ->toBe(["event.{$this->m73->id}" => 'Men -73kg: enter 0 or more.']);
});
```

- [ ] **Step 2: Run it**

Expected: FAIL.

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Services\Phases;

use App\Enums\EventGender;
use App\Models\GameSport;

final class Phase2Limits
{
    /** @param array<int, int|null> $counts game_event_id => count @return array<string, string> */
    public function errors(GameSport $gs, array $counts): array
    {
        $gs->loadMissing(['sport', 'gameEvents.event']);
        $errors = [];
        $sums = ['men' => 0, 'women' => 0, 'total' => 0];

        foreach ($gs->gameEvents as $ge) {
            $n = (int) ($counts[$ge->id] ?? 0);
            if ($n < 0) {
                $errors["event.{$ge->id}"] = "{$ge->event->name}: enter 0 or more.";
                continue;
            }
            if ($ge->p2_max_athletes !== null && $n > $ge->p2_max_athletes) {
                $errors["event.{$ge->id}"] = "{$ge->event->name}: at most {$ge->p2_max_athletes} athletes.";
            }
            $sums['total'] += $n;
            if ($ge->event->gender === EventGender::Men) { $sums['men'] += $n; }
            if ($ge->event->gender === EventGender::Women) { $sums['women'] += $n; }
        }

        $sport = $gs->sport->name;
        if ($gs->p2_max_men !== null && $sums['men'] > $gs->p2_max_men) {
            $errors['sport.men'] = "{$sport}: men's events total {$sums['men']}, the limit is {$gs->p2_max_men}.";
        }
        if ($gs->p2_max_women !== null && $sums['women'] > $gs->p2_max_women) {
            $errors['sport.women'] = "{$sport}: women's events total {$sums['women']}, the limit is {$gs->p2_max_women}.";
        }
        if ($gs->p2_max_total !== null && $sums['total'] > $gs->p2_max_total) {
            $errors['sport.total'] = "{$sport}: total {$sums['total']}, the limit is {$gs->p2_max_total}.";
        }

        return $errors;
    }
}
```

- [ ] **Step 4: Run the tests and commit**

`git commit -m "feat: phase 2 limit checks"`

---

### Task 4: Submit workflow and notifications

**Files:**
- Create:
  - `app/Services/Phases/SubmissionWorkflow.php`
  - `app/Services/Phases/Recipients.php`
  - `app/Notifications/{SubmissionAwaitingApproval, SubmissionReturned, SubmissionReceived, DeadlineChanged}.php`
  - `app/Jobs/GenerateEntryByEventPdfs.php`. Write the stub in this task: an empty `handle()`. Task 8 fills it in.
- Test: `tests/Feature/SubmissionWorkflowTest.php`

**Interfaces:**
- Consumes: `PhaseGate` (Task 2) and `Phase2Limits` (Task 3).
- Produces:
  - **`SubmissionWorkflow` methods.** Each one throws `Illuminate\Validation\ValidationException` with key `submission` when the move is not allowed:
    - `submit(Submission $s, User $by): Submission`
    - `approve(Submission $s, User $by): Submission`
    - `returnToDraft(Submission $s, User $by): Submission`
    - `reopen(Submission $s, User $by, ?CarbonInterface $newDeadline = null): Submission`
    - `extend(Submission $s, User $by, CarbonInterface $deadline): Submission`
  - **`Recipients`:**
    - `Recipients::association(Association $a, ?Role $role = null): Collection<User>` returns active users of the association, optionally only one role.
    - `Recipients::sta(): Collection<User>` returns active super admins plus active STA users with `view_submissions`.
  - `GenerateEntryByEventPdfs::dispatch(Submission $s)` is dispatched on a final Phase 2 submit.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Jobs\GenerateEntryByEventPdfs;
use App\Models\Association;
use App\Models\Game;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\DeadlineChanged;
use App\Notifications\SubmissionAwaitingApproval;
use App\Notifications\SubmissionReceived;
use App\Notifications\SubmissionReturned;
use App\Services\Phases\SubmissionWorkflow;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    Bus::fake([GenerateEntryByEventPdfs::class]);
    $this->assoc = Association::factory()->create(['code' => 'BWF']);
    $this->game = Game::factory()->create();
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->sub = Submission::for($this->game, $this->assoc, 2);
    $this->admin = User::factory()->assocAdmin($this->assoc)->create();
    $this->editor = User::factory()->assocEditor($this->assoc)->create();
    $this->sta = User::factory()->sta()->create();
    $this->sta->givePermissionTo(StaPermission::ViewSubmissions->value);
    $this->wf = app(SubmissionWorkflow::class);
});

it('sends an editor submit to the association admin', function () {
    $s = $this->wf->submit($this->sub, $this->editor);

    expect($s->status)->toBe(SubmissionStatus::AwaitingAssocAdmin)->and($s->submitted_by)->toBe($this->editor->id);
    Notification::assertSentTo($this->admin, SubmissionAwaitingApproval::class);
    Notification::assertNotSentTo($this->sta, SubmissionReceived::class);
});

it('finalises on approval with a reference number, STA notice and PDF job', function () {
    $this->wf->submit($this->sub, $this->editor);
    $s = $this->wf->approve($this->sub->fresh(), $this->admin);

    expect($s->status)->toBe(SubmissionStatus::Submitted)
        ->and($s->reference_no)->toBe(sprintf('BWF-G%03d-P2-R1', $this->game->id))
        ->and($s->approved_by)->toBe($this->admin->id);
    Notification::assertSentTo($this->sta, SubmissionReceived::class);
    Bus::assertDispatched(GenerateEntryByEventPdfs::class);
});

it('lets an association admin submit directly', function () {
    expect($this->wf->submit($this->sub, $this->admin)->status)->toBe(SubmissionStatus::Submitted);
});

it('returns an awaiting entry to draft and tells the editor', function () {
    $this->wf->submit($this->sub, $this->editor);
    expect($this->wf->returnToDraft($this->sub->fresh(), $this->admin)->status)->toBe(SubmissionStatus::Draft);
    Notification::assertSentTo($this->editor, SubmissionReturned::class);
});

it('refuses moves that are not allowed', function () {
    expect(fn () => $this->wf->approve($this->sub, $this->admin))->toThrow(ValidationException::class);
    expect(fn () => $this->wf->approve($this->sub, $this->editor))->toThrow(ValidationException::class);
    $this->game->phase(2)->update(['status' => PhaseStatus::Closed]);
    expect(fn () => $this->wf->submit($this->sub->fresh(), $this->admin))->toThrow(ValidationException::class);
});

it('re-opens a submitted entry, bumps the revision on the next submit and emails the association', function () {
    $this->wf->submit($this->sub, $this->admin);
    $s = $this->wf->reopen($this->sub->fresh(), $this->sta, now()->addDays(10));
    expect($s->status)->toBe(SubmissionStatus::Draft)->and($s->deadline_override)->not->toBeNull();
    Notification::assertSentTo([$this->admin, $this->editor], DeadlineChanged::class);

    expect($this->wf->submit($s, $this->admin)->reference_no)->toEndWith('-R2');
});

it('extends one association\'s deadline', function () {
    $s = $this->wf->extend($this->sub, $this->sta, now()->addDays(20));
    expect($s->deadline_override->isSameDay(now()->addDays(20)))->toBeTrue();
    Notification::assertSentTo($this->admin, DeadlineChanged::class);
});
```
If `UserFactory` has no `assocAdmin(?Association)` state, add one mirroring `assocEditor()`.

- [ ] **Step 2: Run it**

Expected: FAIL.

- [ ] **Step 3: Write the recipients helper**

```php
<?php

namespace App\Services\Phases;

use App\Enums\Role;
use App\Enums\StaPermission;
use App\Models\Association;
use App\Models\User;
use Illuminate\Support\Collection;

final class Recipients
{
    /** @return Collection<int, User> */
    public static function association(Association $a, ?Role $role = null): Collection
    {
        return User::query()->where('association_id', $a->id)->where('is_active', true)
            ->when($role, fn ($q) => $q->where('role', $role))->get();
    }

    /** @return Collection<int, User> */
    public static function sta(): Collection
    {
        return User::query()->where('is_active', true)->whereIn('role', [Role::SuperAdmin, Role::Sta])->get()
            ->filter(fn (User $u) => $u->isSuperAdmin() || $u->can(StaPermission::ViewSubmissions->value))->values();
    }
}
```

- [ ] **Step 4: Write the workflow**

```php
<?php

namespace App\Services\Phases;

use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Jobs\GenerateEntryByEventPdfs;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\DeadlineChanged;
use App\Notifications\SubmissionAwaitingApproval;
use App\Notifications\SubmissionReceived;
use App\Notifications\SubmissionReturned;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class SubmissionWorkflow
{
    public function __construct(private readonly PhaseGate $gate) {}

    public function submit(Submission $s, User $by): Submission
    {
        $this->ownAssociation($s, $by);
        if ($reason = $this->gate->denialReason($s)) {
            $this->fail($reason);
        }
        if ($by->role === Role::AssocAdmin) {
            return $this->finalise($s, $by, submittedBy: $by);
        }
        $s->update(['status' => SubmissionStatus::AwaitingAssocAdmin, 'submitted_by' => $by->id, 'submitted_at' => now()]);
        Notification::send(Recipients::association($s->association, Role::AssocAdmin), new SubmissionAwaitingApproval($s));

        return $s;
    }

    public function approve(Submission $s, User $by): Submission
    {
        $this->ownAssociation($s, $by);
        if ($by->role !== Role::AssocAdmin) { $this->fail('Only the association admin can approve.'); }
        if ($s->status !== SubmissionStatus::AwaitingAssocAdmin) { $this->fail('This entry is not waiting for approval.'); }

        // ponytail: approval is allowed after the deadline — the editor sent it in time; block here if STA wants otherwise.
        return $this->finalise($s, $by, submittedBy: User::find($s->submitted_by) ?? $by);
    }

    public function returnToDraft(Submission $s, User $by): Submission
    {
        $this->ownAssociation($s, $by);
        if ($by->role !== Role::AssocAdmin) { $this->fail('Only the association admin can return an entry.'); }
        if ($s->status !== SubmissionStatus::AwaitingAssocAdmin) { $this->fail('This entry is not waiting for approval.'); }
        $editor = User::find($s->submitted_by);
        $s->update(['status' => SubmissionStatus::Draft]);
        if ($editor) { $editor->notify(new SubmissionReturned($s)); }

        return $s;
    }

    public function reopen(Submission $s, User $by, ?CarbonInterface $newDeadline = null): Submission
    {
        $s->update(['status' => SubmissionStatus::Draft, 'deadline_override' => $newDeadline ?? $s->deadline_override]);
        Notification::send(Recipients::association($s->association), new DeadlineChanged($s, reopened: true));

        return $s;
    }

    public function extend(Submission $s, User $by, CarbonInterface $deadline): Submission
    {
        $s->update(['deadline_override' => $deadline]);
        Notification::send(Recipients::association($s->association), new DeadlineChanged($s, reopened: false));

        return $s;
    }

    private function finalise(Submission $s, User $approver, User $submittedBy): Submission
    {
        DB::transaction(function () use ($s, $approver, $submittedBy) {
            $revision = $s->revision + 1;
            $s->update([
                'status' => SubmissionStatus::Submitted,
                'revision' => $revision,
                'reference_no' => sprintf('%s-G%03d-P%d-R%d', $s->association->code, $s->game_id, $s->phase, $revision),
                'submitted_by' => $submittedBy->id,
                'submitted_at' => $s->submitted_at ?? now(),
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);
        });
        Notification::send(Recipients::sta(), new SubmissionReceived($s));
        if ($s->phase === 2) {
            GenerateEntryByEventPdfs::dispatch($s);
        }

        return $s;
    }

    private function ownAssociation(Submission $s, User $by): void
    {
        if (! $by->role->isAssociationRole() || $by->association_id !== $s->association_id) {
            $this->fail('You cannot act on another association\'s entry.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['submission' => $message]);
    }
}
```
`reopen` and `extend` are called only from admin screens that check `ManageGames` (Task 9). The `$by` argument is used for the activity log causer: activitylog already picks up `auth()->user()`, so `$by` only documents intent. Keep it in the signature, since Task 9 passes it.

- [ ] **Step 5: Write the notifications**

All of them `implements ShouldQueue`, `use Queueable`, `via()` ⇒ `['mail']`. The mail bodies use `MailMessage`. Each states the game name, phase number, association name and a button linking to the portal games page (`url('/portal')`) or, for STA, `url('/admin')`. The subjects are:
- `SubmissionAwaitingApproval`: "Phase {n} entry for {game} is waiting for your approval".
- `SubmissionReturned`: "Phase {n} entry for {game} was returned to draft".
- `SubmissionReceived`: "{association} submitted Phase {n} for {game} ({reference_no})".
- `DeadlineChanged`: when `$reopened` is true, "Phase {n} for {game} has been re-opened"; otherwise "Phase {n} deadline for {game} extended". The body includes the new deadline as `d M Y H:i` (Asia/Bahrain).

Each constructor takes `public Submission $submission` (and `public bool $reopened` for `DeadlineChanged`).

- [ ] **Step 6: Write the job stub**

`app/Jobs/GenerateEntryByEventPdfs.php`: `implements ShouldQueue`, `use Queueable`, `public function __construct(public Submission $submission) {}`, and an empty `public function handle(): void {}`.

- [ ] **Step 7: Run the tests and commit**

`git commit -m "feat: submission workflow and notifications"`

---

### Task 5: Admin game setup — details, associations, clothing items, phases

**Files:**
- Create `app/Filament/Resources/Games/`:
  - `GameResource.php`
  - `Pages/{ListGames, CreateGame, EditGame}.php`
  - `Schemas/GameForm.php`
  - `Tables/GamesTable.php`
  - `RelationManagers/PhasesRelationManager.php`
- Create `app/Notifications/PhaseOpened.php`.
- Test: `tests/Feature/GameSetupTest.php`

**Interfaces:**
- Consumes: Task 1 models and `Recipients`.
- Produces: `GameResource` (requires `StaPermission::ManageGames`, nav group "Games", icon `Heroicon::OutlinedFlag`). Later tasks add relation managers to `GameResource::getRelations()` and a `dashboard` page to `getPages()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Filament\Resources\Games\Pages\CreateGame;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\RelationManagers\PhasesRelationManager;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Game;
use App\Models\User;
use App\Notifications\PhaseOpened;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use function Pest\Livewire\livewire;

beforeEach(fn () => actingInAdmin(User::factory()->superAdmin()->create()));

it('creates a game with associations, clothing items and reminder days', function () {
    $a = Association::factory()->create();
    $shirt = ClothingItem::factory()->create(['name' => 'T-Shirt']);

    livewire(CreateGame::class)->fillForm([
        'name' => 'GCC Youth Games 2027', 'start_date' => '2027-04-01', 'end_date' => '2027-04-10',
        'reminder_days' => ['7', '3', '0'], 'associations' => [$a->id], 'clothingItems' => [$shirt->id],
    ])->call('create')->assertHasNoFormErrors();

    $game = Game::sole();
    expect($game->reminder_days)->toBe([7, 3, 0])->and($game->associations->pluck('id')->all())->toBe([$a->id])
        ->and($game->clothingItems->pluck('name')->all())->toBe(['T-Shirt']);
});

it('rejects an end date before the start date', function () {
    livewire(CreateGame::class)->fillForm(['name' => 'X', 'start_date' => '2027-04-10', 'end_date' => '2027-04-01', 'reminder_days' => ['7']])
        ->call('create')->assertHasFormErrors(['end_date']);
});

it('opens an enabled phase with a deadline and emails invited associations', function () {
    Notification::fake();
    $game = Game::factory()->create();
    $a = Association::factory()->create();
    $game->associations()->attach($a);
    $admin = User::factory()->assocAdmin($a)->create();
    $phase = $game->phase(1);
    $phase->update(['enabled' => true, 'deadline' => now()->addWeek()]);

    livewire(PhasesRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class])
        ->callAction(TestAction::make('open')->table($phase));

    expect($phase->fresh()->status)->toBe(PhaseStatus::Open);
    Notification::assertSentTo($admin, PhaseOpened::class);
});

it('will not open a disabled phase or one without a deadline', function () {
    $game = Game::factory()->create();
    livewire(PhasesRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class])
        ->assertActionHidden(TestAction::make('open')->table($game->phase(2)));
});

it('closes an open phase', function () {
    $game = Game::factory()->create();
    $p = $game->phase(1);
    $p->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    livewire(PhasesRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class])
        ->callAction(TestAction::make('close')->table($p));
    expect($p->fresh()->status)->toBe(PhaseStatus::Closed);
});

it('is hidden from STA users without manage_games', function () {
    $sta = User::factory()->sta()->create();
    actingInAdmin($sta);
    $this->get(\App\Filament\Resources\Games\GameResource::getUrl('index'))->assertForbidden();
    $sta->givePermissionTo(StaPermission::ManageGames->value);
    $this->get(\App\Filament\Resources\Games\GameResource::getUrl('index'))->assertOk();
});
```
Check the Filament 4.16 test API for relation manager table actions: `TestAction::make('open')->table($record)` and `assertActionHidden` exist. If a name differs, use the 4.16 equivalent and keep the meaning.

- [ ] **Step 2: Run it**

Expected: FAIL.

- [ ] **Step 3: Implement the resource**

Follow `app/Filament/Resources/Sports/SportResource.php`: `use RequiresStaPermission`, with `requiredPermission()` returning `StaPermission::ManageGames`. Pages are index, create and edit; relations `[PhasesRelationManager::class]`.

`GameForm::configure()`:
```php
return $schema->components([
    Section::make('Details')->columns(2)->schema([
        TextInput::make('name')->required()->maxLength(200)->columnSpanFull(),
        FileUpload::make('logo_path')->label('Game logo')->image()->acceptedFileTypes(['image/jpeg', 'image/png'])
            ->maxSize(2048)->disk(config('filesystems.private'))->directory('game-logos')->visibility('private'),
        DatePicker::make('start_date')->required(),
        DatePicker::make('end_date')->required()->afterOrEqual('start_date'),
        TagsInput::make('reminder_days')->label('Reminder days before the deadline')->required()
            ->nestedRecursiveRules(['integer', 'min:0', 'max:60'])
            ->helperText('E.g. 7, 3, 0 — emails on those days before the deadline, then daily while overdue.')
            ->dehydrateStateUsing(fn (array $state) => collect($state)->map(fn ($d) => (int) $d)->unique()->sortDesc()->values()->all()),
    ]),
    Section::make('Associations')->schema([
        CheckboxList::make('associations')->relationship('associations', 'name',
            fn ($query) => $query->where('is_active', true)->orderBy('name'))->columns(3)->searchable()->bulkToggleable(),
    ]),
    Section::make('Clothing items collected for this game')->schema([
        CheckboxList::make('clothingItems')->relationship('clothingItems', 'name',
            fn ($query) => $query->where('is_active', true)->orderBy('name'))->columns(3),
    ]),
]);
```
`GamesTable`: columns `name`, `start_date` (date), `end_date`, and an associations count (`counts('associations')`). Edit action only; no delete action.

- [ ] **Step 4: Write the phases relation manager**

`PhasesRelationManager` (relationship `phases`, record title "Phase"):
- **Table columns:**
  - `phase` formatted as "Phase {n} – {name}", with names `[1 => 'Entry by Sport', 2 => 'Entry by Number', 3 => 'Long List', 4 => 'Short List']` (put this as a `public const NAMES` on `GamePhase`);
  - `enabled` (IconColumn boolean);
  - `status` (badge: draft gray, open success, closed danger);
  - `opens_at` and `deadline` (dateTime `d M Y H:i`, timezone `Asia/Bahrain`).
- **No create or delete.**
- **`EditAction` form:** `Toggle::make('enabled')`, `DateTimePicker::make('opens_at')`, `DateTimePicker::make('deadline')->after('opens_at')`.
- **Actions:**
```php
Action::make('open')->label('Open')->icon(Heroicon::OutlinedLockOpen)->requiresConfirmation()
    ->visible(fn (GamePhase $r) => $r->enabled && $r->deadline !== null && $r->status !== PhaseStatus::Open)
    ->action(function (GamePhase $r) {
        $r->update(['status' => PhaseStatus::Open, 'opens_at' => $r->opens_at ?? now()]);
        foreach ($r->game->associations as $association) {
            Notification::send(Recipients::association($association), new PhaseOpened($r));
        }
    }),
Action::make('close')->label('Close')->icon(Heroicon::OutlinedLockClosed)->color('danger')->requiresConfirmation()
    ->visible(fn (GamePhase $r) => $r->status === PhaseStatus::Open)
    ->action(fn (GamePhase $r) => $r->update(['status' => PhaseStatus::Closed])),
```

- [ ] **Step 5: Write the notification**

`PhaseOpened` (ShouldQueue, mail): subject "Phase {n} – {name} is open for {game}". The body gives the deadline (`d M Y H:i`, Asia/Bahrain) and a button "Open the portal" ⇒ `url('/portal')`. The constructor takes `public GamePhase $phase`.

- [ ] **Step 6: Run the tests and commit**

`git commit -m "feat: game setup with phases and phase-opened email"`

---

### Task 6: Admin game sports and events (limits, age range, entry policy)

**Files:**
- Create: `app/Filament/Resources/Games/RelationManagers/GameSportsRelationManager.php`
- Modify: `GameResource::getRelations()`, adding the relation manager after Phases
- Test: append to `tests/Feature/GameSetupTest.php`

**Interfaces:**
- Consumes: `GameSport`, `GameEvent`, `Game::associations`.
- Produces: game sports with events and limits that the portal tasks read.

- [ ] **Step 1: Write the failing tests**

```php
use App\Enums\EventGender;
use App\Filament\Resources\Games\RelationManagers\GameSportsRelationManager;
use App\Models\Event;
use App\Models\GameSport;
use App\Models\Sport;

it('adds a sport of an invited association with all its active events', function () {
    $game = Game::factory()->create();
    $a = Association::factory()->create();
    $game->associations()->attach($a);
    $judo = Sport::factory()->create(['association_id' => $a->id, 'name' => 'Judo']);
    Event::factory()->create(['sport_id' => $judo->id, 'name' => 'Men -60kg', 'gender' => EventGender::Men]);
    Event::factory()->create(['sport_id' => $judo->id, 'name' => 'Old', 'is_active' => false]);

    livewire(GameSportsRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class])
        ->callAction(TestAction::make('create')->table(), ['sport_id' => $judo->id, 'p2_max_men' => 3, 'p2_max_total' => 5,
            'dob_from' => '2002-01-01', 'dob_to' => '2010-12-31', 'entry_policy' => 'Max 1 athlete per weight category'])
        ->assertHasNoFormErrors();

    $gs = GameSport::sole();
    expect($gs->p2_max_men)->toBe(3)->and($gs->gameEvents->pluck('event.name')->all())->toBe(['Men -60kg']);
});

it('only offers sports of invited associations, once', function () {
    $game = Game::factory()->create();
    $invited = Association::factory()->create();
    $game->associations()->attach($invited);
    $ok = Sport::factory()->create(['association_id' => $invited->id]);
    $other = Sport::factory()->create();

    livewire(GameSportsRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class])
        ->callAction(TestAction::make('create')->table(), ['sport_id' => $other->id])
        ->assertHasFormErrors(['sport_id']);
});

it('sets event maximums and rejects dob_to before dob_from', function () {
    $gs = GameSport::factory()->create();
    $ge = \App\Models\GameEvent::factory()->create(['game_id' => $gs->game_id, 'game_sport_id' => $gs->id]);

    livewire(GameSportsRelationManager::class, ['ownerRecord' => $gs->game, 'pageClass' => EditGame::class])
        ->callAction(TestAction::make('edit')->table($gs), ['dob_from' => '2010-01-01', 'dob_to' => '2005-01-01'])
        ->assertHasFormErrors(['dob_to']);

    livewire(GameSportsRelationManager::class, ['ownerRecord' => $gs->game, 'pageClass' => EditGame::class])
        ->callAction(TestAction::make('edit')->table($gs), ['gameEvents' => [['p2_max_athletes' => 2]]]);
    // If repeater item keys make the positional array not match, set state via the record key ("record-{id}").
    expect($ge->fresh()->p2_max_athletes)->toBe(2);
});
```

- [ ] **Step 2: Run them**

Expected: FAIL.

- [ ] **Step 3: Implement**

`GameSportsRelationManager` (relationship `gameSports`, title "Sports & events"):
- **Form:**
```php
Select::make('sport_id')->label('Sport')->required()->searchable()->disabledOn('edit')
    ->options(fn (RelationManager $livewire) => Sport::query()->where('is_active', true)
        ->whereIn('association_id', $livewire->getOwnerRecord()->associations()->pluck('associations.id'))
        ->whereNotIn('id', $livewire->getOwnerRecord()->gameSports()->pluck('sport_id'))
        ->with('association')->get()->mapWithKeys(fn (Sport $s) => [$s->id => "{$s->name} ({$s->association->code})"]))
    ->in(fn (RelationManager $livewire) => Sport::query()
        ->whereIn('association_id', $livewire->getOwnerRecord()->associations()->pluck('associations.id'))->pluck('id')->all()),
Fieldset::make('Phase 2 – Entry by Number (athletes)')->columns(3)->schema([
    TextInput::make('p2_max_men')->label('Max men')->integer()->minValue(0),
    TextInput::make('p2_max_women')->label('Max women')->integer()->minValue(0),
    TextInput::make('p2_max_total')->label('Max total')->integer()->minValue(0),
]),
Fieldset::make('Phase 4 – Short List')->columns(3)->schema([
    TextInput::make('p4_max_men')->integer()->minValue(0),
    TextInput::make('p4_max_women')->integer()->minValue(0),
    TextInput::make('p4_max_officials')->label('Max team officials')->integer()->minValue(0),
]),
Fieldset::make('Age eligibility (optional)')->columns(2)->schema([
    DatePicker::make('dob_from')->label('Born on or after'),
    DatePicker::make('dob_to')->label('Born on or before')->afterOrEqual('dob_from'),
]),
Textarea::make('entry_policy')->rows(3)->columnSpanFull(),
Repeater::make('gameEvents')->relationship()->label('Events')->visibleOn('edit')->addable(false)->deletable(false)
    ->reorderable(false)->columns(3)->columnSpanFull()->schema([
        Placeholder::make('event')->content(fn (?GameEvent $record) => $record?->event?->name),
        Placeholder::make('gender')->content(fn (?GameEvent $record) => $record?->event?->gender?->label()),
        TextInput::make('p2_max_athletes')->label('Phase 2 max athletes')->integer()->minValue(0),
    ]),
```
- **Create action:** `after()` creates a `GameEvent` for every active event of the sport:
```php
CreateAction::make()->after(function (GameSport $record) {
    foreach ($record->sport->events()->where('is_active', true)->orderBy('name')->get() as $event) {
        $record->gameEvents()->create(['game_id' => $record->game_id, 'event_id' => $event->id]);
    }
}),
```
- **Table:** columns sport name + association code, the three P2 maxima, and a game events count. Actions: Edit. Delete is allowed only while no submission exists for the game (`->visible(fn ($r) => ! $r->game->submissions()->exists())`).
- **Header action "Sync events":** adds game events for events created on the sport after it was added (only missing ones).

Use the Filament 4.16 class for `Placeholder`; it may be `Filament\Forms\Components\Placeholder` or `TextEntry` in the schema. Pick the one that renders read-only text inside a form.

- [ ] **Step 4: Run the tests and commit**

`git commit -m "feat: game sports, events, limits and age range"`

---

### Task 7: Portal games list and Phase 1 (Entry by Sport)

**Files:**
- Create:
  - `app/Filament/Portal/Pages/Games.php` + `resources/views/filament/portal/pages/games.blade.php`
  - `app/Filament/Portal/Pages/Phase1Entry.php` + `resources/views/filament/portal/pages/phase1-entry.blade.php`
  - `app/Filament/Portal/Concerns/HandlesSubmission.php`
- Test: `tests/Feature/Phase1EntryTest.php`

**Interfaces:**
- Consumes: `Game::isInvited`, `Game::sportsFor`, `Submission::for`, `PhaseGate`, `SubmissionWorkflow`.
- Produces:
  - **`HandlesSubmission` trait:** used by portal phase pages that set `public Submission $submission` and `public int $loadedVersion`. It provides `claimOrHalt(): void`, which is called first inside each page's save transaction. It also provides:
    - `submitAction()`, `approveAction()`, `returnAction()`: Filament `Action`s, visible by role and status.
    - `guardEditable(): void`, which sends a danger notification and halts when `PhaseGate` denies or the save is stale.
    - `refreshLoadedAt(): void`.
  - `Phase1Entry::getUrl(['game' => $id])` and `Phase2Entry::getUrl(['game' => $id])` (Task 8 follows the same route shape).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\InterestAnswerValue;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Games;
use App\Filament\Portal\Pages\Phase1Entry;
use App\Models\Association;
use App\Models\Game;
use App\Models\GameSport;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    $this->assoc = Association::factory()->create(['code' => 'BWF']);
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->wl = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Weightlifting'])->id]);
    $this->editor = User::factory()->assocEditor($this->assoc)->create();
    actingInPortal($this->editor);
});

it('lists invited games with the open phase', function () {
    Game::factory()->create(['name' => 'Not invited']);
    livewire(Games::class)->assertSee('Asian Indoor Games')->assertSee('Phase 1')->assertDontSee('Not invited');
});

it('saves Yes/No/Maybe answers and submits for approval', function () {
    livewire(Phase1Entry::class, ['game' => $this->game->id])
        ->set("answers.{$this->wl->id}", 'yes')->call('save')->assertHasNoErrors()
        ->callAction('submit');

    $s = Submission::sole();
    expect($s->answers()->sole()->answer)->toBe(InterestAnswerValue::Yes)->and($s->status)->toBe(SubmissionStatus::AwaitingAssocAdmin);
});

it('is read-only when the phase is closed', function () {
    $this->game->phase(1)->update(['status' => PhaseStatus::Closed]);
    livewire(Phase1Entry::class, ['game' => $this->game->id])->assertSee('This phase is closed.')
        ->set("answers.{$this->wl->id}", 'yes')->call('save');
    expect(\App\Models\InterestAnswer::count())->toBe(0);
});

it('returns 404 for a game the association is not invited to', function () {
    $other = Game::factory()->create();
    $this->get(Phase1Entry::getUrl(['game' => $other->id], tenant: $this->assoc))->assertNotFound();
});

it('refuses a stale save', function () {
    $page = livewire(Phase1Entry::class, ['game' => $this->game->id]);
    $s = Submission::sole();
    $s->claimVersion($s->lock_version); // someone else saved in between (same second is fine)
    $page->set("answers.{$this->wl->id}", 'no')->call('save')->assertNotified('This entry was changed by someone else — reload to see the latest.');
    expect(\App\Models\InterestAnswer::count())->toBe(0);
});
```

- [ ] **Step 2: Run it**

Expected: FAIL.

- [ ] **Step 3: Write the shared trait**

`app/Filament/Portal/Concerns/HandlesSubmission.php`:
```php
<?php

namespace App\Filament\Portal\Concerns;

use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Services\Phases\PhaseGate;
use App\Services\Phases\StaleSubmission;
use App\Services\Phases\SubmissionWorkflow;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;

trait HandlesSubmission
{
    public Submission $submission;

    public int $loadedVersion = 0;

    public ?string $readOnlyReason = null;

    protected function refreshLoadedAt(): void
    {
        $this->submission->refresh();
        $this->loadedVersion = (int) $this->submission->lock_version;
        $this->readOnlyReason = app(PhaseGate::class)->denialReason($this->submission);
    }

    /** Status/phase checks; call before validating input. */
    protected function guardEditable(): void
    {
        if ($reason = app(PhaseGate::class)->denialReason($this->submission->fresh())) {
            Notification::make()->danger()->title($reason)->send();
            throw new Halt;
        }
    }

    /** Call FIRST inside the save's DB::transaction: atomically claims the loaded version, else halts (rolling back). */
    protected function claimOrHalt(): void
    {
        try {
            app(PhaseGate::class)->assertFresh($this->submission, $this->loadedVersion);
        } catch (StaleSubmission $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            throw new Halt;
        }
    }

    /** Called before submit so unsaved changes go in; each page implements it. */
    abstract public function save(): void;

    protected function submitAction(): Action
    {
        return Action::make('submit')->label(fn () => auth()->user()->role === Role::AssocAdmin ? 'Submit to STA' : 'Send for approval')
            ->requiresConfirmation()->visible(fn () => $this->readOnlyReason === null)
            ->action(fn () => $this->runWorkflow(function () {
                $this->save();

                return app(SubmissionWorkflow::class)->submit($this->submission->fresh(), auth()->user());
            }));
    }

    protected function approveAction(): Action
    {
        return Action::make('approve')->label('Approve and submit to STA')->requiresConfirmation()->color('success')
            ->visible(fn () => auth()->user()->role === Role::AssocAdmin && $this->submission->status === SubmissionStatus::AwaitingAssocAdmin)
            ->action(fn () => $this->runWorkflow(fn () => app(SubmissionWorkflow::class)->approve($this->submission->fresh(), auth()->user())));
    }

    protected function returnAction(): Action
    {
        return Action::make('return')->label('Return to draft')->color('gray')->requiresConfirmation()
            ->visible(fn () => auth()->user()->role === Role::AssocAdmin && $this->submission->status === SubmissionStatus::AwaitingAssocAdmin)
            ->action(fn () => $this->runWorkflow(fn () => app(SubmissionWorkflow::class)->returnToDraft($this->submission->fresh(), auth()->user())));
    }

    private function runWorkflow(callable $step): void
    {
        try {
            $this->submission = $step();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }
        $this->refreshLoadedAt();
        Notification::make()->success()->title('Saved — status: '.$this->submission->status->label())->send();
    }
}
```

- [ ] **Step 4: Write the games page**

`Games` page:
- Navigation label "Games", icon `Heroicon::OutlinedFlag`, sort 1.
- The view loops over `$this->games`, which is the tenant's invited games ordered by `start_date`.
- Each card shows:
  - the game name and dates;
  - for each enabled phase: name, status, the association's effective deadline, and the association's submission status ("No response" when there is none);
  - a link to `Phase1Entry::getUrl(['game' => $g->id])` or `Phase2Entry::getUrl(['game' => $g->id])` when that phase is enabled and not draft. Phase 3 and 4 links come in Milestones 3–4.

Build the view with Filament's `<x-filament::section>` and plain Tailwind classes that the panel theme already ships. `Phase2Entry` does not exist until Task 8, so guard its link with `class_exists(\App\Filament\Portal\Pages\Phase2Entry::class)`. Task 8 then works without touching this file.

- [ ] **Step 5: Write the Phase 1 page**

`Phase1Entry` page:
- **Route and access:**
  - Route `games/{game}/phase-1`, via `public static function getRoutePath(Panel $panel): string { return '/games/{game}/phase-1'; }`. Use the Filament 4.16 mechanism for page routes with parameters.
  - `shouldRegisterNavigation()` returns false.
  - `mount(int|string $game)`:
```php
$this->game = Game::findOrFail($game);
$tenant = Filament::getTenant();
abort_unless($this->game->isInvited($tenant) && $this->game->phase(1)->enabled, 404);
$this->submission = Submission::for($this->game, $tenant, 1);
$this->answers = $this->submission->answers()->pluck('answer', 'game_sport_id')->map(fn ($a) => $a->value)->all();
$this->refreshLoadedAt();
```
- **Properties:** `public Game $game`, `public array $answers = []`.
- **`save()`:**
```php
public function save(): void
{
    $this->guardEditable();
    $valid = $this->game->sportsFor(Filament::getTenant())->pluck('id')->all();
    DB::transaction(function () use ($valid) {
        $this->claimOrHalt();
        foreach ($this->answers as $gameSportId => $answer) {
            if (! in_array((int) $gameSportId, $valid, true) || InterestAnswerValue::tryFrom((string) $answer) === null) {
                continue;
            }
            InterestAnswer::updateOrCreate(['submission_id' => $this->submission->id, 'game_sport_id' => $gameSportId], ['answer' => $answer]);
        }
        // lock_version already bumped by claimOrHalt()
    });
    $this->refreshLoadedAt();
    Notification::make()->success()->title('Saved')->send();
}
```
- **`getHeaderActions()`:** `[$this->submitAction(), $this->approveAction(), $this->returnAction()]`.
- **View:**
  - Title: "{game} – Phase 1 · Entry by Sport". Under it, the deadline and the `readOnlyReason` banner when set.
  - One row per sport from `sportsFor`: sport name and three radio inputs (`wire:model="answers.{{ $gs->id }}"`, values yes/no/maybe, each a real `<label>`), with "No response" shown when unanswered.
  - The radios are disabled when `readOnlyReason` is set. "Save" is a `<x-filament::button wire:click="save">`.

- [ ] **Step 6: Run the tests and commit**

`git commit -m "feat: portal games list and phase 1 entry by sport"`

---

### Task 8: Portal Phase 2 (Entry by Number)

**Files:**
- Create: `app/Filament/Portal/Pages/Phase2Entry.php` + `resources/views/filament/portal/pages/phase2-entry.blade.php`
- Test: `tests/Feature/Phase2EntryTest.php`

**Interfaces:**
- Consumes: `HandlesSubmission`, `Phase2Limits`, `Game::sportsFor`, Phase 1 answers.
- Produces: `Phase2Entry::visibleSports(): Collection<GameSport>`, the sports shown (those answered "no" in Phase 1 are hidden when Phase 1 is enabled).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Phase2Entry;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\InterestAnswer;
use App\Models\NumberEntry;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    Bus::fake();
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id, 'p2_max_men' => 2, 'entry_policy' => 'One athlete per weight',
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id, 'p2_max_athletes' => 1,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->admin = User::factory()->assocAdmin($this->assoc)->create();
    actingInPortal($this->admin);
});

it('shows the entry policy and saves numbers within limits', function () {
    livewire(Phase2Entry::class, ['game' => $this->game->id])->assertSee('One athlete per weight')
        ->set("counts.{$this->m60->id}", 1)->call('save')->assertHasNoErrors();

    expect(NumberEntry::sole()->count)->toBe(1);
});

it('blocks saving over a limit and shows the message', function () {
    livewire(Phase2Entry::class, ['game' => $this->game->id])
        ->set("counts.{$this->m60->id}", 2)->call('save')
        ->assertHasErrors(["counts.{$this->m60->id}"])->assertSee('Men -60kg: at most 1 athletes.');
    expect(NumberEntry::count())->toBe(0);
});

it('submits directly as association admin and generates the PDF job', function () {
    livewire(Phase2Entry::class, ['game' => $this->game->id])->set("counts.{$this->m60->id}", 1)->callAction('submit');
    expect(Submission::sole()->status)->toBe(SubmissionStatus::Submitted);
    Bus::assertDispatched(\App\Jobs\GenerateEntryByEventPdfs::class);
});

it('hides sports answered No in phase 1', function () {
    $this->game->phase(1)->update(['enabled' => true]);
    $p1 = Submission::for($this->game, $this->assoc, 1);
    InterestAnswer::create(['submission_id' => $p1->id, 'game_sport_id' => $this->judo->id, 'answer' => 'no']);

    livewire(Phase2Entry::class, ['game' => $this->game->id])->assertDontSee('Men -60kg');
});
```

- [ ] **Step 2: Run it**

Expected: FAIL.

- [ ] **Step 3: Implement**

`Phase2Entry`, with the same route pattern as Phase 1, at `/games/{game}/phase-2`:
- **Properties:** `public array $counts = []` (game_event_id ⇒ int).
- **`mount`:** the same checks as Phase 1 for phase 2. Then load `counts` from `$this->submission->numbers()->pluck('count', 'game_event_id')`.
- **`visibleSports()`:**
```php
public function visibleSports(): Collection
{
    $sports = $this->game->sportsFor(Filament::getTenant());
    if (! $this->game->phase(1)->enabled) {
        return $sports;
    }
    $p1 = Submission::withoutGlobalScopes()->where(['game_id' => $this->game->id, 'association_id' => Filament::getTenant()->id, 'phase' => 1])->first();
    $no = $p1 ? $p1->answers()->where('answer', 'no')->pluck('game_sport_id')->all() : [];

    return $sports->reject(fn ($gs) => in_array($gs->id, $no, true))->values();
}
```
- **`save()`:**
```php
public function save(): void
{
    $this->guardEditable();
    $errors = [];
    foreach ($this->visibleSports() as $gs) {
        foreach (app(Phase2Limits::class)->errors($gs, $this->counts) as $key => $message) {
            $errors[str_starts_with($key, 'event.') ? 'counts.'.substr($key, 6) : "sport.{$gs->id}"] = $message;
        }
    }
    if ($errors) {
        throw ValidationException::withMessages($errors);
    }
    $allowed = $this->visibleSports()->flatMap->gameEvents->pluck('id')->all();
    DB::transaction(function () use ($allowed) {
        $this->claimOrHalt();
        foreach ($this->counts as $gameEventId => $count) {
            if (in_array((int) $gameEventId, $allowed, true)) {
                NumberEntry::updateOrCreate(['submission_id' => $this->submission->id, 'game_event_id' => $gameEventId], ['count' => (int) $count]);
            }
        }
        // lock_version already bumped by claimOrHalt()
    });
    $this->refreshLoadedAt();
    Notification::make()->success()->title('Saved')->send();
}
```
- **Live checking:** an `updatedCounts()` hook runs the same `Phase2Limits` check and stores messages in `public array $liveErrors`, so they show while typing without saving. Use `wire:model.live.debounce.400ms`.
- **View:**
  - Header and deadline, the read-only banner, and the header actions from the trait.
  - Per visible sport: a section titled with the sport name, the `entry_policy` text, and the age eligibility if set.
  - A table with columns Event / Men / Women:
    - each event row has a numeric `<input type="number" min="0">` in the column of its gender, with the other column greyed;
    - mixed events show one input spanning both columns, labelled "Mixed";
    - each event shows its own max as a hint.
  - A totals row: men, women and total, with the sport maxima.
  - Errors show next to the input (`counts.{id}`) and under the sport (`sport.{gsId}`).
  - Inputs are disabled when `readOnlyReason` is set.
  - When the submission is submitted and `pdf_paths` has entries: a "Download Entry by Event PDF" link per sport via `Storage::disk(config('filesystems.private'))->temporaryUrl($path, now()->addMinutes(5))`.

- [ ] **Step 4: Run the tests and commit**

`git commit -m "feat: portal phase 2 entry by number with live limits"`

---

### Task 9: Entry by Event PDF (dompdf)

**Files:**
- Create: `resources/views/pdf/entry-by-event.blade.php`
- Modify: `app/Jobs/GenerateEntryByEventPdfs.php` (replace the stub), `composer.json` (`composer require barryvdh/laravel-dompdf`), `README.md`
- Test: `tests/Feature/EntryByEventPdfTest.php`

**Interfaces:**
- Consumes: `Submission` (submitted, phase 2), `Setting::NOC_LOGO`, `Setting::DECLARATION`, `Game::logo_path`.
- Produces: `submissions.pdf_paths = [game_sport_id => 'submissions/{reference_no}/{sport-slug}.pdf']` on the private disk.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\EventGender;
use App\Enums\SubmissionStatus;
use App\Jobs\GenerateEntryByEventPdfs;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\NumberEntry;
use App\Models\Sport;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;

it('stores one Entry by Event PDF per sport with the reference number', function () {
    Storage::fake(config('filesystems.private'));
    $assoc = Association::factory()->create(['code' => 'BWF']);
    $game = Game::factory()->create();
    $gs = GameSport::factory()->create(['game_id' => $game->id, 'entry_policy' => 'Max 1 per category',
        'sport_id' => Sport::factory()->create(['association_id' => $assoc->id, 'name' => 'Weightlifting'])->id]);
    $ge = GameEvent::factory()->create(['game_id' => $game->id, 'game_sport_id' => $gs->id,
        'event_id' => Event::factory()->create(['sport_id' => $gs->sport_id, 'name' => 'Men 61kg', 'gender' => EventGender::Men])->id]);
    $s = Submission::for($game, $assoc, 2);
    $s->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BWF-G001-P2-R1', 'revision' => 1]);
    NumberEntry::create(['submission_id' => $s->id, 'game_event_id' => $ge->id, 'count' => 1]);

    (new GenerateEntryByEventPdfs($s))->handle();

    $path = $s->fresh()->pdf_paths[$gs->id];
    expect($path)->toBe('submissions/BWF-G001-P2-R1/weightlifting.pdf');
    $pdf = Storage::disk(config('filesystems.private'))->get($path);
    expect(substr($pdf, 0, 4))->toBe('%PDF');
});

it('renders the expected content', function () {
    $html = view('pdf.entry-by-event', [
        'game' => Game::factory()->make(['name' => 'Asian Indoor Games']), 'association' => Association::factory()->make(['name' => 'Bahrain Weightlifting Federation']),
        'gameSport' => GameSport::factory()->make(['entry_policy' => 'Max 1 per category']), 'sportName' => 'Weightlifting',
        'rows' => [['event' => 'Men 61kg', 'gender' => 'men', 'count' => 1]], 'totals' => ['men' => 1, 'women' => 0, 'total' => 1],
        'referenceNo' => 'BWF-G001-P2-R1', 'declaration' => 'I confirm.', 'nocLogo' => null, 'gameLogo' => null,
    ])->render();

    expect($html)->toContain('Entry by Event')->toContain('Men 61kg')->toContain('Max 1 per category')
        ->toContain('BWF-G001-P2-R1')->toContain('I confirm.')->toContain('Stamp');
});
```

- [ ] **Step 2: Run it**

Expected: FAIL.

- [ ] **Step 3: Install dompdf**

`$PHP /c/Users/ababy/.config/herd/bin/composer.phar require barryvdh/laravel-dompdf`. Confirm it supports Laravel 12 and PHP 8.5. If composer refuses PHP 8.5, report BLOCKED with the composer output rather than forcing it.

- [ ] **Step 4: Write the job**

```php
public function handle(): void
{
    $s = $this->submission->fresh(['game', 'association']);
    $disk = Storage::disk(config('filesystems.private'));
    $paths = [];
    $counts = $s->numbers()->pluck('count', 'game_event_id');
    foreach ($s->game->sportsFor($s->association) as $gs) {
        $rows = $gs->gameEvents->sortBy('event.name')->map(fn ($ge) => [
            'event' => $ge->event->name, 'gender' => $ge->event->gender->value, 'count' => (int) ($counts[$ge->id] ?? 0),
        ])->values()->all();
        $totals = ['men' => collect($rows)->where('gender', 'men')->sum('count'), 'women' => collect($rows)->where('gender', 'women')->sum('count'),
            'total' => collect($rows)->sum('count')];
        $html = view('pdf.entry-by-event', [
            'game' => $s->game, 'association' => $s->association, 'gameSport' => $gs, 'sportName' => $gs->sport->name,
            'rows' => $rows, 'totals' => $totals, 'referenceNo' => $s->reference_no,
            'declaration' => Setting::get(Setting::DECLARATION, Setting::DEFAULT_DECLARATION),
            'nocLogo' => self::dataUri(Setting::get(Setting::NOC_LOGO)), 'gameLogo' => self::dataUri($s->game->logo_path),
        ])->render();
        $path = 'submissions/'.$s->reference_no.'/'.Str::slug($gs->sport->name).'.pdf';
        $disk->put($path, Pdf::loadHTML($html)->setPaper('a4')->output());
        $paths[$gs->id] = $path;
    }
    $s->forceFill(['pdf_paths' => $paths])->saveQuietly();
}

private static function dataUri(?string $path): ?string
{
    $disk = Storage::disk(config('filesystems.private'));
    if (! $path || ! $disk->exists($path)) {
        return null;
    }

    return 'data:'.$disk->mimeType($path).';base64,'.base64_encode($disk->get($path));
}
```
`saveQuietly` keeps `updated_at` stable for the optimistic-lock check, so leave it as it is.

- [ ] **Step 5: Write the PDF view**

`resources/views/pdf/entry-by-event.blade.php` is a full HTML document. Use only tables and inline CSS, because dompdf has no flexbox or grid. Use the DejaVu Sans font family, which is dompdf's built-in font with Unicode coverage, at 11px.
- **Header table:** the NOC logo on the left (`<img src="{{ $nocLogo }}">` when set), the centre text "{{ $game->name }}", "Entry by Event", "{{ $sportName }}", and the game logo on the right.
- **Association line:** association name, the date range of the game, and "Reference: {{ $referenceNo }}".
- **"Entry policy" box:** `$gameSport->entry_policy`, shown when set.
- **"Age eligibility" line:** born between `dob_from` and `dob_to`, shown when set.
- **Main table:** columns Event | Male | Female.
  - A men's event's count goes in Male, with the Female cell shaded `#d9d9d9`.
  - A women's event is the mirror image.
  - A mixed event's count goes in a cell spanning Male and Female, marked "(mixed)".
  - The table ends with a totals row: men, women, and the overall total.
- **Declaration paragraph:** `{{ $declaration }}`.
- **Signature table:** four labelled boxes, Name | Position | Date | Stamp, each 60px high with borders.
- **Footer:** "Reference {{ $referenceNo }} · generated {{ now()->timezone('Asia/Bahrain')->format('d M Y H:i') }}".

- [ ] **Step 6: Update the README**

Add to "Production notes": "PDFs: dompdf (pure PHP), no extra server packages. Queue worker must run (`queue:work`) for PDFs and emails."

- [ ] **Step 7: Run the tests and commit**

`git commit -m "feat: Entry by Event PDF per sport with dompdf"`

---

### Task 10: Admin game dashboard (status matrix, remind pending, extend, re-open, PDFs)

**Files:**
- Create:
  - `app/Filament/Resources/Games/Pages/GameDashboard.php` + `resources/views/filament/resources/games/pages/game-dashboard.blade.php`
  - `app/Notifications/PhaseReminder.php`
- Modify: `GameResource::getPages()`, adding `'dashboard' => GameDashboard::route('/{record}/dashboard')`. Also add a "Dashboard" record action in `GamesTable`.
- Test: `tests/Feature/GameDashboardTest.php`

**Interfaces:**
- Consumes: `SubmissionWorkflow::extend/reopen`, `Recipients`, `PhaseGate`.
- Produces:
  - `GameDashboard::cell(Association $a, int $phase): array{status: string, label: string, submission: ?Submission}`, where status is one of `no_response|draft|awaiting|submitted|overdue`.
  - `PhaseReminder` notification (also used by Task 11): constructor `(GamePhase $phase, Association $association, bool $overdue)`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\Pages\GameDashboard;
use App\Models\Association;
use App\Models\Game;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\DeadlineChanged;
use App\Notifications\PhaseReminder;
use Illuminate\Support\Facades\Notification;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    $this->game = Game::factory()->create();
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(3)]);
    $this->done = Association::factory()->create(['name' => 'Done Fed', 'code' => 'DON']);
    $this->late = Association::factory()->create(['name' => 'Late Fed', 'code' => 'LAT']);
    $this->game->associations()->attach([$this->done->id, $this->late->id]);
    Submission::for($this->game, $this->done, 2)->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'DON-G001-P2-R1']);
    $this->lateAdmin = User::factory()->assocAdmin($this->late)->create();
    actingInAdmin(User::factory()->superAdmin()->create());
});

it('shows the status matrix', function () {
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->assertSee('Done Fed')->assertSee('Submitted')->assertSee('Late Fed')->assertSee('No response');
});

it('reminds only pending associations', function () {
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])->callAction('remindPending', ['phase' => 2]);
    Notification::assertSentTo($this->lateAdmin, PhaseReminder::class);
    Notification::assertSentTimes(PhaseReminder::class, 1);
});

it('extends and re-opens for one association', function () {
    $page = livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()]);
    $page->call('extend', $this->late->id, 2, now()->addDays(10)->format('Y-m-d H:i'));
    expect(Submission::withoutGlobalScopes()->where('association_id', $this->late->id)->sole()->deadline_override)->not->toBeNull();

    $page->call('reopen', $this->done->id, 2);
    expect(Submission::withoutGlobalScopes()->where('association_id', $this->done->id)->sole()->status)->toBe(SubmissionStatus::Draft);
    Notification::assertSentTo($this->lateAdmin, DeadlineChanged::class);
});

it('needs view_submissions to see and manage_games to extend', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($sta);
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])->assertOk()
        ->call('extend', $this->late->id, 2, now()->addDays(10)->format('Y-m-d H:i'))->assertForbidden();
});
```
The table-free `call('extend', …)` and `call('reopen', …)` style is deliberate: the matrix cells are buttons that open a small modal. The Livewire methods carry the rule checks and are what the tests call. If the implementer builds the cell actions as Filament `Action`s with arguments, keep the public methods as the single code path and have the actions call them.

- [ ] **Step 2: Run it**

Expected: FAIL.

- [ ] **Step 3: Implement the page**

`GameDashboard extends Page` with `use InteractsWithRecord`. It follows the Filament 4.16 resource custom-page-with-record pattern.
- **Access:** `canAccess()` requires `ViewSubmissions` (Super Admin passes via the Gate).
- **Header:** game name, dates, a link back to edit the game, and a toggle filter `public bool $pendingOnly = false`.
- **Matrix:** rows are invited associations (name + code), sorted by name; columns are the enabled phases.
- **Cells:** from `cell()`:
```php
public function cell(Association $a, int $phase): array
{
    $s = $this->submissions->get("{$a->id}-{$phase}"); // preloaded keyed collection
    $deadline = $this->record->phase($phase)->effectiveDeadline($s);
    $status = match (true) {
        $s?->status === SubmissionStatus::Submitted => 'submitted',
        $s?->status === SubmissionStatus::AwaitingAssocAdmin => 'awaiting',
        $deadline !== null && now()->greaterThan($deadline) => 'overdue',
        $s !== null && ($s->answers()->exists() || $s->numbers()->exists()) => 'draft',
        default => 'no_response',
    };
    $labels = ['submitted' => 'Submitted', 'awaiting' => 'Awaiting assoc. admin', 'overdue' => 'Overdue', 'draft' => 'In progress', 'no_response' => 'No response'];

    return ['status' => $status, 'label' => $labels[$status], 'submission' => $s];
}
```
  - Each cell shows a coloured badge, which must differ by lightness and not only hue:
    - submitted: success
    - awaiting: info
    - draft: warning
    - overdue: danger
    - no response: gray
  - Under the badge: the effective deadline when overridden, the `reference_no` and PDF links (5-minute `temporaryUrl` per `pdf_paths` entry) when submitted, and buttons "Extend" and "Re-open". The buttons are visible only with `ManageGames`; Re-open shows only when submitted or awaiting.
- **`pendingOnly`** hides rows whose enabled open phases are all submitted.
- **Methods:**
```php
public function extend(int $associationId, int $phase, string $deadline): void
{
    abort_unless(auth()->user()->can(StaPermission::ManageGames->value), 403);
    $a = $this->record->associations()->findOrFail($associationId);
    app(SubmissionWorkflow::class)->extend(Submission::for($this->record, $a, $phase), auth()->user(), Carbon::parse($deadline, 'Asia/Bahrain'));
    $this->loadSubmissions();
}

public function reopen(int $associationId, int $phase, ?string $deadline = null): void
{
    abort_unless(auth()->user()->can(StaPermission::ManageGames->value), 403);
    $a = $this->record->associations()->findOrFail($associationId);
    app(SubmissionWorkflow::class)->reopen(Submission::for($this->record, $a, $phase), auth()->user(), $deadline ? Carbon::parse($deadline, 'Asia/Bahrain') : null);
    $this->loadSubmissions();
}
```
- **Header action `remindPending`:**
  - Its form has `Select::make('phase')`, whose options are the enabled phases with status `open`.
  - It is visible with `SendReminders`.
  - It notifies every invited association whose submission for that phase is not `submitted` with `PhaseReminder($phase, $association, overdue: now() > effective deadline)`, sending to `Recipients::association($a)`.
  - It shows "Reminder sent to N associations".
- The Filament "Extend" modal uses a `DateTimePicker` and calls `extend()`.

- [ ] **Step 4: Write the reminder notification**

`PhaseReminder` (ShouldQueue, mail):
- Subject "Reminder: Phase {n} – {name} for {game} is due {deadline d M Y}". When overdue, "Overdue: Phase {n} – {name} for {game}".
- The body gives the effective deadline for that association and a button to the portal.
- Constructor `(public GamePhase $phase, public Association $association, public bool $overdue)`.

- [ ] **Step 5: Run the tests and commit**

`git commit -m "feat: game dashboard matrix, reminders, extensions and re-open"`

---

### Task 11: Scheduled phase reminders

**Files:**
- Create: `app/Console/Commands/SendPhaseReminders.php`
- Modify: `routes/console.php` (schedule), `README.md`
- Test: `tests/Feature/PhaseRemindersTest.php`

**Interfaces:**
- Consumes: `PhaseReminder`, `Recipients`, `GamePhase::effectiveDeadline`.
- Produces: the artisan command `phases:send-reminders`, scheduled daily at 08:00 Asia/Bahrain.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\Game;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\PhaseReminder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->game = Game::factory()->create(['reminder_days' => [7, 3, 0]]);
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => '2026-12-10 23:59:00']);
    $this->a = Association::factory()->create();
    $this->game->associations()->attach($this->a);
    $this->user = User::factory()->assocAdmin($this->a)->create();
});

it('reminds on each configured day before the deadline', function (string $today, bool $sent) {
    Carbon::setTestNow(Carbon::parse($today.' 08:00'));
    $this->artisan('phases:send-reminders')->assertSuccessful();
    $sent ? Notification::assertSentTo($this->user, PhaseReminder::class) : Notification::assertNothingSent();
})->with([
    '7 days before' => ['2026-12-03', true],
    '5 days before' => ['2026-12-05', false],
    '3 days before' => ['2026-12-07', true],
    'deadline day' => ['2026-12-10', true],
    'overdue' => ['2026-12-12', true],
]);

it('skips submitted associations, closed phases and uses the association extension', function () {
    Carbon::setTestNow(Carbon::parse('2026-12-07 08:00'));
    $s = Submission::for($this->game, $this->a, 2);
    $s->update(['deadline_override' => '2026-12-20 23:59:00']); // 13 days left -> no reminder
    $this->artisan('phases:send-reminders');
    Notification::assertNothingSent();

    $s->update(['deadline_override' => null, 'status' => SubmissionStatus::Submitted]);
    $this->artisan('phases:send-reminders');
    Notification::assertNothingSent();

    $s->update(['status' => SubmissionStatus::Draft]);
    $this->game->phase(2)->update(['status' => PhaseStatus::Closed]);
    $this->artisan('phases:send-reminders');
    Notification::assertNothingSent();
});
```

- [ ] **Step 2: Run it**

Expected: FAIL.

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Console\Commands;

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\GamePhase;
use App\Models\Submission;
use App\Notifications\PhaseReminder;
use App\Services\Phases\Recipients;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendPhaseReminders extends Command
{
    protected $signature = 'phases:send-reminders';

    protected $description = 'Email associations that have not submitted an open phase, on the game\'s reminder days and daily while overdue';

    public function handle(): int
    {
        $today = now()->startOfDay(); // app time zone is Asia/Bahrain
        $sent = 0;
        GamePhase::query()->where('enabled', true)->where('status', PhaseStatus::Open)->whereNotNull('deadline')
            ->with('game.associations')->each(function (GamePhase $phase) use ($today, &$sent) {
                foreach ($phase->game->associations as $association) {
                    $s = Submission::withoutGlobalScopes()->where(['game_id' => $phase->game_id, 'association_id' => $association->id, 'phase' => $phase->phase])->first();
                    if ($s?->status === SubmissionStatus::Submitted) {
                        continue;
                    }
                    $deadlineDay = $phase->effectiveDeadline($s)->copy()->startOfDay();
                    $daysLeft = (int) $today->diffInDays($deadlineDay, false);
                    $overdue = $daysLeft < 0;
                    if ($overdue || in_array($daysLeft, $phase->game->reminder_days ?? [], true)) {
                        Notification::send(Recipients::association($association), new PhaseReminder($phase, $association, $overdue));
                        $sent++;
                    }
                }
            });
        $this->info("Reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
```
`reminder_days` holds ints (Task 5 casts them), so the `in_array` strict check works.

In `routes/console.php`:
```php
Schedule::command('phases:send-reminders')->dailyAt('08:00')->timezone('Asia/Bahrain');
```
README "Production notes": "Cron: `* * * * * php /path/artisan schedule:run` — sends phase reminders daily at 08:00 Bahrain time."

- [ ] **Step 4: Run the tests, pint and commit**

Run the full suite, then `$PHP vendor/bin/pint`, then the full suite again.

`git commit -m "feat: scheduled phase reminders"`

---

## Spec coverage (Milestone 2)

| Spec item | Task |
|---|---|
| games, game_associations, game_sports, game_events, game_clothing_items, game_phases | 1, 5, 6 |
| Reminder days per game | 5, 11 |
| Limits incl. total, age range, entry policy | 6 |
| Phase enable, open/close, deadlines | 5 |
| Per-association extension and re-open (+ email) | 4, 10 |
| Gating: disabled, draft, closed, past deadline, per-association override | 2 |
| Phase 1 Yes/No/Maybe, "No response", hide "no" sports later | 7, 8, 10 |
| Phase 2 grid, live hard limits (event, men, women, total; mixed only own max), entry policy shown | 3, 8 |
| Entry by Event PDF per sport (logos, Male/Female columns greyed, totals, policy, age, declaration, boxes, reference) | 9 |
| Submit flow editor → assoc admin → submitted; direct submit; return to draft | 4, 7 |
| Notifications: phase opened, awaiting approval, received, extended/re-opened, reminders, Remind pending | 4, 5, 10, 11 |
| Status matrix with pending filter | 10 |
| Optimistic locking | 2, 7, 8 |
| Audit log on games and submissions | 1 |
| Tenant isolation for submissions and portal pages | 1, 7 |
| Comments, STA editing submissions, Phases 3–4, reports | Out of scope (Milestones 3–4) |
