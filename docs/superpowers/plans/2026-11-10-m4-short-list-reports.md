# Milestone 4 – Phase 4 Short List and Reports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Associations shorten their submitted Long List into a Phase 4 Short List in the portal, see per-sport limit warnings live, give a justification when over a limit, submit it through the existing flow and get a Short List PDF; STA reads and edits Phase 4 submissions like the others, and STA users with `view_reports` see eight game reports on one admin page and export each to Excel.

**Architecture:**
- **Data:** no migration. The Phase 4 list reuses `entries` (same shape as Phase 3) and the existing `submissions.justification` column.
- **Rules:** one new service, `ShortListChecks`, holds the Phase 4 preload, the "only people from the submitted Long List" hard error, and the limit counting. `Submission::for()` calls the preload when it creates a Phase 4 row; `SubmissionWorkflow` calls the hard errors and the justification rule on submit and approve.
- **Screens:** a new `EditsPhase4` trait (same pattern as `EditsPhase3`) backs the portal `Phase4Entry` page and the STA `StaPhase4Edit` page with one Blade view. The Phase 3 entry sync moves into `LongListChecks::sync()` so both phases share it.
- **PDF:** `GenerateLongListPdf` gets three overridable constants; `GenerateShortListPdf` is a three-line subclass.
- **Reports:** one service, `App\Services\Reports\GameReports`, one public method per report returning `['headings' => …, 'rows' => …]`; one admin page, `GameReports`, previews a report and streams it as `.xlsx` written with OpenSpout.

**Tech Stack:** PHP 8.5 (Herd `php85`), Laravel 12, Filament 4.16, Livewire 3, Pest 4, `barryvdh/laravel-dompdf`, `openspout/openspout` 4.32 (already installed, no new package), spatie/laravel-activitylog, spatie/laravel-permission.

**Spec:** `docs/superpowers/specs/2026-11-04-sports-entry-system-design.md`.
- Sections 4 (Workflow: `entries` for Phases 3 and 4, `submissions.justification`), 5 (Phase 4 — Short List, Submit flow), 6 (`/admin` reports and Excel exports), 9 (Short List over limit is a warning; over-limit requires justification), 11 (Phase 4 copy and justification tests), 12 (Milestone 4) and 13 ("Sensitive data").

## Global Constraints

- **PHP:** always run `/c/Users/ababy/.config/herd/bin/php85/php.exe` (written `$PHP` below), and Pest as `$PHP vendor/bin/pest`. Format with `$PHP vendor/bin/pint` before each commit.
- **Time zone:** the app runs in `Asia/Bahrain`. Dates are stored, compared and shown in Bahrain time; no code converts time zones.
- **Association phase gating (unchanged):** an association can edit only while the phase is enabled, its status is `open`, now ≤ `deadline_override` (if set) else the phase `deadline`, and the submission is `draft`. `PhaseGate::denialReason()` is the single source.
- **Submit flow (unchanged):** editor submit → `awaiting_assoc_admin`; association admin approves → `submitted`; association admin submitting directly → `submitted`. `reference_no` = `{ASSOC CODE}-G{game id, 3 digits}-P{phase}-R{revision}`.
- **Visibility rule (unchanged):** the sports and events an association may enter are `LongListChecks::visible()` (= `Game::phase2SportsFor($association)`).
- **Phase 4 data:** the Short List is the Phase 4 submission's `entries`, same shape as Phase 3 (athletes: `game_event_id` + its `game_sport_id`; officials: `game_event_id = null` + `game_sport_id`). The single `submissions.justification` text (max 2000 characters) is saved together with the list.
- **Phase 4 preload:** when the association's Phase 4 submission row is first created (`Submission::for()`, `wasRecentlyCreated`), and only then, its entries are copied from the association's Phase 3 submission if that one is `submitted`, inside the same DB transaction as the creation. Nothing is ever re-copied later. If Phase 3 is not `submitted`, the Phase 4 page shows "Submit the Long List first." and nothing can be ticked.
- **Short List page:** per visible sport, each person on the current submitted Long List is shown with a tick per Long List slot (event for athletes, sport for officials); ticked = on the Short List. The association unticks to shorten.
- **Short List hard errors (block save, submit and approve):** a person in a slot where that person is not on the association's current submitted Long List (which also covers sports and events that are not visible); and on submit/approve, a Phase 3 that is not `submitted` ("Submit the Long List first."). Saved entries that are no longer on the Long List are not loaded into the page, so the next save drops them.
- **Short List limits (spec 5 Phase 4) — warnings, never block save:** per visible sport, distinct athletes by **person** gender: male ≤ `p4_max_men`, female ≤ `p4_max_women`; distinct officials ≤ `p4_max_officials`; `null` = no limit. Over-limit lines are shown live as "Judo: 2 men (limit 1)". When any limit is exceeded, a non-blank `justification` is required to submit and to approve ("The Short List is over a limit: give a justification before submitting.").
- **STA edits (extends M3):** `StaPhase4Edit` follows `StaPhase1–3Edit`: `EditSubmissions` + `ViewSubmissions`, any status, after the deadline, only a disabled phase stops it, never changes the status, uses the `lock_version` claim, keeps the Short List hard errors, may edit the justification. After an STA save of a `submitted` Phase 4, the Short List PDF is regenerated under the same `reference_no`.
- **Optimistic locking (unchanged):** every content write claims the loaded `lock_version` first inside its DB transaction via `HandlesSubmission::claimOrHalt()`.
- **PDF:** dompdf, A4 landscape, same layout and columns as the Long List PDF (spec: "layout from the Phase 3 sample"), title "Short List", plus the justification text when present. Stored on `config('filesystems.private')` at `submissions/{reference_no}/short-list.pdf` with `pdf_paths` key `short_list` (Phase 3 keeps `long-list.pdf` / `long_list`). Queued, `tries = 3`, `backoff = 60`.
- **Reports (spec 6):** permission `StaPermission::ViewReports` (Super Admin passes via `Gate::before`). Only `submitted` submissions are included; the page says so. Person-based reports other than Long List / Short List (expiring documents, clothing) read each association's Short List if its Phase 4 is `submitted`, else its Long List if its Phase 3 is `submitted`. Reports never contain medical or health data, passport numbers or CPR numbers. Every query is scoped by `game_id` and eager-loaded (no query per row).
- **Excel:** written with OpenSpout (`OpenSpout\Writer\XLSX\Writer`, `OpenSpout\Common\Entity\Row::fromValues`), one sheet, headings in row 1, streamed with `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, filename `{Str::slug(game name)}-{Str::kebab(report key)}.xlsx` (e.g. `asian-indoor-games-long-list.xlsx`).
- **Livewire route binding (unchanged):** pages with `{submission}` take `int|string|Submission $submission` in `mount`.
- **Tenant isolation (unchanged):** `Person::withoutGlobalScopes()` is only ever used together with `where('association_id', …)` of the submission. Portal pages abort with 404 for a game the tenant is not invited to.
- **Commits:** end every commit message with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Never commit `.superpowers/` or `~$*`.

---

## File Structure

```
app/Services/Phases/ShortListChecks.php                      # NEW: preload, Long List slots, hard errors, limit counting
app/Models/Submission.php                                    # for(): transaction + Phase 4 preload
app/Services/Phases/SubmissionWorkflow.php                   # phase 4 submit/approve rules; Short List PDF dispatch
app/Services/Phases/LongListChecks.php                       # + sync() (moved from EditsPhase3)
app/Filament/Shared/Phases/EditsPhase3.php                   # uses LongListChecks::sync()
app/Filament/Shared/Phases/EditsPhase4.php                   # NEW: Phase 4 state/load/save
app/Filament/Portal/Pages/Phase4Entry.php                    # NEW: /games/{game}/phase-4
resources/views/filament/portal/pages/phase4-entry.blade.php # NEW: shared by portal and STA
app/Filament/Portal/Pages/Games.php                          # link phase 4
app/Jobs/GenerateLongListPdf.php + resources/views/pdf/long-list.blade.php  # KEY/FILE/TITLE constants, title + justification
app/Jobs/GenerateShortListPdf.php                            # NEW: subclass
app/Filament/Resources/Games/Pages/StaPhase4Edit.php         # NEW
app/Filament/Resources/Games/Pages/Concerns/StaEditsSubmission.php  # phase 4 PDF regeneration
app/Filament/Resources/Games/Pages/SubmissionView.php (+ view)      # phase 4 content, edit for phase 4
app/Services/Reports/GameReports.php                         # NEW: all reports
app/Filament/Resources/Games/Pages/GameReports.php + resources/views/filament/resources/games/pages/game-reports.blade.php  # NEW
app/Filament/Resources/Games/GameResource.php                # routes submission-edit-4, reports; canAccess + ViewReports
app/Filament/Resources/Games/Pages/GameDashboard.php         # "Reports" header action
app/Filament/Resources/Games/Tables/GamesTable.php           # "Reports" row action
tests/Feature/{ShortListChecksTest, Phase4EntryTest, ShortListPdfTest, ShortListAdminTest, GameReportsTest}.php
```

---

### Task 1: Short List rules, Phase 4 preload and the justification rule

**Files:**
- Create: `app/Services/Phases/ShortListChecks.php`
- Modify: `app/Models/Submission.php` (`for()`), `app/Services/Phases/SubmissionWorkflow.php` (`assertWithinLimits()`)
- Test: `tests/Feature/ShortListChecksTest.php`

**Interfaces:**
- Consumes: `LongListChecks::visible(Submission): array{0: Collection<int, GameSport>, 1: Collection<int, GameEvent>}` and `LongListChecks::selection(Submission): array{0: array<int, list<string>>, 1: array<int, list<string>>}` (existing); `Submission::for()`.
- Produces (`App\Services\Phases\ShortListChecks`, resolved with `app()`):
  - constants `LONG_LIST_FIRST = 'Submit the Long List first.'`, `NOT_ON_LONG_LIST = 'Choose only people on your submitted Long List.'`, `JUSTIFICATION_REQUIRED = 'The Short List is over a limit: give a justification before submitting.'`, `KINDS = ['men' => 'men', 'women' => 'women', 'officials' => 'team officials']`.
  - `longList(Submission $s): ?Submission` — the association's Phase 3 submission when `submitted`, else null.
  - `preload(Submission $s): void` — copies the submitted Long List entries into `$s`.
  - `allowed(Submission $s): array{0: array<int, list<string>>, 1: array<int, list<string>>}` — Long List slots `[game_event_id => person ids]`, `[game_sport_id => person ids]` (visible only); `[[], []]` without a submitted Long List.
  - `selection(Submission $s): array{0, 1}` — the saved Short List restricted to `allowed()`, with every allowed slot present (possibly `[]`).
  - `errors(Submission $s, array $athletes, array $officials): array<string, string>` keyed `athletes.{game_event_id}` / `officials.{game_sport_id}`.
  - `submitErrors(Submission $s): list<string>`.
  - `overLimits(Submission $s, ?array $selection = null): list<array{sport: string, kind: 'men'|'women'|'officials', count: int, max: int}>` — `$selection` is `[$athletes, $officials]`; null = the saved entries.
  - `static over(iterable $sports, array $athletes, array $officials, array $genders): list<…same shape…>` — pure counting; `$sports` GameSports with `sport` and `gameEvents` loaded, `$genders` person id ⇒ `PersonGender`.
  - `static describe(array $over): string` — e.g. `Judo: 2 men (limit 1)`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ShortListChecksTest.php`:

