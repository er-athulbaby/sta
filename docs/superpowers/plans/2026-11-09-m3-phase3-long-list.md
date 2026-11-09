# Milestone 3 – Phase 3 Long List Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Associations build their Phase 3 Long List in the portal (athletes per game event, team officials per game sport, per-game travel and insurance details), see hard errors and warnings, submit it through the existing flow, and get a Long List PDF; STA reads every submission on one admin page (content, PDFs, comments, audit trail), comments on it (emailed to the association), and edits any phase directly at any status.

**Architecture:**
- **Data:** `entries` (Phase 3 list rows), `long_list_details` (per game, per person travel and insurance) and `comments` hang off `Submission`. Every submission-owned model logs to the activity log name `submission-{id}`, so one query returns a submission's audit trail.
- **Rules:** one new service, `LongListChecks`, holds the Phase 3 hard errors, the submit-only completeness check, the warnings and the table rows. `SubmissionWorkflow` calls it on submit and approve.
- **Screens:** the state, load and save of each phase move out of the portal pages into traits (`EditsPhase1/2/3`). The portal pages and the new STA edit pages are thin wrappers around the same trait and the same Blade view; only the edit-permission check (`editDenialReason`) differs.
- **Background work:** a queued dompdf job renders the Long List PDF on final submit and after an STA edit of a submitted list.

**Tech Stack:** PHP 8.5 (Herd `php85`), Laravel 12, Filament 4.16, Livewire 3, Pest 4, `barryvdh/laravel-dompdf`, spatie/laravel-activitylog, Laravel notifications (mail, queued).

**Spec:** `docs/superpowers/specs/2026-11-04-sports-entry-system-design.md`.
- Sections 4 (Workflow: `entries`, `comments`, audit log), 5 (Phase 3, Submit flow: "STA can edit any submission directly at any status (audited) and add comments"), 6 (`/admin` submission view), 7 (new comment email), 9 (Validation), 12 (Milestone 3) and 13 ("Per game", "Required fields and completeness", "Sensitive data").
- Phase 4, reports and Excel exports are **out of scope** (Milestone 4).

## Global Constraints

- **PHP:** always run `/c/Users/ababy/.config/herd/bin/php85/php.exe` (written `$PHP` below), and Pest as `$PHP vendor/bin/pest`. Format with `$PHP vendor/bin/pint` before each commit.
- **Time zone:** the app runs in `Asia/Bahrain` (`config/app.php`). Dates are stored, compared and shown in Bahrain time; no code converts time zones.
- **Association phase gating (unchanged):** an association can edit only while the phase is enabled, its status is `open`, now ≤ `deadline_override` (if set) else the phase `deadline`, and the submission is `draft`. Closed phases are read-only to associations. `PhaseGate::denialReason()` is the single source.
- **STA edits (spec 5 Submit flow):** STA users with `StaPermission::EditSubmissions` **and** `ViewSubmissions` (Super Admin always) edit any submission of phases 1–3 directly, at any status and after the deadline. The only gate is that the phase is enabled. STA edits never change the status, never submit, approve or return, still use the `lock_version` claim, and still hit the hard errors (Phase 2 limits, Phase 3 hard errors). After an STA save of a `submitted` Phase 2 or 3 submission, its PDFs are regenerated under the same `reference_no`.
- **Submit flow (unchanged):** editor submit → `awaiting_assoc_admin` (association admins emailed); association admin approves → `submitted` (STA notified); association admin submitting directly → `submitted`. `reference_no` = `{ASSOC CODE}-G{game id, 3 digits}-P{phase}-R{revision}`; the revision goes up on every final submit.
- **Visibility rule:** the sports and events an association may enter in Phases 2 and 3 are `Game::phase2SportsFor($association)` (all its sports in the game minus those answered "no" in Phase 1 when Phase 1 is enabled).
- **Long List data:**
  - `entries`: athletes have `game_event_id` and `game_sport_id` = the event's sport; officials have `game_event_id = null` and `game_sport_id` = the sport. One row per (submission, person, event) for athletes and per (submission, person, sport) for officials; the save code keeps that unique (a DB unique on all four columns does not compare nulls).
  - `long_list_details`: one optional row per (submission, person): insurance company, policy number, valid-until date, certificate, vaccinations. Saving the list deletes the rows (and certificate files) of people no longer on it.
  - Saving the list makes the saved entries equal to the visible selection. Entries in sports that became hidden are deleted on the next save.
- **Long List hard errors (block save, submit and approve):** a person not of the submission's association or inactive; an official on an athlete slot or an athlete on an official slot; an athlete whose gender does not match the event (`male`↔`men`, `female`↔`women`, `mixed` accepts both); an event or sport not in the visibility rule.
- **Submit-only error (spec 13):** any person on the saved list with `Person::missingFields()` non-empty blocks submit and approve, with a message naming the person and the missing fields. Saving is allowed.
- **Long List warnings (never block):** event count > its Phase 2 number; passport or CPR expiry before `games.end_date`; passport scan missing; CPR front scan missing; athlete date of birth outside the game sport's `dob_from`–`dob_to`; a missing size for any game clothing item whose `applies_to` includes the person's type. Age is shown as `Person::age($game->start_date)`.
- **Phase 2 number:** the count in the association's Phase 2 submission (`number_entries`) for that game event, used only when that Phase 2 submission is `submitted` (a missing row counts as 0). Otherwise there is no comparison.
- **Optimistic locking:** every write to a submission's content (list save, travel details save) claims the loaded `lock_version` first inside its DB transaction via `HandlesSubmission::claimOrHalt()`. A failed claim shows "This entry was changed by someone else — reload to see the latest" and writes nothing.
- **Audit log:** `Submission`, `InterestAnswer`, `NumberEntry`, `Entry` and `LongListDetail` log with `useLogName('submission-'.$submissionId)`. A submission's audit trail is `Activity::where('log_name', 'submission-'.$id)`. `Comment` is not logged.
- **Sensitive data:** `long_list_details.insurance_policy_no` uses the `encrypted` cast (text column), is excluded from the audit log, and in `/admin` is shown or filled into a form only when `Person::canViewMedical(auth()->user())`. Associations always see their own.
- **Tenant isolation:** `Person::withoutGlobalScopes()` is only ever used together with `where('association_id', …)` of the submission. Portal pages abort with 404 for a game the tenant is not invited to.
- **PDF:** dompdf, A4 landscape, table layouts only, logos and person photos embedded as data URIs (`GenerateEntryByEventPdfs::dataUri()`), `enable_remote` stays off. Stored on `config('filesystems.private')` at `submissions/{reference_no}/long-list.pdf`, offered through a 5-minute `temporaryUrl`. Queued job, `tries = 3`, `backoff = 60`.
- **Uploads in modals:** `AppServiceProvider` turns on `preventFilePathTampering()` for every `FileUpload`. A `FileUpload` with no record behind it must pass `allowFilePathUsing:` that accepts exactly the stored path, or the existing file is rejected.
- **Livewire route binding:** a resource page with a typed public property `Submission $submission` and a route parameter `{submission}` receives a `Submission` model in `mount()` over HTTP and a plain id in Livewire tests, so its `mount` takes `int|string|Submission $submission`.
- **Comments (ruling):** STA users with `EditSubmissions` add comments on the admin submission page; each comment emails the association's active users (queued `NewComment`). Associations only read comments (newest first) on their phase pages; they cannot reply.
- **Notifications:** sent by email through queued Laravel notifications to active users only (`Recipients::association`, `Recipients::sta`).
- **Permissions:** submission view and dashboard: `ViewSubmissions`; comments: `EditSubmissions` on the submission view (which itself needs `ViewSubmissions`); STA edit pages: `EditSubmissions` and `ViewSubmissions`; policy number in `/admin`: `ViewMedical`. Super Admin passes every check (`Gate::before`).
- **Commits:** end every commit message with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Never commit `.superpowers/` or `~$*`.

---

## File Structure

```
database/migrations/2026_11_11_000100_create_long_list_tables.php   # entries, long_list_details, comments
app/Models/{Entry, LongListDetail, Comment}.php                      # new
app/Models/{Submission, InterestAnswer, NumberEntry}.php             # relations + log name
app/Services/Phases/LongListChecks.php                               # Phase 3 errors, warnings, rows
app/Services/Phases/SubmissionWorkflow.php                           # phase 3 checks, Long List PDF dispatch
app/Filament/Portal/Concerns/HandlesSubmission.php                   # + gameModel, mountPortal, association(), editDenialReason(), submissionComments()
app/Filament/Shared/Phases/{EditsPhase1, EditsPhase2, EditsPhase3}.php  # per-phase state/load/save, shared by portal and STA pages
app/Filament/Portal/Pages/{Phase1Entry, Phase2Entry}.php             # become thin
app/Filament/Portal/Pages/Phase3Entry.php + resources/views/filament/portal/pages/phase3-entry.blade.php
app/Filament/Portal/Pages/Games.php                                  # link phase 3
resources/views/filament/partials/comments.blade.php                 # read-only comment list
app/Jobs/GenerateLongListPdf.php + resources/views/pdf/long-list.blade.php
app/Jobs/GenerateEntryByEventPdfs.php                                # dataUri() becomes public
app/Notifications/NewComment.php
app/Filament/Resources/Games/GameResource.php                        # new page routes
app/Filament/Resources/Games/Pages/SubmissionView.php + resources/views/filament/resources/games/pages/submission-view.blade.php
app/Filament/Resources/Games/Pages/GameDashboard.php (+ view)        # link cells, count entries, static pdfLinks
app/Filament/Resources/Games/Pages/Concerns/StaEditsSubmission.php
app/Filament/Resources/Games/Pages/{StaPhase1Edit, StaPhase2Edit, StaPhase3Edit}.php
tests/Feature/{LongListModelTest, LongListChecksTest, Phase3EntryTest, LongListPdfTest, SubmissionViewTest, SubmissionCommentsTest, StaSubmissionEditTest}.php
```

---

### Task 1: Long List schema, models and per-submission audit log names

**Files:**
- Create: `database/migrations/2026_11_11_000100_create_long_list_tables.php`, `app/Models/Entry.php`, `app/Models/LongListDetail.php`, `app/Models/Comment.php`
- Modify: `app/Models/Submission.php`, `app/Models/InterestAnswer.php`, `app/Models/NumberEntry.php`
- Test: `tests/Feature/LongListModelTest.php`

**Interfaces:**
- Consumes: `Submission::for()`, `Person` (`code`, `RenamesAttachments`), factories for `Association`, `Game`, `GameSport`, `GameEvent`, `Sport`, `Person`, `User`.
- Produces:
  - `Entry` (fillable `submission_id`, `person_id`, `game_event_id`, `game_sport_id`; relations `submission()`, `person()`, `gameEvent()`, `gameSport()`).
  - `LongListDetail` (fillable `submission_id`, `person_id`, `insurance_company`, `insurance_policy_no`, `insurance_valid_until`, `insurance_certificate_path`, `vaccinations`; `insurance_policy_no` encrypted, `insurance_valid_until` date; relations `submission()`, `person()`; certificate renamed to `{dir}/{person code}_insurance.{ext}` and deleted with the row).
  - `Comment` (fillable `submission_id`, `user_id`, `body`; relations `submission()`, `user()`).
  - `Submission::entries()`, `Submission::details()`, `Submission::comments()` (HasMany).
  - Activity log name `submission-{submission id}` on `Submission`, `InterestAnswer`, `NumberEntry`, `Entry`, `LongListDetail`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/LongListModelTest.php`:

```php
<?php

use App\Models\Association;
use App\Models\Comment;
use App\Models\Entry;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\InterestAnswer;
use App\Models\LongListDetail;
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create();
    $this->gs = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id])->id]);
    $this->ge = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->gs->id]);
    $this->sub = Submission::for($this->game, $this->assoc, 3);
    $this->athlete = Person::factory()->create(['association_id' => $this->assoc->id]);
});

it('links entries, details and comments to the submission', function () {
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $this->athlete->id, 'game_event_id' => $this->ge->id, 'game_sport_id' => $this->gs->id]);
    LongListDetail::create(['submission_id' => $this->sub->id, 'person_id' => $this->athlete->id, 'insurance_company' => 'AXA']);
    Comment::create(['submission_id' => $this->sub->id, 'user_id' => User::factory()->sta()->create()->id, 'body' => 'Check passports']);

    $s = $this->sub->fresh();
    expect($s->entries()->sole()->person->is($this->athlete))->toBeTrue()
        ->and($s->entries()->sole()->gameEvent->is($this->ge))->toBeTrue()
        ->and($s->details()->sole()->insurance_company)->toBe('AXA')
        ->and($s->comments()->sole()->body)->toBe('Check passports');
});

it('encrypts the insurance policy number and keeps it out of the audit log', function () {
    $d = LongListDetail::create(['submission_id' => $this->sub->id, 'person_id' => $this->athlete->id, 'insurance_policy_no' => 'POL-12345']);

    expect(DB::table('long_list_details')->where('id', $d->id)->value('insurance_policy_no'))->not->toContain('POL-12345')
        ->and($d->fresh()->insurance_policy_no)->toBe('POL-12345')
        ->and(json_encode(Activity::where('subject_type', LongListDetail::class)->get()->pluck('properties')))->not->toContain('POL-12345');
});

it('writes the audit trail of one submission under one log name', function () {
    $p2 = Submission::for($this->game, $this->assoc, 2);
    InterestAnswer::create(['submission_id' => $this->sub->id, 'game_sport_id' => $this->gs->id, 'answer' => 'yes']);
    NumberEntry::create(['submission_id' => $p2->id, 'game_event_id' => $this->ge->id, 'count' => 1]);
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $this->athlete->id, 'game_event_id' => $this->ge->id, 'game_sport_id' => $this->gs->id]);
    LongListDetail::create(['submission_id' => $this->sub->id, 'person_id' => $this->athlete->id, 'insurance_company' => 'AXA']);
    $this->sub->update(['justification' => 'x']);

    expect(Activity::where('log_name', 'submission-'.$this->sub->id)->pluck('subject_type')->unique()->sort()->values()->all())
        ->toBe([Entry::class, InterestAnswer::class, LongListDetail::class, Submission::class])
        ->and(Activity::where('log_name', 'submission-'.$p2->id)->pluck('subject_type')->unique()->sort()->values()->all())
        ->toBe([NumberEntry::class, Submission::class]);
});

