<?php

use App\Enums\EventGender;
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
use App\Services\Phases\SubmissionWorkflow;
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
    $page = livewire(Phase4Entry::class, ['game' => $this->game->id])->assertSee('Judo: 2 male athletes (limit 1)');

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

    $page->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->set("officials.{$this->judo->id}", [])->call('save')
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

it('preloads once on open and the first save succeeds without a stale error', function () {
    $page = livewire(Phase4Entry::class, ['game' => $this->game->id])
        ->assertSet("athletes.{$this->m60->id}", [(string) $this->ali->id, (string) $this->omar->id]);
    expect(phase4()->lock_version)->toBe(1);

    $page->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->set("officials.{$this->judo->id}", [])->call('save')
        ->assertNotNotified('This entry was changed by someone else — reload to see the latest.');
    expect(phase4()->entries()->pluck('person_id')->all())->toBe([$this->ali->id])->and(phase4()->lock_version)->toBe(2);
});

it('refuses save and hides submit while the Long List is a draft, then preloads once it is submitted', function () {
    $this->p3->update(['status' => SubmissionStatus::Draft]);
    $page = livewire(Phase4Entry::class, ['game' => $this->game->id])->assertActionHidden('submit')
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])->call('save')->assertNotified(ShortListChecks::LONG_LIST_FIRST);
    expect(phase4()->entries()->count())->toBe(0)->and(phase4()->lock_version)->toBe(0);

    $this->p3->update(['status' => SubmissionStatus::Submitted]);
    livewire(Phase4Entry::class, ['game' => $this->game->id])
        ->assertSet("athletes.{$this->m60->id}", [(string) $this->ali->id, (string) $this->omar->id]);
    expect(phase4()->entries()->count())->toBe(3);
});

it('preloads after STA extended the Phase 4 deadline before the Long List was submitted', function () {
    $this->p3->update(['status' => SubmissionStatus::Draft]);
    $p4 = Submission::for($this->game, $this->assoc, 4);
    app(SubmissionWorkflow::class)->extend($p4, User::factory()->sta()->create(), now()->addDays(5));
    expect($p4->fresh()->lock_version)->toBeGreaterThan(0);

    $this->p3->update(['status' => SubmissionStatus::Submitted]);
    livewire(Phase4Entry::class, ['game' => $this->game->id])
        ->assertSet("athletes.{$this->m60->id}", [(string) $this->ali->id, (string) $this->omar->id]);
    expect(phase4()->entries()->count())->toBe(3);
});