```php
<?php

use App\Enums\EventGender;
use App\Enums\PersonGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\Phases\ShortListChecks;
use App\Services\Phases\SubmissionWorkflow;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id, 'p4_max_men' => 1, 'p4_max_officials' => 0,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $event = fn (string $name, EventGender $g) => GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => $name, 'gender' => $g])->id]);
    $this->m60 = $event('Men -60kg', EventGender::Men);
    $this->w52 = $event('Women -52kg', EventGender::Women);
    $this->mixed = $event('Mixed team', EventGender::Mixed);
    $this->ali = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->omar = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Omar', 'last_name' => 'Saleh']);
    $this->sara = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Sara', 'last_name' => 'Ali', 'gender' => PersonGender::Female]);
    $this->coach = Person::factory()->official()->create(['association_id' => $this->assoc->id, 'first_name' => 'Coach', 'last_name' => 'Karim']);
    $this->p3 = Submission::for($this->game, $this->assoc, 3);
    foreach ([[$this->ali, $this->m60], [$this->omar, $this->m60], [$this->sara, $this->w52]] as [$p, $ge]) {
        $this->p3->entries()->create(['person_id' => $p->id, 'game_event_id' => $ge->id, 'game_sport_id' => $this->judo->id]);
    }
    $this->p3->entries()->create(['person_id' => $this->coach->id, 'game_event_id' => null, 'game_sport_id' => $this->judo->id]);
    $this->p3->update(['status' => SubmissionStatus::Submitted]);
});

it('copies the submitted Long List into a new Phase 4 submission, once', function () {
    $p4 = Submission::for($this->game, $this->assoc, 4);

    expect($p4->entries()->count())->toBe(4)
        ->and($p4->entries()->whereNull('game_event_id')->sole()->person_id)->toBe($this->coach->id);

    $p4->entries()->where('person_id', $this->omar->id)->delete();
    expect(Submission::for($this->game, $this->assoc, 4)->entries()->count())->toBe(3)
        ->and($this->p3->entries()->count())->toBe(4);
});

it('copies nothing from a Long List that is not submitted, and never copies later', function () {
    $this->p3->update(['status' => SubmissionStatus::Draft]);
    $p4 = Submission::for($this->game, $this->assoc, 4);

    expect($p4->entries()->count())->toBe(0)
        ->and(app(ShortListChecks::class)->submitErrors($p4))->toBe([ShortListChecks::LONG_LIST_FIRST])
        ->and(app(ShortListChecks::class)->allowed($p4))->toBe([[], []]);

    $this->p3->update(['status' => SubmissionStatus::Submitted]);
    expect(Submission::for($this->game, $this->assoc, 4)->entries()->count())->toBe(0);
});

it('allows only people in their own Long List slot', function () {
    $p4 = Submission::for($this->game, $this->assoc, 4);
    $checks = app(ShortListChecks::class);

    expect($checks->errors($p4, [$this->m60->id => [(string) $this->ali->id]], [$this->judo->id => [(string) $this->coach->id]]))->toBe([])
        // Sara is on the Long List, but in Women -52kg, not Men -60kg.
        ->and($checks->errors($p4, [$this->m60->id => [(string) $this->ali->id, (string) $this->sara->id]], []))
        ->toBe(["athletes.{$this->m60->id}" => ShortListChecks::NOT_ON_LONG_LIST])
        ->and($checks->errors($p4, [$this->mixed->id => [(string) $this->ali->id]], [$this->judo->id => [(string) $this->ali->id]]))
        ->toBe(["athletes.{$this->mixed->id}" => ShortListChecks::NOT_ON_LONG_LIST, "officials.{$this->judo->id}" => ShortListChecks::NOT_ON_LONG_LIST]);
});

it('loads only the saved entries that are still on the Long List, with every Long List slot present', function () {
    $p4 = Submission::for($this->game, $this->assoc, 4);
    $this->p3->entries()->where('person_id', $this->omar->id)->delete();
    $p4->entries()->where('person_id', $this->sara->id)->delete();

    expect(app(ShortListChecks::class)->selection($p4))->toBe([
        [$this->m60->id => [(string) $this->ali->id], $this->w52->id => []],
        [$this->judo->id => [(string) $this->coach->id]],
    ]);
});

it('counts distinct athletes by gender and officials per sport against the Phase 4 limits', function () {
    $p4 = Submission::for($this->game, $this->assoc, 4);
    $selection = [
        [$this->m60->id => [(string) $this->ali->id], $this->mixed->id => [(string) $this->ali->id, (string) $this->omar->id, (string) $this->sara->id],
            $this->w52->id => [(string) $this->sara->id]],
        [$this->judo->id => [(string) $this->coach->id]],
    ];

    $over = app(ShortListChecks::class)->overLimits($p4, $selection);
    expect($over)->toBe([
        ['sport' => 'Judo', 'kind' => 'men', 'count' => 2, 'max' => 1],
        ['sport' => 'Judo', 'kind' => 'officials', 'count' => 1, 'max' => 0],
    ])->and(ShortListChecks::describe($over[0]))->toBe('Judo: 2 men (limit 1)')
        ->and(ShortListChecks::describe($over[1]))->toBe('Judo: 1 team officials (limit 0)');

    $this->judo->update(['p4_max_men' => 5, 'p4_max_officials' => null]);
    expect(app(ShortListChecks::class)->overLimits($p4, $selection))->toBe([]);
});

it('requires a justification to submit and to approve when over a limit', function () {
    Bus::fake();
    Notification::fake();
    $this->game->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $p4 = Submission::for($this->game, $this->assoc, 4); // preloaded: 2 men > 1, 1 official > 0
    $editor = User::factory()->assocEditor($this->assoc)->create();
    $admin = User::factory()->assocAdmin($this->assoc)->create();
    $flow = app(SubmissionWorkflow::class);

    expect(fn () => $flow->submit($p4->fresh(), $editor))->toThrow(ValidationException::class, ShortListChecks::JUSTIFICATION_REQUIRED);

    $p4->update(['justification' => 'Both can medal']);
    expect($flow->submit($p4->fresh(), $editor)->status)->toBe(SubmissionStatus::AwaitingAssocAdmin);

    $p4->update(['justification' => null]);
    expect(fn () => $flow->approve($p4->fresh(), $admin))->toThrow(ValidationException::class, ShortListChecks::JUSTIFICATION_REQUIRED);

    $p4->update(['justification' => 'Both can medal']);
    expect($flow->approve($p4->fresh(), $admin)->status)->toBe(SubmissionStatus::Submitted);
});

it('blocks submitting a Short List while the Long List is not submitted', function () {
    $this->game->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $p4 = Submission::for($this->game, $this->assoc, 4);
    $this->p3->update(['status' => SubmissionStatus::Draft]);

    expect(fn () => app(SubmissionWorkflow::class)->submit($p4->fresh(), User::factory()->assocAdmin($this->assoc)->create()))
        ->toThrow(ValidationException::class, ShortListChecks::LONG_LIST_FIRST);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/ShortListChecksTest.php`
Expected: FAIL with `Class "App\Services\Phases\ShortListChecks" not found`.

- [ ] **Step 3: Write `ShortListChecks`**

`app/Services/Phases/ShortListChecks.php`:

```php
<?php

namespace App\Services\Phases;

use App\Enums\PersonGender;
use App\Enums\SubmissionStatus;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Submission;

/** Phase 4 (Short List) rules: people come only from the submitted Long List; limits warn, and then need a justification. */
final class ShortListChecks
{
    public const LONG_LIST_FIRST = 'Submit the Long List first.';

    public const NOT_ON_LONG_LIST = 'Choose only people on your submitted Long List.';

    public const JUSTIFICATION_REQUIRED = 'The Short List is over a limit: give a justification before submitting.';

    public const KINDS = ['men' => 'men', 'women' => 'women', 'officials' => 'team officials'];

    public function __construct(private readonly LongListChecks $long) {}

    public function longList(Submission $s): ?Submission
    {
        $p3 = Submission::withoutGlobalScope('association')
            ->where(['game_id' => $s->game_id, 'association_id' => $s->association_id, 'phase' => 3])->first();

        return $p3?->status === SubmissionStatus::Submitted ? $p3 : null;
    }

    /** Called once, by Submission::for(), in the transaction that creates the Phase 4 row. */
    public function preload(Submission $s): void
    {
        foreach ($this->longList($s)?->entries()->orderBy('id')->get() ?? [] as $e) {
            $s->entries()->create($e->only(['person_id', 'game_event_id', 'game_sport_id']));
        }
    }

    /** @return array{0: array<int, list<string>>, 1: array<int, list<string>>} the Long List slots open to the association */
    public function allowed(Submission $s): array
    {
        $p3 = $this->longList($s);

        return $p3 ? $this->long->selection($p3) : [[], []];
    }

    /** The saved Short List, restricted to the Long List; every Long List slot is present so checkbox arrays bind. */
    public function selection(Submission $s): array
    {
        [$allowedAthletes, $allowedOfficials] = $this->allowed($s);
        [$athletes, $officials] = $this->long->selection($s);
        $pick = fn (array $allowed, array $saved) => collect($allowed)
            ->map(fn (array $ids, int $slot) => array_values(array_intersect($saved[$slot] ?? [], $ids)))->all();

        return [$pick($allowedAthletes, $athletes), $pick($allowedOfficials, $officials)];
    }

    /**
     * @param  array<int|string, mixed>  $athletes  game_event_id => person ids
     * @param  array<int|string, mixed>  $officials  game_sport_id => person ids
     * @return array<string, string>
     */
    public function errors(Submission $s, array $athletes, array $officials): array
    {
        [$allowedAthletes, $allowedOfficials] = $this->allowed($s);
        $errors = [];
        foreach (['athletes' => [$athletes, $allowedAthletes], 'officials' => [$officials, $allowedOfficials]] as $kind => [$picked, $allowed]) {
            foreach ($picked as $slot => $ids) {
                if (array_diff(array_map('strval', (array) $ids), $allowed[(int) $slot] ?? [])) {
                    $errors["$kind.$slot"] = self::NOT_ON_LONG_LIST;
                }
            }
        }

        return $errors;
    }

    /** @return list<string> */
    public function submitErrors(Submission $s): array
    {
        if (! $this->longList($s)) {
            return [self::LONG_LIST_FIRST];
        }

        return array_values($this->errors($s, ...$this->long->selection($s)));
    }

    /** @return list<array{sport: string, kind: string, count: int, max: int}> */
    public function overLimits(Submission $s, ?array $selection = null): array
    {
        [$athletes, $officials] = $selection ?? $this->long->selection($s);
        $ids = collect([...array_values($athletes), ...array_values($officials)])->flatten()->map(fn ($id) => (int) $id)->unique()->all();
        $genders = Person::withoutGlobalScopes()->where('association_id', $s->association_id)->whereKey($ids)->pluck('gender', 'id')->all();

        return self::over($this->long->visible($s)[0], $athletes, $officials, $genders);
    }

    /**
     * @param  iterable<GameSport>  $sports  with sport and gameEvents loaded
     * @param  array<int, PersonGender>  $genders  person id => gender
     * @return list<array{sport: string, kind: string, count: int, max: int}>
     */
    public static function over(iterable $sports, array $athletes, array $officials, array $genders): array
    {
        $out = [];
        foreach ($sports as $gs) {
            $people = $gs->gameEvents->flatMap(fn ($ge) => (array) ($athletes[$ge->id] ?? []))->map(fn ($id) => (int) $id)->unique();
            $count = [
                'men' => $people->filter(fn ($id) => ($genders[$id] ?? null) === PersonGender::Male)->count(),
                'women' => $people->filter(fn ($id) => ($genders[$id] ?? null) === PersonGender::Female)->count(),
                'officials' => collect((array) ($officials[$gs->id] ?? []))->map(fn ($id) => (int) $id)->unique()->count(),
            ];
            foreach (['men' => $gs->p4_max_men, 'women' => $gs->p4_max_women, 'officials' => $gs->p4_max_officials] as $kind => $max) {
                if ($max !== null && $count[$kind] > (int) $max) {
                    $out[] = ['sport' => $gs->sport->name, 'kind' => $kind, 'count' => $count[$kind], 'max' => (int) $max];
                }
            }
        }

        return $out;
    }

    public static function describe(array $over): string
    {
        return "{$over['sport']}: {$over['count']} ".self::KINDS[$over['kind']]." (limit {$over['max']})";
    }
}
```