it('renames the certificate to the person code and deletes it with the row', function () {
    Storage::fake(config('filesystems.private'));
    $disk = Storage::disk(config('filesystems.private'));
    $disk->put('submissions/'.$this->sub->id.'/upload.pdf', '%PDF');

    $d = LongListDetail::create(['submission_id' => $this->sub->id, 'person_id' => $this->athlete->id,
        'insurance_certificate_path' => 'submissions/'.$this->sub->id.'/upload.pdf']);
    $target = 'submissions/'.$this->sub->id.'/'.$this->athlete->code.'_insurance.pdf';

    expect($d->fresh()->insurance_certificate_path)->toBe($target);
    $disk->assertExists($target);
    $d->delete();
    $disk->assertMissing($target);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/LongListModelTest.php`
Expected: FAIL with `Class "App\Models\Entry" not found`.

- [ ] **Step 3: Write the migration**

`database/migrations/2026_11_11_000100_create_long_list_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $t->foreignId('game_event_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('game_sport_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            // Officials have game_event_id null, which a unique index does not compare; the Long List save keeps them unique.
            $t->unique(['submission_id', 'person_id', 'game_event_id', 'game_sport_id']);
        });
        Schema::create('long_list_details', function (Blueprint $t) {
            $t->id();
            $t->foreignId('submission_id')->constrained()->cascadeOnDelete();
            // ponytail: a DB cascade from a deleted person leaves the certificate file behind; delete it in Person::deleted if that matters.
            $t->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $t->string('insurance_company')->nullable();
            $t->text('insurance_policy_no')->nullable(); // encrypted cast
            $t->date('insurance_valid_until')->nullable();
            $t->string('insurance_certificate_path')->nullable();
            $t->text('vaccinations')->nullable();
            $t->timestamps();
            $t->unique(['submission_id', 'person_id']);
        });
        Schema::create('comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->text('body');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['comments', 'long_list_details', 'entries'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
```

- [ ] **Step 4: Write the three models**

`app/Models/Entry.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** A Long List row: an athlete in a game event, or a team official in a game sport (game_event_id null). */
class Entry extends Model
{
    use LogsActivity;

    protected $fillable = ['submission_id', 'person_id', 'game_event_id', 'game_sport_id'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function gameEvent(): BelongsTo
    {
        return $this->belongsTo(GameEvent::class);
    }

    public function gameSport(): BelongsTo
    {
        return $this->belongsTo(GameSport::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('submission-'.$this->submission_id)->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
```

`app/Models/LongListDetail.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\RenamesAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Per game, per person travel and insurance details of a Long List (spec 13 "Per game"). */
class LongListDetail extends Model
{
    use LogsActivity, RenamesAttachments;

    protected $fillable = ['submission_id', 'person_id', 'insurance_company', 'insurance_policy_no', 'insurance_valid_until',
        'insurance_certificate_path', 'vaccinations'];

    protected function casts(): array
    {
        return ['insurance_policy_no' => 'encrypted', 'insurance_valid_until' => 'date'];
    }

    protected static function booted(): void
    {
        static::deleted(function (LongListDetail $d) {
            if ($d->insurance_certificate_path) {
                Storage::disk(config('filesystems.private'))->delete($d->insurance_certificate_path);
            }
        });
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    protected function attachmentColumns(): array
    {
        return ['insurance_certificate_path' => 'insurance'];
    }

    protected function attachmentPrefix(): string
    {
        return $this->person->code;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('submission-'.$this->submission_id)
            ->logFillable()->logExcept(['insurance_policy_no'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
```

`app/Models/Comment.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An STA comment on a submission; associations read them, they cannot reply. */
class Comment extends Model
{
    protected $fillable = ['submission_id', 'user_id', 'body'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Add relations and log names to the existing models**

In `app/Models/Submission.php` add, after `numbers()`:

```php
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(LongListDetail::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
```

and replace `getActivitylogOptions()` with:

```php
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('submission-'.$this->id)->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
```

In `app/Models/InterestAnswer.php` and `app/Models/NumberEntry.php` replace `getActivitylogOptions()` with:

```php
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('submission-'.$this->submission_id)->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
```

- [ ] **Step 6: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/LongListModelTest.php`, then `$PHP vendor/bin/pest`.
Expected: all pass.

- [ ] **Step 7: Commit**

```bash
$PHP vendor/bin/pint
git add database/migrations/2026_11_11_000100_create_long_list_tables.php app/Models/Entry.php app/Models/LongListDetail.php app/Models/Comment.php app/Models/Submission.php app/Models/InterestAnswer.php app/Models/NumberEntry.php tests/Feature/LongListModelTest.php
git commit -m "feat: long list entries, travel details, comments and per-submission audit log

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Long List checks and Phase 3 submit rules

**Files:**
- Create: `app/Services/Phases/LongListChecks.php`
- Modify: `app/Services/Phases/SubmissionWorkflow.php` (`assertWithinLimits`)
- Test: `tests/Feature/LongListChecksTest.php`

**Interfaces:**
- Consumes: `Entry`, `Submission::entries()/details()`, `Game::phase2SportsFor()`, `Person::missingFields()/age()/fullName()`, `ClothingItem::applies_to->includes()`.
- Produces (`App\Services\Phases\LongListChecks`, resolve with `app(LongListChecks::class)`):
  - `static genderMatches(PersonGender $person, EventGender $event): bool`
  - `selection(Submission $s): array{0: array<int, list<string>>, 1: array<int, list<string>>}` — saved entries as `[game_event_id => person ids, game_sport_id => person ids]` (ids as strings, the shape the pickers use).
  - `errors(Submission $s, array $athletes, array $officials): array<string, string>` — hard errors keyed `athletes.{game_event_id}` / `officials.{game_sport_id}`; `[]` means the list may be saved.
  - `submitErrors(Submission $s): list<string>` — hard errors of the saved list plus one "X is incomplete: …" line per incomplete person.
  - `phase2Numbers(Submission $s): ?array<int, int>` — `game_event_id => count`, or `null` when the association's Phase 2 is not submitted.
  - `warnings(Game $game, Person $p, iterable $gameSports): list<string>`
  - `rows(Submission $s, array $athletes, array $officials): Collection<int, array{person: Person, roles: list<string>, age: int, missing: list<string>, warnings: list<string>, detail: ?LongListDetail, on_list: bool}>`
  - `SubmissionWorkflow::submit/approve` throw `ValidationException` (key `submission`) for Phase 3 when `submitErrors()` is not empty.

- [ ] **Step 1: Write the failing test**

`tests/Feature/LongListChecksTest.php`:

```php
<?php

use App\Enums\EventGender;
use App\Enums\PersonGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\InterestAnswer;
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\Phases\LongListChecks;
use App\Services\Phases\SubmissionWorkflow;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    Bus::fake();
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create(['start_date' => '2027-03-01', 'end_date' => '2027-03-10']);
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id, 'dob_from' => '2004-01-01', 'dob_to' => '2009-12-31',
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $mk = fn (string $name, EventGender $g) => GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => $name, 'gender' => $g])->id]);
    $this->m60 = $mk('Men -60kg', EventGender::Men);
    $this->w48 = $mk('Women -48kg', EventGender::Women);
    $this->team = $mk('Mixed team', EventGender::Mixed);
    $this->sub = Submission::for($this->game, $this->assoc, 3);
    $this->man = Person::factory()->complete()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan',
        'cpr_front_path' => 'scans/c.jpg']);
    $this->checks = app(LongListChecks::class);
});

it('accepts a list that follows the rules', function () {
    expect($this->checks->errors($this->sub, [$this->m60->id => [(string) $this->man->id], $this->team->id => [$this->man->id]], []))->toBe([]);
});

it('rejects an athlete whose gender does not match the event', function () {
    expect($this->checks->errors($this->sub, [$this->w48->id => [$this->man->id]], []))
        ->toBe(["athletes.{$this->w48->id}" => 'Women -48kg: Ali Hasan (Male) cannot enter a Women event.']);
});

it('rejects people of another association, inactive people and the wrong type', function () {
    $other = Person::factory()->create();
    $inactive = Person::factory()->create(['association_id' => $this->assoc->id, 'is_active' => false, 'first_name' => 'Old', 'last_name' => 'Timer']);
    $official = Person::factory()->official()->create(['association_id' => $this->assoc->id, 'first_name' => 'Coach', 'last_name' => 'One']);
    $key = "athletes.{$this->m60->id}";

    expect($this->checks->errors($this->sub, [$this->m60->id => [$other->id]], []))->toBe([$key => "Men -60kg: Choose people from your association's registry."])
        ->and($this->checks->errors($this->sub, [$this->m60->id => [$inactive->id]], []))->toBe([$key => 'Men -60kg: Old Timer is inactive.'])
        ->and($this->checks->errors($this->sub, [$this->m60->id => [$official->id]], []))->toBe([$key => 'Men -60kg: Coach One is a team official, not an athlete.'])
        ->and($this->checks->errors($this->sub, [], [$this->judo->id => [$this->man->id]]))
        ->toBe(["officials.{$this->judo->id}" => 'Judo: Ali Hasan is an athlete, not a team official.']);
});

it('rejects events and sports hidden by a phase 1 "no"', function () {
    $this->game->phase(1)->update(['enabled' => true]);
    InterestAnswer::create(['submission_id' => Submission::for($this->game, $this->assoc, 1)->id, 'game_sport_id' => $this->judo->id, 'answer' => 'no']);

    expect($this->checks->errors($this->sub->fresh(), [$this->m60->id => [$this->man->id]], [$this->judo->id => [$this->man->id]]))->toBe([
        "athletes.{$this->m60->id}" => 'This event is not open to your association in this game.',
        "officials.{$this->judo->id}" => 'This sport is not open to your association in this game.',
    ]);
});

it('warns about expiring documents, missing scans, age range and missing sizes', function () {
    $shirt = ClothingItem::factory()->create(['name' => 'T-Shirt']);
    $this->game->clothingItems()->attach($shirt);
    $p = Person::factory()->create(['association_id' => $this->assoc->id, 'dob' => '2001-05-05',
        'passport_expiry' => '2027-03-05', 'cpr_expiry' => '2027-03-09']);

    expect($this->checks->warnings($this->game->fresh(), $p, [$this->judo]))->toBe([
        'Passport expires before the games end (5 Mar 2027).',
        'CPR expires before the games end (9 Mar 2027).',
        'Passport scan missing.',
        'CPR scan missing.',
        'Date of birth is outside the Judo age range.',
        'T-Shirt size missing.',
    ]);

    $this->man->syncSizes([$shirt->id => 'M']);
    expect($this->checks->warnings($this->game->fresh(), $this->man->fresh(), [$this->judo]))->toBe([]);
});

it('compares with phase 2 numbers only once phase 2 is submitted', function () {
    $p2 = Submission::for($this->game, $this->assoc, 2);
    NumberEntry::create(['submission_id' => $p2->id, 'game_event_id' => $this->m60->id, 'count' => 1]);
    expect($this->checks->phase2Numbers($this->sub))->toBeNull();

    $p2->update(['status' => SubmissionStatus::Submitted]);
    expect($this->checks->phase2Numbers($this->sub))->toBe([$this->m60->id => 1]);
});

it('builds the table rows with roles, age, completeness and warnings', function () {
    $row = $this->checks->rows($this->sub, [$this->m60->id => [(string) $this->man->id]], [])->sole();

    expect($row['person']->is($this->man))->toBeTrue()
        ->and($row['roles'])->toBe(['Judo · Men -60kg'])
        ->and($row['age'])->toBe(21)
        ->and($row['missing'])->toBe([])
        ->and($row['on_list'])->toBeFalse();
});

it('blocks submitting a long list with an incomplete person and names the missing fields', function () {
    $sara = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Sara', 'last_name' => 'Ali', 'gender' => PersonGender::Female]);
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $sara->id, 'game_event_id' => $this->w48->id, 'game_sport_id' => $this->judo->id]);

    expect(fn () => app(SubmissionWorkflow::class)->submit($this->sub, User::factory()->assocAdmin($this->assoc)->create()))
        ->toThrow(ValidationException::class, 'Sara Ali is incomplete: Full name, Photo, Passport issuing country, Passport issue date, Passport scan.');
    expect($this->sub->fresh()->status)->toBe(SubmissionStatus::Draft);
});

it('re-checks hard errors of the saved list on submit', function () {
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $this->man->id, 'game_event_id' => $this->w48->id, 'game_sport_id' => $this->judo->id]);

    expect(fn () => app(SubmissionWorkflow::class)->submit($this->sub, User::factory()->assocAdmin($this->assoc)->create()))
        ->toThrow(ValidationException::class, 'cannot enter a Women event');
});

it('submits a long list of complete people', function () {
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $this->man->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);

    expect(app(SubmissionWorkflow::class)->submit($this->sub, User::factory()->assocAdmin($this->assoc)->create())->status)
        ->toBe(SubmissionStatus::Submitted);
});
```

(Age: `dob` 2005-03-10 from the factory, game start 2027-03-01 → 21.)

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/LongListChecksTest.php`
Expected: FAIL with `Class "App\Services\Phases\LongListChecks" not found`.

- [ ] **Step 3: Write `LongListChecks`**

`app/Services/Phases/LongListChecks.php`:

```php
<?php

namespace App\Services\Phases;

use App\Enums\EventGender;
use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Enums\SubmissionStatus;
use App\Models\Game;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Submission;
use Illuminate\Support\Collection;

/** Phase 3 (Long List) rules: hard errors block save and submit, warnings never block. */
final class LongListChecks
{
    public static function genderMatches(PersonGender $person, EventGender $event): bool
    {
        return match ($event) {
            EventGender::Mixed => true,
            EventGender::Men => $person === PersonGender::Male,
            EventGender::Women => $person === PersonGender::Female,
        };
    }

    /** @return array{0: array<int, list<string>>, 1: array<int, list<string>>} [game_event_id => person ids, game_sport_id => person ids] */
    public function selection(Submission $s): array
    {
        $athletes = [];
        $officials = [];
        foreach ($s->entries()->orderBy('id')->get() as $e) {
            if ($e->game_event_id) {
                $athletes[$e->game_event_id][] = (string) $e->person_id;
            } else {
                $officials[$e->game_sport_id][] = (string) $e->person_id;
            }
        }

        return [$athletes, $officials];
    }

    /**
     * @param  array<int|string, mixed>  $athletes  game_event_id => person ids
     * @param  array<int|string, mixed>  $officials  game_sport_id => person ids
     * @return array<string, string> keyed athletes.{game_event_id} / officials.{game_sport_id}
     */
    public function errors(Submission $s, array $athletes, array $officials): array
    {
        $sports = $s->game->phase2SportsFor($s->association)->keyBy('id');
        $events = $sports->flatMap->gameEvents->keyBy('id');
        $ids = collect([...array_values($athletes), ...array_values($officials)])->flatten()->map(fn ($id) => (int) $id)->unique()->all();
        $people = Person::withoutGlobalScopes()->whereKey($ids)->get()->keyBy('id');
        $errors = [];

        foreach ($athletes as $geId => $personIds) {
            $ge = $events->get((int) $geId);
            foreach ((array) $personIds as $id) {
                if ($ge === null) {
                    $errors["athletes.$geId"] = 'This event is not open to your association in this game.';
                    break;
                }
                $p = $people->get((int) $id);
                $msg = $this->personError($s, $p, PersonType::Athlete)
                    ?? (self::genderMatches($p->gender, $ge->event->gender) ? null
                        : "{$p->fullName()} ({$p->gender->label()}) cannot enter a {$ge->event->gender->label()} event.");
                if ($msg) {
                    $errors["athletes.$geId"] = "{$ge->event->name}: $msg";
                    break;
                }
            }
        }

        foreach ($officials as $gsId => $personIds) {
            $gs = $sports->get((int) $gsId);
            foreach ((array) $personIds as $id) {
                if ($gs === null) {
                    $errors["officials.$gsId"] = 'This sport is not open to your association in this game.';
                    break;
                }
                if ($msg = $this->personError($s, $people->get((int) $id), PersonType::Official)) {
                    $errors["officials.$gsId"] = "{$gs->sport->name}: $msg";
                    break;
                }
            }
        }

        return $errors;
    }

    private function personError(Submission $s, ?Person $p, PersonType $type): ?string
    {
        return match (true) {
            $p === null || (int) $p->association_id !== (int) $s->association_id => "Choose people from your association's registry.",
            ! $p->is_active => "{$p->fullName()} is inactive.",
            $p->type !== $type => $type === PersonType::Athlete
                ? "{$p->fullName()} is a team official, not an athlete."
                : "{$p->fullName()} is an athlete, not a team official.",
            default => null,
        };
    }

    /** Hard errors of the saved list plus incomplete people (spec 13): blocks submit and approve. @return list<string> */
    public function submitErrors(Submission $s): array
    {
        [$athletes, $officials] = $this->selection($s);
        $errors = array_values($this->errors($s, $athletes, $officials));
        $people = Person::withoutGlobalScopes()->where('association_id', $s->association_id)
            ->whereIn('id', $s->entries()->pluck('person_id'))->orderBy('last_name')->orderBy('first_name')->get();
        foreach ($people as $p) {
            if ($missing = $p->missingFields()) {
                $errors[] = "{$p->fullName()} is incomplete: ".implode(', ', $missing).'.';
            }
        }

        return $errors;
    }

    /** @return array<int, int>|null game_event_id => Phase 2 count; null unless the association's Phase 2 is submitted */
    public function phase2Numbers(Submission $s): ?array
    {
        $p2 = Submission::withoutGlobalScope('association')
            ->where(['game_id' => $s->game_id, 'association_id' => $s->association_id, 'phase' => 2])->first();

        return $p2?->status === SubmissionStatus::Submitted ? $p2->numbers()->pluck('count', 'game_event_id')->all() : null;
    }

    /**
     * @param  iterable<GameSport>  $gameSports  the sports the person is entered in (for the athlete age range)
     * @return list<string>
     */
    public function warnings(Game $game, Person $p, iterable $gameSports): array
    {
        $w = [];
        if ($p->passport_expiry && $p->passport_expiry->lt($game->end_date)) {
            $w[] = 'Passport expires before the games end ('.$p->passport_expiry->format('j M Y').').';
        }
        if ($p->cpr_expiry && $p->cpr_expiry->lt($game->end_date)) {
            $w[] = 'CPR expires before the games end ('.$p->cpr_expiry->format('j M Y').').';
        }
        if (blank($p->passport_scan_path)) {
            $w[] = 'Passport scan missing.';
        }
        if (blank($p->cpr_front_path)) {
            $w[] = 'CPR scan missing.';
        }
        if ($p->type === PersonType::Athlete) {
            foreach ($gameSports as $gs) {
                if (($gs->dob_from && $p->dob->lt($gs->dob_from)) || ($gs->dob_to && $p->dob->gt($gs->dob_to))) {
                    $w[] = "Date of birth is outside the {$gs->sport->name} age range.";
                }
            }
        }
        $sizes = $p->sizes->pluck('size', 'clothing_item_id');
        foreach ($game->clothingItems as $item) {
            if ($item->applies_to->includes($p->type) && blank($sizes->get($item->id))) {
                $w[] = "{$item->name} size missing.";
            }
        }

        return $w;
    }

    /**
     * The people table under the pickers (and on the admin submission page), for a proposed or saved selection.
     *
     * @return Collection<int, array{person: Person, roles: list<string>, age: int, missing: list<string>, warnings: list<string>, detail: ?\App\Models\LongListDetail, on_list: bool}>
     */
    public function rows(Submission $s, array $athletes, array $officials): Collection
    {
        $game = $s->game;
        $sports = $game->phase2SportsFor($s->association)->keyBy('id');
        $events = $sports->flatMap->gameEvents->keyBy('id');
        $roles = [];
        $sportsOf = [];
        foreach ($athletes as $geId => $ids) {
            $ge = $events->get((int) $geId);
            if (! $ge) {
                continue;
            }
            $gs = $sports->get($ge->game_sport_id);
            foreach ((array) $ids as $id) {
                $roles[(int) $id][] = $gs->sport->name.' · '.$ge->event->name;
                $sportsOf[(int) $id][$gs->id] = $gs;
            }
        }
        foreach ($officials as $gsId => $ids) {
            $gs = $sports->get((int) $gsId);
            if (! $gs) {
                continue;
            }
            foreach ((array) $ids as $id) {
                $roles[(int) $id][] = $gs->sport->name.' · team official';
            }
        }
        $saved = $s->entries()->pluck('person_id')->map(fn ($id) => (int) $id)->all();
        $details = $s->details()->get()->keyBy('person_id');

        return Person::withoutGlobalScopes()->with('sizes')->where('association_id', $s->association_id)->whereKey(array_keys($roles))
            ->orderBy('type')->orderBy('last_name')->orderBy('first_name')->get()
            ->map(fn (Person $p) => [
                'person' => $p,
                'roles' => $roles[$p->id],
                'age' => $p->age($game->start_date),
                'missing' => $p->missingFields(),
                'warnings' => $this->warnings($game, $p, $sportsOf[$p->id] ?? []),
                'detail' => $details->get($p->id),
                'on_list' => in_array($p->id, $saved, true),
            ]);
    }
}
```

- [ ] **Step 4: Run Phase 3 checks in the workflow**

In `app/Services/Phases/SubmissionWorkflow.php` replace `assertWithinLimits()` with:

```php
    private function assertWithinLimits(Submission $s): void
    {
        if ($s->phase === 3) {
            if ($errors = app(LongListChecks::class)->submitErrors($s)) {
                $this->fail(implode(' ', $errors));
            }

            return;
        }
        if ($s->phase !== 2) {
            return;
        }
        $counts = $s->numbers()->pluck('count', 'game_event_id')->all();
        foreach ($s->game->phase2SportsFor($s->association) as $gs) {
            if ($errors = app(Phase2Limits::class)->errors($gs, $counts)) {
                $this->fail(reset($errors));
            }
        }
    }
```

(`LongListChecks` is in the same namespace, so no import is needed. `submit()` and `approve()` already call `assertWithinLimits()`.)

- [ ] **Step 5: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/LongListChecksTest.php`, then `$PHP vendor/bin/pest`.
Expected: all pass.

- [ ] **Step 6: Commit**

```bash
$PHP vendor/bin/pint
git add app/Services/Phases/LongListChecks.php app/Services/Phases/SubmissionWorkflow.php tests/Feature/LongListChecksTest.php
git commit -m "feat: long list hard errors, warnings and submit completeness check

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Extract the phase editors into traits (pure refactor)

**Files:**
- Create: `app/Filament/Shared/Phases/EditsPhase1.php`, `app/Filament/Shared/Phases/EditsPhase2.php`
- Modify: `app/Filament/Portal/Concerns/HandlesSubmission.php`, `app/Filament/Portal/Pages/Phase1Entry.php`, `app/Filament/Portal/Pages/Phase2Entry.php`
- Test: existing `tests/Feature/Phase1EntryTest.php`, `tests/Feature/Phase2EntryTest.php` (no new test; behaviour must not change)

**Interfaces:**
- Produces:
  - `HandlesSubmission` now also holds `public Game $gameModel`, declares `abstract protected function loadPhase(): void`, and provides:
    - `protected mountPortal(int|string $game, int $phase): void` (tenant + invitation + phase-enabled 404 checks, `Submission::for`, `loadPhase()`, `refreshLoadedAt()`);
    - `protected association(): Association` (the tenant; STA pages override it);
    - `protected editDenialReason(Submission $s): ?string` (default `PhaseGate::denialReason`; STA pages override it). `refreshLoadedAt()` and `guardEditable()` use it.
  - `EditsPhase1` (`$answers`, `loadPhase()`, `getSportsProperty()`, `save()`), `EditsPhase2` (`$counts`, `$liveErrors`, `loadPhase()`, `visibleSports()`, `limitErrors()`, `updatedCounts()`, `save()`). Each declares `abstract protected function association(): Association;` and relies on `HandlesSubmission` for `$gameModel`, `$submission`, `guardEditable()`, `claimOrHalt()`, `refreshLoadedAt()`.

- [ ] **Step 1: Confirm the baseline is green**

Run: `$PHP vendor/bin/pest tests/Feature/Phase1EntryTest.php tests/Feature/Phase2EntryTest.php`
Expected: PASS.

- [ ] **Step 2: Rewrite `HandlesSubmission`**

`app/Filament/Portal/Concerns/HandlesSubmission.php`:

```php
<?php

namespace App\Filament\Portal\Concerns;

use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\Game;
use App\Models\Submission;
use App\Services\Phases\PhaseGate;
use App\Services\Phases\StaleSubmission;
use App\Services\Phases\SubmissionWorkflow;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * Submission plumbing shared by the portal phase pages and the STA edit pages.
 * The phase state and save() come from App\Filament\Shared\Phases\EditsPhaseN.
 */
trait HandlesSubmission
{
    public Game $gameModel;

    public Submission $submission;

    #[Locked]
    public int $loadedVersion = 0;

    #[Locked]
    public ?string $readOnlyReason = null;

    /** Fills the phase state from $this->submission. */
    abstract protected function loadPhase(): void;

    /** Called before submit so unsaved changes go in. */
    abstract public function save(): void;

    protected function mountPortal(int|string $game, int $phase): void
    {
        $this->gameModel = Game::findOrFail($game);
        $tenant = Filament::getTenant();
        abort_unless($this->gameModel->isInvited($tenant) && $this->gameModel->phase($phase)->enabled, 404);
        $this->submission = Submission::for($this->gameModel, $tenant, $phase);
        $this->loadPhase();
        $this->refreshLoadedAt();
    }

    protected function association(): Association
    {
        return Filament::getTenant();
    }

    /** Why the current user may not edit; associations follow the phase gate. STA pages override this. */
    protected function editDenialReason(Submission $s): ?string
    {
        return app(PhaseGate::class)->denialReason($s);
    }

    protected function refreshLoadedAt(): void
    {
        $this->submission->refresh();
        $this->loadedVersion = (int) $this->submission->lock_version;
        $this->readOnlyReason = $this->editDenialReason($this->submission);
    }

    /** Status/phase checks; call before validating input. */
    protected function guardEditable(): void
    {
        if ($reason = $this->editDenialReason($this->submission->fresh())) {
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

    protected function submitAction(): Action
    {
        return Action::make('submit')->label(fn () => auth()->user()->role === Role::AssocAdmin ? 'Submit to STA' : 'Send for approval')
            ->requiresConfirmation()->visible(fn () => $this->readOnlyReason === null)
            ->action(fn () => $this->runWorkflow(function () {
                $before = $this->loadedVersion;
                $this->save();
                if ($this->loadedVersion === $before) {
                    throw new Halt; // save() halted (stale or closed); do not submit
                }

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

- [ ] **Step 3: Create `EditsPhase1`**

`app/Filament/Shared/Phases/EditsPhase1.php`:

```php
<?php

namespace App\Filament\Shared\Phases;

use App\Enums\InterestAnswerValue;
use App\Models\Association;
use App\Models\InterestAnswer;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\DB;

/** Phase 1 (Entry by Sport) state and save; use together with HandlesSubmission. */
trait EditsPhase1
{
    /** @var array<int|string, string> game_sport_id => yes|no|maybe */
    public array $answers = [];

    abstract protected function association(): Association;

    protected function loadPhase(): void
    {
        $this->answers = $this->submission->answers()->pluck('answer', 'game_sport_id')->map(fn ($a) => $a->value)->all();
    }

    public function getSportsProperty()
    {
        return $this->gameModel->sportsFor($this->association());
    }

    public function save(): void
    {
        try {
            $this->guardEditable();
            $valid = $this->gameModel->sportsFor($this->association())->pluck('id')->all();
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
        } catch (Halt) {
            return; // notification already sent; loadedVersion unchanged so submit knows nothing was saved
        }
        $this->refreshLoadedAt();
        Notification::make()->success()->title('Saved')->send();
    }
}
```

- [ ] **Step 4: Create `EditsPhase2`**

`app/Filament/Shared/Phases/EditsPhase2.php`:

```php
<?php

namespace App\Filament\Shared\Phases;

use App\Models\Association;
use App\Models\GameSport;
use App\Models\NumberEntry;
use App\Services\Phases\Phase2Limits;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Phase 2 (Entry by Number) state, live limits and save; use together with HandlesSubmission. */
trait EditsPhase2
{
    /** @var array<int|string, int|string|null> game_event_id => count */
    public array $counts = [];

    /** @var array<string, string> */
    public array $liveErrors = [];

    abstract protected function association(): Association;

    protected function loadPhase(): void
    {
        $this->counts = $this->submission->numbers()->pluck('count', 'game_event_id')->all();
    }

    /** @return Collection<int, GameSport> */
    public function visibleSports(): Collection
    {
        return $this->gameModel->phase2SportsFor($this->association());
    }

    /** @return array<string, string> keyed counts.{gameEventId} or sport.{gameSportId}.{men|women|total} */
    protected function limitErrors(): array
    {
        $errors = [];
        foreach ($this->visibleSports() as $gs) {
            $valid = $this->counts;
            foreach ($gs->gameEvents as $ge) {
                $raw = $this->counts[$ge->id] ?? null;
                if ($raw !== null && $raw !== '' && ! preg_match('/^\d{1,3}$/', trim((string) $raw))) {
                    $errors['counts.'.$ge->id] = "{$ge->event->name}: enter a whole number from 0 to 999.";
                    unset($valid[$ge->id]);
                }
            }
            foreach (app(Phase2Limits::class)->errors($gs, $valid) as $key => $message) {
                $errors[str_starts_with($key, 'event.') ? 'counts.'.substr($key, 6) : "sport.{$gs->id}.".substr($key, 6)] ??= $message;
            }
        }

        return $errors;
    }

    public function updatedCounts(): void
    {
        $this->liveErrors = $this->limitErrors();
    }

    public function save(): void
    {
        try {
            $this->guardEditable();
            if ($errors = $this->limitErrors()) {
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
        } catch (Halt) {
            return; // notification already sent; loadedVersion unchanged so submit knows nothing was saved
        }
        $this->liveErrors = [];
        $this->refreshLoadedAt();
        Notification::make()->success()->title('Saved')->send();
    }
}
```

- [ ] **Step 5: Make the portal pages thin**

`app/Filament/Portal/Pages/Phase1Entry.php`:

```php
<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Portal\Concerns\HandlesSubmission;
use App\Filament\Shared\Phases\EditsPhase1;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class Phase1Entry extends Page
{
    use EditsPhase1, HandlesSubmission;

    protected string $view = 'filament.portal.pages.phase1-entry';

    public static function getRoutePath(Panel $panel): string
    {
        return '/games/{game}/phase-1';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->gameModel->name.' – Phase 1 · Entry by Sport';
    }

    public function mount(int|string $game): void
    {
        $this->mountPortal($game, 1);
    }

    protected function getHeaderActions(): array
    {
        return [$this->submitAction(), $this->approveAction(), $this->returnAction()];
    }
}
```

`app/Filament/Portal/Pages/Phase2Entry.php`:

```php
<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Portal\Concerns\HandlesSubmission;
use App\Filament\Shared\Phases\EditsPhase2;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class Phase2Entry extends Page
{
    use EditsPhase2, HandlesSubmission;

    protected string $view = 'filament.portal.pages.phase2-entry';

    public static function getRoutePath(Panel $panel): string
    {
        return '/games/{game}/phase-2';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->gameModel->name.' – Phase 2 · Entry by Number';
    }

    public function mount(int|string $game): void
    {
        $this->mountPortal($game, 2);
    }

    protected function getHeaderActions(): array
    {
        return [$this->submitAction(), $this->approveAction(), $this->returnAction()];
    }
}
```

The Blade views do not change (`$this->sports`, `$this->visibleSports()`, `$answers`, `$counts`, `$liveErrors`, `$readOnlyReason` keep their names).

- [ ] **Step 6: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/Phase1EntryTest.php tests/Feature/Phase2EntryTest.php`, then `$PHP vendor/bin/pest`.
Expected: all pass, with no test edits.

- [ ] **Step 7: Commit**

```bash
$PHP vendor/bin/pint
git add app/Filament/Shared/Phases app/Filament/Portal/Concerns/HandlesSubmission.php app/Filament/Portal/Pages/Phase1Entry.php app/Filament/Portal/Pages/Phase2Entry.php
git commit -m "refactor: move phase 1 and 2 editors into shared traits

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Portal Phase 3 (Long List) with travel and insurance details

**Files:**
- Create: `app/Filament/Shared/Phases/EditsPhase3.php`, `app/Filament/Portal/Pages/Phase3Entry.php`, `resources/views/filament/portal/pages/phase3-entry.blade.php`
- Modify: `app/Filament/Portal/Pages/Games.php` (link phase 3)
- Test: `tests/Feature/Phase3EntryTest.php`

**Interfaces:**
- Consumes: `HandlesSubmission` (Task 3), `LongListChecks` (Task 2), `Entry`, `LongListDetail` (Task 1).
- Produces:
  - `EditsPhase3` trait: public `$athletes` (`game_event_id => person ids`), `$officials` (`game_sport_id => person ids`); `loadPhase()`, `visibleSports(): Collection<GameSport>`, `candidates(PersonType): Collection<Person>`, `phase2Numbers(): ?array`, `listRows(): Collection`, `save()`, `travelAction(): Action` (name `travel`, argument `person`).
  - `Phase3Entry::getUrl(['game' => $id])` → `/portal/{tenant}/games/{game}/phase-3`.
  - View `filament.portal.pages.phase3-entry` (reused by the STA page in Task 8).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Phase3EntryTest.php`:

```php
<?php

use App\Enums\EventGender;
use App\Enums\PersonGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Games;
use App\Filament\Portal\Pages\Phase3Entry;
use App\Models\Association;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\LongListDetail;
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    Bus::fake();
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->ali = Person::factory()->complete()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->omar = Person::factory()->complete()->create(['association_id' => $this->assoc->id, 'first_name' => 'Omar', 'last_name' => 'Saleh']);
    $this->sara = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Sara', 'last_name' => 'Ali', 'gender' => PersonGender::Female]);
    $this->coach = Person::factory()->official()->complete()->create(['association_id' => $this->assoc->id, 'first_name' => 'Coach', 'last_name' => 'Karim']);
    $this->admin = User::factory()->assocAdmin($this->assoc)->create();
    actingInPortal($this->admin);
});

it('offers athletes of the event gender and the officials of the association', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->assertSee('Men -60kg')->assertSee('Ali Hasan')->assertSee('Coach Karim')->assertDontSee('Sara Ali');
});

it('saves the list and syncs removals, dropping travel details of removed people', function () {
    $page = livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id, (string) $this->omar->id])
        ->set("officials.{$this->judo->id}", [(string) $this->coach->id])
        ->call('save')->assertHasNoErrors();

    expect(Entry::count())->toBe(3)
        ->and(Entry::where('person_id', $this->coach->id)->sole()->game_event_id)->toBeNull();

    LongListDetail::create(['submission_id' => Submission::sole()->id, 'person_id' => $this->omar->id, 'insurance_company' => 'AXA']);
    $page->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save')->assertHasNoErrors();

    expect(Entry::count())->toBe(2)
        ->and(Entry::where('person_id', $this->omar->id)->exists())->toBeFalse()
        ->and(LongListDetail::count())->toBe(0);
});

it('blocks a gender mismatch and writes nothing', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->sara->id])->call('save')
        ->assertHasErrors(["athletes.{$this->m60->id}"])->assertSee('Sara Ali (Female) cannot enter a Men event.');
    expect(Entry::count())->toBe(0);
});

it('flags an event over its submitted phase 2 number without blocking', function () {
    $p2 = Submission::for($this->game, $this->assoc, 2);
    NumberEntry::create(['submission_id' => $p2->id, 'game_event_id' => $this->m60->id, 'count' => 1]);
    $p2->update(['status' => SubmissionStatus::Submitted]);

    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id, (string) $this->omar->id])
        ->assertSee('Long List 2 / Phase 2: 1')->assertSee('Over the Phase 2 number')
        ->call('save')->assertHasNoErrors();
    expect(Entry::count())->toBe(2);
});

it('shows completeness and warnings per person', function () {
    $yusuf = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Yusuf', 'last_name' => 'Adel']);

    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $yusuf->id])
        ->assertSee('Incomplete')->assertSee('Missing: Full name')->assertSee('Passport scan missing.');
});

it('saves travel and insurance details with a certificate named after the person', function () {
    Storage::fake(config('filesystems.private'));
    $page = livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save');

    $page->callAction('travel', [
        'insurance_company' => 'AXA Gulf', 'insurance_policy_no' => 'POL-1', 'insurance_valid_until' => '2027-04-01',
        'insurance_certificate_path' => UploadedFile::fake()->image('cert.jpg'), 'vaccinations' => 'Yellow fever',
    ], ['person' => $this->ali->id])->assertHasNoActionErrors();

    $d = LongListDetail::sole();
    $path = 'submissions/'.Submission::sole()->id.'/'.$this->ali->code.'_insurance.jpg';
    expect($d->insurance_company)->toBe('AXA Gulf')->and($d->insurance_policy_no)->toBe('POL-1')
        ->and($d->insurance_valid_until->toDateString())->toBe('2027-04-01')->and($d->insurance_certificate_path)->toBe($path);
    Storage::disk(config('filesystems.private'))->assertExists($path);
});

it('refuses travel details for a person not on the saved list', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->callAction('travel', ['insurance_company' => 'AXA'], ['person' => $this->ali->id])
        ->assertNotified('Save the Long List with this person first.');
    expect(LongListDetail::count())->toBe(0);
});

it('refuses a stale save', function () {
    $page = livewire(Phase3Entry::class, ['game' => $this->game->id]);
    $s = Submission::sole();
    $s->claimVersion($s->lock_version);
    $page->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save')
        ->assertNotified('This entry was changed by someone else — reload to see the latest.');
    expect(Entry::count())->toBe(0);
});

it('is read-only when the phase is closed', function () {
    $this->game->phase(3)->update(['status' => PhaseStatus::Closed]);
    livewire(Phase3Entry::class, ['game' => $this->game->id])->assertSee('This phase is closed.')
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save');
    expect(Entry::count())->toBe(0);
});

it('saves an incomplete person but blocks submit', function () {
    $yusuf = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Yusuf', 'last_name' => 'Adel']);
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $yusuf->id])->callAction('submit')->assertNotified();

    expect(Entry::count())->toBe(1)->and(Submission::sole()->status)->toBe(SubmissionStatus::Draft);
});

it('returns 404 for a game the association is not invited to', function () {
    $other = Game::factory()->create();
    $this->get(Phase3Entry::getUrl(['game' => $other->id], tenant: $this->assoc))->assertNotFound();
});

it('links phase 3 from the games page', function () {
    livewire(Games::class)->assertSeeHtml(Phase3Entry::getUrl(['game' => $this->game->id]));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/Phase3EntryTest.php`
Expected: FAIL with `Class "App\Filament\Portal\Pages\Phase3Entry" not found`.

- [ ] **Step 3: Create `EditsPhase3`**

`app/Filament/Shared/Phases/EditsPhase3.php`:

```php
<?php

namespace App\Filament\Shared\Phases;

use App\Enums\PersonType;
use App\Models\Association;
use App\Models\GameSport;
use App\Models\LongListDetail;
use App\Models\Person;
use App\Services\Phases\LongListChecks;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Phase 3 (Long List) state, checks, save and travel details; use together with HandlesSubmission. */
trait EditsPhase3
{
    /** @var array<int|string, mixed> game_event_id => person ids */
    public array $athletes = [];

    /** @var array<int|string, mixed> game_sport_id => person ids */
    public array $officials = [];

    abstract protected function association(): Association;

    protected function loadPhase(): void
    {
        [$this->athletes, $this->officials] = app(LongListChecks::class)->selection($this->submission);
    }

    /** @return Collection<int, GameSport> */
    public function visibleSports(): Collection
    {
        return $this->gameModel->phase2SportsFor($this->association());
    }

    /** @return Collection<int, Person> active people of this association and type, for the pickers */
    public function candidates(PersonType $type): Collection
    {
        return Person::withoutGlobalScopes()->where('association_id', $this->association()->getKey())
            ->where('is_active', true)->where('type', $type)->orderBy('last_name')->orderBy('first_name')->get();
    }

    /** @return array<int, int>|null */
    public function phase2Numbers(): ?array
    {
        return app(LongListChecks::class)->phase2Numbers($this->submission);
    }

    public function listRows(): Collection
    {
        return app(LongListChecks::class)->rows($this->submission, $this->athletes, $this->officials);
    }

    public function save(): void
    {
        try {
            $this->guardEditable();
            if ($errors = app(LongListChecks::class)->errors($this->submission, $this->athletes, $this->officials)) {
                throw ValidationException::withMessages($errors);
            }
            DB::transaction(function () {
                $this->claimOrHalt();
                $this->syncEntries();
            });
        } catch (Halt) {
            return; // notification already sent; loadedVersion unchanged so submit knows nothing was saved
        }
        $this->refreshLoadedAt();
        Notification::make()->success()->title('Saved')->send();
    }

    /** Makes the saved entries equal to the visible selection; entries and travel details outside it are deleted. */
    private function syncEntries(): void
    {
        $sports = $this->visibleSports()->keyBy('id');
        $events = $sports->flatMap->gameEvents->keyBy('id');
        $want = collect();
        foreach ($this->athletes as $geId => $ids) {
            if ($ge = $events->get((int) $geId)) {
                foreach ((array) $ids as $id) {
                    $want->push(['person_id' => (int) $id, 'game_event_id' => $ge->id, 'game_sport_id' => $ge->game_sport_id]);
                }
            }
        }
        foreach ($this->officials as $gsId => $ids) {
            if ($sports->has((int) $gsId)) {
                foreach ((array) $ids as $id) {
                    $want->push(['person_id' => (int) $id, 'game_event_id' => null, 'game_sport_id' => (int) $gsId]);
                }
            }
        }
        $key = fn (array $e) => $e['person_id'].'-'.($e['game_event_id'] ?? 'official').'-'.$e['game_sport_id'];
        $want = $want->keyBy($key);
        $have = $this->submission->entries()->get()->keyBy(fn ($e) => $key($e->only(['person_id', 'game_event_id', 'game_sport_id'])));

        $have->diffKeys($want)->each->delete();
        $want->diffKeys($have)->each(fn (array $row) => $this->submission->entries()->create($row));
        $this->submission->details()->whereNotIn('person_id', $want->pluck('person_id')->unique()->all())->get()->each->delete();
    }

    public function travelAction(): Action
    {
        return Action::make('travel')->label('Travel & insurance')->modalHeading('Travel & insurance')
            ->visible(fn () => $this->readOnlyReason === null)
            ->fillForm(function (array $arguments): array {
                $d = $this->travelDetail((int) ($arguments['person'] ?? 0));
                if (! $d) {
                    return [];
                }
                // The policy number is only ever sent to the browser for users allowed to see it.
                $keys = ['insurance_company', 'vaccinations', ...(Person::canViewMedical(auth()->user()) ? ['insurance_policy_no'] : [])];

                return [...$d->only($keys), 'insurance_valid_until' => $d->insurance_valid_until?->toDateString(),
                    'insurance_certificate_path' => $d->insurance_certificate_path ? [$d->insurance_certificate_path] : []];
            })
            ->schema([
                TextInput::make('insurance_company')->label('Travel insurance company')->maxLength(255),
                TextInput::make('insurance_policy_no')->label('Policy number')->maxLength(100)
                    ->visible(fn () => Person::canViewMedical(auth()->user())),
                DatePicker::make('insurance_valid_until')->label('Insurance valid until'),
                FileUpload::make('insurance_certificate_path')->label('Insurance certificate')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240)
                    ->disk(config('filesystems.private'))->directory(fn () => 'submissions/'.$this->submission->id)->visibility('private')
                    // No record backs this modal, so accept exactly the stored certificate of the person being edited.
                    ->preventFilePathTampering(allowFilePathUsing: fn (string $file) => $file === $this->travelDetail(
                        (int) ($this->getMountedAction()?->getArguments()['person'] ?? 0))?->insurance_certificate_path),
                Textarea::make('vaccinations')->label('Vaccinations for the destination')->rows(3)->maxLength(2000),
            ])
            ->action(function (array $data, array $arguments) {
                $personId = (int) ($arguments['person'] ?? 0);
                if (! $this->submission->entries()->where('person_id', $personId)->exists()) {
                    Notification::make()->danger()->title('Save the Long List with this person first.')->send();

                    return;
                }
                $cert = $data['insurance_certificate_path'] ?? null;
                $data['insurance_certificate_path'] = is_array($cert) ? (array_values($cert)[0] ?? null) : $cert;
                try {
                    $this->guardEditable();
                    DB::transaction(function () use ($data, $personId) {
                        $this->claimOrHalt();
                        LongListDetail::updateOrCreate(['submission_id' => $this->submission->id, 'person_id' => $personId], $data);
                    });
                } catch (Halt) {
                    return;
                }
                $this->refreshLoadedAt();
                Notification::make()->success()->title('Travel details saved')->send();
            });
    }

    private function travelDetail(int $personId): ?LongListDetail
    {
        return $this->submission->details()->where('person_id', $personId)->first();
    }
}
```

- [ ] **Step 4: Create the page and its view**

`app/Filament/Portal/Pages/Phase3Entry.php`:

```php
<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Portal\Concerns\HandlesSubmission;
use App\Filament\Shared\Phases\EditsPhase3;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class Phase3Entry extends Page
{
    use EditsPhase3, HandlesSubmission;

    protected string $view = 'filament.portal.pages.phase3-entry';

    public static function getRoutePath(Panel $panel): string
    {
        return '/games/{game}/phase-3';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->gameModel->name.' – Phase 3 · Long List';
    }

    public function mount(int|string $game): void
    {
        $this->mountPortal($game, 3);
    }

    protected function getHeaderActions(): array
    {
        return [$this->submitAction(), $this->approveAction(), $this->returnAction()];
    }
}
```

`resources/views/filament/portal/pages/phase3-entry.blade.php`:

```blade
<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Deadline: {{ $this->submission->gamePhase()->effectiveDeadline($this->submission)?->format('j M Y H:i') ?? 'none' }}
    </p>

    @if ($readOnlyReason)
        <div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">{{ $readOnlyReason }}</div>
    @endif

    @php
        $p2 = $this->phase2Numbers();
        $athleteOptions = $this->candidates(\App\Enums\PersonType::Athlete);
        $officialOptions = $this->candidates(\App\Enums\PersonType::Official);
        $selectClass = 'w-full rounded border-gray-300 text-sm dark:bg-white/5';
    @endphp

    @forelse ($this->visibleSports() as $gs)
        <x-filament::section :heading="$gs->sport->name">
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($gs->gameEvents as $ge)
                    @php
                        $n = count((array) ($athletes[$ge->id] ?? []));
                        $max = $p2 === null ? null : ($p2[$ge->id] ?? 0);
                        $over = $max !== null && $n > $max;
                    @endphp
                    <div wire:key="event-{{ $ge->id }}">
                        <label class="text-sm font-medium" for="event-{{ $ge->id }}">{{ $ge->event->name }} ({{ $ge->event->gender->label() }})</label>
                        <p @class(['text-xs', 'font-medium text-warning-600' => $over, 'text-gray-500' => ! $over])>Long List {{ $n }}@if ($max !== null) / Phase 2: {{ $max }}@endif @if ($over) · Over the Phase 2 number @endif</p>
                        <select id="event-{{ $ge->id }}" multiple size="6" class="{{ $selectClass }}" wire:model.live="athletes.{{ $ge->id }}" @disabled($readOnlyReason)>
                            @foreach ($athleteOptions->filter(fn ($p) => \App\Services\Phases\LongListChecks::genderMatches($p->gender, $ge->event->gender)) as $p)
                                <option value="{{ $p->id }}">{{ $p->fullName() }} ({{ $p->code }})</option>
                            @endforeach
                        </select>
                        @error('athletes.'.$ge->id)<p class="text-xs text-danger-600">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div wire:key="officials-{{ $gs->id }}">
                    <label class="text-sm font-medium" for="officials-{{ $gs->id }}">Team officials</label>
                    <select id="officials-{{ $gs->id }}" multiple size="6" class="{{ $selectClass }}" wire:model.live="officials.{{ $gs->id }}" @disabled($readOnlyReason)>
                        @foreach ($officialOptions as $p)
                            <option value="{{ $p->id }}">{{ $p->fullName() }} – {{ $p->function }} ({{ $p->code }})</option>
                        @endforeach
                    </select>
                    @error('officials.'.$gs->id)<p class="text-xs text-danger-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </x-filament::section>
    @empty
        <p class="text-sm text-gray-500">No sports to enter for your association in this game.</p>
    @endforelse

    <x-filament::section heading="People on the Long List">
        @php($rows = $this->listRows())
        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500">Nobody on the list yet.</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500"><th class="py-1">Name</th><th>Entered in</th><th>Age</th><th>Registry</th><th>Warnings</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr wire:key="person-{{ $row['person']->id }}" class="border-t border-gray-200 align-top dark:border-white/10">
                            <td class="py-2">{{ $row['person']->fullName() }} <span class="text-xs text-gray-500">{{ $row['person']->code }}</span></td>
                            <td class="py-2">{{ implode(', ', $row['roles']) }}</td>
                            <td class="py-2">{{ $row['age'] }}</td>
                            <td class="py-2">
                                @if ($row['missing'])
                                    <x-filament::badge color="danger">Incomplete</x-filament::badge>
                                    <div class="text-xs text-gray-500">Missing: {{ implode(', ', $row['missing']) }}</div>
                                @else
                                    <x-filament::badge color="success">Complete</x-filament::badge>
                                @endif
                            </td>
                            <td class="py-2">
                                <ul class="text-xs text-warning-600">
                                    @foreach ($row['warnings'] as $w)<li>{{ $w }}</li>@endforeach
                                </ul>
                            </td>
                            <td class="py-2">
                                @if ($row['detail'])
                                    <div class="text-xs text-gray-500">Insurance: {{ $row['detail']->insurance_company ?? '–' }}</div>
                                @endif
                                @if (! $readOnlyReason && $row['on_list'])
                                    <x-filament::link tag="button" size="xs" wire:click="mountAction('travel', { person: {{ $row['person']->id }} })">Travel & insurance</x-filament::link>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    @unless ($readOnlyReason)
        <div><x-filament::button wire:click="save">Save</x-filament::button></div>
    @endunless
</x-filament-panels::page>
```

- [ ] **Step 5: Link phase 3 from the games page**

In `app/Filament/Portal/Pages/Games.php` replace the `$page = match …` block with:

```php
                $page = match ($p->phase) {
                    1 => Phase1Entry::class,
                    2 => Phase2Entry::class,
                    3 => Phase3Entry::class,
                    default => null,
                };
```

- [ ] **Step 6: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/Phase3EntryTest.php`, then `$PHP vendor/bin/pest`.
Expected: all pass.

- [ ] **Step 7: Commit**

```bash
$PHP vendor/bin/pint
git add app/Filament/Shared/Phases/EditsPhase3.php app/Filament/Portal/Pages/Phase3Entry.php app/Filament/Portal/Pages/Games.php resources/views/filament/portal/pages/phase3-entry.blade.php tests/Feature/Phase3EntryTest.php
git commit -m "feat: portal phase 3 long list with warnings and travel details

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Long List PDF

**Files:**
- Create: `app/Jobs/GenerateLongListPdf.php`, `resources/views/pdf/long-list.blade.php`
- Modify: `app/Jobs/GenerateEntryByEventPdfs.php` (`dataUri` becomes `public static`), `app/Services/Phases/SubmissionWorkflow.php` (`finalise` dispatch), `resources/views/filament/portal/pages/phase3-entry.blade.php` (download link)
- Test: `tests/Feature/LongListPdfTest.php`

**Interfaces:**
- Consumes: `Submission::entries()`, `Game::phase2SportsFor()`, `Game::clothingItems`, `Setting`.
- Produces:
  - `GenerateLongListPdf::dispatch(Submission $s)` → file `submissions/{reference_no}/long-list.pdf`, `pdf_paths = ['long_list' => path]`.
  - View `pdf.long-list` with data `game`, `association`, `athletes` (list of `photo, name, sport, events, dob, sizes (list aligned with athleteItems), passport (bool)`), `officials` (list of `photo, name, function, dob, phone, email, sizes (string), passport (bool)`), `athleteItems` (list of item names), `referenceNo`, `declaration`, `nocLogo`, `gameLogo`.
  - `GenerateEntryByEventPdfs::dataUri(?string $path): ?string` (public).

- [ ] **Step 1: Write the failing test**

`tests/Feature/LongListPdfTest.php`:

```php
<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Phase3Entry;
use App\Jobs\GenerateLongListPdf;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\Phases\SubmissionWorkflow;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake(config('filesystems.private'));
    $this->assoc = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    $this->shirt = ClothingItem::factory()->create(['name' => 'T-Shirt']);
    $this->game->clothingItems()->attach($this->shirt);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->ali = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan', 'passport_scan_path' => 'scans/p.pdf']);
    $this->ali->syncSizes([$this->shirt->id => 'L']);
    $this->coach = Person::factory()->official()->create(['association_id' => $this->assoc->id, 'first_name' => 'Coach', 'last_name' => 'Karim', 'email' => 'coach@example.com']);
    $this->sub = Submission::for($this->game, $this->assoc, 3);
    $this->sub->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BJF-G001-P3-R1', 'revision' => 1]);
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $this->ali->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $this->coach->id, 'game_event_id' => null, 'game_sport_id' => $this->judo->id]);
});

it('stores the Long List PDF under the reference number', function () {
    (new GenerateLongListPdf($this->sub))->handle();

    $path = $this->sub->fresh()->pdf_paths['long_list'];
    expect($path)->toBe('submissions/BJF-G001-P3-R1/long-list.pdf')
        ->and(substr(Storage::disk(config('filesystems.private'))->get($path), 0, 4))->toBe('%PDF');
});

it('passes athlete and official rows with the spec columns to the view', function () {
    $captured = null;
    view()->composer('pdf.long-list', function ($v) use (&$captured) {
        $captured = $v->getData();
    });

    (new GenerateLongListPdf($this->sub))->handle();

    expect($captured['athleteItems'])->toBe(['T-Shirt'])
        ->and(Arr::except($captured['athletes'][0], 'photo'))->toBe([
            'name' => 'Ali Hasan', 'sport' => 'Judo', 'events' => 'Men -60kg', 'dob' => '10 Mar 2005', 'sizes' => ['L'], 'passport' => true,
        ])
        ->and(Arr::except($captured['officials'][0], 'photo'))->toBe([
            'name' => 'Coach Karim', 'function' => 'Head Coach', 'dob' => '10 Mar 2005', 'phone' => '+973 33000000',
            'email' => 'coach@example.com', 'sizes' => '', 'passport' => false,
        ]);
});

it('renders the header, columns, declaration, boxes and reference', function () {
    $html = view('pdf.long-list', [
        'game' => $this->game, 'association' => $this->assoc, 'athleteItems' => ['T-Shirt'],
        'athletes' => [['photo' => null, 'name' => 'Ali Hasan', 'sport' => 'Judo', 'events' => 'Men -60kg', 'dob' => '10 Mar 2005', 'sizes' => ['L'], 'passport' => true]],
        'officials' => [['photo' => null, 'name' => 'Coach Karim', 'function' => 'Head Coach', 'dob' => '10 Mar 2005', 'phone' => '+973 33000000',
            'email' => 'coach@example.com', 'sizes' => 'T-Shirt: M', 'passport' => false]],
        'referenceNo' => 'BJF-G001-P3-R1', 'declaration' => 'I confirm.', 'nocLogo' => 'data:image/png;base64,AA==', 'gameLogo' => null,
    ])->render();

    expect($html)->toContain('Long List')->toContain('Bahrain Judo Federation')->toContain('Asian Indoor Games')
        ->toContain('Photo')->toContain('Date of birth')->toContain('Passport scan')->toContain('T-Shirt')->toContain('Function')
        ->toContain('Phone')->toContain('Email')->toContain('Sizes')->toContain('Ali Hasan')->toContain('Men -60kg')->toContain('T-Shirt: M')
        ->toContain('I confirm.')->toContain('Signature')->toContain('Stamp')->toContain('BJF-G001-P3-R1');
});

it('is dispatched when a long list is finalised', function () {
    Bus::fake([GenerateLongListPdf::class]);
    Notification::fake();
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    Entry::query()->delete();
    $this->sub->update(['status' => SubmissionStatus::Draft]);

    app(SubmissionWorkflow::class)->submit($this->sub->fresh(), User::factory()->assocAdmin($this->assoc)->create());

    Bus::assertDispatched(GenerateLongListPdf::class);
});

it('offers the download on the phase 3 page once submitted', function () {
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->sub->update(['pdf_paths' => ['long_list' => 'submissions/BJF-G001-P3-R1/long-list.pdf']]);
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());

    livewire(Phase3Entry::class, ['game' => $this->game->id])->assertSee('Download Long List PDF');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/LongListPdfTest.php`
Expected: FAIL with `Class "App\Jobs\GenerateLongListPdf" not found`.

- [ ] **Step 3: Share the data-URI helper**

In `app/Jobs/GenerateEntryByEventPdfs.php` change `private static function dataUri(?string $path): ?string` to `public static function dataUri(?string $path): ?string` (body unchanged).

- [ ] **Step 4: Write the job**

`app/Jobs/GenerateLongListPdf.php`:

```php
<?php

namespace App\Jobs;

use App\Enums\PersonType;
use App\Models\Person;
use App\Models\Setting;
use App\Models\Submission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateLongListPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = 60;

    public function __construct(public Submission $submission) {}

    public function handle(): void
    {
        $s = $this->submission->fresh(['game.clothingItems', 'association']);
        $sports = $s->game->phase2SportsFor($s->association)->keyBy('id');
        $entries = $s->entries()->get()->filter(fn ($e) => $sports->has($e->game_sport_id));
        $people = Person::withoutGlobalScopes()->with('sizes')->where('association_id', $s->association_id)
            ->whereKey($entries->pluck('person_id')->unique()->all())->get()->keyBy('id');
        $entries = $entries->filter(fn ($e) => $people->has($e->person_id));
        $items = $s->game->clothingItems->sortBy('name')->values();
        $athleteItems = $items->filter(fn ($i) => $i->applies_to->includes(PersonType::Athlete))->values();
        $officialItems = $items->filter(fn ($i) => $i->applies_to->includes(PersonType::Official))->values();

        // One row per athlete per sport, the events joined.
        $athletes = $entries->whereNotNull('game_event_id')->groupBy(fn ($e) => $e->person_id.'-'.$e->game_sport_id)
            ->map(function ($group) use ($people, $sports, $athleteItems) {
                $p = $people[$group->first()->person_id];
                $gs = $sports[$group->first()->game_sport_id];
                $sizes = $p->sizes->pluck('size', 'clothing_item_id');

                return [
                    'photo' => GenerateEntryByEventPdfs::dataUri($p->photo_path),
                    'name' => $p->full_name ?: $p->fullName(),
                    'sport' => $gs->sport->name,
                    'events' => $gs->gameEvents->whereIn('id', $group->pluck('game_event_id')->all())->map(fn ($ge) => $ge->event->name)->sort()->implode(', '),
                    'dob' => $p->dob->format('d M Y'),
                    'sizes' => $athleteItems->map(fn ($i) => $sizes->get($i->id, ''))->all(),
                    'passport' => filled($p->passport_scan_path),
                ];
            })->sortBy([['sport', 'asc'], ['name', 'asc']])->values()->all();

        // One row per official, whatever the number of sports.
        $officials = $entries->whereNull('game_event_id')->pluck('person_id')->unique()
            ->map(function ($id) use ($people, $officialItems) {
                $p = $people[$id];
                $sizes = $p->sizes->pluck('size', 'clothing_item_id');

                return [
                    'photo' => GenerateEntryByEventPdfs::dataUri($p->photo_path),
                    'name' => $p->full_name ?: $p->fullName(),
                    'function' => $p->function,
                    'dob' => $p->dob->format('d M Y'),
                    'phone' => filled($p->phone) ? $p->phone_cc.' '.$p->phone : '',
                    'email' => $p->email ?? '',
                    'sizes' => $officialItems->filter(fn ($i) => filled($sizes->get($i->id)))->map(fn ($i) => $i->name.': '.$sizes->get($i->id))->implode(', '),
                    'passport' => filled($p->passport_scan_path),
                ];
            })->sortBy('name')->values()->all();

        $html = view('pdf.long-list', [
            'game' => $s->game, 'association' => $s->association, 'athletes' => $athletes, 'officials' => $officials,
            'athleteItems' => $athleteItems->pluck('name')->all(), 'referenceNo' => $s->reference_no,
            'declaration' => Setting::get(Setting::DECLARATION, Setting::DEFAULT_DECLARATION),
            'nocLogo' => GenerateEntryByEventPdfs::dataUri(Setting::get(Setting::NOC_LOGO)),
            'gameLogo' => GenerateEntryByEventPdfs::dataUri($s->game->logo_path),
        ])->render();
        $path = 'submissions/'.$s->reference_no.'/long-list.pdf';
        Storage::disk(config('filesystems.private'))->put($path, Pdf::loadHTML($html)->setPaper('a4', 'landscape')->output());
        $s->forceFill(['pdf_paths' => ['long_list' => $path]])->saveQuietly();
    }
}
```

- [ ] **Step 5: Write the PDF view**

`resources/views/pdf/long-list.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; }
    table { width: 100%; border-collapse: collapse; }
    .grid td, .grid th { border: 1px solid #000; padding: 3px 4px; vertical-align: middle; }
    .grid th { background: #d9d9d9; text-align: left; }
    .c { text-align: center; }
    h3 { font-size: 11px; margin: 10px 0 4px; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width:20%">@if($nocLogo)<img src="{{ $nocLogo }}" style="max-width:100%;max-height:60px">@endif</td>
        <td class="c">
            <div style="font-size:14px;font-weight:bold">{{ $game->name }}</div>
            <div style="font-size:13px">Long List</div>
            <div style="font-size:13px;font-weight:bold">{{ $association->name }}</div>
        </td>
        <td style="width:20%;text-align:right">@if($gameLogo)<img src="{{ $gameLogo }}" style="max-width:100%;max-height:60px">@endif</td>
    </tr>
</table>
<p>
    @if($game->start_date){{ $game->start_date->format('d M Y') }}@if($game->end_date) - {{ $game->end_date->format('d M Y') }}@endif &middot; @endif
    Reference: {{ $referenceNo }}
</p>

<h3>Athletes</h3>
<table class="grid">
    <tr>
        <th style="width:50px">Photo</th><th>Name</th><th>Sport</th><th>Event</th><th>Date of birth</th>
        @foreach($athleteItems as $item)<th>{{ $item }}</th>@endforeach
        <th>Passport scan</th>
    </tr>
    @forelse($athletes as $r)
    <tr>
        <td class="c">@if($r['photo'])<img src="{{ $r['photo'] }}" style="width:40px;height:50px">@endif</td>
        <td>{{ $r['name'] }}</td>
        <td>{{ $r['sport'] }}</td>
        <td>{{ $r['events'] }}</td>
        <td>{{ $r['dob'] }}</td>
        @foreach($r['sizes'] as $size)<td class="c">{{ $size }}</td>@endforeach
        <td class="c">{{ $r['passport'] ? 'Yes' : 'No' }}</td>
    </tr>
    @empty
    <tr><td colspan="{{ 6 + count($athleteItems) }}">No athletes.</td></tr>
    @endforelse
</table>

<h3>Team officials</h3>
<table class="grid">
    <tr>
        <th style="width:50px">Photo</th><th>Name</th><th>Function</th><th>Date of birth</th><th>Phone</th><th>Email</th><th>Sizes</th><th>Passport scan</th>
    </tr>
    @forelse($officials as $r)
    <tr>
        <td class="c">@if($r['photo'])<img src="{{ $r['photo'] }}" style="width:40px;height:50px">@endif</td>
        <td>{{ $r['name'] }}</td>
        <td>{{ $r['function'] }}</td>
        <td>{{ $r['dob'] }}</td>
        <td>{{ $r['phone'] }}</td>
        <td>{{ $r['email'] }}</td>
        <td>{{ $r['sizes'] }}</td>
        <td class="c">{{ $r['passport'] ? 'Yes' : 'No' }}</td>
    </tr>
    @empty
    <tr><td colspan="8">No team officials.</td></tr>
    @endforelse
</table>

<p style="margin-top:14px">{{ $declaration }}</p>
<table class="grid" style="margin-top:10px">
    <tr>
        @foreach(['Name', 'Position', 'Signature', 'Stamp'] as $label)
        <td style="height:60px;width:25%;vertical-align:top">{{ $label }}</td>
        @endforeach
    </tr>
</table>
<p style="margin-top:14px;font-size:8px">Reference {{ $referenceNo }} &middot; generated {{ now()->timezone('Asia/Bahrain')->format('d M Y H:i') }}</p>
</body>
</html>
```

- [ ] **Step 6: Dispatch on final submit and offer the download**

In `app/Services/Phases/SubmissionWorkflow.php`, add `use App\Jobs\GenerateLongListPdf;` and in `finalise()` after the Phase 2 dispatch add:

```php
        if ($s->phase === 3) {
            GenerateLongListPdf::dispatch($s);
        }
```

(`finalise()` already resets `pdf_paths` to null, so the page shows no stale link while the job runs.)

In `resources/views/filament/portal/pages/phase3-entry.blade.php`, directly after the `@php … @endphp` block that sets `$p2`, add:

```blade
    @php($paths = $this->submission->pdf_paths ?? [])
    @if ($this->submission->status === \App\Enums\SubmissionStatus::Submitted && isset($paths['long_list']))
        <a class="text-sm text-primary-600 underline" target="_blank"
           href="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.private'))->temporaryUrl($paths['long_list'], now()->addMinutes(5)) }}">Download Long List PDF</a>
    @endif
```

The Game dashboard already lists every `pdf_paths` entry, so it shows `long-list.pdf` with no change.

- [ ] **Step 7: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/LongListPdfTest.php tests/Feature/EntryByEventPdfTest.php`, then `$PHP vendor/bin/pest`.
Expected: all pass.

- [ ] **Step 8: Commit**

```bash
$PHP vendor/bin/pint
git add app/Jobs/GenerateLongListPdf.php app/Jobs/GenerateEntryByEventPdfs.php resources/views/pdf/long-list.blade.php app/Services/Phases/SubmissionWorkflow.php resources/views/filament/portal/pages/phase3-entry.blade.php tests/Feature/LongListPdfTest.php
git commit -m "feat: Long List PDF on final submit

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Admin submission view and dashboard links

**Files:**
- Create: `app/Filament/Resources/Games/Pages/SubmissionView.php`, `resources/views/filament/resources/games/pages/submission-view.blade.php`
- Modify: `app/Filament/Resources/Games/GameResource.php` (route), `app/Filament/Resources/Games/Pages/GameDashboard.php` (`withExists` entries, static `pdfLinks`), `resources/views/filament/resources/games/pages/game-dashboard.blade.php` (link)
- Test: `tests/Feature/SubmissionViewTest.php`

**Interfaces:**
- Consumes: `LongListChecks::rows/selection` (Task 2), `Person::canViewMedical`, `AttachmentActions::url`, activity log name (Task 1).
- Produces:
  - Route name `submission`: `GameResource::getUrl('submission', ['record' => $game, 'submission' => $submission])` → `/admin/games/{record}/submissions/{submission}`.
  - `SubmissionView` public `Submission $submission`, methods `phase1Rows()`, `phase2Rows()`, `longListRows()`, `auditTrail()`; `getHeaderActions()` returns `[]` for now (Tasks 7 and 8 add actions).
  - `GameDashboard::pdfLinks(Submission $s): array` is now `public static`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/SubmissionViewTest.php`:

```php
<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\GameDashboard;
use App\Filament\Resources\Games\Pages\SubmissionView;
use App\Models\Association;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\LongListDetail;
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->assoc = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->ali = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->sub = Submission::for($this->game, $this->assoc, 3);
    $this->actingAs(User::factory()->assocAdmin($this->assoc)->create(['name' => 'Fatima Admin']));
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $this->ali->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    LongListDetail::create(['submission_id' => $this->sub->id, 'person_id' => $this->ali->id, 'insurance_company' => 'AXA Gulf', 'insurance_policy_no' => 'POL-777']);
    $this->viewer = User::factory()->sta()->create();
    $this->viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
});

it('shows the submission, its long list and its audit trail', function () {
    actingInAdmin($this->viewer);
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->sub->id])
        ->assertSee('Bahrain Judo Federation')->assertSee('Long List')->assertSee('Ali Hasan')->assertSee('Judo · Men -60kg')
        ->assertSee('AXA Gulf')->assertDontSee('POL-777')->assertSee('Fatima Admin')->assertSee('Entry created');
});

it('shows the policy number only with view_medical', function () {
    $this->viewer->givePermissionTo(StaPermission::ViewMedical->value);
    actingInAdmin($this->viewer);
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->sub->id])->assertSee('POL-777');
});

it('shows phase 2 numbers', function () {
    $p2 = Submission::for($this->game, $this->assoc, 2);
    NumberEntry::create(['submission_id' => $p2->id, 'game_event_id' => $this->m60->id, 'count' => 3]);
    actingInAdmin($this->viewer);
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $p2->id])
        ->assertSee('Entry by Number')->assertSee('Men -60kg')->assertSeeHtml('<td class="py-1">3</td>');
});

it('opens over HTTP for view_submissions and forbids others', function () {
    actingInAdmin($this->viewer);
    $this->get(GameResource::getUrl('submission', ['record' => $this->game, 'submission' => $this->sub]))->assertOk();

    actingInAdmin(User::factory()->sta()->create());
    $this->get(GameResource::getUrl('submission', ['record' => $this->game, 'submission' => $this->sub]))->assertForbidden();
});

it('returns 404 for a submission of another game', function () {
    actingInAdmin($this->viewer);
    $other = Game::factory()->create();
    $this->get(GameResource::getUrl('submission', ['record' => $other, 'submission' => $this->sub]))->assertNotFound();
});

it('links dashboard cells to the submission and counts entries as in progress, without creating rows', function () {
    $this->game->phase(1)->update(['enabled' => true]);
    actingInAdmin($this->viewer);

    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->assertSeeHtml(GameResource::getUrl('submission', ['record' => $this->game, 'submission' => $this->sub]))
        ->assertSee('In progress');
    expect(Submission::withoutGlobalScopes()->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/SubmissionViewTest.php`
Expected: FAIL with `Class "App\Filament\Resources\Games\Pages\SubmissionView" not found`.

- [ ] **Step 3: Write the page**

`app/Filament/Resources/Games/Pages/SubmissionView.php`:

```php
<?php

namespace App\Filament\Resources\Games\Pages;

use App\Enums\StaPermission;
use App\Filament\Resources\Games\GameResource;
use App\Models\GamePhase;
use App\Models\Submission;
use App\Services\Phases\LongListChecks;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class SubmissionView extends Page
{
    use InteractsWithRecord;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.resources.games.pages.submission-view';

    public Submission $submission;

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can(StaPermission::ViewSubmissions->value);
    }

    /** $submission is a model over HTTP (Livewire binds the typed property) and an id in Livewire tests. */
    public function mount(int|string $record, int|string|Submission $submission): void
    {
        $this->record = $this->resolveRecord($record);
        $this->submission = Submission::withoutGlobalScopes()->where('game_id', $this->record->getKey())
            ->findOrFail($submission instanceof Submission ? $submission->getKey() : $submission);
    }

    public function getTitle(): string
    {
        return $this->submission->association->name.' – Phase '.$this->submission->phase.' · '.GamePhase::NAMES[$this->submission->phase];
    }

    /** @return Collection<int, array{sport: string, answer: string}> */
    public function phase1Rows(): Collection
    {
        $answers = $this->submission->answers()->get()->keyBy('game_sport_id');

        return $this->record->sportsFor($this->submission->association)
            ->map(fn ($gs) => ['sport' => $gs->sport->name, 'answer' => $answers->get($gs->id)?->answer->label() ?? 'No response']);
    }

    /** @return Collection<int, array{sport: string, event: string, count: int}> */
    public function phase2Rows(): Collection
    {
        $counts = $this->submission->numbers()->pluck('count', 'game_event_id');

        return $this->record->phase2SportsFor($this->submission->association)->flatMap(fn ($gs) => $gs->gameEvents->map(
            fn ($ge) => ['sport' => $gs->sport->name, 'event' => $ge->event->name, 'count' => (int) ($counts[$ge->id] ?? 0)]));
    }

    public function longListRows(): Collection
    {
        $checks = app(LongListChecks::class);

        return $checks->rows($this->submission, ...$checks->selection($this->submission));
    }

    /** @return Collection<int, Activity> */
    public function auditTrail(): Collection
    {
        return Activity::where('log_name', 'submission-'.$this->submission->id)->with('causer')->latest('id')->get();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
```

`resources/views/filament/resources/games/pages/submission-view.blade.php`:

```blade
<x-filament-panels::page>
    @php
        $s = $this->submission;
        $deadline = $s->gamePhase()->effectiveDeadline($s);
        $showMedical = \App\Models\Person::canViewMedical(auth()->user());
        $links = \App\Filament\Resources\Games\Pages\GameDashboard::pdfLinks($s);
    @endphp

    <x-filament::section>
        <dl class="grid gap-3 text-sm sm:grid-cols-3">
            <div><dt class="text-gray-500">Association</dt><dd>{{ $s->association->name }} ({{ $s->association->code }})</dd></div>
            <div><dt class="text-gray-500">Phase</dt><dd>Phase {{ $s->phase }} · {{ \App\Models\GamePhase::NAMES[$s->phase] }}</dd></div>
            <div><dt class="text-gray-500">Status</dt><dd>{{ $s->status->label() }}</dd></div>
            <div><dt class="text-gray-500">Reference</dt><dd>{{ $s->reference_no ?? '–' }}</dd></div>
            <div><dt class="text-gray-500">Deadline</dt><dd>{{ $deadline?->format('j M Y H:i') ?? 'none' }}</dd></div>
            <div><dt class="text-gray-500">PDFs</dt><dd>
                @forelse ($links as $link)
                    <a href="{{ $link['url'] }}" target="_blank" class="block text-primary-600 underline">{{ $link['name'] }}</a>
                @empty
                    –
                @endforelse
            </dd></div>
        </dl>
    </x-filament::section>

    @if ($s->phase === 1)
        <x-filament::section heading="Entry by Sport">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="py-1">Sport</th><th class="py-1">Answer</th></tr></thead>
                <tbody>
                    @foreach ($this->phase1Rows() as $r)
                        <tr class="border-t border-gray-200 dark:border-white/10"><td class="py-1">{{ $r['sport'] }}</td><td class="py-1">{{ $r['answer'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @elseif ($s->phase === 2)
        <x-filament::section heading="Entry by Number">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="py-1">Sport</th><th class="py-1">Event</th><th class="py-1">Athletes</th></tr></thead>
                <tbody>
                    @foreach ($this->phase2Rows() as $r)
                        <tr class="border-t border-gray-200 dark:border-white/10"><td class="py-1">{{ $r['sport'] }}</td><td class="py-1">{{ $r['event'] }}</td><td class="py-1">{{ $r['count'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @elseif ($s->phase === 3)
        <x-filament::section heading="Long List">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500"><th class="py-1">Name</th><th>Entered in</th><th>Age</th><th>Registry</th><th>Warnings</th><th>Travel & insurance</th></tr>
                </thead>
                <tbody>
                    @forelse ($this->longListRows() as $row)
                        <tr class="border-t border-gray-200 align-top dark:border-white/10">
                            <td class="py-2">{{ $row['person']->fullName() }} <span class="text-xs text-gray-500">{{ $row['person']->code }}</span></td>
                            <td class="py-2">{{ implode(', ', $row['roles']) }}</td>
                            <td class="py-2">{{ $row['age'] }}</td>
                            <td class="py-2">
                                @if ($row['missing'])
                                    <x-filament::badge color="danger">Incomplete</x-filament::badge>
                                    <div class="text-xs text-gray-500">Missing: {{ implode(', ', $row['missing']) }}</div>
                                @else
                                    <x-filament::badge color="success">Complete</x-filament::badge>
                                @endif
                            </td>
                            <td class="py-2"><ul class="text-xs text-warning-600">@foreach ($row['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul></td>
                            <td class="py-2 text-xs">
                                @if ($d = $row['detail'])
                                    <div>{{ $d->insurance_company ?? '–' }}@if ($showMedical) · Policy {{ $d->insurance_policy_no ?? '–' }}@endif</div>
                                    <div>Valid until {{ $d->insurance_valid_until?->format('j M Y') ?? '–' }}</div>
                                    @if ($d->insurance_certificate_path)
                                        <a href="{{ \App\Filament\Shared\AttachmentActions::url($d->insurance_certificate_path) }}" target="_blank" class="text-primary-600 underline">Certificate</a>
                                    @endif
                                    @if ($d->vaccinations)<div>Vaccinations: {{ $d->vaccinations }}</div>@endif
                                @else
                                    –
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-2 text-gray-500">Nobody on the list.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    @endif

    <x-filament::section heading="Audit trail">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500"><th class="py-1">When</th><th>Who</th><th>What</th><th>Changed</th></tr></thead>
            <tbody>
                @forelse ($this->auditTrail() as $a)
                    <tr class="border-t border-gray-200 align-top dark:border-white/10">
                        <td class="py-1">{{ $a->created_at->format('j M Y H:i') }}</td>
                        <td class="py-1">{{ $a->causer?->name ?? 'System' }}</td>
                        <td class="py-1">{{ class_basename($a->subject_type) }} {{ $a->event }}</td>
                        <td class="py-1 text-xs">{{ implode(', ', array_keys($a->properties->get('attributes', $a->properties->get('old', [])))) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-2 text-gray-500">No changes recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
```

- [ ] **Step 4: Register the route**

In `app/Filament/Resources/Games/GameResource.php` add `use App\Filament\Resources\Games\Pages\SubmissionView;` and the page entry:

```php
            'dashboard' => GameDashboard::route('/{record}/dashboard'),
            'submission' => SubmissionView::route('/{record}/submissions/{submission}'),
```

- [ ] **Step 5: Update the dashboard**

In `app/Filament/Resources/Games/Pages/GameDashboard.php`:
- in `submissions()` change `->withExists(['answers', 'numbers'])` to `->withExists(['answers', 'numbers', 'entries'])`;
- in `cell()` change the draft condition to `$s !== null && ($s->answers_exists || $s->numbers_exists || $s->entries_exists) => 'draft',`;
- change `public function pdfLinks(Submission $s): array` to `public static function pdfLinks(Submission $s): array` (body unchanged).

In `resources/views/filament/resources/games/pages/game-dashboard.blade.php`, directly after the `<x-filament::badge …>` line in each cell, add:

```blade
                                @if ($s)
                                    <div><a href="{{ \App\Filament\Resources\Games\GameResource::getUrl('submission', ['record' => $record, 'submission' => $s]) }}" class="text-xs text-primary-600 underline">View</a></div>
                                @endif
```

- [ ] **Step 6: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/SubmissionViewTest.php tests/Feature/GameDashboardTest.php`, then `$PHP vendor/bin/pest`.
Expected: all pass.

- [ ] **Step 7: Commit**

```bash
$PHP vendor/bin/pint
git add app/Filament/Resources/Games/Pages/SubmissionView.php resources/views/filament/resources/games/pages/submission-view.blade.php app/Filament/Resources/Games/GameResource.php app/Filament/Resources/Games/Pages/GameDashboard.php resources/views/filament/resources/games/pages/game-dashboard.blade.php tests/Feature/SubmissionViewTest.php
git commit -m "feat: admin submission view with audit trail, linked from the dashboard

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: STA comments (emailed, read-only for associations)

**Files:**
- Create: `app/Notifications/NewComment.php`, `resources/views/filament/partials/comments.blade.php`
- Modify: `app/Filament/Resources/Games/Pages/SubmissionView.php` (+ view), `app/Filament/Portal/Concerns/HandlesSubmission.php` (`submissionComments()`), `resources/views/filament/portal/pages/{phase1-entry,phase2-entry,phase3-entry}.blade.php`
- Test: `tests/Feature/SubmissionCommentsTest.php`

**Interfaces:**
- Consumes: `Comment` (Task 1), `Recipients::association`, `SubmissionView` (Task 6).
- Produces:
  - `NewComment(Comment $comment)` queued mail notification.
  - `submissionComments(): Collection<Comment>` (newest first, `user` loaded) on `HandlesSubmission` and on `SubmissionView`.
  - Partial `filament.partials.comments` taking `$comments`.
  - `SubmissionView` header action `comment` (field `body`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/SubmissionCommentsTest.php`:

```php
<?php

use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Filament\Portal\Pages\Phase1Entry;
use App\Filament\Resources\Games\Pages\SubmissionView;
use App\Models\Association;
use App\Models\Comment;
use App\Models\Game;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\NewComment;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->sub = Submission::for($this->game, $this->assoc, 1);
    $this->admin = User::factory()->assocAdmin($this->assoc)->create();
    $this->editor = User::factory()->assocEditor($this->assoc)->create();
    $this->inactive = User::factory()->assocEditor($this->assoc)->inactive()->create();
    $this->otherUser = User::factory()->assocAdmin()->create();
    $this->sta = User::factory()->sta()->create(['name' => 'Huda STA']);
    $this->sta->givePermissionTo([StaPermission::ViewSubmissions->value, StaPermission::EditSubmissions->value]);
});

it('lets STA with edit_submissions comment and emails the association\'s active users', function () {
    actingInAdmin($this->sta);
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->sub->id])
        ->callAction('comment', ['body' => 'Please upload the passport scans.'])->assertHasNoActionErrors()
        ->assertSee('Please upload the passport scans.');

    $c = Comment::sole();
    expect($c->user_id)->toBe($this->sta->id)->and($c->submission_id)->toBe($this->sub->id);
    Notification::assertSentTo([$this->admin, $this->editor], NewComment::class);
    Notification::assertNotSentTo([$this->inactive, $this->otherUser], NewComment::class);
});

it('words the email with the game, phase and comment', function () {
    $c = Comment::create(['submission_id' => $this->sub->id, 'user_id' => $this->sta->id, 'body' => 'Please upload the passport scans.']);
    $mail = (new NewComment($c))->toMail($this->admin);

    expect($mail->subject)->toBe('New STA comment on Phase 1 for Asian Indoor Games')
        ->and($mail->introLines)->toContain('Please upload the passport scans.');
});

it('hides the comment action without edit_submissions', function () {
    $viewer = User::factory()->sta()->create();
    $viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($viewer);
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->sub->id])->assertActionHidden('comment');
});

