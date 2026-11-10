# M4.3 Management Feedback Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply management's UI feedback (spec 16): Male/Female wording, Phase 2 participant totals and expected medals, Long List views and in-page person creation, consistent attachment View links, Save as draft everywhere, and draft/published games.

**Architecture:** Mostly edits to existing pages, traits and blades. One new table and model (`phase2_sport_totals` / `Phase2SportTotal`) hangs off `Submission` like `number_entries`. `Phase2Limits` keeps its role but its sport limits move from event sums to the participant totals. Game visibility is a nullable `games.published_at`, checked in the three places associations reach a game (portal Games page, `HandlesSubmission::mountPortal`, reminders) and in the Open phase action.

**Tech Stack:** Laravel 12, Filament 4.16, Livewire 3, Alpine (bundled with Filament), Pest 4, DomPDF, OpenSpout.

**Spec:** `docs/superpowers/specs/2026-11-04-sports-entry-system-design.md` — section 16 (binding, overrides earlier sections), with sections 5 and 13 for context.

## Global Constraints

- Gender is shown as **Male** / **Female**, and **Mixed** for events, on screens, PDFs, reports and exports. Stored values `men`, `women`, `mixed` (events) and `male`, `female` (people) do not change; enum case names (`EventGender::Men`, `::Women`) stay too — labels only.
- Participants per sport: whole numbers 0–999. Expected medals: whole numbers 0–99, optional, not linked to any athlete or event.
- `p2_max_men` / `p2_max_women` / `p2_max_total` limit the participant totals (male, female, male + female). Event counts are limited only by `game_events.p2_max_athletes`.
- Attachments open through a 5-minute `temporaryUrl` on `config('filesystems.private')` (`App\Filament\Shared\AttachmentActions::url()`).
- STA edits never change a submission's status; their save button is **Save as draft**.
- A game is a draft until `published_at` is set. Associations never see a draft game; its phases cannot be opened. Existing games are migrated as published.
- No new Composer or npm dependencies.
- PHP binary (Git Bash): `PHP=/c/Users/ababy/.config/herd/bin/php85/php.exe`. Tests: `$PHP artisan test --compact <file>`. Format: `$PHP vendor/bin/pint --dirty` before each commit.

## Decisions taken in this plan (beyond the spec)

1. **Participants required on submit, optional on save.** Submit/approve fails with "{Sport}: enter the total number of male and female participants." when either is blank for a visible sport (0 is a valid answer). Medals stay optional.
2. **Person forms keep one button.** Saving an incomplete person already works (spec 13), so the portal Create/Edit person primary button is relabelled **Save as draft**; no second button. Admin person pages are unchanged (spec 16.6 names associations).
3. **Phase pages get a header "Save as draft" action beside Submit** (`saveDraft`), and the bottom button is renamed **Save as draft** too (the bottom button is what STA edit pages show, since they share the blades).
4. **Publish lives on the Edit game page header**; the games list shows a Draft/Published badge. No unpublish (YAGNI).
5. **Phase 2 report keeps one row per event** and adds five columns (Male participants, Female participants, Gold, Silver, Bronze) filled on each sport's Total row.
6. **Clothing fit options** keep stored values `Men's` / `Women's` / `Youth` but are labelled Male / Female / Youth.
7. **ShortListChecks kinds** keep keys `men` / `women` / `officials`; their labels become `male athletes` / `female athletes` / `team officials` (e.g. "Judo: 2 male athletes (limit 1)").
8. **Portal PDF links** become "View … PDF", open in a new tab, via `AttachmentActions::url()`. Filament upload fields get a 5-minute *open* link through a new `AttachmentActions::view()` helper (Filament's own preview URL runs to the end of the hour).
9. **Grid/List preference** is stored in `localStorage` under `longListView.{userId}`; it is view-only and never sent to the server.

## File Structure

| File | Change | Task |
|---|---|---|
| `app/Enums/EventGender.php` | `label()` → Male/Female/Mixed | 1 |
| `app/Services/Phases/ShortListChecks.php` | `KINDS` labels | 1 |
| `app/Filament/Resources/Games/RelationManagers/GameSportsRelationManager.php` | limit field/column labels | 1 |
| `app/Filament/Shared/PersonForm.php` | fit labels (1); `$type` default param (4); `AttachmentActions::view()` (5) | 1, 4, 5 |
| `resources/views/filament/portal/pages/phase2-entry.blade.php` | headers (1); full rewrite (2); PDF link (5); button label (6) | 1, 2, 5, 6 |
| `resources/views/filament/portal/pages/phase4-entry.blade.php` | limits description (1); PDF link (5); button (6) | 1, 5, 6 |
| `database/migrations/2026_11_13_000100_create_phase2_sport_totals_table.php` | new | 2 |
| `app/Models/Phase2SportTotal.php` | new | 2 |
| `app/Models/Submission.php` | `sportTotals()` | 2 |
| `app/Services/Phases/Phase2Limits.php` | re-pointed | 2 |
| `app/Filament/Shared/Phases/EditsPhase2.php` | totals state, validation, save | 2 |
| `app/Services/Phases/SubmissionWorkflow.php` | Phase 2 submit checks | 2 |
| `app/Jobs/GenerateEntryByEventPdfs.php`, `resources/views/pdf/entry-by-event.blade.php` | participants + medals | 3 |
| `app/Filament/Resources/Games/Pages/SubmissionView.php`, `resources/views/filament/resources/games/pages/submission-view.blade.php` | participants + medals | 3 |
| `app/Services/Reports/GameReports.php` | `phase2Totals` columns | 3 |
| `app/Filament/Portal/Pages/Phase3Entry.php`, `app/Filament/Shared/Phases/EditsPhase3.php`, `resources/views/filament/portal/pages/phase3-entry.blade.php` | List/Grid, Add athlete/official, Open registry | 4 |
| `app/Jobs/GenerateLongListPdf.php`, `resources/views/pdf/long-list.blade.php` | summary | 4 |
| `app/Filament/Shared/AttachmentActions.php` | `view()` helper | 5 |
| `resources/views/filament/shared/long-list-people.blade.php` | View certificate | 5 |
| `resources/views/filament/portal/pages/phase1-entry.blade.php`, `phase3-entry.blade.php` | View PDF links | 5 |
| `app/Filament/Resources/Games/Schemas/GameForm.php` | logo View | 5 |
| `app/Filament/Portal/Concerns/HandlesSubmission.php`, portal `Phase{1,2,3,4}Entry.php` | `saveDraftAction()` | 6 |
| `app/Filament/Portal/Resources/People/Pages/{CreatePerson,EditPerson}.php` | button label | 6 |
| `database/migrations/2026_11_13_000200_add_published_at_to_games_table.php` | new | 7 |
| `app/Models/Game.php`, `database/factories/GameFactory.php` | `published_at`, `isPublished()`, `draft()` | 7 |
| `app/Filament/Resources/Games/Pages/EditGame.php`, `Tables/GamesTable.php`, `RelationManagers/PhasesRelationManager.php` | Publish, badge, Open guard | 7 |
| `app/Filament/Portal/Pages/Games.php`, `app/Console/Commands/SendPhaseReminders.php` | hide drafts | 7 |

---

### Task 1: Male / Female / Mixed labels

**Files:**
- Modify: `app/Enums/EventGender.php`
- Modify: `app/Services/Phases/ShortListChecks.php:21`
- Modify: `app/Filament/Resources/Games/RelationManagers/GameSportsRelationManager.php:43-51,84-85`
- Modify: `app/Filament/Shared/PersonForm.php:109`
- Modify: `resources/views/filament/portal/pages/phase2-entry.blade.php:38`
- Modify: `resources/views/filament/portal/pages/phase4-entry.blade.php:38`
- Create: `tests/Feature/GenderLabelsTest.php`
- Modify tests: `tests/Feature/GameReportsTest.php:93-94`, `tests/Feature/LongListChecksTest.php:52`, `tests/Feature/ShortListChecksTest.php:131`, `tests/Feature/Phase4EntryTest.php:101`, `tests/Feature/ShortListAdminTest.php:61`

**Interfaces:**
- Produces: `EventGender::label()` returns `'Male' | 'Female' | 'Mixed'`; `ShortListChecks::KINDS = ['men' => 'male athletes', 'women' => 'female athletes', 'officials' => 'team officials']`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/GenderLabelsTest.php`

```php
<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Filament\Portal\Pages\Phase2Entry;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\RelationManagers\GameSportsRelationManager;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Sport;
use App\Models\User;
use App\Services\Phases\ShortListChecks;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->gs = GameSport::factory()->create(['game_id' => $this->game->id, 'p2_max_men' => 2, 'p2_max_women' => 2,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    // Event names deliberately avoid the words being checked.
    foreach (['-60kg' => EventGender::Men, '-52kg' => EventGender::Women, 'Team' => EventGender::Mixed] as $name => $g) {
        GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->gs->id,
            'event_id' => Event::factory()->create(['sport_id' => $this->gs->sport_id, 'name' => $name, 'gender' => $g])->id]);
    }
});

it('labels event genders Male, Female and Mixed', function () {
    expect(array_map(fn (EventGender $g) => $g->label(), EventGender::cases()))->toBe(['Male', 'Female', 'Mixed']);
});

it('never shows Men or Women on the Phase 2 page', function () {
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());
    $html = livewire(Phase2Entry::class, ['game' => $this->game->id])
        ->assertSeeHtml('>Male</th>')->assertSeeHtml('>Female</th>')->html();

    expect($html)->not->toMatch('/\b(Men|Women)\b/');
});

it('never shows Men or Women in the Entry by Event PDF', function () {
    $html = view('pdf.entry-by-event', [
        'game' => $this->game, 'association' => $this->assoc, 'gameSport' => $this->gs, 'sportName' => 'Judo',
        'rows' => [['event' => '-60kg', 'gender' => 'men', 'count' => 1], ['event' => '-52kg', 'gender' => 'women', 'count' => 1]],
        'totals' => ['men' => 1, 'women' => 1, 'total' => 2],
        'participants' => ['male' => 1, 'female' => 1], 'medals' => ['gold' => null, 'silver' => null, 'bronze' => null],
        'referenceNo' => 'BJF-G001-P2-R1', 'declaration' => 'I confirm.', 'nocLogo' => null, 'gameLogo' => null,
    ])->render();

    expect($html)->toContain('Male')->toContain('Female')->not->toMatch('/\b(Men|Women)\b/');
});