- [ ] **Step 4: Preload in `Submission::for()`**

In `app/Models/Submission.php` add `use App\Services\Phases\ShortListChecks;` and `use Illuminate\Support\Facades\DB;`, and replace the `return static::withoutGlobalScope('association')->firstOrCreate(...);` at the end of `for()` with:

```php
        return DB::transaction(function () use ($game, $association, $phase) {
            $s = static::withoutGlobalScope('association')->firstOrCreate(
                ['game_id' => $game->id, 'association_id' => $association->id, 'phase' => $phase],
                ['status' => SubmissionStatus::Draft],
            );
            // Spec 5 Phase 4: preloaded with a copy of the Long List, once, when the row is created.
            if ($s->wasRecentlyCreated && $phase === 4) {
                app(ShortListChecks::class)->preload($s);
            }

            return $s;
        });
```

- [ ] **Step 5: Phase 4 rules in `SubmissionWorkflow::assertWithinLimits()`**

In `app/Services/Phases/SubmissionWorkflow.php`, at the top of `assertWithinLimits()` (before `if ($s->phase === 3)`), add:

```php
        if ($s->phase === 4) {
            $checks = app(ShortListChecks::class);
            if ($errors = $checks->submitErrors($s)) {
                $this->fail(implode(' ', $errors));
            }
            if ($checks->overLimits($s) && blank($s->justification)) {
                $this->fail(ShortListChecks::JUSTIFICATION_REQUIRED);
            }

            return;
        }
```

(`ShortListChecks` is in the same namespace; no import needed.)

- [ ] **Step 6: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/ShortListChecksTest.php`
Expected: PASS (7 tests).
Run: `$PHP vendor/bin/pest`
Expected: PASS (no regressions; `Submission::for()` keeps its behaviour for phases 1–3).

- [ ] **Step 7: Commit**

```bash
$PHP vendor/bin/pint
git add app/Services/Phases/ShortListChecks.php app/Models/Submission.php app/Services/Phases/SubmissionWorkflow.php tests/Feature/ShortListChecksTest.php
git commit -m "feat: Short List rules, Phase 4 preload from the submitted Long List, justification required when over a limit

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Portal Phase 4 (Short List) page

**Files:**
- Create: `app/Filament/Shared/Phases/EditsPhase4.php`, `app/Filament/Portal/Pages/Phase4Entry.php`, `resources/views/filament/portal/pages/phase4-entry.blade.php`
- Modify: `app/Services/Phases/LongListChecks.php` (add `sync()`), `app/Filament/Shared/Phases/EditsPhase3.php` (use it), `app/Filament/Portal/Pages/Games.php` (link)
- Test: `tests/Feature/Phase4EntryTest.php`

**Interfaces:**
- Consumes: `ShortListChecks` (Task 1): `longList`, `allowed`, `selection`, `errors`, `overLimits`, `describe`; `HandlesSubmission` (`mountPortal`, `guardEditable`, `claimOrHalt`, `refreshLoadedAt`, `submitAction`, `approveAction`, `returnAction`); `LongListChecks::rows()`; partials `filament.shared.long-list-people`, `filament.partials.comments`.
- Produces:
  - `LongListChecks::sync(Submission $s, array $athletes, array $officials): void` (moved from `EditsPhase3::syncEntries`, unchanged behaviour).
  - Trait `App\Filament\Shared\Phases\EditsPhase4`: public `array $athletes`, `array $officials`, `?string $justification`; methods `loadPhase()`, `longListSubmitted(): bool`, `longListSlots(): array`, `longListPeople(): Collection<int, Person>`, `visibleSports(): Collection`, `overLimits(): array`, `listRows(): Collection`, `save(): void`.
  - Page `Phase4Entry` at portal route `/games/{game}/phase-4`, view `filament.portal.pages.phase4-entry` (Task 4 reuses the view for STA).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Phase4EntryTest.php`:

```php
<?php

use App\Enums\EventGender;
use App\Enums\PersonGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Games;
use App\Filament\Portal\Pages\Phase4Entry;
use App\Models\Association;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\Phases\ShortListChecks;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    Bus::fake();
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id, 'p4_max_men' => 1,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->ali = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->omar = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Omar', 'last_name' => 'Saleh']);
    $this->yusuf = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Yusuf', 'last_name' => 'Adel']);
    $this->coach = Person::factory()->official()->create(['association_id' => $this->assoc->id, 'first_name' => 'Coach', 'last_name' => 'Karim']);
    $this->p3 = Submission::for($this->game, $this->assoc, 3);
    foreach ([$this->ali, $this->omar] as $p) {
        $this->p3->entries()->create(['person_id' => $p->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    }
    $this->p3->entries()->create(['person_id' => $this->coach->id, 'game_event_id' => null, 'game_sport_id' => $this->judo->id]);
    $this->p3->update(['status' => SubmissionStatus::Submitted]);
    $this->admin = User::factory()->assocAdmin($this->assoc)->create();
    actingInPortal($this->admin);
});

function phase4(): Submission
{
    return Submission::where('phase', 4)->sole();
}

it('preloads the submitted Long List with everyone ticked', function () {
    livewire(Phase4Entry::class, ['game' => $this->game->id])
        ->assertSet("athletes.{$this->m60->id}", [(string) $this->ali->id, (string) $this->omar->id])
        ->assertSet("officials.{$this->judo->id}", [(string) $this->coach->id])
        ->assertSee('Men -60kg')->assertSee('Ali Hasan')->assertSee('Coach Karim')->assertDontSee('Yusuf Adel');

    expect(phase4()->entries()->count())->toBe(3);
});

it('unticks people to shorten the list and leaves the Long List alone', function () {
    livewire(Phase4Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->set("officials.{$this->judo->id}", [])
        ->call('save')->assertHasNoErrors();

    expect(phase4()->entries()->pluck('person_id')->all())->toBe([$this->ali->id])
        ->and($this->p3->entries()->count())->toBe(3);
});

it('blocks a person who is not on the Long List and writes nothing', function () {
    livewire(Phase4Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id, (string) $this->yusuf->id])->call('save')
        ->assertHasErrors(["athletes.{$this->m60->id}"])->assertSee(ShortListChecks::NOT_ON_LONG_LIST);

    expect(phase4()->entries()->where('person_id', $this->yusuf->id)->exists())->toBeFalse()
        ->and(phase4()->entries()->count())->toBe(3);
});

it('drops saved entries that are no longer on the Long List', function () {
    livewire(Phase4Entry::class, ['game' => $this->game->id]); // preloads 3
    Entry::where(['submission_id' => $this->p3->id, 'person_id' => $this->omar->id])->delete();

    livewire(Phase4Entry::class, ['game' => $this->game->id])
        ->assertSet("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save')->assertHasNoErrors();

    expect(phase4()->entries()->where('person_id', $this->omar->id)->exists())->toBeFalse();
});

it('asks for the Long List first when it is not submitted', function () {
    $this->p3->update(['status' => SubmissionStatus::Draft]);

    livewire(Phase4Entry::class, ['game' => $this->game->id])
        ->assertSee('Submit the Long List first.')->assertDontSee('Ali Hasan');
    expect(phase4()->entries()->count())->toBe(0);
});

it('warns live when over a limit and needs a justification to submit', function () {
    $page = livewire(Phase4Entry::class, ['game' => $this->game->id])->assertSee('Judo: 2 men (limit 1)');

    $page->callAction('submit')->assertNotified(ShortListChecks::JUSTIFICATION_REQUIRED);
    expect(phase4()->status)->toBe(SubmissionStatus::Draft);

    $page->set('justification', 'Both can win a medal')->callAction('submit');
    expect(phase4()->status)->toBe(SubmissionStatus::Submitted)
        ->and(phase4()->justification)->toBe('Both can win a medal');
});

it('submits without a justification once within the limits', function () {
    livewire(Phase4Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->assertDontSee('(limit 1)')
        ->callAction('submit');

    expect(phase4()->status)->toBe(SubmissionStatus::Submitted)->and(phase4()->justification)->toBeNull();
});

it('refuses a justification over 2000 characters', function () {
    livewire(Phase4Entry::class, ['game' => $this->game->id])
        ->set('justification', str_repeat('x', 2001))->call('save')->assertHasErrors(['justification']);
    expect(phase4()->justification)->toBeNull();
});

it('refuses a stale save', function () {
    $page = livewire(Phase4Entry::class, ['game' => $this->game->id]);
    $s = phase4();
    $s->claimVersion($s->lock_version);

    $page->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save')
        ->assertNotified('This entry was changed by someone else — reload to see the latest.');
    expect(phase4()->entries()->count())->toBe(3);
});

it('is read-only when the phase is closed', function () {
    $this->game->phase(4)->update(['status' => PhaseStatus::Closed]);

    livewire(Phase4Entry::class, ['game' => $this->game->id])->assertSee('This phase is closed.')
        ->set("athletes.{$this->m60->id}", [])->call('save');
    expect(phase4()->entries()->count())->toBe(3);
});

it('returns 404 for a game the association is not invited to', function () {
    $other = Game::factory()->create();
    $other->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Open]);
    $this->get(Phase4Entry::getUrl(['game' => $other->id], tenant: $this->assoc))->assertNotFound();
});

it('links phase 4 from the games page', function () {
    livewire(Games::class)->assertSeeHtml(Phase4Entry::getUrl(['game' => $this->game->id]));
});
```

Note: `phase4()` is a file-level helper; no other test file may declare a function with that name.

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/Phase4EntryTest.php`
Expected: FAIL with `Class "App\Filament\Portal\Pages\Phase4Entry" not found`.

