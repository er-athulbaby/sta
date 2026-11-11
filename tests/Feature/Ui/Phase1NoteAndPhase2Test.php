<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Filament\Portal\Pages\Phase1Entry;
use App\Filament\Portal\Pages\Phase2Entry;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\InterestAnswer;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    $this->assoc = Association::factory()->create(['code' => 'BWF']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id, 'p2_max_men' => 2,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
});

it('saves an optional note with each Phase 1 answer', function () {
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);

    livewire(Phase1Entry::class, ['game' => $this->game->id])
        ->set("answers.{$this->judo->id}", 'maybe')->set("notes.{$this->judo->id}", 'Depends on the national championship')
        ->call('save')->assertHasNoErrors();

    expect(InterestAnswer::sole()->note)->toBe('Depends on the national championship');
    livewire(Phase1Entry::class, ['game' => $this->game->id])
        ->assertSet("notes.{$this->judo->id}", 'Depends on the national championship')
        ->assertSeeHtml('role="radiogroup"')->assertSeeHtml('name="answer-'.$this->judo->id.'"');
});

it('refuses a Phase 1 note longer than 255 characters', function () {
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);

    livewire(Phase1Entry::class, ['game' => $this->game->id])
        ->set("answers.{$this->judo->id}", 'yes')->set("notes.{$this->judo->id}", str_repeat('a', 256))
        ->call('save')->assertHasErrors(["notes.{$this->judo->id}"]);

    expect(InterestAnswer::count())->toBe(0);
});

it('lays out Phase 2 with participant meters, event entry totals and the policy card', function () {
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->judo->update(['entry_policy' => 'One athlete per weight.']);
    GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id, 'p2_max_athletes' => 2,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => '-60kg', 'gender' => EventGender::Men])->id]);

    livewire(Phase2Entry::class, ['game' => $this->game->id])
        ->set("totals.{$this->judo->id}.male", '3')
        ->assertSee('Judo limits')->assertSee('Male participants')->assertSee('3 of 2')->assertSeeHtml('is-over')
        ->assertSee('Event entries')->assertSee('Entry policy')->assertSee('One athlete per weight.')
        ->assertSee('Up to 2 athletes')->assertSeeHtml('>Male</th>')->assertSeeHtml('>Female</th>');
});
