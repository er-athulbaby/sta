<?php

use App\Enums\PhaseStatus;
use App\Filament\Portal\Pages\Games;
use App\Filament\Portal\Pages\Home;
use App\Filament\Portal\Pages\Phase1Entry;
use App\Filament\Resources\Games\Pages\StaPhase1Edit;
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
    $this->assoc = Association::factory()->create(['name' => 'Bahrain Weightlifting Federation', 'code' => 'BWF']);
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(6)->addHour()]);
    GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Weightlifting'])->id]);
});

it('tints a phase page and shows chip, status, deadline and stepper', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());

    livewire(Phase1Entry::class, ['game' => $this->game->id])
        ->assertSeeHtml('sta-phase-page')->assertSeeHtml('sta-ph-1')
        ->assertSee('Entry by Sport')->assertSee('Phase 1 of 4 · Open')->assertSee('Draft · saved')
        ->assertSee('6 days left')->assertSeeHtml('aria-current="step"');
});

it('shows game phases as cards on the Games page and on the portal home', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());

    livewire(Games::class)->assertSeeHtml('sta-phase-card')->assertSee('Entry by Sport')->assertSee('No response')
        ->assertSeeHtml(Phase1Entry::getUrl(['game' => $this->game->id]));
    livewire(Home::class)->assertSee('Asian Indoor Games')->assertSeeHtml('sta-phase-card');
});

it('names the association in the STA edit heading', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $s = Submission::for($this->game, $this->assoc, 1);

    livewire(StaPhase1Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s])
        ->assertSee('Edit Bahrain Weightlifting Federation · Entry by Sport')->assertSeeHtml('sta-ph-1');
});