- [ ] **Step 3: Move the entry sync into `LongListChecks::sync()`**

In `app/Services/Phases/LongListChecks.php` add this public method (the body is `EditsPhase3::syncEntries()` with `$this->submission` → `$s`):

```php
    /**
     * Makes the saved entries equal to the visible selection; entries and travel details outside it are deleted.
     *
     * @param  array<int|string, mixed>  $athletes  game_event_id => person ids
     * @param  array<int|string, mixed>  $officials  game_sport_id => person ids
     */
    public function sync(Submission $s, array $athletes, array $officials): void
    {
        [$sports, $events] = $this->visible($s);
        $want = collect();
        foreach ($athletes as $geId => $ids) {
            if ($ge = $events->get((int) $geId)) {
                foreach ((array) $ids as $id) {
                    $want->push(['person_id' => (int) $id, 'game_event_id' => $ge->id, 'game_sport_id' => $ge->game_sport_id]);
                }
            }
        }
        foreach ($officials as $gsId => $ids) {
            if ($sports->has((int) $gsId)) {
                foreach ((array) $ids as $id) {
                    $want->push(['person_id' => (int) $id, 'game_event_id' => null, 'game_sport_id' => (int) $gsId]);
                }
            }
        }
        $key = fn (array $e) => $e['person_id'].'-'.($e['game_event_id'] ?? 'official').'-'.$e['game_sport_id'];
        $want = $want->keyBy($key);
        $have = $s->entries()->get()->keyBy(fn ($e) => $key($e->only(['person_id', 'game_event_id', 'game_sport_id'])));

        $have->diffKeys($want)->each->delete();
        $want->diffKeys($have)->each(fn (array $row) => $s->entries()->create($row));
        $s->details()->whereNotIn('person_id', $want->pluck('person_id')->unique()->all())->get()->each->delete();
    }
```

In `app/Filament/Shared/Phases/EditsPhase3.php`: delete the private `syncEntries()` method and, inside `save()`, replace `$this->syncEntries();` with:

```php
                app(LongListChecks::class)->sync($this->submission, $this->athletes, $this->officials);
```

Run: `$PHP vendor/bin/pest tests/Feature/Phase3EntryTest.php tests/Feature/StaSubmissionEditTest.php`
Expected: PASS (pure move).

- [ ] **Step 4: Write the trait**

`app/Filament/Shared/Phases/EditsPhase4.php`:

```php
<?php

namespace App\Filament\Shared\Phases;

use App\Models\Association;
use App\Models\GameSport;
use App\Models\Person;
use App\Services\Phases\LongListChecks;
use App\Services\Phases\ShortListChecks;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Phase 4 (Short List) state, checks and save; use together with HandlesSubmission. */
trait EditsPhase4
{
    /** @var array<int|string, mixed> game_event_id => person ids (ticked) */
    public array $athletes = [];

    /** @var array<int|string, mixed> game_sport_id => person ids (ticked) */
    public array $officials = [];

    public ?string $justification = null;

    abstract protected function association(): Association;

    protected function loadPhase(): void
    {
        [$this->athletes, $this->officials] = app(ShortListChecks::class)->selection($this->submission);
        $this->justification = $this->submission->justification;
    }

    public function longListSubmitted(): bool
    {
        return app(ShortListChecks::class)->longList($this->submission) !== null;
    }

    /** @return array{0: array<int, list<string>>, 1: array<int, list<string>>} */
    public function longListSlots(): array
    {
        return app(ShortListChecks::class)->allowed($this->submission);
    }

    /** @return Collection<int, Person> people on the Long List keyed by id, for the tick labels */
    public function longListPeople(): Collection
    {
        [$athletes, $officials] = $this->longListSlots();

        return Person::withoutGlobalScopes()->where('association_id', $this->submission->association_id)
            ->whereKey(collect([...$athletes, ...$officials])->flatten()->unique()->all())->get()->keyBy('id');
    }

    /** @return Collection<int, GameSport> */
    public function visibleSports(): Collection
    {
        return $this->gameModel->phase2SportsFor($this->association());
    }

    /** @return list<array{sport: string, kind: string, count: int, max: int}> for the ticks on screen */
    public function overLimits(): array
    {
        return app(ShortListChecks::class)->overLimits($this->submission, [$this->athletes, $this->officials]);
    }

    public function listRows(): Collection
    {
        return app(LongListChecks::class)->rows($this->submission, $this->athletes, $this->officials);
    }

    public function save(): void
    {
        try {
            $this->guardEditable();
            if (mb_strlen((string) $this->justification) > 2000) {
                throw ValidationException::withMessages(['justification' => 'The justification may not be longer than 2000 characters.']);
            }
            if ($errors = app(ShortListChecks::class)->errors($this->submission, $this->athletes, $this->officials)) {
                throw ValidationException::withMessages($errors);
            }
            DB::transaction(function () {
                $this->claimOrHalt();
                app(LongListChecks::class)->sync($this->submission, $this->athletes, $this->officials);
                $this->submission->update(['justification' => filled($this->justification) ? $this->justification : null]);
            });
        } catch (Halt) {
            return; // notification already sent; loadedVersion unchanged so submit knows nothing was saved
        }
        $this->refreshLoadedAt();
        Notification::make()->success()->title('Saved')->send();
    }
}
```

- [ ] **Step 5: Write the page**

`app/Filament/Portal/Pages/Phase4Entry.php`:

```php
<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Portal\Concerns\HandlesSubmission;
use App\Filament\Shared\Phases\EditsPhase4;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class Phase4Entry extends Page
{
    use EditsPhase4, HandlesSubmission;

    protected string $view = 'filament.portal.pages.phase4-entry';

    public static function getRoutePath(Panel $panel): string
    {
        return '/games/{game}/phase-4';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->gameModel->name.' – Phase 4 · Short List';
    }

    public function mount(int|string $game): void
    {
        $this->mountPortal($game, 4);
    }

    protected function getHeaderActions(): array
    {
        return [$this->submitAction(), $this->approveAction(), $this->returnAction()];
    }
}
```

- [ ] **Step 6: Write the view**

`resources/views/filament/portal/pages/phase4-entry.blade.php`:

```blade
<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Deadline: {{ $this->submission->gamePhase()->effectiveDeadline($this->submission)?->format('j M Y H:i') ?? 'none' }}
    </p>

    @if ($readOnlyReason)
        <div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">{{ $readOnlyReason }}</div>
    @endif

    @if (! $this->longListSubmitted())
        <div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">Submit the Long List first.</div>
    @else
        @php
            [$slotAthletes, $slotOfficials] = $this->longListSlots();
            $people = $this->longListPeople();
            $over = $this->overLimits();
        @endphp

        @if ($over)
            <div class="rounded-lg bg-warning-50 p-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                <p class="font-medium">Over the Short List limit — a justification is required before submitting:</p>
                <ul class="list-disc pl-5">
                    @foreach ($over as $o)<li>{{ \App\Services\Phases\ShortListChecks::describe($o) }}</li>@endforeach
                </ul>
            </div>
        @endif

        @foreach ($this->visibleSports() as $gs)
            @php $events = $gs->gameEvents->filter(fn ($ge) => ! empty($slotAthletes[$ge->id])); @endphp
            @continue($events->isEmpty() && empty($slotOfficials[$gs->id]))
            <x-filament::section :heading="$gs->sport->name"
                :description="'Limits: men '.($gs->p4_max_men ?? '–').' · women '.($gs->p4_max_women ?? '–').' · team officials '.($gs->p4_max_officials ?? '–')">
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($events as $ge)
                        <fieldset wire:key="event-{{ $ge->id }}">
                            <legend class="text-sm font-medium">{{ $ge->event->name }} ({{ $ge->event->gender->label() }})</legend>
                            @foreach ($slotAthletes[$ge->id] as $id)
                                @php $p = $people->get((int) $id); @endphp
                                @continue(! $p)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" value="{{ $id }}" wire:model.live="athletes.{{ $ge->id }}" @disabled($readOnlyReason)>
                                    {{ $p->fullName() }} <span class="text-xs text-gray-500">{{ $p->code }}</span>
                                </label>
                            @endforeach
                            @error('athletes.'.$ge->id)<p class="text-xs text-danger-600">{{ $message }}</p>@enderror
                        </fieldset>
                    @endforeach
                    @if (! empty($slotOfficials[$gs->id]))
                        <fieldset wire:key="officials-{{ $gs->id }}">
                            <legend class="text-sm font-medium">Team officials</legend>
                            @foreach ($slotOfficials[$gs->id] as $id)
                                @php $p = $people->get((int) $id); @endphp
                                @continue(! $p)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" value="{{ $id }}" wire:model.live="officials.{{ $gs->id }}" @disabled($readOnlyReason)>
                                    {{ $p->fullName() }} – {{ $p->function }} <span class="text-xs text-gray-500">{{ $p->code }}</span>
                                </label>
                            @endforeach
                            @error('officials.'.$gs->id)<p class="text-xs text-danger-600">{{ $message }}</p>@enderror
                        </fieldset>
                    @endif
                </div>
            </x-filament::section>
        @endforeach

        <x-filament::section heading="Justification">
            <p class="text-xs text-gray-500">Required before submitting when the Short List is over a limit (at most 2000 characters).</p>
            <textarea wire:model="justification" rows="4" maxlength="2000" class="w-full rounded border-gray-300 text-sm dark:bg-white/5" @disabled($readOnlyReason)></textarea>
            @error('justification')<p class="text-xs text-danger-600">{{ $message }}</p>@enderror
        </x-filament::section>

        <x-filament::section heading="People on the Short List">
            @include('filament.shared.long-list-people', ['rows' => $this->listRows()])
        </x-filament::section>

        @unless ($readOnlyReason)
            <div><x-filament::button wire:click="save">Save</x-filament::button></div>
        @endunless
    @endif

    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
```

- [ ] **Step 7: Link phase 4 on the Games page**

In `app/Filament/Portal/Pages/Games.php`, in the `match ($p->phase)`, add after `3 => Phase3Entry::class,`:

```php
                    4 => Phase4Entry::class,
```

- [ ] **Step 8: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/Phase4EntryTest.php`
Expected: PASS (12 tests).
Run: `$PHP vendor/bin/pest`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
$PHP vendor/bin/pint
git add app/Services/Phases/LongListChecks.php app/Filament/Shared/Phases/EditsPhase3.php app/Filament/Shared/Phases/EditsPhase4.php app/Filament/Portal/Pages/Phase4Entry.php app/Filament/Portal/Pages/Games.php resources/views/filament/portal/pages/phase4-entry.blade.php tests/Feature/Phase4EntryTest.php
git commit -m "feat: portal Phase 4 Short List page with live limit warnings and justification

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Short List PDF

**Files:**
- Create: `app/Jobs/GenerateShortListPdf.php`
- Modify: `app/Jobs/GenerateLongListPdf.php`, `resources/views/pdf/long-list.blade.php`, `app/Services/Phases/SubmissionWorkflow.php` (`finalise()`), `resources/views/filament/portal/pages/phase4-entry.blade.php` (download link)
- Test: `tests/Feature/ShortListPdfTest.php`

**Interfaces:**
- Consumes: `GenerateLongListPdf::handle()` (existing), `SubmissionWorkflow::finalise()`, `Phase4Entry` (Task 2).
- Produces:
  - `GenerateLongListPdf` constants `protected const KEY = 'long_list'`, `FILE = 'long-list.pdf'`, `TITLE = 'Long List'`; view data adds `title` and `justification`.
  - `App\Jobs\GenerateShortListPdf extends GenerateLongListPdf` (`KEY = 'short_list'`, `FILE = 'short-list.pdf'`, `TITLE = 'Short List'`); constructor `(Submission $submission)`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ShortListPdfTest.php`:

