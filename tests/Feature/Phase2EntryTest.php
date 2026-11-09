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
    livewire(Phase2Entry::class, ['game' => $this->game->id])->set("counts.{$this->m60->id}", 1)->callAction('submit');
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
    $page->set("counts.{$this->m60->id}", 1)->callAction('submit');

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