it('labels admin limits and Short List kinds male and female', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(GameSportsRelationManager::class, ['ownerRecord' => $this->game, 'pageClass' => EditGame::class])
        ->assertSee('P2 male')->assertSee('P2 female')->assertDontSee('P2 men');

    expect(ShortListChecks::describe(['sport' => 'Judo', 'kind' => 'women', 'count' => 3, 'max' => 2]))
        ->toBe('Judo: 3 female athletes (limit 2)');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP artisan test --compact tests/Feature/GenderLabelsTest.php`
Expected: FAIL — labels are `Men`/`Women`, headers `>Men</th>`, column `P2 men`, describe gives `3 women`.

- [ ] **Step 3: Implement the labels**

`app/Enums/EventGender.php` — replace `label()`:

```php
    public function label(): string
    {
        // Stored values stay men/women/mixed; spec 16.1 shows Male/Female/Mixed everywhere.
        return match ($this) {
            self::Men => 'Male',
            self::Women => 'Female',
            self::Mixed => 'Mixed',
        };
    }
```

`app/Services/Phases/ShortListChecks.php:21`:

```php
    public const KINDS = ['men' => 'male athletes', 'women' => 'female athletes', 'officials' => 'team officials'];
```

`GameSportsRelationManager.php` form fieldsets:

```php
            Fieldset::make('Phase 2 – Entry by Number (participants)')->columns(3)->schema([
                TextInput::make('p2_max_men')->label('Max male')->integer()->minValue(0),
                TextInput::make('p2_max_women')->label('Max female')->integer()->minValue(0),
                TextInput::make('p2_max_total')->label('Max total (male + female)')->integer()->minValue(0),
            ]),
            Fieldset::make('Phase 4 – Short List')->columns(3)->schema([
                TextInput::make('p4_max_men')->label('Max male')->integer()->minValue(0),
                TextInput::make('p4_max_women')->label('Max female')->integer()->minValue(0),
                TextInput::make('p4_max_officials')->label('Max team officials')->integer()->minValue(0),
            ]),
```

and table columns:

```php
                TextColumn::make('p2_max_men')->label('P2 male'),
                TextColumn::make('p2_max_women')->label('P2 female'),
```

`PersonForm.php:109`:

```php
                Select::make('fit')->options(["Men's" => 'Male', "Women's" => 'Female', 'Youth' => 'Youth']),
```

`phase2-entry.blade.php:38` header row:

```blade
                    <tr class="text-left text-gray-500"><th class="py-1">Event</th><th class="py-1">Male</th><th class="py-1">Female</th></tr>
```

`phase4-entry.blade.php:38`:

```blade
                :description="'Limits: male '.($gs->p4_max_men ?? '–').' · female '.($gs->p4_max_women ?? '–').' · team officials '.($gs->p4_max_officials ?? '–')">
```

- [ ] **Step 4: Update existing expectations to the new labels**

- `tests/Feature/GameReportsTest.php:93-94`: `'Men'` → `'Male'`, `'Women'` → `'Female'` (the Gender column; event names `Men -60kg` / `Women -52kg` are data and stay).
- `tests/Feature/LongListChecksTest.php:52`: `'Women -48kg: Ali Hasan (Male) cannot enter a Female event.'`
- `tests/Feature/ShortListChecksTest.php:131`: `'Judo: 2 male athletes (limit 1)'`
- `tests/Feature/Phase4EntryTest.php:101` and `tests/Feature/ShortListAdminTest.php:61`: `'Judo: 2 male athletes (limit 1)'`
- `app/Filament/Resources/Games/Pages/SubmissionView.php:69` docblock: `e.g. "Judo: 2 male athletes (limit 1)"`.

- [ ] **Step 5: Run the tests**

Run: `$PHP artisan test --compact tests/Feature/GenderLabelsTest.php tests/Feature/GameReportsTest.php tests/Feature/LongListChecksTest.php tests/Feature/ShortListChecksTest.php tests/Feature/Phase4EntryTest.php tests/Feature/ShortListAdminTest.php`
Expected: all PASS except `never shows Men or Women in the Entry by Event PDF` may still pass already (the PDF had Male/Female headers); it guards Task 3.

- [ ] **Step 6: Commit**

```bash
$PHP vendor/bin/pint --dirty
git add -A app resources tests
git commit -m "feat: show gender as Male/Female/Mixed everywhere (spec 16.1)"
```

---

### Task 2: Phase 2 participants and expected medals

**Files:**
- Create: `database/migrations/2026_11_13_000100_create_phase2_sport_totals_table.php`
- Create: `app/Models/Phase2SportTotal.php`
- Modify: `app/Models/Submission.php` (add relation after `numbers()`)
- Modify: `app/Services/Phases/Phase2Limits.php` (rewrite)
- Modify: `app/Filament/Shared/Phases/EditsPhase2.php` (rewrite)
- Modify: `app/Services/Phases/SubmissionWorkflow.php` (`assertWithinLimits`, Phase 2 branch)
- Modify: `resources/views/filament/portal/pages/phase2-entry.blade.php` (rewrite)
- Modify tests: `tests/Feature/Phase2LimitsTest.php` (rewrite), `tests/Feature/Phase2EntryTest.php`, `tests/Feature/SubmissionWorkflowTest.php`

**Interfaces:**
- Produces:
  - `App\Models\Phase2SportTotal` — table `phase2_sport_totals`; columns `submission_id`, `game_sport_id`, `male`, `female`, `gold`, `silver`, `bronze` (nullable ints); `const DIGITS = ['male' => 3, 'female' => 3, 'gold' => 2, 'silver' => 2, 'bronze' => 2]`; `const LABELS` (field ⇒ "Male participants" …).
  - `Submission::sportTotals(): HasMany` (Phase2SportTotal).
  - `Phase2Limits::errors(GameSport $gs, array $counts, array $totals = []): array` — `$totals` = `['male' => ?int, 'female' => ?int]`; keys `event.{id}`, `sport.male`, `sport.female`, `sport.total`.
  - Livewire state on Phase 2 pages: `public array $totals` (game_sport_id ⇒ field ⇒ value); error keys `totals.{gsId}.{field}` and `sport.{gsId}.{male|female|total}`.

- [ ] **Step 1: Rewrite the unit test** — `tests/Feature/Phase2LimitsTest.php`

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
    $this->limits = app(Phase2Limits::class);
});

it('limits event counts only by each event maximum', function () {
    // 6 event entries > p2_max_total 4, but the sport limits no longer apply to event sums.
    expect($this->limits->errors($this->gs, [$this->m60->id => 2, $this->m73->id => 2, $this->w52->id => 1, $this->mix->id => 1]))->toBe([]);
});

it('flags an event over its maximum', function () {
    expect($this->limits->errors($this->gs, [$this->m60->id => 3]))
        ->toBe(["event.{$this->m60->id}" => 'Men -60kg: at most 2 athletes.']);
});

it('applies the sport limits to the participant totals', function () {
    expect($this->limits->errors($this->gs, [], ['male' => 4, 'female' => 1]))->toBe([
        'sport.male' => 'Judo: 4 male participants, the limit is 3.',
        'sport.total' => 'Judo: 5 participants in total, the limit is 4.',
    ])->and($this->limits->errors($this->gs, [], ['male' => 1, 'female' => 3]))->toBe([
        'sport.female' => 'Judo: 3 female participants, the limit is 2.',
    ])->and($this->limits->errors($this->gs, [], ['male' => 3, 'female' => 1]))->toBe([]);
});

it('skips blank totals and null limits and rejects negative counts', function () {
    $this->gs->update(['p2_max_men' => null, 'p2_max_total' => null]);
    $this->m60->update(['p2_max_athletes' => null]);

    expect($this->limits->errors($this->gs->fresh(), [$this->m60->id => 40, $this->m73->id => -1], ['male' => 500]))
        ->toBe(["event.{$this->m73->id}" => 'Men -73kg: enter 0 or more.'])
        ->and($this->limits->errors($this->gs->fresh(), [], []))->toBe([]);
});
```

- [ ] **Step 2: Add the Phase 2 page tests** — append to `tests/Feature/Phase2EntryTest.php` (add `use App\Models\Phase2SportTotal;` to the imports)

```php
it('shows how many athletes each event allows', function () {
    livewire(Phase2Entry::class, ['game' => $this->game->id])->assertSee('Up to 1 athlete')->assertDontSee('(max 1)')
        ->assertSee('Total number of participants')->assertSee('Expected medals');
});

it('saves participants and medals with the numbers', function () {
    livewire(Phase2Entry::class, ['game' => $this->game->id])
        ->set("counts.{$this->m60->id}", 1)
        ->set("totals.{$this->judo->id}.male", '2')->set("totals.{$this->judo->id}.female", '0')
        ->set("totals.{$this->judo->id}.gold", '1')
        ->call('save')->assertHasNoErrors();

    expect(Phase2SportTotal::sole()->only(['game_sport_id', 'male', 'female', 'gold', 'silver', 'bronze']))
        ->toBe(['game_sport_id' => $this->judo->id, 'male' => 2, 'female' => 0, 'gold' => 1, 'silver' => null, 'bronze' => null]);
});

it('limits the male participants by the sport maximum, live and on save', function () {
    livewire(Phase2Entry::class, ['game' => $this->game->id])
        ->set("totals.{$this->judo->id}.male", '3')->assertSee('Judo: 3 male participants, the limit is 2.')
        ->call('save')->assertHasErrors(["sport.{$this->judo->id}.male"]);
    expect(Phase2SportTotal::count())->toBe(0);
});

it('no longer limits the sum of event counts by the sport maximum', function () {
    $m66 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id, 'p2_max_athletes' => 3,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -66kg', 'gender' => EventGender::Men])->id]);
    // p2_max_men is 2; 1 + 3 event entries is fine because one athlete can enter several events.
    livewire(Phase2Entry::class, ['game' => $this->game->id])
        ->set("counts.{$this->m60->id}", 1)->set("counts.{$m66->id}", 3)->call('save')->assertHasNoErrors();
});

it('rejects out of range participants and medals', function (string $field, string $bad, string $message) {
    livewire(Phase2Entry::class, ['game' => $this->game->id])
        ->set("totals.{$this->judo->id}.{$field}", $bad)->assertSee($message)
        ->call('save')->assertHasErrors(["totals.{$this->judo->id}.{$field}"]);
    expect(Phase2SportTotal::count())->toBe(0);
})->with([
    ['male', '1000', 'Judo male participants: enter a whole number from 0 to 999.'],
    ['female', '-1', 'Judo female participants: enter a whole number from 0 to 999.'],
    ['gold', '100', 'Judo gold medals: enter a whole number from 0 to 99.'],
    ['bronze', '1.5', 'Judo bronze medals: enter a whole number from 0 to 99.'],
]);

it('requires the participant totals to submit but not to save', function () {
    livewire(Phase2Entry::class, ['game' => $this->game->id])
        ->set("counts.{$this->m60->id}", 1)->call('save')->assertHasNoErrors()
        ->callAction('submit')->assertNotified('Judo: enter the total number of male and female participants.');
    expect(Submission::sole()->status)->toBe(SubmissionStatus::Draft);
});

it('does not save totals for a sport hidden by Phase 1', function () {
    $this->game->phase(1)->update(['enabled' => true]);
    InterestAnswer::create(['submission_id' => Submission::for($this->game, $this->assoc, 1)->id, 'game_sport_id' => $this->judo->id, 'answer' => 'no']);
    livewire(Phase2Entry::class, ['game' => $this->game->id])->set("totals.{$this->judo->id}.male", '1')->call('save');
    expect(Phase2SportTotal::count())->toBe(0);
});
```

And change the existing `submits directly as association admin and generates the PDF job` test to set totals first:

```php
it('submits directly as association admin and generates the PDF job', function () {
    livewire(Phase2Entry::class, ['game' => $this->game->id])->set("counts.{$this->m60->id}", 1)
        ->set("totals.{$this->judo->id}.male", '1')->set("totals.{$this->judo->id}.female", '0')->callAction('submit');
    expect(Submission::sole()->status)->toBe(SubmissionStatus::Submitted);
    Bus::assertDispatched(GenerateEntryByEventPdfs::class);
});
```

- [ ] **Step 3: Update the workflow tests** — `tests/Feature/SubmissionWorkflowTest.php`

Replace the `overLimit()` helper and the two tests that use it for limits:

```php
function overLimit($t): GameSport
{
    $gs = GameSport::factory()->create([
        'game_id' => $t->game->id, 'sport_id' => Sport::factory()->create(['association_id' => $t->assoc->id])->id,
        'p2_max_total' => 1,
    ]);
    $ge = GameEvent::factory()->create(['game_id' => $t->game->id, 'game_sport_id' => $gs->id, 'p2_max_athletes' => null]);
    $t->sub->numbers()->create(['game_event_id' => $ge->id, 'count' => 2]);
    $t->sub->sportTotals()->create(['game_sport_id' => $gs->id, 'male' => 2, 'female' => 0]);

    return $gs;
}

it('passes submit within the Phase 2 limits', function () {
    overLimit($this);
    $this->sub->sportTotals()->update(['male' => 1]); // event count 2 stays: events are not limited by the sport total
    expect($this->wf->submit($this->sub, $this->admin)->status)->toBe(SubmissionStatus::Submitted);
});

it('requires participant totals for every visible sport on submit', function () {
    overLimit($this);
    $this->sub->sportTotals()->update(['female' => null]);
    expect(fn () => $this->wf->submit($this->sub, $this->admin))->toThrow(ValidationException::class);
});
```

(`blocks submit and approve over the Phase 2 limits` and `ignores stored numbers for sports answered No in phase 1` stay unchanged.)

- [ ] **Step 4: Run tests to verify they fail**

Run: `$PHP artisan test --compact tests/Feature/Phase2LimitsTest.php tests/Feature/Phase2EntryTest.php tests/Feature/SubmissionWorkflowTest.php`
Expected: FAIL — `Phase2SportTotal` / `sportTotals()` do not exist.

- [ ] **Step 5: Migration** — `database/migrations/2026_11_13_000100_create_phase2_sport_totals_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phase2_sport_totals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('game_sport_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('male')->nullable();
            $t->unsignedSmallInteger('female')->nullable();
            foreach (['gold', 'silver', 'bronze'] as $c) {
                $t->unsignedTinyInteger($c)->nullable();
            }
            $t->timestamps();
            $t->unique(['submission_id', 'game_sport_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phase2_sport_totals');
    }
};
```

- [ ] **Step 6: Model** — `app/Models/Phase2SportTotal.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Phase 2 participants (distinct athletes) and expected medals per submission and game sport (spec 16.2). */
class Phase2SportTotal extends Model
{
    use LogsActivity;

    /** field => maximum digits: participants 0–999, medals 0–99. */
    public const DIGITS = ['male' => 3, 'female' => 3, 'gold' => 2, 'silver' => 2, 'bronze' => 2];

    public const LABELS = ['male' => 'Male participants', 'female' => 'Female participants',
        'gold' => 'Gold medals', 'silver' => 'Silver medals', 'bronze' => 'Bronze medals'];

    protected $table = 'phase2_sport_totals';

    protected $fillable = ['submission_id', 'game_sport_id', 'male', 'female', 'gold', 'silver', 'bronze'];

    protected function casts(): array
    {
        return ['male' => 'integer', 'female' => 'integer', 'gold' => 'integer', 'silver' => 'integer', 'bronze' => 'integer'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
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

`app/Models/Submission.php`, after `numbers()`:

```php
    public function sportTotals(): HasMany
    {
        return $this->hasMany(Phase2SportTotal::class);
    }
```

- [ ] **Step 7: Re-point the limits** — replace `app/Services/Phases/Phase2Limits.php`

```php
<?php

namespace App\Services\Phases;

use App\Models\GameSport;

final class Phase2Limits
{
    /**
     * Spec 16.2: event counts are limited only by each event's maximum; p2_max_men/women/total limit the participant totals.
     *
     * @param  array<int, int|null>  $counts  game_event_id => count
     * @param  array{male?: int|null, female?: int|null}  $totals  participants entered for the sport; a blank one is not checked
     * @return array<string, string> keyed event.{gameEventId} or sport.{male|female|total}
     */
    public function errors(GameSport $gs, array $counts, array $totals = []): array
    {
        $gs->loadMissing(['sport', 'gameEvents.event']);
        $errors = [];

        foreach ($gs->gameEvents as $ge) {
            $n = (int) ($counts[$ge->id] ?? 0);
            if ($n < 0) {
                $errors["event.{$ge->id}"] = "{$ge->event->name}: enter 0 or more.";

                continue;
            }
            if ($ge->p2_max_athletes !== null && $n > $ge->p2_max_athletes) {
                $errors["event.{$ge->id}"] = "{$ge->event->name}: at most {$ge->p2_max_athletes} athletes.";
            }
        }

        $sport = $gs->sport->name;
        $male = $totals['male'] ?? null;
        $female = $totals['female'] ?? null;
        if ($male !== null && $gs->p2_max_men !== null && $male > $gs->p2_max_men) {
            $errors['sport.male'] = "{$sport}: {$male} male participants, the limit is {$gs->p2_max_men}.";
        }
        if ($female !== null && $gs->p2_max_women !== null && $female > $gs->p2_max_women) {
            $errors['sport.female'] = "{$sport}: {$female} female participants, the limit is {$gs->p2_max_women}.";
        }
        $total = (int) $male + (int) $female;
        if (($male !== null || $female !== null) && $gs->p2_max_total !== null && $total > $gs->p2_max_total) {
            $errors['sport.total'] = "{$sport}: {$total} participants in total, the limit is {$gs->p2_max_total}.";
        }

        return $errors;
    }
}
```

- [ ] **Step 8: Page state, validation and save** — replace `app/Filament/Shared/Phases/EditsPhase2.php`

```php
<?php

namespace App\Filament\Shared\Phases;

use App\Models\Association;
use App\Models\GameSport;
use App\Models\NumberEntry;
use App\Models\Phase2SportTotal;
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

    /** @var array<int|string, array<string, int|string|null>> game_sport_id => Phase2SportTotal::DIGITS field => value */
    public array $totals = [];

    /** @var array<string, string> */
    public array $liveErrors = [];

    abstract protected function association(): Association;

    protected function loadPhase(): void
    {
        $this->counts = $this->submission->numbers()->pluck('count', 'game_event_id')->all();
        $this->totals = $this->submission->sportTotals()->get()
            ->mapWithKeys(fn (Phase2SportTotal $t) => [$t->game_sport_id => $t->only(array_keys(Phase2SportTotal::DIGITS))])->all();
    }

    /** @return Collection<int, GameSport> */
    public function visibleSports(): Collection
    {
        return $this->gameModel->phase2SportsFor($this->association());
    }

    /** @return array<string, string> keyed counts.{gameEventId}, totals.{gameSportId}.{field} or sport.{gameSportId}.{male|female|total} */
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
            $totals = [];
            foreach (Phase2SportTotal::DIGITS as $field => $digits) {
                $raw = trim((string) ($this->totals[$gs->id][$field] ?? ''));
                if ($raw === '') {
                    continue;
                }
                if (! preg_match('/^\d{1,'.$digits.'}$/', $raw)) {
                    $errors["totals.{$gs->id}.{$field}"] = "{$gs->sport->name} ".strtolower(Phase2SportTotal::LABELS[$field])
                        .': enter a whole number from 0 to '.str_repeat('9', $digits).'.';

                    continue;
                }
                $totals[$field] = (int) $raw;
            }
            foreach (app(Phase2Limits::class)->errors($gs, $valid, $totals) as $key => $message) {
                $errors[str_starts_with($key, 'event.') ? 'counts.'.substr($key, 6) : "sport.{$gs->id}.".substr($key, 6)] ??= $message;
            }
        }

        return $errors;
    }

    public function updatedCounts(): void
    {
        $this->liveErrors = $this->limitErrors();
    }

    public function updatedTotals(): void
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
            $sports = $this->visibleSports();
            $allowedEvents = $sports->flatMap->gameEvents->pluck('id')->all();
            $allowedSports = $sports->pluck('id')->all();
            DB::transaction(function () use ($allowedEvents, $allowedSports) {
                $this->claimOrHalt();
                foreach ($this->counts as $gameEventId => $count) {
                    if (in_array((int) $gameEventId, $allowedEvents, true)) {
                        NumberEntry::updateOrCreate(['submission_id' => $this->submission->id, 'game_event_id' => $gameEventId], ['count' => (int) $count]);
                    }
                }
                foreach ($this->totals as $gameSportId => $row) {
                    if (in_array((int) $gameSportId, $allowedSports, true)) {
                        Phase2SportTotal::updateOrCreate(['submission_id' => $this->submission->id, 'game_sport_id' => $gameSportId],
                            collect(Phase2SportTotal::DIGITS)->map(fn ($digits, $field) => blank($row[$field] ?? null) ? null : (int) $row[$field])->all());
                    }
                }
                // lock_version already bumped by claimOrHalt()
            });
        } catch (Halt) {
            return; // notification already sent; loadedVersion unchanged so submit knows nothing was saved
        }
        $this->resetValidation();
        $this->liveErrors = [];
        $this->refreshLoadedAt();
        Notification::make()->success()->title('Saved')->send();
    }
}
```

- [ ] **Step 9: Workflow re-check on submit and approve** — `SubmissionWorkflow::assertWithinLimits()`, replace the Phase 2 tail (from `$counts = ...` to the end of the method):

```php
        $counts = $s->numbers()->pluck('count', 'game_event_id')->all();
        $totals = $s->sportTotals()->get()->keyBy('game_sport_id');
        foreach ($s->game->phase2SportsFor($s->association) as $gs) {
            $t = $totals->get($gs->id);
            if ($t?->male === null || $t->female === null) {
                $this->fail("{$gs->sport->name}: enter the total number of male and female participants.");
            }
            if ($errors = app(Phase2Limits::class)->errors($gs, $counts, ['male' => $t->male, 'female' => $t->female])) {
                $this->fail(reset($errors));
            }
        }
