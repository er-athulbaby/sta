<?php

use App\Enums\InterestAnswerValue;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Games;
use App\Filament\Portal\Pages\Phase1Entry;
use App\Models\Association;
use App\Models\Game;
use App\Models\GameSport;
use App\Models\InterestAnswer;
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
    expect(InterestAnswer::count())->toBe(0);
});

it('returns 404 for a game the association is not invited to', function () {
    $other = Game::factory()->create();
    $this->get(Phase1Entry::getUrl(['game' => $other->id], tenant: $this->assoc))->assertNotFound();
});

it('refuses a stale save', function () {
    $page = livewire(Phase1Entry::class, ['game' => $this->game->id]);
    $s = Submission::sole();
    $s->claimVersion($s->lock_version);
    $page->set("answers.{$this->wl->id}", 'no')->call('save')->assertNotified('This entry was changed by someone else — reload to see the latest.');
    expect(InterestAnswer::count())->toBe(0);
});