it('shows comments read-only on the association\'s phase page', function () {
    Comment::create(['submission_id' => $this->sub->id, 'user_id' => $this->sta->id, 'body' => 'Please upload the passport scans.']);
    actingInPortal($this->admin);

    livewire(Phase1Entry::class, ['game' => $this->game->id])
        ->assertSee('Comments from STA')->assertSee('Please upload the passport scans.')->assertSee('Huda STA')->assertDontSee('Add comment');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/SubmissionCommentsTest.php`
Expected: FAIL with `Class "App\Notifications\NewComment" not found`.

- [ ] **Step 3: Write the notification**

`app/Notifications/NewComment.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\GamePhase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewComment extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Comment $comment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->comment->submission;
        $game = $s->game->name;

        return (new MailMessage)
            ->subject("New STA comment on Phase {$s->phase} for {$game}")
            ->line("STA commented on {$s->association->name}'s Phase {$s->phase} (".GamePhase::NAMES[$s->phase].") entry for {$game}:")
            ->line($this->comment->body)
            ->action('Open games', url('/portal'));
    }
}
```

- [ ] **Step 4: Write the comments partial**

`resources/views/filament/partials/comments.blade.php`:

```blade
<x-filament::section heading="Comments from STA">
    @forelse ($comments as $c)
        <div class="border-b border-gray-200 py-2 text-sm last:border-0 dark:border-white/10">
            <div class="text-xs text-gray-500">{{ $c->user?->name ?? 'STA' }} · {{ $c->created_at->format('j M Y H:i') }}</div>
            <p class="whitespace-pre-line">{{ $c->body }}</p>
        </div>
    @empty
        <p class="text-sm text-gray-500">No comments.</p>
    @endforelse