```

- [ ] **Step 10: Rewrite the page** — `resources/views/filament/portal/pages/phase2-entry.blade.php` (the PDF link and the `Save` button are left as they are; Tasks 5 and 6 change them)

```blade
<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Deadline: {{ $this->submission->gamePhase()->effectiveDeadline($this->submission)?->format('j M Y H:i') ?? 'none' }}
    </p>

    @if ($readOnlyReason)
        <div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">{{ $readOnlyReason }}</div>
    @endif

    @php
        $paths = $this->submission->pdf_paths ?? [];
        $submitted = $this->submission->status === \App\Enums\SubmissionStatus::Submitted;
        $men = \App\Enums\EventGender::Men;
        $women = \App\Enums\EventGender::Women;
        $mixed = \App\Enums\EventGender::Mixed;
        $inputClass = 'w-24 rounded border-gray-300 text-sm dark:bg-white/5';
        $msg = fn (string $key) => $errors->first($key) ?: ($liveErrors[$key] ?? null);
    @endphp

    @forelse ($this->visibleSports() as $gs)
        <x-filament::section :heading="$gs->sport->name">
            @if ($gs->entry_policy)
                <p class="text-sm">{{ $gs->entry_policy }}</p>
            @endif
            @if ($gs->dob_from || $gs->dob_to)
                <p class="text-xs text-gray-500">Born {{ $gs->dob_from ? 'from '.$gs->dob_from->format('j M Y') : '' }} {{ $gs->dob_to ? 'to '.$gs->dob_to->format('j M Y') : '' }}</p>
            @endif

            <fieldset class="mt-3" wire:key="participants-{{ $gs->id }}">
                <legend class="text-sm font-medium">Total number of participants</legend>
                <p class="text-xs text-gray-500">Count each athlete once, even if they enter several events.</p>
                <div class="mt-1 flex flex-wrap items-start gap-4">
                    @foreach (['male' => ['Male', $gs->p2_max_men], 'female' => ['Female', $gs->p2_max_women]] as $field => [$label, $max])
                        <div>
                            <label class="text-sm" for="totals-{{ $gs->id }}-{{ $field }}">{{ $label }}@if ($max !== null) <span class="text-xs text-gray-500">(up to {{ $max }})</span>@endif</label>
                            <input id="totals-{{ $gs->id }}-{{ $field }}" type="number" min="0" max="999" class="block {{ $inputClass }}"
                                wire:model.live.debounce.400ms="totals.{{ $gs->id }}.{{ $field }}" @disabled($readOnlyReason)>
                            @if ($m = $msg("totals.{$gs->id}.{$field}"))<p class="text-xs text-danger-600">{{ $m }}</p>@endif
                        </div>
                    @endforeach
                    @if ($gs->p2_max_total !== null)
                        <p class="pt-6 text-xs text-gray-500">Male + female: up to {{ $gs->p2_max_total }}</p>
                    @endif
                </div>
                @foreach (['male', 'female', 'total'] as $k)
                    @if ($m = $msg("sport.{$gs->id}.{$k}"))<p class="mt-1 text-xs text-danger-600">{{ $m }}</p>@endif
                @endforeach
            </fieldset>

            <table class="mt-3 w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500"><th class="py-1">Event</th><th class="py-1">Male</th><th class="py-1">Female</th></tr>
                </thead>
                <tbody>
                    @foreach ($gs->gameEvents as $ge)
                        @php
                            $g = $ge->event->gender;
                            $eMsg = $msg('counts.'.$ge->id);
                        @endphp
                        <tr class="border-t border-gray-200 align-top dark:border-white/10">
                            <td class="py-2">{{ $ge->event->name }}
                                @if ($ge->p2_max_athletes !== null)
                                    <span class="block text-xs text-gray-500">Up to {{ $ge->p2_max_athletes }} {{ \Illuminate\Support\Str::plural('athlete', $ge->p2_max_athletes) }}</span>
                                @endif
                            </td>
                            @if ($g === $mixed)
                                <td colspan="2" class="py-2">
                                    <input type="number" min="0" aria-label="{{ $ge->event->name }} (Mixed)" class="{{ $inputClass }}"
                                        wire:model.live.debounce.400ms="counts.{{ $ge->id }}" @disabled($readOnlyReason)>
                                    <span class="text-xs text-gray-500">Mixed</span>
                                    @if ($eMsg)<p class="text-xs text-danger-600">{{ $eMsg }}</p>@endif
                                </td>
                            @else
                                @foreach ([$men, $women] as $col)
                                    @if ($g === $col)
                                        <td class="py-2">
                                            <input type="number" min="0" aria-label="{{ $ge->event->name }}" class="{{ $inputClass }}"
                                                wire:model.live.debounce.400ms="counts.{{ $ge->id }}" @disabled($readOnlyReason)>
                                            @if ($eMsg)<p class="text-xs text-danger-600">{{ $eMsg }}</p>@endif
                                        </td>
                                    @else
                                        <td class="bg-gray-100 py-2 dark:bg-white/5"></td>
                                    @endif
                                @endforeach
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <fieldset class="mt-4" wire:key="medals-{{ $gs->id }}">
                <legend class="text-sm font-medium">Expected medals <span class="text-xs font-normal text-gray-500">(optional)</span></legend>
                <div class="mt-1 flex flex-wrap items-start gap-4">
                    @foreach (['gold' => 'Gold', 'silver' => 'Silver', 'bronze' => 'Bronze'] as $field => $label)
                        <div>
                            <label class="text-sm" for="totals-{{ $gs->id }}-{{ $field }}">{{ $label }}</label>
                            <input id="totals-{{ $gs->id }}-{{ $field }}" type="number" min="0" max="99" class="block {{ $inputClass }}"
                                wire:model.live.debounce.400ms="totals.{{ $gs->id }}.{{ $field }}" @disabled($readOnlyReason)>
                            @if ($m = $msg("totals.{$gs->id}.{$field}"))<p class="text-xs text-danger-600">{{ $m }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </fieldset>

            @if ($submitted && isset($paths[$gs->id]))
                <a class="mt-3 inline-block text-sm text-primary-600 underline" href="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.private'))->temporaryUrl($paths[$gs->id], now()->addMinutes(5)) }}">Download Entry by Event PDF</a>
            @endif
        </x-filament::section>
    @empty
        <p class="text-sm text-gray-500">No sports to enter for your association in this game.</p>
    @endforelse

    @unless ($readOnlyReason)
        <div><x-filament::button wire:click="save">Save</x-filament::button></div>
    @endunless
    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
```

(The old event-sum footer is removed: the sport limits now apply to the participant totals shown above the grid.)

- [ ] **Step 11: Run the tests**

Run: `$PHP artisan test --compact tests/Feature/Phase2LimitsTest.php tests/Feature/Phase2EntryTest.php tests/Feature/SubmissionWorkflowTest.php tests/Feature/StaSubmissionEditTest.php tests/Feature/GenderLabelsTest.php`
Expected: PASS. Then run the whole suite (`$PHP artisan test --compact`); any other test that submits a Phase 2 entry with a visible sport must create a `sportTotals()` row with `male` and `female` set.

- [ ] **Step 12: Commit**

```bash
$PHP vendor/bin/pint --dirty
git add -A app database resources tests
git commit -m "feat: Phase 2 participants per sport and expected medals; sport limits apply to participants (spec 16.2)"
```

---

### Task 3: Participants and medals in the PDF, submission view and report

**Files:**
- Modify: `app/Jobs/GenerateEntryByEventPdfs.php` (inside `handle()`)
- Modify: `resources/views/pdf/entry-by-event.blade.php` (after the events table)
- Modify: `app/Filament/Resources/Games/Pages/SubmissionView.php` (new method after `phase2Rows()`)
- Modify: `resources/views/filament/resources/games/pages/submission-view.blade.php` (Phase 2 branch)
- Modify: `app/Services/Reports/GameReports.php` (`phase2Totals()`)
- Modify tests: `tests/Feature/EntryByEventPdfTest.php`, `tests/Feature/SubmissionViewTest.php`, `tests/Feature/GameReportsTest.php`

**Interfaces:**
- Consumes: `Submission::sportTotals()`, `Phase2SportTotal::DIGITS` (Task 2).
- Produces: view `pdf.entry-by-event` requires `participants` (`['male' => ?int, 'female' => ?int]`) and `medals` (`['gold' => ?int, 'silver' => ?int, 'bronze' => ?int]`); `SubmissionView::phase2SportTotals(): Collection` of `['sport', 'male', 'female', 'gold', 'silver', 'bronze']`; `GameReports::phase2Totals()` headings `['Association', 'Sport', 'Event', 'Gender', 'Count', 'Male participants', 'Female participants', 'Gold', 'Silver', 'Bronze']`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/EntryByEventPdfTest.php` — in `renders the expected content` add to the view data:

```php
        'participants' => ['male' => 3, 'female' => 0], 'medals' => ['gold' => 1, 'silver' => null, 'bronze' => 2],
```

and extend the expectation chain:

```php
        ->toContain('Total number of participants')->toContain('Expected medals')->toContain('Gold')->toContain('Bronze')
```

and add:

```php
it('passes the sport participants and medals to the view', function () {
    Storage::fake(config('filesystems.private'));
    $assoc = Association::factory()->create(['code' => 'BWF']);
    $game = Game::factory()->create();
    $gs = GameSport::factory()->create(['game_id' => $game->id, 'sport_id' => Sport::factory()->create(['association_id' => $assoc->id])->id]);
    $s = Submission::for($game, $assoc, 2);
    $s->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BWF-G001-P2-R1', 'revision' => 1]);
    $s->sportTotals()->create(['game_sport_id' => $gs->id, 'male' => 4, 'female' => 2, 'gold' => 1]);
    $captured = null;
    view()->composer('pdf.entry-by-event', function ($v) use (&$captured) {
        $captured = $v->getData();
    });

    (new GenerateEntryByEventPdfs($s))->handle();

    expect($captured['participants'])->toBe(['male' => 4, 'female' => 2])
        ->and($captured['medals'])->toBe(['gold' => 1, 'silver' => null, 'bronze' => null]);
});
```

`tests/Feature/SubmissionViewTest.php` — add:

```php
it('shows phase 2 participants and expected medals', function () {
    $p2 = Submission::for($this->game, $this->assoc, 2);
    $p2->sportTotals()->create(['game_sport_id' => $this->m60->game_sport_id, 'male' => 7, 'female' => 5, 'silver' => 2]);
    actingInAdmin($this->viewer);
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $p2])
        ->assertSee('Participants and expected medals')->assertSeeHtml('<td class="py-1">7</td>')->assertSeeHtml('<td class="py-1">5</td>');
});
```

`tests/Feature/GameReportsTest.php` — replace `totals phase 2 numbers per event and per sport`:

```php
it('totals phase 2 numbers per event and per sport with participants and medals', function () {
    Submission::where(['game_id' => $this->game->id, 'association_id' => $this->judoFed->id, 'phase' => 2])->sole()
        ->sportTotals()->create(['game_sport_id' => $this->judo->id, 'male' => 2, 'female' => 0, 'gold' => 1]);

    expect($this->reports->phase2Totals($this->game))->toBe([
        'headings' => ['Association', 'Sport', 'Event', 'Gender', 'Count', 'Male participants', 'Female participants', 'Gold', 'Silver', 'Bronze'],
        'rows' => [
            ['Bahrain Judo Federation', 'Judo', 'Men -60kg', 'Male', 2, null, null, null, null, null],
            ['Bahrain Judo Federation', 'Judo', 'Women -52kg', 'Female', 0, null, null, null, null, null],
            ['Bahrain Judo Federation', 'Judo', 'Total', null, 2, 2, 0, 1, null, null],
        ],
    ]);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `$PHP artisan test --compact tests/Feature/EntryByEventPdfTest.php tests/Feature/SubmissionViewTest.php tests/Feature/GameReportsTest.php`
Expected: FAIL — missing view text, missing `participants` key, method and report columns.

- [ ] **Step 3: PDF job** — `GenerateEntryByEventPdfs::handle()`: after `$counts = ...` add

```php
        $sportTotals = $s->sportTotals()->get()->keyBy('game_sport_id');
```

and in the `view('pdf.entry-by-event', [...])` array add (inside the loop, `$t = $sportTotals->get($gs->id);` just before `$html = ...`):

```php
                'participants' => ['male' => $t?->male, 'female' => $t?->female],
                'medals' => ['gold' => $t?->gold, 'silver' => $t?->silver, 'bronze' => $t?->bronze],
```

- [ ] **Step 4: PDF view** — `resources/views/pdf/entry-by-event.blade.php`, insert after the events `</table>` and before the declaration `<p>`:

```blade
<table class="grid" style="margin-top:8px">
    <tr><th style="text-align:left">Total number of participants</th><th style="width:15%">Male</th><th style="width:15%">Female</th></tr>
    <tr><td>Athletes (each counted once)</td><td class="c">{{ $participants['male'] ?? '–' }}</td><td class="c">{{ $participants['female'] ?? '–' }}</td></tr>
</table>
<table class="grid" style="margin-top:8px">
    <tr><th style="text-align:left">Expected medals</th><th style="width:15%">Gold</th><th style="width:15%">Silver</th><th style="width:15%">Bronze</th></tr>
    <tr><td></td><td class="c">{{ $medals['gold'] ?? '–' }}</td><td class="c">{{ $medals['silver'] ?? '–' }}</td><td class="c">{{ $medals['bronze'] ?? '–' }}</td></tr>
</table>
```

- [ ] **Step 5: Submission view** — `SubmissionView.php`, add `use App\Models\Phase2SportTotal;` and after `phase2Rows()`:

```php
    /** @return Collection<int, array{sport: string, male: ?int, female: ?int, gold: ?int, silver: ?int, bronze: ?int}> */
    public function phase2SportTotals(): Collection
    {
        $totals = $this->submission->sportTotals()->get()->keyBy('game_sport_id');

        return $this->record->phase2SportsFor($this->submission->association)->map(fn ($gs) => ['sport' => $gs->sport->name,
            ...collect(Phase2SportTotal::DIGITS)->map(fn ($digits, $field) => $totals->get($gs->id)?->{$field})->all()]);
    }
```

`submission-view.blade.php`, in the `@elseif ($s->phase === 2)` branch, after the closing `</x-filament::section>` of "Entry by Number":

```blade
        <x-filament::section heading="Participants and expected medals">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="py-1">Sport</th><th class="py-1">Male</th><th class="py-1">Female</th><th class="py-1">Gold</th><th class="py-1">Silver</th><th class="py-1">Bronze</th></tr></thead>
                <tbody>
                    @foreach ($this->phase2SportTotals() as $r)
                        <tr class="border-t border-gray-200 dark:border-white/10"><td class="py-1">{{ $r['sport'] }}</td>@foreach (['male', 'female', 'gold', 'silver', 'bronze'] as $f)<td class="py-1">{{ $r[$f] ?? '–' }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
```

- [ ] **Step 6: Report** — replace `GameReports::phase2Totals()`:

```php
    public function phase2Totals(Game $game): array
    {
        $rows = [];
        foreach ($this->submitted($game, 2, ['numbers', 'sportTotals']) as $s) {
            $counts = $s->numbers->pluck('count', 'game_event_id');
            $totals = $s->sportTotals->keyBy('game_sport_id');
            foreach ($game->phase2SportsFor($s->association) as $gs) {
                $total = 0;
                foreach ($gs->gameEvents->sortBy('event.name') as $ge) {
                    $n = (int) ($counts[$ge->id] ?? 0);
                    $total += $n;
                    $rows[] = [$s->association->name, $gs->sport->name, $ge->event->name, $ge->event->gender->label(), $n, null, null, null, null, null];
                }
                $t = $totals->get($gs->id);
                $rows[] = [$s->association->name, $gs->sport->name, 'Total', null, $total, $t?->male, $t?->female, $t?->gold, $t?->silver, $t?->bronze];
            }
        }

        return ['headings' => ['Association', 'Sport', 'Event', 'Gender', 'Count', 'Male participants', 'Female participants', 'Gold', 'Silver', 'Bronze'], 'rows' => $rows];
    }
```

- [ ] **Step 7: Run the tests**

Run: `$PHP artisan test --compact tests/Feature/EntryByEventPdfTest.php tests/Feature/SubmissionViewTest.php tests/Feature/GameReportsTest.php tests/Feature/GenderLabelsTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
$PHP vendor/bin/pint --dirty
git add -A app resources tests
git commit -m "feat: Phase 2 participants and medals in the Entry by Event PDF, submission view and report"
```

---

### Task 4: Long List views, add people in place, PDF summary

**Files:**
- Modify: `app/Filament/Shared/PersonForm.php:38,50` (signature + `type` default)
- Modify: `app/Filament/Shared/Phases/EditsPhase3.php` (add `registryUrl()`)
- Modify: `app/Filament/Portal/Pages/Phase3Entry.php`
- Modify: `resources/views/filament/portal/pages/phase3-entry.blade.php`
- Modify: `app/Jobs/GenerateLongListPdf.php`, `resources/views/pdf/long-list.blade.php`
- Modify tests: `tests/Feature/Phase3EntryTest.php`, `tests/Feature/LongListPdfTest.php`

**Interfaces:**
- Produces: `PersonForm::configure(Schema $schema, bool $withAssociation = false, PersonType $type = PersonType::Athlete): Schema`; `EditsPhase3::registryUrl(): ?string` (null; `Phase3Entry` returns the portal people index URL); header actions `addAthlete`, `addOfficial` on `Phase3Entry`; view `pdf.long-list` requires `summary` = `['male' => int, 'female' => int, 'officials' => int]`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Phase3EntryTest.php` — add `use App\Enums\PersonType;` and `use App\Filament\Portal\Resources\People\PersonResource;`, then:

```php
it('adds an athlete from the Long List and offers them in the picker straight away', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->callAction('addAthlete', ['first_name' => 'Hamad', 'last_name' => 'Isa', 'dob' => '2004-05-01', 'gender' => 'male',
            'nationality' => 'Bahrain', 'passport_no' => 'BH1112223', 'passport_expiry' => now()->addYears(2)->toDateString()])
        ->assertHasNoActionErrors()
        ->assertSee('Hamad Isa');

    $p = Person::where('first_name', 'Hamad')->sole();
    expect($p->association_id)->toBe($this->assoc->id)->and($p->type)->toBe(PersonType::Athlete)->and($p->code)->toStartWith('BJF-AT-');
});

it('adds a team official with the official form', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->callAction('addOfficial', ['first_name' => 'Nasser', 'last_name' => 'Ali', 'dob' => '1980-05-01', 'gender' => 'male', 'function' => 'Team Manager',
            'nationality' => 'Bahrain', 'passport_no' => 'BH9998887', 'passport_expiry' => now()->addYears(2)->toDateString()])
        ->assertHasNoActionErrors()->assertSee('Nasser Ali – Team Manager');

    expect(Person::where('first_name', 'Nasser')->sole()->type)->toBe(PersonType::Official);
});

it('offers List and Grid views and a link to the registry', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->assertSeeHtml('longListView.'.$this->admin->id)->assertSee('List')->assertSee('Grid')
        ->assertSee('Open registry')->assertSeeHtml(PersonResource::getUrl('index'));
});
```

`tests/Feature/LongListPdfTest.php` — in `renders the header, columns, ...` add `'summary' => ['male' => 1, 'female' => 0, 'officials' => 1],` to the view data and `->toContain('Athletes: 1 (1 male, 0 female)')->toContain('Team officials: 1')` to the expectation; and add:

```php
it('passes a summary of athletes by gender and team officials', function () {
    $captured = null;
    view()->composer('pdf.long-list', function ($v) use (&$captured) {
        $captured = $v->getData();
    });

    (new GenerateLongListPdf($this->sub))->handle();

    expect($captured['summary'])->toBe(['male' => 1, 'female' => 0, 'officials' => 1]);
});
```

(`Person::factory()` defaults to a male athlete — check `database/factories/PersonFactory.php`; if not, set `'gender' => PersonGender::Male` on `$this->ali` in `beforeEach`.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `$PHP artisan test --compact tests/Feature/Phase3EntryTest.php tests/Feature/LongListPdfTest.php`
Expected: FAIL — action `addAthlete` not found; missing text; undefined `$summary`.

- [ ] **Step 3: PersonForm type default** — `app/Filament/Shared/PersonForm.php`

```php
    public static function configure(Schema $schema, bool $withAssociation = false, PersonType $type = PersonType::Athlete): Schema
```

and the `type` select:

```php
                Select::make('type')->options(self::options(PersonType::cases()))->required()->live()->default($type->value),
```

- [ ] **Step 4: Registry URL hook** — `EditsPhase3.php`, add:

```php
    /** Link offered next to the pickers; the portal page overrides it (STA edit pages have no registry link). */
    public function registryUrl(): ?string
    {
        return null;
    }
```

- [ ] **Step 5: Portal page actions** — replace `app/Filament/Portal/Pages/Phase3Entry.php`

```php
<?php

namespace App\Filament\Portal\Pages;

use App\Enums\PersonType;
use App\Filament\Portal\Concerns\HandlesSubmission;
use App\Filament\Portal\Resources\People\PersonResource;
use App\Filament\Shared\Phases\EditsPhase3;
use App\Filament\Shared\PersonForm;
use App\Models\Person;
use Filament\Actions\CreateAction;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

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

    public function registryUrl(): ?string
    {
        return PersonResource::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [$this->addPersonAction(PersonType::Athlete), $this->addPersonAction(PersonType::Official),
            $this->submitAction(), $this->approveAction(), $this->returnAction()];
    }

    /** Spec 16.3: the registry person form, without leaving the page; the new person is in the pickers on the next render. */
    protected function addPersonAction(PersonType $type): CreateAction
    {
        return CreateAction::make($type === PersonType::Athlete ? 'addAthlete' : 'addOfficial')
            ->label($type === PersonType::Athlete ? 'Add athlete' : 'Add team official')
            ->modalHeading($type === PersonType::Athlete ? 'Add athlete' : 'Add team official')
            ->color('gray')->slideOver()->createAnother(false)
            ->visible(fn () => $this->readOnlyReason === null)
            ->model(Person::class)
            ->schema(fn (Schema $schema) => PersonForm::configure($schema, type: $type))
            ->modalSubmitActionLabel('Save as draft')
            // Same as the registry Create page (SavesPersonSizes): sizes are not a column, row and sizes commit together.
            ->using(function (array $data): Person {
                $sizes = $data['sizes'] ?? [];
                unset($data['sizes']);

                return DB::transaction(function () use ($data, $sizes) {
                    $person = Person::create([...$data, 'association_id' => $this->association()->getKey()]);
                    $person->syncSizes($sizes);

                    return $person;
                });
            })
            ->successNotificationTitle('Saved to the registry');
    }
}
```

- [ ] **Step 6: Page view** — `resources/views/filament/portal/pages/phase3-entry.blade.php`

Before `@forelse ($this->visibleSports() as $gs)` add:

```blade
    @if ($url = $this->registryUrl())
        <p class="text-sm"><a href="{{ $url }}" target="_blank" class="text-primary-600 underline">Open registry</a>
            <span class="text-gray-500">to edit people; new people added with the buttons above appear in the lists straight away.</span></p>
    @endif
```

Replace the "People on the Long List" section with:

```blade
    <x-filament::section heading="People on the Long List">
        @php $rows = $this->listRows(); @endphp
        <div x-data="{ view: (() => { try { return localStorage.getItem('longListView.{{ auth()->id() }}') || 'list' } catch (e) { return 'list' } })() }"
             x-init="$watch('view', v => { try { localStorage.setItem('longListView.{{ auth()->id() }}', v) } catch (e) {} })">
            <div class="mb-3 flex gap-2" role="group" aria-label="Long List view">
                <x-filament::button size="xs" color="gray" x-on:click="view = 'list'" x-bind:aria-pressed="view === 'list'">List</x-filament::button>
                <x-filament::button size="xs" color="gray" x-on:click="view = 'grid'" x-bind:aria-pressed="view === 'grid'">Grid</x-filament::button>
            </div>
            <div x-show="view === 'list'">
                @include('filament.shared.long-list-people', ['rows' => $rows, 'showTravel' => ! $readOnlyReason])
            </div>
            <div x-show="view === 'grid'" x-cloak class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($rows as $row)
                    @php $p = $row['person']; @endphp
                    <div wire:key="card-{{ $p->id }}" class="flex gap-3 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        @if ($p->photo_path)
                            <img src="{{ \App\Filament\Shared\AttachmentActions::url($p->photo_path) }}" alt="" class="h-16 w-12 rounded object-cover">
                        @else
                            <div class="h-16 w-12 rounded bg-gray-100 dark:bg-white/5"></div>
                        @endif
                        <div class="min-w-0 text-sm">
                            <div class="font-medium">{{ $p->fullName() }}</div>
                            <div class="text-xs text-gray-500">{{ $p->code }}</div>
                            <div class="text-xs">{{ implode(', ', $row['roles']) }}</div>
                            <x-filament::badge :color="$row['missing'] ? 'danger' : 'success'" size="sm">{{ $row['missing'] ? 'Incomplete' : 'Complete' }}</x-filament::badge>
                            <ul class="text-xs text-warning-600">@foreach ($row['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>
```

- [ ] **Step 7: PDF summary** — `GenerateLongListPdf::handle()`: add `use App\Enums\PersonGender;`, and before `$html = view(...)`:

```php
        $athleteIds = $entries->whereNotNull('game_event_id')->pluck('person_id')->unique();
        $summary = [
            'male' => $athleteIds->filter(fn ($id) => $people[$id]->gender === PersonGender::Male)->count(),
            'female' => $athleteIds->filter(fn ($id) => $people[$id]->gender === PersonGender::Female)->count(),
            'officials' => count($officials),
        ];
```

and add `'summary' => $summary,` to the view data. In `resources/views/pdf/long-list.blade.php`, after the `Reference:` paragraph (line 29) insert:

```blade
<p><strong>Summary</strong> &middot; Athletes: {{ $summary['male'] + $summary['female'] }} ({{ $summary['male'] }} male, {{ $summary['female'] }} female) &middot; Team officials: {{ $summary['officials'] }}</p>
```

(`GenerateShortListPdf` extends this job, so the Short List PDF gets the same summary.)

- [ ] **Step 8: Run the tests**

Run: `$PHP artisan test --compact tests/Feature/Phase3EntryTest.php tests/Feature/LongListPdfTest.php tests/Feature/ShortListPdfTest.php tests/Feature/StaSubmissionEditTest.php tests/Feature/PeopleScreensTest.php`
Expected: PASS. If `callAction('addAthlete', …)` reports a NOT NULL error on a `*_cc` column, the form defaults were not applied — check that the action is mounted without `fillForm()` (CreateAction's default mount calls `$schema->fill()`, which applies `->default()`s).

- [ ] **Step 9: Commit**

```bash
$PHP vendor/bin/pint --dirty
git add -A app resources tests
git commit -m "feat: Long List list/grid views, add athlete/official in place, Open registry, PDF summary (spec 16.3)"
```

---

### Task 5: View action on every attachment

Audit (current state): person uploads are `openable()` + `downloadable()` but Filament's open URL lives to the end of the hour; the people list has `AttachmentActions::group()` (5 min); the insurance certificate is linked only for STA ("Certificate") and the travel modal upload is not openable; the game logo upload is not openable; portal PDF links say "Download" and Phase 2's does not open in a new tab. Dashboard/submission-view PDF links already open a 5-minute URL in a new tab and stay as they are.

**Files:**
- Modify: `app/Filament/Shared/AttachmentActions.php`
- Modify: `app/Filament/Shared/PersonForm.php:147-153` (`upload()`)
- Modify: `app/Filament/Shared/Phases/EditsPhase3.php` (travel `FileUpload`)
- Modify: `app/Filament/Resources/Games/Schemas/GameForm.php` (logo)
- Modify: `resources/views/filament/shared/long-list-people.blade.php`
- Modify: `resources/views/filament/portal/pages/phase{1,2,3,4}-entry.blade.php` (PDF links)
- Modify tests: `tests/Feature/EntryBySportPdfTest.php:83,86,89`, `tests/Feature/LongListPdfTest.php:106`, `tests/Feature/ShortListPdfTest.php:103`, `tests/Feature/Phase3EntryTest.php`, `tests/Feature/PersonCodeAndFilesTest.php`

**Interfaces:**
- Produces: `AttachmentActions::view(FileUpload $upload): FileUpload` — makes the field openable with a 5-minute URL.

- [ ] **Step 1: Write the failing tests**

Rename expectations: `'Download Entry by Sport PDF'` → `'View Entry by Sport PDF'` (three lines), `'Download Long List PDF'` → `'View Long List PDF'`, `'Download Short List PDF'` → `'View Short List PDF'`.

`tests/Feature/Phase3EntryTest.php`, at the end of `saves travel and insurance details with a certificate named after the person`:

```php
    $page->assertSee('View certificate');
```

`tests/Feature/PersonCodeAndFilesTest.php`, add (imports: `App\Filament\Shared\AttachmentActions`, `Filament\Forms\Components\FileUpload`):

```php
it('makes uploads openable through the 5-minute link', function () {
    expect(AttachmentActions::view(FileUpload::make('photo_path'))->isOpenable())->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `$PHP artisan test --compact tests/Feature/EntryBySportPdfTest.php tests/Feature/LongListPdfTest.php tests/Feature/ShortListPdfTest.php tests/Feature/Phase3EntryTest.php tests/Feature/PersonCodeAndFilesTest.php`
Expected: FAIL.

- [ ] **Step 3: Helper** — `AttachmentActions.php`, add `use Filament\Forms\Components\FileUpload;` and:

```php
    /** Spec 16.5: Filament's own open link lasts to the end of the hour; open through the 5-minute URL instead. */
    public static function view(FileUpload $upload): FileUpload
    {
        return $upload->openable()->getOpenableFileUrlUsing(fn (string $file) => self::url($file));
    }
```

- [ ] **Step 4: Use it**

`PersonForm::upload()`:

```php
    private static function upload(string $name, string $label, string $dir, array $types, int $maxKb): FileUpload
    {
        return AttachmentActions::view(FileUpload::make($name)->label($label)
            // image() (if wanted) must come before acceptedFileTypes(), or it widens the list to image/* (SVG).
            ->acceptedFileTypes($types)->maxSize($maxKb)
            ->disk(config('filesystems.private'))->directory($dir)->visibility('private')->downloadable());
    }
```

`EditsPhase3::travelAction()`: wrap the certificate field — `AttachmentActions::view(FileUpload::make('insurance_certificate_path')->label('Insurance certificate') …->preventFilePathTampering(...))` (add `use App\Filament\Shared\AttachmentActions;`).

`GameForm.php` logo: `AttachmentActions::view(FileUpload::make('logo_path')->label('Game logo')->image()->acceptedFileTypes(['image/jpeg', 'image/png'])->maxSize(2048)->disk(config('filesystems.private'))->directory('game-logos')->visibility('private')),` (add the import).

`long-list-people.blade.php`: change the STA link text `Certificate` → `View certificate`, and replace the portal branch

```blade
                            @elseif ($row['detail'])
                                <div class="text-xs text-gray-500">Insurance: {{ $row['detail']->insurance_company ?? '–' }}</div>
                            @endif
```

with

```blade
                            @elseif ($row['detail'])
                                <div class="text-xs text-gray-500">Insurance: {{ $row['detail']->insurance_company ?? '–' }}</div>
                                @if ($row['detail']->insurance_certificate_path)
                                    <a href="{{ \App\Filament\Shared\AttachmentActions::url($row['detail']->insurance_certificate_path) }}" target="_blank" class="text-xs text-primary-600 underline">View certificate</a>
                                @endif
                            @endif
```

Portal PDF links — each becomes `target="_blank"`, `AttachmentActions::url()` and "View":

```blade
{{-- phase1-entry.blade.php --}}
        <a class="text-sm text-primary-600 underline" target="_blank" href="{{ \App\Filament\Shared\AttachmentActions::url($paths['entry_by_sport']) }}">View Entry by Sport PDF</a>
{{-- phase2-entry.blade.php --}}
                <a class="mt-3 inline-block text-sm text-primary-600 underline" target="_blank" href="{{ \App\Filament\Shared\AttachmentActions::url($paths[$gs->id]) }}">View Entry by Event PDF</a>
{{-- phase3-entry.blade.php --}}
        <a class="text-sm text-primary-600 underline" target="_blank" href="{{ \App\Filament\Shared\AttachmentActions::url($paths['long_list']) }}">View Long List PDF</a>
{{-- phase4-entry.blade.php --}}
        <a class="text-sm text-primary-600 underline" target="_blank" href="{{ \App\Filament\Shared\AttachmentActions::url($paths['short_list']) }}">View Short List PDF</a>
```

- [ ] **Step 5: Run the tests**

Run: `$PHP artisan test --compact tests/Feature/EntryBySportPdfTest.php tests/Feature/LongListPdfTest.php tests/Feature/ShortListPdfTest.php tests/Feature/Phase3EntryTest.php tests/Feature/PersonCodeAndFilesTest.php tests/Feature/PersonFormV12Test.php tests/Feature/GameSetupTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
$PHP vendor/bin/pint --dirty
git add -A app resources tests
git commit -m "feat: View action with a 5-minute link on every attachment and PDF (spec 16.5)"
```

---

### Task 6: Save as draft

**Files:**
- Modify: `app/Filament/Portal/Concerns/HandlesSubmission.php` (new `saveDraftAction()`)
- Modify: `app/Filament/Portal/Pages/Phase1Entry.php`, `Phase2Entry.php`, `Phase3Entry.php`, `Phase4Entry.php` (`getHeaderActions()`)
- Modify: `resources/views/filament/portal/pages/phase{1,2,3,4}-entry.blade.php` (bottom button)
- Modify: `app/Filament/Shared/Phases/EditsPhase3.php` (travel modal submit label)
- Modify: `app/Filament/Portal/Resources/People/Pages/CreatePerson.php`, `EditPerson.php`
- Modify tests: `tests/Feature/Phase2EntryTest.php`, `tests/Feature/Phase3EntryTest.php`, `tests/Feature/PeopleScreensTest.php`, `tests/Feature/StaSubmissionEditTest.php`

**Interfaces:**
- Produces: `HandlesSubmission::saveDraftAction(): Action` named `saveDraft`.

- [ ] **Step 1: Write the failing tests**

`Phase2EntryTest.php`:

```php
it('saves as draft from the header without submitting', function () {
    livewire(Phase2Entry::class, ['game' => $this->game->id])->assertSee('Save as draft')
        ->set("counts.{$this->m60->id}", 1)->callAction('saveDraft')->assertNotified('Saved');

    expect(NumberEntry::sole()->count)->toBe(1)->and(Submission::sole()->status)->toBe(SubmissionStatus::Draft);
});
```

`Phase3EntryTest.php`:

```php
it('labels the travel modal button Save as draft', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save')
        ->mountAction('travel', ['person' => $this->ali->id])->assertSee('Save as draft');
});
```

`PeopleScreensTest.php` (portal pages, current user is the editor):

```php
it('labels the person form button Save as draft and saves an incomplete person', function () {
    livewire(CreatePerson::class)->assertSee('Save as draft')->fillForm(athleteData())->call('create')->assertHasNoFormErrors();
    $p = Person::sole();
    expect($p->missingFields())->not->toBeEmpty();
    livewire(EditPerson::class, ['record' => $p->getRouteKey()])->assertSee('Save as draft');
});
```

`StaSubmissionEditTest.php`, in the existing Phase 2 STA edit test (line ~80) chain `->assertSee('Save as draft')` on `$page`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `$PHP artisan test --compact tests/Feature/Phase2EntryTest.php tests/Feature/Phase3EntryTest.php tests/Feature/PeopleScreensTest.php tests/Feature/StaSubmissionEditTest.php`
Expected: FAIL — no `saveDraft` action, labels still "Save"/"Create".

- [ ] **Step 3: Implement**

`HandlesSubmission.php`, before `submitAction()`:

```php
    /** Spec 16.6: Save as draft beside Submit; a draft is never submitted. */
    protected function saveDraftAction(): Action
    {
        return Action::make('saveDraft')->label('Save as draft')->color('gray')
            ->visible(fn () => $this->readOnlyReason === null)
            ->action(fn () => $this->save());
    }
```

Portal pages, prepend to the header actions:

```php
// Phase1Entry, Phase2Entry
        return [$this->saveDraftAction(), $this->submitAction(), $this->approveAction(), $this->returnAction()];
// Phase3Entry
        return [$this->addPersonAction(PersonType::Athlete), $this->addPersonAction(PersonType::Official),
            $this->saveDraftAction(), $this->submitAction(), $this->approveAction(), $this->returnAction()];
// Phase4Entry
        return [$this->saveDraftAction()->visible(fn () => $this->readOnlyReason === null && $this->longListSubmitted()),
            $this->submitAction()->visible(fn () => $this->readOnlyReason === null && $this->longListSubmitted()), $this->approveAction(), $this->returnAction()];
```

The four blades — bottom button (shared with the STA edit pages):

```blade
        <div><x-filament::button wire:click="save">Save as draft</x-filament::button></div>
```

`EditsPhase3::travelAction()`: add `->modalSubmitActionLabel('Save as draft')` after `->modalHeading('Travel & insurance')`.

Portal `CreatePerson.php` (add `use Filament\Actions\Action;`):

```php
    protected function getCreateFormAction(): Action
    {
        // Incomplete people can be saved (spec 13); the Complete/Incomplete badge shows the state (spec 16.6).
        return parent::getCreateFormAction()->label('Save as draft');
    }
```

Portal `EditPerson.php` (add `use Filament\Actions\Action;`):

```php
    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Save as draft');
    }
```

- [ ] **Step 4: Run the tests**

Run: `$PHP artisan test --compact tests/Feature/Phase1EntryTest.php tests/Feature/Phase2EntryTest.php tests/Feature/Phase3EntryTest.php tests/Feature/Phase4EntryTest.php tests/Feature/PeopleScreensTest.php tests/Feature/StaSubmissionEditTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
$PHP vendor/bin/pint --dirty
git add -A app resources tests
git commit -m "feat: Save as draft on phase pages, travel details, person forms and STA edits (spec 16.6)"
```

---

### Task 7: Draft and published games

**Files:**
- Create: `database/migrations/2026_11_13_000200_add_published_at_to_games_table.php`
- Modify: `app/Models/Game.php`, `database/factories/GameFactory.php`
- Modify: `app/Filament/Resources/Games/Pages/EditGame.php`, `Tables/GamesTable.php`, `RelationManagers/PhasesRelationManager.php`
- Modify: `app/Filament/Portal/Pages/Games.php`, `app/Filament/Portal/Concerns/HandlesSubmission.php` (`mountPortal`), `app/Console/Commands/SendPhaseReminders.php`
- Create: `tests/Feature/GamePublishTest.php`

**Interfaces:**
- Produces: `games.published_at` (nullable datetime, fillable, cast); `Game::isPublished(): bool`; `GameFactory` default `published_at = now()` and state `draft()`; `EditGame` header action `publish`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/GamePublishTest.php`

```php
<?php

use App\Enums\PhaseStatus;
use App\Filament\Portal\Pages\Games;
use App\Filament\Portal\Pages\Phase2Entry;
use App\Filament\Resources\Games\Pages\CreateGame;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\Pages\ListGames;
use App\Filament\Resources\Games\RelationManagers\PhasesRelationManager;
use App\Models\Association;
use App\Models\Game;
use App\Models\GameSport;
use App\Models\Sport;
use App\Models\User;
use App\Notifications\PhaseReminder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->draft()->create(['name' => 'GCC Youth Games 2027']);
});

it('creates new games as drafts and keeps factory games published', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(CreateGame::class)->fillForm(['name' => 'New Games', 'start_date' => '2027-04-01', 'end_date' => '2027-04-10', 'reminder_days' => ['7']])
        ->call('create')->assertHasNoFormErrors();

    expect(Game::where('name', 'New Games')->sole()->isPublished())->toBeFalse()
        ->and(Game::factory()->create()->isPublished())->toBeTrue();
});

it('needs an invited association and a sport to publish', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(EditGame::class, ['record' => $this->game->getRouteKey()])->callAction('publish')
        ->assertNotified('Invite at least one association and add at least one sport before publishing.');
    expect($this->game->fresh()->isPublished())->toBeFalse();

    $this->game->associations()->attach($this->assoc);
    GameSport::factory()->create(['game_id' => $this->game->id, 'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id])->id]);
    livewire(EditGame::class, ['record' => $this->game->getRouteKey()])->callAction('publish')->assertNotified('Game published')
        ->assertActionHidden('publish');
    expect($this->game->fresh()->isPublished())->toBeTrue();
});

it('shows a Draft or Published badge in the games list', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    Game::factory()->create(['name' => 'Live Games']);
    livewire(ListGames::class)->assertSee('Draft')->assertSee('Published');
});

it('will not open a phase of a draft game', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $phase = $this->game->phase(2);
    $phase->update(['enabled' => true, 'deadline' => now()->addWeek()]);
    livewire(PhasesRelationManager::class, ['ownerRecord' => $this->game, 'pageClass' => EditGame::class])
        ->assertActionHidden(TestAction::make('open')->table($phase));
});

it('hides a draft game from the association', function () {
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());

    livewire(Games::class)->assertDontSee('GCC Youth Games 2027');
    $this->get(Phase2Entry::getUrl(['game' => $this->game->id], tenant: $this->assoc))->assertNotFound();
});

it('sends no reminders for a draft game', function () {
    Notification::fake();
    $this->game->associations()->attach($this->assoc);
    User::factory()->assocAdmin($this->assoc)->create();
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->subDay()]);

    $this->artisan('phases:send-reminders')->assertSuccessful();
    Notification::assertNotSentTo(User::where('association_id', $this->assoc->id)->get(), PhaseReminder::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `$PHP artisan test --compact tests/Feature/GamePublishTest.php`
Expected: FAIL — `draft()` state / `published_at` column missing.

- [ ] **Step 3: Migration** — `database/migrations/2026_11_13_000200_add_published_at_to_games_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $t) {
            $t->dateTime('published_at')->nullable()->after('reminder_days');
        });
        // Games created before spec 16.6 are live: keep them visible.
        DB::table('games')->update(['published_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $t) {
            $t->dropColumn('published_at');
        });
    }
};
```

- [ ] **Step 4: Model and factory**

`Game.php`:

```php
    protected $fillable = ['name', 'logo_path', 'start_date', 'end_date', 'reminder_days', 'published_at'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'reminder_days' => 'array', 'published_at' => 'datetime'];
    }

    /** Spec 16.6: associations see a game, and its phases can open, only once STA publishes it. */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
