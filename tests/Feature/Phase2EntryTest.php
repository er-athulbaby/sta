<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Phase1Entry;
use App\Filament\Portal\Pages\Phase2Entry;
use App\Jobs\GenerateEntryByEventPdfs;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\InterestAnswer;
use App\Models\NumberEntry;
use App\Models\Phase2SportTotal;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

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

it('shows limit messages live while typing', function () {
    livewire(Phase2Entry::class, ['game' => $this->game->id])
        ->set("counts.{$this->m60->id}", 2)->assertSee('Men -60kg: at most 1 athletes.');
    expect(NumberEntry::count())->toBe(0);
});

it('submits directly as association admin and generates the PDF job', function () {
    livewire(Phase2Entry::class, ['game' => $this->game->id])->set("counts.{$this->m60->id}", 1)
        ->set("totals.{$this->judo->id}.male", '1')->set("totals.{$this->judo->id}.female", '0')->callAction('submit');
    expect(Submission::sole()->status)->toBe(SubmissionStatus::Submitted);
    Bus::assertDispatched(GenerateEntryByEventPdfs::class);
});

it('hides sports answered No in phase 1', function () {
    $this->game->phase(1)->update(['enabled' => true]);
    $p1 = Submission::for($this->game, $this->assoc, 1);
    InterestAnswer::create(['submission_id' => $p1->id, 'game_sport_id' => $this->judo->id, 'answer' => 'no']);

    livewire(Phase2Entry::class, ['game' => $this->game->id])->assertDontSee('Men -60kg');
});

it('leaves the submission draft and writes nothing when a stale save happens during submit', function () {
    $page = livewire(Phase2Entry::class, ['game' => $this->game->id]);
    $s = Submission::sole();
    $s->claimVersion($s->lock_version);
    $page->set("counts.{$this->m60->id}", 1)->set("totals.{$this->judo->id}.male", '1')->set("totals.{$this->judo->id}.female", '0')->callAction('submit');

    expect($s->fresh()->status)->toBe(SubmissionStatus::Draft)->and(NumberEntry::count())->toBe(0);
});

it('locks the shared version and read-only properties against client tampering', function () {
    livewire(Phase2Entry::class, ['game' => $this->game->id])->set('loadedVersion', 99);
})->throws(CannotUpdateLockedPropertyException::class);

it('groups phase 1 radios per sport with an accessible label', function () {
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    livewire(Phase1Entry::class, ['game' => $this->game->id])
        ->assertSeeHtml('role="radiogroup"')->assertSeeHtml('aria-label="Judo"')->assertSeeHtml('name="answer-'.$this->judo->id.'"');
});

it('rejects non whole-number or out of range counts and stores nothing', function (string $bad) {
    livewire(Phase2Entry::class, ['game' => $this->game->id])
        ->set("counts.{$this->m60->id}", $bad)->assertSee('Men -60kg: enter a whole number from 0 to 999.')
        ->call('save')->assertHasErrors(["counts.{$this->m60->id}"]);
    expect(NumberEntry::count())->toBe(0);
})->with(['1e9', 'abc', '1.5', '1000']);

it('accepts 999 as a whole number when no limit applies', function () {
    $this->m60->update(['p2_max_athletes' => null]);
    $this->judo->update(['p2_max_men' => null]);
    livewire(Phase2Entry::class, ['game' => $this->game->id])->set("counts.{$this->m60->id}", '999')->call('save')->assertHasNoErrors();
    expect(NumberEntry::sole()->count)->toBe(999);
});

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
        ->set("totals.{$this->judo->id}.male", '3')->assertSee('Over the limit: Male 3 of max 2')
        ->call('save')->assertHasErrors(["sport.{$this->judo->id}.male"]);
    expect(Phase2SportTotal::count())->toBe(0);
});

it('words every over-limit message with Male and Female, never Men or Women', function () {
    $this->judo->update(['p2_max_women' => 1, 'p2_max_total' => 2]);
    $html = livewire(Phase2Entry::class, ['game' => $this->game->id])
        ->set("totals.{$this->judo->id}.male", '3')->set("totals.{$this->judo->id}.female", '2')
        ->assertSee('Over the limit: Male 3 of max 2 · Female 2 of max 1 · Total 5 of max 2')
        ->html();
    preg_match_all('/Over the limit: [^<]*/', $html, $found);
    expect($found[0])->toHaveCount(1)
        ->and($found[0][0])->not->toMatch("/men's|women's|\b(men|women)\b/i");
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

it('saves as draft from the header without submitting', function () {
    livewire(Phase2Entry::class, ['game' => $this->game->id])->assertSee('Save as draft')
        ->set("counts.{$this->m60->id}", 1)->callAction('saveDraft')->assertNotified('Saved');

    expect(NumberEntry::sole()->count)->toBe(1)->and(Submission::sole()->status)->toBe(SubmissionStatus::Draft);
});