```php
<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Phase4Entry;
use App\Jobs\GenerateLongListPdf;
use App\Jobs\GenerateShortListPdf;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\Phases\SubmissionWorkflow;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake(config('filesystems.private'));
    $this->assoc = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    $this->game->associations()->attach($this->assoc);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->ali = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->p4 = Submission::for($this->game, $this->assoc, 4); // no submitted Long List yet: nothing preloaded
    $this->p4->entries()->create(['person_id' => $this->ali->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    $this->p4->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BJF-G001-P4-R1', 'revision' => 1,
        'justification' => 'Two medal chances']);
});

it('stores the Short List PDF under its own key and file name', function () {
    (new GenerateShortListPdf($this->p4))->handle();

    $path = 'submissions/BJF-G001-P4-R1/short-list.pdf';
    expect($this->p4->fresh()->pdf_paths)->toBe(['short_list' => $path])
        ->and(substr(Storage::disk(config('filesystems.private'))->get($path), 0, 4))->toBe('%PDF');
});

it('titles it Short List and passes the justification and the Long List columns', function () {
    $captured = null;
    view()->composer('pdf.long-list', function ($v) use (&$captured) {
        $captured = $v->getData();
    });

    (new GenerateShortListPdf($this->p4))->handle();

    expect($captured['title'])->toBe('Short List')->and($captured['justification'])->toBe('Two medal chances')
        ->and(collect($captured['athletes'])->pluck('name')->all())->toBe(['Ali Hasan']);
});

it('keeps the Long List PDF titled Long List', function () {
    $p3 = Submission::for($this->game, $this->assoc, 3);
    $p3->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BJF-G001-P3-R1', 'revision' => 1]);
    $captured = null;
    view()->composer('pdf.long-list', function ($v) use (&$captured) {
        $captured = $v->getData();
    });

    (new GenerateLongListPdf($p3))->handle();

    expect($captured['title'])->toBe('Long List')->and($p3->fresh()->pdf_paths)->toBe(['long_list' => 'submissions/BJF-G001-P3-R1/long-list.pdf']);
});

it('prints the title and the justification', function () {
    $html = view('pdf.long-list', [
        'game' => $this->game, 'association' => $this->assoc, 'athleteItems' => [], 'athletes' => [], 'officials' => [],
        'referenceNo' => 'BJF-G001-P4-R1', 'declaration' => 'I confirm.', 'nocLogo' => null, 'gameLogo' => null,
        'title' => 'Short List', 'justification' => 'Two medal chances',
    ])->render();

    expect($html)->toContain('Short List')->toContain('Justification')->toContain('Two medal chances')->not->toContain('Long List');
});

it('is dispatched when a Short List is finalised', function () {
    Bus::fake([GenerateShortListPdf::class, GenerateLongListPdf::class]);
    Notification::fake();
    Submission::for($this->game, $this->assoc, 3)->update(['status' => SubmissionStatus::Submitted]);
    $this->game->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->p4->entries()->delete();
    $this->p4->update(['status' => SubmissionStatus::Draft]);

    app(SubmissionWorkflow::class)->submit($this->p4->fresh(), User::factory()->assocAdmin($this->assoc)->create());

    Bus::assertDispatched(GenerateShortListPdf::class);
    Bus::assertNotDispatched(GenerateLongListPdf::class);
});

it('offers the download on the phase 4 page once submitted', function () {
    $this->game->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->p4->update(['pdf_paths' => ['short_list' => 'submissions/BJF-G001-P4-R1/short-list.pdf']]);
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());

    livewire(Phase4Entry::class, ['game' => $this->game->id])->assertSee('Download Short List PDF');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/ShortListPdfTest.php`
Expected: FAIL with `Class "App\Jobs\GenerateShortListPdf" not found`.

- [ ] **Step 3: Generalise `GenerateLongListPdf`**

In `app/Jobs/GenerateLongListPdf.php`:

1. Add after `public $backoff = 60;`:

```php
    /** pdf_paths key, file name and title; GenerateShortListPdf overrides them for Phase 4. */
    protected const KEY = 'long_list';

    protected const FILE = 'long-list.pdf';

    protected const TITLE = 'Long List';
```

2. In the `view('pdf.long-list', [...])` data array add:

```php
            'title' => static::TITLE, 'justification' => $s->justification,
```

3. Replace the last three lines of `handle()` (from `$path = …` to `saveQuietly();`) with:

```php
        $path = 'submissions/'.$s->reference_no.'/'.static::FILE;
        Storage::disk(config('filesystems.private'))->put($path, Pdf::loadHTML($html)->setPaper('a4', 'landscape')->output());
        $s->forceFill(['pdf_paths' => [static::KEY => $path]])->saveQuietly();
```

`app/Jobs/GenerateShortListPdf.php`:

```php
<?php

namespace App\Jobs;

/** Phase 4 PDF: the Long List layout (spec: "layout from the Phase 3 sample") titled Short List, plus the justification. */
class GenerateShortListPdf extends GenerateLongListPdf
{
    protected const KEY = 'short_list';

    protected const FILE = 'short-list.pdf';

    protected const TITLE = 'Short List';
}
```

- [ ] **Step 4: Title and justification in the PDF view**

In `resources/views/pdf/long-list.blade.php`:

1. Replace `<div style="font-size:13px">Long List</div>` with:

```blade
            <div style="font-size:13px">{{ $title ?? 'Long List' }}</div>
```

2. Insert right before `<p style="margin-top:14px">{{ $declaration }}</p>`:

```blade
@if(! empty($justification))
<h3>Justification (over the limit)</h3>
<p style="white-space:pre-line">{{ $justification }}</p>
@endif
```

- [ ] **Step 5: Dispatch on finalise**

In `app/Services/Phases/SubmissionWorkflow.php` add `use App\Jobs\GenerateShortListPdf;` and, in `finalise()` after the `if ($s->phase === 3) { … }` block:

```php
        if ($s->phase === 4) {
            GenerateShortListPdf::dispatch($s);
        }
```

- [ ] **Step 6: Download link on the Phase 4 page**

In `resources/views/filament/portal/pages/phase4-entry.blade.php`, insert after the `@endif` that closes the `$readOnlyReason` block (before `@if (! $this->longListSubmitted())`):

```blade
    @php $paths = $this->submission->pdf_paths ?? []; @endphp
    @if ($this->submission->status === \App\Enums\SubmissionStatus::Submitted && isset($paths['short_list']))
        <a class="text-sm text-primary-600 underline" target="_blank"
           href="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.private'))->temporaryUrl($paths['short_list'], now()->addMinutes(5)) }}">Download Short List PDF</a>
    @endif
```

- [ ] **Step 7: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/ShortListPdfTest.php tests/Feature/LongListPdfTest.php`
Expected: PASS.
Run: `$PHP vendor/bin/pest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
$PHP vendor/bin/pint
git add app/Jobs/GenerateLongListPdf.php app/Jobs/GenerateShortListPdf.php resources/views/pdf/long-list.blade.php app/Services/Phases/SubmissionWorkflow.php resources/views/filament/portal/pages/phase4-entry.blade.php tests/Feature/ShortListPdfTest.php
git commit -m "feat: Short List PDF with justification, generated on final submit

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Admin submission view and STA edits for Phase 4

**Files:**
- Create: `app/Filament/Resources/Games/Pages/StaPhase4Edit.php`
- Modify: `app/Filament/Resources/Games/GameResource.php` (route `submission-edit-4`), `app/Filament/Resources/Games/Pages/Concerns/StaEditsSubmission.php` (phase 4 PDF), `app/Filament/Resources/Games/Pages/SubmissionView.php` (`overLimitLines()`, edit action for phase 4), `resources/views/filament/resources/games/pages/submission-view.blade.php`
- Test: `tests/Feature/ShortListAdminTest.php`

**Interfaces:**
- Consumes: `EditsPhase4` + view `filament.portal.pages.phase4-entry` (Task 2), `GenerateShortListPdf` (Task 3), `ShortListChecks::overLimits/describe` (Task 1), `StaEditsSubmission`, `SubmissionView::longListRows()` (existing; works for any submission's saved entries).
- Produces: route name `submission-edit-4` → `/admin/games/{record}/submissions/{submission}/edit-phase-4`; `SubmissionView::overLimitLines(): list<string>`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ShortListAdminTest.php`:

```php
<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\StaPhase4Edit;
use App\Filament\Resources\Games\Pages\SubmissionView;
use App\Jobs\GenerateShortListPdf;
use App\Models\Association;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
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
    $this->assoc = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Closed, 'deadline' => now()->subDay()]);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id, 'p4_max_men' => 1,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->ali = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->omar = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Omar', 'last_name' => 'Saleh']);
    $this->yusuf = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Yusuf', 'last_name' => 'Adel']);
    $p3 = Submission::for($this->game, $this->assoc, 3);
    foreach ([$this->ali, $this->omar] as $p) {
        $p3->entries()->create(['person_id' => $p->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    }
    $p3->update(['status' => SubmissionStatus::Submitted]);
    $this->p4 = Submission::for($this->game, $this->assoc, 4); // preloaded: Ali and Omar
    $this->p4->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BJF-G001-P4-R1', 'revision' => 1, 'justification' => 'Both can medal']);
    $this->sta = User::factory()->sta()->create();
    $this->sta->givePermissionTo([StaPermission::ViewSubmissions->value, StaPermission::EditSubmissions->value]);
    actingInAdmin($this->sta);
});

it('shows the Short List with its over-limit lines and the justification', function () {
    $viewer = User::factory()->sta()->create();
    $viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($viewer);

    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->p4])
        ->assertSee('Short List')->assertSee('Ali Hasan')->assertSee('Omar Saleh')
        ->assertSee('Judo: 2 men (limit 1)')->assertSee('Both can medal')->assertActionHidden('edit');
});

it('lets STA edit a submitted Short List and its justification after the deadline, audited, and regenerates the PDF', function () {
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->p4])->assertActionVisible('edit');

    livewire(StaPhase4Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->p4])
        ->assertDontSee('This phase is closed.')
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->set('justification', 'Updated by STA')
        ->call('save')->assertHasNoErrors();

    $s = $this->p4->fresh();
    expect($s->entries()->pluck('person_id')->all())->toBe([$this->ali->id])
        ->and($s->status)->toBe(SubmissionStatus::Submitted)->and($s->reference_no)->toBe('BJF-G001-P4-R1')
        ->and($s->justification)->toBe('Updated by STA')
        ->and(Activity::where('log_name', 'submission-'.$s->id)->where('causer_id', $this->sta->id)->exists())->toBeTrue();
    Bus::assertDispatched(GenerateShortListPdf::class);
});