</x-filament::section>
```

- [ ] **Step 5: Show comments on the phase pages**

In `app/Filament/Portal/Concerns/HandlesSubmission.php` add `use Illuminate\Support\Collection;` and the method:

```php
    /** STA comments on this submission, newest first; associations read them only. */
    public function submissionComments(): Collection
    {
        return $this->submission->comments()->with('user')->latest('id')->get();
    }
```

In each of `resources/views/filament/portal/pages/phase1-entry.blade.php`, `phase2-entry.blade.php` and `phase3-entry.blade.php`, add as the last line before `</x-filament-panels::page>`:

```blade
    @include('filament.partials.comments', ['comments' => $this->submissionComments()])
```

- [ ] **Step 6: Add the comment action and list to the submission view**

In `app/Filament/Resources/Games/Pages/SubmissionView.php` add the imports

```php
use App\Notifications\NewComment;
use App\Services\Phases\Recipients;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
```

and replace `getHeaderActions()` with, plus the new `submissionComments()`:

```php
    public function submissionComments(): Collection
    {
        return $this->submission->comments()->with('user')->latest('id')->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('comment')->label('Add comment')
                ->visible(fn () => (bool) auth()->user()->can(StaPermission::EditSubmissions->value))
                ->schema([Textarea::make('body')->label('Comment (emailed to the association)')->required()->rows(4)->maxLength(5000)])
                ->action(function (array $data) {
                    $comment = $this->submission->comments()->create(['user_id' => auth()->id(), 'body' => $data['body']]);
                    NotificationFacade::send(Recipients::association($this->submission->association), new NewComment($comment));
                    Notification::make()->success()->title('Comment added and emailed to the association')->send();
                }),
        ];
    }