```

`GameFactory.php` — add `'published_at' => now(),` to `definition()` and:

```php
    public function draft(): static
    {
        return $this->state(['published_at' => null]);
    }
```

`GameForm` does not include `published_at`, so games created in the admin start as drafts.

- [ ] **Step 5: Admin** — `EditGame.php`:

```php
<?php

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Resources\Games\GameResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditGame extends EditRecord
{
    protected static string $resource = GameResource::class;

    // No delete action: games are never deleted.
    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')->label('Publish')->color('success')->requiresConfirmation()
                ->modalDescription('Invited associations will see this game, and its phases can be opened.')
                ->visible(fn () => ! $this->record->isPublished())
                ->action(function () {
                    if (! $this->record->associations()->exists() || ! $this->record->gameSports()->exists()) {
                        Notification::make()->danger()->title('Invite at least one association and add at least one sport before publishing.')->send();

                        return;
                    }
                    $this->record->update(['published_at' => now()]);
                    Notification::make()->success()->title('Game published')->send();
                }),
        ];
    }
}
```

`GamesTable.php` — after the `name` column (add `use App\Models\Game;`):

```php
                TextColumn::make('published_at')->label('Status')->badge()
                    ->state(fn (Game $record) => $record->isPublished() ? 'Published' : 'Draft')
                    ->color(fn (string $state) => $state === 'Published' ? 'success' : 'gray'),