it('keeps the Long List rule for STA', function () {
    livewire(StaPhase4Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->p4])
        ->set("athletes.{$this->m60->id}", [(string) $this->yusuf->id])->call('save')->assertHasErrors(["athletes.{$this->m60->id}"]);

    expect($this->p4->entries()->count())->toBe(2);
    Bus::assertNotDispatched(GenerateShortListPdf::class);
});

it('needs edit_submissions for the STA Short List page', function () {
    $viewer = User::factory()->sta()->create();
    $viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($viewer);
    $this->get(GameResource::getUrl('submission-edit-4', ['record' => $this->game, 'submission' => $this->p4]))->assertForbidden();

    actingInAdmin($this->sta);
    $this->get(GameResource::getUrl('submission-edit-4', ['record' => $this->game, 'submission' => $this->p4]))->assertOk();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/ShortListAdminTest.php`
Expected: FAIL (`Short List` content missing on the submission view / `StaPhase4Edit` not found).

- [ ] **Step 3: Write `StaPhase4Edit`**

`app/Filament/Resources/Games/Pages/StaPhase4Edit.php`:

```php
<?php

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\Concerns\StaEditsSubmission;
use App\Filament\Shared\Phases\EditsPhase4;
use Filament\Resources\Pages\Page;

class StaPhase4Edit extends Page
{
    use EditsPhase4, StaEditsSubmission {
        StaEditsSubmission::save insteadof EditsPhase4;
        EditsPhase4::save as protected saveEntries;
    }

    public const PHASE = 4;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.portal.pages.phase4-entry';
}
```

In `app/Filament/Resources/Games/GameResource.php` import `StaPhase4Edit` and add to `getPages()` after `submission-edit-3`:

```php
            'submission-edit-4' => StaPhase4Edit::route('/{record}/submissions/{submission}/edit-phase-4'),
```

In `app/Filament/Resources/Games/Pages/Concerns/StaEditsSubmission.php` import `App\Jobs\GenerateShortListPdf` and add to the `match` in `save()`:

```php
            4 => GenerateShortListPdf::dispatch($this->submission),
```

- [ ] **Step 4: Phase 4 on the submission view**

In `app/Filament/Resources/Games/Pages/SubmissionView.php` import `App\Services\Phases\ShortListChecks`, add:

```php
    /** @return list<string> e.g. "Judo: 2 men (limit 1)" */
    public function overLimitLines(): array
    {
        return array_map(ShortListChecks::describe(...), app(ShortListChecks::class)->overLimits($this->submission));
    }
```

and change the `edit` action's visibility from `in_array($this->submission->phase, [1, 2, 3], true)` to `in_array($this->submission->phase, [1, 2, 3, 4], true)`.

In `resources/views/filament/resources/games/pages/submission-view.blade.php`, insert before the `@endif` that closes the `@if ($s->phase === 1)` chain:

```blade
    @elseif ($s->phase === 4)
        <x-filament::section heading="Short List">
            @if ($lines = $this->overLimitLines())
                <ul class="mb-2 list-disc pl-5 text-sm text-warning-600">
                    @foreach ($lines as $line)<li>Over the limit: {{ $line }}</li>@endforeach
                </ul>
            @endif
            @if (filled($s->justification))
                <p class="mb-3 whitespace-pre-line text-sm"><span class="text-gray-500">Justification:</span> {{ $s->justification }}</p>
            @endif
            @include('filament.shared.long-list-people', ['rows' => $this->longListRows()])
        </x-filament::section>
```

- [ ] **Step 5: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/ShortListAdminTest.php tests/Feature/StaSubmissionEditTest.php tests/Feature/SubmissionViewTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
$PHP vendor/bin/pint
git add app/Filament/Resources/Games/Pages/StaPhase4Edit.php app/Filament/Resources/Games/GameResource.php app/Filament/Resources/Games/Pages/Concerns/StaEditsSubmission.php app/Filament/Resources/Games/Pages/SubmissionView.php resources/views/filament/resources/games/pages/submission-view.blade.php tests/Feature/ShortListAdminTest.php
git commit -m "feat: STA view and edit of Phase 4 Short Lists, with PDF regeneration

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Game reports service

**Files:**
- Create: `app/Services/Reports/GameReports.php`
- Test: `tests/Feature/GameReportsTest.php`

**Interfaces:**
- Consumes: `ShortListChecks::over()` and `ShortListChecks::KINDS` (Task 1); models `Submission` (`answers`, `numbers`, `entries`, `association`), `Entry` (`person`, `gameEvent`, `gameSport`), `Person` (`sizes`, `age()`, `fullName()`), `Game` (`gameSports`, `clothingItems`).
- Produces `App\Services\Reports\GameReports` (resolved with `app()`):
  - `REPORTS` = `['phase1Answers' => 'Phase 1 answers', 'phase2Totals' => 'Phase 2 totals', 'longList' => 'Long List', 'shortList' => 'Short List', 'overLimit' => 'Short List over limit', 'expiringDocuments' => 'Expiring documents', 'clothingSizeCounts' => 'Clothing sizes (counts)', 'clothingSizesByPerson' => 'Clothing sizes (per person)']`.
  - `PERSON_HEADINGS` = `['Association', 'Code', 'Name', 'Type', 'Sport', 'Event', 'Gender', 'Date of birth', 'Age at games start', 'Nationality', 'Passport expiry']`.
  - One public method per `REPORTS` key, each `(Game $game): array{headings: list<string>, rows: list<list<scalar|null>>}`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/GameReportsTest.php`:

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
use App\Services\Reports\GameReports;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games', 'start_date' => '2026-12-01', 'end_date' => '2026-12-10']);
    $this->judoFed = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $karateFed = Association::factory()->create(['code' => 'BKF', 'name' => 'Bahrain Karate Federation']);
    $this->game->associations()->attach([$this->judoFed->id, $karateFed->id]);
    $shirt = ClothingItem::factory()->create(['name' => 'T-Shirt', 'sizes' => ['S', 'M', 'L']]);
    $this->game->clothingItems()->attach($shirt);

    $sport = fn (Association $a, string $name, array $limits = []) => GameSport::factory()->create(['game_id' => $this->game->id, ...$limits,
        'sport_id' => Sport::factory()->create(['association_id' => $a->id, 'name' => $name])->id]);
    $event = fn (GameSport $gs, string $name, EventGender $g) => GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $gs->id,
        'event_id' => Event::factory()->create(['sport_id' => $gs->sport_id, 'name' => $name, 'gender' => $g])->id]);
    $this->judo = $sport($this->judoFed, 'Judo', ['p4_max_men' => 1]);
    $this->m60 = $event($this->judo, 'Men -60kg', EventGender::Men);
    $event($this->judo, 'Women -52kg', EventGender::Women);
    $karate = $sport($karateFed, 'Karate');
    $kumite = $event($karate, 'Kumite', EventGender::Men);

    $this->ali = Person::factory()->create(['association_id' => $this->judoFed->id, 'first_name' => 'Ali', 'last_name' => 'Hasan',
        'passport_expiry' => '2026-12-05', 'allergies' => 'Peanut allergy']);
    $this->omar = Person::factory()->create(['association_id' => $this->judoFed->id, 'first_name' => 'Omar', 'last_name' => 'Saleh']);
    $this->coach = Person::factory()->official()->create(['association_id' => $this->judoFed->id, 'first_name' => 'Coach', 'last_name' => 'Karim',
        'cpr_expiry' => '2026-11-30']);
    $karim = Person::factory()->create(['association_id' => $karateFed->id, 'first_name' => 'Karim', 'last_name' => 'Ahmed']);
    $this->ali->syncSizes([$shirt->id => 'L']);
    $this->omar->syncSizes([$shirt->id => 'L']);
    $this->coach->syncSizes([$shirt->id => 'M']);

    $sub = fn (Association $a, int $phase) => Submission::for($this->game, $a, $phase);
    $athlete = fn (Submission $s, Person $p, GameEvent $ge) => $s->entries()->create(['person_id' => $p->id, 'game_event_id' => $ge->id, 'game_sport_id' => $ge->game_sport_id]);

    // Judo federation: every phase submitted.
    $p1 = $sub($this->judoFed, 1);
    $p1->answers()->create(['game_sport_id' => $this->judo->id, 'answer' => 'yes']);
    $p2 = $sub($this->judoFed, 2);
    $p2->numbers()->create(['game_event_id' => $this->m60->id, 'count' => 2]);
    $this->p3 = $sub($this->judoFed, 3);
    $athlete($this->p3, $this->ali, $this->m60);
    $athlete($this->p3, $this->omar, $this->m60);
    $this->p3->entries()->create(['person_id' => $this->coach->id, 'game_event_id' => null, 'game_sport_id' => $this->judo->id]);
    $this->p4 = $sub($this->judoFed, 4); // created while Phase 3 is a draft: nothing preloaded
    $athlete($this->p4, $this->ali, $this->m60);
    $athlete($this->p4, $this->omar, $this->m60);
    $this->p4->update(['justification' => 'Both can medal']);
    foreach ([$p1, $p2, $this->p3, $this->p4] as $s) {
        $s->update(['status' => SubmissionStatus::Submitted]);
    }

    // Karate federation: drafts only, so it must not appear in any report.
    $sub($karateFed, 1)->answers()->create(['game_sport_id' => $karate->id, 'answer' => 'yes']);
    $sub($karateFed, 2)->numbers()->create(['game_event_id' => $kumite->id, 'count' => 1]);
    $athlete($sub($karateFed, 3), $karim, $kumite);

    $this->reports = app(GameReports::class);
});

it('lists phase 1 answers', function () {
    expect($this->reports->phase1Answers($this->game))->toBe([
        'headings' => ['Association', 'Sport', 'Answer'],
        'rows' => [['Bahrain Judo Federation', 'Judo', 'Yes']],
    ]);
});

it('totals phase 2 numbers per event and per sport', function () {
    expect($this->reports->phase2Totals($this->game))->toBe([
        'headings' => ['Association', 'Sport', 'Event', 'Gender', 'Count'],
        'rows' => [
            ['Bahrain Judo Federation', 'Judo', 'Men -60kg', 'Men', 2],
            ['Bahrain Judo Federation', 'Judo', 'Women -52kg', 'Women', 0],
            ['Bahrain Judo Federation', 'Judo', 'Total', null, 2],
        ],
    ]);
});

it('lists the Long List and the Short List one row per entry', function () {
    $long = $this->reports->longList($this->game);

    expect($long['headings'])->toBe(GameReports::PERSON_HEADINGS)
        ->and($long['rows'][0])->toBe(['Bahrain Judo Federation', $this->ali->code, 'Ali Hasan', 'Athlete', 'Judo', 'Men -60kg', 'Male',
            '2005-03-10', 21, 'Bahrain', '2026-12-05'])
        ->and(array_column($long['rows'], 2))->toBe(['Ali Hasan', 'Omar Saleh', 'Coach Karim'])
        ->and($long['rows'][2][3])->toBe('Team official')->and($long['rows'][2][5])->toBe('Official')
        ->and(array_column($this->reports->shortList($this->game)['rows'], 2))->toBe(['Ali Hasan', 'Omar Saleh']);
});