```

In `resources/views/filament/resources/games/pages/submission-view.blade.php`, add before the "Audit trail" section:

```blade
    @include('filament.partials.comments', ['comments' => $this->submissionComments()])
```

- [ ] **Step 7: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/SubmissionCommentsTest.php`, then `$PHP vendor/bin/pest`.
Expected: all pass.

- [ ] **Step 8: Commit**

```bash
$PHP vendor/bin/pint
git add app/Notifications/NewComment.php resources/views/filament/partials/comments.blade.php app/Filament/Portal/Concerns/HandlesSubmission.php resources/views/filament/portal/pages app/Filament/Resources/Games/Pages/SubmissionView.php resources/views/filament/resources/games/pages/submission-view.blade.php tests/Feature/SubmissionCommentsTest.php
git commit -m "feat: STA comments on submissions, emailed and shown read-only to associations

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8: STA edits of any submission

**Files:**
- Create: `app/Filament/Resources/Games/Pages/Concerns/StaEditsSubmission.php`, `app/Filament/Resources/Games/Pages/StaPhase1Edit.php`, `app/Filament/Resources/Games/Pages/StaPhase2Edit.php`, `app/Filament/Resources/Games/Pages/StaPhase3Edit.php`
- Modify: `app/Filament/Resources/Games/GameResource.php` (routes), `app/Filament/Resources/Games/Pages/SubmissionView.php` (edit action)
- Test: `tests/Feature/StaSubmissionEditTest.php`

**Interfaces:**
- Consumes: `HandlesSubmission` (`editDenialReason`, `association`, `loadPhase`, `refreshLoadedAt`), `EditsPhase1/2/3` (Tasks 3–4), `GenerateEntryByEventPdfs`, `GenerateLongListPdf` (Task 5).
- Produces:
  - Route names `submission-edit-1|2|3`: `GameResource::getUrl('submission-edit-'.$phase, ['record' => $game, 'submission' => $s])` → `/admin/games/{record}/submissions/{submission}/edit-phase-{n}`.
  - Each page renders the portal view of its phase; `save()` saves through the phase trait, then regenerates PDFs of a submitted Phase 2 or 3 submission.
  - `SubmissionView` header action `edit` (URL to the right page).

- [ ] **Step 1: Write the failing test**

`tests/Feature/StaSubmissionEditTest.php`:

```php
<?php