```

`PhasesRelationManager.php` — the `open` action's `visible`:

```php
                    ->visible(fn (GamePhase $r) => $r->enabled && $r->deadline !== null && $r->status !== PhaseStatus::Open && $r->game->isPublished())
```

- [ ] **Step 6: Association side**

`Games.php` query:

```php
        return Game::whereNotNull('published_at')->whereHas('associations', fn ($q) => $q->whereKey($tenant->getKey()))
```

`HandlesSubmission::mountPortal()`:

```php
        abort_unless($this->gameModel->isPublished() && $this->gameModel->isInvited($tenant) && $this->gameModel->phase($phase)->enabled, 404);
```

`SendPhaseReminders.php` query — add after `->whereNotNull('deadline')`:

```php
            ->whereHas('game', fn ($q) => $q->whereNotNull('published_at'))
```

(`PhaseOpened` is only sent from the Open action, which is now hidden for drafts, so it needs no change.)

- [ ] **Step 7: Run the whole suite**

Run: `$PHP artisan test --compact`
Expected: PASS (factory games are published, so existing tests are unaffected).

- [ ] **Step 8: Commit**

```bash
$PHP vendor/bin/pint --dirty
git add -A app database tests
git commit -m "feat: games are drafts until STA publishes them; drafts hidden from associations (spec 16.6)"
```

---

## Self-review

- **Spec coverage:** 16.1 → Task 1 (+ report/PDF in 3, fit labels). 16.2 per-event text, participants, medals, storage, limits re-pointed, PDF/view/report → Tasks 2–3. 16.3 List/Grid, Add athlete/official, Open registry, Long & Short List PDF summary → Task 4. 16.4 → already built, no task. 16.5 → Task 5. 16.6 phases, travel, person forms, STA edits → Task 6; games draft/publish → Task 7.
- **Placeholders:** none; every code step has code.
- **Names:** `Phase2SportTotal::DIGITS/LABELS`, `Submission::sportTotals()`, `Phase2Limits::errors($gs, $counts, $totals)`, `saveDraftAction()`/`saveDraft`, `addAthlete`/`addOfficial`, `registryUrl()`, `AttachmentActions::view()`, `Game::isPublished()`, `GameFactory::draft()` are used consistently across tasks.
- **Ordering:** Task 2 rewrites the Phase 2 blade keeping the old PDF link and `Save` button; Tasks 5 and 6 change exactly those lines. Task 6 edits `Phase3Entry::getHeaderActions()` created in Task 4.