it('lists Short Lists over a limit with their justification', function () {
    expect($this->reports->overLimit($this->game))->toBe([
        'headings' => ['Association', 'Sport', 'Limit', 'Count', 'Max', 'Justification'],
        'rows' => [['Bahrain Judo Federation', 'Judo', 'men', 2, 1, 'Both can medal']],
    ]);
});

it('lists documents expiring before the games end, from the Short List when Phase 4 is submitted', function () {
    expect($this->reports->expiringDocuments($this->game))->toBe([
        'headings' => ['Association', 'Code', 'Name', 'Document', 'Expiry'],
        'rows' => [['Bahrain Judo Federation', $this->ali->code, 'Ali Hasan', 'Passport', '2026-12-05']],
    ]);

    $this->p4->update(['status' => SubmissionStatus::Draft]); // falls back to the Long List, which has the coach
    expect($this->reports->expiringDocuments($this->game)['rows'])->toBe([
        ['Bahrain Judo Federation', $this->ali->code, 'Ali Hasan', 'Passport', '2026-12-05'],
        ['Bahrain Judo Federation', $this->coach->code, 'Coach Karim', 'CPR', '2026-11-30'],
    ]);
});

it('counts clothing sizes by association and overall, and lists them per person', function () {
    expect($this->reports->clothingSizeCounts($this->game))->toBe([
        'headings' => ['Association', 'Item', 'Size', 'Count'],
        'rows' => [['Bahrain Judo Federation', 'T-Shirt', 'L', 2], ['All associations', 'T-Shirt', 'L', 2]],
    ])->and($this->reports->clothingSizesByPerson($this->game))->toBe([
        'headings' => ['Association', 'Code', 'Name', 'Item', 'Size'],
        'rows' => [
            ['Bahrain Judo Federation', $this->ali->code, 'Ali Hasan', 'T-Shirt', 'L'],
            ['Bahrain Judo Federation', $this->omar->code, 'Omar Saleh', 'T-Shirt', 'L'],
        ],
    ]);

    $this->p4->update(['status' => SubmissionStatus::Draft]);
    expect($this->reports->clothingSizeCounts($this->game)['rows'])->toBe([
        ['Bahrain Judo Federation', 'T-Shirt', 'M', 1], ['Bahrain Judo Federation', 'T-Shirt', 'L', 2],
        ['All associations', 'T-Shirt', 'M', 1], ['All associations', 'T-Shirt', 'L', 2],
    ]);
});

it('includes only submitted submissions and never document numbers or medical data', function () {
    $all = json_encode(collect(array_keys(GameReports::REPORTS))->map(fn ($r) => $this->reports->{$r}($this->game)));

    expect($all)->not->toContain('Bahrain Karate Federation')->not->toContain('Karim Ahmed')
        ->not->toContain($this->ali->passport_no)->not->toContain($this->ali->cpr_no)->not->toContain('Peanut allergy');
});