use App\Enums\EventGender;
use App\Enums\InterestAnswerValue;
use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\StaPhase1Edit;
use App\Filament\Resources\Games\Pages\StaPhase2Edit;
use App\Filament\Resources\Games\Pages\StaPhase3Edit;
use App\Filament\Resources\Games\Pages\SubmissionView;
use App\Jobs\GenerateEntryByEventPdfs;
use App\Jobs\GenerateLongListPdf;
use App\Models\Association;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\InterestAnswer;
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    Bus::fake();
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    foreach ([1, 2, 3] as $n) {
        $this->game->phase($n)->update(['enabled' => true, 'status' => PhaseStatus::Closed, 'deadline' => now()->subDay()]);
    }
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id, 'p2_max_athletes' => 1,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->ali = Person::factory()->complete()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->sta = User::factory()->sta()->create(['name' => 'Sta Editor']);
    $this->sta->givePermissionTo([StaPermission::ViewSubmissions->value, StaPermission::EditSubmissions->value]);
    actingInAdmin($this->sta);
});

it('lets STA edit a submitted long list after the deadline, audited, and regenerates its PDF', function () {
    $s = Submission::for($this->game, $this->assoc, 3);
    $s->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BJF-G001-P3-R1', 'revision' => 1]);

    livewire(StaPhase3Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s->id])
        ->assertDontSee('This phase is closed.')
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save')->assertHasNoErrors();

    expect(Entry::sole()->person_id)->toBe($this->ali->id)
        ->and($s->fresh()->status)->toBe(SubmissionStatus::Submitted)
        ->and($s->fresh()->reference_no)->toBe('BJF-G001-P3-R1')
        ->and(Activity::where('log_name', 'submission-'.$s->id)->where('subject_type', Entry::class)->sole()->causer_id)->toBe($this->sta->id);
    Bus::assertDispatched(GenerateLongListPdf::class);
});

it('keeps the long list hard errors for STA', function () {
    $official = Person::factory()->official()->create(['association_id' => $this->assoc->id]);
    $s = Submission::for($this->game, $this->assoc, 3);

    livewire(StaPhase3Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s->id])
        ->set("athletes.{$this->m60->id}", [(string) $official->id])->call('save')->assertHasErrors(["athletes.{$this->m60->id}"]);
    expect(Entry::count())->toBe(0);
});

it('keeps phase 2 limits for STA and regenerates the Entry by Event PDFs', function () {
    $s = Submission::for($this->game, $this->assoc, 2);
    $s->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BJF-G001-P2-R1', 'revision' => 1]);
    $page = livewire(StaPhase2Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s->id]);

    $page->set("counts.{$this->m60->id}", 2)->call('save')->assertHasErrors(["counts.{$this->m60->id}"]);
    Bus::assertNotDispatched(GenerateEntryByEventPdfs::class);

    $page->set("counts.{$this->m60->id}", 1)->call('save')->assertHasNoErrors();
    expect(NumberEntry::sole()->count)->toBe(1);
    Bus::assertDispatched(GenerateEntryByEventPdfs::class);
});

it('lets STA answer phase 1 without changing the status', function () {
    $s = Submission::for($this->game, $this->assoc, 1);

    livewire(StaPhase1Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s->id])
        ->set("answers.{$this->judo->id}", 'yes')->call('save')->assertHasNoErrors();

    expect(InterestAnswer::sole()->answer)->toBe(InterestAnswerValue::Yes)->and($s->fresh()->status)->toBe(SubmissionStatus::Draft);
});