it('runs a fixed number of queries whatever the number of people', function () {
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->reports->longList($this->game);
        $this->reports->clothingSizesByPerson($this->game);

        return count(DB::getQueryLog());
    };
    $before = $count();
    foreach (range(1, 3) as $i) {
        $this->p3->entries()->create(['person_id' => Person::factory()->create(['association_id' => $this->judoFed->id])->id,
            'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    }

    expect($count())->toBe($before);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/GameReportsTest.php`
Expected: FAIL with `Class "App\Services\Reports\GameReports" not found`.

- [ ] **Step 3: Write the service**

`app/Services/Reports/GameReports.php`:

```php
<?php

namespace App\Services\Reports;

use App\Enums\SubmissionStatus;
use App\Models\Game;
use App\Models\Person;
use App\Models\Submission;
use App\Services\Phases\ShortListChecks;
use Illuminate\Support\Collection;

/**
 * STA reports for one game (spec 6). Only submitted submissions count. Never medical data, passport or CPR numbers.
 * Every method returns ['headings' => list<string>, 'rows' => list<list<scalar|null>>].
 */
final class GameReports
{
    public const REPORTS = [
        'phase1Answers' => 'Phase 1 answers',
        'phase2Totals' => 'Phase 2 totals',
        'longList' => 'Long List',
        'shortList' => 'Short List',
        'overLimit' => 'Short List over limit',
        'expiringDocuments' => 'Expiring documents',
        'clothingSizeCounts' => 'Clothing sizes (counts)',
        'clothingSizesByPerson' => 'Clothing sizes (per person)',
    ];

    public const PERSON_HEADINGS = ['Association', 'Code', 'Name', 'Type', 'Sport', 'Event', 'Gender', 'Date of birth',
        'Age at games start', 'Nationality', 'Passport expiry'];

    public function phase1Answers(Game $game): array
    {
        $sports = $this->sportsByAssociation($game);
        $rows = [];
        foreach ($this->submitted($game, 1, ['answers']) as $s) {
            $answers = $s->answers->keyBy('game_sport_id');
            foreach ($sports->get($s->association_id, collect()) as $gs) {
                $rows[] = [$s->association->name, $gs->sport->name, $answers->get($gs->id)?->answer->label() ?? 'No response'];
            }
        }

        return ['headings' => ['Association', 'Sport', 'Answer'], 'rows' => $rows];
    }

    public function phase2Totals(Game $game): array
    {
        $sports = $this->sportsByAssociation($game);
        $rows = [];
        foreach ($this->submitted($game, 2, ['numbers']) as $s) {
            $counts = $s->numbers->pluck('count', 'game_event_id');
            foreach ($sports->get($s->association_id, collect()) as $gs) {
                $total = 0;
                foreach ($gs->gameEvents->sortBy('event.name') as $ge) {
                    $n = (int) ($counts[$ge->id] ?? 0);
                    $total += $n;
                    $rows[] = [$s->association->name, $gs->sport->name, $ge->event->name, $ge->event->gender->label(), $n];
                }
                $rows[] = [$s->association->name, $gs->sport->name, 'Total', null, $total];
            }
        }

        return ['headings' => ['Association', 'Sport', 'Event', 'Gender', 'Count'], 'rows' => $rows];
    }

    public function longList(Game $game): array
    {
        return $this->entryRows($game, 3);
    }

    public function shortList(Game $game): array
    {
        return $this->entryRows($game, 4);
    }

    public function overLimit(Game $game): array
    {
        $sports = $this->sportsByAssociation($game);
        $rows = [];
        foreach ($this->submitted($game, 4, ['entries.person']) as $s) {
            $athletes = [];
            $officials = [];
            foreach ($s->entries as $e) {
                if ($e->game_event_id) {
                    $athletes[$e->game_event_id][] = $e->person_id;
                } else {
                    $officials[$e->game_sport_id][] = $e->person_id;
                }
            }
            $genders = $s->entries->pluck('person.gender', 'person_id')->all();
            foreach (ShortListChecks::over($sports->get($s->association_id, collect()), $athletes, $officials, $genders) as $o) {
                $rows[] = [$s->association->name, $o['sport'], ShortListChecks::KINDS[$o['kind']], $o['count'], $o['max'], $s->justification];
            }
        }

        return ['headings' => ['Association', 'Sport', 'Limit', 'Count', 'Max', 'Justification'], 'rows' => $rows];
    }

    public function expiringDocuments(Game $game): array
    {
        $rows = [];
        foreach ($this->people($game) as [$s, $p]) {
            foreach (['Passport' => $p->passport_expiry, 'CPR' => $p->cpr_expiry] as $document => $expiry) {
                if ($expiry && $expiry->lt($game->end_date)) {
                    $rows[] = [$s->association->name, $p->code, $this->name($p), $document, $expiry->toDateString()];
                }
            }
        }

        return ['headings' => ['Association', 'Code', 'Name', 'Document', 'Expiry'], 'rows' => $rows];
    }

    public function clothingSizeCounts(Game $game): array
    {
        $items = $game->clothingItems()->orderBy('name')->get();
        $byAssociation = [];
        $all = [];
        foreach ($this->people($game) as [$s, $p]) {
            $sizes = $p->sizes->pluck('size', 'clothing_item_id');
            foreach ($items as $item) {
                $size = $sizes->get($item->id);
                if ($item->applies_to->includes($p->type) && filled($size)) {
                    $byAssociation[$s->association->name][$item->id][$size] = ($byAssociation[$s->association->name][$item->id][$size] ?? 0) + 1;
                    $all[$item->id][$size] = ($all[$item->id][$size] ?? 0) + 1;
                }
            }
        }
        $rows = [];
        foreach ($all ? [...$byAssociation, 'All associations' => $all] : [] as $association => $counts) {
            foreach ($items as $item) {
                foreach ($item->sizes as $size) { // the item's own size order
                    if ($n = $counts[$item->id][$size] ?? 0) {
                        $rows[] = [$association, $item->name, $size, $n];
                    }
                }
            }
        }

        return ['headings' => ['Association', 'Item', 'Size', 'Count'], 'rows' => $rows];
    }

    public function clothingSizesByPerson(Game $game): array
    {
        $items = $game->clothingItems()->orderBy('name')->get();
        $rows = [];
        foreach ($this->people($game) as [$s, $p]) {
            $sizes = $p->sizes->pluck('size', 'clothing_item_id');
            foreach ($items->filter(fn ($i) => $i->applies_to->includes($p->type)) as $item) {
                $rows[] = [$s->association->name, $p->code, $this->name($p), $item->name, $sizes->get($item->id)];
            }
        }

        return ['headings' => ['Association', 'Code', 'Name', 'Item', 'Size'], 'rows' => $rows];
    }

    private function entryRows(Game $game, int $phase): array
    {
        $rows = [];
        foreach ($this->submitted($game, $phase, ['entries.person', 'entries.gameEvent.event', 'entries.gameSport.sport']) as $s) {
            foreach ($s->entries as $e) {
                $p = $e->person;
                $rows[] = [$s->association->name, $p->code, $this->name($p), $p->type->label(), $e->gameSport->sport->name,
                    $e->gameEvent?->event->name ?? 'Official', $p->gender->label(), $p->dob->toDateString(),
                    $p->age($game->start_date), $p->nationality, $p->passport_expiry?->toDateString()];
            }
        }

        return ['headings' => self::PERSON_HEADINGS,
            'rows' => collect($rows)->sortBy([[0, 'asc'], [4, 'asc'], [3, 'asc'], [2, 'asc']])->values()->all()];
    }

    /** @return Collection<int, Submission> submitted submissions of one phase, association loaded, sorted by association name */
    private function submitted(Game $game, int $phase, array $with = []): Collection
    {
        return Submission::withoutGlobalScopes()->with(['association', ...$with])
            ->where(['game_id' => $game->id, 'phase' => $phase, 'status' => SubmissionStatus::Submitted->value])
            ->get()->sortBy('association.name')->values();
    }

    /** @return Collection<int, Collection> association_id => its game sports (sport, events loaded), sorted by sport name */
    private function sportsByAssociation(Game $game): Collection
    {
        return $game->gameSports()->with(['sport', 'gameEvents.event'])->get()->sortBy('sport.name')->groupBy('sport.association_id');
    }

    /**
     * Distinct people per association: its Short List when Phase 4 is submitted, else its submitted Long List.
     *
     * @return Collection<int, array{0: Submission, 1: Person}>
     */
    private function people(Game $game): Collection
    {
        $with = ['entries.person.sizes'];
        $sources = $this->submitted($game, 4, $with)->keyBy('association_id')
            ->union($this->submitted($game, 3, $with)->keyBy('association_id'))
            ->sortBy('association.name')->values();

        return $sources->flatMap(fn (Submission $s) => $s->entries->pluck('person')->unique('id')->sortBy('code')
            ->map(fn (Person $p) => [$s, $p]))->values();
    }

    private function name(Person $p): string
    {
        return $p->full_name ?: $p->fullName();
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/GameReportsTest.php`
Expected: PASS (9 tests).

- [ ] **Step 5: Commit**

```bash
$PHP vendor/bin/pint
git add app/Services/Reports/GameReports.php tests/Feature/GameReportsTest.php
git commit -m "feat: game reports (phase 1 answers, phase 2 totals, long/short list, over-limit, expiring documents, clothing sizes)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Reports page, Excel export and dashboard link

**Files:**
- Create: `app/Filament/Resources/Games/Pages/GameReports.php`, `resources/views/filament/resources/games/pages/game-reports.blade.php`
- Modify: `app/Filament/Resources/Games/GameResource.php` (route `reports`, `canAccess` + `ViewReports`), `app/Filament/Resources/Games/Pages/GameDashboard.php` (header action), `app/Filament/Resources/Games/Tables/GamesTable.php` (row action)
- Test: `tests/Feature/GameReportsTest.php` (append)

**Interfaces:**
- Consumes: `App\Services\Reports\GameReports` (`REPORTS`, `PERSON_HEADINGS`, report methods) from Task 5; OpenSpout `Writer` / `Row::fromValues` (as in `app/Exports/PeopleTemplateExport.php`), OpenSpout `Reader` (as in `app/Services/PeopleImporter.php`); Livewire's file-download effect (`assertFileDownloaded`, `effects['download']['content']` base64).
- Produces: route name `reports` → `/admin/games/{record}/reports`; page public `string $report`, `reportData(): array`, `export(): StreamedResponse`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/GameReportsTest.php` (and add the imports `use App\Enums\StaPermission;`, `use App\Filament\Resources\Games\GameResource;`, `use App\Filament\Resources\Games\Pages\GameDashboard;`, `use App\Filament\Resources\Games\Pages\GameReports as GameReportsPage;`, `use App\Models\User;`, `use OpenSpout\Reader\XLSX\Reader;`, `use function Pest\Livewire\livewire;`):

```php
it('opens the reports page only with view_reports', function () {
    $reporter = User::factory()->sta()->create();
    $reporter->givePermissionTo(StaPermission::ViewReports->value);
    actingInAdmin($reporter);
    $this->get(GameResource::getUrl('reports', ['record' => $this->game]))->assertOk()
        ->assertSee('Only submitted entries are included');

    $viewer = User::factory()->sta()->create();
    $viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($viewer);
    $this->get(GameResource::getUrl('reports', ['record' => $this->game]))->assertForbidden();
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])->assertActionHidden('reports');

    $viewer->givePermissionTo(StaPermission::ViewReports->value);
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])->assertActionVisible('reports');
});

it('previews the chosen report and exports it to Excel', function () {
    $reporter = User::factory()->sta()->create();
    $reporter->givePermissionTo(StaPermission::ViewReports->value);
    actingInAdmin($reporter);

    $page = livewire(GameReportsPage::class, ['record' => $this->game->getRouteKey()])
        ->assertSee('Judo')->assertSee('Yes')
        ->set('report', 'longList')->assertSee('Ali Hasan')->assertSee('Coach Karim')
        ->call('export')
        ->assertFileDownloaded('asian-indoor-games-long-list.xlsx',
            contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $path = sys_get_temp_dir().'/'.uniqid('report').'.xlsx';
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

    expect($rows[0])->toBe(GameReports::PERSON_HEADINGS)->and($rows[1][2])->toBe('Ali Hasan')->and(count($rows))->toBe(4);
});

it('refuses an unknown report', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(GameReportsPage::class, ['record' => $this->game->getRouteKey()])->set('report', 'longListX')->assertStatus(404);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP vendor/bin/pest tests/Feature/GameReportsTest.php`
Expected: the three new tests FAIL (`Route [filament.admin.resources.games.reports] not defined` / class not found).

- [ ] **Step 3: Write the page**

`app/Filament/Resources/Games/Pages/GameReports.php`:

```php
<?php

namespace App\Filament\Resources\Games\Pages;

use App\Enums\StaPermission;
use App\Filament\Resources\Games\GameResource;
use App\Services\Reports\GameReports as Reports;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GameReports extends Page
{
    use InteractsWithRecord;

    protected static string $resource = GameResource::class;

    protected string $view = 'filament.resources.games.pages.game-reports';

    public string $report = 'phase1Answers';

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can(StaPermission::ViewReports->value);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return $this->record->name.' - reports';
    }

    /** @return array{headings: list<string>, rows: list<list<scalar|null>>} */
    public function reportData(): array
    {
        abort_unless(array_key_exists($this->report, Reports::REPORTS), 404);

        return app(Reports::class)->{$this->report}($this->record);
    }

    public function export(): StreamedResponse
    {
        $data = $this->reportData();

        return response()->streamDownload(function () use ($data) {
            $tmp = tempnam(sys_get_temp_dir(), 'rep');
            $writer = new Writer;
            $writer->openToFile($tmp);
            $writer->addRow(Row::fromValues($data['headings']));
            foreach ($data['rows'] as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->close();
            readfile($tmp);
            unlink($tmp);
        }, Str::slug($this->record->name).'-'.Str::kebab($this->report).'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
```

`resources/views/filament/resources/games/pages/game-reports.blade.php`:

```blade
<x-filament-panels::page>
    @php $data = $this->reportData(); @endphp

    <p class="text-sm text-gray-600 dark:text-gray-400">
        Only submitted entries are included. Expiring documents and clothing sizes use an association's Short List when its Phase 4 is submitted, otherwise its Long List.
    </p>

    <div class="flex flex-wrap items-end gap-3">
        <label class="text-sm">
            Report
            <select wire:model.live="report" class="block rounded border-gray-300 text-sm dark:bg-white/5">
                @foreach (\App\Services\Reports\GameReports::REPORTS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <x-filament::button wire:click="export" icon="heroicon-o-arrow-down-tray">Export to Excel</x-filament::button>
    </div>

    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500">@foreach ($data['headings'] as $h)<th class="py-1 pr-3">{{ $h }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @forelse (array_slice($data['rows'], 0, 500) as $row)
                        <tr class="border-t border-gray-200 dark:border-white/10">@foreach ($row as $cell)<td class="py-1 pr-3">{{ $cell }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($data['headings']) }}" class="py-2 text-gray-500">No rows.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (count($data['rows']) > 500)
            <p class="mt-2 text-xs text-gray-500">Showing the first 500 of {{ count($data['rows']) }} rows; the Excel export has all of them.</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
```

- [ ] **Step 4: Route, resource access and links**

In `app/Filament/Resources/Games/GameResource.php`:
- import `App\Filament\Resources\Games\Pages\GameReports;`
- add to `getPages()`: `'reports' => GameReports::route('/{record}/reports'),`
- `canAccess()` becomes (Filament checks the resource's `canAccess` on every resource page, so a reports-only user needs it):

```php
    public static function canAccess(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->can(StaPermission::ManageGames->value) || $u?->can(StaPermission::ViewSubmissions->value)
            || $u?->can(StaPermission::ViewReports->value));
    }
```

In `app/Filament/Resources/Games/Pages/GameDashboard.php`, add as the second entry of `getHeaderActions()` (after `editGame`):

```php
            Action::make('reports')->label('Reports')->color('gray')
                ->url(fn () => GameResource::getUrl('reports', ['record' => $this->record]))
                ->visible(fn () => (bool) auth()->user()->can(StaPermission::ViewReports->value)),
```

In `app/Filament/Resources/Games/Tables/GamesTable.php`, add to `recordActions([...])` after `dashboard` (so a reports-only user can reach the page):

```php
                Action::make('reports')->icon('heroicon-o-document-chart-bar')
                    ->visible(fn () => (bool) auth()->user()->can(StaPermission::ViewReports->value))
                    ->url(fn ($record) => GameResource::getUrl('reports', ['record' => $record])),
```

- [ ] **Step 5: Run the tests**

Run: `$PHP vendor/bin/pest tests/Feature/GameReportsTest.php tests/Feature/GameDashboardTest.php`
Expected: PASS.
Run: `$PHP vendor/bin/pest`
Expected: PASS (whole suite).

- [ ] **Step 6: Commit**

```bash
$PHP vendor/bin/pint
git add app/Filament/Resources/Games/Pages/GameReports.php resources/views/filament/resources/games/pages/game-reports.blade.php app/Filament/Resources/Games/GameResource.php app/Filament/Resources/Games/Pages/GameDashboard.php app/Filament/Resources/Games/Tables/GamesTable.php tests/Feature/GameReportsTest.php
git commit -m "feat: game reports page with Excel export (OpenSpout), linked from the dashboard and games list

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Spec coverage (Milestone 4)

| Spec item | Task |
|---|---|
| `entries` reused for Phase 4; `submissions.justification` (spec 4) | 1, 2 |
| Phase 4 preloaded with a copy of the Long List; association unticks (spec 5) | 1, 2 |
| Limits per sport `p4_max_men` / `p4_max_women` / `p4_max_officials` (spec 5) | 1, 2 |
| Over a limit: justification required before submit, submission still allowed (spec 5, 9) | 1, 2 |
| Short List PDF, Long List layout, reference, footer (spec 2, 5) | 3 |
| Short List appears in the over-limit report (spec 5, 6) | 5, 6 |
| Submit flow incl. approve for Phase 4, STA notified, PDF on `submitted` (spec 5 Submit flow) | 1, 3 |
| STA edits any submission at any status, audited (spec 5) | 4 |
| Submission view: Phase 4 content, PDF download (spec 6) | 3, 4 |
| Reports and Excel exports: Phase 1 answers, Phase 2 totals, Long List, Short List, over-limit (with justifications), expiring documents, clothing sizes (counts by association and overall, per-person list) (spec 6) | 5, 6 |
| Permission "View reports and export Excel" | 6 |
| Sensitive data never in reports (spec 13) | 5 |
| Tests: Phase 4 copy from Long List and the justification requirement (spec 11) | 1, 2 |
| Optimistic locking on Phase 4 saves; tenant isolation (spec 8, 9) | 2 |