it('refuses a stale STA save', function () {
    $s = Submission::for($this->game, $this->assoc, 3);
    $page = livewire(StaPhase3Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s->id]);
    $fresh = $s->fresh(); // a just-created model has no lock_version attribute loaded
    $fresh->claimVersion($fresh->lock_version);

    $page->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save')
        ->assertNotified('This entry was changed by someone else — reload to see the latest.');
    expect(Entry::count())->toBe(0);
});

it('refuses STA without edit_submissions, another game and the wrong phase page', function () {
    $s = Submission::for($this->game, $this->assoc, 3);
    $viewer = User::factory()->sta()->create();
    $viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($viewer);
    $this->get(GameResource::getUrl('submission-edit-3', ['record' => $this->game, 'submission' => $s]))->assertForbidden();
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $s->id])->assertActionHidden('edit');

    actingInAdmin($this->sta);
    $other = Game::factory()->create();
    $this->get(GameResource::getUrl('submission-edit-3', ['record' => $other, 'submission' => $s]))->assertNotFound();
    $this->get(GameResource::getUrl('submission-edit-2', ['record' => $this->game, 'submission' => $s]))->assertNotFound();
    $this->get(GameResource::getUrl('submission-edit-3', ['record' => $this->game, 'submission' => $s]))->assertOk();
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $s->id])->assertActionVisible('edit');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/StaSubmissionEditTest.php`
Expected: FAIL with `Class "App\Filament\Resources\Games\Pages\StaPhase3Edit" not found`.

- [ ] **Step 3: Write the shared STA trait**

`app/Filament/Resources/Games/Pages/Concerns/StaEditsSubmission.php`:

```php
<?php

namespace App\Filament\Resources\Games\Pages\Concerns;

use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Concerns\HandlesSubmission;
use App\Filament\Resources\Games\GameResource;
use App\Jobs\GenerateEntryByEventPdfs;
use App\Jobs\GenerateLongListPdf;
use App\Models\Association;
use App\Models\GamePhase;
use App\Models\Submission;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * STA edit of one association's submission (spec 5: any status, audited). The page class sets PHASE, uses the
 * matching EditsPhaseN trait with `EditsPhaseN::save as protected saveEntries` and `StaEditsSubmission::save insteadof EditsPhaseN`.
 */
trait StaEditsSubmission
{
    use HandlesSubmission, InteractsWithRecord;

    public static function canAccess(array $parameters = []): bool
    {
        $u = auth()->user();

        return (bool) ($u?->can(StaPermission::EditSubmissions->value) && $u->can(StaPermission::ViewSubmissions->value));
    }

    /** $submission is a model over HTTP (Livewire binds the typed property) and an id in Livewire tests. */
    public function mount(int|string $record, int|string|Submission $submission): void
    {
        $this->record = $this->resolveRecord($record);
        $this->gameModel = $this->record;
        $this->submission = Submission::withoutGlobalScopes()->where(['game_id' => $this->record->getKey(), 'phase' => static::PHASE])
            ->findOrFail($submission instanceof Submission ? $submission->getKey() : $submission);
        $this->loadPhase();
        $this->refreshLoadedAt();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Edit '.$this->submission->association->name.' – Phase '.static::PHASE.' · '.GamePhase::NAMES[static::PHASE];
    }

    protected function association(): Association
    {
        return $this->submission->association;
    }

    /** STA edits at any status and after the deadline; only a disabled phase stops them. */
    protected function editDenialReason(Submission $s): ?string
    {
        return $s->gamePhase()->enabled ? null : 'This phase is not used for this game.';
    }

    abstract protected function saveEntries(): void;

    public function save(): void
    {
        $before = $this->loadedVersion;
        $this->saveEntries();
        if ($this->loadedVersion === $before || $this->submission->status !== SubmissionStatus::Submitted) {
            return;
        }
        // ponytail: the PDF is re-rendered under the same reference_no; bump the revision here if paper copies must show STA edits.
        match ($this->submission->phase) {
            2 => GenerateEntryByEventPdfs::dispatch($this->submission),
            3 => GenerateLongListPdf::dispatch($this->submission),
            default => null,
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to submission')->color('gray')
                ->url(fn () => GameResource::getUrl('submission', ['record' => $this->record, 'submission' => $this->submission])),
        ];
    }
}
```

(`InteractsWithRecord` re-checks `canAccess()` on every Livewire request, so a revoked permission stops the next save.)

- [ ] **Step 4: Write the three pages**

`app/Filament/Resources/Games/Pages/StaPhase1Edit.php`:

```php
<?php

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\Concerns\StaEditsSubmission;
use App\Filament\Shared\Phases\EditsPhase1;
use Filament\Resources\Pages\Page;

class StaPhase1Edit extends Page
{
    use EditsPhase1, StaEditsSubmission {
        StaEditsSubmission::save insteadof EditsPhase1;
        EditsPhase1::save as protected saveEntries;
    }

    public const PHASE = 1;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.portal.pages.phase1-entry';
}
```

`app/Filament/Resources/Games/Pages/StaPhase2Edit.php`:

```php
<?php

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\Concerns\StaEditsSubmission;
use App\Filament\Shared\Phases\EditsPhase2;
use Filament\Resources\Pages\Page;

class StaPhase2Edit extends Page
{
    use EditsPhase2, StaEditsSubmission {
        StaEditsSubmission::save insteadof EditsPhase2;
        EditsPhase2::save as protected saveEntries;
    }

    public const PHASE = 2;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.portal.pages.phase2-entry';
}
```

`app/Filament/Resources/Games/Pages/StaPhase3Edit.php`:

```php
<?php

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\Concerns\StaEditsSubmission;
use App\Filament\Shared\Phases\EditsPhase3;
use Filament\Resources\Pages\Page;

class StaPhase3Edit extends Page
{
    use EditsPhase3, StaEditsSubmission {
        StaEditsSubmission::save insteadof EditsPhase3;
        EditsPhase3::save as protected saveEntries;
    }

    public const PHASE = 3;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.portal.pages.phase3-entry';
}
```

(The trait-conflict pattern was checked on PHP 8.5: the trait's own `editDenialReason()`/`association()` override the ones from the nested `HandlesSubmission`, and the `insteadof` + alias pair resolves the two `save()` methods.)

- [ ] **Step 5: Register the routes and link from the submission view**

In `app/Filament/Resources/Games/GameResource.php` add the imports for the three pages and the entries:

```php
            'submission' => SubmissionView::route('/{record}/submissions/{submission}'),
            'submission-edit-1' => StaPhase1Edit::route('/{record}/submissions/{submission}/edit-phase-1'),
            'submission-edit-2' => StaPhase2Edit::route('/{record}/submissions/{submission}/edit-phase-2'),
            'submission-edit-3' => StaPhase3Edit::route('/{record}/submissions/{submission}/edit-phase-3'),
```

In `app/Filament/Resources/Games/Pages/SubmissionView.php` add, first in the `getHeaderActions()` array:

```php
            Action::make('edit')->label('Edit as STA')
                ->visible(fn () => auth()->user()->can(StaPermission::EditSubmissions->value) && in_array($this->submission->phase, [1, 2, 3], true))
                ->url(fn () => GameResource::getUrl('submission-edit-'.$this->submission->phase, ['record' => $this->record, 'submission' => $this->submission])),
```

- [ ] **Step 6: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/StaSubmissionEditTest.php tests/Feature/Phase1EntryTest.php tests/Feature/Phase2EntryTest.php tests/Feature/Phase3EntryTest.php`, then `$PHP vendor/bin/pest`.
Expected: all pass.

- [ ] **Step 7: Commit**

```bash
$PHP vendor/bin/pint
git add app/Filament/Resources/Games/Pages/Concerns/StaEditsSubmission.php app/Filament/Resources/Games/Pages/StaPhase1Edit.php app/Filament/Resources/Games/Pages/StaPhase2Edit.php app/Filament/Resources/Games/Pages/StaPhase3Edit.php app/Filament/Resources/Games/GameResource.php app/Filament/Resources/Games/Pages/SubmissionView.php tests/Feature/StaSubmissionEditTest.php
git commit -m "feat: STA edits of any submission, audited, with PDF regeneration

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Spec coverage (Milestone 3)

| Spec item | Task |
|---|---|
| `entries` (athletes per game event, officials per game sport), `comments` | 1 |
| Per game travel insurance company, policy no, valid until, certificate, vaccinations (spec 13) | 1, 4 |
| Insurance policy no encrypted, not logged, hidden in `/admin` without `view_medical` | 1, 4, 6 |
| Audit log on submissions, entries, number_entries, interest_answers (+ details) | 1, 6 |
| Assign athletes to events with gender match; officials to sports | 2, 4 |
| Phase 2 number shown per event, flagged not blocked when exceeded | 2, 4 |
| Warnings: expiry before games end, missing scans, age range, missing sizes; age shown | 2, 4 |
| Long List submission blocked for incomplete people (spec 13) | 2, 4 |
| Hidden sports (Phase 1 "no") stay hidden | 2, 4 |
| Long List PDF on `submitted` (columns, footer, logos, reference) | 5 |
| Association and STA download the PDF | 5 (portal), dashboard + 6 (admin) |
| Submission view: read, comments, audit trail, PDF download | 6, 7 |
| Comments email the association's users | 7 |
| STA edits any submission at any status (audited) | 8 |
| Optimistic locking on Phase 3 saves and STA saves | 4, 8 |
| Tenant isolation (portal 404, association-filtered people) | 2, 4 |
| Phase 4, reports, Excel exports | Out of scope (Milestone 4) |
